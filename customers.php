<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_role(['staff', 'admin']);
$db = get_db();

// ── Actions ──────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$error  = '';
$success = '';

// Add customer
if ($action === 'add') {
    if (empty($_POST['full_name']) || empty($_POST['email'])) {
        $error = 'Name and email are required.';
    } else {
        $chk = $db->prepare('SELECT user_id FROM users WHERE email = ?');
        $chk->execute([$_POST['email']]);
        if ($chk->fetch()) {
            $error = 'Email already registered.';
        } else {
            $pass = !empty($_POST['password']) ? $_POST['password'] : bin2hex(random_bytes(6));
            $db->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, contact, address)
                 VALUES (?, ?, ?, "customer", ?, ?)'
            )->execute([
                trim($_POST['full_name']),
                trim($_POST['email']),
                password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]),
                trim($_POST['contact'] ?? ''),
                trim($_POST['address'] ?? ''),
            ]);
            flash('success', 'Customer added successfully.');
            header('Location: customers.php'); exit;
        }
    }
}

// Edit customer
if ($action === 'edit') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid && !empty($_POST['full_name'])) {
        $db->prepare(
            'UPDATE users SET full_name=?, email=?, contact=?, address=? WHERE user_id=? AND role="customer"'
        )->execute([
            trim($_POST['full_name']),
            trim($_POST['email']),
            trim($_POST['contact'] ?? ''),
            trim($_POST['address'] ?? ''),
            $uid,
        ]);
        if (!empty($_POST['password'])) {
            $db->prepare('UPDATE users SET password_hash=? WHERE user_id=?')
               ->execute([password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost'=>12]), $uid]);
        }
        flash('success', 'Customer updated.');
        header('Location: customers.php'); exit;
    }
}

// Delete customer
if ($action === 'delete') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid) {
        $db->prepare('DELETE FROM users WHERE user_id=? AND role="customer"')->execute([$uid]);
        flash('success', 'Customer removed.');
        header('Location: customers.php'); exit;
    }
}

// ── Fetch customers with stats ────────────────────────────────
$search = trim($_GET['q'] ?? '');
$sort   = $_GET['sort'] ?? 'name';

$where  = "WHERE role = 'customer'";
$params = [];
if ($search) {
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR contact LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$order = match($sort) {
    'spent'    => 'ORDER BY total_spent DESC',
    'orders'   => 'ORDER BY total_orders DESC',
    'recent'   => 'ORDER BY last_purchase DESC',
    'newest'   => 'ORDER BY u.created_at DESC',
    default    => 'ORDER BY u.full_name ASC',
};

$customers = $db->prepare(
    "SELECT u.*,
            COUNT(DISTINCT s.sale_id)             AS total_orders,
            COALESCE(SUM(s.total_amount), 0)      AS total_spent,
            MAX(s.sale_date)                      AS last_purchase,
            COUNT(DISTINCT sr.service_id)         AS total_services,
            COUNT(DISTINCT a.appt_id)             AS total_appts
     FROM users u
     LEFT JOIN sales s           ON s.customer_id = u.user_id
     LEFT JOIN service_requests sr ON sr.customer_id = u.user_id
     LEFT JOIN appointments a    ON a.customer_id = u.user_id
     $where
     GROUP BY u.user_id
     $order"
);
$customers->execute($params);
$customers = $customers->fetchAll();

// Stats summary
$totals = $db->query(
    "SELECT COUNT(*) as cnt,
            COALESCE(SUM(s.total_amount),0) as revenue
     FROM users u
     LEFT JOIN sales s ON s.customer_id = u.user_id
     WHERE u.role='customer'"
)->fetch();

// Single customer detail (for modal)
$detail_id = (int)($_GET['view'] ?? 0);
$detail = null;
$detail_sales = [];
if ($detail_id) {
    $detail = $db->prepare("SELECT * FROM users WHERE user_id=? AND role='customer'")->execute([$detail_id]) ? null : null;
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id=? AND role='customer'");
    $stmt->execute([$detail_id]);
    $detail = $stmt->fetch();
    if ($detail) {
        $detail_sales = $db->prepare(
            "SELECT s.*, GROUP_CONCAT(p.product_name SEPARATOR ', ') as items
             FROM sales s
             LEFT JOIN sale_items si ON si.sale_id = s.sale_id
             LEFT JOIN products p   ON p.product_id = si.product_id
             WHERE s.customer_id = ?
             GROUP BY s.sale_id
             ORDER BY s.sale_date DESC LIMIT 10"
        )->execute([$detail_id]) ? [] : [];
        $st = $db->prepare(
            "SELECT s.*, GROUP_CONCAT(p.product_name SEPARATOR ', ') as items
             FROM sales s
             LEFT JOIN sale_items si ON si.sale_id = s.sale_id
             LEFT JOIN products p   ON p.product_id = si.product_id
             WHERE s.customer_id = ?
             GROUP BY s.sale_id
             ORDER BY s.sale_date DESC LIMIT 10"
        );
        $st->execute([$detail_id]);
        $detail_sales = $st->fetchAll();
    }
}

$flash   = flash('success');
$page_title = 'Customers';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ── Page layout ── */
.cust-wrap { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem; }

/* ── Summary cards ── */
.cust-stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1px;
  background: var(--border);
  border: 1px solid var(--border);
  margin-bottom: 2rem;
}
.cust-stat {
  background: var(--bg-0);
  padding: 1.5rem;
  transition: background .15s;
}
.cust-stat:hover { background: var(--bg-1); }
.cust-stat .cs-label {
  font-family: var(--font-cond);
  font-size: .7rem; font-weight: 700;
  letter-spacing: .15em; text-transform: uppercase;
  color: var(--text-2); margin-bottom: .4rem;
}
.cust-stat .cs-val {
  font-family: var(--font-display);
  font-size: 2.2rem; color: var(--text-0); line-height: 1;
}
.cust-stat .cs-val.red { color: var(--red); }

/* ── Toolbar ── */
.cust-toolbar {
  display: flex; align-items: center; gap: .75rem;
  flex-wrap: wrap; margin-bottom: 1.5rem;
}
.cust-search {
  position: relative; flex: 1; max-width: 320px;
}
.cust-search input {
  width: 100%; padding: .65rem 1rem .65rem 2.5rem;
  border: 1.5px solid var(--border); border-radius: 0;
  font-family: var(--font-body); font-size: .9rem;
  outline: none; transition: border-color .2s;
  background: var(--bg-0); color: var(--text-0);
}
.cust-search input:focus { border-color: var(--text-0); }
.cust-search::before {
  content: '⌕'; position: absolute; left: .75rem; top: 50%;
  transform: translateY(-50%); font-size: 1.1rem;
  color: var(--text-2); pointer-events: none;
}
.sort-select {
  padding: .65rem 1rem; border: 1.5px solid var(--border);
  border-radius: 0; font-family: var(--font-cond);
  font-size: .8rem; font-weight: 700; letter-spacing: .08em;
  text-transform: uppercase; background: var(--bg-0);
  color: var(--text-0); outline: none; cursor: pointer;
}

/* ── Table ── */
.cust-table-wrap { overflow-x: auto; border: 1px solid var(--border); }
.cust-table {
  width: 100%; border-collapse: collapse; font-size: .875rem;
}
.cust-table thead th {
  font-family: var(--font-cond); font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .12em; color: var(--text-2);
  padding: .85rem 1rem; text-align: left;
  border-bottom: 2px solid var(--text-0);
  background: var(--bg-1); white-space: nowrap;
}
.cust-table thead th a {
  color: inherit; display: flex; align-items: center; gap: .3rem;
}
.cust-table thead th a:hover { color: var(--red); }
.cust-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.cust-table tbody tr:hover { background: var(--bg-1); }
.cust-table tbody td { padding: .85rem 1rem; vertical-align: middle; }

.cust-avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: var(--bg-2); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display); font-size: .9rem;
  color: var(--text-0); flex-shrink: 0;
  text-transform: uppercase;
}
.cust-name-cell { display: flex; align-items: center; gap: .75rem; }
.cust-name { font-weight: 600; color: var(--text-0); }
.cust-email { font-size: .78rem; color: var(--text-2); }
.spent-val { font-family: var(--font-display); font-size: 1.1rem; color: var(--red); }
.zero-val { color: var(--text-2); }

.action-btns { display: flex; gap: .4rem; }
.icon-btn {
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--border); background: var(--bg-1);
  cursor: pointer; font-size: .85rem; transition: all .15s;
  border-radius: 0;
}
.icon-btn:hover { background: var(--text-0); color: #fff; border-color: var(--text-0); }
.icon-btn.danger:hover { background: var(--red); border-color: var(--red); }

/* ── Modal shared ── */
.modal-backdrop {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.55); z-index: 300;
  align-items: center; justify-content: center; padding: 1rem;
}
.modal-backdrop.open { display: flex; }
.modal-box {
  background: var(--bg-0); width: 100%; max-height: 90vh;
  overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.3);
  animation: mSlide .2s ease;
  border-top: 3px solid var(--text-0);
}
@keyframes mSlide {
  from { opacity:0; transform:translateY(14px); }
  to   { opacity:1; transform:translateY(0); }
}
.modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border);
}
.modal-head h3 { font-size: 1.4rem; margin: 0; }
.modal-close-btn {
  background: none; border: none; font-size: 1.3rem;
  cursor: pointer; color: var(--text-2); transition: color .15s;
}
.modal-close-btn:hover { color: var(--text-0); }
.modal-body { padding: 1.5rem; }
.modal-foot {
  padding: 1rem 1.5rem; border-top: 1px solid var(--border);
  display: flex; justify-content: flex-end; gap: .75rem;
}

/* ── Customer detail modal ── */
.detail-box { max-width: 700px; }
.detail-header-row {
  display: flex; align-items: center; gap: 1.25rem;
  margin-bottom: 1.5rem;
}
.detail-avatar {
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--bg-2); border: 2px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display); font-size: 1.4rem;
  flex-shrink: 0;
}
.detail-name { font-family: var(--font-display); font-size: 1.8rem; }
.detail-email { color: var(--text-2); font-size: .875rem; }

.detail-stats {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 1px; background: var(--border); border: 1px solid var(--border);
  margin-bottom: 1.5rem;
}
.detail-stat {
  background: var(--bg-1); padding: 1rem;
  text-align: center;
}
.detail-stat .ds-lbl {
  font-family: var(--font-cond); font-size: .68rem; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase; color: var(--text-2);
  margin-bottom: .25rem;
}
.detail-stat .ds-val {
  font-family: var(--font-display); font-size: 1.5rem; color: var(--text-0);
}
.detail-stat .ds-val.red { color: var(--red); }

.detail-section-title {
  font-family: var(--font-cond); font-size: .75rem; font-weight: 700;
  letter-spacing: .15em; text-transform: uppercase; color: var(--text-2);
  border-bottom: 1px solid var(--border); padding-bottom: .5rem;
  margin-bottom: .75rem; margin-top: 1.25rem;
}
.sale-history-row {
  display: flex; align-items: center; gap: .75rem;
  padding: .6rem 0; border-bottom: 1px solid var(--border);
  font-size: .85rem;
}
.sale-history-row:last-child { border-bottom: none; }
.sh-id { font-family: var(--font-display); font-size: 1rem; min-width: 40px; color: var(--text-0); }
.sh-info { flex: 1; }
.sh-items { font-size: .78rem; color: var(--text-2); margin-top: .1rem; }
.sh-amount { font-family: var(--font-display); font-size: 1.1rem; color: var(--red); }
.sh-method { font-family: var(--font-cond); font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-2); }

/* ── Add/Edit form modal ── */
.form-modal { max-width: 520px; }
.f-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* ── Empty state ── */
.empty-cust {
  text-align: center; padding: 4rem 2rem; color: var(--text-2);
}
.empty-cust span { font-size: 3rem; display: block; margin-bottom: .75rem; }
.empty-cust p { font-family: var(--font-cond); letter-spacing: .05em; text-transform: uppercase; }

/* ── Flash ── */
.page-flash {
  background: var(--text-0); color: #fff;
  padding: .85rem 1.5rem; margin-bottom: 1.5rem;
  font-family: var(--font-cond); font-size: .85rem; font-weight: 700;
  letter-spacing: .05em; border-left: 4px solid var(--red);
  display: flex; align-items: center; gap: .5rem;
}
</style>

<div class="cust-wrap">

  <!-- Page header -->
  <div class="page-header">
    <div>
      <div class="section-label">Staff Area</div>
      <h1>Customers</h1>
      <p class="page-subtitle"><?= count($customers) ?> registered customer<?= count($customers) !== 1 ? 's' : '' ?></p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Customer</button>
  </div>

  <?php if ($flash): ?>
    <div class="page-flash" id="pageFlash">✓ <?= h($flash) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>

  <!-- Summary stats -->
  <div class="cust-stats">
    <div class="cust-stat">
      <div class="cs-label">Total Customers</div>
      <div class="cs-val"><?= number_format($totals['cnt']) ?></div>
    </div>
    <div class="cust-stat">
      <div class="cs-label">Lifetime Revenue</div>
      <div class="cs-val red">₱<?= number_format($totals['revenue'], 0) ?></div>
    </div>
    <div class="cust-stat">
      <div class="cs-label">Avg. Spend</div>
      <div class="cs-val">
        <?= $totals['cnt'] > 0 ? '₱' . number_format($totals['revenue'] / $totals['cnt'], 0) : '—' ?>
      </div>
    </div>
    <div class="cust-stat">
      <div class="cs-label">Showing</div>
      <div class="cs-val"><?= count($customers) ?></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="cust-toolbar">
    <div class="cust-search">
      <input type="text" id="custSearch" placeholder="Search name, email, contact…"
             value="<?= h($search) ?>" oninput="liveSearch(this.value)">
    </div>
    <form method="GET" style="display:contents">
      <?php if ($search): ?><input type="hidden" name="q" value="<?= h($search) ?>"><?php endif; ?>
      <select name="sort" class="sort-select" onchange="this.form.submit()">
        <option value="name"   <?= $sort==='name'   ? 'selected':'' ?>>Sort: Name</option>
        <option value="spent"  <?= $sort==='spent'  ? 'selected':'' ?>>Sort: Top Spenders</option>
        <option value="orders" <?= $sort==='orders' ? 'selected':'' ?>>Sort: Most Orders</option>
        <option value="recent" <?= $sort==='recent' ? 'selected':'' ?>>Sort: Recent Purchase</option>
        <option value="newest" <?= $sort==='newest' ? 'selected':'' ?>>Sort: Newest First</option>
      </select>
    </form>
    <?php if ($search): ?>
      <a href="customers.php" class="btn btn-ghost btn-sm">Clear</a>
    <?php endif; ?>
    <span style="margin-left:auto;font-family:var(--font-cond);font-size:.78rem;color:var(--text-2);text-transform:uppercase;letter-spacing:.05em">
      <?= count($customers) ?> result<?= count($customers)!==1?'s':'' ?>
    </span>
  </div>

  <!-- Customer table -->
  <?php if (empty($customers)): ?>
    <div class="empty-cust">
      <span>👥</span>
      <p>No customers found<?= $search ? " for \"$search\"" : '' ?></p>
    </div>
  <?php else: ?>
  <div class="cust-table-wrap">
    <table class="cust-table" id="custTable">
      <thead>
        <tr>
          <th><a href="?sort=name<?= $search ? '&q='.urlencode($search) : '' ?>">Customer <?= $sort==='name'?'↑':'' ?></a></th>
          <th>Contact</th>
          <th><a href="?sort=orders<?= $search ? '&q='.urlencode($search) : '' ?>">Orders <?= $sort==='orders'?'↓':'' ?></a></th>
          <th><a href="?sort=spent<?= $search ? '&q='.urlencode($search) : '' ?>">Total Spent <?= $sort==='spent'?'↓':'' ?></a></th>
          <th>Services</th>
          <th><a href="?sort=recent<?= $search ? '&q='.urlencode($search) : '' ?>">Last Purchase <?= $sort==='recent'?'↓':'' ?></a></th>
          <th><a href="?sort=newest<?= $search ? '&q='.urlencode($search) : '' ?>">Joined <?= $sort==='newest'?'↓':'' ?></a></th>
          <th></th>
        </tr>
      </thead>
      <tbody id="custTbody">
        <?php foreach ($customers as $c):
          $initials = strtoupper(substr($c['full_name'], 0, 1));
        ?>
        <tr data-search="<?= strtolower(h($c['full_name'] . ' ' . $c['email'] . ' ' . $c['contact'])) ?>">
          <td>
            <div class="cust-name-cell">
              <div class="cust-avatar"><?= $initials ?></div>
              <div>
                <div class="cust-name"><?= h($c['full_name']) ?></div>
                <div class="cust-email"><?= h($c['email']) ?></div>
              </div>
            </div>
          </td>
          <td><?= h($c['contact'] ?: '—') ?></td>
          <td>
            <?php if ($c['total_orders'] > 0): ?>
              <span class="badge badge-success"><?= $c['total_orders'] ?> order<?= $c['total_orders']>1?'s':'' ?></span>
            <?php else: ?>
              <span class="zero-val">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['total_spent'] > 0): ?>
              <span class="spent-val">₱<?= number_format($c['total_spent'], 2) ?></span>
            <?php else: ?>
              <span class="zero-val">₱0.00</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['total_services'] > 0): ?>
              <span class="badge badge-info"><?= $c['total_services'] ?></span>
            <?php else: ?>
              <span class="zero-val">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--text-2)">
            <?= $c['last_purchase'] ? date('M d, Y', strtotime($c['last_purchase'])) : '—' ?>
          </td>
          <td style="font-size:.82rem;color:var(--text-2)">
            <?= date('M d, Y', strtotime($c['created_at'])) ?>
          </td>
          <td>
            <div class="action-btns">
              <a href="?view=<?= $c['user_id'] ?>" class="icon-btn" title="View details">👁</a>
              <button class="icon-btn" title="Edit"
                onclick="openEdit(<?= htmlspecialchars(json_encode([
                  'user_id'  => $c['user_id'],
                  'full_name'=> $c['full_name'],
                  'email'    => $c['email'],
                  'contact'  => $c['contact'],
                  'address'  => $c['address'],
                ]), ENT_QUOTES) ?>)">✏️</button>
              <form method="POST" style="margin:0" onsubmit="return confirm('Delete <?= h(addslashes($c['full_name'])) ?>? This cannot be undone.')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= $c['user_id'] ?>">
                <button type="submit" class="icon-btn danger" title="Delete">🗑</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ Customer Detail Modal ═══ -->
<?php if ($detail): ?>
<div class="modal-backdrop open" id="detailModal">
  <div class="modal-box detail-box">
    <div class="modal-head">
      <h3>Customer Profile</h3>
      <button class="modal-close-btn" onclick="window.location='customers.php'">✕</button>
    </div>
    <div class="modal-body">
      <div class="detail-header-row">
        <div class="detail-avatar"><?= strtoupper(substr($detail['full_name'],0,1)) ?></div>
        <div>
          <div class="detail-name"><?= h($detail['full_name']) ?></div>
          <div class="detail-email"><?= h($detail['email']) ?></div>
          <?php if ($detail['contact']): ?>
            <div style="font-size:.82rem;color:var(--text-2);margin-top:.2rem">📞 <?= h($detail['contact']) ?></div>
          <?php endif; ?>
          <?php if ($detail['address']): ?>
            <div style="font-size:.82rem;color:var(--text-2)">📍 <?= h($detail['address']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <?php
        $dstats = $db->prepare(
          "SELECT COUNT(s.sale_id) as orders, COALESCE(SUM(s.total_amount),0) as spent,
                  COUNT(DISTINCT sr.service_id) as services, COUNT(DISTINCT a.appt_id) as appts
           FROM users u
           LEFT JOIN sales s ON s.customer_id=u.user_id
           LEFT JOIN service_requests sr ON sr.customer_id=u.user_id
           LEFT JOIN appointments a ON a.customer_id=u.user_id
           WHERE u.user_id=?"
        );
        $dstats->execute([$detail['user_id']]);
        $ds = $dstats->fetch();
      ?>
      <div class="detail-stats">
        <div class="detail-stat">
          <div class="ds-lbl">Orders</div>
          <div class="ds-val"><?= $ds['orders'] ?></div>
        </div>
        <div class="detail-stat">
          <div class="ds-lbl">Total Spent</div>
          <div class="ds-val red">₱<?= number_format($ds['spent'], 0) ?></div>
        </div>
        <div class="detail-stat">
          <div class="ds-lbl">Services</div>
          <div class="ds-val"><?= $ds['services'] ?></div>
        </div>
      </div>

      <?php if (!empty($detail_sales)): ?>
        <div class="detail-section-title">Purchase History</div>
        <?php foreach ($detail_sales as $s): ?>
          <div class="sale-history-row">
            <div class="sh-id">#<?= $s['sale_id'] ?></div>
            <div class="sh-info">
              <div><?= date('M d, Y · g:i A', strtotime($s['sale_date'])) ?></div>
              <?php if ($s['items']): ?>
                <div class="sh-items"><?= h($s['items']) ?></div>
              <?php endif; ?>
            </div>
            <div>
              <div class="sh-amount">₱<?= number_format($s['total_amount'], 2) ?></div>
              <div class="sh-method"><?= h($s['payment_method']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:var(--text-2);font-size:.875rem;text-align:center;padding:1rem 0">No purchases yet.</p>
      <?php endif; ?>
    </div>
    <div class="modal-foot">
      <a href="customers.php" class="btn btn-ghost btn-sm">Close</a>
      <button class="btn btn-primary btn-sm"
        onclick="openEdit(<?= htmlspecialchars(json_encode([
          'user_id'  => $detail['user_id'],
          'full_name'=> $detail['full_name'],
          'email'    => $detail['email'],
          'contact'  => $detail['contact'],
          'address'  => $detail['address'],
        ]), ENT_QUOTES) ?>)">Edit Customer</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══ Add Customer Modal ═══ -->
<div class="modal-backdrop" id="addModal">
  <div class="modal-box form-modal">
    <div class="modal-head">
      <h3>Add Customer</h3>
      <button class="modal-close-btn" onclick="closeModal('addModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <div class="f-row">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" required value="<?= h($_POST['full_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Contact</label>
            <input type="text" name="contact" class="form-control" placeholder="09XXXXXXXXX" value="<?= h($_POST['contact'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" required value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" placeholder="Optional" value="<?= h($_POST['address'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Password <span style="color:var(--text-2);font-weight:400;text-transform:none">(leave blank to auto-generate)</span></label>
          <input type="password" name="password" class="form-control" placeholder="Min. 6 characters">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Add Customer</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ Edit Customer Modal ═══ -->
<div class="modal-backdrop" id="editModal">
  <div class="modal-box form-modal">
    <div class="modal-head">
      <h3>Edit Customer</h3>
      <button class="modal-close-btn" onclick="closeModal('editModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" id="edit_uid">
      <div class="modal-body">
        <div class="f-row">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" id="edit_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Contact</label>
            <input type="text" name="contact" id="edit_contact" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" id="edit_email" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input type="text" name="address" id="edit_address" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">New Password <span style="color:var(--text-2);font-weight:400;text-transform:none">(leave blank to keep current)</span></label>
          <input type="password" name="password" class="form-control" placeholder="Leave blank to keep">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
// Modal open/close
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Open edit modal pre-filled
function openEdit(data) {
  document.getElementById('edit_uid').value     = data.user_id;
  document.getElementById('edit_name').value    = data.full_name;
  document.getElementById('edit_email').value   = data.email;
  document.getElementById('edit_contact').value = data.contact || '';
  document.getElementById('edit_address').value = data.address || '';
  closeModal('detailModal');
  openModal('editModal');
}

// Close modals on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(bd => {
  bd.addEventListener('click', e => {
    if (e.target === bd) {
      if (bd.id === 'detailModal') window.location = 'customers.php';
      else bd.classList.remove('open');
    }
  });
});

// Re-open add modal if there was a validation error
<?php if ($error && $action === 'add'): ?>
openModal('addModal');
<?php endif; ?>

// Live search filter
function liveSearch(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#custTbody tr').forEach(row => {
    row.style.display = row.dataset.search.includes(q) ? '' : 'none';
  });
}

// Auto-dismiss flash
const fl = document.getElementById('pageFlash');
if (fl) setTimeout(() => {
  fl.style.transition = 'opacity .4s';
  fl.style.opacity = '0';
  setTimeout(() => fl.remove(), 400);
}, 3500);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>