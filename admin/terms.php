<?php
// إدارة الترمات الدراسية
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('admin');
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_term'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $start = sanitize($_POST['start_date'] ?? '');
        $end = sanitize($_POST['end_date'] ?? '');
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        if ($name === '') {
            $flash = 'اسم الترم مطلوب';
        } else {
            if ($isCurrent) {
                $conn->query('UPDATE terms SET is_current = 0');
            }
            $ins = $conn->prepare('INSERT INTO terms (name, is_current, start_date, end_date) VALUES (?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'))');
            $ins->bind_param('siss', $name, $isCurrent, $start, $end);
            $flash = $ins->execute() ? 'تم إضافة الترم' : 'تعذر الإضافة (ربما الاسم مكرر)';
            $ins->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_current'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $id = (int) ($_POST['term_id'] ?? 0);
        $conn->query('UPDATE terms SET is_current = 0');
        $upd = $conn->prepare('UPDATE terms SET is_current = 1, is_active = 1 WHERE id = ?');
        $upd->bind_param('i', $id);
        $flash = $upd->execute() ? 'تم تعيين الترم الحالي' : 'تعذر التعيين';
        $upd->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_term'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $id = (int) ($_POST['term_id'] ?? 0);
        $upd = $conn->prepare('UPDATE terms SET is_active = IF(is_active = 1, 0, 1), is_current = IF(is_active = 1, 0, is_current) WHERE id = ?');
        $upd->bind_param('i', $id);
        $flash = $upd->execute() ? 'تم تغيير حالة الترم' : 'تعذر التغيير';
        $upd->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_term'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $id = (int) ($_POST['term_id'] ?? 0);
        $chk = $conn->prepare('SELECT COUNT(*) AS c FROM courses WHERE term_id = ?');
        $chk->bind_param('i', $id);
        $chk->execute();
        $cnt = (int) ($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();
        if ($cnt > 0) {
            $flash = 'لا يمكن حذف ترم مرتبط بمقررات';
        } else {
            $del = $conn->prepare('DELETE FROM terms WHERE id = ?');
            $del->bind_param('i', $id);
            $flash = $del->execute() ? 'تم حذف الترم' : 'تعذر الحذف';
            $del->close();
        }
    }
}

$terms = [];
$res = $conn->query(
    'SELECT t.id, t.name, t.is_current, t.is_active, t.start_date, t.end_date,
            (SELECT COUNT(*) FROM courses c WHERE c.term_id = t.id) AS courses
     FROM terms t ORDER BY t.is_current DESC, t.name DESC'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $terms[] = $row;
    }
}

$pageTitle = 'الترمات';
$activeNav = 'terms';
$role = 'admin';
$extraCss = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">إدارة الترمات</h1>
<p class="text-muted">عند بدء ترم جديد، الطلاب يبقون مسجّلين في النظام — الدكتور ينشئ مقرراً للترم الجديد ويسجّل طلاب القسم دفعة واحدة.</p>
<?php if ($flash !== ''): ?>
    <div class="alert <?php echo str_contains($flash, 'تم') ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div class="assign-form-card">
    <h2 class="h2-title">إضافة ترم</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="add_term" value="1">
        <div class="form-group">
            <label>اسم الترم</label>
            <input type="text" name="name" class="form-control" required placeholder="خريف 2026">
        </div>
        <div class="form-group">
            <label>تاريخ البداية</label>
            <input type="date" name="start_date" class="form-control">
        </div>
        <div class="form-group">
            <label>تاريخ النهاية</label>
            <input type="date" name="end_date" class="form-control">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_current" value="1"> تعيين كالترم الحالي</label>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> إضافة</button>
    </form>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البداية</th>
                <th>النهاية</th>
                <th>مقررات</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($terms as $t): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ((int) $t['is_current'] === 1): ?>
                            <span class="text-muted">(حالي)</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($t['start_date'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($t['end_date'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $t['courses']; ?></td>
                    <td><?php echo (int) $t['is_active'] === 1 ? 'نشط' : 'معطّل'; ?></td>
                    <td>
                        <?php if ((int) $t['is_current'] !== 1): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="set_current" value="1">
                                <input type="hidden" name="term_id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" class="btn btn-primary btn-sm">تعيين حالي</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="toggle_term" value="1">
                            <input type="hidden" name="term_id" value="<?php echo (int) $t['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?php echo (int) $t['is_active'] === 1 ? 'تعليق' : 'تفعيل'; ?></button>
                        </form>
                        <?php if ((int) $t['courses'] === 0): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('حذف الترم؟');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="delete_term" value="1">
                                <input type="hidden" name="term_id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($terms) === 0): ?>
                <tr><td colspan="6" class="text-muted">لا توجد ترمات.</td></tr>
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
