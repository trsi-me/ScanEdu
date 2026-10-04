<?php
// قائمة الطلاب المسجّلين في المقرر مع حالة الحضور للمحاضرة (حاضر / غائب)
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    startSession();

    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'doctor') {
        sendJSON(['success' => false, 'message' => 'غير مصرح'], 403);
    }

    $lectureId = (int) ($_GET['lecture_id'] ?? 0);
    if ($lectureId <= 0) {
        sendJSON(['success' => false, 'message' => 'معرّف المحاضرة مطلوب'], 400);
    }

    $doctorId = (int) $_SESSION['user_id'];
    $own = $conn->prepare(
        'SELECT l.id, l.week_number FROM lectures l
         INNER JOIN courses c ON c.id = l.course_id
         WHERE l.id = ? AND c.doctor_id = ? LIMIT 1'
    );
    $own->bind_param('ii', $lectureId, $doctorId);
    $own->execute();
    $lecMeta = $own->get_result()->fetch_assoc();
    $own->close();
    if (!$lecMeta) {
        sendJSON(['success' => false, 'message' => 'المحاضرة غير موجودة'], 404);
    }

    $sql = 'SELECT u.id AS student_id, u.name, u.student_id AS student_id_num, u.email, u.department,
                   a.scanned_at,
                   CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END AS is_present
            FROM course_enrollments ce
            INNER JOIN lectures l ON l.id = ? AND l.course_id = ce.course_id
            INNER JOIN users u ON u.id = ce.student_id
            LEFT JOIN attendance a ON a.lecture_id = l.id AND a.student_id = ce.student_id
            ORDER BY u.name ASC';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $lectureId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'student_id' => (int) $row['student_id'],
            'name' => $row['name'],
            'student_id_num' => $row['student_id_num'],
            'email' => $row['email'] ?? '',
            'department' => $row['department'] ?? '',
            'scanned_at' => $row['scanned_at'],
            'present' => (int) $row['is_present'] === 1,
        ];
    }
    $stmt->close();

    sendJSON([
        'success' => true,
        'message' => 'تم الجلب',
        'data' => [
            'rows' => $rows,
            'week_number' => (int) ($lecMeta['week_number'] ?? 0),
        ],
    ]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
