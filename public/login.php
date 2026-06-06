<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

// already logged in? route to the right place
if (current_admin() !== null) {
    header('Location: /admin/index.php');
    exit;
}
if (current_user() !== null) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant   = trim($_POST['tenant'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } elseif ($tenant === '' || strtolower($tenant) === 'master') {
        // Tenant "master" (or blank) → treat as a master admin login.
        if (attempt_master_login($email, $password)) {
            header('Location: /admin/index.php');
            exit;
        }
        $error = 'Invalid email or password. (Use tenant "master" for master admins.)';
    } else {
        // Tenant given → tenant admin login.
        if (attempt_login($tenant, $email, $password)) {
            header('Location: /dashboard.php');
            exit;
        }
        $error = 'Invalid tenant, email, or password (or the tenant is disabled).';
    }
}

render_header('Sign in');
?>
<form class="login card" method="post" action="/login.php">
    <h1>Sign in</h1>
    <p class="muted">Tenant admins enter their tenant. Master admins use <code>master</code>.</p>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <label for="tenant">Tenant <span class="muted">(use "master" for master admin)</span></label>
    <input id="tenant" name="tenant" placeholder="acme" value="<?= e($_POST['tenant'] ?? '') ?>" autofocus>

    <label for="email">Email</label>
    <input id="email" name="email" type="email" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" placeholder="••••••••">

    <button type="submit">Sign in</button>

    <div class="hint">
        <strong>Demo logins</strong><br>
        Tenant admin → Tenant <code>acme</code>, <code>admin@acme.test</code> / <code>password123</code><br>
        Master admin → Tenant <code>master</code>, <code>master@platform.test</code> / <code>master123</code>
    </div>
    <div class="hint" style="text-align:center;">
        New tenant? <a href="/register.php">Create one</a>
    </div>
</form>
<?php
render_footer();
