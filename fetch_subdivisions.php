<?php
require 'db.php';

$division = $_GET['division'] ?? '';

if ($division === '') {
    echo json_encode([]);
    exit;
}

try {
    $q = $pdo->prepare("SELECT DISTINCT subdivision_name FROM data WHERE division_name = ?");
    $q->execute([$division]);
    $result = $q->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($result);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
