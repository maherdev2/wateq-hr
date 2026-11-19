<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core.php';
require dirname(__DIR__) . '/app/Calendar.php';
if (PHP_SAPI !== 'cli') exit(1);
$date=$argv[1] ?? date('Y-m-d',strtotime('-1 day'));
if (!valid_date($date) || $date>=date('Y-m-d')) { fwrite(STDERR,"Use a past date: YYYY-MM-DD\n"); exit(1); }
$employees=all("SELECT e.id FROM employees e WHERE e.status IN ('active','leave') AND e.hired_at<=? AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.employee_id=e.id AND a.work_date=?) AND NOT EXISTS (SELECT 1 FROM leave_requests l WHERE l.employee_id=e.id AND l.status='approved' AND l.starts_on<=? AND l.ends_on>=?)",[$date,$date,$date,$date]);
$count=0;
foreach ($employees as $employee) {
    if (!schedule_days((int)$employee['id'],$date,$date)[$date]['working']) continue;
    $count+=query("INSERT IGNORE INTO attendance (employee_id,work_date,status,notes) VALUES (?,?,'absent','تسجيل تلقائي')",[$employee['id'],$date])->rowCount();
}
echo "Marked $count absences for $date.\n";
