<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Attempt login for a tenant (by slug) + email + password.
 * Returns true on success and stores the user in the session.
 */
function attempt_login(string $tenantSlug, string $email, string $password): bool
{
    $sql = "SELECT u.id, u.password_hash, u.tenant_id, t.name AS tenant_name, t.slug AS tenant_slug
            FROM users u
            JOIN tenants t ON t.id = u.tenant_id
            WHERE t.slug = :slug AND u.email = :email AND u.deleted_at IS NULL
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([':slug' => $tenantSlug, ':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['tenant_id']   = $user['tenant_id'];
    $_SESSION['tenant_name'] = $user['tenant_name'];
    $_SESSION['tenant_slug'] = $user['tenant_slug'];
    $_SESSION['email']       = $email;
    return true;
}

/**
 * Register a new tenant plus its first admin user, then log them in.
 * Returns [true, null] on success, or [false, 'error message'] on failure.
 */
function register_tenant(string $company, string $slug, string $email, string $password, string $fullName): array
{
    $slug = strtolower(trim($slug));

    if ($company === '' || $slug === '' || $email === '' || $password === '') {
        return [false, 'Please fill in all required fields.'];
    }
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])?$/', $slug)) {
        return [false, 'Tenant slug must be 2–40 lowercase letters, numbers, or hyphens.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Please enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return [false, 'Password must be at least 8 characters.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO tenants (name, slug) VALUES (:n, :s) RETURNING id");
        $stmt->execute([':n' => $company, ':s' => $slug]);
        $tenantId = $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "INSERT INTO users (tenant_id, email, password_hash) VALUES (:t, :e, :h) RETURNING id"
        );
        $stmt->execute([':t' => $tenantId, ':e' => $email, ':h' => password_hash($password, PASSWORD_DEFAULT)]);
        $userId = $stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO user_profiles (user_id, full_name) VALUES (:u, :f)");
        $stmt->execute([':u' => $userId, ':f' => $fullName !== '' ? $fullName : null]);

        $pdo->commit();
    } catch (PDOException $ex) {
        $pdo->rollBack();
        // 23505 = unique_violation
        if ($ex->getCode() === '23505') {
            return [false, 'That tenant slug is already taken. Pick another.'];
        }
        return [false, 'Could not create the account. Please try again.'];
    }

    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id']     = $userId;
    $_SESSION['tenant_id']   = $tenantId;
    $_SESSION['tenant_name'] = $company;
    $_SESSION['tenant_slug'] = $slug;
    $_SESSION['email']       = $email;
    return [true, null];
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'          => $_SESSION['user_id'],
        'email'       => $_SESSION['email'] ?? '',
        'tenant_id'   => $_SESSION['tenant_id'] ?? '',
        'tenant_name' => $_SESSION['tenant_name'] ?? '',
        'tenant_slug' => $_SESSION['tenant_slug'] ?? '',
    ];
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

/** Escape helper for output. */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Set a one-time flash message (shown on the next page load). */
function flash(string $message, string $type = 'success'): void
{
    start_session();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/** Read and clear the flash message, if any. */
function take_flash(): ?array
{
    start_session();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
