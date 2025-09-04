<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

$item_id = (int)($_GET['item_id'] ?? 0);

if ($item_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM item_variants 
        WHERE item_id = ? AND is_active = TRUE 
        ORDER BY variant_name, color_name
    ");
    $stmt->execute([$item_id]);
    $variants = $stmt->fetchAll();
    
    echo json_encode($variants);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>