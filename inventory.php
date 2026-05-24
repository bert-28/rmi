<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['staff', 'admin']);
$db = get_db();

// Handle Add/Edit product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $data = [
            $_POST['product_name'],
            $_POST['category'],
            $_POST['description'],
            $_POST['price'],
            $_POST['stock_qty'],
            $_POST['image_url'] ?? null,
        ];
        if ($action === 'add') {
            $db->prepare("INSERT INTO products (product_name,category,description,price,stock_qty,image_url) VALUES (?,?,?,?,?,?)")->execute($data);
            flash('success', 'Product added successfully.');
        } else {
            $data[] = $_POST['product_id'];
            $db->prepare("UPDATE products SET product_name=?,category=?,description=?,price=?,stock_qty=?,image_url=? WHERE product_id=?")->execute($data);
            flash('success', 'Product updated.');
        }
    } elseif ($action === 'delete' && $role === 'admin') {
        $db->prepare("DELETE FROM products WHERE product_id=?")->execute([$_POST['product_id']]);
        flash('success', 'Product deleted.');
    }
    header('Location: inventory.php');
    exit;
}

$categories = ['Guitar','Bass','Amplifier','Strings','Pedal','Accessory','Other'];
$cat_filter = $_GET['category'] ?? '';
$search = $_GET['q'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($cat_filter) { $where .= ' AND category = ?'; $params[] = $cat_filter; }
if ($search) { $where .= ' AND (product_name LIKE ? OR description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("SELECT * FROM products $where ORDER BY category, product_name");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Edit prefill
$edit_product = null;
if (isset($_GET['edit'])) {
    $ep = $db->prepare("SELECT * FROM products WHERE product_id=?");
    $ep->execute([$_GET['edit']]);
    $edit_product = $ep->fetch();
}

$page_title = 'Inventory';
require_once __DIR__ . '/includes/header.php';

$cat_icons = ['Guitar'=>'🎸','Bass'=>'🎵','Amplifier'=>'🔊','Strings'=>'〰️','Pedal'=>'🎛️','Accessory'=>'🎼','Other'=>'📦'];
?>

<?php $suc = flash('success'); if ($suc): ?>
<div class="container" style="padding-bottom:0"><div class="alert alert-success"><?= h($suc) ?></div></div>
<?php endif; ?>

<div class="container">
  <div class="page-header">
    <div>
      <h1>Inventory</h1>
      <p class="page-subtitle"><?= count($products) ?> product(s) found</p>
    </div>
    <button onclick="openModal('addModal')" class="btn btn-primary">+ Add Product</button>
  </div>

  <!-- Filters -->
  <div class="filters-bar">
    <form method="GET" style="display:contents">
      <div class="search-input">
        <input type="text" name="q" class="form-control" placeholder="Search products…" value="<?= h($search) ?>" style="width:220px">
      </div>
      <select name="category" class="form-control" onchange="this.form.submit()" style="width:160px">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c ?>" <?= $cat_filter === $c ? 'selected' : '' ?>><?= $c ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary">Filter</button>
      <?php if ($cat_filter || $search): ?>
        <a href="<?= BASE_URL ?>/inventory.php" class="btn btn-ghost">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Table -->
  <div class="card">
    <div style="overflow-x:auto">
      <table class="table" data-searchable>
        <thead>
          <tr>
            <th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="icon">📦</div><p>No products found.</p></div></td></tr>
          <?php else: foreach ($products as $p): ?>
            <tr>
              <td>
                <div style="font-weight:500"><?= h($p['product_name']) ?></div>
                <?php if ($p['description']): ?>
                  <div style="font-size:.78rem;color:var(--text-2)"><?= h(mb_strimwidth($p['description'], 0, 60, '…')) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span style="font-size:.85rem"><?= $cat_icons[$p['category']] ?? '📦' ?> <?= h($p['category']) ?></span>
              </td>
              <td style="font-family:var(--font-display);color:var(--gold)"><?= format_money($p['price']) ?></td>
              <td>
                <span class="badge <?= $p['stock_qty'] == 0 ? 'badge-danger' : ($p['stock_qty'] <= 3 ? 'badge-warning' : 'badge-success') ?>">
                  <?= $p['stock_qty'] ?> in stock
                </span>
              </td>
              <td style="text-align:right">
                <a href="?edit=<?= $p['product_id'] ?>" class="btn btn-sm btn-ghost">Edit</a>
                <?php if ($role === 'admin'): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete '<?= h($p['product_name']) ?>'?">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Add Product</h3>
      <button class="modal-close" data-close-modal>✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" name="product_name" class="form-control" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Category *</label>
            <select name="category" class="form-control" required>
              <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Price (₱) *</label>
            <input type="number" name="price" class="form-control" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Stock Quantity *</label>
          <input type="number" name="stock_qty" class="form-control" min="0" value="0" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Image URL</label>
          <input type="url" name="image_url" class="form-control" placeholder="https://…">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-primary">Add Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal (auto-open if ?edit= is set) -->
<?php if ($edit_product): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Product</h3>
      <a href="<?= BASE_URL ?>/inventory.php" class="modal-close">✕</a>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="product_id" value="<?= $edit_product['product_id'] ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" name="product_name" class="form-control" required value="<?= h($edit_product['product_name']) ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Category *</label>
            <select name="category" class="form-control" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c ?>" <?= $edit_product['category'] === $c ? 'selected' : '' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Price (₱) *</label>
            <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?= $edit_product['price'] ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Stock Quantity *</label>
          <input type="number" name="stock_qty" class="form-control" min="0" required value="<?= $edit_product['stock_qty'] ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"><?= h($edit_product['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Image URL</label>
          <input type="url" name="image_url" class="form-control" value="<?= h($edit_product['image_url'] ?? '') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?= BASE_URL ?>/inventory.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>