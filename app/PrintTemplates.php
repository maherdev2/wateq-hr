<?php
declare(strict_types=1);

function organization_settings(): array
{
    $values = array_column(all('SELECT setting_key,setting_value FROM settings'), 'setting_value', 'setting_key');
    return $values + [
        'company_name' => 'واثق',
        'company_address' => '',
        'company_email' => '',
        'company_phone' => '',
        'currency' => 'ر.س',
        'contract_terms' => "يلتزم الطرفان بالمهام والحقوق المبينة في العقد المعتمد وسياسات المنشأة.\nيُصرف الراتب وفق دورة الرواتب المعتمدة بعد تطبيق الاستحقاقات والخصومات النظامية.\nتخضع ساعات العمل والإجازات والإنهاء للأنظمة السارية ولوائح المنشأة.",
        'report_footer' => 'هذا التقرير مستخرج من نظام إدارة الموظفين واثق، وتخضع بياناته للمراجعة والاعتماد.',
    ];
}

function print_letterhead(string $kind, string $title, string $reference = '', ?array $settings = null): void
{
    $settings ??= organization_settings();
    echo '<header class="document-letterhead">';
    echo '<div class="document-identity"><img src="assets/logo.png" alt="شعار '.e($settings['company_name']).'"><div><strong>'.e($settings['company_name']).'</strong><span>'.e($settings['company_address']).'</span></div></div>';
    echo '<div class="document-heading"><small>'.e($kind).'</small><h2>'.e($title).'</h2>'.($reference !== '' ? '<span>المرجع: '.e($reference).'</span>' : '').'</div>';
    echo '</header>';
    echo '<div class="document-contact"><span>'.e($settings['company_email']).'</span><span>'.e($settings['company_phone']).'</span><span>تاريخ الإصدار: '.e(date('Y/m/d')).'</span></div>';
}

function print_footer(string $label = 'اعتماد الإدارة', ?array $settings = null): void
{
    $settings ??= organization_settings();
    echo '<footer class="document-footer"><div class="document-signature"><span>'.e($label).'</span><span>الاسم والتوقيع</span></div><p>'.e($settings['report_footer']).'</p></footer>';
}
