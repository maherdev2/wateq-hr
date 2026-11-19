$ErrorActionPreference = 'Stop'
$phpPath = (Get-Command php -ErrorAction Stop).Source
$extensionDir = Join-Path (Split-Path $phpPath) 'ext'
$root = Split-Path $PSScriptRoot
$localDatabase = Join-Path $root 'storage/mysql-datadir.txt'
if (Test-Path -LiteralPath $localDatabase) {
    $portOpen = Test-NetConnection -ComputerName 127.0.0.1 -Port 3307 -InformationLevel Quiet -WarningAction SilentlyContinue
    if (-not $portOpen) {
        Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoProfile','-ExecutionPolicy','Bypass','-File',"`"$(Join-Path $PSScriptRoot 'run_mysql.ps1')`"") -WindowStyle Hidden
        $ready = $false
        for ($attempt=0; $attempt -lt 15; $attempt++) {
            Start-Sleep -Seconds 1
            if (Test-NetConnection -ComputerName 127.0.0.1 -Port 3307 -InformationLevel Quiet -WarningAction SilentlyContinue) { $ready=$true; break }
        }
        if (-not $ready) { throw 'Local MySQL did not start.' }
    }
}
& $phpPath -d "extension_dir=$extensionDir" -d extension=pdo_mysql -d extension=fileinfo (Join-Path $root 'bin/daily.php')
exit $LASTEXITCODE
