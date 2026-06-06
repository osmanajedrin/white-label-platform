<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';

// Leave the impersonated tenant but stay logged in as master.
require_admin();
exit_tenant();
header('Location: /admin/index.php');
exit;
