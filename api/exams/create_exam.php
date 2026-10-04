<?php
// إنشاء اختبار مع أسئلته ورمز QR
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    startSession();

    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'doctor') {
        sendJSON(['success' => false, 'message' => 'غير مصرح'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        sendJSON(['success' => false, 'message' => 'رمز الحماية غير صالح'], 403);
    }

    $title = sanitize($_POST['title'] ?? '');
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $startRaw = $_POST['start_time'] ?? '';
    $endRaw = $_POST['end_time'] ?? '';
    $duration = (int) ($_POST['duration_minutes'] ?? 0);
    $totalScore = (int) ($_POST['total_score'] ?? 100);
    $questionsJson = $_POST['questions'] ?? '[]';

    if ($title === '' || $courseId <= 0 || $startRaw === '' || $endRaw === '' || $duration <= 0) {
        sendJSON(['success' => false, 'message' => 'البيانات ناقصة'], 400);
    }

    $start = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', str_replace(' ', 'T', $startRaw));
    $end = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', str_replace(' ', 'T', $endRaw));
    if (!$start) {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startRaw);
    }
    if (!$end) {
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endRaw);
    }
    if (!$start || !$end || $end <= $start) {
        sendJSON(['success' => false, 'message' => 'أوقات الاختبار غير صالحة'], 400);
    }

    $questions = json_decode(is_string($questionsJson) ? $questionsJson : '[]', true);
    if (!is_array($questions) || count($questions) === 0) {
        sendJSON(['success' => false, 'message' => 'أضف سؤالاً واحداً على الأقل'], 400);
    }

    $doctorId = (int) $_SESSION['user_id'];
    $chk = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
    $chk->bind_param('ii', $courseId, $doctorId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        $chk->close();
        sendJSON(['success' => false, 'message' => 'المقرر غير موجود'], 404);
    }
    $chk->close();

    $conn->begin_transaction();

    $tempToken = bin2hex(random_bytes(24));
    $startStr = $start->format('Y-m-d H:i:s');
    $endStr = $end->format('Y-m-d H:i:s');

    $insE = $conn->prepare('INSERT INTO exams (course_id, title, qr_token, start_time, end_time, duration_minutes, total_score) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insE->bind_param('issssii', $courseId, $title, $tempToken, $startStr, $endStr, $duration, $totalScore);
    if (!$insE->execute()) {
        $conn->rollback();
        $insE->close();
        sendJSON(['success' => false, 'message' => 'تعذر حفظ الاختبار'], 500);
    }
    $examId = (int) $conn->insert_id;
    $insE->close();

    $qi = $conn->prepare('INSERT INTO exam_questions (exam_id, question_text, question_type, options, correct_answer, score) VALUES (?, ?, ?, ?, ?, ?)');

    foreach ($questions as $q) {
        if (!is_array($q)) {
            continue;
        }
        $qtext = sanitize($q['question_text'] ?? '');
        $qtype = sanitize($q['question_type'] ?? '');
        if ($qtext === '' || !in_array($qtype, ['mcq', 'true_false', 'essay'], true)) {
            $conn->rollback();
            sendJSON(['success' => false, 'message' => 'سؤال غير صالح'], 400);
        }
        $opts = $q['options'] ?? null;
        $optsJson = null;
        if ($opts !== null) {
            $optsJson = is_string($opts) ? $opts : json_encode($opts, JSON_UNESCAPED_UNICODE);
        }
        $corr = isset($q['correct_answer']) ? sanitize((string) $q['correct_answer']) : null;
        $qscore = isset($q['score']) ? (int) $q['score'] : 10;

        $qi->bind_param('issssi', $examId, $qtext, $qtype, $optsJson, $corr, $qscore);
        if (!$qi->execute()) {
            $conn->rollback();
            $qi->close();
            sendJSON(['success' => false, 'message' => 'تعذر حفظ الأسئلة'], 500);
        }
    }
    $qi->close();

    $payload = ['exam_id' => $examId, 'type' => 'exam'];
    $qrToken = generateQRToken($payload);
    $upd = $conn->prepare('UPDATE exams SET qr_token = ? WHERE id = ?');
    $upd->bind_param('si', $qrToken, $examId);
    $upd->execute();
    $upd->close();

    $conn->commit();

    sendJSON([
        'success' => true,
        'message' => 'تم إنشاء الاختبار',
        'data' => ['exam_id' => $examId, 'qr_token' => $qrToken],
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
