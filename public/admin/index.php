<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/layout.php';

$admin = require_admin();
$pdo   = db();

// ---------- actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tid    = $_POST['tenant_id'] ?? '';

    if ($action === 'enter') {
        if (enter_tenant($tid)) {
            header('Location: /dashboard.php');
        } else {
            flash('That tenant has no admin to act as.', 'error');
            header('Location: /admin/index.php');
        }
        exit;
    }

    if ($action === 'toggle') {
        $stmt = $pdo->prepare("UPDATE tenants SET is_active = NOT is_active WHERE id = :t RETURNING is_active, name");
        $stmt->execute([':t' => $tid]);
        $row = $stmt->fetch();
        if ($row) {
            flash($row['name'] . ' is now ' . ($row['is_active'] ? 'active' : 'disabled') . '.');
        }
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = :t");
        $stmt->execute([':t' => $tid]);
        flash($stmt->rowCount() ? 'Tenant deleted.' : 'Tenant not found.', $stmt->rowCount() ? 'success' : 'error');
    }
    header('Location: /admin/index.php');
    exit;
}

// ---------- tenant list with counts ----------
$tenants = $pdo->query(
    "SELECT t.id, t.name, t.slug, t.is_active, t.created_at,
            (SELECT count(*) FROM users u    WHERE u.tenant_id = t.id AND u.deleted_at IS NULL) AS admins,
            (SELECT count(*) FROM patients p WHERE p.tenant_id = t.id)                          AS patients
     FROM tenants t
     ORDER BY t.created_at DESC"
)->fetchAll();

render_admin_header('Tenants', $admin);
?>
<h1>Tenants</h1>
<p class="muted">All tenant workspaces on the platform. <a href="/admin/tenant.php">+ New tenant</a></p>

<div class="card">
    <h2><?= count($tenants) ?> tenant<?= count($tenants) === 1 ? '' : 's' ?></h2>
    <?php if (!$tenants): ?>
        <p class="muted">No tenants yet — <a href="/admin/tenant.php">create one</a>.</p>
    <?php else: ?>
        <table>
            <tr><th>Tenant</th><th>Slug</th><th>Admins</th><th>Patients</th><th>Status</th><th>Created</th><th></th></tr>
            <?php foreach ($tenants as $t): ?>
                <tr>
                    <td><strong><?= e($t['name']) ?></strong></td>
                    <td><code><?= e($t['slug']) ?></code></td>
                    <td><?= (int) $t['admins'] ?></td>
                    <td><?= (int) $t['patients'] ?></td>
                    <td>
                        <span class="badge" style="<?= $t['is_active'] ? '' : 'background:#fee2e2;color:#b91c1c;' ?>">
                            <?= $t['is_active'] ? 'active' : 'disabled' ?>
                        </span>
                    </td>
                    <td><?= e(substr((string)$t['created_at'], 0, 10)) ?></td>
                    <td class="row-actions">
                        <?php if ($t['is_active']): ?>
                            <form method="post" action="/admin/index.php">
                                <input type="hidden" name="action" value="enter">
                                <input type="hidden" name="tenant_id" value="<?= e($t['id']) ?>">
                                <button type="submit" class="btn-link" style="color:#15803d;font-weight:600;">Enter</button>
                            </form>
                            &nbsp;·&nbsp;
                        <?php endif; ?>
                        <a href="/admin/tenant.php?edit=<?= e($t['id']) ?>">Edit</a>
                        &nbsp;·&nbsp;
                        <form method="post" action="/admin/index.php">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="tenant_id" value="<?= e($t['id']) ?>">
                            <button type="submit" class="btn-link" style="color:#2d6cdf;"><?= $t['is_active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                        &nbsp;·&nbsp;
                        <form method="post" action="/admin/index.php" onsubmit="return confirm('Delete this tenant and ALL its data?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tenant_id" value="<?= e($t['id']) ?>">
                            <button type="submit" class="btn-link">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<?php
render_footer();
