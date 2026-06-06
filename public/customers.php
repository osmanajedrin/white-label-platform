<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$user = require_login();
$pdo  = db();
$tid  = $user['tenant_id'];

// ---------- handle POST (add / update / delete) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $cid  = $_POST['customer_id'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id=:c AND tenant_id=:t");
        $stmt->execute([':c' => $cid, ':t' => $tid]);
        flash($stmt->rowCount() ? 'Customer deleted.' : 'Customer not found.', $stmt->rowCount() ? 'success' : 'error');
        header('Location: /customers.php');
        exit;
    }

    if ($action === 'add' || $action === 'update') {
        $cid      = $_POST['customer_id'] ?? '';
        $email    = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

        if ($errors) {
            flash(implode(' ', $errors), 'error');
            header('Location: /customers.php' . ($action === 'update' ? '?edit=' . urlencode($cid) : ''));
            exit;
        }

        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO customers (tenant_id, email, full_name) VALUES (:t, :e, :n)");
                $stmt->execute([':t' => $tid, ':e' => $email, ':n' => $fullName !== '' ? $fullName : null]);
                flash('Customer "' . $email . '" added.');
            } else {
                $stmt = $pdo->prepare("UPDATE customers SET email=:e, full_name=:n WHERE id=:c AND tenant_id=:t");
                $stmt->execute([':e' => $email, ':n' => $fullName !== '' ? $fullName : null, ':c' => $cid, ':t' => $tid]);
                flash('Customer updated.');
            }
        } catch (PDOException $ex) {
            flash($ex->getCode() === '23505' ? 'That email already exists for this tenant.' : 'Could not save customer.', 'error');
        }
        header('Location: /customers.php');
        exit;
    }
}

// ---------- editing? load the record ----------
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM customers WHERE id=:c AND tenant_id=:t");
    $stmt->execute([':c' => $_GET['edit'], ':t' => $tid]);
    $editing = $stmt->fetch() ?: null;
}

// ---------- list ----------
$stmt = $pdo->prepare(
    "SELECT id, full_name, email, created_at FROM customers WHERE tenant_id=:t ORDER BY created_at DESC"
);
$stmt->execute([':t' => $tid]);
$customers = $stmt->fetchAll();

render_header('Customers', $user);
?>
<h1>Customers</h1>
<p class="muted">End customers of <strong><?= e($user['tenant_name']) ?></strong>.</p>

<div class="card">
    <h2><?= $editing ? 'Edit customer' : 'Add a customer' ?></h2>
    <form method="post" action="/customers.php" class="form-inline">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="customer_id" value="<?= e($editing['id']) ?>">
        <?php endif; ?>
        <div>
            <label for="full_name">Name</label>
            <input id="full_name" name="full_name" placeholder="John Carter" value="<?= e($editing['full_name'] ?? '') ?>">
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="john@example.com" value="<?= e($editing['email'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn-inline"><?= $editing ? 'Save changes' : 'Add customer' ?></button>
        <?php if ($editing): ?>
            <a href="/customers.php" class="btn-link" style="padding:9px 4px;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2><?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?></h2>
    <?php if (!$customers): ?>
        <p class="muted">No customers yet — add one above.</p>
    <?php else: ?>
        <table>
            <tr><th>Name</th><th>Email</th><th>Joined</th><th></th></tr>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= e($c['full_name'] ?? '—') ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e(substr((string)$c['created_at'], 0, 10)) ?></td>
                    <td class="row-actions">
                        <a href="/customers.php?edit=<?= e($c['id']) ?>">Edit</a>
                        &nbsp;·&nbsp;
                        <form method="post" action="/customers.php" onsubmit="return confirm('Delete this customer?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="customer_id" value="<?= e($c['id']) ?>">
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
