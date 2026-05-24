<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $role = $_SESSION['user_role'];
    header('Location: ' . ($role === 'customer' ? 'shop.php' : 'dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) {
        header('Location: ' . ($result['role'] === 'customer' ? 'shop.php' : 'dashboard.php'));
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
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

    <!-- Guitar SVG -->
    

    <!-- Callout card -->
    <div class="connect-bg-container fender"><!----><div class="marketing-callout ng-star-inserted"><strong>Introducing the Fender Stratocaster Standard</strong><br><span class="t-12">With vintage-accurate pickups, era-correct necks and iconic finishes, our new series brings back legendary classics that have inspired players for generations.</span><br><br><a target="_blank" class="btn btn-primary" href="<?= BASE_URL ?>/shop.php">Shop Now</a></div><!----></div>

  </div><!-- /hero -->


  <!-- ── LOGIN PANEL ── -->
  <div class="auth-panel">

    <div class="auth-logo">
      <img src="https://github.com/bert-28/rmi/blob/main/RMI%20Logo%20(2).png?raw=true" alt="<?= APP_NAME ?> Logo">
      <h1 class="auth-logo-name">Resurrection</h1>
      <p class="auth-logo-sub">Musical Instruments</p>
    </div>

    <hr class="auth-divider-top">
    <p class="auth-panel-title">Sign into your account to continue.</p>

    <!-- Sign in / Sign up tabs -->
    <div class="auth-tabs">
      <span class="auth-tab active">Sign In</span>
      <a href="<?= BASE_URL ?>/register.php" class="auth-tab inactive">Sign Up</a>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <?php $flash = flash('success'); if ($flash): ?>
      <div class="alert alert-success"><?= h($flash) ?></div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input
          type="email"
          name="email"
          class="form-control"
          placeholder="you@example.com"
          required
          autofocus
          value="<?= h($_POST['email'] ?? '') ?>"
        >
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="pw-wrap">
          <input
            type="password"
            name="password"
            id="pw-input"
            class="form-control"
            placeholder="••••••••"
            required
          >
          <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Toggle password">&#128065;</button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:.5rem">
        Sign In
      </button>
    </form>

    <div class="divider">or</div>
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-ghost btn-full">Create an account</a>

    <div class="auth-footer"></div>

  </div><!-- /panel -->

</div>

<script src="<?= BASE_URL ?>/js/app.js"></script>
<script>
  const pwInput  = document.getElementById('pw-input');
  const pwToggle = document.getElementById('pw-toggle');
  if (pwInput && pwToggle) {
    pwToggle.addEventListener('click', () => {
      const hidden = pwInput.type === 'password';
      pwInput.type   = hidden ? 'text' : 'password';
      pwToggle.innerHTML = hidden ? '&#128065;&#65039;' : '&#128065;';
    });
  }
</script>
</body>
</html>