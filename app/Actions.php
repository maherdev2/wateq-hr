<?php
declare(strict_types=1);

function handle_post(string $page): void
{
    verify_csrf();
    $action = input('action');
    if ($page === 'login') {
        if (!filter_var(input('email'), FILTER_VALIDATE_EMAIL) || !login(input('email'), input('password'))) throw new RuntimeException('بيانات الدخول غير صحيحة أو الحساب مقفل مؤقتًا.');
        redirect(isset($_SESSION['pending_mfa'])?'mfa_login':'dashboard');
    }
    if ($page==='mfa_login') { verify_login_mfa(); redirect(); }
    if ($page==='forgot_password') { password_reset_action('request'); redirect('forgot_password'); }
    if ($page==='reset_password') { password_reset_action('reset'); redirect('login'); }
    require_login();
    if ($action === 'logout') { logout(); redirect('login'); }
    if ($page === 'password') { change_password(); redirect('password'); }
    if ($page === 'attendance') { attendance_action($action); redirect('attendance'); }
    if ($page === 'corrections') { correction_action($action); redirect('corrections'); }
    if ($page === 'services') { service_action($action); redirect('services'); }
    if ($page === 'tasks') { task_action($action); redirect('tasks'); }
    if ($page === 'payroll') { payroll_action($action); redirect('payroll'); }
    if ($page === 'alerts') { alert_rule_action($action); redirect('alerts'); }
    if ($page === 'mfa') { mfa_action($action); redirect('mfa'); }
    if ($page === 'calendar') { calendar_action($action); redirect('calendar',['month'=>input('month') ?: date('Y-m')]); }
    if ($page === 'leaves') { leave_action($action); redirect('leaves'); }
    if ($page === 'contracts' && in_array($action,['submit','approve','return'],true)) { contract_action($action); redirect('contracts',['id'=>require_int(input('id'),'العقد')]); }
    if ($page === 'users') { user_action($action); redirect('users'); }
    if ($page === 'settings') { settings_action($action); redirect('settings'); }
    if ($page === 'backup') { backup_action(); redirect('settings'); }
    if ($page === 'notifications') { query('UPDATE notifications SET read_at=NOW() WHERE user_id=?', [current_user()['id']]); redirect('notifications'); }
    $defs = entities();
    if (isset($defs[$page])) { entity_action($page,$action,$defs[$page]); redirect($page); }
    throw new RuntimeException('إجراء غير معروف.');
}

function entity_action(string $page, string $action, array $def): void
{
    authorize($def['permission']);
    $table = $def['table'];
    $id = (int)input('id');
    if ($action === 'delete') {
        if ($id < 1) throw new InvalidArgumentException('العنصر غير صحيح.');
        if ($page==='salaries') { $salary=one('SELECT pay_month,status FROM salaries WHERE id=?',[$id]); if (!$salary || $salary['status']!=='draft' || payroll_locked($salary['pay_month'])) throw new InvalidArgumentException('يمكن حذف مسودة راتب من شهر مفتوح فقط.'); }
        if ($page==='contracts' && scalar('SELECT COUNT(*) FROM contract_versions WHERE contract_id=?',[$id])) throw new InvalidArgumentException('لا يمكن حذف عقد له إصدار معتمد.');
        query("DELETE FROM $table WHERE id=?", [$id]); audit('delete',$table,$id); flash('success','تم الحذف.'); return;
    }
    if (!in_array($action,['create','update'],true)) throw new InvalidArgumentException('إجراء غير معروف.');
    if ($action === 'update' && $id < 1) throw new InvalidArgumentException('العنصر غير صحيح.');
    $existing = $action === 'update' ? one("SELECT * FROM $table WHERE id=?",[$id]) : null;
    if ($action === 'update' && !$existing) throw new InvalidArgumentException('العنصر غير موجود.');
    if ($page==='salaries' && $existing && ($existing['status']!=='draft' || payroll_locked($existing['pay_month']))) throw new InvalidArgumentException('يمكن تعديل مسودة راتب من شهر مفتوح فقط.');
    $values = [];
    foreach ($def['fields'] as $name=>$field) {
        if ($field['type'] === 'file') {
            $v = upload_file($name, $page === 'documents' && $action === 'create');
            if ($v === null && $existing) continue;
        } else {
            $v = input($name);
            if ($v === '') $v = null;
            if ($v === null && $field['type'] === 'enum' && empty($field['required'])) $v = (string)array_key_first($field['options']);
            if ($v === null && $field['type'] === 'number' && empty($field['required'])) $v = '0';
            if ($v === null && !empty($field['required'])) throw new InvalidArgumentException($field['label'] . ' مطلوب.');
            if ($v !== null && $field['type'] === 'date') require_date($v,$field['label']);
            if ($v !== null && $field['type'] === 'email' && !filter_var($v,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('البريد الإلكتروني غير صحيح.');
            if ($v !== null && $field['type'] === 'number' && (!is_numeric($v) || (float)$v < (float)($field['min'] ?? 0) || (isset($field['max']) && (float)$v > $field['max']))) throw new InvalidArgumentException($field['label'] . ' غير صحيح.');
            if ($v !== null && $field['type'] === 'select') require_int($v,$field['label']);
            if ($v !== null && $field['type'] === 'enum' && !array_key_exists($v,$field['options'])) throw new InvalidArgumentException($field['label'] . ' غير صحيح.');
            if ($v !== null && strlen($v) > ($field['type'] === 'textarea' ? 3000 : 255)) throw new InvalidArgumentException($field['label'] . ' طويل جدًا.');
        }
        $values[$name] = $v;
    }
    if ($page === 'reviews') $values['reviewer_id'] = current_user()['id'];
    if ($page === 'shifts' && $values['work_days'] !== null) $values['work_days']=normalize_work_days($values['work_days']);
    if ($page === 'holidays' && $values['ends_on'] < $values['starts_on']) throw new InvalidArgumentException('نهاية العطلة تسبق بدايتها.');
    if ($page === 'salaries') $values['pay_month'] = substr($values['pay_month'],0,7) . '-01';
    if ($page === 'salaries') { $values['status']='draft'; if (payroll_locked($values['pay_month'])) throw new InvalidArgumentException('شهر الرواتب مقفل.'); }
    if ($page === 'employees' && $action === 'update' && $existing['status'] !== $values['status']) $values['left_on']=in_array($values['status'],['resigned','terminated'],true)?date('Y-m-d'):null;
    if ($page === 'contracts' && $action === 'update' && $existing['status'] !== 'draft') { $values['status']='draft'; $values['submitted_by']=null; $values['approved_by']=null; $values['approved_at']=null; }
    if ($action === 'create') {
        $columns = array_keys($values);
        query("INSERT INTO $table (" . implode(',',$columns) . ') VALUES (' . implode(',',array_fill(0,count($columns),'?')) . ')', array_values($values));
        $id = (int)App::db()->lastInsertId();
        if ($page === 'employees') seed_employee_balances($id);
        if ($page === 'employees') seed_workflow_tasks($id,'onboarding');
    } else {
        $set = implode(',', array_map(fn($c)=>"$c=?",array_keys($values)));
        query("UPDATE $table SET $set WHERE id=?", [...array_values($values),$id]);
    }
    if ($page === 'employees' && $existing && $existing['status']!==$values['status'] && in_array($values['status'],['resigned','terminated'],true)) seed_workflow_tasks($id,'offboarding');
    $changed=$existing?array_keys(array_filter($values,fn($value,$key)=>(string)$value!==(string)($existing[$key] ?? null),ARRAY_FILTER_USE_BOTH)):array_keys($values);
    audit($action,$table,$id,'الحقول: '.substr(implode(',',$changed),0,240));
    flash('success',$action === 'create' ? 'تمت الإضافة بنجاح.' : 'تم حفظ التعديلات.');
}
function seed_employee_balances(int $id): void
{
    query('INSERT INTO leave_balances (employee_id,leave_type_id,balance_year,allocated) SELECT ?,id,YEAR(CURRENT_DATE),annual_days FROM leave_types WHERE annual_days>0',[$id]);
}
function change_password(): void
{
    $u = require_login();
    $row = one('SELECT password_hash FROM users WHERE id=?',[$u['id']]);
    if (!password_verify(input('current_password'),$row['password_hash'])) throw new InvalidArgumentException('كلمة المرور الحالية غير صحيحة.');
    $new = input('new_password');
    if (strlen($new)<12 || !preg_match('/[A-Z]/',$new) || !preg_match('/[0-9]/',$new)) throw new InvalidArgumentException('استخدم 12 حرفًا على الأقل، مع حرف كبير ورقم.');
    query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);
    audit('password_changed','users',$u['id']); flash('success','تم تغيير كلمة المرور.');
}

function attendance_action(string $action): void
{
    $u = require_login();
    if (in_array($action,['check_in','check_out'],true)) {
        authorize('attendance.self');
        $id = $u['employee_id'];
        if (!$id) throw new InvalidArgumentException('حسابك غير مرتبط بموظف.');
        $today = date('Y-m-d');
        $row = one('SELECT * FROM attendance WHERE employee_id=? AND work_date=?',[$id,$today]);
        if ($action === 'check_in') {
            if ($row) throw new InvalidArgumentException('تم تسجيل الحضور لهذا اليوم.');
            $shift = one('SELECT s.* FROM employee_shifts es JOIN shifts s ON s.id=es.shift_id WHERE es.employee_id=? AND es.effective_from<=? ORDER BY es.effective_from DESC LIMIT 1',[$id,$today]) ?? one('SELECT * FROM shifts WHERE active=1 ORDER BY id LIMIT 1');
            $now = date('Y-m-d H:i:s');
            $scheduled = $shift ? strtotime($today.' '.$shift['starts_at']) : time();
            $late = max(0,(int)floor((time()-$scheduled)/60)-(int)($shift['grace_minutes'] ?? 0));
            query('INSERT INTO attendance (employee_id,shift_id,work_date,check_in,status,late_minutes,recorded_by) VALUES (?,?,?,?,?,?,?)',[$id,$shift['id'] ?? null,$today,$now,$late?'late':'present',$late,$u['id']]);
        } else {
            if (!$row || !$row['check_in'] || $row['check_out']) throw new InvalidArgumentException('يجب تسجيل الحضور أولًا ولمرة واحدة.');
            $shift = $row['shift_id'] ? one('SELECT * FROM shifts WHERE id=?',[$row['shift_id']]) : null;
            $end = $shift ? strtotime($today.' '.$shift['ends_at']) : time();
            if ($shift && $end <= strtotime($today.' '.$shift['starts_at'])) $end += 86400;
            $early = max(0,(int)floor(($end-time())/60));
            $overtime = max(0,(int)floor((time()-$end)/60));
            query('UPDATE attendance SET check_out=NOW(),early_minutes=?,overtime_minutes=? WHERE id=?',[$early,$overtime,$row['id']]);
        }
        audit($action,'attendance',$id); flash('success','تم تسجيل الوقت بنجاح.'); return;
    }
    authorize('attendance.manage');
    if ($action !== 'manual') throw new InvalidArgumentException('إجراء غير معروف.');
    $employeeId = require_int(input('employee_id'),'الموظف'); employee_access($employeeId);
    $date = require_date(input('work_date'),'الحضور');
    $status = input('status'); if (!in_array($status,['present','late','absent','leave'],true)) throw new InvalidArgumentException('حالة الحضور غير صحيحة.');
    $checkIn = input('check_in'); $checkOut = input('check_out');
    if ($checkIn && !preg_match('/^\d{2}:\d{2}$/',$checkIn)) throw new InvalidArgumentException('وقت الحضور غير صحيح.');
    if ($checkOut && !preg_match('/^\d{2}:\d{2}$/',$checkOut)) throw new InvalidArgumentException('وقت الانصراف غير صحيح.');
    if ($checkIn && $checkOut && $checkOut <= $checkIn) throw new InvalidArgumentException('وقت الانصراف يجب أن يكون بعد الحضور.');
    query('INSERT INTO attendance (employee_id,work_date,check_in,check_out,status,notes,recorded_by) VALUES (?,?,?,?,?,?,?)',[$employeeId,$date,$checkIn ? "$date $checkIn:00" : null,$checkOut ? "$date $checkOut:00" : null,$status,input('notes'),$u['id']]);
    audit('attendance_manual','attendance',$employeeId); flash('success','تم تسجيل الحضور.');
}

function leave_action(string $action): void
{
    $u = require_login();
    if ($action === 'request') {
        authorize('leave.self');
        $id = $u['employee_id']; if (!$id) throw new InvalidArgumentException('حسابك غير مرتبط بموظف.');
        $type = require_int(input('leave_type_id'),'نوع الإجازة');
        $start = require_date(input('starts_on'),'البداية'); $end = require_date(input('ends_on'),'النهاية');
        $span = (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
        if ($end < $start || $span > 365) throw new InvalidArgumentException('نطاق الإجازة غير صحيح.');
        $leaveType = one('SELECT * FROM leave_types WHERE id=?',[$type]); if (!$leaveType) throw new InvalidArgumentException('نوع الإجازة غير موجود.');
        $breakdown=leave_count_by_year($id,$start,$end,$leaveType['count_mode']);
        $days=array_sum($breakdown);
        if ($days===0) throw new InvalidArgumentException('الفترة لا تتضمن أيامًا تُحتسب لهذا النوع من الإجازات.');
        $attachment = upload_file('attachment', (bool)$leaveType['requires_attachment']);
        if (scalar("SELECT COUNT(*) FROM leave_requests WHERE employee_id=? AND status IN ('pending','approved') AND starts_on<=? AND ends_on>=?",[$id,$end,$start])) throw new InvalidArgumentException('يوجد طلب متداخل مع هذه الفترة.');
        query('INSERT INTO leave_requests (employee_id,leave_type_id,starts_on,ends_on,days,day_breakdown,reason,attachment_path) VALUES (?,?,?,?,?,?,?,?)',[$id,$type,$start,$end,$days,json_encode($breakdown,JSON_THROW_ON_ERROR),input('reason'),$attachment]);
        $requestId=(int)App::db()->lastInsertId();
        foreach (all("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code IN ('admin','hr') AND u.active=1") as $manager) notify_user((int)$manager['id'],'طلب إجازة جديد','طلب جديد يحتاج المراجعة',url('leaves'));
        audit('leave_request','leave_requests',$requestId); flash('success','تم إرسال طلب الإجازة.'); return;
    }
    if ($action === 'cancel') {
        $id=require_int(input('id'),'الطلب');
        query("UPDATE leave_requests SET status='cancelled' WHERE id=? AND employee_id=? AND status='pending'",[$id,$u['employee_id'] ?? 0]);
        audit('leave_cancel','leave_requests',$id); flash('success','تم إلغاء الطلب.'); return;
    }
    authorize('leave.manage');
    $id=require_int(input('id'),'الطلب');
    if ($action === 'supervisor_approve') {
        if ($u['role_code'] !== 'supervisor') throw new InvalidArgumentException('الإجراء مخصص للمشرف.');
        $r=one('SELECT lr.employee_id FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.id=? AND lr.status=? AND e.manager_id=?',[$id,'pending',$u['employee_id'] ?? 0]);
        if (!$r) throw new InvalidArgumentException('الطلب غير متاح.');
        query('UPDATE leave_requests SET supervisor_approved_by=? WHERE id=?',[$u['id'],$id]);
        audit('leave_supervisor_approve','leave_requests',$id); flash('success','تمت الموافقة الأولية.'); return;
    }
    if (!in_array($u['role_code'],['admin','hr'],true) || !in_array($action,['approve','reject'],true)) throw new InvalidArgumentException('الإجراء غير متاح.');
    $db=App::db(); $db->beginTransaction();
    try {
        $r=one('SELECT * FROM leave_requests WHERE id=? FOR UPDATE',[$id]);
        if (!$r || $r['status'] !== 'pending') throw new InvalidArgumentException('الطلب تمت معالجته.');
        if ($action === 'approve') {
            $leaveType=one('SELECT annual_days FROM leave_types WHERE id=?',[$r['leave_type_id']]);
            $breakdown=$r['day_breakdown']!==null?json_decode($r['day_breakdown'],true,512,JSON_THROW_ON_ERROR):leave_days_by_year($r['starts_on'],$r['ends_on']);
            foreach ($breakdown as $year=>$days) {
                if ((float)$leaveType['annual_days'] > 0) query('INSERT INTO leave_balances (employee_id,leave_type_id,balance_year,allocated) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE employee_id=employee_id',[$r['employee_id'],$r['leave_type_id'],$year,$leaveType['annual_days']]);
                $balance=one('SELECT * FROM leave_balances WHERE employee_id=? AND leave_type_id=? AND balance_year=? FOR UPDATE',[$r['employee_id'],$r['leave_type_id'],$year]);
                if ($balance && (float)$balance['allocated']-(float)$balance['used'] < $days) throw new InvalidArgumentException('رصيد الإجازة غير كاف.');
                if ($balance) query('UPDATE leave_balances SET used=used+? WHERE employee_id=? AND leave_type_id=? AND balance_year=?',[$days,$r['employee_id'],$r['leave_type_id'],$year]);
            }
        }
        query('UPDATE leave_requests SET status=?,decided_by=?,decided_at=NOW() WHERE id=?',[$action === 'approve'?'approved':'rejected',$u['id'],$id]);
        $employeeUser=one('SELECT id FROM users WHERE employee_id=?',[$r['employee_id']]);
        if ($employeeUser) notify_user((int)$employeeUser['id'],'تحديث طلب الإجازة',$action === 'approve'?'تم قبول طلبك':'تم رفض طلبك',url('leaves'));
        audit('leave_'.$action,'leave_requests',$id); $db->commit(); flash('success','تم تحديث الطلب.');
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
function leave_days_by_year(string $start, string $end): array
{
    $days=[]; $cursor=new DateTimeImmutable($start); $last=new DateTimeImmutable($end);
    while ($cursor<=$last) { $year=(int)$cursor->format('Y'); $days[$year]=($days[$year] ?? 0)+1; $cursor=$cursor->modify('+1 day'); }
    return $days;
}

function user_action(string $action): void
{
    authorize('users.manage');
    if ($action === 'create') {
        $email=input('email'); $password=input('password');
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 || !preg_match('/[A-Z]/',$password) || !preg_match('/[0-9]/',$password)) throw new InvalidArgumentException('أدخل بريدًا صحيحًا وكلمة مرور 12 حرفًا تتضمن حرفًا كبيرًا ورقمًا.');
        $role=require_int(input('role_id'),'الدور'); $emp=input('employee_id');
        query('INSERT INTO users (employee_id,role_id,email,password_hash) VALUES (?,?,?,?)',[$emp?require_int($emp,'الموظف'):null,$role,$email,password_hash($password,PASSWORD_DEFAULT)]);
        audit('user_create','users',(int)App::db()->lastInsertId()); flash('success','تم إنشاء المستخدم.'); return;
    }
    if ($action === 'toggle') {
        $id=require_int(input('id'),'المستخدم'); if ($id===current_user()['id']) throw new InvalidArgumentException('لا يمكنك تعطيل حسابك.');
        query('UPDATE users SET active=1-active WHERE id=?',[$id]); audit('user_toggle','users',$id); flash('success','تم تحديث الحساب.'); return;
    }
    throw new InvalidArgumentException('إجراء غير معروف.');
}
function settings_action(string $action): void
{
    authorize('settings.manage'); if ($action !== 'save') throw new InvalidArgumentException('إجراء غير معروف.');
    $allowed=['company_name','company_address','company_email','company_phone','work_start','work_end','work_days','currency','timezone','contract_terms','report_footer'];
    normalize_work_days(input('work_days'));
    foreach ($allowed as $key) { $v=input($key); if (strlen($v)>(in_array($key,['contract_terms','report_footer'],true)?3000:255)) throw new InvalidArgumentException('القيمة طويلة جدًا.'); query('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$v]); }
    audit('settings_update','settings'); flash('success','تم حفظ الإعدادات.');
}
function backup_action(): void
{
    authorize('backup.create');
    create_backup(); flash('success','تم حفظ النسخة داخل storage/backups.');
}
