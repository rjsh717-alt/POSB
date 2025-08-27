<?php
require 'db.php';

$region = $_GET['region'] ?? '';

if ($region === '') {
    echo json_encode([]);
    exit;
}

try {
    $q = $pdo->prepare("SELECT DISTINCT division_name FROM data WHERE region_name = ?");
    $q->execute([$region]);
    $result = $q->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($result);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
