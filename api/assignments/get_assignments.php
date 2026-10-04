<?php
// قائمة الواجبات للدكتور مع عدد المسلّمين
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    startSession();

    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'doctor') {
        sendJSON(['success' => false, 'message' => 'غير مصرح'], 403);
    }

    $doctorId = (int) $_SESSION['user_id'];
    $subFor = isset($_GET['submissions_for']) ? (int) $_GET['submissions_for'] : 0;

    if ($subFor > 0) {
        $chk = $conn->prepare(
            'SELECT a.id FROM assignments a
             INNER JOIN courses c ON c.id = a.course_id
             WHERE a.id = ? AND c.doctor_id = ? LIMIT 1'
        );
        $chk->bind_param('ii', $subFor, $doctorId);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) {
            $chk->close();
            sendJSON(['success' => false, 'message' => 'غير موجود'], 404);
        }
        $chk->close();

        $q = $conn->prepare(
            'SELECT u.name, u.student_id, s.answer, s.submitted_at, s.score
             FROM assignment_submissions s
             INNER JOIN users u ON u.id = s.student_id
             WHERE s.assignment_id = ?
             ORDER BY s.submitted_at ASC'
        );
        $q->bind_param('i', $subFor);
        $q->execute();
        $r = $q->get_result();
        $subs = [];
        while ($row = $r->fetch_assoc()) {
            $subs[] = $row;
        }
        $q->close();
        sendJSON(['success' => true, 'message' => 'تم', 'data' => ['submissions' => $subs]]);
    }

    $courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

    if ($courseId > 0) {
        $own = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
        $own->bind_param('ii', $courseId, $doctorId);
        $own->execute();
        $own->store_result();
        if ($own->num_rows === 0) {
            $own->close();
            sendJSON(['success' => false, 'message' => 'المقرر غير موجود'], 404);
        }
        $own->close();
    }

    $sql = 'SELECT a.id, a.title, a.due_date, a.max_score, a.qr_token, c.name AS course_name,
            (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) AS submitted_count
            FROM assignments a
            INNER JOIN courses c ON c.id = a.course_id
            WHERE c.doctor_id = ?';
    if ($courseId > 0) {
        $sql .= ' AND a.course_id = ?';
    }
    $sql .= ' ORDER BY a.created_at DESC';

    if ($courseId > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $doctorId, $courseId);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $doctorId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'due_date' => $row['due_date'],
            'max_score' => (int) $row['max_score'],
            'qr_token' => $row['qr_token'],
            'course_name' => $row['course_name'],
            'submitted_count' => (int) $row['submitted_count'],
        ];
    }
    $stmt->close();

    sendJSON(['success' => true, 'message' => 'تم', 'data' => ['rows' => $rows]]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
