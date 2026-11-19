<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core.php';
if (PHP_SAPI !== 'cli') exit(1);
$password = $argv[1] ?? '';
if (strlen($password) < 12 || !preg_match('/[A-Z]/',$password) || !preg_match('/[0-9]/',$password)) { fwrite(STDERR,"Usage: php bin/seed.php '<choose-a-strong-demo-password>'\n"); exit(1); }
$db = App::db();
if (! $db->query('SELECT COUNT(*) FROM roles')->fetchColumn()) {
    $sql = file_get_contents(dirname(__DIR__).'/database/seed.sql');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) !== '') $db->exec(trim($statement));
    }
}
$stmt=$db->prepare('INSERT INTO users (employee_id,role_id,email,password_hash) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),active=1');
foreach ([[null,1,'admin@wateq.local'],[1,2,'hr@wateq.local'],[3,3,'supervisor@wateq.local'],[4,4,'employee@wateq.local']] as [$employeeId,$roleId,$email]) {
    $stmt->execute([$employeeId,$roleId,$email,password_hash($password,PASSWORD_DEFAULT)]);
    echo "Demo user: $email\n";
}
