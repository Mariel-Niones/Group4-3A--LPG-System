<?php
declare(strict_types=1);
session_start();

$host = 'localhost';
$db   = 'bgs_lpg';
$user = 'root';
$pass = ''; // Default XAMPP MySQL password is blank. Change if yours differs.
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database connection failed. Start Apache and MySQL in XAMPP, then import database.sql. Details: ' . htmlspecialchars($e->getMessage()));
}
date_default_timezone_set('Asia/Manila');
