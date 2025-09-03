<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['hasNew' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Vérifier s'il y a de nouveaux messages non lus
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE (c.buyer_id = ? OR c.seller_id = ?)
        AND m.sender_id != ?
        AND m.is_read = FALSE
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $result = $stmt->fetch();
    
    echo json_encode(['hasNew' => $result['unread_count'] > 0]);
    
} catch (Exception $e) {
    echo json_encode(['hasNew' => false]);
}
?>