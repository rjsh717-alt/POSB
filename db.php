<?php
$host = 'localhost';
$db   = 'postoff2_mcq';
$user = 'postoff2_data';
$pass = 'DataAdmin@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

