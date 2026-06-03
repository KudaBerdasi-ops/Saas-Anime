<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

// Konfigurasi Database untuk Localhost
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Password default untuk Laragon/XAMPP adalah kosong
define('DB_NAME', 'db_saasanime'); // Silakan sesuaikan dengan nama database lokal Anda di phpMyAdmin

function getDB() {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return $pdo;
}