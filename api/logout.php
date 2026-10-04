<?php
// تسجيل الخروج
require_once __DIR__ . '/../includes/auth.php';
startSession();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], !empty($p['secure']), true);
}
session_destroy();
header('Location: ' . scaneduUrl('login.php'));
exit;
