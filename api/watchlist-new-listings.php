<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

$stmt = $pdo->prepare("
    SELECT l.id, i.name, l.item_id
    FROM watchlist w
    JOIN listings l ON w.item_id = l.item_id AND l.status = 'ACTIVE' AND (w.variant_id IS NULL OR w.variant_id = l.variant_id)
    JOIN items i ON l.item_id = i.id
    WHERE w.user_id = ? AND l.id > ?
    ORDER BY l.id ASC
    LIMIT 50
");
$stmt->execute([$user_id, $last_id]);
$listings = $stmt->fetchAll();

echo json_encode(['success' => true, 'listings' => $listings]);
?>