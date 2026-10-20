$ErrorActionPreference = 'Stop'
# Exact confirmed Plugin Tester owner; never CI, deploy or a general command runner.
$taskNode = 'C:\Program Files\nodejs\node.exe'
$taskRoot = 'C:\Users\Captain\Local Sites\plugin-tester\app\public'
$taskSites = Get-Content -Raw -LiteralPath 'C:\Users\Captain\AppData\Roaming\Local\sites.json' | ConvertFrom-Json
$taskSite = @($taskSites.PSObject.Properties.Value | Where-Object { $_.domain -eq 'plugin-tester.local' })
if ($taskSite.Count -ne 1 -or [IO.Path]::GetFullPath($taskSite[0].path + '/app/public') -ne $taskRoot -or [int]$taskSite[0].services.mariadb.ports.MYSQL[0] -ne 10004 -or -not $env:MYSQL_PWD) { throw 'Exact local scope or credential source mismatch.' }
function Start-SelectedOwnedNode([string]$Script, [bool]$Confirmed) {
    if ($Script -notin @('selected-native-tree-proof.mjs','selected-native-proof.mjs')) { throw 'Fixed owner only.' }
    $taskInfo = [Diagnostics.ProcessStartInfo]::new()
    $taskInfo.FileName = $taskNode; $taskInfo.UseShellExecute = $false; $taskInfo.CreateNoWindow = $true
    $taskInfo.RedirectStandardInput = $true; $taskInfo.RedirectStandardOutput = $true; $taskInfo.RedirectStandardError = $true
    $taskInfo.ArgumentList.Add(($PSScriptRoot + '/' + $Script))
    if ($Confirmed) { $taskInfo.ArgumentList.Add('--confirmed-plugin-tester') }
    $taskProcess = [Diagnostics.Process]::new(); $taskProcess.StartInfo = $taskInfo
    if (-not $taskProcess.Start()) { throw 'Owned Node start refused.' }
    $taskProcess.StandardInput.Close(); return $taskProcess
}
# Physical process-tree prerequisite, before fixture creation or any response loss.
$taskTree = Start-SelectedOwnedNode 'selected-native-tree-proof.mjs' $false
$taskDescendant = $null
try {
    $taskLine = $taskTree.StandardOutput.ReadLineAsync(); $taskTreeErrors = $taskTree.StandardError.ReadToEndAsync()
    if (-not $taskLine.Wait(3000)) { throw 'Owned tree readiness unresolved.' }
    $taskReady = $taskLine.Result | ConvertFrom-Json
    if ($taskReady.kind -ne 'owned_tree_ready' -or $taskReady.child_pid -isnot [long] -and $taskReady.child_pid -isnot [int]) { throw 'Owned child identity refused.' }
    $taskDescendant = [Diagnostics.Process]::GetProcessById([int]$taskReady.child_pid)
    $taskDescendantStart = $taskDescendant.StartTime # Hold the actual instance, not a later PID lookup.
    if ($taskDescendant.HasExited -or $taskTree.HasExited -or $taskDescendantStart -lt $taskTree.StartTime) { throw 'Owned tree was not live.' }
    $taskTree.Kill($true)
    if (-not $taskTree.WaitForExit(3000) -or -not $taskDescendant.WaitForExit(3000) -or -not $taskTreeErrors.Wait(1000) -or $taskTreeErrors.Result.Length -ne 0) { throw 'Owned tree cleanup unresolved.' }
    [ordered]@{ stage='owned_process_tree_prerequisite'; passed=$true; owned_root_closed=$true; owned_descendant_closed=$true; wordpress_or_sql=$false } | ConvertTo-Json -Compress
} finally {
    if (-not $taskTree.HasExited) { $taskTree.Kill($true); [void]$taskTree.WaitForExit(3000) }
    if ($null -ne $taskDescendant) { $taskDescendant.Dispose() }; $taskTree.Dispose()
}
# Both pipes are drained asynchronously. Never wait-then-block on inherited pipes.
$taskOwner = Start-SelectedOwnedNode 'selected-native-proof.mjs' $true
try {
    $taskOutput = $taskOwner.StandardOutput.ReadToEndAsync(); $taskError = $taskOwner.StandardError.ReadToEndAsync()
    if (-not $taskOwner.WaitForExit(90000)) {
        $taskOwner.Kill($true); $taskStopped = $taskOwner.WaitForExit(3000)
        [ordered]@{ passed=$false; blocker='owned_owner_watchdog'; owned_root_closed=$taskStopped; fixtures_require_review=$true } | ConvertTo-Json -Compress
        exit 1
    }
    if (-not $taskOutput.Wait(1000) -or -not $taskError.Wait(1000)) { throw 'Owned pipe closure unresolved; no further SQL.' }
    if ($taskError.Result.Length -ne 0 -or $taskOutput.Result.Length -gt 65536) { throw 'Owned output refused; diagnostics withheld.' }
    $taskOutput.Result
    exit $taskOwner.ExitCode
} finally {
    if (-not $taskOwner.HasExited) { $taskOwner.Kill($true); [void]$taskOwner.WaitForExit(3000) }
    $taskOwner.Dispose()
}
