<?php
declare(strict_types=1);

function workflow_status(string $status): string { return ['pending'=>'قيد المراجعة','approved'=>'معتمد','rejected'=>'مرفوض','draft'=>'مسودة','review'=>'قيد المراجعة','paid'=>'مصروف','open'=>'مفتوح','locked'=>'مقفل'][$status] ?? $status; }
function dashboard_workflow_summary(): void
{
    [$scope,$params]=employee_scope(); $items=[];
    $items[]=['تصحيحات تنتظر المراجعة',(int)scalar("SELECT COUNT(*) FROM attendance_corrections c JOIN employees e ON e.id=c.employee_id WHERE $scope AND c.status='pending'",$params),'corrections'];
    if (in_array(current_user()['role_code'],['admin','hr'],true)) {
        $items[]=['خدمات تنتظر الاعتماد',(int)scalar("SELECT COUNT(*) FROM service_requests WHERE status='pending'"),'services'];
        $items[]=['مهام متأخرة',(int)scalar('SELECT COUNT(*) FROM workflow_tasks WHERE completed_at IS NULL AND due_on<CURRENT_DATE'),'tasks'];
        $items[]=['وثائق منتهية',(int)scalar('SELECT COUNT(*) FROM documents WHERE expires_on<CURRENT_DATE'),can('settings.manage')?'alerts':'documents'];
    }
    echo '<div class="dashboard-workflows">'; foreach($items as [$label,$count,$page]) echo '<a href="'.e(url($page)).'"><span>'.e($label).'</span><strong>'.e($count).'</strong></a>'; echo '</div>';
}
function workflow_action_form(string $page,string $action,int $id,string $label): void
{
    row_action_form($page,$action,$id,$label,in_array($action,['reject','return','disable'],true)?'danger':'neutral');
}
function view_corrections(): void
{
    $u=require_login(); if (!can('attendance.self') && !can('attendance.manage')) authorize('attendance.self');
    echo '<div class="two-col"><section class="panel"><h2>طلب تصحيح حضور</h2><p class="muted">أدخل الوقت الصحيح وسبب التصحيح. يراجع الطلب المشرف أو الموارد البشرية.</p>';
    if (can('attendance.self') && $u['employee_id']) echo '<form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="request"><label>تاريخ الدوام<input type="date" name="work_date" max="'.e(date('Y-m-d')).'" required></label><div class="inline-actions"><label>الحضور<input type="time" name="check_in" required></label><label>الانصراف<input type="time" name="check_out"></label></div><label>سبب التصحيح<textarea name="reason" minlength="5" maxlength="500" required></textarea></label><button class="primary-button">إرسال الطلب</button></form>';
    else echo '<p class="muted">لإرسال طلب، اربط الحساب بموظف.</p>';
    echo '</section><section class="panel"><h2>طريقة المعالجة</h2><p class="muted">عند الاعتماد يُحدّث سجل حضور اليوم، ويُحفظ الطلب وقرار المراجع في سجل النشاط.</p><a class="outline-button" href="'.e(url('attendance')).'">فتح سجل الحضور</a></section></div>';
    [$scope,$params]=employee_scope();
    $rows=all("SELECT c.*,CONCAT(e.first_name,' ',e.last_name) employee_name FROM attendance_corrections c JOIN employees e ON e.id=c.employee_id WHERE $scope ORDER BY c.id DESC LIMIT 100",$params);
    echo '<section class="panel table-panel"><div class="panel-head"><h2>طلبات التصحيح</h2></div><div class="table-wrap"><table><thead><tr><th>الموظف</th><th>التاريخ</th><th>الوقت المطلوب</th><th>السبب</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="6" class="empty">لا توجد طلبات تصحيح.</td></tr>';
    foreach($rows as $row) { echo '<tr><td>'.e($row['employee_name']).'</td><td>'.e($row['work_date']).'</td><td>'.e(substr($row['check_in'],0,5).' – '.($row['check_out']?substr($row['check_out'],0,5):'—')).'</td><td class="wrap-cell">'.e($row['reason']).'</td><td>'.e(workflow_status($row['status'])).'</td><td class="actions-cell"><div class="row-actions">'; if ($row['status']==='pending' && can('attendance.manage') && (int)$u['employee_id']!==(int)$row['employee_id']) { workflow_action_form('corrections','approve',(int)$row['id'],'اعتماد'); workflow_action_form('corrections','reject',(int)$row['id'],'رفض'); } echo '</div></td></tr>'; }
    echo '</tbody></table></div></section>';
}
function view_services(): void
{
    $u=require_login();
    echo '<div class="two-col"><section class="panel"><h2>طلب خدمة</h2>';
    if ($u['employee_id']) echo '<form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="request"><label>الخدمة<select name="kind" required>'.options_html(['salary_letter'=>'خطاب تعريف بالراتب','experience_letter'=>'شهادة خبرة','contact_change'=>'تحديث بيانات التواصل'],'salary_letter').'</select></label><p class="muted">لتحديث بيانات التواصل، أدخل الجوال أو البريد أدناه. تُترك الحقول الأخرى فارغة.</p><label>الجوال الجديد<input name="phone" type="tel" maxlength="30"></label><label>البريد الجديد<input name="email" type="email"></label><button class="primary-button">إرسال الطلب</button></form>';
    else echo '<p class="muted">اربط الحساب بموظف لإرسال الطلبات.</p>';
    echo '</section><section class="panel"><h2>خطابات المنشأة</h2><p class="muted">تُنشأ نسخة ثابتة من بيانات الخطاب عند الاعتماد، وتظهر بخط Tajawal وشعار واثق للطباعة.</p></section></div>';
    [$scope,$params]=in_array($u['role_code'],['admin','hr'],true)?['1=1',[]]:['e.id=?',[(int)($u['employee_id'] ?? 0)]];
    $rows=all("SELECT s.*,CONCAT(e.first_name,' ',e.last_name) employee_name FROM service_requests s JOIN employees e ON e.id=s.employee_id WHERE $scope ORDER BY s.id DESC LIMIT 100",$params);
    echo '<section class="panel table-panel"><div class="panel-head"><h2>طلبات الخدمات</h2></div><div class="table-wrap"><table><thead><tr><th>الموظف</th><th>الخدمة</th><th>التاريخ</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="5" class="empty">لا توجد طلبات.</td></tr>';
    foreach($rows as $row) { echo '<tr><td>'.e($row['employee_name']).'</td><td>'.e(service_kind_label($row['kind'])).'</td><td>'.e($row['created_at']).'</td><td>'.e(workflow_status($row['status'])).'</td><td class="actions-cell"><div class="row-actions">'; if ($row['status']==='approved' && $row['kind']!=='contact_change') row_action_link(url('service_letter',['id'=>$row['id']]),'عرض الخطاب','primary'); if ($row['status']==='pending' && in_array($u['role_code'],['admin','hr'],true)) { workflow_action_form('services','approve',(int)$row['id'],'اعتماد'); workflow_action_form('services','reject',(int)$row['id'],'رفض'); } echo '</div></td></tr>'; }
    echo '</tbody></table></div></section>';
}
function view_service_letter(): void
{
    $id=require_int((string)($_GET['id'] ?? ''),'الخطاب');
    $row=one('SELECT * FROM service_requests WHERE id=?',[$id]);
    if (!$row || $row['status']!=='approved' || !$row['result_snapshot']) throw new InvalidArgumentException('الخطاب غير متاح.');
    $user=require_login();
    if (!in_array($user['role_code'],['admin','hr'],true) && (int)$user['employee_id']!==(int)$row['employee_id']) { http_response_code(403); throw new RuntimeException('لا يمكنك عرض هذا الخطاب.'); }
    $data=json_decode($row['result_snapshot'],true,512,JSON_THROW_ON_ERROR); $settings=$data['settings'];
    echo '<div class="document-toolbar"><a href="'.e(url('services')).'">→ العودة إلى الطلبات</a><button class="primary-button print-button" type="button" data-print>طباعة / حفظ PDF</button></div><article class="document-sheet">';
    print_letterhead('خدمات الموظفين',service_kind_label($row['kind']),'SR-'.$id,$settings);
    echo '<div class="document-intro"><p>لمن يهمه الأمر</p><h3>'.e(service_kind_label($row['kind'])).'</h3></div><p>تشهد '.e($settings['company_name']).' بأن الموظف '.e(trim($data['first_name'].' '.($data['father_name'] ?? '').' '.$data['last_name'])).' يعمل لديها وفق البيانات التالية:</p><div class="document-facts">';
    foreach (['employee_no'=>'الرقم الوظيفي','job_title'=>'المسمى الوظيفي','department'=>'القسم','hired_at'=>'تاريخ الالتحاق'] as $key=>$label) echo '<div class="document-fact"><span>'.e($label).'</span><strong>'.e($data[$key] ?? '—').'</strong></div>';
    if ($row['kind']==='salary_letter') { $salary=(float)$data['base_salary']+(float)$data['allowances']; echo '<div class="document-fact"><span>الراتب الأساسي</span><strong>'.e(number_format((float)$data['base_salary'],2).' '.$settings['currency']).'</strong></div><div class="document-fact"><span>الراتب مع البدلات</span><strong>'.e(number_format($salary,2).' '.$settings['currency']).'</strong></div>'; }
    echo '</div><p class="document-footnote">أُصدر هذا الخطاب بناءً على طلب الموظف بتاريخ '.e($data['issued_on']).'.</p>'; print_footer('اعتماد الإدارة',$settings); echo '</article>';
}
function view_tasks(): void
{
    authorize('employees.manage');
    $employees=field_options(['table'=>'employees']);
    echo '<div class="two-col"><section class="panel"><h2>بدء قائمة مهام</h2><form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="start"><label>الموظف<select name="employee_id" required><option value="">اختر</option>'.options_html($employees,'').'</select></label><label>المرحلة<select name="phase">'.options_html(['onboarding'=>'تعيين','offboarding'=>'مغادرة'],'onboarding').'</select></label><button class="primary-button">إنشاء المهام الأساسية</button></form></section><section class="panel"><h2>إضافة مهمة</h2><form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="add"><label>الموظف<select name="employee_id" required><option value="">اختر</option>'.options_html($employees,'').'</select></label><label>المرحلة<select name="phase">'.options_html(['onboarding'=>'تعيين','offboarding'=>'مغادرة'],'onboarding').'</select></label><label>المهمة<input name="title" minlength="3" maxlength="160" required></label><label>تاريخ الاستحقاق<input type="date" name="due_on"></label><button class="outline-button">إضافة</button></form></section></div>';
    [$scope,$params]=employee_scope();
    $rows=all("SELECT t.*,CONCAT(e.first_name,' ',e.last_name) employee_name FROM workflow_tasks t JOIN employees e ON e.id=t.employee_id WHERE $scope ORDER BY t.completed_at IS NOT NULL,t.due_on IS NULL,t.due_on,t.id DESC LIMIT 150",$params);
    echo '<section class="panel table-panel"><div class="panel-head"><h2>متابعة المهام</h2></div><div class="table-wrap"><table><thead><tr><th>الموظف</th><th>المرحلة</th><th>المهمة</th><th>الاستحقاق</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="6" class="empty">لا توجد مهام.</td></tr>';
    foreach($rows as $row) { echo '<tr><td>'.e($row['employee_name']).'</td><td>'.($row['phase']==='onboarding'?'تعيين':'مغادرة').'</td><td>'.e($row['title']).'</td><td>'.e($row['due_on'] ?? '—').'</td><td>'.($row['completed_at']?'مكتملة':'مفتوحة').'</td><td class="actions-cell"><div class="row-actions">'; if (!$row['completed_at']) workflow_action_form('tasks','complete',(int)$row['id'],'إكمال'); echo '</div></td></tr>'; }
    echo '</tbody></table></div></section>';
}
function view_payroll(): void
{
    authorize('salaries.manage'); staff_reviewer();
    $month=(string)($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/',$month)) $month=date('Y-m');
    $rows=all('SELECT s.*,CONCAT(e.first_name," ",e.last_name) employee_name FROM salaries s JOIN employees e ON e.id=s.employee_id WHERE s.pay_month=? ORDER BY e.first_name',[$month.'-01']);
    $locked=payroll_locked($month.'-01');
    echo '<section class="panel"><div class="panel-head"><div><h2>دورة رواتب '.e($month).'</h2><p class="muted">مراجعة من مستخدم آخر، ثم اعتماد وتسجيل الصرف وقفل الشهر. تأكيد الصرف هنا توثيق داخلي ولا يحوّل المال.</p></div><span class="badge-soft">'.($locked?'مقفل':'مفتوح').'</span></div><form method="get" class="filters"><input type="hidden" name="page" value="payroll"><label>الشهر<input type="month" name="month" value="'.e($month).'"></label><button class="outline-button">عرض</button></form><a class="outline-button" href="'.e(url('salaries')).'">إدارة المسودات</a>';
    if (!$locked && $rows && !array_filter($rows,fn($row)=>$row['status']!=='paid')) echo '<form method="post" class="inline-actions">'.csrf_field().'<input type="hidden" name="action" value="lock"><input type="hidden" name="pay_month" value="'.e($month).'-01"><button class="primary-button" data-confirm="قفل الشهر بعد صرف جميع الرواتب؟">قفل الشهر</button></form>';
    echo '</section><section class="panel table-panel"><div class="panel-head"><h2>رواتب الشهر</h2></div><div class="table-wrap"><table><thead><tr><th>الموظف</th><th>الصافي</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="4" class="empty">لا توجد رواتب لهذا الشهر.</td></tr>';
    foreach($rows as $row) { echo '<tr><td>'.e($row['employee_name']).'</td><td>'.e(number_format((float)$row['net_salary'],2)).'</td><td>'.e(workflow_status($row['status'])).'</td><td class="actions-cell"><div class="row-actions">'; row_action_link(url('salaries',['id'=>$row['id']]),'التفاصيل','primary'); if (!$locked) { if ($row['status']==='draft') workflow_action_form('payroll','submit',(int)$row['id'],'إرسال'); if ($row['status']==='review') { if ((int)$row['submitted_by']!==(int)current_user()['id']) workflow_action_form('payroll','approve',(int)$row['id'],'اعتماد'); workflow_action_form('payroll','return',(int)$row['id'],'إعادة'); } if ($row['status']==='approved') workflow_action_form('payroll','pay',(int)$row['id'],'تأكيد الصرف'); } echo '</div></td></tr>'; }
    echo '</tbody></table></div></section>';
}
function view_alerts(): void
{
    authorize('settings.manage');
    echo '<div class="two-col"><section class="panel"><h2>قاعدة تنبيه</h2><p class="muted">0 في يوم الانتهاء، وقيمة سالبة بعد الانتهاء.</p><form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="save"><label>النوع<select name="kind">'.options_html(['contract'=>'عقد','document'=>'وثيقة','training'=>'شهادة تدريب'],'contract').'</select></label><label>عدد الأيام قبل الانتهاء<input type="number" name="days_before" min="-30" max="365" value="30" required></label><label><input type="checkbox" name="notify_admin" checked> المدير العام</label><label><input type="checkbox" name="notify_hr" checked> الموارد البشرية</label><label><input type="checkbox" name="notify_employee" checked> الموظف</label><button class="primary-button">حفظ القاعدة</button></form></section><section class="panel"><h2>المواعيد المتأخرة</h2>';
    foreach ([['العقود','SELECT COUNT(*) FROM contracts WHERE ends_on<CURRENT_DATE'],['الوثائق','SELECT COUNT(*) FROM documents WHERE expires_on<CURRENT_DATE'],['الشهادات','SELECT COUNT(*) FROM trainings WHERE certificate_expires_on<CURRENT_DATE']] as [$label,$sql]) echo '<div class="balance-row"><span>'.e($label).'</span><strong>'.e(scalar($sql)).'</strong></div>';
    echo '</section></div><section class="panel table-panel"><div class="panel-head"><h2>قواعد التنبيه</h2></div><div class="table-wrap"><table><thead><tr><th>النوع</th><th>التوقيت</th><th>المستلمون</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>';
    foreach(all('SELECT * FROM alert_rules ORDER BY kind,days_before DESC') as $row) { $receivers=[]; if($row['notify_admin'])$receivers[]='المدير'; if($row['notify_hr'])$receivers[]='الموارد البشرية'; if($row['notify_employee'])$receivers[]='الموظف'; echo '<tr><td>'.e(['contract'=>'عقد','document'=>'وثيقة','training'=>'شهادة تدريب'][$row['kind']]).'</td><td>'.e($row['days_before']).' يوم</td><td>'.e(implode('، ',$receivers)).'</td><td>'.($row['active']?'مفعّلة':'معطلة').'</td><td class="actions-cell"><div class="row-actions">'; row_action_form('alerts','toggle',(int)$row['id'],$row['active']?'تعطيل':'تفعيل',$row['active']?'danger':'neutral'); echo '</div></td></tr>'; }
    echo '</tbody></table></div></section>';
}
function view_mfa(): void
{
    $u=require_login(); $dbUser=one('SELECT mfa_enabled FROM users WHERE id=?',[$u['id']]);
    echo '<section class="panel narrow-panel"><h2>التحقق الإضافي</h2>';
    if (!empty($_SESSION['mfa_recovery_once'])) { echo '<div class="alert alert-success"><strong>احفظ هذه الرموز الآن؛ لن تظهر مرة أخرى:</strong><div class="recovery-codes">'.e(implode('  ·  ',$_SESSION['mfa_recovery_once'])).'</div></div>'; unset($_SESSION['mfa_recovery_once']); }
    if ($dbUser['mfa_enabled']) echo '<p class="muted">التحقق الإضافي مفعل. لتعطيله، أدخل كلمة مرورك ورمز التطبيق أو رمز استعادة.</p><form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="disable"><label>كلمة المرور<input type="password" name="password" required></label><label>الرمز<input name="code" required></label><button class="outline-button">إيقاف التحقق الإضافي</button></form>';
    elseif (empty($_SESSION['mfa_enrollment_secret'])) echo '<p class="muted">أضف المفتاح إلى تطبيق مصادقة يدعم الرموز المتغيرة كل 30 ثانية.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="begin"><button class="primary-button">بدء الإعداد</button></form>';
    else echo '<p>مفتاح الإعداد:</p><code class="mfa-secret">'.e($_SESSION['mfa_enrollment_secret']).'</code><p class="muted">أدخل المفتاح يدويًا في تطبيق المصادقة، ثم أكد الرمز الحالي.</p><form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="enable"><label>كلمة المرور الحالية<input type="password" name="password" required></label><label>رمز التطبيق<input name="code" inputmode="numeric" pattern="[0-9]{6}" required></label><button class="primary-button">تفعيل التحقق</button></form>';
    echo '</section>';
}
function auth_simple_start(string $title,string $page): void
{
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | واثق</title><script src="assets/theme-init.js"></script><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/icons/phosphor-duotone/style.css"><link rel="stylesheet" href="assets/redesign.css"><link rel="stylesheet" href="assets/theme.css"></head><body class="login-body"><main class="login-main auth-simple"><form class="login-card" method="post" action="'.e(url($page)).'">'.csrf_field().'<div class="login-mark auth-logo"><img src="assets/logo.png" alt="واثق"></div><h1>'.e($title).'</h1>';
    if (isset($_SESSION['flash'])) { [$type,$message]=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert alert-'.e($type).'">'.e($message).'</div>'; }
}
function auth_simple_end(): void { echo '<p><a href="'.e(url('login')).'">العودة إلى تسجيل الدخول</a></p></form></main></body></html>'; }
function view_mfa_login(): void
{
    if (empty($_SESSION['pending_mfa'])) redirect('login');
    auth_simple_start('التحقق الإضافي','mfa_login');
    echo '<p class="muted">أدخل الرمز من تطبيق المصادقة أو رمز استعادة غير مستخدم.</p><label>رمز التحقق<input name="code" autocomplete="one-time-code" inputmode="numeric" required autofocus></label><button class="primary-button full">تأكيد الدخول</button>'; auth_simple_end();
}
function view_forgot_password(): void
{
    auth_simple_start('استعادة كلمة المرور','forgot_password');
    echo '<p class="muted">إذا كان البريد مهيأ في النظام، سنرسل رابطًا صالحًا لمدة 30 دقيقة.</p><label>البريد الإلكتروني<input type="email" name="email" required></label><button class="primary-button full">إرسال الرابط</button>'; auth_simple_end();
}
function view_reset_password(): void
{
    $token=(string)($_GET['token'] ?? '');
    auth_simple_start('كلمة مرور جديدة','reset_password');
    echo '<input type="hidden" name="token" value="'.e($token).'"><label>كلمة المرور الجديدة<input type="password" name="new_password" minlength="12" required></label><p class="muted">12 حرفًا على الأقل، مع حرف كبير ورقم.</p><button class="primary-button full">حفظ كلمة المرور</button>'; auth_simple_end();
}
