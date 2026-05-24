<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'resurrection_music');
define('DB_USER', 'root');       
define('DB_PASS', '');           
define('DB_CHARSET', 'utf8mb4');

// App Settings
define('APP_NAME',    'Resurrection Musical Instruments');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/rmi');

// Session lifetime (seconds)
define('SESSION_LIFETIME', 3600);

//  PDO Connection
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}