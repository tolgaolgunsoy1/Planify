<?php
// config.php : the ONLY file that knows where the database is
date_default_timezone_set('Europe/Istanbul');

// Detect if running on localhost or on InfinityFree host
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$local = ($serverName === 'localhost' || $serverName === '127.0.0.1' || php_sapi_name() === 'cli');

$host = $local ? 'localhost' : 'sqlXXX.infinityfree.com';      // MySQL host name
$name = $local ? 'planify_db' : 'if0_12345678_planify_db';    // database name
$user = $local ? 'root' : 'if0_12345678';                      // MySQL username
$pass = $local ? '' : 'PASSWORD-FROM-THE-PANEL';               // replace with *** before submitting zip

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
