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
        $pid  = $_POST['patient_id'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM patients WHERE id=:p AND tenant_id=:t");
        $stmt->execute([':p' => $pid, ':t' => $tid]);
        flash($stmt->rowCount() ? 'Patient deleted.' : 'Patient not found.', $stmt->rowCount() ? 'success' : 'error');
        header('Location: /patients.php');
        exit;
    }

    if ($action === 'add' || $action === 'update') {
        $pid      = $_POST['patient_id'] ?? '';
        $email    = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

        if ($errors) {
            flash(implode(' ', $errors), 'error');
            header('Location: /patients.php' . ($action === 'update' ? '?edit=' . urlencode($pid) : ''));
            exit;
        }

        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO patients (tenant_id, email, full_name) VALUES (:t, :e, :n)");
                $stmt->execute([':t' => $tid, ':e' => $email, ':n' => $fullName !== '' ? $fullName : null]);
                flash('Patient "' . $email . '" added.');
            } else {
                $stmt = $pdo->prepare("UPDATE patients SET email=:e, full_name=:n WHERE id=:p AND tenant_id=:t");
                $stmt->execute([':e' => $email, ':n' => $fullName !== '' ? $fullName : null, ':p' => $pid, ':t' => $tid]);
                flash('Patient updated.');
            }
        } catch (PDOException $ex) {
            flash($ex->getCode() === '23505' ? 'That email already exists for this tenant.' : 'Could not save patient.', 'error');
        }
        header('Location: /patients.php');
        exit;
    }
}

// ---------- editing? load the record ----------
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM patients WHERE id=:p AND tenant_id=:t");
    $stmt->execute([':p' => $_GET['edit'], ':t' => $tid]);
    $editing = $stmt->fetch() ?: null;
}

// ---------- list ----------
$stmt = $pdo->prepare(
    "SELECT id, full_name, email, created_at FROM patients WHERE tenant_id=:t ORDER BY created_at DESC"
);
$stmt->execute([':t' => $tid]);
$patients = $stmt->fetchAll();

render_header('Patients', $user);
?>
<h1>Patients</h1>
<p class="muted">Patients of <strong><?= e($user['tenant_name']) ?></strong>.</p>

<div class="card">
    <h2><?= $editing ? 'Edit patient' : 'Add a patient' ?></h2>
    <form method="post" action="/patients.php" class="form-inline">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="patient_id" value="<?= e($editing['id']) ?>">
        <?php endif; ?>
        <div>
            <label for="full_name">Name</label>
            <input id="full_name" name="full_name" placeholder="John Carter" value="<?= e($editing['full_name'] ?? '') ?>">
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="john@example.com" value="<?= e($editing['email'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn-inline"><?= $editing ? 'Save changes' : 'Add patient' ?></button>
        <?php if ($editing): ?>
            <a href="/patients.php" class="btn-link" style="padding:9px 4px;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2><?= count($patients) ?> patient<?= count($patients) === 1 ? '' : 's' ?></h2>
    <?php if (!$patients): ?>
        <p class="muted">No patients yet — add one above.</p>
    <?php else: ?>
        <table>
            <tr><th>Name</th><th>Email</th><th>Joined</th><th></th></tr>
            <?php foreach ($patients as $p): ?>
                <tr>
                    <td><?= e($p['full_name'] ?? '—') ?></td>
                    <td><?= e($p['email']) ?></td>
                    <td><?= e(substr((string)$p['created_at'], 0, 10)) ?></td>
                    <td class="row-actions">
                        <a href="/patients.php?edit=<?= e($p['id']) ?>">Edit</a>
                        &nbsp;·&nbsp;
                        <form method="post" action="/patients.php" onsubmit="return confirm('Delete this patient?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="patient_id" value="<?= e($p['id']) ?>">
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
