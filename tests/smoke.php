<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core.php';
require dirname(__DIR__).'/app/Auth.php';
require dirname(__DIR__).'/app/Entities.php';
require dirname(__DIR__).'/app/Calendar.php';
require dirname(__DIR__).'/app/Security.php';
require dirname(__DIR__).'/app/Workflows.php';
require dirname(__DIR__).'/app/Actions.php';

function check(bool $condition,string $message): void { if (!$condition) { fwrite(STDERR,"FAIL: $message\n"); exit(1); } }
check(e('<script>alert(1)</script>')==='&lt;script&gt;alert(1)&lt;/script&gt;','HTML escaping');
check(valid_date('2024-02-29') && !valid_date('2025-02-29'),'calendar date validation');
check(leave_days_by_year('2026-12-30','2027-01-02')===[2026=>2,2027=>2],'cross-year leave accounting');
check(normalize_work_days('4,0,1,2,3')==='0,1,2,3,4','workday normalization');
check(totp_at(base32_encode('12345678901234567890'),1)==='287082','TOTP reference vector');
check(valid_clock('09:15') && !valid_clock('25:00'),'attendance time validation');
$invalid=false; try { normalize_work_days('0,0,8'); } catch (InvalidArgumentException) { $invalid=true; } check($invalid,'invalid workday rejection');
$schema=file_get_contents(dirname(__DIR__).'/database/schema.sql');
foreach (entities() as $def) check(str_contains($schema,'CREATE TABLE '.$def['table'].' '),'missing table: '.$def['table']);
check(str_contains($schema,'UNIQUE(employee_id,work_date)'),'daily attendance uniqueness');
echo "Smoke checks passed.\n";
