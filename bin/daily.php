<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core.php';
require dirname(__DIR__).'/app/PrintTemplates.php';
require dirname(__DIR__).'/app/Features.php';
require dirname(__DIR__).'/app/BackupVerification.php';
if (PHP_SAPI!=='cli') exit(1);
$lock=fopen(dirname(__DIR__).'/storage/daily.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { fwrite(STDERR,"Daily job already running.\n"); exit(1); }
try {
    $count=dispatch_expiry_alerts();
    $backup=create_backup();
    if (date('w')==='0' && (getenv('BACKUP_VERIFY_USER') || is_file(dirname(__DIR__).'/storage/mysql-root.pass'))) record_backup_check($backup);
    file_put_contents(dirname(__DIR__).'/storage/logs/daily.log',date('c')." alerts=$count backup=".basename($backup)."\n",FILE_APPEND|LOCK_EX);
    echo "Notifications: $count\nBackup: $backup\n";
} catch (Throwable $error) { error_log((string)$error); file_put_contents(dirname(__DIR__).'/storage/logs/daily.log',date('c')." failed: ".$error->getMessage()."\n",FILE_APPEND|LOCK_EX); fwrite(STDERR,"Daily job failed. Check PHP error log.\n"); exit(1); }
finally { flock($lock,LOCK_UN); fclose($lock); }
