param([Parameter(Mandatory=$true)][ValidateSet('snapshot','owned','closed')][string]$Mode,[int]$OwnedPid=0,[int]$OwnerPid=0,[int]$Port=0,[string]$Cgi='', [string]$Ini='', [int]$ConsolePid=0)
$ErrorActionPreference='Stop'
function Normalize-CgiPath([string]$Value){return [IO.Path]::GetFullPath($Value).Replace('\','/').TrimEnd('/').ToLowerInvariant()}
if($Mode -eq 'snapshot'){
    $sites=Get-Content -LiteralPath 'C:\Users\Captain\AppData\Roaming\Local\sites.json' -Raw | ConvertFrom-Json
    $site=@($sites.PSObject.Properties | Where-Object {$_.Value.domain -ceq 'plugin-tester.local'})
    if($site.Count -ne 1 -or (Normalize-CgiPath ($site[0].Value.path+'/app/public')) -cne 'c:/users/captain/local sites/plugin-tester/app/public'){throw 'profile_mismatch'}
    $conf='C:\Users\Captain\AppData\Roaming\Local\run\'+$site[0].Name+'\conf'
    $files=@(Get-ChildItem -LiteralPath $conf -Recurse -File | Where-Object {$_.Extension -in @('.ini','.conf')} | Sort-Object FullName | ForEach-Object { @{path=$_.FullName;hash=(Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash} })
    if($files.Count -lt 5){throw 'config_inventory'}
    $ports=@($site[0].Value.services.php.ports.cgi)+@($site[0].Value.services.nginx.ports.HTTP)+@($site[0].Value.services.mariadb.ports.MYSQL)
    $listeners=@(Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object LocalPort -in $ports | Sort-Object LocalPort,LocalAddress | ForEach-Object {
        $p=Get-CimInstance Win32_Process -Filter ('ProcessId = '+$_.OwningProcess)
        if(!$p){throw 'listener_identity'}
        @{port=$_.LocalPort;address=$_.LocalAddress;pid=$p.ProcessId;start=$p.CreationDate.ToUniversalTime().ToString('o');exe=$p.ExecutablePath}
    })
    @{files=$files;listeners=$listeners} | ConvertTo-Json -Depth 5 -Compress
}elseif($Mode -eq 'closed'){
    if($Port -lt 1024 -or $Port -gt 65535){throw 'port_scope'}
    $found=@(Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object LocalPort -eq $Port)
    $console=if($ConsolePid -gt 0){Get-CimInstance Win32_Process -Filter ('ProcessId = '+$ConsolePid)}else{$null}
    @{closed=($found.Count -eq 0 -and !$console)} | ConvertTo-Json -Compress
}else{
    if($OwnedPid -lt 1 -or $OwnerPid -lt 1 -or $Port -lt 1024 -or $Port -gt 65535){throw 'owned_scope'}
    $p=Get-CimInstance Win32_Process -Filter ('ProcessId = '+$OwnedPid)
    $children=@(Get-CimInstance Win32_Process -Filter ('ParentProcessId = '+$OwnedPid))
    $path=Normalize-CgiPath $Ini;$temporary=Normalize-CgiPath ([IO.Path]::GetTempPath())
    # Windows may attach one private console host. It is not a PHP worker; reject every other child or descendant.
    $consoleValid=$children.Count -le 1
    foreach($consoleChild in $children){
        $descendants=@(Get-CimInstance Win32_Process -Filter ('ParentProcessId = '+$consoleChild.ProcessId))
        if(!$p -or $consoleChild.Name -cne 'conhost.exe' -or (Normalize-CgiPath $consoleChild.ExecutablePath) -cne 'c:/windows/system32/conhost.exe' -or $consoleChild.CreationDate -lt $p.CreationDate -or $descendants.Count -ne 0){$consoleValid=$false}
    }
    $owned=$p -and $p.ParentProcessId -eq $OwnerPid -and (Normalize-CgiPath $p.ExecutablePath) -ceq (Normalize-CgiPath $Cgi) -and $path.StartsWith($temporary+'/alynt-cgi-profile-') -and $path -match '/alynt-cgi-profile-[a-f0-9]{32}/php\.ini$' -and ([string]$p.CommandLine).Replace('\','/').ToLowerInvariant().Contains($path) -and $consoleValid
    $listeners=@(Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object LocalPort -eq $Port)
    $listener=$listeners.Count -eq 1 -and $listeners[0].LocalAddress -ceq '127.0.0.1' -and $listeners[0].OwningProcess -eq $OwnedPid
    $guards=@{exists=[bool]$p;parent=($p -and $p.ParentProcessId -eq $OwnerPid);executable=($p -and (Normalize-CgiPath $p.ExecutablePath) -ceq (Normalize-CgiPath $Cgi));temporary=$path.StartsWith($temporary+'/alynt-cgi-profile-');suffix=($path -match '/alynt-cgi-profile-[a-f0-9]{32}/php\.ini$');command=($p -and ([string]$p.CommandLine).Replace('\','/').ToLowerInvariant().Contains($path));children=$consoleValid}
    $childFacts=@($children | ForEach-Object {@{pid=$_.ProcessId;parent=$_.ParentProcessId;name=$_.Name;executable_matches=($_.ExecutablePath -and (Normalize-CgiPath $_.ExecutablePath) -ceq (Normalize-CgiPath $Cgi));command_matches=([string]$_.CommandLine).Replace('\','/').ToLowerInvariant().Contains($path)}})
    @{owned=[bool]$owned;listener=[bool]$listener;children=$children.Count;console_valid=[bool]$consoleValid;console_pid=$(if($consoleValid -and $children.Count -eq 1){$children[0].ProcessId}else{0});guards=$guards;child_facts=$childFacts} | ConvertTo-Json -Depth 4 -Compress
}
