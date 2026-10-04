<?php
// تسجيل مستخدم جديد — JSON
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
        sendJSON(['success' => false, 'message' => 'انتهت صلاحية النموذج'], 403);
    }

    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize($_POST['role'] ?? '');
    $studentId = sanitize($_POST['student_id'] ?? '');
    $department = sanitize($_POST['department'] ?? '');

    if (strlen($name) < 2) {
        sendJSON(['success' => false, 'message' => 'الاسم قصير جداً'], 400);
    }
    if (!validateEmail($email)) {
        sendJSON(['success' => false, 'message' => 'البريد غير صالح'], 400);
    }
    if (strlen($password) < 6) {
        sendJSON(['success' => false, 'message' => 'كلمة المرور يجب أن لا تقل عن 6 أحرف'], 400);
    }
    if ($role !== 'doctor' && $role !== 'student') {
        sendJSON(['success' => false, 'message' => 'الدور غير صالح'], 400);
    }
    if ($role === 'student' && $studentId === '') {
        sendJSON(['success' => false, 'message' => 'رقم الطالب مطلوب'], 400);
    }
    if ($role === 'doctor') {
        $studentId = '';
    }

    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $departmentName = '';
    if ($departmentId > 0) {
        $dStmt = $conn->prepare('SELECT name FROM departments WHERE id = ? AND is_active = 1 LIMIT 1');
        $dStmt->bind_param('i', $departmentId);
        $dStmt->execute();
        $dRow = $dStmt->get_result()->fetch_assoc();
        $dStmt->close();
        if (!$dRow) {
            sendJSON(['success' => false, 'message' => 'القسم المختار غير صالح'], 400);
        }
        $departmentName = $dRow['name'];
    } elseif ($department !== '') {
        sendJSON(['success' => false, 'message' => 'اختر القسم من القائمة'], 400);
    }

    if ($role === 'student' && $departmentId <= 0) {
        sendJSON(['success' => false, 'message' => 'القسم مطلوب للطلاب'], 400);
    }

    if ($role === 'student' && $studentId !== '') {
        $sidChk = $conn->prepare('SELECT id FROM users WHERE student_id = ? LIMIT 1');
        $sidChk->bind_param('s', $studentId);
        $sidChk->execute();
        $sidChk->store_result();
        if ($sidChk->num_rows > 0) {
            $sidChk->close();
            sendJSON(['success' => false, 'message' => 'رقم الطالب مسجّل مسبقاً'], 409);
        }
        $sidChk->close();
    }

    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        sendJSON(['success' => false, 'message' => 'البريد مسجّل مسبقاً'], 409);
    }
    $stmt->close();

    $hash = hashPassword($password);
    $deptIdParam = $departmentId > 0 ? $departmentId : null;
    $ins = $conn->prepare('INSERT INTO users (name, email, password, role, status, student_id, department, department_id) VALUES (?, ?, ?, ?, \'active\', NULLIF(?, \'\'), NULLIF(?, \'\'), ?)');
    $ins->bind_param('ssssssi', $name, $email, $hash, $role, $studentId, $departmentName, $deptIdParam);
    if (!$ins->execute()) {
        $ins->close();
        sendJSON(['success' => false, 'message' => 'تعذر حفظ الحساب'], 500);
    }
    $ins->close();

    sendJSON(['success' => true, 'message' => 'تم إنشاء الحساب يمكنك تسجيل الدخول']);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
