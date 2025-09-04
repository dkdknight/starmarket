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
$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$last_message_time = $_GET['last_message'] ?? '';

if (!$conversation_id) {
    echo json_encode(['hasNew' => false]);
    exit;
}

try {
    // Vérifier que l'utilisateur fait partie de cette conversation
    $stmt = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND (buyer_id = ? OR seller_id = ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['hasNew' => false]);
        exit;
    }
    
    // Vérifier s'il y a de nouveaux messages depuis le timestamp donné
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as new_count, MAX(created_at) as latest_time
        FROM messages 
        WHERE conversation_id = ? AND created_at > ? AND sender_id != ?
    ");
    $stmt->execute([$conversation_id, $last_message_time, $user_id]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'hasNew' => $result['new_count'] > 0,
        'newCount' => (int)$result['new_count'],
        'latestTime' => $result['latest_time']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['hasNew' => false]);
}
?>