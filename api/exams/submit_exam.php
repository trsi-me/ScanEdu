<?php
// تسليم اختبار وحساب الدرجة
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

    $examId = (int) ($_POST['exam_id'] ?? 0);
    $answersRaw = $_POST['answers'] ?? '{}';
    if ($examId <= 0) {
        sendJSON(['success' => false, 'message' => 'معرّف الاختبار مطلوب'], 400);
    }

    $answers = json_decode(is_string($answersRaw) ? $answersRaw : '{}', true);
    if (!is_array($answers)) {
        sendJSON(['success' => false, 'message' => 'صيغة الإجابات غير صالحة'], 400);
    }

    $studentId = (int) $_SESSION['user_id'];

    $ex = $conn->prepare('SELECT e.*, c.id AS cid FROM exams e INNER JOIN courses c ON c.id = e.course_id WHERE e.id = ? LIMIT 1');
    $ex->bind_param('i', $examId);
    $ex->execute();
    $exam = $ex->get_result()->fetch_assoc();
    $ex->close();
    if (!$exam) {
        sendJSON(['success' => false, 'message' => 'الاختبار غير موجود'], 404);
    }

    $courseId = (int) $exam['course_id'];
    $en = $conn->prepare('SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
    $en->bind_param('ii', $courseId, $studentId);
    $en->execute();
    $en->store_result();
    if ($en->num_rows === 0) {
        $en->close();
        sendJSON(['success' => false, 'message' => 'أنت غير مسجّل في هذا المقرر'], 403);
    }
    $en->close();

    $now = new DateTimeImmutable('now');
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $exam['start_time']);
    $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $exam['end_time']);
    if (!$start || !$end || $now < $start) {
        sendJSON(['success' => false, 'message' => 'لم يبدأ الاختبار بعد'], 403);
    }
    if ($now > $end) {
        sendJSON(['success' => false, 'message' => 'انتهى وقت الاختبار'], 410);
    }

    $dup = $conn->prepare('SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1');
    $dup->bind_param('ii', $examId, $studentId);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        sendJSON(['success' => false, 'message' => 'لقد سلّمت هذا الاختبار مسبقاً'], 409);
    }
    $dup->close();

    $q = $conn->prepare('SELECT id, question_type, correct_answer, score FROM exam_questions WHERE exam_id = ?');
    $q->bind_param('i', $examId);
    $q->execute();
    $res = $q->get_result();
    $total = 0;
    while ($row = $res->fetch_assoc()) {
        $qid = (string) $row['id'];
        $ans = isset($answers[$qid]) ? $answers[$qid] : (isset($answers[(int) $qid]) ? $answers[(int) $qid] : null);
        $type = $row['question_type'];
        if ($type === 'mcq' || $type === 'true_false') {
            $corr = (string) ($row['correct_answer'] ?? '');
            $given = is_scalar($ans) ? (string) $ans : '';
            if ($corr !== '' && $given !== '' && hash_equals($corr, $given)) {
                $total += (int) $row['score'];
            }
        }
    }
    $q->close();

    $answersJson = json_encode($answers, JSON_UNESCAPED_UNICODE);
    $ins = $conn->prepare('INSERT INTO exam_submissions (exam_id, student_id, answers, total_score) VALUES (?, ?, ?, ?)');
    $ins->bind_param('iisi', $examId, $studentId, $answersJson, $total);
    if (!$ins->execute()) {
        $ins->close();
        sendJSON(['success' => false, 'message' => 'تعذر حفظ التسليم'], 500);
    }
    $ins->close();

    sendJSON([
        'success' => true,
        'message' => 'تم تسليم الاختبار',
        'data' => ['total_score' => $total],
    ]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
