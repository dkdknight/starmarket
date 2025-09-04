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
$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);

if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => 'Conversation invalide']);
    exit;
}

try {
    // Vérifier que l'utilisateur appartient à la conversation
    $stmt = $pdo->prepare(
        "SELECT id FROM conversations WHERE id = ? AND (buyer_id = ? OR seller_id = ?)"
    );
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit;
    }

    // Récupérer les nouveaux messages
    $stmt = $pdo->prepare(
        "SELECT m.*, u.username
         FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE m.conversation_id = ? AND m.id > ?
         ORDER BY m.created_at ASC"
    );
    $stmt->execute([$conversation_id, $last_id]);
    $rows = $stmt->fetchAll();

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'sender_name' => $row['username'],
            'body' => $row['body'],
            'created_at' => $row['created_at'],
            'is_own' => $row['sender_id'] == $user_id
        ];
    }

    // Marquer les messages récupérés comme lus
    if (!empty($messages)) {
        $lastFetchedId = end($messages)['id'];
        $stmt = $pdo->prepare(
            "UPDATE messages SET is_read = TRUE WHERE conversation_id = ? AND sender_id != ? AND id <= ?"
        );
        $stmt->execute([$conversation_id, $user_id, $lastFetchedId]);
    }

    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}