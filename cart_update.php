<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$data = json_decode(file_get_contents('php://input'), true);
if (is_array($data)) {
    $_SESSION['cart'] = [];
    foreach ($data as $pid => $qty) {
        $pid = (int)$pid; $qty = (int)$qty;
        if ($pid > 0 && $qty > 0) $_SESSION['cart'][$pid] = $qty;
    }
}
echo json_encode(['ok' => true, 'count' => array_sum($_SESSION['cart'])]);