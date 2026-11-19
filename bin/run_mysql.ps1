$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot
$dataDirFile = Join-Path $projectRoot 'storage/mysql-datadir.txt'
if (-not (Test-Path -LiteralPath $dataDirFile)) { throw 'storage/mysql-datadir.txt is missing. Configure an existing MySQL server or provide the local data directory.' }
$dataDir = (Get-Content -LiteralPath $dataDirFile -Raw).Trim()
if (-not (Test-Path -LiteralPath (Join-Path $dataDir 'auto.cnf'))) { throw 'The configured MySQL data directory is not initialized.' }
$server = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe'
if (-not (Test-Path -LiteralPath $server)) { throw 'MySQL Server 8.4 is not installed at the expected path.' }
& $server --no-defaults --basedir='C:\Program Files\MySQL\MySQL Server 8.4' "--datadir=$dataDir" "--log-error=$dataDir\mysql.err" "--pid-file=$dataDir\mysql.pid" --port=3307 --bind-address=127.0.0.1 --mysqlx=OFF --console
