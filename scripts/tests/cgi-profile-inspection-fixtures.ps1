# Isolated API fixtures only: all CIM/listener calls are shadowed; no native process inspection.
$ErrorActionPreference='Stop'
$helper=Join-Path $PSScriptRoot 'cgi-profile-inspect.ps1'
$global:alyntCgiFixture=@{scenario='valid';creation=[datetime]'2026-10-23T10:00:00Z';ini=([IO.Path]::GetTempPath()+'alynt-cgi-profile-'+('1'*32)+'/php.ini').Replace('\','/')}
function Get-CimInstance {
    param([string]$ClassName,[string]$Filter)
    if($ClassName -cne 'Win32_Process'){throw 'unexpected_class'}
    switch -Exact ($Filter){
        'ProcessId = 456' {return [pscustomobject]@{ProcessId=456;ParentProcessId=$(if($global:alyntCgiFixture.scenario -eq 'parent'){999}else{123});ExecutablePath=$(if($global:alyntCgiFixture.scenario -eq 'exe'){'C:/foreign/php-cgi.exe'}else{'C:/fixed/php-cgi.exe'});CreationDate=$global:alyntCgiFixture.creation;CommandLine=$(if($global:alyntCgiFixture.scenario -eq 'command'){'foreign'}else{'php-cgi.exe -c "'+$global:alyntCgiFixture.ini+'" -b 127.0.0.1:25000'})}}
        'ParentProcessId = 456' {
            if($global:alyntCgiFixture.scenario -eq 'no_child'){return}
            $c=[pscustomobject]@{ProcessId=789;ParentProcessId=456;Name=$(if($global:alyntCgiFixture.scenario -eq 'name'){'php-cgi.exe'}else{'conhost.exe'});ExecutablePath=$(if($global:alyntCgiFixture.scenario -eq 'child_exe'){'C:/foreign/conhost.exe'}else{'C:/Windows/System32/conhost.exe'});CreationDate=$(if($global:alyntCgiFixture.scenario -eq 'old'){$global:alyntCgiFixture.creation.AddSeconds(-1)}else{$global:alyntCgiFixture.creation.AddSeconds(1)});CommandLine='owned console'}
            $c;if($global:alyntCgiFixture.scenario -eq 'two'){$c};return
        }
        'ParentProcessId = 789' {if($global:alyntCgiFixture.scenario -eq 'grandchild'){[pscustomobject]@{ProcessId=900}};return}
        'ProcessId = 789' {if($global:alyntCgiFixture.scenario -eq 'residue'){[pscustomobject]@{ProcessId=789}};return}
        default {throw 'unexpected_cim_filter'}
    }
}
function Get-NetTCPConnection {
    param([string]$State,[string]$ErrorAction)
    if($global:alyntCgiFixture.scenario -in @('closed','residue')){return}
    $c=[pscustomobject]@{LocalPort=25000;LocalAddress=$(if($global:alyntCgiFixture.scenario -eq 'address'){'0.0.0.0'}else{'127.0.0.1'});OwningProcess=$(if($global:alyntCgiFixture.scenario -eq 'listener'){999}else{456})}
    $c;if($global:alyntCgiFixture.scenario -eq 'duplicate'){$c}
}
$checks=0
foreach($case in @('valid','no_child','name','child_exe','old','two','grandchild','parent','exe','command','address','listener','duplicate')){
    $global:alyntCgiFixture.scenario=$case
    $r=& $helper -Mode owned -OwnedPid 456 -OwnerPid 123 -Port 25000 -Cgi 'C:/fixed/php-cgi.exe' -Ini $global:alyntCgiFixture.ini | ConvertFrom-Json
    $expectOwned=$case -in @('valid','no_child','address','listener','duplicate')
    $expectListener=$case -notin @('address','listener','duplicate')
    if($r.owned -cne $expectOwned -or $r.listener -cne $expectListener){throw ('fixture_mismatch_'+$case)}
    $checks+=2
}
foreach($case in @('closed','residue')){
    $global:alyntCgiFixture.scenario=$case
    $r=& $helper -Mode closed -Port 25000 -ConsolePid 789 | ConvertFrom-Json
    if($r.closed -cne ($case -eq 'closed')){throw 'closure_fixture_mismatch'}
    $checks++
}
Remove-Variable -Name alyntCgiFixture -Scope Global
@{passed=$true;checks=$checks;isolated_api_only=$true;native_inspection=$false} | ConvertTo-Json -Compress
