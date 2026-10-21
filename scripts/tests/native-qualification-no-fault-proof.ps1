$ErrorActionPreference = 'Stop'
# Exact approved owner only. The fixed Node program cannot select response loss.
$proofNode = 'C:\Program Files\nodejs\node.exe'
$proofRoot = 'C:\Users\Captain\Local Sites\plugin-tester\app\public'
$proofSites = Get-Content -Raw -LiteralPath 'C:\Users\Captain\AppData\Roaming\Local\sites.json' | ConvertFrom-Json
$proofSite = @($proofSites.PSObject.Properties.Value | Where-Object { $_.domain -eq 'plugin-tester.local' })
if ($proofSite.Count -ne 1 -or [IO.Path]::GetFullPath($proofSite[0].path + '/app/public') -ne $proofRoot -or [int]$proofSite[0].services.mariadb.ports.MYSQL[0] -ne 10004 -or -not $env:MYSQL_PWD) { throw 'Exact confirmed local scope or credential source mismatch.' }
function Start-NativeQualificationNode([string]$Script, [bool]$Confirmed) {
    if ($Script -notin @('selected-native-tree-proof.mjs','native-qualification-no-fault-proof.mjs')) { throw 'Fixed owned child only.' }
    $proofInfo = [Diagnostics.ProcessStartInfo]::new()
    $proofInfo.FileName = $proofNode; $proofInfo.UseShellExecute = $false; $proofInfo.CreateNoWindow = $true
    $proofInfo.RedirectStandardInput = $true; $proofInfo.RedirectStandardOutput = $true; $proofInfo.RedirectStandardError = $true
    $proofInfo.ArgumentList.Add(($PSScriptRoot + '/' + $Script)); if ($Confirmed) { $proofInfo.ArgumentList.Add('--confirmed-plugin-tester') }
    $proofChild = [Diagnostics.Process]::new(); $proofChild.StartInfo = $proofInfo
    if (-not $proofChild.Start()) { throw 'Owned Node start refused.' }; $proofChild.StandardInput.Close(); return $proofChild
}
$proofTree = Start-NativeQualificationNode 'selected-native-tree-proof.mjs' $false; $proofDescendant = $null
try {
    $proofLine = $proofTree.StandardOutput.ReadLineAsync(); $proofErrors = $proofTree.StandardError.ReadToEndAsync()
    if (-not $proofLine.Wait(3000)) { throw 'Owned tree readiness unresolved.' }; $proofReady = $proofLine.Result | ConvertFrom-Json
    if ($proofReady.kind -ne 'owned_tree_ready' -or $proofReady.child_pid -isnot [long] -and $proofReady.child_pid -isnot [int]) { throw 'Owned child identity refused.' }
    $proofDescendant = [Diagnostics.Process]::GetProcessById([int]$proofReady.child_pid); $proofStart = $proofDescendant.StartTime
    if ($proofDescendant.HasExited -or $proofTree.HasExited -or $proofStart -lt $proofTree.StartTime) { throw 'Owned tree was not live.' }
    $proofTree.Kill($true)
    if (-not $proofTree.WaitForExit(3000) -or -not $proofDescendant.WaitForExit(3000) -or -not $proofErrors.Wait(1000) -or $proofErrors.Result.Length -ne 0) { throw 'Owned tree cleanup unresolved.' }
    [ordered]@{ stage='native_qualification_owned_tree_prerequisite'; passed=$true; owned_root_closed=$true; owned_descendant_closed=$true; wordpress_or_sql=$false } | ConvertTo-Json -Compress
} finally { if (-not $proofTree.HasExited) { $proofTree.Kill($true); [void]$proofTree.WaitForExit(3000) }; if ($null -ne $proofDescendant) { $proofDescendant.Dispose() }; $proofTree.Dispose() }
$proofOwner = Start-NativeQualificationNode 'native-qualification-no-fault-proof.mjs' $true
try {
    $proofOut = $proofOwner.StandardOutput.ReadToEndAsync(); $proofErr = $proofOwner.StandardError.ReadToEndAsync()
    if (-not $proofOwner.WaitForExit(90000)) { $proofOwner.Kill($true); [void]$proofOwner.WaitForExit(3000); throw 'Owned watchdog stopped; fixtures require independent review.' }
    if (-not $proofOut.Wait(1000) -or -not $proofErr.Wait(1000) -or $proofErr.Result.Length -ne 0 -or $proofOut.Result.Length -gt 65536) { throw 'Owned pipe closure/output refused; private diagnostics withheld.' }
    $proofOut.Result; exit $proofOwner.ExitCode
} finally { if (-not $proofOwner.HasExited) { $proofOwner.Kill($true); [void]$proofOwner.WaitForExit(3000) }; $proofOwner.Dispose() }
