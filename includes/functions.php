<?php
// دوال مساعدة عامة للمشروع

require_once __DIR__ . '/auth.php';

/**
 * إرسال استجابة JSON موحّدة
 */
function sendJSON(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * تنظيف المدخلات النصية
 */
function sanitize(?string $input): string
{
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * التحقق من صيغة البريد الإلكتروني
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * تنسيق التاريخ للعرض بالعربية (يوم رقم شهر سنة)
 */
function formatDate(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $d = (int) date('j', $ts);
    $m = $months[(int) date('n', $ts) - 1];
    $y = date('Y', $ts);
    return $d . ' ' . $m . ' ' . $y;
}

/**
 * تنسيق الوقت للعرض (ساعة:دقيقة)
 */
function formatTime(string $time): string
{
    $ts = strtotime($time);
    if ($ts === false) {
        return $time;
    }
    return date('H:i', $ts);
}

/**
 * رفع ملف بأمان ويُرجع المسار النسبي أو null
 */
function uploadFile(array $file, array $allowedMimes, string $relativeDir): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp = $file['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if (!in_array($mime, $allowedMimes, true)) {
        return null;
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '', $ext);
    $name = bin2hex(random_bytes(16)) . ($safe ? '.' . $safe : '');
    $root = dirname(__DIR__);
    $destDir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $dest = $destDir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        return null;
    }
    return $relativeDir . '/' . $name;
}

/**
 * يُرجع النص المراد تحويله لرمز QR على الواجهة (يُستخدم مع qrcode.js)
 */
function generateQRCode(string $data): string
{
    return $data;
}

/**
 * حساب معلومات بسيطة لترقيم الصفحات
 */
function paginate(int $total, int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $pages = (int) ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'offset' => $offset,
    ];
}

/**
 * يضمن وجود 15 محاضرة (أسبوع 1–15) لكل مقرر مع عناوين وتواريخ مرجعية ثابتة
 */
function ensureCourseWeeks(mysqli $conn, int $courseId): void
{
    $base = new DateTimeImmutable('2026-01-05');
    for ($w = 1; $w <= 15; $w++) {
        $chk = $conn->prepare('SELECT id FROM lectures WHERE course_id = ? AND week_number = ? LIMIT 1');
        $chk->bind_param('ii', $courseId, $w);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($exists) {
            continue;
        }
        $tok = 'pending-' . $courseId . '-w' . $w . '-' . bin2hex(random_bytes(10));
        $exp = (new DateTimeImmutable('+400 days'))->format('Y-m-d H:i:s');
        $title = 'الأسبوع ' . $w;
        $ld = $base->modify('+' . ($w - 1) . ' weeks')->format('Y-m-d');
        $ins = $conn->prepare('INSERT INTO lectures (course_id, week_number, title, lecture_date, qr_token, qr_expires_at) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->bind_param('iissss', $courseId, $w, $title, $ld, $tok, $exp);
        $ins->execute();
        $ins->close();
    }
}

/**
 * يجلب الأقسام النشطة
 */
function getActiveDepartments(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query('SELECT id, name, description FROM departments WHERE is_active = 1 ORDER BY name ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * يجلب الترمات النشطة
 */
function getActiveTerms(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query('SELECT id, name, is_current, start_date, end_date FROM terms WHERE is_active = 1 ORDER BY is_current DESC, name DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * يجلب الترم الحالي إن وُجد
 */
function getCurrentTerm(mysqli $conn): ?array
{
    $res = $conn->query('SELECT id, name FROM terms WHERE is_active = 1 AND is_current = 1 LIMIT 1');
    if (!$res) {
        return null;
    }
    $row = $res->fetch_assoc();
    return $row ?: null;
}

/**
 * يسجّل جميع طلاب القسم النشطين في مقرر
 */
function enrollDepartmentStudents(mysqli $conn, int $courseId, int $departmentId): int
{
    $count = 0;
    $stmt = $conn->prepare(
        'SELECT id FROM users
         WHERE role = \'student\' AND status = \'active\' AND department_id = ?'
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $studentId = (int) $row['id'];
        $dup = $conn->prepare('SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
        $dup->bind_param('ii', $courseId, $studentId);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows === 0) {
            $en = $conn->prepare('INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)');
            $en->bind_param('ii', $courseId, $studentId);
            if ($en->execute()) {
                $count++;
            }
            $en->close();
        }
        $dup->close();
    }
    $stmt->close();
    if ($count > 0) {
        ensureCourseWeeks($conn, $courseId);
    }
    return $count;
}

/**
 * ينسخ تسجيلات الطلاب من مقرر سابق إلى مقرر جديد
 */
function copyCourseEnrollments(mysqli $conn, int $fromCourseId, int $toCourseId): int
{
    $count = 0;
    $stmt = $conn->prepare('SELECT student_id FROM course_enrollments WHERE course_id = ?');
    $stmt->bind_param('i', $fromCourseId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $studentId = (int) $row['student_id'];
        $dup = $conn->prepare('SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
        $dup->bind_param('ii', $toCourseId, $studentId);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows === 0) {
            $en = $conn->prepare('INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)');
            $en->bind_param('ii', $toCourseId, $studentId);
            if ($en->execute()) {
                $count++;
            }
            $en->close();
        }
        $dup->close();
    }
    $stmt->close();
    if ($count > 0) {
        ensureCourseWeeks($conn, $toCourseId);
    }
    return $count;
}
