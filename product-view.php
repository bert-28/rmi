<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
require_role(['customer']);
$db = get_db();

// Fix 4: Initialize session cart consistently with cart.php
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// 1. Grab the ID from the URL
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: shop.php");
    exit;
}

// 2. Fetch product
$stmt = $db->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
$stmt->execute([$id]);
$p = $stmt->fetch();

// 3. Handle not found
if (!$p) {
    header("Location: shop.php");
    exit;
}

// 4. Fetch related products (same category, exclude current)
$rel_stmt = $db->prepare("SELECT * FROM products WHERE category = ? AND product_id != ? LIMIT 4");
$rel_stmt->execute([$p['category'], $id]);
$related = $rel_stmt->fetchAll();

$page_title = $p['product_name'];

// Fix 2: Define format_money() if not already available via config helpers
if (!function_exists('format_money')) {
    function format_money(float $amount): string {
        return '₱' . number_format($amount, 2);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
  /* ── Product View Page ── */
  .pv-page {
    background: var(--bg-0);
    min-height: 100vh;
    padding-bottom: 6rem;
  }

  /* Breadcrumb */
  .pv-breadcrumb {
    padding: 1.5rem 0 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--text-2);
  }

  .pv-breadcrumb a {
    color: var(--text-2);
    text-decoration: none;
    transition: color 0.15s;
  }
  .pv-breadcrumb a:hover { color: var(--gold); }

  .pv-breadcrumb .sep { opacity: 0.4; }
  .pv-breadcrumb .current { color: var(--text-1); }

  /* ── Main grid ── */
  .pv-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    margin-top: 2.5rem;
    align-items: start;
  }

  @media (max-width: 900px) {
    .pv-grid { grid-template-columns: 1fr; gap: 2.5rem; }
  }

  /* ── Image column ── */
  .pv-img-col { position: sticky; top: 2rem; }

  .pv-img-main {
    background: var(--bg-1);
    border: 1px solid var(--border);
    border-radius: 16px;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
  }

  .pv-img-main img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 2rem;
    transition: transform 0.5s ease;
  }
  .pv-img-main:hover img { transform: scale(1.04); }

  .pv-img-placeholder {
    font-size: 6rem;
    opacity: 0.15;
  }

  /* Stock badge on image */
  .pv-stock-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    padding: 5px 14px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }
  .pv-stock-badge.in  { background: rgba(34,197,94,0.12); color: var(--success, #22c55e); border: 1px solid rgba(34,197,94,0.2); }
  .pv-stock-badge.out { background: rgba(239,68,68,0.12);  color: var(--danger,  #ef4444); border: 1px solid rgba(239,68,68,0.2); }

  /* ── Details column ── */
  .pv-details { padding-top: 0.5rem; }

  .pv-category {
    display: inline-block;
    color: var(--gold);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.14em;
    margin-bottom: 0.75rem;
  }

  .pv-name {
    font-family: var(--font-display, 'Oswald', sans-serif);
    font-size: clamp(2rem, 4vw, 2.8rem);
    font-weight: 700;
    line-height: 1.1;
    color: var(--text-0, #fff);
    margin: 0 0 1rem;
  }

  .pv-price {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gold);
    margin-bottom: 1.75rem;
    letter-spacing: -0.01em;
  }

  .pv-desc {
    color: var(--text-2);
    line-height: 1.8;
    font-size: 1rem;
    margin-bottom: 2rem;
    border-top: 1px solid var(--border);
    padding-top: 1.5rem;
  }

  /* Specs mini-table */
  .pv-specs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1px;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 2rem;
  }

  .pv-spec {
    background: var(--bg-1);
    padding: 0.85rem 1.1rem;
  }

  .pv-spec-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-2);
    margin-bottom: 0.2rem;
  }

  .pv-spec-value {
    font-size: 0.95rem;
    color: var(--text-1);
    font-weight: 500;
  }

  /* Purchase box */
  .pv-purchase {
    background: var(--bg-1);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.75rem;
  }

  .pv-avail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
  }

  .pv-avail-label {
    font-size: 0.85rem;
    color: var(--text-2);
    font-weight: 500;
  }

  .pv-avail-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    font-weight: 600;
  }

  .pv-avail-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
  }

  .pv-qty-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
  }

  .pv-qty-label { font-size: 0.85rem; color: var(--text-2); font-weight: 500; }

  .pv-qty-ctrl {
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
  }

  .pv-qty-btn {
    width: 36px; height: 36px;
    background: none;
    border: none;
    color: var(--text-1);
    font-size: 1.1rem;
    cursor: pointer;
    transition: background 0.15s;
    display: flex; align-items: center; justify-content: center;
  }
  .pv-qty-btn:hover { background: var(--bg-2, rgba(255,255,255,0.05)); }

  .pv-qty-num {
    width: 42px;
    text-align: center;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-1);
    border-left: 1px solid var(--border);
    border-right: 1px solid var(--border);
    padding: 0;
    height: 36px;
    line-height: 36px;
    background: none;
    user-select: none;
  }

  /* Buttons */
  .pv-btn-group { display: flex; gap: 0.75rem; }

  .pv-btn-cart {
    flex: 1;
    padding: 0.95rem 1.5rem;
    background: var(--gold, #c99526);
    color: #000;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    letter-spacing: 0.03em;
    transition: opacity 0.2s, transform 0.15s;
  }
  .pv-btn-cart:hover  { opacity: 0.88; }
  .pv-btn-cart:active { transform: scale(0.98); }
  .pv-btn-cart:disabled { opacity: 0.4; cursor: not-allowed; }

  .pv-btn-wish {
    width: 48px; height: 48px;
    background: var(--bg-0);
    border: 1px solid var(--border);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
    transition: border-color 0.2s, background 0.2s;
    flex-shrink: 0;
    color: var(--text-2);
  }
  .pv-btn-wish:hover { border-color: #e8192c; color: #e8192c; }
  .pv-btn-wish.active { color: #e8192c; border-color: #e8192c; background: rgba(232,25,44,0.06); }

  /* Trust badges */
  .pv-trust {
    display: flex;
    gap: 1.25rem;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--border);
  }

  .pv-trust-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    text-align: center;
  }

  .pv-trust-icon { font-size: 1.3rem; }

  .pv-trust-text {
    font-size: 0.7rem;
    color: var(--text-2);
    line-height: 1.3;
    font-weight: 500;
  }

  /* ── Related products ── */
  .pv-related { margin-top: 5rem; }

  .pv-related-title {
    font-family: var(--font-display, 'Oswald', sans-serif);
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text-0, #fff);
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
  }

  .pv-related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.25rem;
  }

  .pv-related-card {
    background: var(--bg-1);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    display: block;
    transition: border-color 0.2s, transform 0.2s;
  }
  .pv-related-card:hover { border-color: var(--gold); transform: translateY(-3px); }

  .pv-related-img {
    aspect-ratio: 1;
    background: var(--bg-0);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
  }
  .pv-related-img img { width: 100%; height: 100%; object-fit: contain; padding: 1rem; }
  .pv-related-img span { font-size: 3rem; opacity: 0.2; }

  .pv-related-info { padding: 1rem; }

  .pv-related-name {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-1);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .pv-related-price {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--gold);
  }

  /* Page-load animation */
  @keyframes pvFadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .pv-img-col  { animation: pvFadeUp 0.55s ease both; }
  .pv-details  { animation: pvFadeUp 0.55s ease 0.1s both; }
  .pv-related  { animation: pvFadeUp 0.55s ease 0.2s both; }
</style>

<div class="pv-page">
  <div class="container">

    <!-- Breadcrumb -->
    <nav class="pv-breadcrumb" aria-label="breadcrumb">
      <a href="shop.php">Shop</a>
      <span class="sep">›</span>
      <a href="shop.php?category=<?= urlencode($p['category']) ?>"><?= h($p['category']) ?></a>
      <span class="sep">›</span>
      <span class="current"><?= h($p['product_name']) ?></span>
    </nav>

    <!-- Main grid -->
    <div class="pv-grid">

      <!-- Left: Image -->
      <div class="pv-img-col">
        <div class="pv-img-main">
          <?php if ($p['image_url']): ?>
            <img src="<?= h($p['image_url']) ?>" alt="<?= h($p['product_name']) ?>">
          <?php else: ?>
            <span class="pv-img-placeholder"><svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
          <?php endif; ?>

          <span class="pv-stock-badge <?= $p['stock_qty'] > 0 ? 'in' : 'out' ?>">
            <?= $p['stock_qty'] > 0 ? 'In Stock' : 'Out of Stock' ?>
          </span>
        </div>
      </div>

      <!-- Right: Details -->
      <div class="pv-details">
        <span class="pv-category"><?= h($p['category']) ?></span>
        <h1 class="pv-name"><?= h($p['product_name']) ?></h1>
        <div class="pv-price"><?= format_money($p['price']) ?></div>

        <div class="pv-desc">
          <?= nl2br(h($p['description'])) ?>
        </div>

        <!-- Specs -->
        <div class="pv-specs">
          <div class="pv-spec">
            <div class="pv-spec-label">Category</div>
            <div class="pv-spec-value"><?= h($p['category']) ?></div>
          </div>
          <div class="pv-spec">
            <div class="pv-spec-label">Stock</div>
            <div class="pv-spec-value">
              <?= $p['stock_qty'] > 0 ? $p['stock_qty'] . ' units' : 'Unavailable' ?>
            </div>
          </div>
          <div class="pv-spec">
            <div class="pv-spec-label">SKU</div>
            <div class="pv-spec-value"><?= h('RMI-' . str_pad($p['product_id'], 4, '0', STR_PAD_LEFT)) ?></div>
          </div>
          <div class="pv-spec">
            <div class="pv-spec-label">Condition</div>
            <div class="pv-spec-value"><?= h($p['condition'] ?? 'New') ?></div>
          </div>
        </div>

        <!-- Purchase box -->
        <div class="pv-purchase">
          <div class="pv-avail-row">
            <span class="pv-avail-label">Availability</span>
            <?php if ($p['stock_qty'] > 0): ?>
              <span class="pv-avail-status">
                <span class="pv-avail-dot" style="background:var(--success,#22c55e)"></span>
                <span style="color:var(--success,#22c55e)"><?= $p['stock_qty'] ?> units ready to ship</span>
              </span>
            <?php else: ?>
              <span class="pv-avail-status">
                <span class="pv-avail-dot" style="background:var(--danger,#ef4444)"></span>
                <span style="color:var(--danger,#ef4444)">Out of Stock</span>
              </span>
            <?php endif; ?>
          </div>

          <?php if ($p['stock_qty'] > 0): ?>
          <div class="pv-qty-row">
            <span class="pv-qty-label">Qty</span>
            <div class="pv-qty-ctrl">
              <button class="pv-qty-btn" id="qty-minus" aria-label="Decrease quantity">−</button>
              <span class="pv-qty-num" id="qty-display">1</span>
              <button class="pv-qty-btn" id="qty-plus" aria-label="Increase quantity">+</button>
            </div>
          </div>
          <?php endif; ?>

          <div class="pv-btn-group">
            <button
              class="pv-btn-cart"
              id="add-to-cart-btn"
              <?= $p['stock_qty'] <= 0 ? 'disabled' : '' ?>
              data-id="<?= h($p['product_id']) ?>"
            >
              <?php if ($p['stock_qty'] > 0): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:.4rem;vertical-align:middle"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>Add to Cart
              <?php else: ?>Out of Stock<?php endif; ?>
              </div>
            </button>

          <div class="pv-trust">
            <div class="pv-trust-item">
              <span class="pv-trust-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
              <span class="pv-trust-text">Free Delivery</span>
            </div>
            <div class="pv-trust-item">
              <span class="pv-trust-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></span>
              <span class="pv-trust-text">Easy Returns</span>
            </div>
            <div class="pv-trust-item">
              <span class="pv-trust-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
              <span class="pv-trust-text">Secure Checkout</span>
            </div>
            <div class="pv-trust-item">
              <span class="pv-trust-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m9 9-2 2a4.5 4.5 0 1 0 6 6l2-2"/><path d="M13 6l3-3 3 3-3 3z"/><path d="m10 10 4-4"/><path d="m6 20 3-3"/></svg></span>
              <span class="pv-trust-text">Expert Support</span>
            </div>
          </div>
        </div>

      </div><!-- /details -->
    </div><!-- /grid -->

    <!-- Related products -->
    <?php if (!empty($related)): ?>
    <section class="pv-related">
      <h2 class="pv-related-title">More in <?= h($p['category']) ?></h2>
      <div class="pv-related-grid">
        <?php foreach ($related as $r): ?>
          <a href="product-view.php?id=<?= $r['product_id'] ?>" class="pv-related-card">
            <div class="pv-related-img">
              <?php if ($r['image_url']): ?>
                <img src="<?= h($r['image_url']) ?>" alt="<?= h($r['product_name']) ?>">
              <?php else: ?>
                <span><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
              <?php endif; ?>
            </div>
            <div class="pv-related-info">
              <div class="pv-related-name"><?= h($r['product_name']) ?></div>
              <div class="pv-related-price"><?= format_money($r['price']) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </div><!-- /container -->
</div><!-- /pv-page -->

<script>
(function () {
  // Quantity control
  let qty = 1;
  const maxQty  = <?= (int)$p['stock_qty'] ?>;
  const display = document.getElementById('qty-display');
  const minus   = document.getElementById('qty-minus');
  const plus    = document.getElementById('qty-plus');

  function updateQty(n) {
    qty = Math.max(1, Math.min(n, maxQty));
    if (display) display.textContent = qty;
    if (minus)   minus.disabled = qty <= 1;
    if (plus)    plus.disabled  = qty >= maxQty;
  }

  if (minus) minus.addEventListener('click', () => updateQty(qty - 1));
  if (plus)  plus.addEventListener('click',  () => updateQty(qty + 1));
  updateQty(1);

  // Add to cart
  const cartBtn   = document.getElementById('add-to-cart-btn');
  const productId = <?= (int)$p['product_id'] ?>;
  // Seed from session so merges are accurate
  let sessionCart = <?= json_encode((object)($_SESSION['cart'] ?? [])) ?>;

  if (cartBtn && maxQty > 0) {
    cartBtn.addEventListener('click', async function () {
      this.disabled = true;
      const originalHTML = this.innerHTML;

      try {
        const existing = parseInt(sessionCart[productId] ?? 0, 10);
        sessionCart[productId] = Math.min(existing + qty, maxQty);

        const res = await fetch('cart_update.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(sessionCart),
        });

        if (res.ok) {
          // Fix 3: Safely parse JSON — cart_update.php may return empty or non-JSON on errors
          let data = {};
          try { data = await res.json(); } catch (_) { /* non-JSON body — continue anyway */ }

          this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:.4rem;vertical-align:middle"><polyline points="20 6 9 17 4 12"/></svg>Added! Redirecting…';
          this.style.background = 'var(--success, #22c55e)';
          this.style.color = '#fff';

          // Update any cart count badge in the header
          document.querySelectorAll('.cart-count, .cart-badge, [data-cart-count], #heroCartCount').forEach(el => {
            if (data.count !== undefined) el.textContent = data.count;
          });

          setTimeout(() => {
            window.location.href = 'cart.php';
          }, 900);
        } else {
          throw new Error('Save failed');
        }
      } catch (err) {
        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:.4rem;vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Error – try again';
        this.style.background = 'var(--danger, #ef4444)';
        this.style.color = '#fff';
        setTimeout(() => {
          this.innerHTML = originalHTML;
          this.style.background = '';
          this.style.color = '';
          this.disabled = false;
        }, 2000);
      }
    });
  }

  // Wishlist toggle
  const wishBtn  = document.getElementById('wishlist-btn');
  const wishIcon = document.getElementById('wish-icon');
  let wishlisted = false;

  if (wishBtn) {
    wishBtn.addEventListener('click', async function () {
      wishlisted = !wishlisted;
      // Update icon fill immediately for instant feedback
      if (wishIcon) {
        wishIcon.setAttribute('fill', wishlisted ? '#e8192c' : 'none');
        wishIcon.setAttribute('stroke', wishlisted ? '#e8192c' : 'currentColor');
      }
      this.classList.toggle('active', wishlisted);

      // Persist to server
      try {
        await fetch('wishlist_update.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ product_id: productId, action: wishlisted ? 'add' : 'remove' }),
        });
      } catch (_) {
        // silently fail — UI state already toggled
      }
    });
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>