<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core.php';
require dirname(__DIR__).'/app/BackupVerification.php';
if (PHP_SAPI!=='cli') exit(1);
$files=glob(dirname(__DIR__).'/storage/backups/wateq-*.sql') ?: [];
usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));
if (!$files) { fwrite(STDERR,"No backup found.\n"); exit(1); }
try { $result=record_backup_check($argv[1] ?? $files[0]); echo "Restored {$result['tables']} tables from {$result['file']} into a temporary database.\n"; }
catch (Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
