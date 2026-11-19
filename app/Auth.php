<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user'])) return null;
    if (time() - (int)($_SESSION['last_activity'] ?? 0) > App::$config['session_idle_seconds']) { unset($_SESSION['user']); return null; }
    $_SESSION['last_activity'] = time();
    return $_SESSION['user'];
}
function require_login(): array { $u = current_user(); if (!$u) redirect('login'); return $u; }
function can(string $permission): bool
{
    $u = current_user();
    if (!$u) return false;
    if ($u['role_code'] === 'admin') return true;
    return in_array($permission, $u['permissions'], true);
}
function authorize(string $permission): void { if (!can($permission)) { http_response_code(403); throw new RuntimeException('ليس لديك صلاحية للوصول إلى هذه الصفحة.'); } }
function login(string $email, string $password): bool
{
    $u = one('SELECT u.*,r.code role_code,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=?', [$email]);
    if (!$u || !$u['active'] || ($u['locked_until'] && strtotime($u['locked_until']) > time())) { password_verify($password, '$2y$10$abcdefghijklmnopqrstuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuu'); return false; }
    if (!password_verify($password, $u['password_hash'])) {
        $attempts = (int)$u['failed_attempts'] + 1;
        query('UPDATE users SET failed_attempts=?,locked_until=? WHERE id=?', [$attempts >= 5 ? 0 : $attempts, $attempts >= 5 ? date('Y-m-d H:i:s',time()+900) : null, $u['id']]);
        audit('login_failed','users',(int)$u['id']);
        return false;
    }
    if ($u['mfa_enabled']) {
        session_regenerate_id(true);
        $_SESSION['pending_mfa']=['id'=>(int)$u['id'],'started'=>time(),'attempts'=>0];
        unset($_SESSION['user']);
        return true;
    }
    finish_login($u);
    return true;
}
function finish_login(array $u): void
{
    session_regenerate_id(true);
    $permissions = array_column(all('SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=?', [$u['role_id']]), 'code');
    $_SESSION['user'] = ['id'=>(int)$u['id'],'employee_id'=>$u['employee_id'] ? (int)$u['employee_id'] : null,'email'=>$u['email'],'role_code'=>$u['role_code'],'role_name'=>$u['role_name'],'permissions'=>$permissions];
    $_SESSION['last_activity'] = time();
    query('UPDATE users SET failed_attempts=0,locked_until=NULL,last_login_at=NOW() WHERE id=?', [$u['id']]);
    audit('login','users',(int)$u['id']);
}
function logout(): void { if (current_user()) audit('logout'); $_SESSION = []; session_regenerate_id(true); }
function employee_scope(string $alias = 'e'): array
{
    $u = require_login();
    if ($u['role_code'] === 'employee') return ["$alias.id = ?", [$u['employee_id'] ?? 0]];
    if ($u['role_code'] === 'supervisor') return ["($alias.manager_id = ? OR $alias.id = ?)", [$u['employee_id'] ?? 0,$u['employee_id'] ?? 0]];
    return ['1=1', []];
}
function employee_access(int $id): void { [$where,$p]=employee_scope(); if (!scalar("SELECT COUNT(*) FROM employees e WHERE e.id=? AND $where", array_merge([$id],$p))) { http_response_code(403); throw new RuntimeException('لا يمكنك الوصول إلى بيانات هذا الموظف.'); } }
