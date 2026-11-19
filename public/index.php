<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/Entities.php';
require dirname(__DIR__) . '/app/Calendar.php';
require dirname(__DIR__) . '/app/Security.php';
require dirname(__DIR__) . '/app/Workflows.php';
require dirname(__DIR__) . '/app/Actions.php';
require dirname(__DIR__) . '/app/PrintTemplates.php';
require dirname(__DIR__) . '/app/Features.php';
require dirname(__DIR__) . '/app/EmployeeFile.php';
require dirname(__DIR__) . '/app/Views.php';
require dirname(__DIR__) . '/app/WorkflowViews.php';

$page = (string)($_GET['page'] ?? 'dashboard');
if (in_array($page,['mfa','mfa_login','reset_password'],true)) { header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer'); }
$known = ['login','mfa_login','forgot_password','reset_password','mfa','dashboard','attendance','corrections','services','service_letter','tasks','payroll','alerts','calendar','leaves','reports','users','settings','password','notifications','profile','employee_file','salary_slip','contract_print','download','backup'];
if (!in_array($page,$known,true) && !isset(entities()[$page])) { http_response_code(404); $page='not_found'; }

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') handle_post($page);
    if ($page === 'login') { if (current_user()) redirect(); view_login(); exit; }
    if ($page === 'mfa_login') { if (current_user()) redirect(); view_mfa_login(); exit; }
    if ($page === 'forgot_password') { view_forgot_password(); exit; }
    if ($page === 'reset_password') { view_reset_password(); exit; }
    $user = require_login();
    if ($page === 'download') { serve_download(); exit; }
    if (isset(entities()[$page]) && isset($_GET['export'])) { export_entity($page); exit; }
    if ($page === 'reports' && isset($_GET['export'])) { export_report(); exit; }
    ob_start();
    layout_start($page);
    switch ($page) {
        case 'dashboard': view_dashboard(); break;
        case 'attendance': view_attendance(); break;
        case 'corrections': view_corrections(); break;
        case 'services': view_services(); break;
        case 'service_letter': view_service_letter(); break;
        case 'tasks': view_tasks(); break;
        case 'payroll': view_payroll(); break;
        case 'alerts': view_alerts(); break;
        case 'mfa': view_mfa(); break;
        case 'calendar': view_calendar(); break;
        case 'leaves': view_leaves(); break;
        case 'reports': view_reports(); break;
        case 'users': view_users(); break;
        case 'settings': view_settings(); break;
        case 'password': view_password(); break;
        case 'notifications': view_notifications(); break;
        case 'profile': view_profile(); break;
        case 'employee_file': view_employee_file(); break;
        case 'salary_slip': view_salary_slip(); break;
        case 'contract_print': view_contract_print(); break;
        case 'not_found': echo '<div class="empty">الصفحة غير موجودة.</div>'; break;
        default: view_entity($page); break;
    }
    layout_end();
    echo ob_get_clean();
} catch (Throwable $error) {
    if (ob_get_level() > 0) ob_end_clean();
    error_log((string)$error);
    $message = $error instanceof PDOException ? 'تعذر إتمام العملية بسبب تعارض في البيانات أو مشكلة اتصال.' : $error->getMessage();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { flash('danger',$message); redirect($page === 'not_found' ? 'dashboard' : $page,$page==='reset_password'?['token'=>input('token')]:[]); }
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    if (in_array($page,['login','mfa_login','forgot_password','reset_password'],true)) view_login($message);
    else { layout_start($page); echo '<div class="alert alert-danger">' . e($message) . '</div>'; layout_end(); }
}
