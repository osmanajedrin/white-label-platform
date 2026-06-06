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

    if ($action === 'add') {
        $email    = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (strlen($password) < 8)                      $errors[] = 'Password must be at least 8 characters.';

        if ($errors) {
            flash(implode(' ', $errors), 'error');
            header('Location: /users.php');
            exit;
        }

        try {
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
        } catch (PDOException $ex) {
            $pdo->rollBack();
            flash($ex->getCode() === '23505' ? 'That email already exists for this tenant.' : 'Could not add user.', 'error');
        }
        header('Location: /users.php');
        exit;
    }
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
    <h2>Add a user</h2>
    <form method="post" action="/users.php" class="form-inline">
        <input type="hidden" name="action" value="add">
        <div>
            <label for="full_name">Name</label>
            <input id="full_name" name="full_name" placeholder="Jane Doe">
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="jane@acme.test" required>
        </div>
        <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="min 8 chars" required>
        </div>
        <button type="submit" class="btn-inline">Add user</button>
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
                    <?php if ($u['id'] !== $user['id']): ?>
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
