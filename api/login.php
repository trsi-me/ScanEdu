<?php
// تسجيل الدخول — JSON
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    startSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        sendJSON(['success' => false, 'message' => 'انتهت صلاحية الجلسة، أعد تحميل الصفحة'], 403);
    }

    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        sendJSON(['success' => false, 'message' => 'البريد وكلمة المرور مطلوبان'], 400);
    }

    if (!validateEmail($email)) {
        sendJSON(['success' => false, 'message' => 'البريد غير صالح'], 400);
    }

    $stmt = $conn->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user || !verifyPassword($password, $user['password'])) {
        sendJSON(['success' => false, 'message' => 'بيانات الدخول غير صحيحة'], 401);
    }

    if (($user['status'] ?? 'active') === 'suspended') {
        sendJSON(['success' => false, 'message' => 'حسابك معلّق. تواصل مع الإدارة.'], 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];

    $redirect = dashboardPathForRole($user['role']);
    sendJSON([
        'success' => true,
        'message' => 'تم تسجيل الدخول',
        'data' => ['redirect' => $redirect],
    ]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
