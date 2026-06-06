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
