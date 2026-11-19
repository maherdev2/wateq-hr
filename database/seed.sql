USE wateq_hr;
INSERT INTO roles (id,name,code) VALUES (1,'المدير العام','admin'),(2,'مدير الموارد البشرية','hr'),(3,'المشرف','supervisor'),(4,'الموظف','employee');
INSERT INTO permissions (code,label) VALUES ('dashboard.view','عرض لوحة التحكم'),('employees.manage','إدارة الموظفين'),('organization.manage','إدارة الهيكل'),('attendance.manage','إدارة الحضور'),('attendance.self','تسجيل حضوري'),('leave.manage','إدارة الإجازات'),('leave.self','طلب إجازة'),('contracts.manage','إدارة العقود'),('documents.manage','إدارة الوثائق'),('salaries.manage','إدارة الرواتب'),('reviews.manage','إدارة التقييم'),('trainings.manage','إدارة التدريب'),('reports.view','عرض التقارير'),('users.manage','إدارة المستخدمين'),('settings.manage','إدارة الإعدادات'),('backup.create','إنشاء نسخة احتياطية');
INSERT INTO alert_rules (kind,days_before) VALUES ('contract',30),('contract',7),('contract',1),('contract',0),('document',30),('document',7),('document',1),('document',0),('training',30),('training',7),('training',1),('training',0);
INSERT INTO role_permissions SELECT 1,id FROM permissions;
INSERT INTO role_permissions SELECT 2,id FROM permissions WHERE code IN ('dashboard.view','employees.manage','organization.manage','attendance.manage','attendance.self','leave.manage','leave.self','contracts.manage','documents.manage','salaries.manage','reviews.manage','trainings.manage','reports.view');
INSERT INTO role_permissions SELECT 3,id FROM permissions WHERE code IN ('dashboard.view','attendance.manage','attendance.self','leave.manage','leave.self','reports.view');
INSERT INTO role_permissions SELECT 4,id FROM permissions WHERE code IN ('dashboard.view','attendance.self','leave.self');
INSERT INTO branches (name,city) VALUES ('المقر الرئيسي','الرياض');
INSERT INTO departments (branch_id,name) VALUES (1,'الموارد البشرية'),(1,'المالية'),(1,'العمليات'),(1,'تقنية المعلومات');
INSERT INTO grades (name) VALUES ('إداري'),('مشرف'),('موظف');
INSERT INTO job_titles (department_id,grade_id,name) VALUES (1,1,'مدير الموارد البشرية'),(2,3,'محاسب'),(3,2,'مشرف عمليات'),(4,3,'مطور نظم');
INSERT INTO shifts (name,starts_at,ends_at) VALUES ('الدوام الصباحي','09:00:00','17:00:00'),('الدوام المسائي','16:00:00','00:00:00');
INSERT INTO leave_types (name,annual_days,count_mode,paid,requires_attachment) VALUES ('سنوية',30,'workdays',1,0),('مرضية',15,'calendar',1,1),('اضطرارية',5,'workdays',1,0),('بدون راتب',0,'workdays',0,0),('وضع',70,'calendar',1,1),('زواج',5,'calendar',1,0),('وفاة',3,'calendar',1,0),('أخرى',0,'calendar',0,0);
INSERT INTO settings (setting_key,setting_value) VALUES ('company_name','واثق'),('company_address','الرياض، المملكة العربية السعودية'),('company_email','info@wateq.local'),('company_phone',''),('work_start','09:00'),('work_end','17:00'),('work_days','0,1,2,3,4'),('currency','ر.س'),('timezone','Asia/Riyadh'),('alert_days','7,30,60,90'),('contract_terms','يلتزم الطرفان بالمهام والحقوق المبينة في العقد المعتمد وسياسات المنشأة.\nيُصرف الراتب وفق دورة الرواتب المعتمدة بعد تطبيق الاستحقاقات والخصومات النظامية.\nتخضع ساعات العمل والإجازات والإنهاء للأنظمة السارية ولوائح المنشأة.'),('report_footer','هذا التقرير مستخرج من نظام إدارة الموظفين واثق، وتخضع بياناته للمراجعة والاعتماد.');
INSERT INTO employees (employee_no,first_name,father_name,last_name,national_id,nationality,gender,phone,email,branch_id,department_id,job_title_id,hired_at,base_salary,status) VALUES
('WT-001','أحمد','محمد','العتيبي','1000000001','سعودي','male','0500000001','ahmad@wateq.local',1,1,1,'2024-01-14',12000,'active'),
('WT-002','سارة','خالد','القحطاني','1000000002','سعودية','female','0500000002','sara@wateq.local',1,2,2,'2024-04-07',8500,'active'),
('WT-003','عبدالله','ناصر','الحربي','1000000003','سعودي','male','0500000003','abdullah@wateq.local',1,3,3,'2025-02-02',9000,'active'),
('WT-004','نورة','إبراهيم','الدوسري','1000000004','سعودية','female','0500000004','noura@wateq.local',1,3,3,'2025-06-01',7000,'active');
UPDATE employees SET manager_id=3 WHERE id=4;
INSERT INTO employee_shifts (employee_id,shift_id,effective_from) VALUES (1,1,'2024-01-14'),(2,1,'2024-04-07'),(3,1,'2025-02-02'),(4,1,'2025-06-01');
INSERT INTO leave_balances (employee_id,leave_type_id,balance_year,allocated) SELECT e.id,l.id,YEAR(CURRENT_DATE),l.annual_days FROM employees e CROSS JOIN leave_types l WHERE l.annual_days>0;
INSERT INTO contracts (employee_id,contract_no,type,starts_on,ends_on,salary) VALUES (4,'WT-C-004','سنوي',DATE_SUB(CURRENT_DATE,INTERVAL 11 MONTH),DATE_ADD(CURRENT_DATE,INTERVAL 28 DAY),7000);
INSERT INTO attendance (employee_id,shift_id,work_date,check_in,status) VALUES (1,1,CURRENT_DATE,CONCAT(CURRENT_DATE,' 09:02:00'),'present'),(2,1,CURRENT_DATE,CONCAT(CURRENT_DATE,' 09:14:00'),'late');
INSERT INTO leave_requests (employee_id,leave_type_id,starts_on,ends_on,days,reason) VALUES (4,1,DATE_ADD(CURRENT_DATE,INTERVAL 14 DAY),DATE_ADD(CURRENT_DATE,INTERVAL 16 DAY),3,'إجازة عائلية');
INSERT INTO salaries (employee_id,pay_month,base_salary,allowances,deductions,bonuses,status) VALUES (4,DATE_FORMAT(CURRENT_DATE,'%Y-%m-01'),7000,500,100,250,'draft');
INSERT INTO performance_reviews (employee_id,period_start,period_end,attendance_score,quality_score,productivity_score,cooperation_score,strengths,improvements) VALUES (4,DATE_SUB(CURRENT_DATE,INTERVAL 90 DAY),CURRENT_DATE,4,5,4,5,'التعاون','تنظيم الوقت');
INSERT INTO trainings (employee_id,course_name,provider,starts_on,ends_on,status) VALUES (4,'أساسيات القيادة','الأكاديمية المهنية',DATE_SUB(CURRENT_DATE,INTERVAL 30 DAY),DATE_SUB(CURRENT_DATE,INTERVAL 28 DAY),'completed');
