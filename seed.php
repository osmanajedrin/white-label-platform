<?php
declare(strict_types=1);

/**
 * Seed the database with demo data so the frontend has something to show.
 * Safe to re-run: it wipes the demo data first.
 *
 *   php seed.php
 */

require __DIR__ . '/src/db.php';

$pdo = db();

echo "Wiping existing data...\n";
$pdo->exec("TRUNCATE admins, tenants, products, product_categories RESTART IDENTITY CASCADE");

echo "Inserting demo data...\n";

// ---- master admin (platform owner, no tenant) ----
$pdo->prepare(
    "INSERT INTO admins (email, password_hash, full_name) VALUES ('master@platform.test', :h, 'Platform Owner')"
)->execute([':h' => password_hash('master123', PASSWORD_DEFAULT)]);

// ---- tenant ----
$tenantId = $pdo->query(
    "INSERT INTO tenants (name, slug) VALUES ('Acme Clinic', 'acme') RETURNING id"
)->fetchColumn();

// ---- admin user ----
$hash = password_hash('password123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    "INSERT INTO users (tenant_id, email, password_hash) VALUES (:t, 'admin@acme.test', :h) RETURNING id"
);
$stmt->execute([':t' => $tenantId, ':h' => $hash]);
$userId = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "INSERT INTO user_profiles (user_id, full_name, phone) VALUES (:u, 'Acme Admin', '+1 555 0100')"
);
$stmt->execute([':u' => $userId]);

// ---- categories ----
$catLife = $pdo->query("INSERT INTO product_categories (name, slug) VALUES ('Life', 'life') RETURNING id")->fetchColumn();
$catAuto = $pdo->query("INSERT INTO product_categories (name, slug) VALUES ('Auto', 'auto') RETURNING id")->fetchColumn();

// ---- products (global catalog) ----
$products = [
    ['Term Life 20',    $catLife, 'active',  49.00],
    ['Whole Life Plus', $catLife, 'active',  89.00],
    ['Auto Basic',      $catAuto, 'active',  29.00],
    ['Auto Premium',    $catAuto, 'draft',   59.00],
];

$insProduct = $pdo->prepare("INSERT INTO products (category_id, name, status) VALUES (:c, :n, :s) RETURNING id");
$insPlan    = $pdo->prepare("INSERT INTO product_plans (product_id, name, billing_period) VALUES (:p, 'Standard', 'monthly') RETURNING id");
$insCost    = $pdo->prepare("INSERT INTO product_costs (plan_id, amount, currency) VALUES (:pl, :a, 'USD')");
$insTenantP = $pdo->prepare("INSERT INTO tenant_products (tenant_id, product_id, enabled) VALUES (:t, :p, true)");

foreach ($products as [$name, $cat, $status, $price]) {
    $insProduct->execute([':c' => $cat, ':n' => $name, ':s' => $status]);
    $pid = $insProduct->fetchColumn();

    $insPlan->execute([':p' => $pid]);
    $planId = $insPlan->fetchColumn();
    $insCost->execute([':pl' => $planId, ':a' => $price]);

    // enable the active ones for this tenant
    if ($status === 'active') {
        $insTenantP->execute([':t' => $tenantId, ':p' => $pid]);
    }
}

// ---- patients ----
$insPatient = $pdo->prepare(
    "INSERT INTO patients (tenant_id, email, full_name, created_at) VALUES (:t, :e, :n, now() - (:d || ' days')::interval)"
);
$patients = [
    ['john@example.com',  'John Carter', 1],
    ['mary@example.com',  'Mary Lopez',  3],
    ['ahmed@example.com', 'Ahmed Khan',  6],
];
foreach ($patients as [$email, $nm, $daysAgo]) {
    $insPatient->execute([':t' => $tenantId, ':e' => $email, ':n' => $nm, ':d' => $daysAgo]);
}

echo "Done.\n\n";
echo "Master admin (leave Tenant blank):\n";
echo "  Email:    master@platform.test\n";
echo "  Password: master123\n\n";
echo "Tenant admin:\n";
echo "  Tenant:   acme\n";
echo "  Email:    admin@acme.test\n";
echo "  Password: password123\n";
