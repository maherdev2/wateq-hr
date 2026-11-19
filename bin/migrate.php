<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core.php';
if (PHP_SAPI !== 'cli') exit(1);

function has_column(string $table,string $column): bool
{
    return (bool)scalar('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$table,$column]);
}
foreach (['left_on'=>'DATE NULL'] as $column=>$definition) {
    if (!has_column('employees',$column)) query("ALTER TABLE employees ADD COLUMN $column $definition");
}
foreach ([
    'status'=>"ENUM('draft','review','approved') NOT NULL DEFAULT 'draft'",
    'version'=>'INT UNSIGNED NOT NULL DEFAULT 0',
    'submitted_by'=>'INT UNSIGNED NULL',
    'approved_by'=>'INT UNSIGNED NULL',
    'approved_at'=>'DATETIME NULL',
] as $column=>$definition) {
    if (!has_column('contracts',$column)) query("ALTER TABLE contracts ADD COLUMN $column $definition");
}
query('CREATE TABLE IF NOT EXISTS contract_versions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contract_id INT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL, snapshot JSON NOT NULL, approved_by INT UNSIGNED NULL, approved_at DATETIME NOT NULL, UNIQUE(contract_id,version))');
query('CREATE TABLE IF NOT EXISTS notification_events (event_key VARCHAR(190) PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
if (!has_column('shifts','work_days')) query('ALTER TABLE shifts ADD COLUMN work_days VARCHAR(20) NULL AFTER ends_at');
$newLeaveMode=!has_column('leave_types','count_mode');
if ($newLeaveMode) query("ALTER TABLE leave_types ADD COLUMN count_mode ENUM('calendar','workdays') NOT NULL DEFAULT 'calendar' AFTER annual_days");
if (!has_column('leave_requests','day_breakdown')) query('ALTER TABLE leave_requests ADD COLUMN day_breakdown JSON NULL AFTER days');
query('CREATE TABLE IF NOT EXISTS company_holidays (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, starts_on DATE NOT NULL, ends_on DATE NOT NULL, branch_id INT UNSIGNED NULL, is_day_off TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(starts_on,ends_on))');
if ($newLeaveMode) query("UPDATE leave_types SET count_mode='workdays' WHERE name IN ('سنوية','اضطرارية','بدون راتب')");
foreach (['totp_secret'=>'VARCHAR(64) NULL','mfa_enabled'=>'TINYINT(1) NOT NULL DEFAULT 0'] as $column=>$definition) if (!has_column('users',$column)) query("ALTER TABLE users ADD COLUMN $column $definition");
query('CREATE TABLE IF NOT EXISTS mfa_recovery_codes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, code_hash VARCHAR(255) NOT NULL, used_at DATETIME NULL, INDEX(user_id))');
query('CREATE TABLE IF NOT EXISTS password_resets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
query("CREATE TABLE IF NOT EXISTS attendance_corrections (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, work_date DATE NOT NULL, check_in TIME NOT NULL, check_out TIME NULL, reason VARCHAR(500) NOT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', decided_by INT UNSIGNED NULL, decided_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(status,work_date))");
query("CREATE TABLE IF NOT EXISTS service_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, kind ENUM('salary_letter','experience_letter','contact_change') NOT NULL, payload JSON NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', decided_by INT UNSIGNED NULL, decided_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(status,created_at))");
if (!has_column('service_requests','result_snapshot')) query('ALTER TABLE service_requests ADD COLUMN result_snapshot JSON NULL AFTER payload');
query("CREATE TABLE IF NOT EXISTS workflow_tasks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, phase ENUM('onboarding','offboarding') NOT NULL, title VARCHAR(160) NOT NULL, due_on DATE NULL, completed_at DATETIME NULL, completed_by INT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE(employee_id,phase,title))");
if (has_column('salaries','status') && !str_contains((string)scalar("SELECT column_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='salaries' AND column_name='status'"),"'review'")) query("ALTER TABLE salaries MODIFY COLUMN status ENUM('draft','review','approved','paid') NOT NULL DEFAULT 'draft'");
foreach (['submitted_by'=>'INT UNSIGNED NULL','approved_by'=>'INT UNSIGNED NULL','approved_at'=>'DATETIME NULL','paid_at'=>'DATETIME NULL'] as $column=>$definition) if (!has_column('salaries',$column)) query("ALTER TABLE salaries ADD COLUMN $column $definition");
query("CREATE TABLE IF NOT EXISTS payroll_periods (pay_month DATE PRIMARY KEY, status ENUM('open','locked') NOT NULL DEFAULT 'open', locked_by INT UNSIGNED NULL, locked_at DATETIME NULL)");
query("CREATE TABLE IF NOT EXISTS alert_rules (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, kind ENUM('contract','document','training') NOT NULL, days_before SMALLINT NOT NULL, notify_admin TINYINT(1) NOT NULL DEFAULT 1, notify_hr TINYINT(1) NOT NULL DEFAULT 1, notify_employee TINYINT(1) NOT NULL DEFAULT 1, active TINYINT(1) NOT NULL DEFAULT 1, UNIQUE(kind,days_before))");
query("CREATE TABLE IF NOT EXISTS backup_checks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255) NOT NULL, checked_at DATETIME NOT NULL, status ENUM('passed','failed') NOT NULL, details VARCHAR(255) NULL)");
if ((int)scalar('SELECT COUNT(*) FROM alert_rules')===0) foreach (['contract','document','training'] as $kind) foreach ([30,7,1,0] as $days) query('INSERT INTO alert_rules (kind,days_before) VALUES (?,?)',[$kind,$days]);
echo "Feature migration complete.\n";
