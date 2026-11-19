<?php
declare(strict_types=1);

function entities(): array
{
    $text = fn(string $label, bool $required = false) => ['label'=>$label,'type'=>'text','required'=>$required];
    $date = fn(string $label, bool $required = false) => ['label'=>$label,'type'=>'date','required'=>$required];
    $money = fn(string $label) => ['label'=>$label,'type'=>'number','step'=>'0.01'];
    $ref = fn(string $label, string $table, bool $required = false) => ['label'=>$label,'type'=>'select','table'=>$table,'required'=>$required];
    $file = fn(string $label) => ['label'=>$label,'type'=>'file'];
    return [
      'employees'=>['title'=>'الموظفون','table'=>'employees','permission'=>'employees.manage','list'=>['employee_no','first_name','last_name','department_id','job_title_id','status'], 'fields'=>[
        'employee_no'=>$text('الرقم الوظيفي',true),'first_name'=>$text('الاسم الأول',true),'father_name'=>$text('اسم الأب'),'last_name'=>$text('اسم العائلة',true),
        'national_id'=>$text('رقم الهوية أو الإقامة'),'nationality'=>$text('الجنسية'),'gender'=>['label'=>'الجنس','type'=>'enum','options'=>['male'=>'ذكر','female'=>'أنثى']],
        'birth_date'=>$date('تاريخ الميلاد'),'phone'=>$text('الجوال'),'email'=>['label'=>'البريد الإلكتروني','type'=>'email'],'address'=>$text('العنوان'),
        'branch_id'=>$ref('الفرع','branches'),'department_id'=>$ref('القسم','departments'),'job_title_id'=>$ref('المسمى الوظيفي','job_titles'),'manager_id'=>$ref('المدير المباشر','employees'),
        'hired_at'=>$date('تاريخ التعيين',true),'contract_type'=>$text('نوع العقد'),'base_salary'=>$money('الراتب الأساسي'),'allowances'=>$money('البدلات'),
        'status'=>['label'=>'الحالة','type'=>'enum','required'=>true,'options'=>['active'=>'على رأس العمل','leave'=>'في إجازة','suspended'=>'موقوف','resigned'=>'مستقيل','terminated'=>'منتهية خدمته']],
        'photo_path'=>$file('صورة الموظف'),'notes'=>['label'=>'ملاحظات','type'=>'textarea']]],
      'departments'=>['title'=>'الأقسام','table'=>'departments','permission'=>'organization.manage','list'=>['name','branch_id','manager_employee_id'],'fields'=>['name'=>$text('اسم القسم',true),'branch_id'=>$ref('الفرع','branches'),'manager_employee_id'=>$ref('المدير','employees')]],
      'branches'=>['title'=>'الفروع','table'=>'branches','permission'=>'organization.manage','list'=>['name','city','address'],'fields'=>['name'=>$text('اسم الفرع',true),'city'=>$text('المدينة'),'address'=>$text('العنوان')]],
      'grades'=>['title'=>'الدرجات الوظيفية','table'=>'grades','permission'=>'organization.manage','list'=>['name','description'],'fields'=>['name'=>$text('الدرجة',true),'description'=>$text('الوصف')]],
      'job_titles'=>['title'=>'المسميات الوظيفية','table'=>'job_titles','permission'=>'organization.manage','list'=>['name','department_id','grade_id'],'fields'=>['name'=>$text('المسمى',true),'department_id'=>$ref('القسم','departments'),'grade_id'=>$ref('الدرجة','grades')]],
      'shifts'=>['title'=>'الورديات','table'=>'shifts','permission'=>'attendance.manage','list'=>['name','starts_at','ends_at','work_days','grace_minutes','active'],'fields'=>['name'=>$text('اسم الوردية',true),'starts_at'=>['label'=>'بداية الدوام','type'=>'time','required'=>true],'ends_at'=>['label'=>'نهاية الدوام','type'=>'time','required'=>true],'work_days'=>$text('أيام العمل (0 الأحد إلى 6 السبت؛ اتركه فارغًا لاعتماد الإعداد العام)'),'grace_minutes'=>['label'=>'دقائق السماح','type'=>'number'],'active'=>['label'=>'مفعّلة','type'=>'enum','options'=>['1'=>'نعم','0'=>'لا']]]],
      'holidays'=>['title'=>'العطل الرسمية','table'=>'company_holidays','permission'=>'organization.manage','list'=>['title','starts_on','ends_on','branch_id','is_day_off'],'fields'=>['title'=>$text('اسم العطلة',true),'starts_on'=>$date('البداية',true),'ends_on'=>$date('النهاية',true),'branch_id'=>$ref('الفرع (فارغ لكل الفروع)','branches'),'is_day_off'=>['label'=>'يوم غير محسوب','type'=>'enum','options'=>['1'=>'نعم','0'=>'لا']]]],
      'leave_types'=>['title'=>'أنواع الإجازات','table'=>'leave_types','permission'=>'leave.manage','list'=>['name','annual_days','count_mode','paid','requires_attachment'],'fields'=>['name'=>$text('النوع',true),'annual_days'=>$money('الرصيد السنوي'),'count_mode'=>['label'=>'طريقة احتساب الأيام','type'=>'enum','required'=>true,'options'=>['calendar'=>'أيام تقويمية','workdays'=>'أيام العمل فقط']],'paid'=>['label'=>'مدفوعة','type'=>'enum','options'=>['1'=>'نعم','0'=>'لا']],'requires_attachment'=>['label'=>'يتطلب مرفقًا','type'=>'enum','options'=>['1'=>'نعم','0'=>'لا']]]],
      'contracts'=>['title'=>'العقود','table'=>'contracts','permission'=>'contracts.manage','list'=>['employee_id','contract_no','type','starts_on','ends_on','status'],'fields'=>['employee_id'=>$ref('الموظف','employees',true),'contract_no'=>$text('رقم العقد',true),'type'=>$text('نوع العقد',true),'starts_on'=>$date('البداية',true),'ends_on'=>$date('النهاية'),'salary'=>$money('الراتب'),'file_path'=>$file('ملف العقد'),'notes'=>['label'=>'ملاحظات','type'=>'textarea']]],
      'documents'=>['title'=>'الوثائق','table'=>'documents','permission'=>'documents.manage','list'=>['employee_id','title','type','issued_on','expires_on'],'fields'=>['employee_id'=>$ref('الموظف','employees',true),'title'=>$text('اسم الوثيقة',true),'type'=>$text('النوع',true),'issued_on'=>$date('الإصدار'),'expires_on'=>$date('الانتهاء'),'file_path'=>$file('الملف'),'notes'=>['label'=>'ملاحظات','type'=>'textarea']]],
      'salaries'=>['title'=>'الرواتب','table'=>'salaries','permission'=>'salaries.manage','list'=>['employee_id','pay_month','base_salary','allowances','deductions','net_salary','status'],'fields'=>['employee_id'=>$ref('الموظف','employees',true),'pay_month'=>$date('شهر الراتب',true),'base_salary'=>$money('الأساسي'),'allowances'=>$money('البدلات'),'deductions'=>$money('الخصومات'),'advances'=>$money('السلف'),'bonuses'=>$money('المكافآت'),'overtime'=>$money('الإضافي'),'status'=>['label'=>'الحالة','type'=>'enum','options'=>['draft'=>'مسودة','review'=>'قيد المراجعة','approved'=>'معتمد','paid'=>'مصروف']],'notes'=>['label'=>'ملاحظات','type'=>'textarea']]],
      'reviews'=>['title'=>'تقييم الأداء','table'=>'performance_reviews','permission'=>'reviews.manage','list'=>['employee_id','period_start','period_end','final_score'],'fields'=>['employee_id'=>$ref('الموظف','employees',true),'period_start'=>$date('بداية الفترة',true),'period_end'=>$date('نهاية الفترة',true),'attendance_score'=>['label'=>'الالتزام من 5','type'=>'number','required'=>true,'min'=>1,'max'=>5],'quality_score'=>['label'=>'جودة العمل من 5','type'=>'number','required'=>true,'min'=>1,'max'=>5],'productivity_score'=>['label'=>'الإنتاجية من 5','type'=>'number','required'=>true,'min'=>1,'max'=>5],'cooperation_score'=>['label'=>'التعاون من 5','type'=>'number','required'=>true,'min'=>1,'max'=>5],'strengths'=>['label'=>'نقاط القوة','type'=>'textarea'],'improvements'=>['label'=>'نقاط التحسين','type'=>'textarea'],'notes'=>['label'=>'ملاحظات المدير','type'=>'textarea']]],
      'trainings'=>['title'=>'التدريب والشهادات','table'=>'trainings','permission'=>'trainings.manage','list'=>['employee_id','course_name','provider','starts_on','status'],'fields'=>['employee_id'=>$ref('الموظف','employees',true),'course_name'=>$text('الدورة',true),'provider'=>$text('الجهة المنفذة'),'starts_on'=>$date('البداية'),'ends_on'=>$date('النهاية'),'status'=>['label'=>'الحالة','type'=>'enum','options'=>['planned'=>'مخططة','ongoing'=>'جارية','completed'=>'مكتملة']],'certificate_path'=>$file('الشهادة'),'certificate_expires_on'=>$date('انتهاء الشهادة')]],
    ];
}
function entity_name(string $entity): string { return entities()[$entity]['title'] ?? ''; }
function option_label(string $field, mixed $value, array $def): string
{
    if ($value === null || $value === '') return '—';
    if (isset($def['options'])) return $def['options'][(string)$value] ?? (string)$value;
    if (isset($def['table'])) {
        $table = $def['table'];
        $r = one($table === 'employees' ? 'SELECT CONCAT(first_name," ",last_name) name FROM employees WHERE id=?' : "SELECT name FROM $table WHERE id=?", [$value]);
        return $r['name'] ?? '—';
    }
    return (string)$value;
}
function field_options(array $def): array
{
    if (isset($def['options'])) return $def['options'];
    if (!isset($def['table'])) return [];
    $table = $def['table'];
    $rows = $table === 'employees' ? all('SELECT id,CONCAT(first_name," ",last_name) name FROM employees ORDER BY first_name') : all("SELECT id,name FROM $table ORDER BY name");
    return array_column($rows,'name','id');
}
