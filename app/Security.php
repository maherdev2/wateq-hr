<?php
declare(strict_types=1);

function base32_encode(string $bytes): string
{
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits='';
    foreach (str_split($bytes) as $byte) $bits.=str_pad(decbin(ord($byte)),8,'0',STR_PAD_LEFT);
    $out=''; foreach (str_split($bits,5) as $chunk) $out.=$alphabet[bindec(str_pad($chunk,5,'0'))];
    return $out;
}
function base32_decode(string $value): string
{
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits='';
    foreach (str_split(strtoupper($value)) as $char) { $index=strpos($alphabet,$char); if ($index===false) return ''; $bits.=str_pad(decbin($index),5,'0',STR_PAD_LEFT); }
    $out=''; foreach (str_split($bits,8) as $chunk) if (strlen($chunk)===8) $out.=chr(bindec($chunk));
    return $out;
}
function totp_at(string $secret,int $step): string
{
    $key=base32_decode($secret); if ($key==='') return '';
    $counter=pack('N2',0,$step); $hash=hash_hmac('sha1',$counter,$key,true);
    $offset=ord($hash[19]) & 15;
    $number=(unpack('N',substr($hash,$offset,4))[1] & 0x7fffffff)%1000000;
    return str_pad((string)$number,6,'0',STR_PAD_LEFT);
}
function totp_valid(string $secret,string $code): bool
{
    if (!preg_match('/^\d{6}$/',$code)) return false;
    $step=(int)floor(time()/30);
    for($delta=-1;$delta<=1;$delta++) if (hash_equals(totp_at($secret,$step+$delta),$code)) return true;
    return false;
}
function mfa_code_valid(array $user,string $code): bool
{
    if (totp_valid((string)$user['totp_secret'],$code)) return true;
    if (!preg_match('/^[A-F0-9]{16}$/i',$code)) return false;
    foreach (all('SELECT id,code_hash FROM mfa_recovery_codes WHERE user_id=? AND used_at IS NULL',[$user['id']]) as $row) {
        if (password_verify(strtoupper($code),$row['code_hash'])) {
            return query('UPDATE mfa_recovery_codes SET used_at=NOW() WHERE id=? AND used_at IS NULL',[$row['id']])->rowCount()===1;
        }
    }
    return false;
}
function verify_login_mfa(): void
{
    $pending=$_SESSION['pending_mfa'] ?? null;
    if (!$pending || time()-(int)$pending['started']>300) { unset($_SESSION['pending_mfa']); throw new InvalidArgumentException('انتهت جلسة التحقق. سجّل الدخول مجددًا.'); }
    $user=one('SELECT u.*,r.code role_code,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1',[$pending['id']]);
    if (!$user || !$user['mfa_enabled'] || !mfa_code_valid($user,strtoupper(input('code')))) {
        $_SESSION['pending_mfa']['attempts']=((int)$pending['attempts'])+1;
        if ($_SESSION['pending_mfa']['attempts']>=5) unset($_SESSION['pending_mfa']);
        throw new InvalidArgumentException('رمز التحقق غير صحيح أو انتهت محاولات التحقق.');
    }
    unset($_SESSION['pending_mfa']); finish_login($user);
}
function mfa_action(string $action): void
{
    $session=require_login(); $user=one('SELECT * FROM users WHERE id=?',[$session['id']]);
    if ($action==='begin') {
        if ($user['mfa_enabled']) throw new InvalidArgumentException('التحقق الإضافي مفعل بالفعل.');
        $_SESSION['mfa_enrollment_secret']=base32_encode(random_bytes(20)); flash('success','أدخل المفتاح في تطبيق المصادقة ثم أكد الرمز.'); return;
    }
    if ($action==='enable') {
        $secret=(string)($_SESSION['mfa_enrollment_secret'] ?? '');
        if (!$secret || !password_verify(input('password'),$user['password_hash']) || !totp_valid($secret,input('code'))) throw new InvalidArgumentException('كلمة المرور أو رمز التطبيق غير صحيح.');
        $codes=[]; $db=App::db(); $ownTransaction=!$db->inTransaction(); if ($ownTransaction) $db->beginTransaction();
        try {
            query('UPDATE users SET totp_secret=?,mfa_enabled=1 WHERE id=?',[$secret,$user['id']]);
            query('DELETE FROM mfa_recovery_codes WHERE user_id=?',[$user['id']]);
            for($i=0;$i<8;$i++) { $code=strtoupper(bin2hex(random_bytes(8))); $codes[]=$code; query('INSERT INTO mfa_recovery_codes (user_id,code_hash) VALUES (?,?)',[$user['id'],password_hash($code,PASSWORD_DEFAULT)]); }
            audit('mfa_enable','users',(int)$user['id']); if ($ownTransaction) $db->commit();
        } catch (Throwable $error) { if ($ownTransaction) $db->rollBack(); throw $error; }
        unset($_SESSION['mfa_enrollment_secret']); $_SESSION['mfa_recovery_once']=$codes; flash('success','تم تفعيل التحقق الإضافي. احفظ رموز الاستعادة الآن.'); return;
    }
    if ($action==='disable') {
        if (!$user['mfa_enabled'] || !password_verify(input('password'),$user['password_hash']) || !mfa_code_valid($user,strtoupper(input('code')))) throw new InvalidArgumentException('تعذر التحقق من الهوية.');
        query('UPDATE users SET totp_secret=NULL,mfa_enabled=0 WHERE id=?',[$user['id']]);
        query('DELETE FROM mfa_recovery_codes WHERE user_id=?',[$user['id']]);
        audit('mfa_disable','users',(int)$user['id']); flash('success','تم إيقاف التحقق الإضافي.'); return;
    }
    throw new InvalidArgumentException('إجراء غير معروف.');
}

function reset_mail_ready(): bool { return getenv('MAIL_ENABLED')==='1' && filter_var(getenv('MAIL_FROM'),FILTER_VALIDATE_EMAIL) && filter_var(getenv('APP_URL'),FILTER_VALIDATE_URL); }
function password_reset_action(string $action): void
{
    if ($action==='request') {
        if (!reset_mail_ready()) throw new InvalidArgumentException('استعادة كلمة المرور غير مهيأة بعد. تواصل مع مدير النظام.');
        $email=input('email');
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('البريد الإلكتروني غير صحيح.');
        $user=one('SELECT id,email FROM users WHERE email=? AND active=1',[$email]);
        if ($user && (int)scalar('SELECT COUNT(*) FROM password_resets WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)',[$user['id']])<3) {
            $token=bin2hex(random_bytes(32));
            query('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))',[$user['id'],hash('sha256',$token)]);
            $link=rtrim((string)getenv('APP_URL'),'/').'/index.php?page=reset_password&token='.$token;
            $subject='=?UTF-8?B?'.base64_encode('استعادة كلمة المرور | واثق').'?=';
            $headers='From: '.getenv('MAIL_FROM')."\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            if (!mail($user['email'],$subject,"لإعادة تعيين كلمة المرور، افتح الرابط خلال 30 دقيقة:\n".$link,$headers)) error_log('Password reset mail delivery failed for user '.$user['id']);
        }
        flash('success','إذا كان الحساب موجودًا ومهيأ للبريد، ستصلك رسالة الاستعادة.'); return;
    }
    if ($action==='reset') {
        $token=input('token'); $new=input('new_password');
        if (!preg_match('/^[a-f0-9]{64}$/',$token)) throw new InvalidArgumentException('رابط الاستعادة غير صحيح.');
        if (strlen($new)<12 || !preg_match('/[A-Z]/',$new) || !preg_match('/[0-9]/',$new)) throw new InvalidArgumentException('استخدم 12 حرفًا على الأقل، مع حرف كبير ورقم.');
        $db=App::db(); $ownTransaction=!$db->inTransaction(); if ($ownTransaction) $db->beginTransaction();
        try {
            $row=one('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE',[hash('sha256',$token)]);
            if (!$row) throw new InvalidArgumentException('انتهت صلاحية رابط الاستعادة. اطلب رابطًا جديدًا.');
            query('UPDATE users SET password_hash=?,failed_attempts=0,locked_until=NULL WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$row['user_id']]);
            query('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$row['user_id']]);
            audit('password_reset','users',(int)$row['user_id']); if ($ownTransaction) $db->commit(); flash('success','تم تغيير كلمة المرور. سجّل الدخول الآن.');
        } catch (Throwable $error) { if ($ownTransaction) $db->rollBack(); throw $error; }
        return;
    }
    throw new InvalidArgumentException('إجراء غير معروف.');
}
