<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

// Front door: send to dashboard if logged in, otherwise to login.
header('Location: ' . (current_user() !== null ? '/dashboard.php' : '/login.php'));
exit;
