$ErrorActionPreference = 'Stop'
$phpPath = (Get-Command php -ErrorAction Stop).Source
$extensionDir = Join-Path (Split-Path $phpPath) 'ext'
$root = Split-Path $PSScriptRoot
Get-ChildItem (Join-Path $root 'app'),(Join-Path $root 'bin'),(Join-Path $root 'public'),(Join-Path $root 'tests') -Recurse -File -Filter '*.php' | ForEach-Object {
    & $phpPath -l $_.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax failed: $($_.FullName)" }
}
& $phpPath -d "extension_dir=$extensionDir" -d extension=pdo_mysql -d extension=fileinfo (Join-Path $root 'tests/smoke.php')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
& $phpPath -d "extension_dir=$extensionDir" -d extension=pdo_mysql -d extension=fileinfo (Join-Path $root 'tests/integration.php')
exit $LASTEXITCODE
