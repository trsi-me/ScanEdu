<?php
// تسليم واجب
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    startSession();

    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
        sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول كطالب'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        sendJSON(['success' => false, 'message' => 'رمز الحماية غير صالح'], 403);
    }

    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    $answer = sanitize($_POST['answer'] ?? '');
    if ($assignmentId <= 0 || $answer === '') {
        sendJSON(['success' => false, 'message' => 'الإجابة مطلوبة'], 400);
    }

    $studentId = (int) $_SESSION['user_id'];

    $a = $conn->prepare('SELECT id, course_id, due_date FROM assignments WHERE id = ? LIMIT 1');
    $a->bind_param('i', $assignmentId);
    $a->execute();
    $asg = $a->get_result()->fetch_assoc();
    $a->close();
    if (!$asg) {
        sendJSON(['success' => false, 'message' => 'الواجب غير موجود'], 404);
    }

    $now = new DateTimeImmutable('now');
    $due = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $asg['due_date']);
    if ($due && $now > $due) {
        sendJSON(['success' => false, 'message' => 'انتهى موعد التسليم'], 410);
    }

    $courseId = (int) $asg['course_id'];
    $en = $conn->prepare('SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
    $en->bind_param('ii', $courseId, $studentId);
    $en->execute();
    $en->store_result();
    if ($en->num_rows === 0) {
        $en->close();
        sendJSON(['success' => false, 'message' => 'أنت غير مسجّل في هذا المقرر'], 403);
    }
    $en->close();

    $dup = $conn->prepare('SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1');
    $dup->bind_param('ii', $assignmentId, $studentId);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        sendJSON(['success' => false, 'message' => 'لقد سلّمت هذا الواجب مسبقاً'], 409);
    }
    $dup->close();

    $filePath = null;
    if (!empty($_FILES['file']) && isset($_FILES['file']['error']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $filePath = uploadFile($_FILES['file'], $allowed, 'uploads/assignments');
    }

    if ($filePath === null) {
        $ins = $conn->prepare('INSERT INTO assignment_submissions (assignment_id, student_id, answer) VALUES (?, ?, ?)');
        $ins->bind_param('iis', $assignmentId, $studentId, $answer);
    } else {
        $ins = $conn->prepare('INSERT INTO assignment_submissions (assignment_id, student_id, answer, file_path) VALUES (?, ?, ?, ?)');
        $ins->bind_param('iiss', $assignmentId, $studentId, $answer, $filePath);
    }
    if (!$ins->execute()) {
        $ins->close();
        sendJSON(['success' => false, 'message' => 'تعذر حفظ التسليم'], 500);
    }
    $ins->close();

    sendJSON(['success' => true, 'message' => 'تم تسليم الواجب بنجاح', 'data' => []]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
