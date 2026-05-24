<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['staff', 'admin']);
$db = get_db();

// Stats
$total_sales   = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn();
$total_orders  = $db->query("SELECT COUNT(*) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn();
$low_stock     = $db->query("SELECT COUNT(*) FROM products WHERE stock_qty <= 3")->fetchColumn();
$pending_svc   = $db->query("SELECT COUNT(*) FROM service_requests WHERE status='Pending'")->fetchColumn();
$pending_appts = $db->query("SELECT COUNT(*) FROM appointments WHERE status='Pending'")->fetchColumn();
$total_products= $db->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Recent sales
$recent_sales = $db->query(
    "SELECT s.sale_id, s.total_amount, s.payment_method, s.sale_date,
            u.full_name AS customer_name
     FROM sales s
     LEFT JOIN users u ON s.customer_id = u.user_id
     ORDER BY s.sale_date DESC LIMIT 8"
)->fetchAll();

// Low stock products
$low_stock_products = $db->query(
    "SELECT product_name, category, stock_qty FROM products WHERE stock_qty <= 5 ORDER BY stock_qty ASC LIMIT 6"
)->fetchAll();

// Upcoming appointments
$upcoming = $db->query(
    "SELECT a.appt_date, a.appt_time, a.appt_type, a.status,
            u.full_name AS customer_name
     FROM appointments a
     LEFT JOIN users u ON a.customer_id = u.user_id
     WHERE a.appt_date >= CURDATE() AND a.status IN ('Pending','Confirmed')
     ORDER BY a.appt_date, a.appt_time LIMIT 5"
)->fetchAll();

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="page-header">
    <div>
      <h1>Dashboard</h1>
      <p class="page-subtitle">Welcome back, <?= h(explode(' ', $user['name'])[0]) ?> — <?= date('l, F j, Y') ?></p>
    </div>
    <?php if ($role === 'admin'): ?>
      <a href="<?= BASE_URL ?>/customers.php" class="btn btn-secondary">Manage Users</a>
    <?php endif; ?>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Today's Revenue</div>
      <div class="stat-value"><?= format_money($total_sales) ?></div>
      <div style="font-size:.78rem;color:var(--text-2);margin-top:.4rem"><?= $total_orders ?> sale(s) today</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending Services</div>
      <div class="stat-value" style="color:var(--gold-light)"><?= $pending_svc ?></div>
      <div style="font-size:.78rem;color:var(--text-2);margin-top:.4rem"><a href="<?= BASE_URL ?>/service.php">View all →</a></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending Appointments</div>
      <div class="stat-value" style="color:var(--blue)"><?= $pending_appts ?></div>
      <div style="font-size:.78rem;color:var(--text-2);margin-top:.4rem"><a href="<?= BASE_URL ?>/appointments.php">View all →</a></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Low Stock Items</div>
      <div class="stat-value" style="color:<?= $low_stock > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= $low_stock ?></div>
      <div style="font-size:.78rem;color:var(--text-2);margin-top:.4rem"><a href="<?= BASE_URL ?>/inventory.php">View inventory →</a></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Products</div>
      <div class="stat-value"><?= $total_products ?></div>
      <div style="font-size:.78rem;color:var(--text-2);margin-top:.4rem"><a href="<?= BASE_URL ?>/inventory.php">Manage →</a></div>
    </div>
  </div>

  <!-- Main grid -->
  <div class="dashboard-grid">
    <!-- Recent Sales -->
    <div class="card">
      <div class="card-header">
        <h3>Recent Sales</h3>
        <a href="<?= BASE_URL ?>/pos.php" class="btn btn-sm btn-primary">+ New Sale</a>
      </div>
      <div style="overflow-x:auto">
        <table class="table">
          <thead>
            <tr>
              <th>#</th><th>Customer</th><th>Payment</th><th>Amount</th><th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_sales)): ?>
              <tr><td colspan="5" class="text-center" style="color:var(--text-2);padding:2rem">No sales today.</td></tr>
            <?php else: foreach ($recent_sales as $s): ?>
              <tr>
                <td>#<?= $s['sale_id'] ?></td>
                <td><?= $s['customer_name'] ? h($s['customer_name']) : '<span style="color:var(--text-2)">Walk-in</span>' ?></td>
                <td><span class="badge badge-info"><?= h($s['payment_method']) ?></span></td>
                <td style="color:var(--gold);font-family:var(--font-display)"><?= format_money($s['total_amount']) ?></td>
                <td style="color:var(--text-2);font-size:.8rem"><?= date('h:i A', strtotime($s['sale_date'])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Right column -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">
      <!-- Low Stock -->
      <div class="card">
        <div class="card-header">
          <h3>Low Stock Alert</h3>
          <a href="<?= BASE_URL ?>/inventory.php" class="btn btn-sm btn-ghost">View All</a>
        </div>
        <div class="card-body" style="padding:.75rem">
          <?php if (empty($low_stock_products)): ?>
            <p style="color:var(--green);text-align:center;padding:1rem;margin:0">All items well stocked ✓</p>
          <?php else: foreach ($low_stock_products as $p): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem .5rem;border-bottom:1px solid var(--border)">
              <div>
                <div style="font-size:.875rem;font-weight:500"><?= h($p['product_name']) ?></div>
                <div style="font-size:.75rem;color:var(--text-2)"><?= h($p['category']) ?></div>
              </div>
              <span class="badge <?= $p['stock_qty'] == 0 ? 'badge-danger' : 'badge-warning' ?>">
                <?= $p['stock_qty'] ?> left
              </span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Upcoming Appointments -->
      <div class="card">
        <div class="card-header">
          <h3>Upcoming Appointments</h3>
          <a href="<?= BASE_URL ?>/appointments.php" class="btn btn-sm btn-ghost">View All</a>
        </div>
        <div class="card-body" style="padding:.75rem">
          <?php if (empty($upcoming)): ?>
            <p style="color:var(--text-2);text-align:center;padding:1rem;margin:0">No upcoming appointments.</p>
          <?php else: foreach ($upcoming as $a): ?>
            <div style="padding:.65rem .5rem;border-bottom:1px solid var(--border)">
              <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                  <div style="font-size:.875rem;font-weight:500"><?= h($a['customer_name'] ?? 'Unknown') ?></div>
                  <div style="font-size:.75rem;color:var(--text-2)"><?= h($a['appt_type']) ?> · <?= date('M j', strtotime($a['appt_date'])) ?> at <?= date('h:i A', strtotime($a['appt_time'])) ?></div>
                </div>
                <span class="badge badge-<?= $a['status'] === 'Confirmed' ? 'success' : 'warning' ?>"><?= h($a['status']) ?></span>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>