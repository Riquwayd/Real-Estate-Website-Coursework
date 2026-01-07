<?php
$db_host = 'localhost';
$db_name = 'real_estate';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("CRITICAL Database Connection Error in db_config.php: " . $e->getMessage());
    die("Critical error: Failed to connect to the database. Please contact the site administrator. (Check db_config.php and MySQL server status)");
}
