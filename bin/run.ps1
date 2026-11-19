$ErrorActionPreference = 'Stop'
$phpPath = (Get-Command php -ErrorAction Stop).Source
$extensionDir = Join-Path (Split-Path $phpPath) 'ext'
if (-not (Test-Path (Join-Path $extensionDir 'php_pdo_mysql.dll'))) { throw 'pdo_mysql is missing from this PHP installation.' }
if (-not (Test-Path (Join-Path $extensionDir 'php_fileinfo.dll'))) { throw 'fileinfo is missing from this PHP installation.' }
& $phpPath -d "extension_dir=$extensionDir" -d extension=pdo_mysql -d extension=fileinfo -S 127.0.0.1:8000 -t public public/router.php
