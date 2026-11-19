<?php
declare(strict_types=1);

function normalize_work_days(string $value): string
{
    $parts=explode(',',str_replace(' ','',$value));
    if (!$value || count($parts)>7 || count($parts)!==count(array_unique($parts)) || array_filter($parts,fn($day)=>!preg_match('/^[0-6]$/',$day))) throw new InvalidArgumentException('أيام العمل يجب أن تكون أرقامًا فريدة من 0 (الأحد) إلى 6 (السبت) مفصولة بفواصل.');
    sort($parts);
    return implode(',',$parts);
}

function schedule_days(int $employeeId,string $start,string $end): array
{
    $employee=one('SELECT branch_id FROM employees WHERE id=?',[$employeeId]);
    if (!$employee) throw new InvalidArgumentException('الموظف غير موجود.');
    $default=(string)(scalar("SELECT setting_value FROM settings WHERE setting_key='work_days'") ?: '0,1,2,3,4');
    $shifts=all('SELECT es.effective_from,s.name,s.work_days FROM employee_shifts es JOIN shifts s ON s.id=es.shift_id WHERE es.employee_id=? AND es.effective_from<=? ORDER BY es.effective_from DESC',[$employeeId,$end]);
    $holidays=all('SELECT title,starts_on,ends_on,is_day_off FROM company_holidays WHERE starts_on<=? AND ends_on>=? AND (branch_id IS NULL OR branch_id=?) ORDER BY branch_id DESC,id DESC',[$end,$start,$employee['branch_id']]);
    $result=[];
    for ($date=new DateTimeImmutable($start),$last=new DateTimeImmutable($end);$date<=$last;$date=$date->modify('+1 day')) {
        $key=$date->format('Y-m-d'); $shift=null;
        foreach ($shifts as $candidate) if ($candidate['effective_from']<=$key) { $shift=$candidate; break; }
        $working=in_array($date->format('w'),explode(',',(string)($shift['work_days'] ?: $default)),true);
        $holiday=null;
        foreach ($holidays as $item) if ($item['starts_on']<=$key && $item['ends_on']>=$key) { $holiday=$item; break; }
        if ($holiday && (int)$holiday['is_day_off']===1) $working=false;
        $result[$key]=['working'=>$working,'shift'=>$shift['name'] ?? null,'holiday'=>$holiday['title'] ?? null];
    }
    return $result;
}

function leave_count_by_year(int $employeeId,string $start,string $end,string $mode): array
{
    if ($mode==='calendar') return leave_days_by_year($start,$end);
    if ($mode!=='workdays') throw new InvalidArgumentException('طريقة احتساب الإجازة غير صحيحة.');
    $years=[];
    foreach (schedule_days($employeeId,$start,$end) as $date=>$day) if ($day['working']) { $year=(int)substr($date,0,4); $years[$year]=($years[$year] ?? 0)+1; }
    return $years;
}

function calendar_action(string $action): void
{
    authorize('attendance.manage');
    if ($action!=='assign_shift') throw new InvalidArgumentException('إجراء غير معروف.');
    $employeeId=require_int(input('employee_id'),'الموظف'); employee_access($employeeId);
    $shiftId=require_int(input('shift_id'),'الوردية');
    if (!scalar('SELECT COUNT(*) FROM shifts WHERE id=? AND active=1',[$shiftId])) throw new InvalidArgumentException('الوردية غير متاحة.');
    $effective=require_date(input('effective_from'),'بدء الوردية');
    query('INSERT INTO employee_shifts (employee_id,shift_id,effective_from) VALUES (?,?,?) ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id)',[$employeeId,$shiftId,$effective]);
    audit('assign_shift','employee_shifts',$employeeId,$effective); flash('success','تم حفظ جدول الوردية.');
}

function view_calendar(): void
{
    $u=require_login();
    $month=(string)($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)) $month=date('Y-m');
    $first=$month.'-01'; $last=(new DateTimeImmutable($first))->format('Y-m-t');
    [$scope,$params]=employee_scope();
    $employees=all("SELECT e.id,CONCAT(e.first_name,' ',e.last_name) name FROM employees e WHERE $scope ORDER BY e.first_name",$params);
    $employeeId=(int)($_GET['employee_id'] ?? ($u['employee_id'] ?? ($employees[0]['id'] ?? 0)));
    if ($employeeId) employee_access($employeeId);
    $employeeName=''; foreach ($employees as $person) if ((int)$person['id']===$employeeId) $employeeName=$person['name'];
    echo '<section class="panel calendar-header"><div><p class="eyebrow">التخطيط والحضور</p><h2>تقويم '.e($employeeName ?: 'الفريق').'</h2><p class="muted">جدول العمل والعطل والإجازات المعتمدة في صفحة واحدة.</p></div><form method="get" class="calendar-filters"><input type="hidden" name="page" value="calendar"><label>الشهر<input type="month" name="month" value="'.e($month).'"></label>';
    if ($u['role_code']!=='employee') echo '<label>الموظف<select name="employee_id">'.options_html(array_column($employees,'name','id'),$employeeId).'</select></label>';
    echo '<button class="outline-button">عرض</button></form></section>';
    if (!$employeeId) { echo '<div class="empty">أضف موظفًا لعرض التقويم.</div>'; return; }
    $days=schedule_days($employeeId,$first,$last);
    $leaves=all("SELECT starts_on,ends_on,t.name FROM leave_requests l JOIN leave_types t ON t.id=l.leave_type_id WHERE l.employee_id=? AND l.status='approved' AND l.starts_on<=? AND l.ends_on>=?",[$employeeId,$last,$first]);
    $attendance=all('SELECT work_date,status FROM attendance WHERE employee_id=? AND work_date BETWEEN ? AND ?',[$employeeId,$first,$last]);
    $attendance=array_column($attendance,'status','work_date');
    $workCount=count(array_filter($days,fn($day)=>$day['working']));
    $holidayCount=count(array_filter($days,fn($day)=>$day['holiday']!==null));
    echo '<div class="calendar-stats"><div><strong>'.e($workCount).'</strong><span>يوم عمل</span></div><div><strong>'.e($holidayCount).'</strong><span>يوم عطلة مسجل</span></div><div><strong>'.e(count($attendance)).'</strong><span>سجل حضور</span></div></div>';
    echo '<section class="panel calendar-panel"><div class="calendar-weekdays">'; foreach (['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'] as $name) echo '<span>'.e($name).'</span>'; echo '</div><div class="calendar-grid">';
    $offset=(int)(new DateTimeImmutable($first))->format('w'); for($i=0;$i<$offset;$i++) echo '<div class="calendar-cell empty-cell"></div>';
    foreach($days as $date=>$day) {
        $leave=null; foreach($leaves as $item) if ($item['starts_on']<=$date && $item['ends_on']>=$date) { $leave=$item['name']; break; }
        $classes='calendar-cell'.(!$day['working']?' off':'').($day['holiday']?' holiday':'').($date===date('Y-m-d')?' today':'');
        echo '<div class="'.$classes.'"><strong>'.e((int)substr($date,8)).'</strong><small>'.e($day['shift'] ?: 'الدوام العام').'</small>';
        if ($day['holiday']) echo '<span class="calendar-tag holiday-tag">'.e($day['holiday']).'</span>';
        if ($leave) echo '<span class="calendar-tag leave-tag">إجازة: '.e($leave).'</span>';
        if (isset($attendance[$date])) echo '<span class="calendar-tag attendance-tag">'.e(report_translate($attendance[$date])).'</span>';
        if (!$day['working'] && !$day['holiday']) echo '<span class="calendar-tag off-tag">راحة أسبوعية</span>';
        echo '</div>';
    }
    echo '</div></section>';
    if (can('attendance.manage')) echo '<section class="panel calendar-manage"><div><h2>تعيين وردية</h2><p class="muted">يسري التغيير من التاريخ المحدد، وتظل الورديات السابقة محفوظة.</p></div><form method="post" class="calendar-filters">'.csrf_field().'<input type="hidden" name="action" value="assign_shift"><input type="hidden" name="month" value="'.e($month).'"><label>الموظف<select name="employee_id" required>'.options_html(array_column($employees,'name','id'),$employeeId).'</select></label><label>الوردية<select name="shift_id" required>'.options_html(array_column(all('SELECT id,name FROM shifts WHERE active=1 ORDER BY name'),'name','id'),'').'</select></label><label>تسري من<input type="date" name="effective_from" value="'.e(date('Y-m-d')).'" required></label><button class="primary-button">حفظ الوردية</button></form><div class="calendar-links"><a href="'.e(url('shifts')).'">إدارة الورديات</a>'.(can('organization.manage')?'<a href="'.e(url('holidays')).'">إدارة العطل</a>':'').'</div></section>';
}
