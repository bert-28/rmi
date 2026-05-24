<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_role(['staff', 'admin']);
$db = get_db();

// Handle sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_json'])) {
    $cart = json_decode($_POST['cart_json'], true);
    if (!empty($cart)) {
        $customer_id    = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $notes          = $_POST['notes'] ?? '';
        $staff_id       = $_SESSION['user_id'];
        $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));

        $db->prepare(
            "INSERT INTO sales (customer_id,staff_id,total_amount,payment_method,notes) VALUES (?,?,?,?,?)"
        )->execute([$customer_id, $staff_id, $total, $payment_method, $notes]);
        $sale_id = $db->lastInsertId();

        foreach ($cart as $item) {
            $db->prepare(
                "INSERT INTO sale_items (sale_id,product_id,quantity,unit_price) VALUES (?,?,?,?)"
            )->execute([$sale_id, $item['id'], $item['qty'], $item['price']]);
            $db->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ? AND stock_qty >= ?")
               ->execute([$item['qty'], $item['id'], $item['qty']]);
        }

        $_SESSION['last_sale'] = [
            'id'      => $sale_id,
            'total'   => $total,
            'method'  => $payment_method,
            'items'   => $cart,
            'date'    => date('Y-m-d H:i:s'),
        ];

        flash('success', "Sale #$sale_id recorded — " . format_money($total));
        header('Location: pos.php');
        exit;
    }
}

// Load products
$products  = $db->query("SELECT * FROM products WHERE stock_qty > 0 ORDER BY category, product_name")->fetchAll();
$customers = $db->query("SELECT user_id, full_name, email FROM users WHERE role='customer' ORDER BY full_name")->fetchAll();
$categories= array_unique(array_column($products, 'category'));

// Recent sales (last 10)
$recent_sales = $db->query(
  "SELECT s.*, u.full_name as customer_name
   FROM sales s
   LEFT JOIN users u ON s.customer_id = u.user_id
   ORDER BY s.sale_date DESC LIMIT 10"
)->fetchAll();


$last_sale  = $_SESSION['last_sale'] ?? null;
if ($last_sale) unset($_SESSION['last_sale']);

$cat_icons = ['Guitar'=>'🎸','Bass'=>'🎵','Amplifier'=>'🔊','Strings'=>'〰️','Pedal'=>'🎛️','Accessory'=>'🎼','Other'=>'📦'];
$page_title = 'Point of Sale';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ── POS Page Styles ── */
.pos-wrap {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 0;
  height: calc(100vh - 68px);
  overflow: hidden;
}

/* Left: Products panel */
.pos-products-panel {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-right: 2px solid var(--text-0);
}

.pos-topbar {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: var(--bg-0);
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-shrink: 0;
}
.pos-topbar h2 {
  font-size: 1.6rem;
  white-space: nowrap;
  margin-right: auto;
}

.pos-search-wrap {
  position: relative;
  flex: 1;
  max-width: 280px;
}
.pos-search-wrap input {
  width: 100%;
  padding: .6rem 1rem .6rem 2.5rem;
  border: 1.5px solid var(--border);
  border-radius: 0;
  font-family: var(--font-body);
  font-size: .9rem;
  outline: none;
  transition: border-color .2s;
}
.pos-search-wrap input:focus { border-color: var(--text-0); }
.pos-search-wrap::before {
  content: '⌕';
  position: absolute;
  left: .75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 1.1rem;
  color: var(--text-2);
  pointer-events: none;
}

.cat-tabs {
  display: flex;
  gap: 0;
  overflow-x: auto;
  flex-shrink: 0;
  border-bottom: 2px solid var(--border);
  background: var(--bg-1);
  scrollbar-width: none;
}
.cat-tabs::-webkit-scrollbar { display: none; }
.cat-tab {
  padding: .65rem 1.25rem;
  font-family: var(--font-cond);
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--text-2);
  border: none;
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all .15s;
}
.cat-tab:hover { color: var(--text-0); }
.cat-tab.active { color: var(--red); border-bottom-color: var(--red); }

.pos-grid {
  flex: 1;
  overflow-y: auto;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 1px;
  background: var(--border);
  align-content: start;
  padding: 1px;
}

.pos-tile {
  background: var(--bg-0);
  padding: 1.25rem 1rem 1rem;
  cursor: pointer;
  transition: background .15s;
  text-align: center;
  border: none;
  position: relative;
  user-select: none;
}
.pos-tile:hover:not(.out-of-stock) { background: var(--bg-1); }
.pos-tile.out-of-stock { opacity: .35; cursor: not-allowed; }
.pos-tile.adding {
  animation: tileFlash .25s ease;
}
@keyframes tileFlash {
  0%   { background: var(--bg-0); }
  50%  { background: #ffe5e5; }
  100% { background: var(--bg-0); }
}

.pos-tile .tile-icon {
  font-size: 2.5rem;
  margin-bottom: .6rem;
  line-height: 1;
  display: block;
}
.pos-tile img.tile-img {
  width: 70px; height: 70px;
  object-fit: cover;
  margin: 0 auto .6rem;
}
.pos-tile .tile-name {
  font-family: var(--font-cond);
  font-size: .8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--text-0);
  margin-bottom: .2rem;
  line-height: 1.2;
}
.pos-tile .tile-price {
  font-family: var(--font-display);
  font-size: 1.1rem;
  color: var(--red);
}
.pos-tile .tile-stock {
  font-family: var(--font-cond);
  font-size: .68rem;
  letter-spacing: .05em;
  color: var(--text-2);
  margin-top: .15rem;
}
.pos-tile .in-cart-badge {
  position: absolute;
  top: 6px; right: 6px;
  background: var(--red);
  color: #fff;
  font-family: var(--font-cond);
  font-size: .65rem;
  font-weight: 700;
  width: 20px; height: 20px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  opacity: 0;
  transition: opacity .15s;
}
.pos-tile.has-item .in-cart-badge { opacity: 1; }

/* Right: Cart panel */
.pos-cart-panel {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--bg-0);
}

.cart-header {
  padding: 1rem 1.25rem;
  border-bottom: 2px solid var(--text-0);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.cart-header h2 { font-size: 1.4rem; }
.cart-clear-btn {
  font-family: var(--font-cond);
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--text-2);
  background: none;
  border: none;
  cursor: pointer;
  transition: color .15s;
}
.cart-clear-btn:hover { color: var(--red); }

.cart-scroll {
  flex: 1;
  overflow-y: auto;
}
.cart-empty-msg {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--text-2);
  gap: .5rem;
  padding: 2rem;
}
.cart-empty-msg span { font-size: 3rem; }
.cart-empty-msg p {
  font-family: var(--font-cond);
  font-size: .85rem;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--text-2);
}

.cart-row {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: .75rem 1.25rem;
  border-bottom: 1px solid var(--border);
  transition: background .15s;
}
.cart-row:hover { background: var(--bg-1); }
.cart-row-icon { font-size: 1.4rem; flex-shrink: 0; }
.cart-row-info { flex: 1; min-width: 0; }
.cart-row-name {
  font-family: var(--font-cond);
  font-size: .85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--text-0);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cart-row-unit {
  font-size: .75rem;
  color: var(--text-2);
}
.qty-ctrl {
  display: flex;
  align-items: center;
  gap: .25rem;
}
.qty-btn {
  width: 24px; height: 24px;
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid var(--border);
  background: var(--bg-1);
  color: var(--text-0);
  cursor: pointer;
  font-size: .9rem;
  border-radius: 0;
  transition: all .15s;
}
.qty-btn:hover { background: var(--text-0); color: #fff; border-color: var(--text-0); }
.qty-num {
  width: 28px;
  text-align: center;
  font-family: var(--font-cond);
  font-weight: 700;
  font-size: .9rem;
}
.cart-row-price {
  font-family: var(--font-display);
  font-size: 1.1rem;
  color: var(--text-0);
  min-width: 64px;
  text-align: right;
}
.cart-remove {
  background: none; border: none;
  color: var(--text-2); cursor: pointer;
  font-size: .85rem;
  padding: .2rem;
  transition: color .15s;
}
.cart-remove:hover { color: var(--red); }

/* Cart footer / checkout */
.cart-footer {
  border-top: 2px solid var(--text-0);
  flex-shrink: 0;
  background: var(--bg-0);
}

.subtotal-rows {
  padding: .75rem 1.25rem .5rem;
}
.subtotal-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .25rem 0;
}
.subtotal-row .lbl {
  font-family: var(--font-cond);
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--text-2);
}
.subtotal-row .val {
  font-family: var(--font-body);
  font-size: .9rem;
  color: var(--text-0);
}
.total-big-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .75rem 1.25rem;
  background: var(--text-0);
}
.total-big-row .lbl {
  font-family: var(--font-display);
  font-size: 1.2rem;
  color: #fff;
  text-transform: uppercase;
}
.total-big-row .val {
  font-family: var(--font-display);
  font-size: 2rem;
  color: #fff;
}

.checkout-form {
  padding: .75rem 1.25rem 1rem;
  display: flex;
  flex-direction: column;
  gap: .6rem;
}
.checkout-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .6rem;
}
.checkout-label {
  font-family: var(--font-cond);
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--text-2);
  margin-bottom: .25rem;
  display: block;
}
.checkout-input {
  width: 100%;
  padding: .5rem .75rem;
  border: 1.5px solid var(--border);
  border-radius: 0;
  font-family: var(--font-body);
  font-size: .85rem;
  outline: none;
  transition: border-color .2s;
  background: var(--bg-0);
  color: var(--text-0);
}
.checkout-input:focus { border-color: var(--text-0); }

.change-display {
  background: var(--bg-1);
  border: 1.5px solid var(--border);
  border-left: 3px solid var(--red);
  padding: .5rem .75rem;
  display: none;
}
.change-display.visible { display: block; }
.change-display .change-lbl {
  font-family: var(--font-cond);
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--text-2);
}
.change-display .change-val {
  font-family: var(--font-display);
  font-size: 1.4rem;
  color: var(--red);
}

.complete-btn {
  width: 100%;
  padding: 1rem;
  background: var(--red);
  color: #fff;
  border: none;
  cursor: pointer;
  font-family: var(--font-display);
  font-size: 1.3rem;
  letter-spacing: .08em;
  text-transform: uppercase;
  transition: background .15s;
  margin-top: .25rem;
}
.complete-btn:hover:not(:disabled) { background: #aa0000; }
.complete-btn:disabled { opacity: .35; cursor: not-allowed; }

/* Recent sales panel (tab) */
.tabs-bar {
  display: flex;
  border-bottom: 1px solid var(--border);
  background: var(--bg-1);
  flex-shrink: 0;
}
.tab-btn {
  flex: 1;
  padding: .65rem;
  font-family: var(--font-cond);
  font-size: .75rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  border: none;
  background: transparent;
  color: var(--text-2);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all .15s;
}
.tab-btn.active { color: var(--text-0); border-bottom-color: var(--text-0); background: var(--bg-0); }

.tab-panel { display: none; flex: 1; overflow-y: auto; flex-direction: column; }
.tab-panel.active { display: flex; }

.recent-sale-row {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .75rem 1.25rem;
  border-bottom: 1px solid var(--border);
  font-size: .82rem;
}
.recent-sale-row:hover { background: var(--bg-1); }
.rs-id {
  font-family: var(--font-display);
  font-size: 1rem;
  color: var(--text-0);
  min-width: 40px;
}
.rs-info { flex: 1; }
.rs-name { font-weight: 600; color: var(--text-0); }
.rs-meta { color: var(--text-2); font-size: .75rem; }
.rs-total { font-family: var(--font-display); font-size: 1.1rem; color: var(--red); }

/* Receipt modal */
.receipt-modal-wrap {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.7);
  z-index: 300;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.receipt-modal-wrap.open { display: flex; }
.receipt-modal {
  background: #fff;
  width: 360px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,.4);
  animation: receiptSlide .25s ease;
}
@keyframes receiptSlide {
  from { opacity:0; transform:scale(.96) translateY(12px); }
  to   { opacity:1; transform:scale(1) translateY(0); }
}
.receipt-inner { padding: 1.5rem; font-family: 'Courier New', monospace; color: #111; font-size: .82rem; }
.receipt-logo { text-align: center; border-bottom: 2px dashed #ccc; padding-bottom: 1rem; margin-bottom: 1rem; }
.receipt-logo h2 { font-family: var(--font-display); font-size: 1.6rem; letter-spacing: .05em; margin-bottom: .2rem; color: var(--red); }
.receipt-logo p { font-size: .72rem; color: #555; margin: 0; }
.receipt-meta { margin-bottom: .75rem; }
.receipt-meta div { display: flex; justify-content: space-between; }
.receipt-items { border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: .75rem 0; margin: .75rem 0; }
.receipt-item { display: flex; justify-content: space-between; gap: .5rem; margin-bottom: .3rem; }
.receipt-item .ri-name { flex: 1; }
.receipt-totals { margin-bottom: 1rem; }
.receipt-totals div { display: flex; justify-content: space-between; }
.receipt-totals .total-line { font-weight: bold; border-top: 1px solid #ccc; padding-top: .4rem; margin-top: .4rem; font-size: .9rem; }
.receipt-footer { text-align: center; font-size: .72rem; color: #777; border-top: 2px dashed #ccc; padding-top: 1rem; }
.receipt-actions { display: flex; gap: .5rem; padding: 1rem 1.5rem; border-top: 1px solid #eee; background: #f9f9f9; }
.receipt-actions button { flex: 1; padding: .65rem; font-family: var(--font-cond); font-size: .8rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; cursor: pointer; border: none; transition: background .15s; }
.receipt-print-btn { background: var(--text-0); color: #fff; }
.receipt-print-btn:hover { background: #333; }
.receipt-close-btn { background: var(--bg-2); color: var(--text-0); border: 1px solid var(--border) !important; }

@media print {
  body > *:not(.receipt-modal-wrap) { display: none !important; }
  .receipt-modal-wrap { display: flex !important; background: none; }
  .receipt-modal { box-shadow: none; }
  .receipt-actions { display: none; }
}

/* Mobile */
@media (max-width: 900px) {
  .pos-wrap { grid-template-columns: 1fr; height: auto; }
  .pos-products-panel { height: 60vh; }
  .pos-cart-panel { height: auto; max-height: 70vh; }
}

/* Flash */
.pos-flash {
  position: fixed;
  top: 80px; right: 1.5rem;
  z-index: 200;
  background: var(--text-0);
  color: #fff;
  padding: .85rem 1.5rem;
  font-family: var(--font-cond);
  font-size: .85rem;
  font-weight: 700;
  letter-spacing: .05em;
  box-shadow: 0 8px 24px rgba(0,0,0,.3);
  animation: flashIn .25s ease;
  border-left: 4px solid var(--red);
}
@keyframes flashIn {
  from { opacity:0; transform: translateX(20px); }
  to   { opacity:1; transform: translateX(0); }
}
</style>

<?php $suc = flash('success'); ?>
<?php if ($suc): ?>
<div class="pos-flash" id="posFlash">✓ <?= h($suc) ?></div>
<?php endif; ?>

<div class="pos-wrap">

  <!-- ═══ LEFT: Products ═══ -->
  <div class="pos-products-panel">

    <!-- Top bar -->
    <div class="pos-topbar">
      <h2>POS</h2>
      <div class="pos-search-wrap">
        <input type="text" id="posSearch" placeholder="Search products…" autocomplete="off">
      </div>
    </div>

    <!-- Category tabs -->
    <div class="cat-tabs" id="catTabs">
      <button class="cat-tab active" data-cat="">All</button>
      <?php foreach ($categories as $c): ?>
        <button class="cat-tab" data-cat="<?= h($c) ?>"><?= ($cat_icons[$c] ?? '') . ' ' . h($c) ?></button>
      <?php endforeach; ?>
    </div>

    <!-- Product grid -->
    <div class="pos-grid" id="posGrid">
      <?php foreach ($products as $p):
        $icon = $cat_icons[$p['category']] ?? '📦';
        $oos  = $p['stock_qty'] < 1;
      ?>
        <div class="pos-tile <?= $oos ? 'out-of-stock' : '' ?>"
             id="tile-<?= $p['product_id'] ?>"
             data-id="<?= $p['product_id'] ?>"
             data-name="<?= h($p['product_name']) ?>"
             data-price="<?= $p['price'] ?>"
             data-stock="<?= $p['stock_qty'] ?>"
             data-cat="<?= h($p['category']) ?>"
             data-search="<?= strtolower(h($p['product_name'] . ' ' . $p['category'])) ?>"
             onclick="posAdd(<?= $p['product_id'] ?>)">
          <div class="in-cart-badge" id="badge-<?= $p['product_id'] ?>">0</div>
          <?php if ($p['image_url']): ?>
            <img class="tile-img" src="<?= h($p['image_url']) ?>" alt="<?= h($p['product_name']) ?>">
          <?php else: ?>
            <span class="tile-icon"><?= $icon ?></span>
          <?php endif; ?>
          <div class="tile-name"><?= h($p['product_name']) ?></div>
          <div class="tile-price">₱<?= number_format($p['price'], 2) ?></div>
          <div class="tile-stock"><?= $p['stock_qty'] ?> left</div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- ═══ RIGHT: Cart + Checkout ═══ -->
  <div class="pos-cart-panel" id="cartPanel">

    <!-- Tabs -->
    <div class="tabs-bar">
      <button class="tab-btn active" onclick="switchTab('cart')">🛒 Cart</button>
      <button class="tab-btn" onclick="switchTab('sales')">📋 Recent Sales</button>
    </div>

    <!-- Cart tab -->
    <div class="tab-panel active" id="tabCart" style="flex-direction:column;">
      <div class="cart-header">
        <h2>CART <span id="cartCount" style="font-size:1rem;color:var(--text-2);">(0)</span></h2>
        <button class="cart-clear-btn" onclick="clearCart()">Clear All</button>
      </div>

      <div class="cart-scroll" id="cartScroll">
        <div class="cart-empty-msg" id="cartEmpty">
          <span>🛒</span>
          <p>Tap a product to add</p>
        </div>
        <div id="cartRows"></div>
      </div>

      <!-- Totals + checkout -->
      <div class="cart-footer">
        <div class="subtotal-rows">
          <div class="subtotal-row">
            <span class="lbl">Items</span>
            <span class="val" id="itemCount">0</span>
          </div>
          <div class="subtotal-row">
            <span class="lbl">Subtotal</span>
            <span class="val" id="subtotalDisplay">₱0.00</span>
          </div>
        </div>
        <div class="total-big-row">
          <span class="lbl">Total</span>
          <span class="val" id="totalDisplay">₱0.00</span>
        </div>

        <form method="POST" id="saleForm">
          <input type="hidden" name="cart_json" id="cartJson" value="{}">
          <div class="checkout-form">
            <div class="checkout-row">
              <div>
                <span class="checkout-label">Customer</span>
                <select name="customer_id" class="checkout-input">
                  <option value="">Walk-in</option>
                  <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['user_id'] ?>"><?= h($c['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <span class="checkout-label">Payment</span>
                <select name="payment_method" class="checkout-input" id="paymentMethod" onchange="onPaymentChange()">
                  <option>Cash</option>
                  <option>GCash</option>
                  <option>Card</option>
                  <option>Bank Transfer</option>
                </select>
              </div>
            </div>

            <!-- Cash tendered (shown for Cash only) -->
            <div id="cashSection">
              <span class="checkout-label">Cash Tendered</span>
              <input type="number" id="cashTendered" class="checkout-input" placeholder="0.00" step="0.01" min="0" oninput="calcChange()">
              <div class="change-display" id="changeDisplay">
                <div class="change-lbl">Change</div>
                <div class="change-val" id="changeVal">₱0.00</div>
              </div>
            </div>

            <div>
              <span class="checkout-label">Notes</span>
              <input type="text" name="notes" class="checkout-input" placeholder="Optional note…">
            </div>

            <button type="submit" class="complete-btn" id="completeBtn" disabled>
              COMPLETE SALE
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Recent Sales tab -->
    <div class="tab-panel" id="tabSales">
      <?php if (empty($recent_sales)): ?>
        <div class="cart-empty-msg">
          <span>📋</span>
          <p>No sales yet today</p>
        </div>
      <?php else: ?>
        <?php foreach ($recent_sales as $s): ?>
          <div class="recent-sale-row">
            <div class="rs-id">#<?= $s['sale_id'] ?></div>
            <div class="rs-info">
              <div class="rs-name"><?= h($s['customer_name'] ?? 'Walk-in') ?></div>
              <div class="rs-meta"><?= $s['payment_method'] ?> · <?= date('M d, g:i A', strtotime($s['sale_date'])) ?></div>
            </div>
            <div class="rs-total">₱<?= number_format($s['total_amount'], 2) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ═══ Receipt Modal ═══ -->
<div class="receipt-modal-wrap" id="receiptModal">
  <div class="receipt-modal">
    <div class="receipt-inner" id="receiptContent">
      <!-- Filled by JS -->
    </div>
    <div class="receipt-actions">
      <button class="receipt-print-btn" onclick="window.print()">🖨 Print</button>
      <button class="receipt-close-btn" onclick="closeReceipt()">✕ Close</button>
    </div>
  </div>
</div>

<script>
/* ── State ── */
const products = <?= json_encode(array_map(fn($p) => [
  'id'    => (int)$p['product_id'],
  'name'  => $p['product_name'],
  'price' => (float)$p['price'],
  'stock' => (int)$p['stock_qty'],
  'cat'   => $p['category'],
  'icon'  => $cat_icons[$p['category']] ?? '📦',
], $products)) ?>;

let cart = {}; // { id: { id, name, price, qty, stock, icon } }
let totalAmount = 0;

/* ── Add to cart ── */
function posAdd(id) {
  const p = products.find(x => x.id === id);
  if (!p || p.stock < 1) return;
  if (cart[id]) {
    if (cart[id].qty >= p.stock) { showToast('Max stock reached'); return; }
    cart[id].qty++;
  } else {
    cart[id] = { ...p, qty: 1 };
  }
  // Flash tile
  const tile = document.getElementById('tile-' + id);
  tile.classList.remove('adding');
  void tile.offsetWidth;
  tile.classList.add('adding');

  renderCart();
}

/* ── Qty controls ── */
window.posChangeQty = function(id, delta) {
  if (!cart[id]) return;
  const newQ = cart[id].qty + delta;
  if (newQ <= 0)                      { delete cart[id]; }
  else if (newQ > cart[id].stock)     { showToast('Max stock reached'); return; }
  else                                { cart[id].qty = newQ; }
  renderCart();
};

window.posRemoveItem = function(id) {
  delete cart[id];
  renderCart();
};

function clearCart() {
  if (Object.keys(cart).length === 0) return;
  if (!confirm('Clear the cart?')) return;
  cart = {};
  renderCart();
}

/* ── Render ── */
function renderCart() {
  const items = Object.values(cart);
  const count = items.reduce((s, i) => s + i.qty, 0);
  totalAmount  = items.reduce((s, i) => s + i.price * i.qty, 0);

  // Update badges on tiles
  products.forEach(p => {
    const badge = document.getElementById('badge-' + p.id);
    const tile  = document.getElementById('tile-' + p.id);
    if (!badge || !tile) return;
    if (cart[p.id]) {
      badge.textContent = cart[p.id].qty;
      tile.classList.add('has-item');
    } else {
      badge.textContent = 0;
      tile.classList.remove('has-item');
    }
  });

  // Cart rows
  const cartRows  = document.getElementById('cartRows');
  const cartEmpty = document.getElementById('cartEmpty');
  cartEmpty.style.display  = items.length ? 'none' : '';
  cartRows.innerHTML = items.map(i => `
    <div class="cart-row">
      <div class="cart-row-icon">${i.icon}</div>
      <div class="cart-row-info">
        <div class="cart-row-name">${i.name}</div>
        <div class="cart-row-unit">₱${i.price.toLocaleString('en-PH',{minimumFractionDigits:2})} each</div>
      </div>
      <div class="qty-ctrl">
        <button class="qty-btn" onclick="posChangeQty(${i.id},-1)">−</button>
        <span class="qty-num">${i.qty}</span>
        <button class="qty-btn" onclick="posChangeQty(${i.id},+1)">+</button>
      </div>
      <div class="cart-row-price">₱${(i.price*i.qty).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
      <button class="cart-remove" onclick="posRemoveItem(${i.id})" title="Remove">✕</button>
    </div>
  `).join('');

  // Totals
  document.getElementById('cartCount').textContent    = `(${count})`;
  document.getElementById('itemCount').textContent    = count;
  document.getElementById('subtotalDisplay').textContent = fmt(totalAmount);
  document.getElementById('totalDisplay').textContent    = fmt(totalAmount);
  document.getElementById('cartJson').value           = JSON.stringify(cart);
  document.getElementById('completeBtn').disabled     = items.length === 0;

  calcChange();
}

/* ── Change calculator ── */
function calcChange() {
  const tendered = parseFloat(document.getElementById('cashTendered').value) || 0;
  const change   = tendered - totalAmount;
  const display  = document.getElementById('changeDisplay');
  const val      = document.getElementById('changeVal');
  if (document.getElementById('paymentMethod').value !== 'Cash') {
    display.classList.remove('visible'); return;
  }
  if (tendered > 0) {
    display.classList.add('visible');
    val.textContent = change >= 0 ? fmt(change) : '⚠ Short by ' + fmt(Math.abs(change));
    val.style.color = change >= 0 ? 'var(--red)' : '#c0392b';
  } else {
    display.classList.remove('visible');
  }
}

function onPaymentChange() {
  const method = document.getElementById('paymentMethod').value;
  document.getElementById('cashSection').style.display = method === 'Cash' ? '' : 'none';
  calcChange();
}

/* ── Category filter ── */
document.getElementById('catTabs').addEventListener('click', e => {
  const btn = e.target.closest('.cat-tab');
  if (!btn) return;
  document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const cat = btn.dataset.cat;
  filterTiles(cat, document.getElementById('posSearch').value.toLowerCase());
});

/* ── Search filter ── */
document.getElementById('posSearch').addEventListener('input', function() {
  const activeCat = document.querySelector('.cat-tab.active')?.dataset.cat || '';
  filterTiles(activeCat, this.value.toLowerCase());
});

function filterTiles(cat, q) {
  document.querySelectorAll('.pos-tile').forEach(tile => {
    const catMatch = !cat || tile.dataset.cat === cat;
    const qMatch   = !q   || tile.dataset.search.includes(q);
    tile.style.display = (catMatch && qMatch) ? '' : 'none';
  });
}

/* ── Tab switching ── */
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach((b,i) => {
    b.classList.toggle('active', (i === 0 && name==='cart') || (i===1 && name==='sales'));
  });
  document.getElementById('tabCart').classList.toggle('active', name === 'cart');
  document.getElementById('tabSales').classList.toggle('active', name === 'sales');
}

/* ── Toast ── */
function showToast(msg) {
  const t = document.createElement('div');
  t.className = 'pos-flash';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 2000);
}

/* ── Receipt ── */
<?php if ($last_sale): ?>
window.addEventListener('DOMContentLoaded', () => showReceipt(<?= json_encode($last_sale) ?>));
<?php endif; ?>

function showReceipt(sale) {
  const items = Object.values(sale.items);
  document.getElementById('receiptContent').innerHTML = `
    <div class="receipt-logo">
      <h2>RMI.</h2>
      <p>Resurrection Musical Instruments</p>
      <p>Panabo City, Davao del Norte</p>
      <p>09463439660</p>
    </div>
    <div class="receipt-meta">
      <div><span>Sale #</span><strong>${sale.id}</strong></div>
      <div><span>Date</span><span>${sale.date}</span></div>
      <div><span>Payment</span><span>${sale.method}</span></div>
    </div>
    <div class="receipt-items">
      ${items.map(i=>`
        <div class="receipt-item">
          <span class="ri-name">${i.name} x${i.qty}</span>
          <span>₱${(i.price*i.qty).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>
        </div>
      `).join('')}
    </div>
    <div class="receipt-totals">
      <div class="total-line"><strong>TOTAL</strong><strong>₱${sale.total.toLocaleString('en-PH',{minimumFractionDigits:2})}</strong></div>
    </div>
    <div class="receipt-footer">
      <p>Thank you for your purchase!</p>
      <p>Come back soon — RMI Panabo City</p>
    </div>
  `;
  document.getElementById('receiptModal').classList.add('open');
}

function closeReceipt() {
  document.getElementById('receiptModal').classList.remove('open');
}

/* ── Util ── */
function fmt(n) {
  return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
}

/* ── Auto-dismiss flash ── */
const flash = document.getElementById('posFlash');
if (flash) setTimeout(() => {
  flash.style.transition = 'opacity .4s';
  flash.style.opacity = '0';
  setTimeout(() => flash.remove(), 400);
}, 3500);

/* ── Confirm on unload if cart has items ── */
window.addEventListener('beforeunload', e => {
  if (Object.keys(cart).length > 0) {
    e.preventDefault();
    e.returnValue = '';
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>