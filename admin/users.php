<?php
// إدارة المستخدمين (دكاترة، طلاب)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('admin');
$flash = '';
$filterRole = sanitize($_GET['role'] ?? '');

function syncDepartmentName(mysqli $conn, int $userId, ?int $departmentId): void
{
    $deptName = '';
    if ($departmentId !== null && $departmentId > 0) {
        $d = $conn->prepare('SELECT name FROM departments WHERE id = ? LIMIT 1');
        $d->bind_param('i', $departmentId);
        $d->execute();
        $row = $d->get_result()->fetch_assoc();
        $d->close();
        $deptName = $row['name'] ?? '';
    }
    $u = $conn->prepare('UPDATE users SET department = NULLIF(?, \'\') WHERE id = ?');
    $u->bind_param('si', $deptName, $userId);
    $u->execute();
    $u->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitize($_POST['role'] ?? '');
        $studentId = sanitize($_POST['student_id'] ?? '');
        $departmentId = (int) ($_POST['department_id'] ?? 0);

        if ($name === '' || !validateEmail($email) || strlen($password) < 6) {
            $flash = 'تحقق من الاسم والبريد وكلمة المرور (6 أحرف على الأقل)';
        } elseif ($role !== 'doctor' && $role !== 'student') {
            $flash = 'الدور غير صالح';
        } elseif ($role === 'student' && $studentId === '') {
            $flash = 'رقم الطالب مطلوب';
        } elseif ($departmentId <= 0) {
            $flash = 'اختر القسم';
        } else {
            $em = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $em->bind_param('s', $email);
            $em->execute();
            $em->store_result();
            if ($em->num_rows > 0) {
                $flash = 'البريد مسجّل مسبقاً';
                $em->close();
            } else {
                $em->close();
                if ($role === 'student') {
                    $sidChk = $conn->prepare('SELECT id FROM users WHERE student_id = ? LIMIT 1');
                    $sidChk->bind_param('s', $studentId);
                    $sidChk->execute();
                    $sidChk->store_result();
                    if ($sidChk->num_rows > 0) {
                        $flash = 'رقم الطالب مسجّل مسبقاً';
                        $sidChk->close();
                    } else {
                        $sidChk->close();
                    }
                }
                if ($flash === '') {
                    if ($role === 'doctor') {
                        $studentId = '';
                    }
                    $hash = hashPassword($password);
                    $ins = $conn->prepare('INSERT INTO users (name, email, password, role, status, student_id, department_id) VALUES (?, ?, ?, ?, \'active\', NULLIF(?, \'\'), ?)');
                    $ins->bind_param('sssssi', $name, $email, $hash, $role, $studentId, $departmentId);
                    if ($ins->execute()) {
                        syncDepartmentName($conn, (int) $conn->insert_id, $departmentId);
                        $flash = 'تم إضافة المستخدم';
                    } else {
                        $flash = 'تعذر الإضافة';
                    }
                    $ins->close();
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $role = sanitize($_POST['role'] ?? '');
        $studentId = sanitize($_POST['student_id'] ?? '');
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if ($userId <= 0 || $name === '' || !validateEmail($email)) {
            $flash = 'بيانات غير صالحة';
        } else {
            $chk = $conn->prepare('SELECT role FROM users WHERE id = ? AND role != \'admin\' LIMIT 1');
            $chk->bind_param('i', $userId);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$existing) {
                $flash = 'المستخدم غير موجود';
            } else {
                if ($password !== '' && strlen($password) >= 6) {
                    $hash = hashPassword($password);
                    $upd = $conn->prepare('UPDATE users SET name = ?, email = ?, role = ?, student_id = NULLIF(?, \'\'), department_id = ?, password = ? WHERE id = ?');
                    $upd->bind_param('ssssisi', $name, $email, $role, $studentId, $departmentId, $hash, $userId);
                } else {
                    $upd = $conn->prepare('UPDATE users SET name = ?, email = ?, role = ?, student_id = NULLIF(?, \'\'), department_id = ? WHERE id = ?');
                    $upd->bind_param('ssssii', $name, $email, $role, $studentId, $departmentId, $userId);
                }
                $flash = $upd->execute() ? 'تم تحديث المستخدم' : 'تعذر التحديث';
                $upd->close();
                if (str_contains($flash, 'تم')) {
                    syncDepartmentName($conn, $userId, $departmentId);
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $upd = $conn->prepare('UPDATE users SET status = IF(status = \'active\', \'suspended\', \'active\') WHERE id = ? AND role != \'admin\'');
        $upd->bind_param('i', $userId);
        $flash = $upd->execute() ? 'تم تغيير حالة الحساب' : 'تعذر التغيير';
        $upd->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $del = $conn->prepare('DELETE FROM users WHERE id = ? AND role != \'admin\'');
        $del->bind_param('i', $userId);
        if ($del->execute() && $del->affected_rows > 0) {
            $flash = 'تم حذف المستخدم';
        } else {
            $flash = 'تعذر الحذف (ربما مرتبط ببيانات)';
        }
        $del->close();
    }
}

$departments = getActiveDepartments($conn);
$users = [];
$sql = 'SELECT u.id, u.name, u.email, u.role, u.status, u.student_id, u.department_id, d.name AS department_name
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.role != \'admin\'';
if ($filterRole === 'doctor' || $filterRole === 'student') {
    $sql .= ' AND u.role = \'' . ($filterRole === 'doctor' ? 'doctor' : 'student') . '\'';
}
$sql .= ' ORDER BY u.role ASC, u.name ASC';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

$pageTitle = 'المستخدمون';
$activeNav = 'users';
$role = 'admin';
$extraCss = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">إدارة المستخدمين</h1>
<p class="text-muted">سجّل الطلاب مرة واحدة — يبقون في النظام بين الترمات. عيّن كل دكتور وطالب لقسمه.</p>
<?php if ($flash !== ''): ?>
    <div class="alert <?php echo str_contains($flash, 'تم') ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<p>
    <a href="users.php" class="btn btn-outline btn-sm <?php echo $filterRole === '' ? 'btn-primary' : ''; ?>">الكل</a>
    <a href="users.php?role=doctor" class="btn btn-outline btn-sm <?php echo $filterRole === 'doctor' ? 'btn-primary' : ''; ?>">دكاترة</a>
    <a href="users.php?role=student" class="btn btn-outline btn-sm <?php echo $filterRole === 'student' ? 'btn-primary' : ''; ?>">طلاب</a>
</p>

<div class="assign-form-card">
    <h2 class="h2-title">إضافة مستخدم</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="add_user" value="1">
        <div class="form-group">
            <label>الاسم</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>البريد</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
            <label>كلمة المرور</label>
            <input type="password" name="password" class="form-control" required minlength="6">
        </div>
        <div class="form-group">
            <label>الدور</label>
            <select name="role" class="form-control" required>
                <option value="student">طالب</option>
                <option value="doctor">دكتور</option>
            </select>
        </div>
        <div class="form-group">
            <label>رقم الطالب (للطلاب)</label>
            <input type="text" name="student_id" class="form-control">
        </div>
        <div class="form-group">
            <label>القسم</label>
            <select name="department_id" class="form-control" required>
                <option value="">— اختر —</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo (int) $dept['id']; ?>"><?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> إضافة</button>
    </form>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد</th>
                <th>الدور</th>
                <th>الرقم الجامعي</th>
                <th>القسم</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $u['role'] === 'doctor' ? 'دكتور' : 'طالب'; ?></td>
                    <td><?php echo htmlspecialchars($u['student_id'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($u['department_name'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo ($u['status'] ?? 'active') === 'active' ? 'نشط' : 'معلّق'; ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="toggle_status" value="1">
                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?php echo ($u['status'] ?? 'active') === 'active' ? 'تعليق' : 'تفعيل'; ?></button>
                        </form>
                        <details style="display:inline-block;margin-right:6px;">
                            <summary class="btn btn-secondary btn-sm">تعديل</summary>
                            <form method="post" style="margin-top:8px;padding:8px;border:1px solid #ddd;border-radius:6px;min-width:220px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="edit_user" value="1">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>" required style="margin-top:6px;">
                                <select name="role" class="form-control" style="margin-top:6px;">
                                    <option value="student" <?php echo $u['role'] === 'student' ? 'selected' : ''; ?>>طالب</option>
                                    <option value="doctor" <?php echo $u['role'] === 'doctor' ? 'selected' : ''; ?>>دكتور</option>
                                </select>
                                <input type="text" name="student_id" class="form-control" value="<?php echo htmlspecialchars($u['student_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="رقم الطالب" style="margin-top:6px;">
                                <select name="department_id" class="form-control" style="margin-top:6px;" required>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo (int) $dept['id']; ?>" <?php echo (int) ($u['department_id'] ?? 0) === (int) $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="password" name="password" class="form-control" placeholder="كلمة مرور جديدة (اختياري)" style="margin-top:6px;">
                                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:6px;">حفظ</button>
                            </form>
                        </details>
                        <form method="post" style="display:inline;" onsubmit="return confirm('حذف المستخدم؟');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="delete_user" value="1">
                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($users) === 0): ?>
                <tr><td colspan="7" class="text-muted">لا يوجد مستخدمون.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

        </main>
    </div>
    </div>
</div>
</body>
</html>
