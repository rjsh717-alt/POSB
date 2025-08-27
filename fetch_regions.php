<?php
require 'db.php';

$circle = $_GET['circle'] ?? '';

if ($circle === '') {
    echo json_encode([]);
    exit;
}

try {
    $q = $pdo->prepare("SELECT DISTINCT region_name FROM data WHERE circle_name = ?");
    $q->execute([$circle]);
    $result = $q->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($result);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
