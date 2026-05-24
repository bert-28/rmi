<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$db   = get_db();
$user = current_user();
$role = $user['role'];

$msg = '';
$err = '';

// Book a New Appointment (Customer) ------------------------------------------------
if ($role === 'customer' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'book') {
        $type     = $_POST['appt_type']  ?? '';
        $date     = $_POST['appt_date']  ?? '';
        $time     = $_POST['appt_time']  ?? '';
        $duration = (int)($_POST['duration_min'] ?? 60);
        $notes    = trim($_POST['notes'] ?? '');

        if (!$type || !$date || !$time) {
            $err = 'Please fill in all required fields.';
        } elseif (strtotime($date) < strtotime('today')) {
            $err = 'Appointment date cannot be in the past.';
        } else {
            $stmt = $db->prepare(
                'INSERT INTO appointments (customer_id, appt_type, appt_date, appt_time, duration_min, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$user['id'], $type, $date, $time, $duration, $notes ?: null]);
            $msg = '✅ Appointment booked! We\'ll confirm it shortly.';
        }
    } elseif ($_POST['action'] === 'cancel') {
        $appt_id = (int)($_POST['appt_id'] ?? 0);
        $stmt = $db->prepare(
            'UPDATE appointments SET status = "Cancelled"
             WHERE appt_id = ? AND customer_id = ? AND status IN ("Pending","Confirmed")'
        );
        $stmt->execute([$appt_id, $user['id']]);
        $msg = 'Appointment cancelled.';
    }
}

// Staff/Admin: Update Appointment Status ------------------------------------------------
if (in_array($role, ['staff','admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $appt_id    = (int)($_POST['appt_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $valid = ['Pending','Confirmed','Completed','Cancelled'];
        if (in_array($new_status, $valid, true)) {
            $stmt = $db->prepare('UPDATE appointments SET status = ?, staff_id = ? WHERE appt_id = ?');
            $stmt->execute([$new_status, $user['id'], $appt_id]);
            $msg = "Status updated to $new_status.";
        }
    }
}

// Fetch Appointments
if ($role === 'customer') {
    $stmt = $db->prepare(
        'SELECT a.*, u.full_name AS staff_name
         FROM appointments a
         LEFT JOIN users u ON u.user_id = a.staff_id
         WHERE a.customer_id = ?
         ORDER BY a.appt_date DESC, a.appt_time DESC'
    );
    $stmt->execute([$user['id']]);
} else {
    $stmt = $db->query(
        'SELECT a.*, c.full_name AS customer_name, u.full_name AS staff_name
         FROM appointments a
         LEFT JOIN users c ON c.user_id = a.customer_id
         LEFT JOIN users u ON u.user_id = a.staff_id
         ORDER BY a.appt_date DESC, a.appt_time DESC'
    );
}
$appointments = $stmt->fetchAll();

$status_colors = [
    'Pending'   => 'badge-warning',
    'Confirmed' => 'badge-info',
    'Completed' => 'badge-success',
    'Cancelled' => 'badge-danger',
];

$page_title = 'Appointments';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<div style="background:linear-gradient(135deg,var(--bg-1),var(--bg-0));border-bottom:1px solid var(--border);padding:3rem 1.5rem 2rem">
  <div style="max-width:1280px;margin:0 auto">
    <div style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--gold);margin-bottom:.5rem">Schedule</div>
    <h1 style="font-size:clamp(2rem,5vw,3.5rem);margin-bottom:.5rem">📅 <span style="color:var(--gold)">Appointments</span></h1>
    <p style="color:var(--text-2);margin:0">
      <?= $role === 'customer' ? 'Book a lesson, repair, or consultation.' : 'Manage all customer appointments.' ?>
    </p>
  </div>
</div>

<div class="container" style="padding-top:2rem">

  <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem"><?= h($msg) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="alert alert-danger" style="margin-bottom:1.5rem"><?= h($err) ?></div>
  <?php endif; ?>

  <?php if ($role === 'customer'): ?>
  <!-- ── Book Form ───────────────────────────────────────────────── -->
  <div class="card" style="margin-bottom:2rem;max-width:600px">
    <div class="card-header"><h3 style="margin:0">Book an Appointment</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="book">
        <div class="form-group">
          <label class="form-label">Type *</label>
          <select name="appt_type" class="form-control" required>
            <option value="">— Select —</option>
            <option value="Lesson">🎸 Guitar Lesson</option>
            <option value="Repair">🔧 Repair / Setup</option>
            <option value="Consultation">💬 Consultation</option>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label">Date *</label>
            <input type="date" name="appt_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Time *</label>
            <input type="time" name="appt_time" class="form-control" min="08:00" max="18:00" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Duration</label>
          <select name="duration_min" class="form-control">
            <option value="30">30 minutes</option>
            <option value="60" selected>1 hour</option>
            <option value="90">1.5 hours</option>
            <option value="120">2 hours</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Anything we should know..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Book Appointment</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Appointments Table ──────────────────────────────────────── -->
  <h3 style="margin-bottom:1rem"><?= $role === 'customer' ? 'Your Appointments' : 'All Appointments' ?></h3>

  <?php if (empty($appointments)): ?>
    <div class="empty-state">
      <div class="icon">📅</div>
      <p>No appointments yet.</p>
    </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <?php if ($role !== 'customer'): ?><th>Customer</th><?php endif; ?>
          <th>Type</th>
          <th>Date & Time</th>
          <th>Duration</th>
          <th>Status</th>
          <?php if ($role !== 'customer'): ?><th>Staff</th><?php endif; ?>
          <th>Notes</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($appointments as $a): ?>
        <tr>
          <?php if ($role !== 'customer'): ?>
            <td><?= h($a['customer_name'] ?? '—') ?></td>
          <?php endif; ?>
          <td><?= h($a['appt_type']) ?></td>
          <td style="white-space:nowrap">
            <?= date('M j, Y', strtotime($a['appt_date'])) ?><br>
            <small style="color:var(--text-2)"><?= date('g:i A', strtotime($a['appt_time'])) ?></small>
          </td>
          <td><?= $a['duration_min'] ?> min</td>
          <td><span class="badge <?= $status_colors[$a['status']] ?? '' ?>"><?= h($a['status']) ?></span></td>
          <?php if ($role !== 'customer'): ?>
            <td><?= h($a['staff_name'] ?? 'Unassigned') ?></td>
          <?php endif; ?>
          <td style="max-width:160px;font-size:.8rem;color:var(--text-2)"><?= h($a['notes'] ?? '—') ?></td>
          <td>
            <?php if ($role === 'customer' && in_array($a['status'], ['Pending','Confirmed'])): ?>
              <form method="POST" onsubmit="return confirm('Cancel this appointment?')">
                <input type="hidden" name="action"  value="cancel">
                <input type="hidden" name="appt_id" value="<?= $a['appt_id'] ?>">
                <button class="btn btn-sm btn-ghost" style="color:var(--danger)">Cancel</button>
              </form>
            <?php elseif (in_array($role, ['staff','admin'])): ?>
              <form method="POST" style="display:flex;gap:.4rem;align-items:center">
                <input type="hidden" name="action"  value="update_status">
                <input type="hidden" name="appt_id" value="<?= $a['appt_id'] ?>">
                <select name="new_status" class="form-control" style="padding:.25rem .5rem;font-size:.8rem">
                  <?php foreach (['Pending','Confirmed','Completed','Cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $a['status'] === $s ? 'selected':'' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-secondary">Save</button>
              </form>
            <?php else: ?>
              <span style="color:var(--text-2);font-size:.8rem"><?= h($a['status']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
