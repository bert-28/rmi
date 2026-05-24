<?php

require_once __DIR__ . '/auth.php';
$user = current_user();
$role = $user['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($page_title ?? APP_NAME) ?> — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/shop.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-inner">
    <a class="nav-brand" href="<?= BASE_URL ?>/index.php">
      <span class="brand-icon"><img src="https://github.com/bert-28/rmi/blob/main/RMI%20Logo%20(2).png?raw=true" alt="Brand Logo" style="width: 100%; max-width: 120px; height: auto;"></span>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>

    <ul class="nav-links" id="navLinks">
      <?php if ($role === 'customer'): ?>
        <li><a href="<?= BASE_URL ?>/shop.php"       class="<?= active('products') ?>">Products</a></li>
        <li><a href="<?= BASE_URL ?>/appointments.php"   class="<?= active('appointments') ?>">Appointments</a></li>
        <li><a href="<?= BASE_URL ?>/my_orders.php"      class="<?= active('my_orders') ?>">My Orders</a></li>
        <li><a href="<?= BASE_URL ?>/profile.php"        class="<?= active('profile') ?>">Profile</a></li>
      <?php elseif (in_array($role, ['staff','admin'])): ?>
        <li><a href="<?= BASE_URL ?>/dashboard.php"      class="<?= active('dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= BASE_URL ?>/analytics.php"      class="<?= active('analytics') ?>">Analytics</a></li>
        <li><a href="<?= BASE_URL ?>/inventory.php"      class="<?= active('inventory') ?>">Inventory</a></li>
        <li><a href="<?= BASE_URL ?>/pos.php"            class="<?= active('pos') ?>">Sales / POS</a></li>
        <li><a href="<?= BASE_URL ?>/service.php"        class="<?= active('service') ?>">Service</a></li>
        <li><a href="<?= BASE_URL ?>/appointments.php"   class="<?= active('appointments') ?>">Appointments</a></li>
        <li><a href="<?= BASE_URL ?>/customers.php"      class="<?= active('customers') ?>">Customers</a></li>
      <?php else: ?>
        <li><a href="<?= BASE_URL ?>/shop.php">Browse Store</a></li>
      <?php endif; ?>
    </ul>

    <div class="nav-actions">
      <?php if ($user): ?>
        <span class="nav-username">👤 <?= h(explode(' ', $user['name'])[0]) ?></span>
        <span class="nav-badge nav-badge--<?= $role ?>"><?= ucfirst($role) ?></span>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-ghost">Logout</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/login.php"    class="btn btn-sm btn-ghost">Login</a>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-primary">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="main-content">
<?php

function active(string $page): string {
    $current = basename($_SERVER['PHP_SELF'], '.php');
    return $current === $page ? 'active' : '';
}