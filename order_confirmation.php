<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
require_role(['customer']);

$db   = get_db();
$user = current_user();

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    header('Location: shop.php');
    exit;
}

// Fetch order — must belong to this user
$stmt = $db->prepare("SELECT * FROM sales WHERE sale_id = ? AND customer_id = ? LIMIT 1");
$stmt->execute([$order_id, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: shop.php');
    exit;
}

// Fetch order items
$stmt = $db->prepare("
    SELECT si.*, p.product_name, p.category, p.image_url
    FROM sale_items si
    JOIN products p ON p.product_id = si.product_id
    WHERE si.sale_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

$flash      = flash('success');
$page_title = 'Order Confirmed';
require_once __DIR__ . '/includes/header.php';

$cat_icons = ['Guitar'=>'🎸','Bass'=>'🎵','Amplifier'=>'🔊','Strings'=>'〰️','Pedal'=>'🎛️','Accessory'=>'🎼','Other'=>'📦'];
?>

<style>
  .oc-wrap {
    max-width: 680px;
    margin: 0 auto;
    padding: 3rem 1.5rem 5rem;
  }

  /* Success banner */
  .oc-banner {
    text-align: center;
    padding: 2.5rem 2rem 2rem;
    background: var(--bg-1);
    border: 1px solid var(--border);
    border-top: 4px solid var(--success, #22c55e);
    border-radius: 0;
    margin-bottom: 2rem;
    animation: ocFadeUp 0.5s ease both;
  }

  .oc-checkmark {
    width: 60px; height: 60px;
    background: rgba(34,197,94,0.12);
    border: 2px solid var(--success, #22c55e);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    margin: 0 auto 1rem;
  }

  .oc-banner h1 {
    font-family: var(--font-display);
    font-size: 1.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 0 0 0.4rem;
  }

  .oc-banner p {
    font-size: 0.9rem;
    color: var(--text-2);
  }

  .oc-order-num {
    display: inline-block;
    margin-top: 0.75rem;
    padding: 0.35rem 1rem;
    background: var(--bg-0);
    border: 1px solid var(--border);
    font-family: var(--font-cond);
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-2);
  }

  /* Details card */
  .oc-card {
    background: var(--bg-1);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    animation: ocFadeUp 0.5s ease 0.1s both;
    opacity: 0;
  }

  .oc-card-head {
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--border);
    font-family: var(--font-cond);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-2);
  }

  .oc-card-body { padding: 1.25rem; }

  /* Meta rows */
  .oc-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  .oc-meta-item { }
  .oc-meta-label {
    font-family: var(--font-cond);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-2);
    margin-bottom: 0.2rem;
  }
  .oc-meta-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-0);
  }

  /* Order items */
  .oc-item-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 0;
    border-bottom: 1px solid var(--border);
  }
  .oc-item-row:last-child { border-bottom: none; padding-bottom: 0; }

  .oc-item-icon { font-size: 1.6rem; flex-shrink: 0; }

  .oc-item-info { flex: 1; }
  .oc-item-name {
    font-family: var(--font-cond);
    font-size: 0.88rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-0);
  }
  .oc-item-qty {
    font-size: 0.78rem;
    color: var(--text-2);
    margin-top: 2px;
  }

  .oc-item-sub {
    font-family: var(--font-display);
    font-size: 1rem;
    color: var(--red);
    flex-shrink: 0;
  }

  /* Total row */
  .oc-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: var(--text-0);
    color: #fff;
    margin-top: 0;
  }
  .oc-total-label {
    font-family: var(--font-display);
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }
  .oc-total-amount {
    font-family: var(--font-display);
    font-size: 1.7rem;
  }

  /* Status badge */
  .oc-status {
    display: inline-block;
    padding: 3px 12px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    border-radius: 2px;
  }
  .oc-status.pending  { background: rgba(234,179,8,0.12);  color: #ca8a04; border: 1px solid rgba(234,179,8,0.25); }
  .oc-status.paid     { background: rgba(34,197,94,0.12); color: var(--success,#22c55e); border: 1px solid rgba(34,197,94,0.25); }
  .oc-status.cancelled{ background: rgba(239,68,68,0.12); color: var(--danger,#ef4444); border: 1px solid rgba(239,68,68,0.25); }

  /* Actions */
  .oc-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    animation: ocFadeUp 0.5s ease 0.2s both;
    opacity: 0;
  }
  .oc-actions .btn { flex: 1; text-align: center; }

  /* Notice */
  .oc-notice {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 1rem 1.25rem;
    background: rgba(201,149,42,0.06);
    border: 1px solid rgba(201,149,42,0.2);
    border-left: 3px solid var(--gold);
    font-size: 0.85rem;
    color: var(--text-2);
    line-height: 1.6;
    margin-bottom: 1.5rem;
    animation: ocFadeUp 0.5s ease 0.15s both;
    opacity: 0;
  }
  .oc-notice-icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }

  @keyframes ocFadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .oc-banner { animation: ocFadeUp 0.5s ease both; }
</style>

<div class="oc-wrap">

  <!-- Success banner -->
  <div class="oc-banner">
    <div class="oc-checkmark">✓</div>
    <h1>Order Confirmed!</h1>
    <p>Thank you, <?= h(explode(' ', $user['name'])[0] ?? 'valued customer') ?>. Your order has been received.</p>
    <span class="oc-order-num">Order #<?= str_pad($order['sale_id'], 5, '0', STR_PAD_LEFT) ?></span>
  </div>

  <!-- Pickup notice -->
  <div class="oc-notice">
    <span class="oc-notice-icon">🏪</span>
    <span>Your order is prepared for <strong>in-store pickup</strong>. We'll have it ready shortly — please bring this order number when you visit.</span>
  </div>

  <!-- Order meta -->
  <div class="oc-card">
    <div class="oc-card-head">Order Details</div>
    <div class="oc-card-body">
      <div class="oc-meta-grid">
        <div class="oc-meta-item">
          <div class="oc-meta-label">Date Placed</div>
          <div class="oc-meta-value"><?= date('M j, Y · g:i A', strtotime($order['sale_date'])) ?></div>
        </div>
        <div class="oc-meta-item">
          <div class="oc-meta-label">Status</div>
          <div class="oc-meta-value">
            <span class="oc-status pending">
              Pending
            </span>
          </div>
        </div>
        <div class="oc-meta-item">
          <div class="oc-meta-label">Payment Method</div>
          <div class="oc-meta-value"><?= h($order['payment_method']) ?></div>
        </div>
        <div class="oc-meta-item">
          <div class="oc-meta-label">Fulfillment</div>
          <div class="oc-meta-value">In-store Pickup</div>
        </div>
        <?php if ($order['notes']): ?>
        <div class="oc-meta-item" style="grid-column: span 2">
          <div class="oc-meta-label">Notes</div>
          <div class="oc-meta-value"><?= h($order['notes']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Order items -->
  <div class="oc-card">
    <div class="oc-card-head">Items Ordered</div>
    <div class="oc-card-body">
      <?php foreach ($items as $item):
        $icon = $cat_icons[$item['category']] ?? '📦';
      ?>
        <div class="oc-item-row">
          <span class="oc-item-icon"><?= $icon ?></span>
          <div class="oc-item-info">
            <div class="oc-item-name"><?= h($item['product_name']) ?></div>
            <div class="oc-item-qty">Qty: <?= $item['quantity'] ?> × ₱<?= number_format($item['unit_price'], 2) ?></div>
          </div>
          <span class="oc-item-sub">₱<?= number_format($item['subtotal'], 2) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="oc-total-row">
      <span class="oc-total-label">Total</span>
      <span class="oc-total-amount">₱<?= number_format($order['total_amount'], 2) ?></span>
    </div>
  </div>

  <!-- Actions -->
  <div class="oc-actions">
    <a href="shop.php" class="btn btn-ghost">← Continue Shopping</a>
    <a href="my_orders.php" class="btn btn-primary">View My Orders</a>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>