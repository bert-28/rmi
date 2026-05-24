<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$db   = get_db();
$user = current_user();

$msg = '';
$err = '';

// ── Load current data ─────────────────────────────────────────────────────────
$stmt = $db->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ─ Update profile info ───────────────────────────────────────────────────
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $contact   = trim($_POST['contact']   ?? '');
        $address   = trim($_POST['address']   ?? '');

        if (!$full_name) {
            $err = 'Name cannot be empty.';
        } else {
            $stmt = $db->prepare(
                'UPDATE users SET full_name = ?, contact = ?, address = ? WHERE user_id = ?'
            );
            $stmt->execute([$full_name, $contact ?: null, $address ?: null, $user['id']]);

            // Refresh session name
            $_SESSION['user_name'] = $full_name;
            $profile['full_name']  = $full_name;
            $profile['contact']    = $contact;
            $profile['address']    = $address;

            $msg = '✅ Profile updated successfully.';
        }
    }

    // ─ Change password ───────────────────────────────────────────────────────
    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new_pass = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if (!password_verify($current, $profile['password_hash'])) {
            $err = '❌ Current password is incorrect.';
        } elseif (strlen($new_pass) < 8) {
            $err = 'New password must be at least 8 characters.';
        } elseif ($new_pass !== $confirm) {
            $err = 'New passwords do not match.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $stmt->execute([$hash, $user['id']]);
            $msg = '✅ Password changed successfully.';
        }
    }
}

// Quick stats for the customer
$stats = [];
if ($profile['role'] === 'customer') {
    $r = $db->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM sales WHERE customer_id = ?');
    $r->execute([$user['id']]);
    $stats['sales'] = $r->fetch();

    $r = $db->prepare('SELECT COUNT(*) AS cnt FROM appointments WHERE customer_id = ?');
    $r->execute([$user['id']]);
    $stats['appts'] = $r->fetch();
}

$page_title = 'My Profile';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<div style="background:linear-gradient(135deg,var(--bg-1),var(--bg-0));border-bottom:1px solid var(--border);padding:3rem 1.5rem 2rem">
  <div style="max-width:1280px;margin:0 auto">
    <div style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--gold);margin-bottom:.5rem">Account</div>
    <h1 style="font-size:clamp(2rem,5vw,3.5rem);margin-bottom:.5rem">👤 My <span style="color:var(--gold)">Profile</span></h1>
    <p style="color:var(--text-2);margin:0">Manage your account details and password</p>
  </div>
</div>

<div class="container" style="padding-top:2rem;max-width:820px">

  <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem"><?= h($msg) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="alert alert-danger" style="margin-bottom:1.5rem"><?= h($err) ?></div>
  <?php endif; ?>

  <!-- Avatar + quick info -->
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-body" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
      <div style="width:72px;height:72px;border-radius:50%;background:var(--gold-dim);border:2px solid var(--gold);display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0">
        <?= mb_strtoupper(mb_substr($profile['full_name'], 0, 1)) ?>
      </div>
      <div style="flex:1">
        <div style="font-size:1.3rem;font-weight:700"><?= h($profile['full_name']) ?></div>
        <div style="color:var(--text-2);font-size:.88rem"><?= h($profile['email']) ?></div>
        <span class="badge badge-info" style="margin-top:.35rem"><?= ucfirst($profile['role']) ?></span>
      </div>
      <?php if (!empty($stats)): ?>
      <div style="display:flex;gap:1.5rem;flex-wrap:wrap">
        <div style="text-align:center">
          <div style="font-size:1.5rem;font-weight:700;color:var(--gold)"><?= $stats['sales']['cnt'] ?></div>
          <div style="font-size:.75rem;color:var(--text-2)">Orders</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:1.5rem;font-weight:700;color:var(--gold)"><?= format_money($stats['sales']['total']) ?></div>
          <div style="font-size:.75rem;color:var(--text-2)">Spent</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:1.5rem;font-weight:700;color:var(--gold)"><?= $stats['appts']['cnt'] ?></div>
          <div style="font-size:.75rem;color:var(--text-2)">Appointments</div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Edit Profile -->
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header"><h3 style="margin:0">Edit Profile</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="full_name" class="form-control"
                 value="<?= h($profile['full_name']) ?>" required maxlength="100">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" value="<?= h($profile['email']) ?>" disabled
                 style="opacity:.6;cursor:not-allowed" title="Email cannot be changed">
          <small style="color:var(--text-2)">Contact us to change your email address.</small>
        </div>
        <div class="form-group">
          <label class="form-label">Contact Number</label>
          <input type="tel" name="contact" class="form-control"
                 value="<?= h($profile['contact'] ?? '') ?>" placeholder="09XXXXXXXXX" maxlength="20">
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"
                    placeholder="Your address (optional)"><?= h($profile['address'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-header"><h3 style="margin:0">Change Password</h3></div>
    <div class="card-body">
      <form method="POST" id="pw-form">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label class="form-label">Current Password *</label>
          <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-group">
          <label class="form-label">New Password * <small style="color:var(--text-2)">(min. 8 characters)</small></label>
          <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password *</label>
          <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
          <small id="pw-match-msg" style="display:none;color:var(--danger)">Passwords do not match.</small>
        </div>
        <button type="submit" class="btn btn-secondary">Update Password</button>
      </form>
    </div>
  </div>

  <!-- Member since -->
  <p style="text-align:center;color:var(--text-2);font-size:.8rem;margin-top:1.5rem">
    Member since <?= date('F Y', strtotime($profile['created_at'])) ?>
  </p>

</div>

<script>
// Live password match validation
const np = document.getElementById('new_password');
const cp = document.getElementById('confirm_password');
const msg = document.getElementById('pw-match-msg');
function checkMatch() {
  if (cp.value.length === 0) { msg.style.display = 'none'; return; }
  msg.style.display = np.value !== cp.value ? 'block' : 'none';
}
np.addEventListener('input', checkMatch);
cp.addEventListener('input', checkMatch);
document.getElementById('pw-form').addEventListener('submit', function(e) {
  if (np.value !== cp.value) { e.preventDefault(); }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
