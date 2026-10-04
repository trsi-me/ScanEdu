<?php
// نتائج الاختبار
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

    $ex = $conn->prepare(
        'SELECT e.id, e.title, c.doctor_id, c.name AS course_name
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

    if ($role === 'doctor') {
        if ((int) $exam['doctor_id'] !== $userId) {
            sendJSON(['success' => false, 'message' => 'غير مصرح'], 403);
        }
        $stmt = $conn->prepare(
            'SELECT u.name, u.student_id, s.total_score, s.submitted_at
             FROM exam_submissions s
             INNER JOIN users u ON u.id = s.student_id
             WHERE s.exam_id = ?
             ORDER BY s.submitted_at ASC'
        );
        $stmt->bind_param('i', $examId);
        $stmt->execute();
        $r = $stmt->get_result();
        $rows = [];
        while ($row = $r->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        sendJSON([
            'success' => true,
            'message' => 'تم',
            'data' => [
                'exam_title' => $exam['title'],
                'course_name' => $exam['course_name'],
                'rows' => $rows,
            ],
        ]);
    }

    if ($role === 'student') {
        $stmt = $conn->prepare(
            'SELECT total_score, submitted_at FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $examId, $userId);
        $stmt->execute();
        $mine = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        sendJSON([
            'success' => true,
            'message' => 'تم',
            'data' => [
                'exam_title' => $exam['title'],
                'course_name' => $exam['course_name'],
                'submission' => $mine,
            ],
        ]);
    }

    sendJSON(['success' => false, 'message' => 'غير مصرح'], 403);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
