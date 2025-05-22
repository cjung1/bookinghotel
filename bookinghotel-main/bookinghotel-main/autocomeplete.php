<?php
header('Content-Type: application/json');
require_once __DIR__.'/../includes/config.php';

$query = $_GET['q'] ?? '';
$results = [];

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT name AS suggestion FROM hotels 
        WHERE name LIKE :query
        UNION
        SELECT DISTINCT location AS suggestion FROM hotels 
        WHERE location LIKE :query
        LIMIT 5
    ");
    $stmt->execute([':query' => "%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    http_response_code(500);
    $results = ['error' => 'Lỗi database'];
}

echo json_encode($results);