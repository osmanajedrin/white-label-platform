<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$user = require_login();
$pdo  = db();
$tid  = $user['tenant_id'];

// --- stat counts (all scoped to the tenant) ---
function count_for(PDO $pdo, string $sql, string $tid): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tid' => $tid]);
    return (int) $stmt->fetchColumn();
}

$stats = [
    'Users'     => count_for($pdo, "SELECT count(*) FROM users WHERE tenant_id=:tid AND deleted_at IS NULL", $tid),
    'Customers' => count_for($pdo, "SELECT count(*) FROM customers WHERE tenant_id=:tid", $tid),
    'Products'  => count_for($pdo, "SELECT count(*) FROM tenant_products WHERE tenant_id=:tid AND enabled", $tid),
    'Purchases' => count_for($pdo, "SELECT count(*) FROM customer_purchases WHERE tenant_id=:tid", $tid),
];

// --- products this tenant offers ---
$stmt = $pdo->prepare(
    "SELECT p.name, p.status, c.name AS category,
            COALESCE(tp.label_override, p.name) AS display_name,
            tp.price_override
     FROM tenant_products tp
     JOIN products p ON p.id = tp.product_id
     LEFT JOIN product_categories c ON c.id = p.category_id
     WHERE tp.tenant_id = :tid AND tp.enabled
     ORDER BY p.name"
);
$stmt->execute([':tid' => $tid]);
$products = $stmt->fetchAll();

// --- recent customers ---
$stmt = $pdo->prepare(
    "SELECT full_name, email, created_at
     FROM customers WHERE tenant_id = :tid
     ORDER BY created_at DESC LIMIT 5"
);
$stmt->execute([':tid' => $tid]);
$customers = $stmt->fetchAll();

render_header('Dashboard', $user);
?>
<h1>Dashboard</h1>
<p class="muted">Welcome back — you're managing <strong><?= e($user['tenant_name']) ?></strong>.</p>

<div class="grid" style="margin:18px 0 26px;">
    <?php foreach ($stats as $label => $n): ?>
        <div class="stat"><div class="n"><?= $n ?></div><div class="l"><?= e($label) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Products you offer</h2>
    <?php if (!$products): ?>
        <p class="muted">No products enabled for this tenant yet.</p>
    <?php else: ?>
        <table>
            <tr><th>Product</th><th>Category</th><th>Status</th><th>Price override</th></tr>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= e($p['display_name']) ?></td>
                    <td><?= e($p['category'] ?? '—') ?></td>
                    <td><span class="badge"><?= e($p['status']) ?></span></td>
                    <td><?= $p['price_override'] !== null ? '$' . e($p['price_override']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Recent customers</h2>
    <?php if (!$customers): ?>
        <p class="muted">No customers yet.</p>
    <?php else: ?>
        <table>
            <tr><th>Name</th><th>Email</th><th>Joined</th></tr>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= e($c['full_name'] ?? '—') ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e(substr((string)$c['created_at'], 0, 10)) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<?php
render_footer();
