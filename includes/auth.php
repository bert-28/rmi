<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  Auth helpers
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'name'     => $_SESSION['user_name'],
        'email'    => $_SESSION['user_email'],
        'role'     => $_SESSION['user_role'],
    ];
}

function require_login(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        header("Location: $redirect");
        exit;
    }
}

function require_role(array $roles, string $redirect = 'dashboard.php'): void {
    require_login();
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        header("Location: $redirect?error=unauthorized");
        exit;
    }
}

function login(string $email, string $password): array {
    $db   = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => '❌ Invalid email or password.'];
    }

    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['user_name']  = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    session_regenerate_id(true);

    return ['success' => true, 'role' => $user['role']];
}

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

function register(array $data): array {
    $db = get_db();

    // Check duplicate email
    $chk = $db->prepare('SELECT user_id FROM users WHERE email = ?');
    $chk->execute([$data['email']]);
    if ($chk->fetch()) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }

    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, contact, address)
         VALUES (?, ?, ?, "customer", ?, ?)'
    );
    $stmt->execute([
        $data['full_name'],
        $data['email'],
        $hash,
        $data['contact'] ?? null,
        $data['address'] ?? null,
    ]);

    return ['success' => true, 'message' => 'Account created! You can now log in.'];
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, string $message = ''): string {
    if ($message) {
        $_SESSION['flash'][$key] = $message;
        return '';
    }
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function format_money(float $amount): string {
    return '₱' . number_format($amount, 2);
}