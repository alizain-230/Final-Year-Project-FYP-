<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sms_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:Arial;text-align:center;margin-top:100px;color:red;">
        <h2>Database Error</h2>
        <p>Cannot connect to database. Please check XAMPP MySQL is running.</p>
        <p style="font-size:12px;color:#666">'.$e->getMessage().'</p>
    </div>');
}