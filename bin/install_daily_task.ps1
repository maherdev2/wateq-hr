$ErrorActionPreference = 'Stop'
$script = Join-Path $PSScriptRoot 'run_daily.ps1'
$taskName = 'Wateq HR Daily'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$script`"" -WorkingDirectory (Split-Path $PSScriptRoot)
$trigger = New-ScheduledTaskTrigger -Daily -At '01:00'
$principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Description 'Daily HR expiry notifications and private SQL backup' -Force | Out-Null
Write-Output "Registered $taskName at 01:00 (when this Windows user is signed in)."
