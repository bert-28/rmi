<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
require_role(['customer']);

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

$db   = get_db();
$user = current_user();

// ── 1. Validate cart is not empty ────────────────────────────
if (empty($_SESSION['cart'])) {
    flash('error', 'Your cart is empty.');
    header('Location: cart.php');
    exit;
}

// ── 2. Sanitize inputs ───────────────────────────────────────
$payment_method = trim($_POST['payment_method'] ?? '');
$notes          = trim($_POST['notes']          ?? '');

$allowed_payments = ['Cash', 'GCash', 'Card', 'Bank Transfer'];
if (!in_array($payment_method, $allowed_payments, true)) {
    flash('error', 'Invalid payment method.');
    header('Location: cart.php');
    exit;
}

// ── 3. Re-fetch products from DB (never trust session prices) ─
$pids         = array_values(array_map('intval', array_keys($_SESSION['cart'])));
$placeholders = implode(',', array_fill(0, count($pids), '?'));
$stmt         = $db->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
$stmt->execute($pids);
$products     = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    flash('error', 'No valid products found.');
    header('Location: cart.php');
    exit;
}

// ── 4. Validate stock & build order lines ────────────────────
$order_lines = [];
$order_total = 0;
$errors      = [];

foreach ($products as $p) {
    $pid = (int)$p['product_id'];
    $qty = (int)($_SESSION['cart'][$pid] ?? $_SESSION['cart'][(string)$pid] ?? 0);

    if ($qty < 1) continue;

    if ($p['stock_qty'] < $qty) {
        $errors[] = "{$p['product_name']} only has {$p['stock_qty']} unit(s) left. Please update your cart.";
        continue;
    }

    $subtotal      = $p['price'] * $qty;
    $order_total  += $subtotal;
    $order_lines[] = [
        'product_id'   => $pid,
        'product_name' => $p['product_name'],
        'qty'          => $qty,
        'unit_price'   => $p['price'],
        'subtotal'     => $subtotal,
    ];
}

if (!empty($errors)) {
    flash('error', implode(' ', $errors));
    header('Location: cart.php');
    exit;
}

if (empty($order_lines)) {
    flash('error', 'No valid items to order.');
    header('Location: cart.php');
    exit;
}

// ── 5. Wrap everything in a transaction ──────────────────────
try {
    $db->beginTransaction();

    // 5a. Insert into sales table
    $stmt = $db->prepare("
        INSERT INTO sales (customer_id, total_amount, payment_method, notes, sale_date)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user['id'],
        $order_total,
        $payment_method,
        $notes ?: null,
    ]);
    $sale_id = $db->lastInsertId();

    // 5b. Insert each sale line & deduct stock
    $line_stmt  = $db->prepare("
        INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stock_stmt = $db->prepare("
        UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ? AND stock_qty >= ?
    ");

    foreach ($order_lines as $line) {
        $line_stmt->execute([
            $sale_id,
            $line['product_id'],
            $line['qty'],
            $line['unit_price'],
            $line['subtotal'],
        ]);

        $stock_stmt->execute([
            $line['qty'],
            $line['product_id'],
            $line['qty'],
        ]);

        if ($stock_stmt->rowCount() === 0) {
            throw new Exception("Stock conflict on product ID {$line['product_id']}.");
        }
    }

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    error_log('Checkout error: ' . $e->getMessage());
    flash('error', 'Something went wrong while placing your order. Please try again.');
    header('Location: cart.php');
    exit;
}

// ── 6. Clear the cart ────────────────────────────────────────
$_SESSION['cart'] = [];

// ── 7. Redirect to confirmation ──────────────────────────────
flash('success', 'Order #' . $sale_id . ' placed successfully!');
header('Location: order_confirmation.php?id=' . $sale_id);
exit;