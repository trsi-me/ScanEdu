<?php
// إدارة الأقسام
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('admin');
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        if ($name === '') {
            $flash = 'اسم القسم مطلوب';
        } else {
            $ins = $conn->prepare('INSERT INTO departments (name, description) VALUES (?, NULLIF(?, \'\'))');
            $ins->bind_param('ss', $name, $desc);
            $flash = $ins->execute() ? 'تم إضافة القسم' : 'تعذر الإضافة (ربما الاسم مكرر)';
            $ins->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_department'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $id = (int) ($_POST['department_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        if ($id <= 0 || $name === '') {
            $flash = 'بيانات غير صالحة';
        } else {
            $upd = $conn->prepare('UPDATE departments SET name = ?, description = NULLIF(?, \'\') WHERE id = ?');
            $upd->bind_param('ssi', $name, $desc, $id);
            $flash = $upd->execute() ? 'تم تحديث القسم' : 'تعذر التحديث';
            $upd->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_department'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $id = (int) ($_POST['department_id'] ?? 0);
        $upd = $conn->prepare('UPDATE departments SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
        $upd->bind_param('i', $id);
        $flash = $upd->execute() ? 'تم تغيير حالة القسم' : 'تعذر التغيير';
        $upd->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_department'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $id = (int) ($_POST['department_id'] ?? 0);
        $chk = $conn->prepare('SELECT COUNT(*) AS c FROM users WHERE department_id = ?');
        $chk->bind_param('i', $id);
        $chk->execute();
        $cnt = (int) ($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();
        if ($cnt > 0) {
            $flash = 'لا يمكن حذف قسم مرتبط بمستخدمين — علّقه بدلاً من ذلك';
        } else {
            $del = $conn->prepare('DELETE FROM departments WHERE id = ?');
            $del->bind_param('i', $id);
            $flash = $del->execute() ? 'تم حذف القسم' : 'تعذر الحذف';
            $del->close();
        }
    }
}

$departments = [];
$res = $conn->query(
    'SELECT d.id, d.name, d.description, d.is_active,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = \'doctor\') AS doctors,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = \'student\') AS students
     FROM departments d ORDER BY d.name ASC'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$pageTitle = 'الأقسام';
$activeNav = 'departments';
$role = 'admin';
$extraCss = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">إدارة الأقسام</h1>
<?php if ($flash !== ''): ?>
    <div class="alert <?php echo str_contains($flash, 'تم') ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div class="assign-form-card">
    <h2 class="h2-title">إضافة قسم</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="add_department" value="1">
        <div class="form-group">
            <label>اسم القسم</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>الوصف (اختياري)</label>
            <input type="text" name="description" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> إضافة</button>
    </form>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الوصف</th>
                <th>دكاترة</th>
                <th>طلاب</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($departments as $d): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($d['description'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $d['doctors']; ?></td>
                    <td><?php echo (int) $d['students']; ?></td>
                    <td><?php echo (int) $d['is_active'] === 1 ? 'نشط' : 'معطّل'; ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="toggle_department" value="1">
                            <input type="hidden" name="department_id" value="<?php echo (int) $d['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?php echo (int) $d['is_active'] === 1 ? 'تعليق' : 'تفعيل'; ?></button>
                        </form>
                        <details style="display:inline-block;margin-right:6px;">
                            <summary class="btn btn-secondary btn-sm">تعديل</summary>
                            <form method="post" style="margin-top:8px;padding:8px;border:1px solid #ddd;border-radius:6px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="edit_department" value="1">
                                <input type="hidden" name="department_id" value="<?php echo (int) $d['id']; ?>">
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($d['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="margin-top:6px;">
                                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:6px;">حفظ</button>
                            </form>
                        </details>
                        <?php if ((int) $d['doctors'] === 0 && (int) $d['students'] === 0): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('حذف القسم؟');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="delete_department" value="1">
                                <input type="hidden" name="department_id" value="<?php echo (int) $d['id']; ?>">
                                <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($departments) === 0): ?>
                <tr><td colspan="6" class="text-muted">لا توجد أقسام.</td></tr>
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
