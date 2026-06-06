<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function render_header(string $title, ?array $user = null): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · White Label</title>
    <style>
        :root { --brand:#2d6cdf; --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --bg:#f8fafc; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:"Segoe UI",system-ui,sans-serif; color:var(--ink); background:var(--bg); }
        a { color:var(--brand); text-decoration:none; }
        .topbar { background:var(--brand); color:#fff; padding:14px 24px; display:flex; justify-content:space-between; align-items:center; }
        .topbar .brand { font-weight:700; font-size:18px; }
        .topbar .right { font-size:14px; opacity:.95; }
        .topbar .right a { color:#fff; text-decoration:underline; }
        .wrap { max-width:1000px; margin:32px auto; padding:0 20px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:22px; margin-bottom:20px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        h1 { margin:0 0 6px; } h2 { margin:0 0 14px; font-size:18px; }
        .muted { color:var(--muted); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; }
        .stat { background:#fff; border:1px solid var(--line); border-radius:12px; padding:18px; }
        .stat .n { font-size:28px; font-weight:700; color:var(--brand); }
        .stat .l { color:var(--muted); font-size:13px; margin-top:4px; }
        table { width:100%; border-collapse:collapse; }
        th,td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); font-size:14px; }
        th { color:var(--muted); font-weight:600; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; background:#eef2ff; color:var(--brand); }
        form.login { max-width:380px; margin:8vh auto; }
        label { display:block; font-size:13px; margin:12px 0 4px; color:var(--muted); }
        input { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; }
        button { margin-top:18px; width:100%; padding:11px; background:var(--brand); color:#fff; border:0; border-radius:8px; font-size:15px; cursor:pointer; }
        button:hover { background:#2559bd; }
        .error { background:#fee2e2; color:#b91c1c; padding:10px 12px; border-radius:8px; font-size:14px; margin-top:12px; }
        .hint { background:#f1f5f9; padding:12px; border-radius:8px; font-size:13px; color:var(--muted); margin-top:16px; }
    </style>
</head>
<body>
    <?php if ($user !== null): ?>
    <div class="topbar">
        <div class="brand"><?= e($user['tenant_name']) ?> <span style="opacity:.6;font-weight:400">/ White Label</span></div>
        <div class="right"><?= e($user['email']) ?> · <a href="/logout.php">Log out</a></div>
    </div>
    <?php endif; ?>
    <div class="wrap">
    <?php
}

function render_footer(): void
{
    ?>
    </div>
</body>
</html>
    <?php
}
