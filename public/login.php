<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

// already logged in? go to dashboard
if (current_user() !== null) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant   = trim($_POST['tenant'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($tenant === '' || $email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (attempt_login($tenant, $email, $password)) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = 'Invalid tenant, email, or password.';
    }
}

render_header('Sign in');
?>
<form class="login card" method="post" action="/login.php">
    <h1>Sign in</h1>
    <p class="muted">Tenant portal login.</p>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <label for="tenant">Tenant</label>
    <input id="tenant" name="tenant" placeholder="acme" value="<?= e($_POST['tenant'] ?? '') ?>" autofocus>

    <label for="email">Email</label>
    <input id="email" name="email" type="email" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" placeholder="••••••••">

    <button type="submit">Sign in</button>

    <div class="hint">
        <strong>Demo login</strong><br>
        Tenant: <code>acme</code><br>
        Email: <code>admin@acme.test</code><br>
        Password: <code>password123</code>
    </div>
</form>
<?php
render_footer();
