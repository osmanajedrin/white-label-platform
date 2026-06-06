<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/layout.php';

$admin = require_admin();
$pdo   = db();

$slugRe = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])?$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $company  = trim($_POST['company'] ?? '');
        $slug     = strtolower(trim($_POST['slug'] ?? ''));
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');

        $errors = [];
        if ($company === '')                       $errors[] = 'Company name is required.';
        if (!preg_match($slugRe, $slug))           $errors[] = 'Slug must be 2–40 lowercase letters, numbers, or hyphens.';
        elseif ($slug === 'master')                $errors[] = '"master" is a reserved slug.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid admin email is required.';
        if (strlen($password) < 8)                 $errors[] = 'Password must be at least 8 characters.';

        if ($errors) {
            flash(implode(' ', $errors), 'error');
            header('Location: /admin/tenant.php');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO tenants (name, slug) VALUES (:n, :s) RETURNING id");
            $stmt->execute([':n' => $company, ':s' => $slug]);
            $tenantId = $stmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO users (tenant_id, email, password_hash) VALUES (:t, :e, :h) RETURNING id");
            $stmt->execute([':t' => $tenantId, ':e' => $email, ':h' => password_hash($password, PASSWORD_DEFAULT)]);
            $userId = $stmt->fetchColumn();

            $pdo->prepare("INSERT INTO user_profiles (user_id, full_name) VALUES (:u, :f)")
                ->execute([':u' => $userId, ':f' => $fullName !== '' ? $fullName : null]);
            $pdo->commit();
            flash('Tenant "' . $company . '" created with admin ' . $email . '.');
            header('Location: /admin/index.php');
            exit;
        } catch (PDOException $ex) {
            $pdo->rollBack();
            flash($ex->getCode() === '23505' ? 'That slug is already taken.' : 'Could not create tenant.', 'error');
            header('Location: /admin/tenant.php');
            exit;
        }
    }

    if ($action === 'update') {
        $tid     = $_POST['tenant_id'] ?? '';
        $company = trim($_POST['company'] ?? '');
        $slug    = strtolower(trim($_POST['slug'] ?? ''));
        $active  = isset($_POST['is_active']) ? 'true' : 'false';

        $errors = [];
        if ($company === '')             $errors[] = 'Company name is required.';
        if (!preg_match($slugRe, $slug)) $errors[] = 'Invalid slug.';

        if ($errors) {
            flash(implode(' ', $errors), 'error');
            header('Location: /admin/tenant.php?edit=' . urlencode($tid));
            exit;
        }

        try {
            $pdo->prepare("UPDATE tenants SET name=:n, slug=:s, is_active=:a WHERE id=:t")
                ->execute([':n' => $company, ':s' => $slug, ':a' => $active, ':t' => $tid]);
            flash('Tenant updated.');
        } catch (PDOException $ex) {
            flash($ex->getCode() === '23505' ? 'That slug is already taken.' : 'Could not update tenant.', 'error');
            header('Location: /admin/tenant.php?edit=' . urlencode($tid));
            exit;
        }
        header('Location: /admin/index.php');
        exit;
    }
}

// editing?
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT id, name, slug, is_active FROM tenants WHERE id = :t");
    $stmt->execute([':t' => $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

render_admin_header($editing ? 'Edit tenant' : 'New tenant', $admin);
?>
<h1><?= $editing ? 'Edit tenant' : 'New tenant' ?></h1>

<?php if ($editing): ?>
<div class="card">
    <h2>Tenant details</h2>
    <form method="post" action="/admin/tenant.php" class="login" style="margin:0;max-width:480px;">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="tenant_id" value="<?= e($editing['id']) ?>">
        <label for="company">Company name</label>
        <input id="company" name="company" value="<?= e($editing['name']) ?>" required>
        <label for="slug">Slug</label>
        <input id="slug" name="slug" value="<?= e($editing['slug']) ?>" required>
        <label style="display:flex;align-items:center;gap:8px;margin-top:14px;">
            <input type="checkbox" name="is_active" value="1" style="width:auto;" <?= $editing['is_active'] ? 'checked' : '' ?>>
            Active (tenant admins can log in)
        </label>
        <button type="submit">Save changes</button>
        <div class="hint" style="text-align:center;"><a href="/admin/index.php">Back to tenants</a></div>
    </form>
</div>
<?php else: ?>
<div class="card">
    <h2>Create a tenant + first admin</h2>
    <form method="post" action="/admin/tenant.php" class="login" style="margin:0;max-width:480px;">
        <input type="hidden" name="action" value="create">
        <label for="company">Company name</label>
        <input id="company" name="company" placeholder="Globex Clinic" required>
        <label for="slug">Slug <span class="muted">(used at login)</span></label>
        <input id="slug" name="slug" placeholder="globex" required>
        <label for="full_name">Admin name</label>
        <input id="full_name" name="full_name" placeholder="Jane Doe">
        <label for="email">Admin email</label>
        <input id="email" name="email" type="email" placeholder="admin@globex.test" required>
        <label for="password">Admin password <span class="muted">(min 8)</span></label>
        <input id="password" name="password" type="password" placeholder="••••••••" required>
        <button type="submit">Create tenant</button>
        <div class="hint" style="text-align:center;"><a href="/admin/index.php">Back to tenants</a></div>
    </form>
</div>
<?php endif; ?>
<?php
render_footer();
