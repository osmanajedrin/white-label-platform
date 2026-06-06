<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = db();
    $tables = $pdo->query(
        "SELECT table_name
         FROM information_schema.tables
         WHERE table_schema = 'public'
         ORDER BY table_name"
    )->fetchAll(PDO::FETCH_COLUMN);
    $connected = true;
} catch (Throwable $e) {
    $connected = false;
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>White Label Platform</title>
    <style>
        body { font-family: Segoe UI, system-ui, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 16px; color: #1f2937; }
        h1 { margin-bottom: 4px; }
        .ok { color: #15803d; } .err { color: #b91c1c; }
        ul { columns: 2; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>White Label Platform</h1>
    <p>Plain PHP + PostgreSQL starter.</p>

    <?php if ($connected): ?>
        <p class="ok">✔ Connected to the database. Found <?= count($tables) ?> tables:</p>
        <ul>
            <?php foreach ($tables as $t): ?>
                <li><code><?= htmlspecialchars($t) ?></code></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="err">✗ Could not connect to the database.</p>
        <p><code><?= htmlspecialchars($error) ?></code></p>
        <p>Check your <code>.env</code> values and that PostgreSQL is running.</p>
    <?php endif; ?>
</body>
</html>
