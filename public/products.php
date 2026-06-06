<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$user = require_login();
$pdo  = db();
$tid  = $user['tenant_id'];

// ---------- handle POST (add / delete) using Post-Redirect-Get ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pid = $_POST['product_id'] ?? '';
        // only allow deleting a product this tenant actually offers
        $own = $pdo->prepare("SELECT 1 FROM tenant_products WHERE tenant_id=:t AND product_id=:p");
        $own->execute([':t' => $tid, ':p' => $pid]);
        if ($own->fetchColumn()) {
            $pdo->prepare("DELETE FROM products WHERE id=:p")->execute([':p' => $pid]);
            flash('Product deleted.');
        } else {
            flash('Product not found.', 'error');
        }
        header('Location: /products.php');
        exit;
    }

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $category = $_POST['category_id'] ?? '';
        $status   = ($_POST['status'] ?? 'active') === 'draft' ? 'draft' : 'active';
        $priceRaw = trim($_POST['price'] ?? '');

        $errors = [];
        if ($name === '')                                   $errors[] = 'Product name is required.';
        if ($priceRaw === '' || !is_numeric($priceRaw))     $errors[] = 'Price must be a number.';
        elseif ((float) $priceRaw < 0)                      $errors[] = 'Price cannot be negative.';

        if ($errors) {
            flash(implode(' ', $errors), 'error');
            header('Location: /products.php');
            exit;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO products (category_id, name, status) VALUES (:c, :n, :s) RETURNING id");
        $stmt->execute([':c' => $category !== '' ? $category : null, ':n' => $name, ':s' => $status]);
        $pid = $stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO product_plans (product_id, name, billing_period) VALUES (:p, 'Standard', 'monthly') RETURNING id");
        $stmt->execute([':p' => $pid]);
        $planId = $stmt->fetchColumn();

        $pdo->prepare("INSERT INTO product_costs (plan_id, amount, currency) VALUES (:pl, :a, 'USD')")
            ->execute([':pl' => $planId, ':a' => (float) $priceRaw]);

        $pdo->prepare("INSERT INTO tenant_products (tenant_id, product_id, enabled) VALUES (:t, :p, true)")
            ->execute([':t' => $tid, ':p' => $pid]);
        $pdo->commit();

        flash('Product "' . $name . '" added.');
        header('Location: /products.php');
        exit;
    }
}

// ---------- data for the page ----------
$categories = $pdo->query("SELECT id, name FROM product_categories ORDER BY name")->fetchAll();

$stmt = $pdo->prepare(
    "SELECT p.id, p.name, p.status, c.name AS category, pc.amount, pc.currency
     FROM tenant_products tp
     JOIN products p ON p.id = tp.product_id
     LEFT JOIN product_categories c ON c.id = p.category_id
     LEFT JOIN product_plans pl ON pl.product_id = p.id
     LEFT JOIN product_costs pc ON pc.plan_id = pl.id
     WHERE tp.tenant_id = :t
     ORDER BY p.name"
);
$stmt->execute([':t' => $tid]);
$products = $stmt->fetchAll();

render_header('Products', $user);
?>
<h1>Products</h1>
<p class="muted">Products offered by <strong><?= e($user['tenant_name']) ?></strong>.</p>

<div class="card">
    <h2>Add a product</h2>
    <form method="post" action="/products.php" class="form-inline">
        <input type="hidden" name="action" value="add">
        <div>
            <label for="name">Name</label>
            <input id="name" name="name" placeholder="Travel Insurance" required>
        </div>
        <div>
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
                <option value="">— none —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active">active</option>
                <option value="draft">draft</option>
            </select>
        </div>
        <div>
            <label for="price">Price (USD/mo)</label>
            <input id="price" name="price" type="number" step="0.01" min="0" placeholder="39.00" required>
        </div>
        <button type="submit" class="btn-inline">Add product</button>
    </form>
</div>

<div class="card">
    <h2><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?></h2>
    <?php if (!$products): ?>
        <p class="muted">No products yet — add one above.</p>
    <?php else: ?>
        <table>
            <tr><th>Product</th><th>Category</th><th>Status</th><th>Price</th><th></th></tr>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['category'] ?? '—') ?></td>
                    <td><span class="badge"><?= e($p['status']) ?></span></td>
                    <td><?= $p['amount'] !== null ? '$' . e($p['amount']) : '—' ?></td>
                    <td class="row-actions">
                        <form method="post" action="/products.php" onsubmit="return confirm('Delete this product?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                            <button type="submit" class="btn-link">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<?php
render_footer();
