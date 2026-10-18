param([string]$WordPressRoot = 'C:/Users/Captain/Local Sites/plugin-tester/app/public', [ValidateSet('guard','native-observation','native-closed-begin','native-paired-completion','native-scan-observation','native-scan-ledger','registration-construction')][string]$Proof = 'guard')
$ErrorActionPreference = 'Stop'
# Explicit local-only operator entry point, never a CI/database task.
$approvedRoot = [IO.Path]::GetFullPath('C:/Users/Captain/Local Sites/plugin-tester/app/public')
if ([IO.Path]::GetFullPath($WordPressRoot) -ne $approvedRoot) { throw 'Wrong proof target.' }
$sites = Get-Content -LiteralPath 'C:/Users/Captain/AppData/Roaming/Local/sites.json' -Raw | ConvertFrom-Json
$site = @($sites.PSObject.Properties.Value | Where-Object { $_.domain -eq 'plugin-tester.local' })
if ($site.Count -ne 1 -or [IO.Path]::GetFullPath(($site[0].path + '/app/public')) -ne $approvedRoot) { throw 'Target metadata mismatch.' }
$serviceRoot = 'C:/Users/Captain/AppData/Roaming/Local/lightning-services'
$dbVersion = $site[0].services.mariadb.version
$phpVersion = $site[0].services.php.version
$mysql = @(Get-ChildItem -LiteralPath $serviceRoot -Directory | Where-Object Name -Like "mariadb-$dbVersion+*" | Get-ChildItem -Recurse -Filter mysql.exe)
$php = @(Get-ChildItem -LiteralPath $serviceRoot -Directory | Where-Object Name -Like "php-$phpVersion+*" | Get-ChildItem -Recurse -Filter php.exe)
if ($mysql.Count -ne 1 -or $php.Count -ne 1) { throw 'Resolve a unique matching LocalWP runtime.' }
$port = [int]$site[0].services.mariadb.ports.MYSQL[0]
$config = Get-Content -LiteralPath ($approvedRoot + '/wp-config.php')
$prefixLine = @($config | Where-Object { $_ -match '^\s*\$table_prefix\s*=' })
if ($prefixLine.Count -ne 1 -or $prefixLine[0] -notmatch "=\s*'([A-Za-z0-9_]+)'\s*;") { throw 'Cannot resolve actual table prefix.' }
$actualPrefix = $Matches[1]
if ($actualPrefix -ne 'wp_') { throw 'This proof supports only the inspected Plugin Tester prefix.' }
$priorPassword = $env:MYSQL_PWD
if ([string]::IsNullOrEmpty($priorPassword)) { throw 'Set MYSQL_PWD from the confirmed local secret source before running this proof.' }
function Invoke-GuardAuditSql([string]$Sql) {
    # Capture both streams to completion. Fast repeated Windows native pipeline
    # invocations intermittently yielded no rows despite an exit code of zero.
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $mysql[0].FullName
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.RedirectStandardInput = $true
    # Only the metadata-validated integer is interpolated in process arguments;
    # SQL travels through stdin, not shell parsing or command-line quoting.
    $start.Arguments = "-h 127.0.0.1 -P $port -u root -D local -N -B -r --connect-timeout=5"
    $process = [Diagnostics.Process]::new()
    $process.StartInfo = $start
    try {
        if (-not $process.Start()) { throw 'Independent MySQL inspection could not start.' }
        $stdout = $process.StandardOutput.ReadToEndAsync()
        $stderr = $process.StandardError.ReadToEndAsync()
        $process.StandardInput.WriteLine($Sql + ';')
        $process.StandardInput.Close()
        if (-not $process.WaitForExit(30000)) { $process.Kill(); $process.WaitForExit(); throw 'Independent MySQL inspection timed out.' }
        $text = $stdout.GetAwaiter().GetResult()
        $errorText = $stderr.GetAwaiter().GetResult()
        if ($process.ExitCode -ne 0 -or $errorText.Length -ne 0) { throw 'Independent MySQL inspection failed; raw diagnostics withheld.' }
        if ($text.Length -eq 0) { return @() }
        return $text.TrimEnd("`r","`n") -split "`r?`n"
    } finally { $process.Dispose() }
}
function Get-GuardAuditSnapshot {
    $tables = @(Invoke-GuardAuditSql 'SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name')
    if ($tables.Count -lt 3) { throw 'Incomplete application inventory.' }
    $state = @{}
    foreach ($table in $tables) {
        if ($table -cnotmatch '^[A-Za-z0-9_]+$') { throw 'Unsafe table identifier.' }
    }
    # One server statement keeps the same full inventory gate without 77 rapid
    # client launches. Every returned table/value is still strictly reconciled.
    $rows = @(Invoke-GuardAuditSql ('CHECKSUM TABLE ' + ($tables -join ',') + ' EXTENDED'))
    if ($rows.Count -ne $tables.Count) { throw 'Incomplete application checksum inventory; proof not admitted.' }
    foreach ($row in $rows) {
        if ($row -cnotmatch '^local\.([A-Za-z0-9_]+)\t([0-9]+)$') { throw 'Application checksum unavailable; proof not admitted.' }
        $table = $Matches[1]; $checksum = $Matches[2]
        if ($table -cnotin $tables -or $state.ContainsKey($table)) { throw 'Unknown or duplicate application checksum row.' }
        $state[$table] = $checksum
    }
    return $state
}
try {
    $url = @(Invoke-GuardAuditSql "SELECT option_value FROM wp_options WHERE option_name='siteurl'")
    if ($url.Count -ne 1 -or $url[0] -cne 'http://plugin-tester.local') { throw 'Independent database target mismatch.' }
    $before = Get-GuardAuditSnapshot
    if (@($before.Keys | Where-Object { $_ -cmatch '^wp_alynt_core_probe_' }).Count -ne 0) { throw 'Pre-existing guard fixtures need independent review; not deleting them.' }
    $phpArgs = @('-d', ('extension_dir=' + $php[0].DirectoryName + '/ext'), '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0')
    $output = @(& $php[0].FullName @phpArgs ($PSScriptRoot + '/fixed-core-local-proof.php') $approvedRoot $port $Proof 2>&1)
    $proofExit = $LASTEXITCODE
    $after = Get-GuardAuditSnapshot
    $same = $before.Count -eq $after.Count
    foreach ($table in $before.Keys) { $same = $same -and $after.ContainsKey($table) -and $before[$table] -ceq $after[$table] }
    $receipt = $null
    try { $receipt = ($output -join "`n") | ConvertFrom-Json } catch { $receipt = @{ failed = $true; invalid_worker_receipt = $true } }
    [ordered]@{ proof = $receipt; independent_mysql_audit = @{ application_table_count = $before.Count; all_application_checksums_unchanged = $same; no_guard_fixture_tables = @($after.Keys | Where-Object { $_ -cmatch '^wp_alynt_core_probe_' }).Count -eq 0 }; proof_exit_code = $proofExit } | ConvertTo-Json -Depth 12
    if ($proofExit -ne 0 -or -not $same -or $receipt.failed) { exit 1 }
} finally { $env:MYSQL_PWD = $priorPassword }
