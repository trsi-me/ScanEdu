<?php
// جلب بيانات اختبار للعرض أو التقديم
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    startSession();

    if (!isLoggedIn()) {
        sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول'], 403);
    }

    $examId = (int) ($_GET['exam_id'] ?? 0);
    if ($examId <= 0) {
        sendJSON(['success' => false, 'message' => 'معرّف الاختبار مطلوب'], 400);
    }

    $role = $_SESSION['role'] ?? '';
    $userId = (int) $_SESSION['user_id'];
    $takeMode = isset($_GET['take']) && $_GET['take'] === '1';

    $ex = $conn->prepare(
        'SELECT e.*, c.doctor_id, c.name AS course_name
         FROM exams e
         INNER JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? LIMIT 1'
    );
    $ex->bind_param('i', $examId);
    $ex->execute();
    $exam = $ex->get_result()->fetch_assoc();
    $ex->close();

    if (!$exam) {
        sendJSON(['success' => false, 'message' => 'الاختبار غير موجود'], 404);
    }

    $courseId = (int) $exam['course_id'];
    $now = new DateTimeImmutable('now');
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $exam['start_time']);
    $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $exam['end_time']);

    if ($role === 'doctor') {
        if ((int) $exam['doctor_id'] !== $userId) {
            sendJSON(['success' => false, 'message' => 'غير مصرح'], 403);
        }
        $includeAnswers = true;
    } else {
        $en = $conn->prepare('SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
        $en->bind_param('ii', $courseId, $userId);
        $en->execute();
        $en->store_result();
        if ($en->num_rows === 0) {
            $en->close();
            sendJSON(['success' => false, 'message' => 'أنت غير مسجّل في هذا المقرر'], 403);
        }
        $en->close();
        $includeAnswers = false;
        if ($takeMode) {
            if (!$start || !$end || $now < $start) {
                sendJSON(['success' => false, 'message' => 'الاختبار لم يبدأ بعد'], 403);
            }
            if ($now > $end) {
                sendJSON(['success' => false, 'message' => 'انتهى وقت الاختبار'], 410);
            }
            $did = $conn->prepare('SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1');
            $did->bind_param('ii', $examId, $userId);
            $did->execute();
            $did->store_result();
            if ($did->num_rows > 0) {
                $did->close();
                sendJSON(['success' => false, 'message' => 'لقد سلّمت هذا الاختبار مسبقاً'], 409);
            }
            $did->close();
        }
    }

    $q = $conn->prepare('SELECT id, question_text, question_type, options, correct_answer, score FROM exam_questions WHERE exam_id = ? ORDER BY id ASC');
    $q->bind_param('i', $examId);
    $q->execute();
    $res = $q->get_result();
    $questions = [];
    while ($row = $res->fetch_assoc()) {
        $item = [
            'id' => (int) $row['id'],
            'question_text' => $row['question_text'],
            'question_type' => $row['question_type'],
            'score' => (int) $row['score'],
            'options' => $row['options'] ? json_decode($row['options'], true) : null,
        ];
        if ($includeAnswers) {
            $item['correct_answer'] = $row['correct_answer'];
        }
        $questions[] = $item;
    }
    $q->close();

    $status = 'upcoming';
    if ($start && $end) {
        if ($now < $start) {
            $status = 'upcoming';
        } elseif ($now <= $end) {
            $status = 'running';
        } else {
            $status = 'ended';
        }
    }

    sendJSON([
        'success' => true,
        'message' => 'تم',
        'data' => [
            'exam' => [
                'id' => (int) $exam['id'],
                'title' => $exam['title'],
                'course_name' => $exam['course_name'],
                'start_time' => $exam['start_time'],
                'end_time' => $exam['end_time'],
                'duration_minutes' => (int) $exam['duration_minutes'],
                'total_score' => (int) $exam['total_score'],
                'status' => $status,
            ],
            'questions' => $questions,
        ],
    ]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
