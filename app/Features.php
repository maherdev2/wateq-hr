<?php
declare(strict_types=1);

function contract_snapshot(int $id): array
{
    $row=one('SELECT c.*,e.employee_no,e.first_name,e.father_name,e.last_name,e.national_id,d.name department,j.name job_title FROM contracts c JOIN employees e ON e.id=c.employee_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN job_titles j ON j.id=e.job_title_id WHERE c.id=?',[$id]);
    if (!$row) throw new InvalidArgumentException('العقد غير موجود.');
    return $row + ['print_settings'=>organization_settings()];
}

function contract_action(string $action): void
{
    authorize('contracts.manage');
    if (!in_array($action,['submit','approve','return'],true)) throw new InvalidArgumentException('إجراء غير معروف.');
    $id=require_int(input('id'),'العقد');
    $db=App::db(); $ownTransaction=!$db->inTransaction(); if ($ownTransaction) $db->beginTransaction();
    try {
        $row=one('SELECT * FROM contracts WHERE id=? FOR UPDATE',[$id]);
        if (!$row) throw new InvalidArgumentException('العقد غير موجود.');
        $user=current_user();
        if ($action==='submit') {
            if ($row['status']!=='draft') throw new InvalidArgumentException('العقد ليس في حالة مسودة.');
            query("UPDATE contracts SET status='review',submitted_by=? WHERE id=?",[$user['id'],$id]);
            foreach (all("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code IN ('admin','hr') AND u.active=1 AND u.id<>?",[$user['id']]) as $recipient) notify_user((int)$recipient['id'],'عقد ينتظر المراجعة','العقد '.$row['contract_no'].' جاهز للاعتماد',url('contracts',['id'=>$id]));
        } elseif ($action==='return') {
            if ($row['status']!=='review') throw new InvalidArgumentException('العقد ليس قيد المراجعة.');
            query("UPDATE contracts SET status='draft',submitted_by=NULL WHERE id=?",[$id]);
            if ($row['submitted_by']) notify_user((int)$row['submitted_by'],'إعادة عقد للمراجعة','العقد '.$row['contract_no'].' أعيد إلى المسودة',url('contracts',['id'=>$id]));
        } else {
            if ($row['status']!=='review') throw new InvalidArgumentException('العقد ليس قيد المراجعة.');
            if ((int)$row['submitted_by']===(int)$user['id']) throw new InvalidArgumentException('يجب أن يعتمد العقد مستخدم آخر غير مقدم المراجعة.');
            $version=(int)$row['version']+1;
            $snapshot=contract_snapshot($id);
            $snapshot['approved_at']=date('Y-m-d H:i:s');
            $snapshot['version']=$version;
            query('INSERT INTO contract_versions (contract_id,version,snapshot,approved_by,approved_at) VALUES (?,?,?,?,?)',[$id,$version,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$user['id'],$snapshot['approved_at']]);
            query("UPDATE contracts SET status='approved',version=?,approved_by=?,approved_at=? WHERE id=?",[$version,$user['id'],$snapshot['approved_at'],$id]);
            $recipient=one('SELECT id FROM users WHERE employee_id=? AND active=1',[$row['employee_id']]);
            if ($recipient) notify_user((int)$recipient['id'],'اعتماد العقد','اعتُمد العقد '.$row['contract_no'].'، الإصدار '.$version,url('contract_print',['id'=>$id,'version'=>$version]));
        }
        audit('contract_'.$action,'contracts',$id,'رقم العقد: '.$row['contract_no']);
        if ($ownTransaction) $db->commit();
        flash('success','تم تحديث حالة العقد.');
    } catch (Throwable $error) { if ($ownTransaction) $db->rollBack(); throw $error; }
}

function send_notification_email(int $userId,string $title,string $body): void
{
    if (getenv('MAIL_ENABLED')!=='1') return;
    $to=scalar('SELECT email FROM users WHERE id=? AND active=1',[$userId]);
    $from=getenv('MAIL_FROM') ?: '';
    if (!filter_var($to,FILTER_VALIDATE_EMAIL) || !filter_var($from,FILTER_VALIDATE_EMAIL)) return;
    $safeTitle=str_replace(["\r","\n"],' ',$title);
    $headers="From: $from\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    if (!mail($to,'=?UTF-8?B?'.base64_encode($safeTitle).'?=',$body,$headers)) error_log('Mail delivery failed for notification to user '.$userId);
}

function dispatch_expiry_alerts(): int
{
    $events=all("SELECT 'contract' kind,c.id item_id,c.employee_id,c.contract_no label,c.ends_on due_on,DATEDIFF(c.ends_on,CURRENT_DATE) days_left FROM contracts c WHERE c.ends_on IS NOT NULL UNION ALL SELECT 'document',d.id,d.employee_id,d.title,d.expires_on,DATEDIFF(d.expires_on,CURRENT_DATE) FROM documents d WHERE d.expires_on IS NOT NULL UNION ALL SELECT 'training',t.id,t.employee_id,t.course_name,t.certificate_expires_on,DATEDIFF(t.certificate_expires_on,CURRENT_DATE) FROM trainings t WHERE t.certificate_expires_on IS NOT NULL");
    $rules=all('SELECT * FROM alert_rules WHERE active=1');
    $sent=0;
    foreach ($events as $event) {
        foreach ($rules as $rule) {
        if ($rule['kind']!==$event['kind'] || (int)$rule['days_before']!==(int)$event['days_left']) continue;
        $key=$event['kind'].':'.$event['item_id'].':'.$event['days_left'].':'.$event['due_on'];
        $insert=query('INSERT IGNORE INTO notification_events (event_key) VALUES (?)',[$key]);
        if ($insert->rowCount()===0) continue;
        $kind=['contract'=>'عقد','document'=>'وثيقة','training'=>'شهادة تدريب'][$event['kind']];
        $target=$event['kind']==='contract'?url('contracts',['id'=>$event['item_id']]):($event['kind']==='document'?url('documents',['id'=>$event['item_id']]):url('trainings',['id'=>$event['item_id']]));
        $title='تنبيه انتهاء '.$kind;
        $body=$kind.' '.$event['label'].' ينتهي في '.$event['due_on'].' (بعد '.$event['days_left'].' يوم).';
        $recipients=all("SELECT DISTINCT u.id,r.code role_code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.active=1 AND (r.code IN ('admin','hr') OR u.employee_id=?)",[$event['employee_id']]);
        foreach ($recipients as $recipient) {
            $role=$recipient['role_code'];
            if (($role==='admin' && !$rule['notify_admin']) || ($role==='hr' && !$rule['notify_hr']) || (!in_array($role,['admin','hr'],true) && !$rule['notify_employee'])) continue;
            notify_user((int)$recipient['id'],$title,$body,in_array($role,['admin','hr'],true)?$target:url('employee_file',['id'=>$event['employee_id']]));
        }
        $sent++;
        }
    }
    return $sent;
}

function create_backup(): string
{
    $c=require dirname(__DIR__).'/config/database.php';
    $directory=dirname(__DIR__).'/storage/backups';
    if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('تعذر إنشاء مجلد النسخ الاحتياطية.');
    $file=$directory.'/wateq-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
    $tables=array_column(all('SHOW TABLES'),'Tables_in_'.$c['database']);
    $out=fopen($file,'xb'); if (!$out) throw new RuntimeException('تعذر إنشاء النسخة.');
    try {
        fwrite($out,"SET FOREIGN_KEY_CHECKS=0;\n");
        foreach ($tables as $table) {
            $create=one("SHOW CREATE TABLE `$table`"); fwrite($out,"DROP TABLE IF EXISTS `$table`;\n".$create['Create Table'].";\n");
            $allowed=array_column(array_filter(all("SHOW COLUMNS FROM `$table`"),fn($col)=>!str_contains(strtoupper($col['Extra'] ?? ''),'GENERATED')),'Field');
            foreach (all("SELECT * FROM `$table`") as $row) {
                $row=array_intersect_key($row,array_flip($allowed));
                $columns=implode(',',array_map(fn($key)=>"`$key`",array_keys($row)));
                $values=implode(',',array_map(fn($value)=>$value===null?'NULL':App::db()->quote((string)$value),array_values($row)));
                fwrite($out,"INSERT INTO `$table` ($columns) VALUES ($values);\n");
            }
        }
        fwrite($out,"SET FOREIGN_KEY_CHECKS=1;\n");
    } catch (Throwable $error) { fclose($out); unlink($file); throw $error; }
    fclose($out);
    audit('backup_create','settings',null,basename($file));
    return $file;
}
