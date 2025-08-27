<?php
require 'db.php';

try {
    $q = $pdo->prepare("SELECT DISTINCT circle_name FROM data ORDER BY circle_name");
    $q->execute();
    $result = $q->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($result);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
