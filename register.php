<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) { header('Location: shop.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] !== $_POST['password_confirm']) {
        $error = 'Passwords do not match.';
    } elseif (strlen($_POST['password']) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $result = register([
            'full_name' => $_POST['full_name'] ?? '',
            'email'     => $_POST['email'] ?? '',
            'password'  => $_POST['password'] ?? '',
            'contact'   => $_POST['contact'] ?? '',
            'address'   => $_POST['address'] ?? '',
        ]);
        if ($result['success']) {
            flash('success', $result['message']);
            header('Location: login.php');
            exit;
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/login.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">

  <style>
    /* ── Hero image: full bleed, maximized ── */
    .auth-hero {
      position: relative;
      overflow: hidden;
    }

    .auth-hero > img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center center;
      display: block;
      z-index: 0;
    }

    /* Keep overlaid elements above the image */
    .hero-amp,
    .connect-bg-container {
      position: relative;
      z-index: 1;
    }

    /* Subtle dark gradient so callout text stays readable */
    .auth-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(
        to bottom,
        rgba(0,0,0,0.08) 0%,
        rgba(0,0,0,0.45) 100%
      );
      z-index: 0;
      pointer-events: none;
    }

    /* Make sure the callout sits above the gradient */
    .connect-bg-container {
      z-index: 2;
    }

    /* Tighten form rows for the extra fields */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0 1rem;
    }

    @media (max-width: 400px) {
      .form-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="auth-page">

  <!-- ── HERO ── -->
  <div class="auth-hero">
      <img src="https://my.fender.com/assets/images/connect-login-hero3.jpg" alt="Guitar and Amplifier">

    <!-- Amp -->
    <div class="hero-amp">
      <div class="amp-box">
        <div class="amp-knobs">
          <div class="amp-knob"></div>
          <div class="amp-knob"></div>
          <div class="amp-knob"></div>
          <div class="amp-knob"></div>
          <div class="amp-knob"></div>
          <div class="amp-knob"></div>
        </div>
        <div class="amp-brand">Fender</div>
        <div class="amp-grille"></div>
      </div>
    </div>

    <!-- Callout card -->
    <div class="connect-bg-container fender"><!----><div class="marketing-callout ng-star-inserted"><strong>Introducing the Fender Stratocaster Standard</strong><br><span class="t-12">With vintage-accurate pickups, era-correct necks and iconic finishes, our new series brings back legendary classics that have inspired players for generations.</span><br><br><a target="_blank" class="btn btn-primary" href="<?= BASE_URL ?>/shop.php">Shop Now</a></div><!----></div>

  </div><!-- /hero -->


  <!-- ── REGISTER PANEL ── -->
  <div class="auth-panel">

    <div class="auth-logo">
      <img src="https://github.com/bert-28/rmi/blob/main/RMI%20Logo%20(2).png?raw=true" alt="<?= APP_NAME ?> Logo">
      <h1 class="auth-logo-name">Resurrection</h1>
      <p class="auth-logo-sub">Musical Instruments</p>
    </div>

    <hr class="auth-divider-top">
    <p class="auth-panel-title">Create your account to get started.</p>

    <!-- Sign in / Sign up tabs -->
    <div class="auth-tabs">
      <a href="<?= BASE_URL ?>/login.php" class="auth-tab inactive">Sign In</a>
      <span class="auth-tab active">Sign Up</span>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" required autofocus
          placeholder="Full Name"
                 value="<?= h($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Contact Number</label>
          <input type="text" name="contact" class="form-control" placeholder="09XXXXXXXXX"
                 value="<?= h($_POST['contact'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com"
               required value="<?= h($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Address</label>
        <input type="text" name="address" class="form-control" placeholder="Address (optional)"
               value="<?= h($_POST['address'] ?? '') ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pw-input" class="form-control"
                   placeholder="Password" required minlength="6">
            <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Toggle password">&#128065;</button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="pw-wrap">
            <input type="password" name="password_confirm" id="pw-confirm" class="form-control"
                   placeholder="Confirm Password" required>
            <button type="button" class="pw-toggle" id="pw-confirm-toggle" aria-label="Toggle password">&#128065;</button>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:.5rem">
        Create Account →
      </button>
    </form>



    <div class="auth-footer"></div>

  </div><!-- /panel -->

</div>

<script src="<?= BASE_URL ?>/js/app.js"></script>
<script>
  function bindToggle(inputId, toggleId) {
    const input  = document.getElementById(inputId);
    const toggle = document.getElementById(toggleId);
    if (input && toggle) {
      toggle.addEventListener('click', () => {
        const hidden = input.type === 'password';
        input.type        = hidden ? 'text' : 'password';
        toggle.innerHTML  = hidden ? '&#128065;&#65039;' : '&#128065;';
      });
    }
  }
  bindToggle('pw-input',  'pw-toggle');
  bindToggle('pw-confirm', 'pw-confirm-toggle');
</script>
</body>
</html>