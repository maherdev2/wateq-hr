<?php
declare(strict_types=1);

function view_employee_file(): void
{
    $id=require_int((string)($_GET['id'] ?? ''),'الموظف');
    employee_access($id);
    $employee=one('SELECT e.*,d.name department,j.name job_title,b.name branch FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN job_titles j ON j.id=e.job_title_id LEFT JOIN branches b ON b.id=e.branch_id WHERE e.id=?',[$id]);
    if (!$employee) throw new InvalidArgumentException('الموظف غير موجود.');
    $name=trim($employee['first_name'].' '.$employee['last_name']);
    $own=(int)(current_user()['employee_id'] ?? 0)===$id;
    echo '<section class="panel employee-file-head"><div><p class="eyebrow">'.e($employee['employee_no']).'</p><h2>'.e($name).'</h2><p>'.e($employee['job_title'] ?? '—').' · '.e($employee['department'] ?? '—').' · '.e($employee['branch'] ?? '—').'</p></div><span class="badge-soft">'.e(report_translate($employee['status'])).'</span></section>';
    $contracts=all('SELECT id,contract_no,status,version,starts_on,ends_on FROM contracts WHERE employee_id=? ORDER BY starts_on DESC',[$id]);
    $documents=all('SELECT id,title,type,issued_on,expires_on FROM documents WHERE employee_id=? ORDER BY id DESC',[$id]);
    $leaves=all('SELECT l.starts_on,l.ends_on,l.status,t.name type_name FROM leave_requests l JOIN leave_types t ON t.id=l.leave_type_id WHERE l.employee_id=? ORDER BY l.id DESC LIMIT 12',[$id]);
    $attendance=all('SELECT work_date,status,check_in,check_out FROM attendance WHERE employee_id=? ORDER BY work_date DESC LIMIT 12',[$id]);
    $salaryAllowed=can('salaries.manage') || $own;
    $salaries=$salaryAllowed?all('SELECT id,pay_month,net_salary,status FROM salaries WHERE employee_id=? ORDER BY pay_month DESC LIMIT 12',[$id]):[];
    $reviews=all('SELECT period_end,final_score FROM performance_reviews WHERE employee_id=? ORDER BY period_end DESC LIMIT 8',[$id]);
    echo '<div class="file-overview">';
    foreach ([['العقود',count($contracts)],['الوثائق',count($documents)],['طلبات الإجازة',count($leaves)],['سجلات الحضور الأخيرة',count($attendance)]] as [$label,$count]) echo '<div><strong>'.e($count).'</strong><span>'.e($label).'</span></div>';
    echo '</div><div class="employee-file-grid">';
    echo '<section class="panel"><h3>العقود</h3>';
    if (!$contracts) echo '<p class="muted">لا توجد عقود.</p>';
    foreach ($contracts as $row) echo '<div class="file-row"><div><strong>'.e($row['contract_no']).'</strong><small>'.e($row['starts_on']).' — '.e($row['ends_on'] ?? 'مفتوح').'</small></div><div><span class="badge-soft">'.e(contract_status_label($row['status'])).'</span> '.($own || can('contracts.manage')?'<a href="'.e(url('contract_print',['id'=>$row['id']])).'">عرض</a>':'').'</div></div>';
    echo '</section><section class="panel"><h3>الوثائق</h3>';
    if (!$documents) echo '<p class="muted">لا توجد وثائق.</p>';
    foreach ($documents as $row) echo '<div class="file-row"><div><strong>'.e($row['title']).'</strong><small>'.e($row['type']).' · الانتهاء: '.e($row['expires_on'] ?? '—').'</small></div>'.($own || can('documents.manage')?'<a href="'.e(url('download',['entity'=>'documents','id'=>$row['id'],'field'=>'file_path'])).'">تحميل</a>':'').'</div>';
    echo '</section><section class="panel"><h3>الحضور الأخير</h3>';
    if (!$attendance) echo '<p class="muted">لا توجد قيود حضور.</p>';
    foreach ($attendance as $row) echo '<div class="file-row"><div><strong>'.e($row['work_date']).'</strong><small>'.e($row['check_in'] ?? '—').' — '.e($row['check_out'] ?? '—').'</small></div><span class="badge-soft">'.e(report_translate($row['status'])).'</span></div>';
    echo '</section><section class="panel"><h3>الإجازات</h3>';
    if (!$leaves) echo '<p class="muted">لا توجد طلبات.</p>';
    foreach ($leaves as $row) echo '<div class="file-row"><div><strong>'.e($row['type_name']).'</strong><small>'.e($row['starts_on']).' — '.e($row['ends_on']).'</small></div><span class="badge-soft">'.e(report_translate($row['status'])).'</span></div>';
    echo '</section>';
    if ($salaryAllowed) { echo '<section class="panel"><h3>الرواتب</h3>'; if (!$salaries) echo '<p class="muted">لا توجد قسائم.</p>'; foreach ($salaries as $row) echo '<div class="file-row"><div><strong>'.e($row['pay_month']).'</strong><small>الصافي '.e(number_format((float)$row['net_salary'],2)).'</small></div><a href="'.e(url('salary_slip',['id'=>$row['id']])).'">القسيمة</a></div>'; echo '</section>'; }
    echo '<section class="panel"><h3>تقييمات الأداء</h3>'; if (!$reviews) echo '<p class="muted">لا توجد تقييمات.</p>'; foreach ($reviews as $row) echo '<div class="file-row"><strong>'.e($row['period_end']).'</strong><span class="badge-soft">'.e($row['final_score']).' / 5</span></div>'; echo '</section></div>';
    $timeline=[];
    foreach ($contracts as $row) $timeline[]=['date'=>$row['starts_on'],'label'=>'بداية العقد '.$row['contract_no']];
    foreach ($leaves as $row) $timeline[]=['date'=>$row['starts_on'],'label'=>'إجازة '.$row['type_name'].' · '.report_translate($row['status'])];
    foreach ($reviews as $row) $timeline[]=['date'=>$row['period_end'],'label'=>'تقييم أداء: '.$row['final_score'].' / 5'];
    foreach ($documents as $row) if ($row['issued_on']) $timeline[]=['date'=>$row['issued_on'],'label'=>'إصدار وثيقة '.$row['title']];
    usort($timeline,fn($a,$b)=>strcmp($b['date'],$a['date']));
    echo '<section class="panel file-timeline"><h3>التسلسل الزمني</h3>';
    if (!$timeline) echo '<p class="muted">لا توجد أحداث بعد.</p>';
    foreach (array_slice($timeline,0,20) as $event) echo '<div><time>'.e($event['date']).'</time><span>'.e($event['label']).'</span></div>';
    echo '</section>';
}
