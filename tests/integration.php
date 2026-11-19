<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core.php';
require dirname(__DIR__).'/app/Auth.php';
require dirname(__DIR__).'/app/Entities.php';
require dirname(__DIR__).'/app/Calendar.php';
require dirname(__DIR__).'/app/Security.php';
require dirname(__DIR__).'/app/Workflows.php';
require dirname(__DIR__).'/app/Actions.php';
require dirname(__DIR__).'/app/PrintTemplates.php';
require dirname(__DIR__).'/app/Features.php';
require dirname(__DIR__).'/app/EmployeeFile.php';
require dirname(__DIR__).'/app/Views.php';
require dirname(__DIR__).'/app/WorkflowViews.php';

function check_db(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
$_SESSION['user']=['id'=>1,'employee_id'=>null,'email'=>'admin@wateq.local','role_code'=>'admin','role_name'=>'المدير العام','permissions'=>[]];
$_SESSION['last_activity']=time();
check_db((int)scalar('SELECT COUNT(*) FROM roles')===4,'roles');
check_db((int)scalar('SELECT COUNT(*) FROM employees')>=4,'employees');
check_db((float)scalar('SELECT net_salary FROM salaries WHERE employee_id=4')===7650.0,'salary formula');
foreach (entities() as $key=>$def) entity_rows($key,$def,1);
foreach (array_keys(report_definitions()) as $type) report_data($type);
check_db((int)scalar("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='wateq_hr' AND table_name='attendance' AND index_name='employee_id'")>=1,'attendance unique index');
ob_start(); view_dashboard(); $dashboard=ob_get_clean();
check_db(str_contains($dashboard,'طلبات الإجازة المعلقة') && str_contains($dashboard,'سجل الحضور خلال 7 أيام'),'manager dashboard rendering');
$_GET=['type'=>'employees']; ob_start(); view_reports(); $report=ob_get_clean();
check_db(str_contains($report,'document-letterhead') && str_contains($report,'مراجعة التقرير'),'report letterhead rendering');
$_GET=['id'=>1]; ob_start(); view_contract_print(); $contract=ob_get_clean();
check_db(str_contains($contract,'document-letterhead') && str_contains($contract,'الطرف الثاني'),'contract letterhead rendering');
putenv('MAIL_ENABLED=0');
$db=App::db(); $db->beginTransaction();
try {
    query('INSERT INTO contracts (employee_id,contract_no,type,starts_on,ends_on,salary) VALUES (?,?,?,?,?,?)',[4,'WT-C-TEST-'.bin2hex(random_bytes(4)),'اختبار',date('Y-m-d'),date('Y-m-d',strtotime('+7 days')),1234]);
    $testId=(int)$db->lastInsertId(); $_POST=['id'=>(string)$testId];
    contract_action('submit');
    check_db(scalar('SELECT status FROM contracts WHERE id=?',[$testId])==='review','contract submitted for review');
    $sameSignerDenied=false;
    try { contract_action('approve'); } catch (InvalidArgumentException $error) { $sameSignerDenied=true; }
    check_db($sameSignerDenied,'two-person contract approval');
    $_SESSION['user']=['id'=>2,'employee_id'=>1,'email'=>'hr@wateq.local','role_code'=>'hr','role_name'=>'الموارد البشرية','permissions'=>['contracts.manage']];
    contract_action('approve');
    check_db((int)scalar('SELECT version FROM contracts WHERE id=?',[$testId])===1,'approved contract version');
    query('UPDATE contracts SET salary=9999 WHERE id=?',[$testId]);
    $_GET=['id'=>$testId,'version'=>1]; ob_start(); view_contract_print(); $immutable=ob_get_clean();
    check_db(str_contains($immutable,'1,234.00') && !str_contains($immutable,'9,999.00'),'immutable contract snapshot');
    check_db(dispatch_expiry_alerts()>=1,'expiry alert dispatched');
    check_db(dispatch_expiry_alerts()===0,'expiry alert deduplication');
} finally { $db->rollBack(); $_SESSION['user']=['id'=>1,'employee_id'=>null,'email'=>'admin@wateq.local','role_code'=>'admin','role_name'=>'المدير العام','permissions'=>[]]; }
$_GET=['id'=>4]; ob_start(); view_employee_file(); $file=ob_get_clean();
check_db(str_contains($file,'التسلسل الزمني') && str_contains($file,'العقود'),'unified employee file');
$_GET=['type'=>'monthly_attendance','from'=>date('Y-01-01'),'to'=>date('Y-m-d'),'department_id'=>'3']; [$definition,$monthly]=report_data('monthly_attendance');
check_db($definition['title']==='مقارنة الحضور الشهرية' && is_array($monthly),'filtered monthly report');
$_GET=['type'=>'monthly_attendance'];
set_error_handler(static function(int $level,string $message): never { throw new ErrorException($message,0,$level); });
ob_start(); view_reports(); $monthlyHtml=ob_get_clean(); restore_error_handler();
check_db(str_contains($monthlyHtml,'report-chart-row'),'monthly report chart rendering');
$db->beginTransaction();
try {
    $_SESSION['user']=['id'=>4,'employee_id'=>3,'email'=>'employee@wateq.local','role_code'=>'employee','role_name'=>'موظف','permissions'=>['attendance.self']];
    $_POST=['work_date'=>date('Y-m-d',strtotime('-1 day')),'check_in'=>'09:05','check_out'=>'17:03','reason'=>'نسيت تسجيل الحضور'];
    correction_action('request'); $correctionId=(int)scalar("SELECT MAX(id) FROM attendance_corrections WHERE employee_id=3 AND status='pending'");
    $_POST=['kind'=>'salary_letter']; service_action('request'); $serviceId=(int)scalar("SELECT MAX(id) FROM service_requests WHERE employee_id=3 AND status='pending'");
    $_SESSION['user']=['id'=>1,'employee_id'=>null,'email'=>'admin@wateq.local','role_code'=>'admin','role_name'=>'المدير العام','permissions'=>[]];
    $_POST=['id'=>(string)$correctionId]; correction_action('approve');
    check_db(scalar('SELECT status FROM attendance_corrections WHERE id=?',[$correctionId])==='approved','attendance correction approval');
    check_db(scalar('SELECT status FROM attendance WHERE employee_id=3 AND work_date=?',[date('Y-m-d',strtotime('-1 day'))])==='present','attendance correction applied');
    $_POST=['id'=>(string)$serviceId]; service_action('approve');
    $_GET=['id'=>(string)$serviceId]; ob_start(); view_service_letter(); $letter=ob_get_clean();
    check_db(str_contains($letter,'document-letterhead') && str_contains($letter,'خطاب تعريف بالراتب'),'approved salary letter rendering');
    $_SESSION['user']=['id'=>5,'employee_id'=>4,'email'=>'other@wateq.local','role_code'=>'employee','role_name'=>'موظف','permissions'=>[]];
    $letterDenied=false; try { ob_start(); view_service_letter(); } catch (RuntimeException) { $letterDenied=true; } finally { ob_end_clean(); http_response_code(200); }
    check_db($letterDenied,'other employee salary letter denied');
    $_SESSION['user']=['id'=>1,'employee_id'=>null,'email'=>'admin@wateq.local','role_code'=>'admin','role_name'=>'المدير العام','permissions'=>[]];
    seed_workflow_tasks(3,'onboarding');
    check_db((int)scalar("SELECT COUNT(*) FROM workflow_tasks WHERE employee_id=3 AND phase='onboarding'")===4,'onboarding task template');
    query('INSERT INTO salaries (employee_id,pay_month,base_salary) VALUES (?,?,?)',[3,'2028-05-01',9000]); $salaryId=(int)$db->lastInsertId();
    $_POST=['id'=>(string)$salaryId]; payroll_action('submit');
    $sameApproverDenied=false; try { payroll_action('approve'); } catch (InvalidArgumentException) { $sameApproverDenied=true; }
    check_db($sameApproverDenied,'two-person payroll approval');
    $_SESSION['user']=['id'=>2,'employee_id'=>1,'email'=>'hr@wateq.local','role_code'=>'hr','role_name'=>'الموارد البشرية','permissions'=>['salaries.manage']];
    payroll_action('approve'); payroll_action('pay');
    $_POST=['pay_month'=>'2028-05-01']; payroll_action('lock');
    check_db(payroll_locked('2028-05-01'),'payroll month lock');
} finally { $db->rollBack(); $_SESSION['user']=['id'=>1,'employee_id'=>null,'email'=>'admin@wateq.local','role_code'=>'admin','role_name'=>'المدير العام','permissions'=>[]]; }
$db->beginTransaction();
try {
    $secret=base32_encode(random_bytes(20));
    query('UPDATE users SET password_hash=?,totp_secret=?,mfa_enabled=1 WHERE id=4',[password_hash('TestPassword2026!',PASSWORD_DEFAULT),$secret]);
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    check_db(login('employee@wateq.local','TestPassword2026!'),'password accepted before MFA');
    check_db(isset($_SESSION['pending_mfa']) && empty($_SESSION['user']),'MFA challenge required');
    $_POST=['code'=>totp_at($secret,(int)floor(time()/30))]; verify_login_mfa();
    check_db((int)current_user()['id']===4,'MFA completed login');
    $token=bin2hex(random_bytes(32));
    query('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))',[4,hash('sha256',$token)]);
    $_POST=['token'=>$token,'new_password'=>'AnotherPassword2026!']; password_reset_action('reset');
    check_db(password_verify('AnotherPassword2026!',scalar('SELECT password_hash FROM users WHERE id=4')),'password reset');
    $reused=false; try { password_reset_action('reset'); } catch (InvalidArgumentException) { $reused=true; }
    check_db($reused,'one-time password reset token');
} finally { $db->rollBack(); $_SESSION['user']=['id'=>1,'employee_id'=>null,'email'=>'admin@wateq.local','role_code'=>'admin','role_name'=>'المدير العام','permissions'=>[]]; $_SESSION['last_activity']=time(); }
$db->beginTransaction();
try {
    $branch=scalar('SELECT branch_id FROM employees WHERE id=4');
    query('INSERT INTO company_holidays (title,starts_on,ends_on,branch_id) VALUES (?,?,?,?)',['اختبار العطلة','2026-12-31','2026-12-31',$branch]);
    check_db(leave_count_by_year(4,'2026-12-30','2027-01-04','workdays')===[2026=>1,2027=>2],'holiday and weekend leave accounting');
    query('INSERT INTO shifts (name,starts_at,ends_at,work_days) VALUES (?,?,?,?)',['اختبار الوردية '.bin2hex(random_bytes(3)),'09:00','17:00','1']);
    $shiftId=(int)$db->lastInsertId();
    query('INSERT INTO employee_shifts (employee_id,shift_id,effective_from) VALUES (?,?,?)',[4,$shiftId,'2027-01-03']);
    check_db(leave_count_by_year(4,'2026-12-30','2027-01-04','workdays')===[2026=>1,2027=>1],'effective shift leave accounting');
    $_GET=['month'=>'2026-12','employee_id'=>'4']; ob_start(); view_calendar(); $calendarHtml=ob_get_clean();
    check_db(str_contains($calendarHtml,'اختبار العطلة') && str_contains($calendarHtml,'calendar-grid'),'calendar rendering');
} finally { $db->rollBack(); }
$_GET=['type'=>'activity'];
set_error_handler(static function(int $level,string $message): never { throw new ErrorException($message,0,$level); });
ob_start(); view_reports(); $activityHtml=ob_get_clean(); restore_error_handler();
check_db(str_contains($activityHtml,'نشاط المستخدمين'),'activity audit report rendering');
[$definition,$turnover]=report_data('turnover');
check_db($definition['title']==='حركة الموظفين' && is_array($turnover),'turnover report');
$_GET=['id'=>1];
$_SESSION['user']=['id'=>4,'employee_id'=>3,'email'=>'employee@wateq.local','role_code'=>'employee','role_name'=>'موظف','permissions'=>['dashboard.view']];
$denied=false;
try { ob_start(); view_contract_print(); } catch (RuntimeException $error) { $denied=true; } finally { ob_end_clean(); http_response_code(200); }
check_db($denied,'other employee contract access');
$_GET=['id'=>4]; $fileDenied=false;
try { ob_start(); view_employee_file(); } catch (RuntimeException $error) { $fileDenied=true; } finally { ob_end_clean(); http_response_code(200); }
check_db($fileDenied,'other employee file access');
$_GET=['id'=>3]; ob_start(); view_employee_file(); $ownFile=ob_get_clean();
check_db(str_contains($ownFile,'التسلسل الزمني'),'own employee file access');
ob_start(); view_dashboard(); $employeeDashboard=ob_get_clean();
check_db(str_contains($employeeDashboard,'حالة الحضور اليوم') && str_contains($employeeDashboard,'طلبات قيد المراجعة'),'employee dashboard rendering');
echo "Database integration checks passed.\n";
