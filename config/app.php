<?php
declare(strict_types=1);
return [
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Riyadh',
    'session_idle_seconds' => 1800,
    'upload_max_bytes' => 5 * 1024 * 1024,
];
