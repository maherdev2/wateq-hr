<?php
declare(strict_types=1);

function page_title(string $page): string
{
    return entities()[$page]['title'] ?? ['dashboard'=>'لوحة التحكم','attendance'=>'الحضور والانصراف','corrections'=>'تصحيح الحضور','services'=>'خدمات الموظفين','service_letter'=>'خطاب الموظف','tasks'=>'مهام الموظفين','payroll'=>'اعتماد الرواتب','alerts'=>'قواعد التنبيه','mfa'=>'التحقق الإضافي','calendar'=>'تقويم الدوام','leaves'=>'الإجازات','reports'=>'التقارير','users'=>'المستخدمون','settings'=>'الإعدادات','password'=>'تغيير كلمة المرور','notifications'=>'الإشعارات','profile'=>'ملفي الشخصي','employee_file'=>'ملف الموظف','salary_slip'=>'قسيمة الراتب','contract_print'=>'طباعة العقد'][$page] ?? 'النظام';
}
function icon_html(string $name): string
{
    return '<i class="ph-duotone ph-'.e($name).'" aria-hidden="true"></i>';
}
function nav_link(string $page, string $label, string $icon, ?string $permission = null): void
{
    if ($permission && !can($permission)) return;
    $current=(string)($_GET['page'] ?? 'dashboard');
    $current=['employee_file'=>'employees','service_letter'=>'services','salary_slip'=>'salaries','contract_print'=>'contracts'][$current] ?? $current;
    $active = $current === $page ? ' active' : '';
    echo '<a class="nav-item'.$active.'"'.($active?' aria-current="page"':'').' href="'.e(url($page)).'"><span class="nav-icon" aria-hidden="true">'.icon_html($icon).'</span><span>'.e($label).'</span></a>';
}
function nav_group(string $label, string $icon, array $items): void
{
    $items=array_values(array_filter($items,fn($item)=>!isset($item[3]) || can($item[3])));
    if (!$items) return;
    $current=(string)($_GET['page'] ?? 'dashboard');
    $current=['employee_file'=>'employees','service_letter'=>'services','salary_slip'=>'salaries','contract_print'=>'contracts'][$current] ?? $current;
    $active=in_array($current,array_column($items,0),true);
    echo '<details name="main-navigation" class="nav-group'.($active?' has-active':'').'"'.($active?' open':'').'><summary><span class="nav-icon" aria-hidden="true">'.icon_html($icon).'</span><span>'.e($label).'</span><span class="nav-chevron" aria-hidden="true">'.icon_html('caret-down').'</span></summary><div class="nav-group-items">';
    foreach ($items as [$page,$title,$itemIcon,$permission]) nav_link($page,$title,$itemIcon,$permission);
    echo '</div></details>';
}
function layout_start(string $page): void
{
    $u=current_user();
    $unread=(int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL',[$u['id']]);
    $title=page_title($page);
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | واثق</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css"><script src="assets/theme-init.js"></script><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/icons/phosphor-duotone/style.css"><link rel="stylesheet" href="assets/redesign.css"><link rel="stylesheet" href="assets/theme.css"><script defer src="assets/app.js"></script></head><body><a class="skip-link" href="#content-start">تجاوز القائمة إلى المحتوى</a><div class="app-shell"><aside class="sidebar" id="sidebar"><a class="brand" href="'.e(url()).'"><img src="assets/logo.png" alt="واثق"><span><strong>واثق</strong><small>إدارة الموظفين</small></span></a><div class="nav-caption">مساحة العمل</div><nav aria-label="التنقل الرئيسي">';
    nav_link('dashboard','نظرة عامة','squares-four','dashboard.view');
    nav_group('الفريق','users-three',[
        ['employees','الموظفون','identification-card','employees.manage'],['services','خدمات الموظفين','user-gear',null],['tasks','مهام الموظفين','check-square','employees.manage'],['reviews','تقييم الأداء','chart-line-up','reviews.manage'],['trainings','التدريب','graduation-cap','trainings.manage'],
    ]);
    nav_group('الدوام والإجازات','clock',[
        ['attendance','الحضور والانصراف','clock',null],['corrections','تصحيح الحضور','clock-counter-clockwise',null],['calendar','تقويم الدوام','calendar-blank',null],['leaves','الإجازات','calendar-check',null],['holidays','العطل الرسمية','sun','organization.manage'],['shifts','الورديات','arrows-clockwise','attendance.manage'],['leave_types','أنواع الإجازات','list-checks','leave.manage'],
    ]);
    nav_group('العقود والرواتب','wallet',[
        ['contracts','العقود','file-text','contracts.manage'],['documents','الوثائق','files','documents.manage'],['salaries','الرواتب','wallet','salaries.manage'],['payroll','اعتماد الرواتب','receipt','salaries.manage'],
    ]);
    nav_group('المنشأة','tree-structure',[
        ['departments','الأقسام','tree-structure','organization.manage'],['branches','الفروع','buildings','organization.manage'],['job_titles','المسميات الوظيفية','briefcase','organization.manage'],['grades','الدرجات','medal','organization.manage'],
    ]);
    nav_link('reports','التقارير','chart-bar','reports.view');
    nav_group('إدارة النظام','gear-six',[
        ['users','المستخدمون','user-gear','users.manage'],['settings','الإعدادات','gear-six','settings.manage'],['alerts','قواعد التنبيه','bell-ringing','settings.manage'],
    ]);
    echo '</nav><div class="sidebar-foot">واثق <span>© '.date('Y').'</span></div></aside><div class="main-wrap"><header class="topbar"><button class="icon-button menu-toggle" id="menuToggle" aria-label="فتح القائمة">' .icon_html('list').'</button><div class="crumb">مساحة العمل <span>›</span> '.e($title).'</div><div class="top-actions"><button class="icon-button" id="themeToggle" aria-label="تفعيل الوضع الداكن" aria-pressed="false">' .icon_html('moon-stars').'</button><a class="icon-button notification-button" href="'.e(url('notifications')).'" aria-label="الإشعارات">' .icon_html('bell').($unread?'<em>'.e($unread).'</em>':'').'</a><a class="user-chip" href="'.e(url('profile')).'"><span class="avatar">'.e(substr($u['email'],0,1)).'</span><span><strong>'.e($u['email']).'</strong><small>'.e($u['role_name']).'</small></span></a><form action="'.e(url('dashboard')).'" method="post">'.csrf_field().'<input type="hidden" name="action" value="logout"><button class="logout-button" title="تسجيل الخروج">' .icon_html('sign-out').'<span>خروج</span></button></form></div></header><main class="content"><div class="page-heading"><div><div class="eyebrow">نظام إدارة الموظفين</div><h1>'.e($title).'</h1></div><div class="date-pill">'.e(date('Y/m/d')).'</div></div>';
    echo '<span id="content-start" tabindex="-1"></span>';
    if (isset($_SESSION['flash'])) { [$type,$message]=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert alert-'.e($type).'">'.e($message).'</div>'; }
}
function layout_end(): void { echo '</main></div></div></body></html>'; }
function view_login(string $error=''): void
{
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تسجيل الدخول | واثق</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css"><script src="assets/theme-init.js"></script><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/icons/phosphor-duotone/style.css"><link rel="stylesheet" href="assets/redesign.css"><link rel="stylesheet" href="assets/theme.css"></head><body class="login-body"><div class="login-shell"><div class="login-aside"><div class="login-brand"><img src="assets/logo.png" alt="واثق"></div><div><p class="login-kicker">نظام إدارة الموظفين</p><h1>كل ما يخص فريقك،<br>في مكان واحد.</h1><p>إدارة واضحة للموظفين والحضور والإجازات والرواتب.</p></div><div class="login-bottom">واثق · إدارة أكثر وضوحًا</div></div><div class="login-main"><form class="login-card" method="post" action="'.e(url('login')).'">'.csrf_field().'<div class="login-mark"><img src="assets/logo.png" alt="واثق"></div><p class="eyebrow">مرحبًا بعودتك</p><h2>تسجيل الدخول</h2><p class="muted">أدخل بيانات حسابك للوصول إلى مساحة العمل.</p>';
    if ($error) echo '<div class="alert alert-danger">'.e($error).'</div>';
    if (isset($_SESSION['flash'])) { [$type,$message]=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert alert-'.e($type).'">'.e($message).'</div>'; }
    echo '<label>البريد الإلكتروني<input type="email" name="email" autocomplete="username" required autofocus></label><label>كلمة المرور<input type="password" name="password" autocomplete="current-password" required></label><button class="primary-button full">الدخول إلى النظام <span>←</span></button><p class="login-note"><a href="'.e(url('forgot_password')).'">نسيت كلمة المرور؟</a><br>إن لم يكن لديك حساب، تواصل مع مدير النظام.</p></form></div></div></body></html>';
}
function stat_card(string $label, mixed $value, string $icon, string $tone='blue'): void { echo '<div class="stat-card"><div class="stat-top"><span>'.e($label).'</span><span class="stat-icon '.$tone.'">'.$icon.'</span></div><strong>'.e($value).'</strong></div>'; }
function view_dashboard(): void
{
    authorize('dashboard.view');
    if (current_user()['role_code']==='employee') { view_employee_dashboard(); return; }
    view_manager_dashboard();
}

function dashboard_kpi(string $label, mixed $value, string $note, string $tone='navy', ?string $page=null): void
{
    $tag=$page?'a':'div'; $href=$page?' href="'.e(url($page)).'"':'';
    $icon=['employees'=>'users-three','attendance'=>'clock','leaves'=>'calendar-check','contracts'=>'file-text'][$page ?? ''] ?? 'chart-bar';
    echo '<'.$tag.' class="kpi-card '.$tone.'"'.$href.'><span class="kpi-icon">'.icon_html($icon).'</span><span>'.e($label).'</span><strong>'.e($value).'</strong><small>'.e($note).'</small></'.$tag.'>';
}

function dashboard_action_label(string $action): string
{
    return ['login'=>'تسجيل دخول','logout'=>'تسجيل خروج','login_failed'=>'محاولة دخول غير ناجحة','create'=>'إضافة سجل','update'=>'تعديل سجل','delete'=>'حذف سجل','attendance_manual'=>'تسجيل حضور يدوي','check_in'=>'تسجيل حضور','check_out'=>'تسجيل انصراف','leave_request'=>'طلب إجازة','leave_approve'=>'قبول إجازة','leave_reject'=>'رفض إجازة','leave_cancel'=>'إلغاء إجازة','settings_update'=>'تحديث الإعدادات','user_create'=>'إنشاء مستخدم','user_toggle'=>'تغيير حالة مستخدم','contract_submit'=>'إرسال عقد للمراجعة','contract_approve'=>'اعتماد عقد','contract_return'=>'إعادة عقد للمسودة','backup_create'=>'إنشاء نسخة احتياطية','backup_verify'=>'اختبار استعادة نسخة','correction_request'=>'طلب تصحيح حضور','correction_approve'=>'اعتماد تصحيح حضور','correction_reject'=>'رفض تصحيح حضور','service_request'=>'طلب خدمة موظف','service_approve'=>'اعتماد خدمة موظف','service_reject'=>'رفض خدمة موظف','tasks_start'=>'بدء مهام موظف','task_add'=>'إضافة مهمة','task_complete'=>'إكمال مهمة','payroll_submit'=>'إرسال راتب للمراجعة','payroll_approve'=>'اعتماد راتب','payroll_return'=>'إعادة راتب للمسودة','payroll_pay'=>'تأكيد صرف راتب','payroll_lock'=>'قفل شهر رواتب','alert_rule_save'=>'تعديل قاعدة تنبيه','alert_rule_toggle'=>'تغيير حالة تنبيه','mfa_enable'=>'تفعيل التحقق الإضافي','mfa_disable'=>'تعطيل التحقق الإضافي','password_reset'=>'استعادة كلمة المرور'][$action] ?? 'نشاط في النظام';
}

function view_manager_dashboard(): void
{
    $user=current_user(); [$scope,$params]=employee_scope();
    $total=(int)scalar("SELECT COUNT(*) FROM employees e WHERE $scope",$params);
    $present=(int)scalar("SELECT COUNT(*) FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE $scope AND a.work_date=CURRENT_DATE AND a.status IN ('present','late')",$params);
    $pending=(int)scalar("SELECT COUNT(*) FROM leave_requests l JOIN employees e ON e.id=l.employee_id WHERE $scope AND l.status='pending'",$params);
    $expiring=(int)scalar("SELECT COUNT(*) FROM contracts c JOIN employees e ON e.id=c.employee_id WHERE $scope AND c.ends_on BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE,INTERVAL 30 DAY)",$params);
    echo '<section class="welcome dashboard-welcome"><div><p>ملخص يوم العمل</p><h2>فريقك في صورة واضحة</h2><span>بيانات الحضور والطلبات والعقود حتى '.e(date('Y/m/d')).'</span><div class="welcome-actions"><a href="'.e(url('attendance')).'">سجل الحضور</a><a href="'.e(url('reports')).'">التقارير</a></div></div><img src="assets/logo.png" alt="واثق"></section>';
    echo '<div class="overview-grid">';
    dashboard_kpi('إجمالي الموظفين',$total,'جميع الموظفين ضمن نطاقك','navy',can('employees.manage')?'employees':null);
    dashboard_kpi('الحضور اليوم',$present,'حضور مسجل حتى الآن','blue','attendance');
    dashboard_kpi('طلبات الإجازة المعلقة',$pending,'تنتظر قرارًا','amber','leaves');
    dashboard_kpi('عقود تنتهي خلال 30 يومًا',$expiring,'تحتاج متابعة','red',can('contracts.manage')?'contracts':null);
    echo '</div><div class="metrics-strip">';
    foreach ([
        ['على رأس العمل',"SELECT COUNT(*) FROM employees e WHERE $scope AND e.status='active'",$params],
        ['في إجازة',"SELECT COUNT(*) FROM employees e WHERE $scope AND e.status='leave'",$params],
        ['غياب مسجل اليوم',"SELECT COUNT(*) FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE $scope AND a.work_date=CURRENT_DATE AND a.status='absent'",$params],
        ['الأقسام','SELECT COUNT(*) FROM departments',[]],
        ['عقود قيد المراجعة',"SELECT COUNT(*) FROM contracts c JOIN employees e ON e.id=c.employee_id WHERE $scope AND c.status='review'",$params]
    ] as [$label,$sql,$queryParams]) echo '<div><span>'.e($label).'</span><strong>'.e(scalar($sql,$queryParams)).'</strong></div>';
    echo '</div>'; dashboard_workflow_summary(); echo '<div class="dashboard-main"><section class="panel"><div class="panel-head"><div><h2>سجل الحضور خلال 7 أيام</h2><p class="panel-subtitle">الأيام بلا تسجيل لا تُعد غيابًا</p></div><a href="'.e(url('attendance')).'">التفاصيل</a></div>';
    $trend=all("SELECT a.work_date,SUM(a.status IN ('present','late')) present_count,SUM(a.status='absent') absent_count FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE $scope AND a.work_date>=DATE_SUB(CURRENT_DATE,INTERVAL 6 DAY) GROUP BY a.work_date ORDER BY a.work_date DESC",$params);
    if (!$trend) echo '<div class="empty">لا توجد قيود حضور في الأيام السبعة الأخيرة.</div>';
    foreach ($trend as $day) {
        $attendance=(int)$day['present_count']; $absence=(int)$day['absent_count'];
        echo '<div class="trend-row"><time>'.e(date('d/m',strtotime($day['work_date']))).'</time><div class="trend-track"><span class="trend-present" style="width:'.e(round($attendance/max(1,$total)*100)).'%"></span><span class="trend-absent" style="width:'.e(round($absence/max(1,$total)*100)).'%"></span></div><div class="trend-numbers"><b>'.e($attendance).'</b><b>'.e($absence).'</b></div></div>';
    }
    echo '<div class="trend-legend"><span><i class="trend-present"></i> حضور</span><span><i class="trend-absent"></i> غياب مسجل</span></div></section>';
    echo '<section class="panel"><div class="panel-head"><h2>مواعيد تحتاج متابعة</h2></div>';
    $alerts=all("SELECT 'عقد' kind,CONCAT(e.first_name,' ',e.last_name) name,c.ends_on expires FROM contracts c JOIN employees e ON e.id=c.employee_id WHERE $scope AND c.ends_on BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY) UNION ALL SELECT 'وثيقة',d.title,d.expires_on FROM documents d JOIN employees e ON e.id=d.employee_id WHERE $scope AND d.expires_on BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY) ORDER BY expires LIMIT 6",[...$params,...$params]);
    if (!$alerts) echo '<div class="empty">لا توجد عقود أو وثائق تنتهي خلال 90 يومًا.</div>';
    foreach ($alerts as $alert) {
        $days=(int)((strtotime($alert['expires'])-strtotime(date('Y-m-d')))/86400);
        echo '<div class="alert-row"><span class="alert-icon">'.e($days).'</span><div><strong>'.e($alert['kind'].' · '.$alert['name']).'</strong><small>ينتهي في '.e($alert['expires']).' · بعد '.e($days).' يوم</small></div></div>';
    }
    echo '</section></div><div class="dashboard-grid"><section class="panel"><div class="panel-head"><h2>الموظفون حسب القسم</h2></div>';
    $departments=all("SELECT d.name,COUNT(e.id) total FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND $scope GROUP BY d.id ORDER BY total DESC",$params);
    $max=max(1,...array_column($departments,'total'));
    foreach ($departments as $department) echo '<div class="bar-row"><span>'.e($department['name']).'</span><div class="bar-track"><i style="width:'.e(round($department['total']/$max*100)).'%"></i></div><strong>'.e($department['total']).'</strong></div>';
    echo '</section><section class="panel"><div class="panel-head"><h2>آخر العمليات</h2></div>';
    $logs=in_array($user['role_code'],['admin','hr'],true)?all('SELECT action,details,created_at FROM activity_logs ORDER BY id DESC LIMIT 5'):all('SELECT action,details,created_at FROM activity_logs WHERE user_id=? ORDER BY id DESC LIMIT 5',[$user['id']]);
    if (!$logs) echo '<div class="empty">لا توجد عمليات حديثة.</div>';
    foreach($logs as $log) echo '<div class="activity-item"><span class="activity-dot"></span><div><strong>'.e(dashboard_action_label($log['action'])).'</strong><small>'.e($log['created_at']).'</small></div></div>';
    echo '</section></div><section class="panel table-panel dashboard-new-hires"><div class="panel-head"><h2>آخر المنضمين للفريق</h2>'.(can('employees.manage')?'<a href="'.e(url('employees')).'">عرض الموظفين</a>':'').'</div><div class="table-wrap"><table><thead><tr><th>الموظف</th><th>القسم</th><th>تاريخ التعيين</th></tr></thead><tbody>';
    foreach(all("SELECT e.first_name,e.last_name,e.hired_at,d.name department FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE $scope ORDER BY e.hired_at DESC LIMIT 5",$params) as $row) echo '<tr><td>'.e($row['first_name'].' '.$row['last_name']).'</td><td>'.e($row['department'] ?? '—').'</td><td>'.e($row['hired_at']).'</td></tr>';
    echo '</tbody></table></div></section>';
}

function view_employee_dashboard(): void
{
    $user=current_user(); $id=(int)($user['employee_id'] ?? 0);
    $employee=$id?one('SELECT first_name,last_name FROM employees WHERE id=?',[$id]):null;
    $attendance=$id?one('SELECT status,check_in,check_out FROM attendance WHERE employee_id=? AND work_date=CURRENT_DATE',[$id]):null;
    $balance=$id?one("SELECT allocated-used remaining FROM leave_balances WHERE employee_id=? AND balance_year=YEAR(CURRENT_DATE) AND leave_type_id=(SELECT id FROM leave_types WHERE name='سنوية' LIMIT 1)",[$id]):null;
    $pending=$id?(int)scalar("SELECT COUNT(*) FROM leave_requests WHERE employee_id=? AND status='pending'",[$id]):0;
    echo '<section class="welcome dashboard-welcome"><div><p>مساحتي اليوم</p><h2>'.e(trim(($employee['first_name'] ?? '').' '.($employee['last_name'] ?? ''))).'</h2><span>متابعة حضورك وإجازاتك من مكان واحد</span><div class="welcome-actions"><a href="'.e(url('attendance')).'">الحضور والانصراف</a><a href="'.e(url('leaves')).'">طلب إجازة</a></div></div><img src="assets/logo.png" alt="واثق"></section>';
    echo '<div class="overview-grid employee-grid">';
    dashboard_kpi('حالة الحضور اليوم',$attendance?report_translate($attendance['status']):'لم يُسجل','فتح سجل الحضور','navy','attendance');
    dashboard_kpi('رصيد الإجازة السنوية',$balance?number_format((float)$balance['remaining'],0):'—','يوم متاح','blue','leaves');
    dashboard_kpi('طلبات قيد المراجعة',$pending,'يمكنك متابعة حالتها','amber','leaves');
    echo '</div><div class="dashboard-grid"><section class="panel table-panel"><div class="panel-head"><h2>آخر حضور</h2><a href="'.e(url('attendance')).'">السجل الكامل</a></div><div class="table-wrap"><table><thead><tr><th>التاريخ</th><th>الحضور</th><th>الانصراف</th><th>الحالة</th></tr></thead><tbody>';
    $rows=$id?all('SELECT work_date,check_in,check_out,status FROM attendance WHERE employee_id=? ORDER BY work_date DESC LIMIT 5',[$id]):[];
    if (!$rows) echo '<tr><td colspan="4" class="empty">لم يُسجل حضور بعد.</td></tr>';
    foreach($rows as $row) echo '<tr><td>'.e($row['work_date']).'</td><td>'.e($row['check_in'] ?? '—').'</td><td>'.e($row['check_out'] ?? '—').'</td><td>'.e(report_translate($row['status'])).'</td></tr>';
    echo '</tbody></table></div></section><section class="panel"><div class="panel-head"><h2>آخر طلبات الإجازة</h2><a href="'.e(url('leaves')).'">عرض الطلبات</a></div>';
    $leaves=$id?all('SELECT starts_on,ends_on,status FROM leave_requests WHERE employee_id=? ORDER BY id DESC LIMIT 4',[$id]):[];
    if (!$leaves) echo '<div class="empty">لا توجد طلبات إجازة بعد.</div>';
    foreach($leaves as $leave) echo '<div class="leave-summary"><div><strong>'.e($leave['starts_on']).' — '.e($leave['ends_on']).'</strong><small>طلب إجازة</small></div><span class="badge-soft">'.e(report_translate($leave['status'])).'</span></div>';
    echo '</section></div>';
}

function entity_rows(string $page, array $def, int $limit=15, int $offset=0): array
{
    $table=$def['table']; $where=['1=1']; $params=[];
    $search=trim((string)($_GET['q'] ?? ''));
    if ($search !== '') {
        if ($page==='employees') { $where[]='(employee_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR national_id LIKE ?)'; $params=array_fill(0,4,'%'.$search.'%'); }
        else { $column=isset($def['fields']['name'])?'name':(isset($def['fields']['title'])?'title':(isset($def['fields']['course_name'])?'course_name':null)); if($column){$where[]="$column LIKE ?";$params[]='%'.$search.'%';} }
    }
    if ($page==='employees') {
        foreach (['department_id','status','nationality'] as $f) if (!empty($_GET[$f])) { $where[]="$f=?"; $params[]=$_GET[$f]; }
    }
    $sql=' FROM '.$table.' WHERE '.implode(' AND ',$where);
    $total=(int)scalar('SELECT COUNT(*)'.$sql,$params);
    $rows=all('SELECT *'.$sql.' ORDER BY id DESC LIMIT '.(int)$limit.' OFFSET '.(int)$offset,$params);
    return [$rows,$total];
}
function field_cell(string $name, mixed $value, array $field): string
{
    if ($field['type']==='file') return $value ? '<span class="badge-soft">مرفق</span>' : '—';
    if ($name==='status') return '<span class="badge-soft">'.e(isset($field['options'])?option_label($name,$value,$field):contract_status_label((string)$value)).'</span>';
    return e(option_label($name,$value,$field));
}
function view_entity(string $page): void
{
    $def=entities()[$page]; authorize($def['permission']);
    $id=(int)($_GET['id'] ?? 0); $edit=isset($_GET['edit']);
    if ($id || $edit) { entity_form($page,$def,$id,$edit); return; }
    $p=max(1,(int)($_GET['p'] ?? 1)); [$rows,$total]=entity_rows($page,$def,15,($p-1)*15);
    echo '<div class="toolbar"><form class="search-form" method="get"><input type="hidden" name="page" value="'.e($page).'"><input name="q" placeholder="بحث في '.e($def['title']).'" value="'.e($_GET['q'] ?? '').'"><button>بحث</button></form><div class="toolbar-actions"><a class="outline-button" href="'.e(url($page,['export'=>'csv'])).'">تصدير Excel</a><a class="primary-button" href="'.e(url($page,['edit'=>1])).'">+ إضافة جديد</a></div></div>';
    if ($page==='employees') echo '<form class="filters" method="get"><input type="hidden" name="page" value="employees"><select name="department_id"><option value="">كل الأقسام</option>'.options_html(field_options(['table'=>'departments']),$_GET['department_id'] ?? '').'</select><select name="status"><option value="">كل الحالات</option>'.options_html($def['fields']['status']['options'],$_GET['status'] ?? '').'</select><input name="nationality" placeholder="الجنسية" value="'.e($_GET['nationality'] ?? '').'"><button class="outline-button">تصفية</button></form>';
    echo '<section class="panel table-panel"><div class="panel-head"><h2>'.e($def['title']).'</h2><span class="count">'.e($total).' سجل</span></div><div class="table-wrap"><table><thead><tr>';
    foreach ($def['list'] as $key) echo '<th>'.e($def['fields'][$key]['label'] ?? ($key==='status'?'الحالة':$key)).'</th>';
    echo '<th class="actions-heading">إجراءات</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="'.e(count($def['list'])+1).'" class="empty">لا توجد سجلات. أضف أول سجل من الزر أعلاه.</td></tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($def['list'] as $key) echo '<td>'.field_cell($key,$row[$key] ?? null,$def['fields'][$key] ?? ['type'=>'text']).'</td>';
        echo '<td class="actions-cell"><div class="row-actions">';
        row_action_link(url($page,['id'=>$row['id']]),'عرض','primary');
        if ($page!=='salaries' || ($row['status']==='draft' && !payroll_locked($row['pay_month']))) row_action_link(url($page,['id'=>$row['id'],'edit'=>1]),'تعديل');
        if ($page==='employees') row_action_link(url('employee_file',['id'=>$row['id']]),'الملف الموحد');
        if ($page==='contracts') row_action_link(url('contract_print',['id'=>$row['id']]),'طباعة');
        if ($page!=='salaries' || ($row['status']==='draft' && !payroll_locked($row['pay_month']))) row_action_form($page,'delete',(int)$row['id'],'حذف','danger','حذف هذا السجل نهائيًا؟');
        echo '</div></td></tr>';
    }
    echo '</tbody></table></div>';
    $pages=(int)ceil($total/15); if ($pages>1) { echo '<div class="pagination">'; for($i=1;$i<=$pages;$i++) echo '<a class="'.($i===$p?'current':'').'" href="'.e(url($page,['p'=>$i,'q'=>$_GET['q'] ?? ''])).'">'.$i.'</a>'; echo '</div>'; }
    echo '</section>';
}

function view_attendance(): void
{
    $u=require_login(); if (!can('attendance.self') && !can('attendance.manage')) authorize('attendance.self');
    $today=date('Y-m-d'); $own=$u['employee_id'] ? one('SELECT * FROM attendance WHERE employee_id=? AND work_date=?',[$u['employee_id'],$today]) : null;
    echo '<div class="two-col"><section class="panel"><div class="panel-head"><h2>دوامي اليوم</h2><span class="date-pill">'.e($today).'</span></div><div class="clock">'.e(date('H:i')).'</div><p class="muted">'.($own ? 'الحضور: '.e($own['check_in'] ?? '—').' · الانصراف: '.e($own['check_out'] ?? '—') : 'لم يتم تسجيل حضورك اليوم.').'</p>';
    if (can('attendance.self') && $u['employee_id']) echo '<div class="inline-actions">'.(!$own?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="check_in"><button class="primary-button">تسجيل الحضور</button></form>':'').($own && !$own['check_out']?'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="check_out"><button class="outline-button">تسجيل الانصراف</button></form>':'').'</div>';
    echo '</section>';
    if (can('attendance.manage')) {
        echo '<section class="panel"><div class="panel-head"><h2>تسجيل يدوي</h2></div><form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="manual"><select name="employee_id" required><option value="">اختر الموظف</option>'.options_html(field_options(['table'=>'employees']),'').'</select><input type="date" name="work_date" value="'.e($today).'" required><select name="status">'.options_html(['present'=>'حاضر','late'=>'متأخر','absent'=>'غائب','leave'=>'إجازة'],'present').'</select><div class="inline-actions"><input type="time" name="check_in" aria-label="الحضور"><input type="time" name="check_out" aria-label="الانصراف"></div><input name="notes" placeholder="ملاحظات"><button class="primary-button">حفظ الحضور</button></form></section>';
    } else echo '<section class="panel"><div class="panel-head"><h2>ساعات العمل</h2></div><p class="muted">تابع سجل حضورك أدناه، وقدّم طلب إجازة عند الحاجة.</p><a class="outline-button" href="'.e(url('leaves')).'">طلب إجازة</a></section>';
    echo '</div><section class="panel table-panel"><div class="panel-head"><h2>سجل الحضور</h2><div class="toolbar-actions">'.(can('reports.view')?'<a class="outline-button" href="'.e(url('reports',['type'=>'attendance','export'=>'csv'])).'">تصدير Excel</a>':'').'<button class="outline-button print-button" type="button" data-print>طباعة / PDF</button></div></div>';
    [$scope,$params]=employee_scope();
    $month=(string)($_GET['month'] ?? date('Y-m')); if (!preg_match('/^\d{4}-\d{2}$/',$month)) $month=date('Y-m');
    echo '<form method="get" class="filters"><input type="hidden" name="page" value="attendance"><input type="month" name="month" value="'.e($month).'"><button class="outline-button">تصفية</button></form><div class="table-wrap"><table><thead><tr><th>الموظف</th><th>التاريخ</th><th>الحضور</th><th>الانصراف</th><th>الساعات</th><th>التأخر</th><th>الإضافي</th><th>الحالة</th></tr></thead><tbody>';
    $rows=all("SELECT a.*,CONCAT(e.first_name,' ',e.last_name) employee_name FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE $scope AND a.work_date LIKE ? ORDER BY a.work_date DESC LIMIT 100",[...$params,$month.'%']);
    if (!$rows) echo '<tr><td colspan="8" class="empty">لا توجد سجلات لهذا الشهر.</td></tr>';
    foreach ($rows as $r) {
        $hours=$r['check_in'] && $r['check_out'] ? round((strtotime($r['check_out'])-strtotime($r['check_in']))/3600,2) : '—';
        echo '<tr><td>'.e($r['employee_name']).'</td><td>'.e($r['work_date']).'</td><td>'.e($r['check_in']).'</td><td>'.e($r['check_out']).'</td><td>'.e($hours).'</td><td>'.e($r['late_minutes']).'</td><td>'.e($r['overtime_minutes']).'</td><td><span class="badge-soft">'.e(['present'=>'حاضر','late'=>'متأخر','absent'=>'غائب','leave'=>'إجازة'][$r['status']] ?? $r['status']).'</span></td></tr>';
    }
    echo '</tbody></table></div></section>';
}

function view_leaves(): void
{
    $u=require_login(); if (!can('leave.self') && !can('leave.manage')) authorize('leave.self');
    echo '<div class="two-col"><section class="panel"><div class="panel-head"><h2>طلب إجازة</h2></div><p class="muted">تُحسب الإجازات المحددة بأيام العمل حسب الوردية والعطل المسجلة. <a href="'.e(url('calendar')).'">عرض التقويم</a></p>';
    if (can('leave.self') && $u['employee_id']) echo '<form method="post" enctype="multipart/form-data" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="request"><label>نوع الإجازة<select name="leave_type_id" required><option value="">اختر النوع</option>'.options_html(field_options(['table'=>'leave_types']),'').'</select></label><div class="inline-actions"><label>من<input type="date" name="starts_on" required></label><label>إلى<input type="date" name="ends_on" required></label></div><label>السبب<textarea name="reason" rows="2"></textarea></label><label>مرفق (عند الحاجة)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"></label><button class="primary-button">إرسال الطلب</button></form>';
    else echo '<p class="muted">حسابك غير مرتبط بموظف. يمكن إدارة الطلبات من الجدول أدناه.</p>';
    echo '</section><section class="panel"><div class="panel-head"><h2>رصيد الإجازات</h2></div>';
    if ($u['employee_id']) foreach (all('SELECT lt.name,lb.allocated,lb.used FROM leave_balances lb JOIN leave_types lt ON lt.id=lb.leave_type_id WHERE lb.employee_id=? AND lb.balance_year=YEAR(CURRENT_DATE)',[$u['employee_id']]) as $b) echo '<div class="balance-row"><span>'.e($b['name']).'</span><strong>'.e((float)$b['allocated']-(float)$b['used']).' يوم</strong></div>';
    echo '</section></div><section class="panel table-panel"><div class="panel-head"><h2>طلبات الإجازة</h2><div class="toolbar-actions">'.(can('reports.view')?'<a class="outline-button" href="'.e(url('reports',['type'=>'leaves','export'=>'csv'])).'">تصدير Excel</a>':'').'<button class="outline-button print-button" type="button" data-print>طباعة / PDF</button></div></div><div class="table-wrap"><table><thead><tr><th>الموظف</th><th>النوع</th><th>الفترة</th><th>الأيام</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>';
    [$scope,$params]=employee_scope();
    $rows=all("SELECT l.*,CONCAT(e.first_name,' ',e.last_name) employee_name,t.name type_name FROM leave_requests l JOIN employees e ON e.id=l.employee_id JOIN leave_types t ON t.id=l.leave_type_id WHERE $scope ORDER BY l.id DESC LIMIT 100",$params);
    if (!$rows) echo '<tr><td colspan="6" class="empty">لا توجد طلبات إجازة.</td></tr>';
    foreach ($rows as $r) {
        echo '<tr><td>'.e($r['employee_name']).'</td><td>'.e($r['type_name']).'</td><td>'.e($r['starts_on'].' — '.$r['ends_on']).'</td><td>'.e($r['days']).'</td><td><span class="badge-soft">'.e(['pending'=>'قيد المراجعة','approved'=>'مقبول','rejected'=>'مرفوض','cancelled'=>'ملغى'][$r['status']]).'</span></td><td class="actions-cell"><div class="row-actions">';
        if ($r['status']==='pending') {
            if ($r['employee_id']===$u['employee_id']) action_form('leaves','cancel',(int)$r['id'],'إلغاء');
            if (can('leave.manage') && in_array($u['role_code'],['admin','hr'],true)) { action_form('leaves','approve',(int)$r['id'],'قبول'); action_form('leaves','reject',(int)$r['id'],'رفض'); }
            if ($u['role_code']==='supervisor' && !$r['supervisor_approved_by']) action_form('leaves','supervisor_approve',(int)$r['id'],'موافقة أولية');
        }
        echo '</div></td></tr>';
    }
    echo '</tbody></table></div></section>';
}
function row_action_link(string $href,string $label,string $tone='neutral'): void
{
    echo '<a class="action-pill action-pill--'.e($tone).'" href="'.e($href).'">'.e($label).'</a>';
}
function row_action_form(string $page,string $action,int $id,string $label,string $tone='neutral',?string $confirmation=null): void
{
    echo '<form method="post" action="'.e(url($page)).'"'.($confirmation?' data-confirm="'.e($confirmation).'"':'').'>'.csrf_field().'<input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="id" value="'.e($id).'"><button type="submit" class="action-pill action-pill--'.e($tone).'">'.e($label).'</button></form>';
}
function action_form(string $page,string $action,int $id,string $label): void
{
    row_action_form($page,$action,$id,$label,in_array($action,['reject','cancel','disable'],true)?'danger':'neutral');
}

function report_definitions(): array
{
    return [
        'employees'=>['title'=>'جميع الموظفين','headers'=>['الرقم','الموظف','القسم','الحالة','تاريخ التعيين'],'sql'=>"SELECT e.employee_no,CONCAT(e.first_name,' ',e.last_name),COALESCE(d.name,''),e.status,e.hired_at FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE %s ORDER BY e.id DESC"],
        'attendance'=>['title'=>'الحضور والغياب','headers'=>['الموظف','التاريخ','الحضور','الانصراف','الحالة','دقائق التأخر'],'sql'=>"SELECT CONCAT(e.first_name,' ',e.last_name),a.work_date,a.check_in,a.check_out,a.status,a.late_minutes FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE %s ORDER BY a.work_date DESC LIMIT 1000"],
        'late'=>['title'=>'التأخر','headers'=>['الموظف','التاريخ','دقائق التأخر'],'sql'=>"SELECT CONCAT(e.first_name,' ',e.last_name),a.work_date,a.late_minutes FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE %s AND a.late_minutes>0 ORDER BY a.work_date DESC LIMIT 1000"],
        'leaves'=>['title'=>'الإجازات','headers'=>['الموظف','النوع','البداية','النهاية','الأيام','الحالة'],'sql'=>"SELECT CONCAT(e.first_name,' ',e.last_name),t.name,l.starts_on,l.ends_on,l.days,l.status FROM leave_requests l JOIN employees e ON e.id=l.employee_id JOIN leave_types t ON t.id=l.leave_type_id WHERE %s ORDER BY l.id DESC LIMIT 1000"],
        'contracts'=>['title'=>'العقود المنتهية والقريبة','headers'=>['الموظف','رقم العقد','الانتهاء'],'sql'=>"SELECT CONCAT(e.first_name,' ',e.last_name),c.contract_no,c.ends_on FROM contracts c JOIN employees e ON e.id=c.employee_id WHERE %s AND c.ends_on<=DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY) ORDER BY c.ends_on"],
        'documents'=>['title'=>'الوثائق المنتهية والقريبة','headers'=>['الموظف','الوثيقة','الانتهاء'],'sql'=>"SELECT CONCAT(e.first_name,' ',e.last_name),d.title,d.expires_on FROM documents d JOIN employees e ON e.id=d.employee_id WHERE %s AND d.expires_on<=DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY) ORDER BY d.expires_on"],
        'salaries'=>['title'=>'الرواتب','headers'=>['الموظف','الشهر','الصافي','الصرف'],'sql'=>"SELECT CONCAT(e.first_name,' ',e.last_name),s.pay_month,s.net_salary,s.status FROM salaries s JOIN employees e ON e.id=s.employee_id WHERE %s ORDER BY s.pay_month DESC LIMIT 1000"],
        'new_hires'=>['title'=>'التعيينات الجديدة','headers'=>['الرقم','الموظف','تاريخ التعيين'],'sql'=>"SELECT e.employee_no,CONCAT(e.first_name,' ',e.last_name),e.hired_at FROM employees e WHERE %s AND e.hired_at>=DATE_SUB(CURRENT_DATE,INTERVAL 1 YEAR) ORDER BY e.hired_at DESC"],
        'resignations'=>['title'=>'الاستقالات','headers'=>['الرقم','الموظف','القسم'],'sql'=>"SELECT e.employee_no,CONCAT(e.first_name,' ',e.last_name),COALESCE(d.name,'') FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE %s AND e.status='resigned'"],
        'reviews'=>['title'=>'تقييم الأداء','headers'=>['الموظف','بداية الفترة','نهاية الفترة','النتيجة'],'sql'=>"SELECT CONCAT(e.first_name,' ',e.last_name),r.period_start,r.period_end,r.final_score FROM performance_reviews r JOIN employees e ON e.id=r.employee_id WHERE %s ORDER BY r.id DESC"],
        'monthly_attendance'=>['title'=>'مقارنة الحضور الشهرية','headers'=>['الشهر','حضور','تأخر','غياب مسجل'],'sql'=>"SELECT DATE_FORMAT(a.work_date,'%%Y-%%m'),SUM(a.status='present'),SUM(a.status='late'),SUM(a.status='absent') FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE %s GROUP BY DATE_FORMAT(a.work_date,'%%Y-%%m') ORDER BY 1 DESC LIMIT 24"],
        'turnover'=>['title'=>'حركة الموظفين','headers'=>['الشهر','تعيينات','مغادرات','صافي الحركة'],'sql'=>''],
    ];
}
function report_data(string $type): array
{
    $defs=report_definitions(); if (!isset($defs[$type])) throw new InvalidArgumentException('التقرير غير موجود.');
    if (isset(['salaries'=>'salaries.manage','contracts'=>'contracts.manage','documents'=>'documents.manage'][$type])) authorize(['salaries'=>'salaries.manage','contracts'=>'contracts.manage','documents'=>'documents.manage'][$type]);
    [$scope,$params]=employee_scope();
    foreach (['department_id','branch_id'] as $column) {
        $value=(string)($_GET[$column] ?? '');
        if ($value!=='' && ctype_digit($value)) { $scope.=" AND e.$column=?"; $params[]=(int)$value; }
    }
    if ($type==='employees') {
        $status=(string)($_GET['status'] ?? '');
        if (in_array($status,['active','leave','suspended','resigned','terminated'],true)) { $scope.=' AND e.status=?'; $params[]=$status; }
    }
    $from=(string)($_GET['from'] ?? ''); $to=(string)($_GET['to'] ?? '');
    if ($from!=='' && !valid_date($from)) throw new InvalidArgumentException('تاريخ البداية غير صحيح.');
    if ($to!=='' && !valid_date($to)) throw new InvalidArgumentException('تاريخ النهاية غير صحيح.');
    if ($from!=='' && $to!=='' && $from>$to) throw new InvalidArgumentException('الفترة المحددة غير صحيحة.');
    if ($type==='turnover') {
        $rows=all("SELECT e.hired_at,e.left_on FROM employees e WHERE $scope",$params);
        $months=[];
        foreach ($rows as $row) foreach (['hired_at'=>1,'left_on'=>-1] as $field=>$delta) {
            if (!$row[$field] || ($from && $row[$field]<$from) || ($to && $row[$field]>$to)) continue;
            $month=substr($row[$field],0,7); $months[$month] ??= [0,0]; $months[$month][$delta===1?0:1]++;
        }
        krsort($months);
        $result=[]; foreach ($months as $month=>[$hires,$exits]) $result[]=[$month,$hires,$exits,$hires-$exits];
        return [$defs[$type],array_slice($result,0,24)];
    }
    $dateColumns=['employees'=>'e.hired_at','attendance'=>'a.work_date','late'=>'a.work_date','leaves'=>'l.starts_on','contracts'=>'c.ends_on','documents'=>'d.expires_on','salaries'=>'s.pay_month','new_hires'=>'e.hired_at','resignations'=>'e.left_on','reviews'=>'r.period_end','monthly_attendance'=>'a.work_date'];
    $column=$dateColumns[$type];
    if ($from!=='') { $scope.=" AND $column>=?"; $params[]=$from; }
    if ($to!=='') { $scope.=" AND $column<=?"; $params[]=$to; }
    return [$defs[$type],all(sprintf($defs[$type]['sql'],$scope),$params)];
}
function activity_report_filter(): array
{
    $where='1=1'; $params=[];
    if (!empty($_GET['from']) && !empty($_GET['to']) && (string)$_GET['from']>(string)$_GET['to']) throw new InvalidArgumentException('الفترة المحددة غير صحيحة.');
    foreach (['from'=>'>=','to'=>'<='] as $field=>$operator) {
        $value=(string)($_GET[$field] ?? '');
        if ($value==='') continue;
        require_date($value,$field==='from'?'البداية':'النهاية');
        $where.=" AND a.created_at $operator ?";
        $params[]=$field==='to'?$value.' 23:59:59':$value.' 00:00:00';
    }
    return [$where,$params];
}
function activity_report_rows(int $limit): array
{
    [$where,$params]=activity_report_filter();
    $rows=all('SELECT COALESCE(u.email,"—"),a.action,a.entity,a.details,a.created_at FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE '.$where.' ORDER BY a.id DESC LIMIT '.$limit,$params);
    $names=[]; foreach (entities() as $definition) $names[$definition['table']]=$definition['title'];
    foreach ($rows as &$row) { $row=array_values($row); $row[1]=dashboard_action_label($row[1]); $row[2]=$names[$row[2]] ?? ($row[2] ?: 'النظام'); }
    unset($row);
    return $rows;
}
function view_reports(): void
{
    authorize('reports.view');
    $type=(string)($_GET['type'] ?? 'employees');
    $defs=report_definitions(); if (!isset($defs[$type]) && $type!=='activity') $type='employees';
    echo '<div class="report-tabs">'; foreach($defs as $key=>$def) { $permission=['salaries'=>'salaries.manage','contracts'=>'contracts.manage','documents'=>'documents.manage'][$key] ?? null; if (!$permission || can($permission)) echo '<a class="'.($key===$type?'active':'').'" href="'.e(url('reports',['type'=>$key])).'">'.e($def['title']).'</a>'; }
    if (current_user()['role_code']==='admin') echo '<a class="'.($type==='activity'?'active':'').'" href="'.e(url('reports',['type'=>'activity'])).'">نشاط المستخدمين</a>';
    echo '</div>';
    echo '<form method="get" class="filters report-filters"><input type="hidden" name="page" value="reports"><input type="hidden" name="type" value="'.e($type).'">';
    if ($type!=='activity') echo '<select name="department_id" aria-label="القسم"><option value="">كل الأقسام</option>'.options_html(field_options(['table'=>'departments']),$_GET['department_id'] ?? '').'</select><select name="branch_id" aria-label="الفرع"><option value="">كل الفروع</option>'.options_html(field_options(['table'=>'branches']),$_GET['branch_id'] ?? '').'</select>';
    if ($type==='employees') echo '<select name="status" aria-label="الحالة"><option value="">كل الحالات</option>'.options_html(entities()['employees']['fields']['status']['options'],$_GET['status'] ?? '').'</select>';
    echo '<label>من<input type="date" name="from" value="'.e($_GET['from'] ?? '').'"></label><label>إلى<input type="date" name="to" value="'.e($_GET['to'] ?? '').'"></label><button class="outline-button">تطبيق الفلاتر</button></form>';
    if ($type==='activity') { authorize('users.manage'); $headers=['المستخدم','الإجراء','القسم','التفاصيل','التاريخ']; $rows=activity_report_rows(1000); }
    else { [$def,$rows]=report_data($type); $headers=$def['headers']; }
    $title=$type==='activity'?'نشاط المستخدمين':$defs[$type]['title'];
    $exportParams=['type'=>$type,'export'=>'csv']; foreach (['department_id','branch_id','status','from','to'] as $filter) if (isset($_GET[$filter])) $exportParams[$filter]=$_GET[$filter];
    echo '<section class="panel report-panel"><div class="panel-head report-controls"><div><h2>'.e($title).'</h2><p class="muted">مراجعة البيانات قبل الطباعة أو التصدير</p></div><div class="toolbar-actions"><a class="outline-button" href="'.e(url('reports',$exportParams)).'">تصدير Excel</a><button class="primary-button print-button" type="button" data-print>طباعة / حفظ PDF</button></div></div>';
    echo '<article class="document-sheet report-sheet">';
    print_letterhead('تقرير إداري',$title,'RPT-'.date('Ymd').'-'.strtoupper($type));
    echo '<div class="report-meta"><span>عدد السجلات <strong>'.e(count($rows)).'</strong></span><span>الفترة حتى <strong>'.e(date('Y/m/d')).'</strong></span>';
    if ($type==='employees' && !empty($_GET['department_id']) && ctype_digit((string)$_GET['department_id'])) echo '<span>القسم <strong>'.e(option_label('department_id',$_GET['department_id'],['table'=>'departments'])).'</strong></span>';
    if ($type==='employees' && !empty($_GET['status'])) echo '<span>الحالة <strong>'.e(report_translate($_GET['status'])).'</strong></span>';
    if (!empty($_GET['from'])) echo '<span>من <strong>'.e($_GET['from']).'</strong></span>';
    if (!empty($_GET['to'])) echo '<span>إلى <strong>'.e($_GET['to']).'</strong></span>';
    echo '</div>';
    if (in_array($type,['monthly_attendance','turnover'],true) && $rows) render_report_chart($type,$rows);
    echo '<div class="table-wrap"><table><thead><tr>'; foreach($headers as $h) echo '<th>'.e($h).'</th>'; echo '</tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="'.e(count($headers)).'" class="empty">لا توجد بيانات لهذا التقرير.</td></tr>';
    foreach($rows as $row) { echo '<tr>'; foreach($row as $v) echo '<td>'.e(report_translate($v)).'</td>'; echo '</tr>'; }
    echo '</tbody></table></div>';
    print_footer('مراجعة التقرير');
    echo '</article></section>';
}
function render_report_chart(string $type,array $rows): void
{
    $items=array_map('array_values',array_slice(array_reverse($rows),-12));
    $max=1; foreach ($items as $row) $max=max($max,(int)$row[1]+(int)$row[2]+($type==='monthly_attendance'?(int)$row[3]:0));
    echo '<div class="report-chart"><div class="report-chart-legend"><strong>المقارنة الشهرية</strong><span><i class="chart-first"></i>'.($type==='turnover'?'تعيينات':'حضور').'</span><span><i class="chart-second"></i>'.($type==='turnover'?'مغادرات':'تأخر').'</span>';
    if ($type==='monthly_attendance') echo '<span><i class="chart-third"></i>غياب مسجل</span>';
    echo '</div>';
    foreach ($items as $row) {
        echo '<div class="report-chart-row"><time>'.e($row[0]).'</time><div class="report-chart-track">';
        for ($index=1;$index<=($type==='turnover'?2:3);$index++) echo '<span class="chart-part-'.$index.'" style="width:'.e(round((int)$row[$index]/$max*100,2)).'%" title="'.e($row[$index]).'"></span>';
        echo '</div><strong>'.e($type==='turnover'?$row[3]:((int)$row[1]+(int)$row[2]+(int)$row[3])).'</strong></div>';
    }
    echo '</div>';
}
function export_report(): never
{
    authorize('reports.view'); $type=(string)($_GET['type'] ?? 'employees');
    if ($type==='activity') { authorize('users.manage'); export_csv('activity.csv',['المستخدم','الإجراء','القسم','التفاصيل','التاريخ'],activity_report_rows(10000)); }
    [$def,$rows]=report_data($type); export_csv($type.'.csv',$def['headers'],array_map(fn($row)=>array_map('report_translate',$row),$rows));
}
function report_translate(mixed $value): mixed
{
    return ['active'=>'على رأس العمل','leave'=>'في إجازة','suspended'=>'موقوف','resigned'=>'مستقيل','terminated'=>'منتهية خدمته','present'=>'حاضر','late'=>'متأخر','absent'=>'غائب','pending'=>'قيد المراجعة','approved'=>'مقبول','rejected'=>'مرفوض','cancelled'=>'ملغى','draft'=>'مسودة','paid'=>'مصروف'][(string)$value] ?? $value;
}
function contract_status_label(string $status): string { return ['draft'=>'مسودة','review'=>'قيد المراجعة','approved'=>'معتمد'][$status] ?? $status; }

function view_users(): void
{
    authorize('users.manage');
    echo '<div class="two-col"><section class="panel"><div class="panel-head"><h2>إضافة مستخدم</h2></div><form method="post" class="compact-form">'.csrf_field().'<input type="hidden" name="action" value="create"><label>البريد الإلكتروني<input type="email" name="email" required></label><label>كلمة المرور<input type="password" name="password" minlength="12" required><small>12 حرفًا على الأقل، حرف كبير ورقم.</small></label><label>الدور<select name="role_id" required><option value="">اختر الدور</option>'.options_html(array_column(all('SELECT id,name FROM roles ORDER BY id'),'name','id'),'').'</select></label><label>الموظف<select name="employee_id"><option value="">دون ربط</option>'.options_html(field_options(['table'=>'employees']),'').'</select></label><button class="primary-button">إنشاء المستخدم</button></form></section><section class="panel"><div class="panel-head"><h2>إدارة الوصول</h2></div><p class="muted">المدير العام يملك جميع الصلاحيات. مدير الموارد البشرية يدير شؤون الموظفين. المشرف يتابع فريقه، والموظف يرى بياناته فقط.</p></section></div><section class="panel table-panel"><div class="panel-head"><h2>الحسابات</h2></div><div class="table-wrap"><table><thead><tr><th>البريد</th><th>الدور</th><th>الموظف</th><th>آخر دخول</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>';
    foreach(all('SELECT u.*,r.name role_name,CONCAT(e.first_name," ",e.last_name) employee_name FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN employees e ON e.id=u.employee_id ORDER BY u.id DESC') as $r) {
        echo '<tr><td>'.e($r['email']).'</td><td>'.e($r['role_name']).'</td><td>'.e($r['employee_name']).'</td><td>'.e($r['last_login_at']).'</td><td>'.($r['active']?'نشط':'معطل').'</td><td class="actions-cell"><div class="row-actions">'; if ($r['id']!==current_user()['id']) row_action_form('users','toggle',(int)$r['id'],$r['active']?'تعطيل':'تفعيل',$r['active']?'danger':'neutral'); echo '</div></td></tr>';
    }
    echo '</tbody></table></div></section>';
}
function view_settings(): void
{
    authorize('settings.manage'); $settings=organization_settings();
    echo '<div class="settings-intro"><div><span class="eyebrow">إدارة النظام</span><h2>إعدادات المنشأة</h2><p>نظّم بيانات المنشأة وأوقات العمل والنصوص التي تظهر في المستندات الرسمية.</p></div><a class="outline-button" href="'.e(url('alerts')).'">إدارة قواعد التنبيه</a></div>';
    echo '<form method="post" class="settings-form">'.csrf_field().'<input type="hidden" name="action" value="save">';
    $groups=[
        ['بيانات المنشأة','تظهر هذه البيانات في العقود والتقارير المطبوعة.',[
            'company_name'=>['اسم المنشأة','text','اسم المنشأة'],
            'company_address'=>['العنوان','text','العنوان الرسمي'],
            'company_email'=>['البريد الإلكتروني','email','name@example.com'],
            'company_phone'=>['رقم الهاتف','tel','رقم التواصل'],
        ]],
        ['الدوام والمنطقة','القيم المعتمدة لتنظيم الدوام وعرض المواعيد.',[
            'work_start'=>['بداية الدوام','time',''],
            'work_end'=>['نهاية الدوام','time',''],
            'work_days'=>['أيام العمل','text','0,1,2,3,4'],
            'timezone'=>['المنطقة الزمنية','text','Asia/Riyadh'],
            'currency'=>['العملة','text','ر.س'],
        ]],
    ];
    foreach ($groups as [$title,$description,$fields]) {
        echo '<section class="panel settings-section"><div class="settings-section-head"><div><h2>'.e($title).'</h2><p>'.e($description).'</p></div></div><div class="settings-fields">';
        foreach ($fields as $key=>[$label,$type,$placeholder]) {
            echo '<div class="field"><label for="settings-'.e($key).'">'.e($label).'</label><input id="settings-'.e($key).'" name="'.e($key).'" type="'.e($type).'" value="'.e($settings[$key] ?? '').'" placeholder="'.e($placeholder).'"'.($key==='company_name'?' required':'').($key==='work_days'?' aria-describedby="work-days-help"':'').'></div>';
        }
        if (isset($fields['work_days'])) echo '<p id="work-days-help" class="settings-help">اكتب أرقام الأيام مفصولة بفواصل: 0 الأحد، 1 الاثنين، 2 الثلاثاء، 3 الأربعاء، 4 الخميس، 5 الجمعة، 6 السبت.</p>';
        echo '</div></section>';
    }
    echo '<section class="panel settings-section"><div class="settings-section-head"><div><h2>قوالب المستندات</h2><p>نصوص جاهزة تظهر في النسخ المطبوعة ويمكن تعديلها عند الحاجة.</p></div></div><div class="settings-fields"><div class="field wide"><label for="contract_terms">كليشة العقود</label><textarea id="contract_terms" name="contract_terms" rows="5">'.e($settings['contract_terms']).'</textarea><small>يظهر هذا النص في العقود المطبوعة. راجعه قانونيًا قبل اعتماد أي عقد.</small></div><div class="field wide"><label for="report_footer">تذييل التقارير</label><textarea id="report_footer" name="report_footer" rows="3">'.e($settings['report_footer']).'</textarea><small>يظهر أسفل التقارير الرسمية المطبوعة.</small></div></div></section>';
    echo '<div class="settings-save"><span>تُطبّق التغييرات على المستندات والتقارير الجديدة.</span><button class="primary-button" type="submit">حفظ الإعدادات</button></div></form>';
    echo '<section class="panel settings-backup"><div><h2>النسخ الاحتياطي</h2><p>أنشئ نسخة SQL داخل مجلد التخزين الخاص لاستعادتها عند الحاجة.</p></div><form method="post" action="'.e(url('backup')).'">'.csrf_field().'<button class="outline-button" type="submit">إنشاء نسخة الآن</button></form></section>';
    $check=one('SELECT filename,checked_at,status,details FROM backup_checks ORDER BY id DESC LIMIT 1');
    echo '<section class="panel settings-restore"><div><h2>آخر اختبار استعادة</h2><p>حالة التحقق من إمكانية استعادة نسخة احتياطية.</p></div><div class="settings-restore-result">'.($check?'<span class="badge-soft">'.($check['status']==='passed'?'نجح':'فشل').'</span><span>'.e($check['checked_at']).'</span><small>'.e($check['filename'].' · '.$check['details']).'</small>':'<span class="muted">لم يُجر اختبار استعادة بعد.</span>').'</div></section>';
}
function view_password(): void
{
    echo '<section class="panel narrow-panel"><form method="post" class="compact-form">'.csrf_field().'<label>كلمة المرور الحالية<input type="password" name="current_password" required></label><label>كلمة المرور الجديدة<input type="password" name="new_password" minlength="12" required><small>12 حرفًا على الأقل، حرف كبير ورقم.</small></label><button class="primary-button">تغيير كلمة المرور</button></form></section>';
}
function view_notifications(): void
{
    $u=require_login(); $rows=all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100',[$u['id']]);
    echo '<section class="panel"><div class="panel-head"><h2>آخر الإشعارات</h2><form method="post">'.csrf_field().'<button class="outline-button">تحديد الكل كمقروء</button></form></div>';
    if (!$rows) echo '<div class="empty">ليس لديك إشعارات جديدة.</div>';
    foreach($rows as $r) echo '<div class="notification-row '.(!$r['read_at']?'unread':'').'"><div><strong>'.e($r['title']).'</strong><p>'.e($r['body']).'</p></div><small>'.e($r['created_at']).'</small></div>';
    echo '</section>';
}
function view_profile(): void
{
    $u=require_login(); $e=$u['employee_id'] ? one('SELECT e.*,d.name department,j.name job_title FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN job_titles j ON j.id=e.job_title_id WHERE e.id=?',[$u['employee_id']]) : null;
    echo '<section class="panel profile-panel"><div class="profile-head"><span class="avatar large">'.e(substr($u['email'],0,1)).'</span><div><h2>'.e($e ? $e['first_name'].' '.$e['last_name'] : $u['email']).'</h2><p>'.e($e['job_title'] ?? $u['role_name']).'</p></div></div><div class="form-grid">';
    foreach(['employee_no'=>'الرقم الوظيفي','national_id'=>'الهوية','phone'=>'الجوال','email'=>'البريد','department'=>'القسم','hired_at'=>'التعيين','status'=>'الحالة'] as $key=>$label) echo '<div class="field"><label>'.e($label).'</label><div class="readonly-value">'.e(report_translate($e[$key] ?? '—')).'</div></div>';
    echo '</div><div class="form-footer"><a class="outline-button" href="'.e(url('password')).'">تغيير كلمة المرور</a><a class="outline-button" href="'.e(url('mfa')).'">التحقق الإضافي</a><a class="outline-button" href="'.e(url('services')).'">طلب خدمة</a></div></section>';
    if ($e) echo '<p><a class="primary-button" href="'.e(url('employee_file',['id'=>$e['id']])).'">ملفي الموحد</a></p>';
    if ($e) {
        echo '<section class="panel table-panel"><div class="panel-head"><h2>مستنداتي</h2></div><div class="table-wrap"><table><thead><tr><th>الاسم</th><th>النوع</th><th>الانتهاء</th><th>تحميل</th></tr></thead><tbody>';
        foreach(all('SELECT * FROM documents WHERE employee_id=? ORDER BY id DESC',[$e['id']]) as $d) echo '<tr><td>'.e($d['title']).'</td><td>'.e($d['type']).'</td><td>'.e($d['expires_on']).'</td><td><a href="'.e(url('download',['entity'=>'documents','id'=>$d['id'],'field'=>'file_path'])).'">تحميل</a></td></tr>';
        echo '</tbody></table></div></section><section class="panel table-panel"><div class="panel-head"><h2>عقودي</h2></div><div class="table-wrap"><table><thead><tr><th>رقم العقد</th><th>النوع</th><th>تاريخ النهاية</th><th>النسخة المنسقة</th></tr></thead><tbody>';
        foreach(all('SELECT id,contract_no,type,ends_on FROM contracts WHERE employee_id=? ORDER BY id DESC',[$e['id']]) as $c) echo '<tr><td>'.e($c['contract_no']).'</td><td>'.e($c['type']).'</td><td>'.e($c['ends_on']).'</td><td><a href="'.e(url('contract_print',['id'=>$c['id']])).'">عرض / طباعة</a></td></tr>';
        echo '</tbody></table></div></section><section class="panel table-panel"><div class="panel-head"><h2>قسائم الراتب</h2></div><div class="table-wrap"><table><thead><tr><th>الشهر</th><th>الصافي</th><th>الحالة</th></tr></thead><tbody>';
        foreach(all('SELECT id,pay_month,net_salary,status FROM salaries WHERE employee_id=? ORDER BY pay_month DESC',[$e['id']]) as $s) echo '<tr><td><a href="'.e(url('salary_slip',['id'=>$s['id']])).'">'.e($s['pay_month']).'</a></td><td>'.e($s['net_salary']).'</td><td>'.e($s['status']).'</td></tr>';
        echo '</tbody></table></div></section>';
    }
}
function view_salary_slip(): void
{
    $id=(int)($_GET['id'] ?? 0); if ($id<1) throw new InvalidArgumentException('القسيمة غير موجودة.');
    $row=one('SELECT s.*,e.first_name,e.last_name,e.employee_no FROM salaries s JOIN employees e ON e.id=s.employee_id WHERE s.id=?',[$id]);
    if (!$row) throw new InvalidArgumentException('القسيمة غير موجودة.');
    $u=require_login(); if (!can('salaries.manage') && (int)$row['employee_id'] !== (int)$u['employee_id']) { http_response_code(403); throw new RuntimeException('لا يمكنك عرض هذه القسيمة.'); }
    echo '<section class="panel narrow-panel"><div class="panel-head"><h2>قسيمة راتب '.e($row['pay_month']).'</h2><button class="outline-button print-button" type="button" data-print>حفظ PDF</button></div><div class="profile-head"><span class="avatar large">و</span><div><h2>'.e($row['first_name'].' '.$row['last_name']).'</h2><p>'.e($row['employee_no']).'</p></div></div>';
    foreach(['base_salary'=>'الراتب الأساسي','allowances'=>'البدلات','bonuses'=>'المكافآت','overtime'=>'العمل الإضافي','deductions'=>'الخصومات','advances'=>'السلف','net_salary'=>'الصافي','status'=>'حالة الصرف'] as $key=>$label) echo '<div class="balance-row"><span>'.e($label).'</span><strong>'.e($row[$key]).'</strong></div>';
    echo '</section>';
}
function view_contract_print(): void
{
    $id=(int)($_GET['id'] ?? 0);
    if ($id<1) throw new InvalidArgumentException('العقد غير موجود.');
    $contract=contract_snapshot($id);
    $user=require_login();
    if (!can('contracts.manage') && (int)$contract['employee_id'] !== (int)$user['employee_id']) { http_response_code(403); throw new RuntimeException('لا يمكنك عرض هذا العقد.'); }
    $version=(int)($_GET['version'] ?? ($contract['status']==='approved'?$contract['version']:0));
    if ($version>0) {
        $saved=one('SELECT snapshot FROM contract_versions WHERE contract_id=? AND version=?',[$id,$version]);
        if (!$saved) throw new InvalidArgumentException('إصدار العقد غير موجود.');
        $contract=json_decode($saved['snapshot'],true,512,JSON_THROW_ON_ERROR);
    }
    $settings=$contract['print_settings'] ?? organization_settings();
    $employeeName=trim(implode(' ',array_filter([$contract['first_name'],$contract['father_name'],$contract['last_name']])));
    echo '<div class="document-toolbar"><a class="back-link" href="'.e(can('contracts.manage')?url('contracts'):url('profile')).'">→ العودة</a><button type="button" class="primary-button" data-print>طباعة العقد / حفظ PDF</button></div>';
    echo '<article class="document-sheet contract-sheet">';
    print_letterhead('إدارة الموارد البشرية','عقد عمل',$contract['contract_no'],$settings);
    echo '<div class="contract-document-state '.($version?'approved':'draft').'">'.($version?'نسخة معتمدة · الإصدار '.e($version).' · '.e($contract['approved_at']):'مسودة غير معتمدة').'</div>';
    echo '<div class="document-intro"><p>بيانات الطرفين</p><h3>عقد بين '.e($settings['company_name']).' والموظف '.e($employeeName).'</h3></div>';
    echo '<div class="document-facts">';
    foreach (['employee_no'=>'الرقم الوظيفي','national_id'=>'رقم الهوية أو الإقامة','department'=>'القسم','job_title'=>'المسمى الوظيفي','type'=>'نوع العقد','starts_on'=>'تاريخ البداية','ends_on'=>'تاريخ النهاية','salary'=>'الراتب الأساسي'] as $key=>$label) {
        $value=$contract[$key] ?: '—';
        if ($key==='salary') $value=number_format((float)$contract[$key],2).' '.($settings['currency'] ?? 'ر.س');
        echo '<div class="document-fact"><span>'.e($label).'</span><strong>'.e($value).'</strong></div>';
    }
    echo '</div><section class="document-terms"><h3>الشروط الأساسية</h3><p>'.nl2br(e($settings['contract_terms'])).'</p></section>';
    if (trim((string)$contract['notes'])!=='') echo '<section class="document-terms"><h3>ملاحظات إضافية</h3><p>'.nl2br(e($contract['notes'])).'</p></section>';
    echo '<div class="contract-signatures"><div><strong>الطرف الأول</strong><span>'.e($settings['company_name']).'</span><small>الاسم والتوقيع والختم</small></div><div><strong>الطرف الثاني</strong><span>'.e($employeeName).'</span><small>الاسم والتوقيع</small></div></div>';
    echo '<p class="document-footnote">يُراجع هذا النموذج ويُعتمد من الطرفين قبل اعتباره عقدًا نهائيًا.</p></article>';
}
function options_html(array $options, mixed $selected): string
{
    $html=''; foreach($options as $key=>$label) $html.='<option value="'.e($key).'"'.((string)$selected===(string)$key?' selected':'').'>'.e($label).'</option>'; return $html;
}
function entity_form(string $page, array $def, int $id, bool $edit): void
{
    $row=$id?one('SELECT * FROM '.$def['table'].' WHERE id=?',[$id]):null;
    if ($id && !$row) throw new InvalidArgumentException('السجل غير موجود.');
    if ($page==='salaries' && $edit && $id && ($row['status']!=='draft' || payroll_locked($row['pay_month']))) throw new InvalidArgumentException('يمكن تعديل مسودة راتب من شهر مفتوح فقط.');
    echo '<div class="form-header"><a class="back-link" href="'.e(url($page)).'">→ العودة إلى '.e($def['title']).'</a><h2>'.($edit?($id?'تعديل السجل':'إضافة سجل جديد'):'تفاصيل السجل').'</h2></div><section class="panel detail-panel">';
    if ($edit) echo '<form method="post" enctype="multipart/form-data" action="'.e(url($page)).'">'.csrf_field().'<input type="hidden" name="action" value="'.($id?'update':'create').'"><input type="hidden" name="id" value="'.e($id).'">';
    echo '<div class="form-grid">';
    foreach ($def['fields'] as $name=>$field) {
        if ($page==='salaries' && $edit && $name==='status') continue;
        $value=$row[$name] ?? '';
        echo '<div class="field '.($field['type']==='textarea'?'wide':'').'"><label for="f_'.e($name).'">'.e($field['label']).(!empty($field['required'])?' <b>*</b>':'').'</label>';
        if (!$edit) {
            if ($field['type']==='file' && $value) echo '<a href="'.e(url('download',['entity'=>$page,'id'=>$id,'field'=>$name])).'">تحميل الملف</a>';
            else echo '<div class="readonly-value">'.field_cell($name,$value,$field).'</div>';
        } elseif ($field['type']==='select' || $field['type']==='enum') {
            echo '<select id="f_'.e($name).'" name="'.e($name).'"'.(!empty($field['required'])?' required':'').'><option value="">اختر</option>'.options_html(field_options($field),$value).'</select>';
        } elseif ($field['type']==='textarea') echo '<textarea id="f_'.e($name).'" name="'.e($name).'" rows="3">'.e($value).'</textarea>';
        elseif ($field['type']==='file') echo '<input id="f_'.e($name).'" type="file" name="'.e($name).'" accept=".pdf,.jpg,.jpeg,.png,.webp"'.(!empty($field['required'])?' required':'').'>'.($value?'<small>الملف الحالي محفوظ.</small>':'');
        else echo '<input id="f_'.e($name).'" type="'.e($field['type']).'" name="'.e($name).'" value="'.e($value).'"'.(!empty($field['step'])?' step="'.e($field['step']).'"':'').(isset($field['min'])?' min="'.e($field['min']).'"':'').(isset($field['max'])?' max="'.e($field['max']).'"':'').(!empty($field['required'])?' required':'').'>';
        echo '</div>';
    }
    echo '</div>';
    if ($edit) echo '<div class="form-footer"><button class="primary-button">حفظ البيانات</button><a class="outline-button" href="'.e(url($page)).'">إلغاء</a></div></form>';
    if (!$edit && $page==='employees') {
        echo '<div class="related"><h3>سجل الموظف</h3>';
        foreach (['contracts'=>'العقود','documents'=>'الوثائق','salaries'=>'الرواتب','reviews'=>'التقييمات','trainings'=>'الدورات'] as $table=>$label) {
            $count=(int)scalar('SELECT COUNT(*) FROM '.entities()[$table]['table'].' WHERE employee_id=?',[$id]);
            echo '<span>'.e($label).' <strong>'.e($count).'</strong></span>';
        }
        echo '</div><a class="primary-button" href="'.e(url('employee_file',['id'=>$id])).'">فتح الملف الموحد</a>';
    }
    if (!$edit && $page==='salaries') echo '<button class="outline-button print-button" type="button" data-print>طباعة أو حفظ PDF</button>';
    if (!$edit && $page==='contracts') {
        echo '<div class="contract-workflow"><div><strong>حالة العقد: '.e(contract_status_label($row['status'])).'</strong><small>آخر إصدار معتمد: '.e($row['version']).'</small></div><a class="outline-button" href="'.e(url('contract_print',['id'=>$id])).'">عرض كليشة العقد</a>';
        if ($row['status']==='draft') action_form('contracts','submit',$id,'إرسال للمراجعة');
        if ($row['status']==='review') { if ((int)$row['submitted_by']!==(int)current_user()['id']) action_form('contracts','approve',$id,'اعتماد وإصدار'); action_form('contracts','return',$id,'إعادة للمسودة'); }
        echo '</div>';
        $versions=all('SELECT version,approved_at FROM contract_versions WHERE contract_id=? ORDER BY version DESC',[$id]);
        if ($versions) { echo '<div class="contract-versions"><strong>الإصدارات المعتمدة</strong>'; foreach ($versions as $version) echo '<a href="'.e(url('contract_print',['id'=>$id,'version'=>$version['version']])).'">الإصدار '.e($version['version']).' · '.e($version['approved_at']).'</a>'; echo '</div>'; }
    }
    echo '</section>';
}
function export_csv(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,$headers);
    foreach($rows as $row) fputcsv($out,array_values($row)); fclose($out); exit;
}
function export_entity(string $page): never
{
    $def=entities()[$page]; authorize($def['permission']);
    [$rows]=entity_rows($page,$def,10000);
    $headers=[]; foreach($def['list'] as $f) $headers[]=$def['fields'][$f]['label'] ?? ($f==='status'?'الحالة':$f);
    $data=[]; foreach($rows as $row) { $r=[]; foreach($def['list'] as $f) $r[]=($page==='contracts' && $f==='status')?contract_status_label($row[$f]):option_label($f,$row[$f] ?? null,$def['fields'][$f] ?? []); $data[]=$r; }
    export_csv($page.'.csv',$headers,$data);
}

function serve_download(): never
{
    $entity=(string)($_GET['entity'] ?? ''); $id=(int)($_GET['id'] ?? 0); $field=(string)($_GET['field'] ?? '');
    $defs=entities(); if (!isset($defs[$entity]) || !isset($defs[$entity]['fields'][$field]) || $defs[$entity]['fields'][$field]['type']!=='file' || $id<1) throw new InvalidArgumentException('الملف غير موجود.');
    $table=$defs[$entity]['table']; $row=one("SELECT * FROM $table WHERE id=?",[$id]); if (!$row || !$row[$field]) throw new InvalidArgumentException('الملف غير موجود.');
    $u=require_login();
    if (!can($defs[$entity]['permission'])) {
        if (!in_array($entity,['documents','contracts','trainings','employees'],true) || (int)($entity==='employees' ? $row['id'] : $row['employee_id']) !== (int)$u['employee_id']) { http_response_code(403); throw new RuntimeException('ليس لديك صلاحية لتحميل الملف.'); }
    }
    $path=dirname(__DIR__).'/storage/uploads/'.basename($row[$field]); if (!is_file($path)) throw new InvalidArgumentException('الملف غير موجود.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path); header('Content-Type: '.$mime); header('Content-Disposition: attachment; filename="'.basename($path).'"'); header('Content-Length: '.filesize($path)); readfile($path); exit;
}
