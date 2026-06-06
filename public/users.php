<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$user = require_login();
$pdo  = db();
$tid  = $user['tenant_id'];

// ---------- handle POST (add / delete) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $uid = $_POST['user_id'] ?? '';
        if ($uid === $user['id']) {
            flash("You can't delete your own account.", 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=:u AND tenant_id=:t");
            $stmt->execute([':u' => $uid, ':t' => $tid]);
            flash($stmt->rowCount() ? 'User deleted.' : 'User not found.', $stmt->rowCount() ? 'success' : 'error');
        }
        header('Location: /users.php');
        exit;
    }

    if ($action === 'add' || $action === 'update') {
        $uidPost  = $_POST['user_id'] ?? '';
        $email    = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        // password required on add; optional on update (blank = keep current)
        if ($action === 'add' && strlen($password) < 8)              $errors[] = 'Password must be at least 8 characters.';
        if ($action === 'update' && $password !== '' && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

        if ($errors) {
            flash(implode(' ', $errors), 'error');
            header('Location: /users.php' . ($action === 'update' ? '?edit=' . urlencode($uidPost) : ''));
            exit;
        }

        try {
            if ($action === 'add') {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    "INSERT INTO users (tenant_id, email, password_hash) VALUES (:t, :e, :h) RETURNING id"
                );
                $stmt->execute([':t' => $tid, ':e' => $email, ':h' => password_hash($password, PASSWORD_DEFAULT)]);
                $uid = $stmt->fetchColumn();

                $pdo->prepare("INSERT INTO user_profiles (user_id, full_name) VALUES (:u, :f)")
                    ->execute([':u' => $uid, ':f' => $fullName !== '' ? $fullName : null]);
                $pdo->commit();
                flash('User "' . $email . '" added.');
            } else {
                $pdo->beginTransaction();
                if ($password !== '') {
                    $pdo->prepare("UPDATE users SET email=:e, password_hash=:h WHERE id=:u AND tenant_id=:t")
                        ->execute([':e' => $email, ':h' => password_hash($password, PASSWORD_DEFAULT), ':u' => $uidPost, ':t' => $tid]);
                } else {
                    $pdo->prepare("UPDATE users SET email=:e WHERE id=:u AND tenant_id=:t")
                        ->execute([':e' => $email, ':u' => $uidPost, ':t' => $tid]);
                }
                // upsert profile name
                $pdo->prepare(
                    "INSERT INTO user_profiles (user_id, full_name) VALUES (:u, :f)
                     ON CONFLICT (user_id) DO UPDATE SET full_name = EXCLUDED.full_name"
                )->execute([':u' => $uidPost, ':f' => $fullName !== '' ? $fullName : null]);
                $pdo->commit();
                flash('User updated.');
            }
        } catch (PDOException $ex) {
            $pdo->rollBack();
            flash($ex->getCode() === '23505' ? 'That email already exists for this tenant.' : 'Could not save user.', 'error');
        }
        header('Location: /users.php');
        exit;
    }
}

// ---------- editing? load the record ----------
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.email, p.full_name
         FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
         WHERE u.id = :u AND u.tenant_id = :t AND u.deleted_at IS NULL"
    );
    $stmt->execute([':u' => $_GET['edit'], ':t' => $tid]);
    $editing = $stmt->fetch() ?: null;
}

// ---------- data ----------
$stmt = $pdo->prepare(
    "SELECT u.id, u.email, u.created_at, p.full_name
     FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE u.tenant_id = :t AND u.deleted_at IS NULL
     ORDER BY u.created_at"
);
$stmt->execute([':t' => $tid]);
$users = $stmt->fetchAll();

render_header('Users', $user);
?>
<h1>Users</h1>
<p class="muted">Team members for <strong><?= e($user['tenant_name']) ?></strong>.</p>

<div class="card">
    <h2><?= $editing ? 'Edit user' : 'Add a user' ?></h2>
    <form method="post" action="/users.php" class="form-inline">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="user_id" value="<?= e($editing['id']) ?>">
        <?php endif; ?>
        <div>
            <label for="full_name">Name</label>
            <input id="full_name" name="full_name" placeholder="Jane Doe" value="<?= e($editing['full_name'] ?? '') ?>">
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="jane@acme.test" value="<?= e($editing['email'] ?? '') ?>" required>
        </div>
        <div>
            <label for="password">Password <?= $editing ? '<span class="muted">(blank = keep)</span>' : '' ?></label>
            <input id="password" name="password" type="password" placeholder="<?= $editing ? 'leave blank to keep' : 'min 8 chars' ?>" <?= $editing ? '' : 'required' ?>>
        </div>
        <button type="submit" class="btn-inline"><?= $editing ? 'Save changes' : 'Add user' ?></button>
        <?php if ($editing): ?>
            <a href="/users.php" class="btn-link" style="padding:9px 4px;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?></h2>
    <table>
        <tr><th>Name</th><th>Email</th><th>Joined</th><th></th></tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['full_name'] ?? '—') ?><?= $u['id'] === $user['id'] ? ' <span class="badge">you</span>' : '' ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e(substr((string)$u['created_at'], 0, 10)) ?></td>
                <td class="row-actions">
                    <a href="/users.php?edit=<?= e($u['id']) ?>">Edit</a>
                    <?php if ($u['id'] !== $user['id']): ?>
                        &nbsp;·&nbsp;
                        <form method="post" action="/users.php" onsubmit="return confirm('Delete this user?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                            <button type="submit" class="btn-link">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php
render_footer();
