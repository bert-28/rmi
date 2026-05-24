<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
require_role(['customer']);
$db   = get_db();
$user = current_user();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Load cart products from DB
$cart_items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    $pids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $stmt = $db->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
    $stmt->execute($pids);
    foreach ($stmt->fetchAll() as $p) {
        $qty = (int)$_SESSION['cart'][$p['product_id']];
        $qty = min($qty, $p['stock_qty']); // cap at available stock
        if ($qty < 1) { unset($_SESSION['cart'][$p['product_id']]); continue; }
        $_SESSION['cart'][$p['product_id']] = $qty;
        $cart_items[] = array_merge($p, ['qty' => $qty, 'subtotal' => $qty * $p['price']]);
        $total += $qty * $p['price'];
    }
}

$flash       = flash('success');
$flash_error = flash('error');
$page_title  = 'Your Cart';
require_once __DIR__ . '/includes/header.php';

$cat_icons = [
  'Guitar'    => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 9-2 2a4.5 4.5 0 1 0 6 6l2-2"/><path d="M13 6l3-3 3 3-3 3z"/><path d="m10 10 4-4"/><path d="m6 20 3-3"/></svg>',
  'Bass'      => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
  'Amplifier' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>',
  'Strings'   => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h1m16 0h1"/><path d="M4 12c2-4 4-6 8-6s6 2 8 6"/><path d="M4 12c2 4 4 6 8 6s6-2 8-6"/></svg>',
  'Pedal'     => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3"/><path d="M8 5V3"/><path d="M8 11v2"/><circle cx="16" cy="16" r="3"/><path d="M16 13v-2"/><path d="M16 19v2"/><path d="M3 16h5"/><path d="M16 8h5"/></svg>',
  'Accessory' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="17" r="3"/><circle cx="16" cy="15" r="3"/><polyline points="9 17 9 5 19 3 19 15"/><line x1="9" y1="9" x2="19" y2="7"/></svg>',
  'Other'     => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
];
?>

<style>
.cart-page-wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem; }
.cart-page-grid { display: grid; grid-template-columns: 1fr 320px; gap: 2rem; align-items: start; }

.cart-table { width: 100%; border-collapse: collapse; }
.cart-table thead th {
  font-family: var(--font-cond); font-size: .72rem; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase; color: var(--text-2);
  padding: .75rem 1rem; border-bottom: 2px solid var(--text-0);
  text-align: left; background: var(--bg-1);
}
.cart-table tbody tr { border-bottom: 1px solid var(--border); }
.cart-table tbody tr:hover { background: var(--bg-1); }
.cart-table tbody td { padding: 1rem; vertical-align: middle; }

.cart-item-info { display: flex; align-items: center; gap: 1rem; }
.cart-item-icon { font-size: 2rem; flex-shrink: 0; }
.cart-item-name {
  font-family: var(--font-cond); font-size: .9rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .04em; color: var(--text-0);
}
.cart-item-cat { font-size: .75rem; color: var(--text-2); }

.qty-control { display: flex; align-items: center; gap: .4rem; }
.qty-control button {
  width: 28px; height: 28px; border: 1.5px solid var(--border);
  background: var(--bg-1); cursor: pointer; font-size: 1rem;
  display: flex; align-items: center; justify-content: center;
  transition: all .12s; border-radius: 0;
}
.qty-control button:hover { background: var(--text-0); color: #fff; border-color: var(--text-0); }
.qty-control span { width: 30px; text-align: center; font-weight: 700; font-size: .9rem; }

.item-price { font-family: var(--font-display); font-size: 1.1rem; color: var(--text-0); }
.item-subtotal { font-family: var(--font-display); font-size: 1.2rem; color: var(--red); }
.remove-btn {
  background: none; border: none; color: var(--text-2); cursor: pointer;
  font-size: .85rem; padding: .3rem; transition: color .12s;
}
.remove-btn:hover { color: var(--red); }

/* Order summary */
.order-summary {
  border: 1px solid var(--border);
  border-top: 3px solid var(--text-0);
  background: var(--bg-0);
  position: sticky; top: 88px;
}
.os-head {
  padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
  font-family: var(--font-display); font-size: 1.2rem; text-transform: uppercase;
}
.os-body { padding: 1.25rem; }
.os-row { display: flex; justify-content: space-between; margin-bottom: .75rem; font-size: .875rem; }
.os-row .lbl { color: var(--text-2); font-family: var(--font-cond); font-weight: 700; letter-spacing: .06em; text-transform: uppercase; font-size: .75rem; }
.os-row .val { font-weight: 600; }
.os-total { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; background: var(--text-0); }
.os-total .lbl { font-family: var(--font-display); font-size: 1.1rem; color: #fff; text-transform: uppercase; }
.os-total .val { font-family: var(--font-display); font-size: 1.8rem; color: #fff; }

.checkout-form-section { padding: 1.25rem; border-top: 1px solid var(--border); }
.checkout-form-section label {
  font-family: var(--font-cond); font-size: .72rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; color: var(--text-2);
  display: block; margin-bottom: .35rem;
}
.checkout-form-section select,
.checkout-form-section input {
  width: 100%; padding: .6rem .9rem; border: 1.5px solid var(--border);
  border-radius: 0; font-family: var(--font-body); font-size: .875rem;
  background: var(--bg-0); color: var(--text-0); outline: none;
  margin-bottom: .85rem; transition: border-color .2s;
}
.checkout-form-section select:focus,
.checkout-form-section input:focus { border-color: var(--text-0); }

.place-order-btn {
  width: 100%; padding: 1rem; background: var(--red); color: #fff;
  border: none; cursor: pointer; font-family: var(--font-display);
  font-size: 1.2rem; letter-spacing: .06em; text-transform: uppercase;
  transition: background .15s;
}
.place-order-btn:hover { background: #aa0000; }
.place-order-btn:disabled { background: var(--bg-3); color: var(--text-2); cursor: not-allowed; }

@media (max-width: 700px) {
  .cart-page-grid { grid-template-columns: 1fr; }
  .order-summary { position: static; }
}
</style>

<div class="cart-page-wrap">
  <div class="page-header">
    <div>
      <div class="section-label">Checkout</div>
      <h1>Your Cart</h1>
      <p class="page-subtitle"><?= count($cart_items) ?> item<?= count($cart_items)!==1?'s':'' ?> · <a href="shop.php">← Continue Shopping</a></p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success">✓ <?= h($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-danger">⚠ <?= h($flash_error) ?></div>
  <?php endif; ?>

  <?php if (empty($cart_items)): ?>
    <div class="empty-state">
      <div class="icon">🛒</div>
      <p>Your cart is empty.</p>
      <a href="shop.php" class="btn btn-primary" style="margin-top:1.25rem">Browse the Shop</a>
    </div>
  <?php else: ?>

  <div class="cart-page-grid">
    <!-- Cart items -->
    <div>
      <table class="cart-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Subtotal</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="cartBody">
          <?php foreach ($cart_items as $item):
            $icon = $cat_icons[$item['category']] ?? '📦';
          ?>
          <tr id="row-<?= $item['product_id'] ?>">
            <td>
              <div class="cart-item-info">
                <div class="cart-item-icon"><?= $icon ?></div>
                <div>
                  <div class="cart-item-name"><?= h($item['product_name']) ?></div>
                  <div class="cart-item-cat"><?= h($item['category']) ?> · <?= $item['stock_qty'] ?> in stock</div>
                </div>
              </div>
            </td>
            <td><span class="item-price">₱<?= number_format($item['price'], 2) ?></span></td>
            <td>
              <div class="qty-control">
                <button onclick="updateQty(<?= $item['product_id'] ?>, -1)">−</button>
                <span id="qty-<?= $item['product_id'] ?>"><?= $item['qty'] ?></span>
                <button onclick="updateQty(<?= $item['product_id'] ?>, +1)">+</button>
              </div>
            </td>
            <td>
              <span class="item-subtotal" id="sub-<?= $item['product_id'] ?>">
                ₱<?= number_format($item['subtotal'], 2) ?>
              </span>
            </td>
            <td>
              <button class="remove-btn" onclick="removeItem(<?= $item['product_id'] ?>)" title="Remove">✕</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Order summary + checkout form -->
    <div class="order-summary">
      <div class="os-head">Order Summary</div>
      <div class="os-body">
        <div class="os-row">
          <span class="lbl">Items</span>
          <span class="val" id="summaryCount"><?= count($cart_items) ?></span>
        </div>
        <div class="os-row">
          <span class="lbl">Subtotal</span>
          <span class="val" id="summarySubtotal">₱<?= number_format($total, 2) ?></span>
        </div>
        <div class="os-row">
          <span class="lbl">Pickup</span>
          <span class="val" style="color:var(--text-2)">In-store only</span>
        </div>
      </div>
      <div class="os-total">
        <span class="lbl">Total</span>
        <span class="val" id="summaryTotal">₱<?= number_format($total, 2) ?></span>
      </div>

      <!-- Checkout form -->
      <form method="POST" action="checkout_process.php" id="checkoutForm">
        <div class="checkout-form-section">
          <label>Payment Method</label>
          <select name="payment_method" required>
            <option>Cash</option>
            <option>GCash</option>
            <option>Card</option>
            <option>Bank Transfer</option>
          </select>

          <label>Notes / Special Requests</label>
          <input type="text" name="notes" placeholder="Optional…">

          <button type="submit" class="place-order-btn" id="placeOrderBtn">
            Place Order →
          </button>
          <p style="font-size:.72rem;color:var(--text-2);text-align:center;margin-top:.75rem;font-family:var(--font-cond);letter-spacing:.05em;text-transform:uppercase">
            Orders are prepared for in-store pickup
          </p>
        </div>
      </form>
    </div>
  </div>

  <?php endif; ?>
</div>

<script>
const stockLimits = {
  <?php foreach ($cart_items as $item): ?>
  <?= $item['product_id'] ?>: <?= $item['stock_qty'] ?>,
  <?php endforeach; ?>
};
const prices = {
  <?php foreach ($cart_items as $item): ?>
  <?= $item['product_id'] ?>: <?= $item['price'] ?>,
  <?php endforeach; ?>
};
let cartQtys = {
  <?php foreach ($cart_items as $item): ?>
  <?= $item['product_id'] ?>: <?= $item['qty'] ?>,
  <?php endforeach; ?>
};

function updateQty(id, delta) {
  const newQ = cartQtys[id] + delta;
  if (newQ < 1)                  { removeItem(id); return; }
  if (newQ > stockLimits[id])    { alert('Max stock reached'); return; }
  cartQtys[id] = newQ;
  document.getElementById('qty-' + id).textContent = newQ;
  document.getElementById('sub-' + id).textContent = '₱' + (prices[id] * newQ).toLocaleString('en-PH', {minimumFractionDigits:2});
  syncSummary();
  saveSession();
}

function removeItem(id) {
  delete cartQtys[id];
  const row = document.getElementById('row-' + id);
  if (row) row.remove();
  syncSummary();
  saveSession();
}

function syncSummary() {
  const ids = Object.keys(cartQtys);
  const count = ids.reduce((s,id) => s + cartQtys[id], 0);
  const total = ids.reduce((s,id) => s + prices[id] * cartQtys[id], 0);
  document.getElementById('summaryCount').textContent    = ids.length;
  document.getElementById('summarySubtotal').textContent = '₱' + total.toLocaleString('en-PH',{minimumFractionDigits:2});
  document.getElementById('summaryTotal').textContent    = '₱' + total.toLocaleString('en-PH',{minimumFractionDigits:2});
  document.getElementById('placeOrderBtn').disabled = ids.length === 0;
}

function saveSession() {
  fetch('cart_update.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(cartQtys),
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>