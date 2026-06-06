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
    [$ok, $error] = register_tenant(
        trim($_POST['company'] ?? ''),
        trim($_POST['slug'] ?? ''),
        trim($_POST['email'] ?? ''),
        $_POST['password'] ?? '',
        trim($_POST['full_name'] ?? '')
    );
    if ($ok) {
        header('Location: /dashboard.php');
        exit;
    }
}

render_header('Create account');
?>
<form class="login card" method="post" action="/register.php">
    <h1>Create your account</h1>
    <p class="muted">Register a new tenant workspace.</p>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <label for="company">Company name</label>
    <input id="company" name="company" placeholder="Acme Insurance" value="<?= e($_POST['company'] ?? '') ?>" autofocus>

    <label for="slug">Tenant slug <span class="muted">(used to sign in)</span></label>
    <input id="slug" name="slug" placeholder="acme" value="<?= e($_POST['slug'] ?? '') ?>">

    <label for="full_name">Your name</label>
    <input id="full_name" name="full_name" placeholder="Jane Doe" value="<?= e($_POST['full_name'] ?? '') ?>">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">

    <label for="password">Password <span class="muted">(min 8 characters)</span></label>
    <input id="password" name="password" type="password" placeholder="••••••••">

    <button type="submit">Create account</button>

    <div class="hint">
        Already have an account? <a href="/login.php">Sign in</a>
    </div>
</form>
<?php
render_footer();
