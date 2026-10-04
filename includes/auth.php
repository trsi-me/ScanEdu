<?php
// دوال المصادقة والجلسات وأمان رموز QR

/**
 * يُرجع المسار تحت نطاق الويب لجذر المشروع (مثل /Projects/ScanEdu أو فارغاً إن كان الجذر هو المشروع)
 */
function scaneduBaseUrl(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projRoot = str_replace('\\', '/', dirname(__DIR__));
    $rel = '';
    if ($docRoot !== '' && str_starts_with($projRoot, $docRoot)) {
        $rel = substr($projRoot, strlen($docRoot));
    }
    $rel = trim(str_replace('\\', '/', $rel), '/');
    $base = $rel === '' ? '' : '/' . $rel;
    return $base;
}

/**
 * يبني URL كاملاً نسبياً لملف داخل جذر المشروع (يعمل مع XAMPP وphp -S)
 */
function scaneduUrl(string $path): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $b = scaneduBaseUrl();
    return ($b === '' ? '' : $b) . '/' . $path;
}

/**
 * يبدأ الجلسة بإعدادات آمنة أساسية
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * مسار لوحة التحكم حسب الدور
 */
function dashboardPathForRole(string $role): string
{
    if ($role === 'admin') {
        return 'admin/dashboard.php';
    }
    if ($role === 'doctor') {
        return 'doctor/dashboard.php';
    }
    return 'student/dashboard.php';
}

/**
 * يتحقق إن كان المستخدم مسجلاً (يجب أن يوجد معرف صالح ودور admin أو doctor أو student)
 */
function isLoggedIn(): bool
{
    if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
        return false;
    }
    $r = $_SESSION['role'] ?? '';
    return $r === 'admin' || $r === 'doctor' || $r === 'student';
}

/**
 * يعيد التوجيه لصفحة الدخول إن لم يكن مسجلاً
 */
function requireLogin(): void
{
    startSession();
    if (!isLoggedIn()) {
        header('Location: ' . scaneduUrl('login.php'));
        exit;
    }
}

/**
 * يتحقق من دور المستخدم الحالي؛ إن كان مسجلاً بدور آخر يُوجَّه للوحة مناسبة بدل نص 403
 */
function requireRole(string $role): void
{
    startSession();
    if (!isLoggedIn()) {
        header('Location: ' . scaneduUrl('login.php'));
        exit;
    }
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: ' . scaneduUrl(dashboardPathForRole($_SESSION['role'] ?? 'student')));
        exit;
    }
}

/**
 * يجلب صف المستخدم الحالي من قاعدة البيانات
 */
function getCurrentUser(): ?array
{
    global $conn;
    if (!isLoggedIn() || !isset($conn) || !($conn instanceof mysqli)) {
        return null;
    }
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare(
        'SELECT u.id, u.name, u.email, u.role, u.status, u.student_id, u.department, u.department_id,
                d.name AS department_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/**
 * تشفير كلمة المرور
 */
function hashPassword(string $pass): string
{
    return password_hash($pass, PASSWORD_BCRYPT);
}

/**
 * التحقق من كلمة المرور
 */
function verifyPassword(string $pass, string $hash): bool
{
    return password_verify($pass, $hash);
}

/**
 * توليد توكن CSRF وتخزينه في الجلسة
 */
function getCsrfToken(): string
{
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * التحقق من توكن CSRF
 */
function verifyCsrfToken(?string $token): bool
{
    startSession();
    if ($token === null || $token === '') {
        return false;
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * يولّد حمولة مشفرة لرمز QR (ترميز base64 لـ JSON مع ملح عشوائي)
 */
function generateQRToken(array $data): string
{
    $data['salt'] = bin2hex(random_bytes(8));
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    return base64_encode($json);
}

/**
 * يفك الترميز ويُرجع المصفوفة أو null إن فشل التحقق
 */
function validateQRToken(string $token): ?array
{
    $decoded = base64_decode($token, true);
    if ($decoded === false) {
        return null;
    }
    $arr = json_decode($decoded, true);
    return is_array($arr) ? $arr : null;
}
