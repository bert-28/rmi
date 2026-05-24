<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
$db = get_db();

$categories = ['Guitar','Bass','Amplifier','Strings','Pedal','Accessory','Other'];
$cat_icons  = [
  'Guitar'    => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 9-2 2a4.5 4.5 0 1 0 6 6l2-2"/><path d="M13 6l3-3 3 3-3 3z"/><path d="m10 10 4-4"/><path d="m6 20 3-3"/></svg>',
  'Bass'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
  'Amplifier' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>',
  'Strings'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h1m16 0h1"/><path d="M4 12c2-4 4-6 8-6s6 2 8 6"/><path d="M4 12c2 4 4 6 8 6s6-2 8-6"/></svg>',
  'Pedal'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3"/><path d="M8 5V3"/><path d="M8 11v2"/><circle cx="16" cy="16" r="3"/><path d="M16 13v-2"/><path d="M16 19v2"/><path d="M3 16h5"/><path d="M16 8h5"/></svg>',
  'Accessory' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="17" r="3"/><circle cx="16" cy="15" r="3"/><polyline points="9 17 9 5 19 3 19 15"/><line x1="9" y1="9" x2="19" y2="7"/></svg>',
  'Other'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
];

$cat_filter = $_GET['category'] ?? '';
$search     = trim($_GET['q'] ?? '');
$sort       = $_GET['sort'] ?? 'default';

$where  = "WHERE stock_qty > 0";
$params = [];
if ($cat_filter) { $where .= ' AND category = ?'; $params[] = $cat_filter; }
if ($search) {
    $where .= ' AND (product_name LIKE ? OR description LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}
$order = match($sort) {
    'price_asc'  => 'ORDER BY price ASC',
    'price_desc' => 'ORDER BY price DESC',
    'name'       => 'ORDER BY product_name ASC',
    default      => 'ORDER BY category, product_name',
};

$stmt = $db->prepare("SELECT * FROM products $where $order");
$stmt->execute($params);
$products = $stmt->fetchAll();

$counts = [];
foreach ($db->query("SELECT category, COUNT(*) as cnt FROM products WHERE stock_qty > 0 GROUP BY category")->fetchAll() as $row) {
    $counts[$row['category']] = $row['cnt'];
}

// Session cart: ['product_id' => qty, ...]
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$cart_count = array_sum($_SESSION['cart']);

$page_title = 'Shop';
require_once __DIR__ . '/includes/header.php';
?>

<style></style>

<!-- Shop hero -->
<div class="shop-hero">
  <div class="shop-hero-inner">
    <div>
      <div class="section-label" style="color:rgba(255,255,255,.4)">Browse</div>
      <h1>Our <span>Collection</span></h1>
      <p>Premium instruments and accessories, ready for pickup</p>
    </div>
    <button class="cart-trigger" onclick="toggleMobileCart()">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:.35rem"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>Cart
      <span class="cart-trigger-badge" id="heroCartCount"><?= $cart_count ?></span>
    </button>
  </div>
</div>

<!-- Filters -->
<div class="shop-filters">
  <div class="cat-chips">
    <a href="?<?= $search ? 'q='.urlencode($search).'&' : '' ?>sort=<?= h($sort) ?>"
       class="cat-chip <?= !$cat_filter ? 'active' : '' ?>">All <span class="chip-count">(<?= array_sum($counts) ?>)</span></a>
    <?php foreach ($categories as $c): if (!isset($counts[$c])) continue; ?>
      <a href="?category=<?= urlencode($c) ?><?= $search ? '&q='.urlencode($search) : '' ?>&sort=<?= h($sort) ?>"
         class="cat-chip <?= $cat_filter === $c ? 'active' : '' ?>">
        <?= $cat_icons[$c] ?> <?= $c ?> <span class="chip-count">(<?= $counts[$c] ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="sort-bar">
    <form method="GET" style="display:contents">
      <?php if ($cat_filter): ?><input type="hidden" name="category" value="<?= h($cat_filter) ?>"><?php endif; ?>
      <?php if ($search): ?><input type="hidden" name="q" value="<?= h($search) ?>"><?php endif; ?>
      <select name="sort" onchange="this.form.submit()">
        <option value="default"    <?= $sort==='default'    ?'selected':'' ?>>Default</option>
        <option value="price_asc"  <?= $sort==='price_asc'  ?'selected':'' ?>>Price ↑</option>
        <option value="price_desc" <?= $sort==='price_desc' ?'selected':'' ?>>Price ↓</option>
        <option value="name"       <?= $sort==='name'       ?'selected':'' ?>>Name A–Z</option>
      </select>
    </form>
  </div>
</div>

<!-- Main content -->
<div class="shop-wrap">

  <!-- Products -->
  <div class="shop-products">
    <div class="search-bar-wrap">
      <form method="GET" style="display:contents">
        <?php if ($cat_filter): ?><input type="hidden" name="category" value="<?= h($cat_filter) ?>"><?php endif; ?>
        <?php if ($sort !== 'default'): ?><input type="hidden" name="sort" value="<?= h($sort) ?>"><?php endif; ?>
        <input type="text" name="q" placeholder="Search instruments…" value="<?= h($search) ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <?php if ($search || $cat_filter): ?>
          <a href="shop.php" class="btn btn-ghost btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="results-count"><?= count($products) ?> product<?= count($products)!==1?'s':'' ?> found</div>

    <?php if (empty($products)): ?>
      <div class="empty-state">
        <div class="icon"><?= $cat_icons[$cat_filter] ?? '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' ?></div>
        <p>No products found<?= $search ? ' for "'.h($search).'"' : '' ?>.</p>
        <a href="shop.php" class="btn btn-ghost" style="margin-top:1rem">Browse All</a>
      </div>
    <?php else: ?>
      <div class="shop-grid">
        <?php foreach ($products as $p):
          $oos = $p['stock_qty'] < 1;
          $low = $p['stock_qty'] <= 3 && !$oos;
        ?>
          <div class="shop-card">
            <a href="product-view.php?id=<?= $p['product_id'] ?>" style="text-decoration:none;color:inherit">
              <div class="shop-card-img">
                <?php if ($p['image_url']): ?>
                  <img src="<?= h($p['image_url']) ?>" alt="<?= h($p['product_name']) ?>">
                <?php else: ?>
                  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="opacity:.2"><path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                <?php endif; ?>
              </div>
            </a>
            <div class="shop-card-body">
              <div class="shop-card-cat"><?= $cat_icons[$p['category']] ?? '' ?> <?= h($p['category']) ?></div>
              <div class="shop-card-name"><?= h($p['product_name']) ?></div>
              <?php if ($p['description']): ?>
                <div class="shop-card-desc"><?= h(mb_strimwidth($p['description'], 0, 75, '…')) ?></div>
              <?php endif; ?>
              <div class="shop-card-foot">
                <span class="shop-card-price">₱<?= number_format($p['price'], 2) ?></span>
                <div style="display:flex;align-items:center;gap:.4rem">
                  <?php if ($low): ?>
                    <span class="stock-badge stock-low">Only <?= $p['stock_qty'] ?> left</span>
                  <?php elseif ($oos): ?>
                    <span class="stock-badge stock-out">Out of Stock</span>
                  <?php else: ?>
                    <span class="stock-badge stock-ok">In Stock</span>
                  <?php endif; ?>
                  <button class="add-cart-btn"
                    <?= $oos ? 'disabled' : '' ?>
                    onclick="addToCart(<?= $p['product_id'] ?>, '<?= addslashes(h($p['product_name'])) ?>', <?= $p['price'] ?>, <?= $p['stock_qty'] ?>, '<?= addslashes(h($p['category'])) ?>')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    Cart
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Cart sidebar -->
  <div class="cart-sidebar" id="cartSidebar">
    <div class="cart-side-head">
      <h3>YOUR CART <span id="cartCountLabel" style="font-size:.85rem;font-weight:400;color:var(--text-2)">(0 items)</span></h3>
      <button onclick="toggleMobileCart()" style="background:none;border:none;cursor:pointer;color:var(--text-2);display:none;line-height:1" id="mobileCartClose">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="cart-side-scroll" id="cartScroll">
      <div class="cart-side-empty" id="cartEmpty">
        <span><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></span>
        <p>Your cart is empty</p>
      </div>
      <div id="cartItems"></div>
    </div>
    <div class="cart-side-foot">
      <div class="cart-total-row">
        <span class="lbl">Total</span>
        <span class="val" id="cartTotal">₱0.00</span>
      </div>
      <a href="cart.php" class="checkout-link disabled" id="checkoutBtn">Checkout →</a>
    </div>
  </div>

</div>

<!-- Toast -->
<div class="cart-toast" id="cartToast">✓ Added to cart</div>

<script>
// SVG icon map for cart items
const CAT_ICONS = {
  'Guitar':    '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 9-2 2a4.5 4.5 0 1 0 6 6l2-2"/><path d="M13 6l3-3 3 3-3 3z"/><path d="m10 10 4-4"/><path d="m6 20 3-3"/></svg>',
  'Bass':      '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
  'Amplifier': '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>',
  'Strings':   '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h1m16 0h1"/><path d="M4 12c2-4 4-6 8-6s6 2 8 6"/><path d="M4 12c2 4 4 6 8 6s6-2 8-6"/></svg>',
  'Pedal':     '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3"/><path d="M8 5V3"/><path d="M8 11v2"/><circle cx="16" cy="16" r="3"/><path d="M16 13v-2"/><path d="M16 19v2"/><path d="M3 16h5"/><path d="M16 8h5"/></svg>',
  'Accessory': '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="17" r="3"/><circle cx="16" cy="15" r="3"/><polyline points="9 17 9 5 19 3 19 15"/><line x1="9" y1="9" x2="19" y2="7"/></svg>',
  'Other':     '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
};

// ── Cart state (synced with session via AJAX) ──
let cart = <?= json_encode(array_map(function($pid) use ($products) {
    foreach ($products as $p) {
        if ($p['product_id'] == $pid) return [
            'id'      => (int)$p['product_id'],
            'name'    => $p['product_name'],
            'price'   => (float)$p['price'],
            'stock'   => (int)$p['stock_qty'],
            'category'=> $p['category'],
            'qty'     => (int)$_SESSION['cart'][$pid],
        ];
    }
    return null;
}, array_keys($_SESSION['cart']))) ?>.filter(Boolean);

// Build lookup from array
let cartMap = {};
cart.forEach(i => cartMap[i.id] = i);

function addToCart(id, name, price, stock, category) {
  if (cartMap[id]) {
    if (cartMap[id].qty >= stock) { showToast('Max stock reached'); return; }
    cartMap[id].qty++;
  } else {
    cartMap[id] = { id, name, price, stock, category, qty: 1 };
  }
  // Save to session, then redirect to cart.php
  const payload = {};
  Object.values(cartMap).forEach(i => payload[i.id] = i.qty);
  fetch('cart_update.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  }).then(() => {
    window.location.href = 'cart.php';
  }).catch(() => {
    window.location.href = 'cart.php';
  });
}

window.changeQty = function(id, delta) {
  if (!cartMap[id]) return;
  const newQ = cartMap[id].qty + delta;
  if (newQ <= 0)                 { delete cartMap[id]; }
  else if (newQ > cartMap[id].stock) { showToast('Max stock reached'); return; }
  else                           { cartMap[id].qty = newQ; }
  saveCart();
  renderCart();
};

window.removeItem = function(id) {
  delete cartMap[id];
  saveCart();
  renderCart();
};

function saveCart() {
  const payload = {};
  Object.values(cartMap).forEach(i => payload[i.id] = i.qty);
  fetch('cart_update.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
}

function renderCart() {
  const items = Object.values(cartMap);
  const count = items.reduce((s,i) => s + i.qty, 0);
  const total = items.reduce((s,i) => s + i.price * i.qty, 0);

  document.getElementById('cartEmpty').style.display = items.length ? 'none' : '';
  document.getElementById('cartItems').innerHTML = items.map(i => `
    <div class="cart-side-item">
      <div class="csi-icon">${CAT_ICONS[i.category] || CAT_ICONS['Other']}</div>
      <div class="csi-info">
        <div class="csi-name">${i.name}</div>
        <div class="csi-price">₱${i.price.toLocaleString('en-PH',{minimumFractionDigits:2})} each</div>
      </div>
      <div class="csi-qty">
        <button class="csi-qty-btn" onclick="changeQty(${i.id},-1)">−</button>
        <span class="csi-qty-num">${i.qty}</span>
        <button class="csi-qty-btn" onclick="changeQty(${i.id},+1)">+</button>
      </div>
      <button class="csi-remove" onclick="removeItem(${i.id})"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
  `).join('');

  document.getElementById('cartCountLabel').textContent = `(${count} item${count!==1?'s':''})`;
  document.getElementById('cartTotal').textContent      = '₱' + total.toLocaleString('en-PH',{minimumFractionDigits:2});
  document.getElementById('heroCartCount').textContent  = count;

  const btn = document.getElementById('checkoutBtn');
  if (items.length) btn.classList.remove('disabled');
  else              btn.classList.add('disabled');
}

function showToast(msg) {
  const t = document.getElementById('cartToast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 2500);
}

function toggleMobileCart() {
  document.getElementById('cartSidebar').classList.toggle('mobile-open');
  const closeBtn = document.getElementById('mobileCartClose');
  if (window.innerWidth <= 900) closeBtn.style.display = '';
}

// Initial render
renderCart();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>