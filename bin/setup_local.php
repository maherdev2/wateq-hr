<?php
declare(strict_types=1);
// One-time setup for a fresh local MySQL instance on loopback port 3307.
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
if (is_file($root.'/.env')) { fwrite(STDERR,".env already exists; refusing to overwrite it.\n"); exit(1); }
$port=(int)($argv[1] ?? 3307);
if ($port<1 || $port>65535) { fwrite(STDERR,"Invalid port.\n"); exit(1); }
$pdo=new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$sql=file_get_contents($root.'/database/schema.sql');
foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) if (trim($statement)!=='') $pdo->exec(trim($statement));
$appPassword=bin2hex(random_bytes(20));
$rootPassword=bin2hex(random_bytes(24));
$pdo->exec("CREATE USER 'wateq_app'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($appPassword));
$pdo->exec("GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP ON wateq_hr.* TO 'wateq_app'@'127.0.0.1'");
$env="DB_HOST=127.0.0.1\nDB_PORT=$port\nDB_NAME=wateq_hr\nDB_USER=wateq_app\nDB_PASS=$appPassword\nAPP_TIMEZONE=Asia/Riyadh\n";
file_put_contents($root.'/.env',$env,LOCK_EX);
file_put_contents($root.'/storage/mysql-root.pass',$rootPassword,LOCK_EX);
$pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($rootPassword));
echo "Database initialized. Application credentials saved in .env.\n";
