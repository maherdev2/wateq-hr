<?php
declare(strict_types=1);

function backup_verify_credentials(): array
{
    $user=getenv('BACKUP_VERIFY_USER') ?: '';
    $password=getenv('BACKUP_VERIFY_PASS');
    $local=dirname(__DIR__).'/storage/mysql-root.pass';
    if ($user==='' && is_file($local)) { $user='root'; $password=trim((string)file_get_contents($local)); }
    if ($user==='' || $password===false) throw new RuntimeException('اضبط BACKUP_VERIFY_USER وBACKUP_VERIFY_PASS بحساب يمكنه إنشاء قاعدة اختبار، أو شغّل التحقق في البيئة المحلية المهيأة.');
    return [$user,(string)$password];
}
function verify_backup_restore(string $file): array
{
    $directory=realpath(dirname(__DIR__).'/storage/backups'); $path=realpath($file);
    if (!$directory || !$path || dirname($path)!==$directory || !str_ends_with($path,'.sql')) throw new InvalidArgumentException('ملف النسخة خارج مجلد النسخ الاحتياطية.');
    [$user,$password]=backup_verify_credentials();
    $config=require dirname(__DIR__).'/config/database.php';
    $dsn='mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4';
    $admin=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $testDb='wateq_verify_'.bin2hex(random_bytes(6));
    $mysql=getenv('MYSQL_CLI') ?: (is_file('C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysql.exe')?'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysql.exe':'mysql');
    $credentialFile=tempnam(sys_get_temp_dir(),'wateq-verify-');
    if (!$credentialFile) throw new RuntimeException('تعذر تجهيز ملف تحقق مؤقت.');
    $safePassword=str_replace(['\\','"'],['\\\\','\\"'],$password);
    file_put_contents($credentialFile,"[client]\npassword=\"$safePassword\"\n");
    @chmod($credentialFile,0600);
    $created=false;
    try {
        $admin->exec("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $created=true;
        $command=[$mysql,'--defaults-extra-file='.$credentialFile,'--host='.$config['host'],'--port='.(string)$config['port'],'--user='.$user,$testDb];
        $process=proc_open($command,[0=>['file',$path,'r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('تعذر تشغيل عميل MySQL.');
        $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process)!==0) throw new RuntimeException('فشل استيراد نسخة الاختبار: '.substr(trim($stderr ?: $stdout),0,180));
        $restored=new PDO($dsn.';dbname='.$testDb,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $expected=[]; $handle=fopen($path,'rb'); if (!$handle) throw new RuntimeException('تعذر قراءة النسخة.');
        while (($line=fgets($handle))!==false) if (preg_match('/^INSERT INTO `([^`]+)` /',$line,$match)) $expected[$match[1]]=($expected[$match[1]] ?? 0)+1;
        fclose($handle);
        $tables=$restored->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (!$tables) throw new RuntimeException('لم تُستعد أي جداول.');
        foreach ($tables as $table) {
            if (!preg_match('/^[A-Za-z0-9_]+$/',$table)) throw new RuntimeException('اسم جدول غير متوقع في النسخة.');
            $actual=(int)$restored->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            if ($actual!==($expected[$table] ?? 0)) throw new RuntimeException('اختلاف عدد السجلات في جدول '.$table);
            unset($expected[$table]);
        }
        if ($expected) throw new RuntimeException('جداول مفقودة من النسخة المستعادة.');
        return ['tables'=>count($tables),'file'=>basename($path)];
    } finally {
        try { if ($created) $admin->exec("DROP DATABASE `$testDb`"); }
        finally { @unlink($credentialFile); }
    }
}
function record_backup_check(string $file): array
{
    try {
        $result=verify_backup_restore($file);
        query("INSERT INTO backup_checks (filename,checked_at,status,details) VALUES (?,NOW(),'passed',?)",[basename($file),$result['tables'].' جدول']);
        audit('backup_verify','settings',null,basename($file)); return $result;
    } catch (Throwable $error) {
        query("INSERT INTO backup_checks (filename,checked_at,status,details) VALUES (?,NOW(),'failed',?)",[basename($file),substr($error->getMessage(),0,240)]);
        throw $error;
    }
}
