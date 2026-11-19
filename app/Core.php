<?php
declare(strict_types=1);

final class App
{
    private static ?PDO $db = null;
    public static array $config;

    public static function init(): void
    {
        $envFile = dirname(__DIR__) . '/.env';
        if (is_file($envFile)) {
            foreach (parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [] as $key=>$value) {
                if (getenv((string)$key) === false) putenv($key . '=' . $value);
            }
        }
        self::$config = require dirname(__DIR__) . '/config/app.php';
        date_default_timezone_set(self::$config['timezone']);
        if (PHP_SAPI !== 'cli') {
            session_name('wateq_session');
            session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: same-origin');
            header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data:; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; script-src \'self\'; font-src \'self\'; form-action \'self\'; base-uri \'self\'');
        }
    }

    public static function db(): PDO
    {
        if (self::$db) return self::$db;
        if (!extension_loaded('pdo_mysql')) throw new RuntimeException('إضافة pdo_mysql غير مفعّلة في PHP.');
        $c = require dirname(__DIR__) . '/config/database.php';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['database']);
        self::$db = new PDO($dsn, $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        return self::$db;
    }
}

function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page = 'dashboard', array $params = []): string { return 'index.php?' . http_build_query(['page' => $page] + $params); }
function redirect(string $page = 'dashboard', array $params = []): never { header('Location: ' . url($page, $params)); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) { http_response_code(419); throw new RuntimeException('انتهت صلاحية النموذج. أعد تحميل الصفحة.'); } }
function query(string $sql, array $params = []): PDOStatement { $s = App::db()->prepare($sql); $s->execute($params); return $s; }
function one(string $sql, array $params = []): ?array { $r = query($sql, $params)->fetch(); return $r ?: null; }
function all(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(); }
function scalar(string $sql, array $params = []): mixed { return query($sql, $params)->fetchColumn(); }
function audit(string $action, ?string $entity = null, ?int $id = null, string $details = ''): void { query('INSERT INTO activity_logs (user_id,action,entity,entity_id,details,ip_address) VALUES (?,?,?,?,?,?)', [$_SESSION['user']['id'] ?? null,$action,$entity,$id,$details,substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45)]); }
function notify_user(int $userId, string $title, string $body = '', string $target = ''): void { query('INSERT INTO notifications (user_id,title,body,url) VALUES (?,?,?,?)', [$userId,$title,$body,$target]); if (function_exists('send_notification_email')) send_notification_email($userId,$title,$body); }
function input(string $key): string { return trim((string)($_POST[$key] ?? '')); }
function valid_date(string $value): bool { $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value); return $d && $d->format('Y-m-d') === $value; }
function require_date(string $value, string $label): string { if (!valid_date($value)) throw new InvalidArgumentException("تاريخ $label غير صحيح."); return $value; }
function require_int(string $value, string $label): int { if (!ctype_digit($value) || (int)$value < 1) throw new InvalidArgumentException("$label غير صحيح."); return (int)$value; }
function upload_file(string $key, bool $required = false): ?string
{
    $f = $_FILES[$key] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) { if ($required) throw new InvalidArgumentException('الملف مطلوب.'); return null; }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > App::$config['upload_max_bytes']) throw new InvalidArgumentException('تعذر رفع الملف أو تجاوز 5 ميغابايت.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($types[$mime])) throw new InvalidArgumentException('الملفات المسموحة: PDF وJPG وPNG وWebP.');
    $name = bin2hex(random_bytes(20)) . '.' . $types[$mime];
    if (!move_uploaded_file($f['tmp_name'], dirname(__DIR__) . '/storage/uploads/' . $name)) throw new RuntimeException('تعذر حفظ الملف.');
    return $name;
}

App::init();
