param([Parameter(Mandatory=$true)][ValidatePattern('^alynt-closed-entry-input-proof-[a-f0-9]{12}\.php$')][string]$FixtureName)
$ErrorActionPreference = 'Stop'
# Exact confirmed local-only operator command. Never imported or run by CI.
$targetRoot = 'C:\Users\Captain\Local Sites\plugin-tester\app\public'
$fixturePath = [IO.Path]::GetFullPath((Join-Path $targetRoot $FixtureName))
if ((Split-Path -Parent $fixturePath) -cne $targetRoot) { throw 'Wrong fixture scope.' }
$expectedStub = "<?php`nrequire 'C:/Development/WordPress/Plugins/alynt-drime-backups-uploader/scripts/tests/closed-entry-http-probe.php';`n"
if (!(Test-Path -LiteralPath $fixturePath) -or (Get-Content -Raw -LiteralPath $fixturePath).Replace("`r`n","`n") -cne $expectedStub) { throw 'Owned fixture identity mismatch.' }
$sites = Get-Content -Raw -LiteralPath 'C:\Users\Captain\AppData\Roaming\Local\sites.json' | ConvertFrom-Json
$site = @($sites.PSObject.Properties.Value | Where-Object domain -CEQ 'plugin-tester.local')
if ($site.Count -ne 1 -or [IO.Path]::GetFullPath(($site[0].path + '/app/public')) -cne $targetRoot -or $site[0].services.mariadb.ports.MYSQL[0] -ne 10004 -or $site[0].services.nginx.ports.HTTP[0] -ne 10005) { throw 'Local metadata mismatch.' }
$services = 'C:\Users\Captain\AppData\Roaming\Local\lightning-services'
$mysql = @(Get-ChildItem -LiteralPath $services -Directory | Where-Object Name -Like ('mariadb-' + $site[0].services.mariadb.version + '+*') | Get-ChildItem -Recurse -Filter mysql.exe)
$php = @(Get-ChildItem -LiteralPath $services -Directory | Where-Object Name -Like ('php-' + $site[0].services.php.version + '+*') | Get-ChildItem -Recurse -Filter php.exe)
if ($mysql.Count -ne 1 -or $php.Count -ne 1 -or !$env:MYSQL_PWD) { throw 'Resolve exact bundled runtime and local password source.' }
function Invoke-EntryAuditSql([string]$Sql) {
    $start = [Diagnostics.ProcessStartInfo]::new(); $start.FileName = $mysql[0].FullName
    $start.UseShellExecute = $false; $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true; $start.RedirectStandardError = $true; $start.RedirectStandardInput = $true
    $start.Arguments = '-h 127.0.0.1 -P 10004 -u root -D local -N -B -r --connect-timeout=3'
    $process = [Diagnostics.Process]::new(); $process.StartInfo = $start
    try {
        if (!$process.Start()) { throw 'Independent SQL start failed.' }
        $stdout = $process.StandardOutput.ReadToEndAsync(); $stderr = $process.StandardError.ReadToEndAsync()
        $process.StandardInput.WriteLine($Sql + ';'); $process.StandardInput.Close()
        if (!$process.WaitForExit(30000)) { $process.Kill(); $process.WaitForExit(); throw 'Independent audit timed out.' }
        $out = $stdout.GetAwaiter().GetResult(); $err = $stderr.GetAwaiter().GetResult()
        if ($process.ExitCode -ne 0 -or $err.Length -ne 0) { throw 'Independent SQL refused; raw details withheld.' }
        return @($out.TrimEnd("`r","`n") -split "`r?`n" | Where-Object { $_ -ne '' })
    } finally { $process.Dispose() }
}
function Get-EntryAuditSnapshot {
    $tables = @(Invoke-EntryAuditSql 'SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name')
    if ($tables.Count -lt 3 -or @($tables | Where-Object { $_ -cnotmatch '^[A-Za-z0-9_]+$' }).Count -ne 0) { throw 'Invalid application inventory.' }
    $rows = @(Invoke-EntryAuditSql ('CHECKSUM TABLE ' + ($tables -join ',') + ' EXTENDED')); $state = @{}
    foreach ($row in $rows) {
        if ($row -cnotmatch '^local\.([A-Za-z0-9_]+)\t([0-9]+)$') { throw 'Checksum unavailable.' }
        if ($state.ContainsKey($Matches[1])) { throw 'Duplicate checksum.' }; $state[$Matches[1]] = $Matches[2]
    }
    if ($state.Count -ne $tables.Count) { throw 'Incomplete snapshot.' }; return $state
}
$receipt = [ordered]@{ completed = $false; ingress_enabled = $false; native_execution = $false }
$before = $null; $proofExit = 1
try {
    $url = @(Invoke-EntryAuditSql "SELECT option_value FROM wp_options WHERE option_name='siteurl'")
    if ($url.Count -ne 1 -or $url[0] -cne 'http://plugin-tester.local') { throw 'Wrong local database.' }
    $before = Get-EntryAuditSnapshot
    if (@($before.Keys | Where-Object { $_ -cmatch '^wp_alynt_(entry|core)_probe_' }).Count -ne 0) { throw 'Preexisting owned fixtures require review.' }
    $httpOut = @(& 'C:\Program Files\nodejs\node.exe' ($PSScriptRoot + '/closed-entry-http-profile.mjs') $FixtureName 2>&1)
    $httpExit = $LASTEXITCODE; $http = ($httpOut -join "`n") | ConvertFrom-Json
    if ($httpExit -ne 0 -or !$http.completed) { throw 'Input-profile observation did not complete.' }
    $receipt.http_profile = $http
    $phpArgs = @('-d', ('extension_dir=' + $php[0].DirectoryName + '/ext'), '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0')
    $proofOut = @(& $php[0].FullName @phpArgs ($PSScriptRoot + '/closed-entry-owned-proof.php') $targetRoot 10004 2>&1)
    $proofExit = $LASTEXITCODE; $receipt.auth_helpers = ($proofOut -join "`n") | ConvertFrom-Json
} catch { $receipt.failure = 'Owned proof/audit refused; private diagnostics withheld.' }
finally {
    # Exact resolved path + unchanged owned stub, never a glob or recursive delete.
    if ((Test-Path -LiteralPath $fixturePath) -and (Get-Content -Raw -LiteralPath $fixturePath).Replace("`r`n","`n") -ceq $expectedStub) {
        $receipt.http_fixture_bytes_previewed = (Get-Item -LiteralPath $fixturePath).Length
        Remove-Item -LiteralPath $fixturePath
    }
    $receipt.http_fixture_removed = !(Test-Path -LiteralPath $fixturePath)
    if ($null -ne $before) {
        $after = Get-EntryAuditSnapshot; $same = $before.Count -eq $after.Count
        foreach ($table in $before.Keys) { $same = $same -and $after.ContainsKey($table) -and $before[$table] -ceq $after[$table] }
        $receipt.application_table_count = $before.Count; $receipt.all_application_checksums_unchanged = $same
        $receipt.no_owned_fixture_tables = @($after.Keys | Where-Object { $_ -cmatch '^wp_alynt_entry_probe_' }).Count -eq 0
    }
}
$receipt.completed = $proofExit -eq 0 -and $receipt.http_fixture_removed -and $receipt.all_application_checksums_unchanged -and $receipt.no_owned_fixture_tables
$receipt | ConvertTo-Json -Depth 12
if (!$receipt.completed) { exit 1 }
