<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$db   = get_db();
$role = $_SESSION['user_role'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        // Customers create their own requests
        $db->prepare(
            "INSERT INTO service_requests (customer_id, service_type, description, estimated_cost)
             VALUES (?, ?, ?, ?)"
        )->execute([
            $_SESSION['user_id'],
            $_POST['service_type'],
            $_POST['description'],
            !empty($_POST['estimated_cost']) ? $_POST['estimated_cost'] : null,
        ]);
        flash('success', 'Service request submitted successfully.');

    } elseif ($action === 'update_status' && in_array($role, ['staff','admin'])) {
        $db->prepare(
            "UPDATE service_requests SET status=?, assigned_staff=?, actual_cost=?, notes=?,
             date_completed = IF(? = 'Completed', NOW(), NULL)
             WHERE service_id=?"
        )->execute([
            $_POST['status'],
            !empty($_POST['assigned_staff']) ? $_POST['assigned_staff'] : null,
            !empty($_POST['actual_cost']) ? $_POST['actual_cost'] : null,
            $_POST['notes'] ?? null,
            $_POST['status'],
            $_POST['service_id'],
        ]);
        flash('success', 'Service request updated.');

    } elseif ($action === 'delete' && $role === 'admin') {
        $db->prepare("DELETE FROM service_requests WHERE service_id=?")->execute([$_POST['service_id']]);
        flash('success', 'Request deleted.');
    }

    header('Location: service.php');
    exit;
}

// Fetch data
$status_filter = $_GET['status'] ?? '';
$where  = 'WHERE 1=1';
$params = [];

if ($role === 'customer') {
    $where .= ' AND sr.customer_id = ?';
    $params[] = $_SESSION['user_id'];
}
if ($status_filter) {
    $where .= ' AND sr.status = ?';
    $params[] = $status_filter;
}

$stmt = $db->prepare(
    "SELECT sr.*,
            c.full_name AS customer_name, c.contact AS customer_contact,
            s.full_name AS staff_name
     FROM service_requests sr
     LEFT JOIN users c ON sr.customer_id    = c.user_id
     LEFT JOIN users s ON sr.assigned_staff = s.user_id
     $where
     ORDER BY sr.date_requested DESC"
);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Stats (staff/admin only)
$stats = [];
if (in_array($role, ['staff','admin'])) {
    $stats = $db->query(
        "SELECT status, COUNT(*) as cnt FROM service_requests GROUP BY status"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Staff list for assignment
$staff_list = [];
if (in_array($role, ['staff','admin'])) {
    $staff_list = $db->query(
        "SELECT user_id, full_name FROM users WHERE role IN ('staff','admin') ORDER BY full_name"
    )->fetchAll();
}

$service_types = ['Setup','Repair','String Replacement','Electronics','Cleaning','Other'];
$statuses = ['Pending','Ongoing','Completed','Cancelled'];

$page_title = 'Service Requests';
require_once __DIR__ . '/includes/header.php';
?>

<?php $suc = flash('success'); if ($suc): ?>
<div class="container" style="padding-bottom:0"><div class="alert alert-success"><?= h($suc) ?></div></div>
<?php endif; ?>

<!-- Hero bar -->
<div style="background:linear-gradient(135deg,var(--bg-1),var(--bg-0));border-bottom:1px solid var(--border);padding:2.5rem 1.5rem 2rem">
  <div style="max-width:1280px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div>
      <div style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--gold);margin-bottom:.4rem">Repair & Service</div>
      <h1 style="font-size:clamp(1.6rem,4vw,2.5rem);margin-bottom:.25rem">Service <span style="color:var(--gold)">Requests</span></h1>
      <p style="color:var(--text-2);margin:0;font-size:.875rem">Track repairs, setups, and service jobs</p>
    </div>
    <?php if ($role === 'customer'): ?>
      <button onclick="openModal('newRequestModal')" class="btn btn-primary">+ Request Service</button>
    <?php endif; ?>
  </div>
</div>

<div class="container">

  <!-- Stats bar (staff/admin) -->
  <?php if (in_array($role, ['staff','admin'])): ?>
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:2rem">
    <?php
    $stat_def = ['Pending'=>['color'=>'var(--gold-light)','icon'=>'⏳'],
                 'Ongoing'=>['color'=>'var(--blue)','icon'=>'🔧'],
                 'Completed'=>['color'=>'var(--green)','icon'=>'✅'],
                 'Cancelled'=>['color'=>'var(--text-2)','icon'=>'✕']];
    foreach ($stat_def as $s => $meta): ?>
    <div class="stat-card">
      <div class="stat-label"><?= $s ?></div>
      <div class="stat-value" style="color:<?= $meta['color'] ?>"><?= $stats[$s] ?? 0 ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Status filter tabs -->
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
    <a href="service.php" class="cat-chip <?= !$status_filter ? 'active' : '' ?>">All</a>
    <?php foreach ($statuses as $s): ?>
    <a href="?status=<?= urlencode($s) ?>" class="cat-chip <?= $status_filter===$s ? 'active' : '' ?>"><?= $s ?></a>
    <?php endforeach; ?>
    <?php if (in_array($role, ['staff','admin'])): ?>
    <button onclick="openModal('newRequestModal')" class="btn btn-sm btn-primary" style="margin-left:auto">+ New Request</button>
    <?php endif; ?>
  </div>

  <!-- Requests table/cards -->
  <?php if (empty($requests)): ?>
    <div class="empty-state">
      <div class="icon">🔧</div>
      <p>No service requests<?= $status_filter ? ' with status "'.h($status_filter).'"' : '' ?>.</p>
      <?php if ($role === 'customer'): ?>
        <button onclick="openModal('newRequestModal')" class="btn btn-primary" style="margin-top:1rem">Request a Service</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="card">
      <div style="overflow-x:auto">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <?php if (in_array($role, ['staff','admin'])): ?><th>Customer</th><?php endif; ?>
              <th>Service Type</th>
              <th>Description</th>
              <th>Est. Cost</th>
              <th>Status</th>
              <th>Date</th>
              <th style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
            <tr>
              <td><strong>#<?= $r['service_id'] ?></strong></td>
              <?php if (in_array($role, ['staff','admin'])): ?>
              <td>
                <div style="font-weight:500"><?= h($r['customer_name'] ?? 'Unknown') ?></div>
                <?php if ($r['customer_contact']): ?>
                  <div style="font-size:.75rem;color:var(--text-2)"><?= h($r['customer_contact']) ?></div>
                <?php endif; ?>
              </td>
              <?php endif; ?>
              <td><span class="badge badge-info"><?= h($r['service_type']) ?></span></td>
              <td style="max-width:220px">
                <div style="font-size:.85rem;color:var(--text-1)"><?= h(mb_strimwidth($r['description'] ?? '', 0, 70, '…')) ?></div>
                <?php if ($r['staff_name']): ?>
                  <div style="font-size:.72rem;color:var(--text-2);margin-top:.25rem">Assigned: <?= h($r['staff_name']) ?></div>
                <?php endif; ?>
              </td>
              <td style="color:var(--gold);font-family:var(--font-display)">
                <?= $r['estimated_cost'] ? format_money($r['estimated_cost']) : '—' ?>
                <?php if ($r['actual_cost']): ?>
                  <div style="font-size:.72rem;color:var(--text-2)">Actual: <?= format_money($r['actual_cost']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= h($r['status']) ?></span></td>
              <td style="color:var(--text-2);font-size:.78rem;white-space:nowrap"><?= date('M j, Y', strtotime($r['date_requested'])) ?></td>
              <td style="text-align:right;white-space:nowrap">
                <?php if (in_array($role, ['staff','admin'])): ?>
                  <button onclick="openUpdateModal(<?= htmlspecialchars(json_encode($r)) ?>)" class="btn btn-sm btn-ghost">Update</button>
                  <?php if ($role === 'admin'): ?>
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="service_id" value="<?= $r['service_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this request?">Del</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- New Request Modal -->
<div class="modal-overlay" id="newRequestModal">
  <div class="modal">
    <div class="modal-header">
      <h3>🔧 New Service Request</h3>
      <button class="modal-close" data-close-modal>✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <?php if (in_array($role, ['staff','admin'])): ?>
        <div class="form-group">
          <label class="form-label">Customer</label>
          <select name="customer_id" class="form-control">
            <option value="">— Select customer —</option>
            <?php foreach ($db->query("SELECT user_id,full_name FROM users WHERE role='customer' ORDER BY full_name")->fetchAll() as $cu): ?>
              <option value="<?= $cu['user_id'] ?>"><?= h($cu['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Service Type *</label>
          <select name="service_type" class="form-control" required>
            <?php foreach ($service_types as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description *</label>
          <textarea name="description" class="form-control" rows="3" required placeholder="Describe the issue or service needed…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Estimated Cost (₱)</label>
          <input type="number" name="estimated_cost" class="form-control" step="0.01" min="0" placeholder="Optional">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Status Modal (staff/admin) -->
<?php if (in_array($role, ['staff','admin'])): ?>
<div class="modal-overlay" id="updateModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Update Service Request</h3>
      <button class="modal-close" data-close-modal>✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="service_id" id="upd_service_id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Status *</label>
            <select name="status" id="upd_status" class="form-control" required>
              <?php foreach ($statuses as $s): ?><option><?= $s ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Assign Staff</label>
            <select name="assigned_staff" id="upd_staff" class="form-control">
              <option value="">Unassigned</option>
              <?php foreach ($staff_list as $st): ?>
                <option value="<?= $st['user_id'] ?>"><?= h($st['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Actual Cost (₱)</label>
          <input type="number" name="actual_cost" id="upd_actual_cost" class="form-control" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea name="notes" id="upd_notes" class="form-control" rows="3" placeholder="Internal notes…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<script>
function openUpdateModal(r) {
  document.getElementById('upd_service_id').value  = r.service_id;
  document.getElementById('upd_status').value       = r.status;
  document.getElementById('upd_staff').value        = r.assigned_staff || '';
  document.getElementById('upd_actual_cost').value  = r.actual_cost || '';
  document.getElementById('upd_notes').value        = r.notes || '';
  openModal('updateModal');
}
</script>
<?php endif; ?>

<style>
.cat-chip {
  display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .9rem;
  border-radius:99px;background:var(--bg-2);border:1px solid var(--border);
  color:var(--text-1);font-size:.82rem;font-weight:500;text-decoration:none;transition:all .2s;
}
.cat-chip:hover{border-color:var(--gold);color:var(--gold)}
.cat-chip.active{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-light)}
.badge-info{background:var(--blue-dim);color:#5dade2;border:1px solid #2980b944}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>