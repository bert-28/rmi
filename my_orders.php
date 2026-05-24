<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_role(['customer']);

$db   = get_db();
$user = current_user();

// Fetch all sales for this customer with their items
$stmt = $db->prepare(
    'SELECT s.*, u.full_name AS staff_name
     FROM sales s
     LEFT JOIN users u ON u.user_id = s.staff_id
     WHERE s.customer_id = ?
     ORDER BY s.sale_date DESC'
);
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

// Fetch line items for all those orders in one query
$order_ids = array_column($orders, 'sale_id');
$items_by_sale = [];
if ($order_ids) {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $istmt = $db->prepare(
        "SELECT si.*, p.product_name, p.category
         FROM sale_items si
         JOIN products p ON p.product_id = si.product_id
         WHERE si.sale_id IN ($placeholders)
         ORDER BY si.item_id ASC"
    );
    $istmt->execute($order_ids);
    foreach ($istmt->fetchAll() as $item) {
        $items_by_sale[$item['sale_id']][] = $item;
    }
}

$payment_icons = [
    'Cash'          => '💵',
    'GCash'         => '📱',
    'Card'          => '💳',
    'Bank Transfer' => '🏦',
];

$page_title = 'My Orders';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<div style="background:linear-gradient(135deg,var(--bg-1),var(--bg-0));border-bottom:1px solid var(--border);padding:3rem 1.5rem 2rem">
  <div style="max-width:1280px;margin:0 auto">
    <div style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--gold);margin-bottom:.5rem">History</div>
    <h1 style="font-size:clamp(2rem,5vw,3.5rem);margin-bottom:.5rem">🧾 My <span style="color:var(--gold)">Orders</span></h1>
    <p style="color:var(--text-2);margin:0">Your purchase history at Resurrection Musical Instruments</p>
  </div>
</div>

<div class="container" style="padding-top:2rem">

  <?php if (empty($orders)): ?>
    <div class="empty-state">
      <div class="icon">🛒</div>
      <p>You haven't placed any orders yet.</p>
      <a href="<?= BASE_URL ?>/shop.php" class="btn btn-primary" style="margin-top:1rem">Browse the Shop</a>
    </div>
  <?php else: ?>

    <!-- Summary strip -->
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:2rem">
      <?php
        $total_spent = array_sum(array_column($orders, 'total_amount'));
        $order_count = count($orders);
      ?>
      <div class="stat-card" style="flex:1;min-width:160px">
        <div class="stat-label">Total Orders</div>
        <div class="stat-value"><?= $order_count ?></div>
      </div>
      <div class="stat-card" style="flex:1;min-width:160px">
        <div class="stat-label">Total Spent</div>
        <div class="stat-value" style="color:var(--gold)"><?= format_money($total_spent) ?></div>
      </div>
      <div class="stat-card" style="flex:1;min-width:160px">
        <div class="stat-label">Last Order</div>
        <div class="stat-value" style="font-size:1rem"><?= date('M j, Y', strtotime($orders[0]['sale_date'])) ?></div>
      </div>
    </div>

    <!-- Order cards -->
    <?php foreach ($orders as $order):
      $items = $items_by_sale[$order['sale_id']] ?? [];
    ?>
    <div class="card" style="margin-bottom:1.5rem">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
        <div>
          <span style="font-weight:700;font-size:.95rem">Order #<?= str_pad($order['sale_id'], 5, '0', STR_PAD_LEFT) ?></span>
          <span style="color:var(--text-2);font-size:.82rem;margin-left:.75rem">
            <?= date('F j, Y — g:i A', strtotime($order['sale_date'])) ?>
          </span>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem">
          <span style="font-size:.85rem">
            <?= $payment_icons[$order['payment_method']] ?? '💳' ?> <?= h($order['payment_method']) ?>
          </span>
          <span style="font-weight:700;color:var(--gold);font-size:1.05rem"><?= format_money($order['total_amount']) ?></span>
        </div>
      </div>

      <div class="card-body" style="padding:0">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:var(--bg-1);border-bottom:1px solid var(--border)">
              <th style="padding:.6rem 1rem;text-align:left;font-size:.8rem;color:var(--text-2);font-weight:600">Product</th>
              <th style="padding:.6rem 1rem;text-align:center;font-size:.8rem;color:var(--text-2);font-weight:600">Qty</th>
              <th style="padding:.6rem 1rem;text-align:right;font-size:.8rem;color:var(--text-2);font-weight:600">Unit Price</th>
              <th style="padding:.6rem 1rem;text-align:right;font-size:.8rem;color:var(--text-2);font-weight:600">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:.65rem 1rem">
                <span style="font-weight:500"><?= h($item['product_name']) ?></span>
                <small style="color:var(--text-2);display:block"><?= h($item['category']) ?></small>
              </td>
              <td style="padding:.65rem 1rem;text-align:center"><?= $item['quantity'] ?></td>
              <td style="padding:.65rem 1rem;text-align:right;color:var(--text-2)"><?= format_money($item['unit_price']) ?></td>
              <td style="padding:.65rem 1rem;text-align:right;font-weight:600"><?= format_money($item['subtotal']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--bg-1)">
              <td colspan="3" style="padding:.65rem 1rem;text-align:right;font-weight:700">Total</td>
              <td style="padding:.65rem 1rem;text-align:right;font-weight:700;color:var(--gold)"><?= format_money($order['total_amount']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <?php if ($order['notes'] || $order['staff_name']): ?>
      <div style="padding:.65rem 1rem;background:var(--bg-0);border-top:1px solid var(--border);font-size:.82rem;color:var(--text-2);display:flex;gap:1.5rem;flex-wrap:wrap">
        <?php if ($order['staff_name']): ?>
          <span>👤 Served by <?= h($order['staff_name']) ?></span>
        <?php endif; ?>
        <?php if ($order['notes']): ?>
          <span>📝 <?= h($order['notes']) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
