<?php
declare(strict_types=1);

function staff_reviewer(): void
{
    if (!in_array(require_login()['role_code'],['admin','hr'],true)) throw new RuntimeException('هذا الإجراء مخصص للإدارة والموارد البشرية.');
}
function unicode_length(string $value): int { return preg_match_all('/./u',$value) ?: 0; }
function valid_clock(string $value): bool { return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$value); }

function correction_action(string $action): void
{
    $user=require_login();
    if ($action==='request') {
        authorize('attendance.self');
        $employeeId=(int)($user['employee_id'] ?? 0);
        if (!$employeeId) throw new InvalidArgumentException('الحساب غير مرتبط بموظف.');
        $date=require_date(input('work_date'),'الدوام');
        if ($date>date('Y-m-d') || $date<date('Y-m-d',strtotime('-90 days'))) throw new InvalidArgumentException('التصحيح متاح لآخر 90 يومًا وحتى اليوم.');
        $in=input('check_in'); $out=input('check_out'); $reason=input('reason');
        if (!valid_clock($in) || ($out!=='' && !valid_clock($out)) || unicode_length($reason)<5 || unicode_length($reason)>500) throw new InvalidArgumentException('أدخل وقت حضور صحيحًا وسببًا واضحًا من 5 إلى 500 حرف.');
        if (scalar("SELECT COUNT(*) FROM attendance_corrections WHERE employee_id=? AND work_date=? AND status='pending'",[$employeeId,$date])) throw new InvalidArgumentException('يوجد طلب تصحيح معلق لهذا اليوم.');
        query('INSERT INTO attendance_corrections (employee_id,work_date,check_in,check_out,reason) VALUES (?,?,?,?,?)',[$employeeId,$date,$in,$out?:null,$reason]);
        $id=(int)App::db()->lastInsertId();
        notify_reviewers('طلب تصحيح حضور','طلب حضور جديد يحتاج المراجعة',url('corrections'));
        audit('correction_request','attendance_corrections',$id); flash('success','تم إرسال طلب التصحيح.'); return;
    }
    authorize('attendance.manage');
    if (!in_array($action,['approve','reject'],true)) throw new InvalidArgumentException('إجراء غير معروف.');
    $id=require_int(input('id'),'الطلب'); $db=App::db(); $ownTransaction=!$db->inTransaction(); if ($ownTransaction) $db->beginTransaction();
    try {
        $row=one('SELECT * FROM attendance_corrections WHERE id=? FOR UPDATE',[$id]);
        if (!$row || $row['status']!=='pending') throw new InvalidArgumentException('الطلب غير متاح.');
        employee_access((int)$row['employee_id']);
        if ((int)$user['employee_id']===(int)$row['employee_id']) throw new InvalidArgumentException('لا يمكنك اعتماد طلبك.');
        if ($action==='approve') {
            if (scalar("SELECT COUNT(*) FROM leave_requests WHERE employee_id=? AND status='approved' AND starts_on<=? AND ends_on>=?",[$row['employee_id'],$row['work_date'],$row['work_date']])) throw new InvalidArgumentException('يجب تسوية الإجازة المعتمدة قبل تصحيح الحضور.');
            $shift=one('SELECT s.* FROM employee_shifts es JOIN shifts s ON s.id=es.shift_id WHERE es.employee_id=? AND es.effective_from<=? ORDER BY es.effective_from DESC LIMIT 1',[$row['employee_id'],$row['work_date']]);
            $checkIn=$row['work_date'].' '.$row['check_in'];
            $checkOut=$row['check_out'] ? $row['work_date'].' '.$row['check_out'] : null;
            if ($checkOut && strtotime($checkOut)<=strtotime($checkIn)) $checkOut=date('Y-m-d H:i:s',strtotime($checkOut.' +1 day'));
            $late=$shift?max(0,(int)floor((strtotime($checkIn)-strtotime($row['work_date'].' '.$shift['starts_at']))/60)-(int)$shift['grace_minutes']):0;
            query('INSERT INTO attendance (employee_id,shift_id,work_date,check_in,check_out,status,late_minutes,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id),check_in=VALUES(check_in),check_out=VALUES(check_out),status=VALUES(status),late_minutes=VALUES(late_minutes),notes=VALUES(notes),recorded_by=VALUES(recorded_by)',[$row['employee_id'],$shift['id'] ?? null,$row['work_date'],$checkIn,$checkOut,$late?'late':'present',$late,'تصحيح معتمد رقم '.$id,$user['id']]);
        }
        query('UPDATE attendance_corrections SET status=?,decided_by=?,decided_at=NOW() WHERE id=?',[$action==='approve'?'approved':'rejected',$user['id'],$id]);
        notify_employee((int)$row['employee_id'],'تحديث طلب تصحيح الحضور',$action==='approve'?'تم اعتماد طلبك.':'تم رفض طلبك.',url('corrections'));
        audit('correction_'.$action,'attendance_corrections',$id); if ($ownTransaction) $db->commit(); flash('success','تم تحديث الطلب.');
    } catch (Throwable $error) { if ($ownTransaction) $db->rollBack(); throw $error; }
}

function notify_reviewers(string $title,string $body,string $target): void
{
    foreach (all("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code IN ('admin','hr') AND u.active=1") as $recipient) notify_user((int)$recipient['id'],$title,$body,$target);
}
function notify_employee(int $employeeId,string $title,string $body,string $target): void
{
    $recipient=one('SELECT id FROM users WHERE employee_id=? AND active=1',[$employeeId]);
    if ($recipient) notify_user((int)$recipient['id'],$title,$body,$target);
}
function service_kind_label(string $kind): string { return ['salary_letter'=>'خطاب تعريف بالراتب','experience_letter'=>'شهادة خبرة','contact_change'=>'تحديث بيانات التواصل'][$kind] ?? $kind; }

function service_action(string $action): void
{
    $user=require_login();
    if ($action==='request') {
        $employeeId=(int)($user['employee_id'] ?? 0); if (!$employeeId) throw new InvalidArgumentException('الحساب غير مرتبط بموظف.');
        $kind=input('kind'); if (!in_array($kind,['salary_letter','experience_letter','contact_change'],true)) throw new InvalidArgumentException('نوع الخدمة غير صحيح.');
        $payload=null;
        if ($kind==='contact_change') {
            $phone=input('phone'); $email=input('email');
            if ($phone!=='' && (unicode_length($phone)<7 || unicode_length($phone)>30)) throw new InvalidArgumentException('رقم التواصل غير صحيح.');
            if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('البريد الإلكتروني غير صحيح.');
            if ($phone==='' && $email==='') throw new InvalidArgumentException('أدخل وسيلة تواصل واحدة على الأقل.');
            $payload=json_encode(['phone'=>$phone?:null,'email'=>$email?:null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        }
        query('INSERT INTO service_requests (employee_id,kind,payload) VALUES (?,?,?)',[$employeeId,$kind,$payload]);
        $id=(int)App::db()->lastInsertId(); notify_reviewers('طلب خدمة موظف',service_kind_label($kind),url('services')); audit('service_request','service_requests',$id); flash('success','تم إرسال الطلب.'); return;
    }
    staff_reviewer();
    if (!in_array($action,['approve','reject'],true)) throw new InvalidArgumentException('إجراء غير معروف.');
    $id=require_int(input('id'),'الطلب'); $db=App::db(); $ownTransaction=!$db->inTransaction(); if ($ownTransaction) $db->beginTransaction();
    try {
        $row=one('SELECT * FROM service_requests WHERE id=? FOR UPDATE',[$id]);
        if (!$row || $row['status']!=='pending') throw new InvalidArgumentException('الطلب غير متاح.');
        $snapshot=null;
        if ($action==='approve') {
            if ($row['kind']==='contact_change') {
                $payload=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
                if ($payload['phone']!==null) query('UPDATE employees SET phone=? WHERE id=?',[$payload['phone'],$row['employee_id']]);
                if ($payload['email']!==null) query('UPDATE employees SET email=? WHERE id=?',[$payload['email'],$row['employee_id']]);
            } else {
                $employee=one('SELECT e.employee_no,e.first_name,e.father_name,e.last_name,e.hired_at,e.base_salary,e.allowances,d.name department,j.name job_title FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN job_titles j ON j.id=e.job_title_id WHERE e.id=?',[$row['employee_id']]);
                $snapshot=json_encode($employee+['issued_on'=>date('Y-m-d'),'kind'=>$row['kind'],'settings'=>organization_settings()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            }
        }
        query('UPDATE service_requests SET status=?,result_snapshot=?,decided_by=?,decided_at=NOW() WHERE id=?',[$action==='approve'?'approved':'rejected',$snapshot,$user['id'],$id]);
        notify_employee((int)$row['employee_id'],'تحديث طلب الخدمة',$action==='approve'?'تم اعتماد طلبك.':'تم رفض طلبك.',url('services'));
        audit('service_'.$action,'service_requests',$id); if ($ownTransaction) $db->commit(); flash('success','تم تحديث الطلب.');
    } catch (Throwable $error) { if ($ownTransaction) $db->rollBack(); throw $error; }
}

function seed_workflow_tasks(int $employeeId,string $phase): void
{
    $templates=$phase==='onboarding'?['استكمال الوثائق','تجهيز الحساب والصلاحيات','تسليم الأجهزة','شرح السياسات والدوام']:['استلام الأجهزة','إلغاء الصلاحيات','تسوية المستحقات','إخلاء الطرف وأرشفة الملف'];
    foreach ($templates as $title) query('INSERT IGNORE INTO workflow_tasks (employee_id,phase,title) VALUES (?,?,?)',[$employeeId,$phase,$title]);
}
function task_action(string $action): void
{
    authorize('employees.manage');
    if ($action==='start') {
        $employeeId=require_int(input('employee_id'),'الموظف'); employee_access($employeeId);
        $phase=input('phase'); if (!in_array($phase,['onboarding','offboarding'],true)) throw new InvalidArgumentException('نوع المهام غير صحيح.');
        seed_workflow_tasks($employeeId,$phase); audit('tasks_start','workflow_tasks',$employeeId,$phase); flash('success','أضيفت المهام الأساسية.'); return;
    }
    if ($action==='add') {
        $employeeId=require_int(input('employee_id'),'الموظف'); employee_access($employeeId); $title=input('title'); $phase=input('phase');
        if (unicode_length($title)<3 || unicode_length($title)>160 || !in_array($phase,['onboarding','offboarding'],true)) throw new InvalidArgumentException('بيانات المهمة غير صحيحة.');
        $due=input('due_on'); if ($due!=='') require_date($due,'الاستحقاق');
        query('INSERT INTO workflow_tasks (employee_id,phase,title,due_on) VALUES (?,?,?,?)',[$employeeId,$phase,$title,$due?:null]); audit('task_add','workflow_tasks',(int)App::db()->lastInsertId()); flash('success','أضيفت المهمة.'); return;
    }
    if ($action==='complete') {
        $id=require_int(input('id'),'المهمة'); $row=one('SELECT employee_id,completed_at FROM workflow_tasks WHERE id=?',[$id]);
        if (!$row) throw new InvalidArgumentException('المهمة غير موجودة.'); employee_access((int)$row['employee_id']);
        if ($row['completed_at']) throw new InvalidArgumentException('المهمة مكتملة بالفعل.');
        query('UPDATE workflow_tasks SET completed_at=NOW(),completed_by=? WHERE id=? AND completed_at IS NULL',[current_user()['id'],$id]); audit('task_complete','workflow_tasks',$id); flash('success','اكتملت المهمة.'); return;
    }
    throw new InvalidArgumentException('إجراء غير معروف.');
}

function payroll_locked(string $month): bool { return (bool)scalar("SELECT COUNT(*) FROM payroll_periods WHERE pay_month=? AND status='locked'",[$month]); }
function payroll_action(string $action): void
{
    authorize('salaries.manage'); staff_reviewer();
    $user=current_user(); $db=App::db(); $ownTransaction=!$db->inTransaction(); if ($ownTransaction) $db->beginTransaction();
    try {
        if ($action==='lock') {
            $month=require_date(input('pay_month'),'شهر الرواتب');
            if (substr($month,8)!=='01') throw new InvalidArgumentException('اختر أول يوم من الشهر.');
            if (!scalar('SELECT COUNT(*) FROM salaries WHERE pay_month=?',[$month])) throw new InvalidArgumentException('لا توجد رواتب لهذا الشهر.');
            if (scalar("SELECT COUNT(*) FROM salaries WHERE pay_month=? AND status<>'paid'",[$month])) throw new InvalidArgumentException('يجب صرف كل رواتب الشهر قبل قفله.');
            query("INSERT INTO payroll_periods (pay_month,status,locked_by,locked_at) VALUES (?,'locked',?,NOW()) ON DUPLICATE KEY UPDATE status='locked',locked_by=VALUES(locked_by),locked_at=VALUES(locked_at)",[$month,$user['id']]);
            audit('payroll_lock','payroll_periods',null,$month);
        } else {
            if (!in_array($action,['submit','approve','return','pay'],true)) throw new InvalidArgumentException('إجراء غير معروف.');
            $id=require_int(input('id'),'الراتب'); $row=one('SELECT * FROM salaries WHERE id=? FOR UPDATE',[$id]);
            if (!$row || payroll_locked($row['pay_month'])) throw new InvalidArgumentException('الراتب غير متاح أو الشهر مقفل.');
            if ($action==='submit' && $row['status']==='draft') query("UPDATE salaries SET status='review',submitted_by=? WHERE id=?",[$user['id'],$id]);
            elseif ($action==='approve' && $row['status']==='review') {
                if ((int)$row['submitted_by']===(int)$user['id']) throw new InvalidArgumentException('يعتمد الراتب مستخدم آخر غير مُرسله.');
                query("UPDATE salaries SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?",[$user['id'],$id]);
            } elseif ($action==='return' && $row['status']==='review') query("UPDATE salaries SET status='draft',submitted_by=NULL WHERE id=?",[$id]);
            elseif ($action==='pay' && $row['status']==='approved') query("UPDATE salaries SET status='paid',paid_at=NOW() WHERE id=?",[$id]);
            else throw new InvalidArgumentException('انتقال حالة الراتب غير مسموح.');
            audit('payroll_'.$action,'salaries',$id);
            if ($action==='submit') notify_reviewers('راتب ينتظر المراجعة','راتب جديد ينتظر الاعتماد',url('payroll'));
            if ($action==='pay') notify_employee((int)$row['employee_id'],'صرف الراتب','تتوفر قسيمة راتبك.',url('salary_slip',['id'=>$id]));
        }
        if ($ownTransaction) $db->commit(); flash('success','تم تحديث الرواتب.');
    } catch (Throwable $error) { if ($ownTransaction) $db->rollBack(); throw $error; }
}

function alert_rule_action(string $action): void
{
    authorize('settings.manage');
    if ($action==='toggle') {
        $id=require_int(input('id'),'القاعدة');
        query('UPDATE alert_rules SET active=1-active WHERE id=?',[$id]);
        audit('alert_rule_toggle','alert_rules',$id); flash('success','تم تحديث قاعدة التنبيه.'); return;
    }
    if ($action!=='save') throw new InvalidArgumentException('إجراء غير معروف.');
    $kind=input('kind'); $days=input('days_before');
    if (!in_array($kind,['contract','document','training'],true) || !preg_match('/^-?\d{1,3}$/',$days) || (int)$days< -30 || (int)$days>365) throw new InvalidArgumentException('قاعدة التنبيه غير صحيحة.');
    $flags=[]; foreach (['notify_admin','notify_hr','notify_employee'] as $key) $flags[]=isset($_POST[$key])?1:0;
    if (array_sum($flags)===0) throw new InvalidArgumentException('اختر مستلمًا واحدًا على الأقل.');
    query('INSERT INTO alert_rules (kind,days_before,notify_admin,notify_hr,notify_employee,active) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE notify_admin=VALUES(notify_admin),notify_hr=VALUES(notify_hr),notify_employee=VALUES(notify_employee),active=1',[$kind,(int)$days,...$flags]);
    audit('alert_rule_save','alert_rules'); flash('success','حُفظت قاعدة التنبيه.');
}
