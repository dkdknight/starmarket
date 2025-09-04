<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['conversation_id']) || !isset($input['message'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$conversation_id = (int)$input['conversation_id'];
$message_body = trim($input['message']);
$csrf_token = $input['csrf_token'] ?? '';

// Validation CSRF
if (!validateCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
    exit;
}

// Validation du message
if (empty($message_body)) {
    echo json_encode(['success' => false, 'message' => 'Le message ne peut pas être vide']);
    exit;
}

if (strlen($message_body) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Le message ne peut pas dépasser 2000 caractères']);
    exit;
}

try {
    // Vérifier que l'utilisateur fait partie de cette conversation
    $stmt = $pdo->prepare("
        SELECT c.*, l.seller_id,
               u_buyer.username as buyer_username, u_seller.username as seller_username,
               u_buyer.discord_user_id as buyer_discord, u_seller.discord_user_id as seller_discord,
               u_buyer.discord_notifications as buyer_discord_enabled,
               u_seller.discord_notifications as seller_discord_enabled
        FROM conversations c
        JOIN listings l ON c.listing_id = l.id
        JOIN users u_buyer ON c.buyer_id = u_buyer.id
        JOIN users u_seller ON c.seller_id = u_seller.id
        WHERE c.id = ? AND (c.buyer_id = ? OR c.seller_id = ?)
        AND u_buyer.is_banned = FALSE AND u_seller.is_banned = FALSE
    ");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        echo json_encode(['success' => false, 'message' => 'Conversation non trouvée']);
        exit;
    }
    
    // Vérifier que la conversation n'est pas fermée
    if (!in_array($conversation['status'], ['OPEN', 'SCHEDULED'])) {
        echo json_encode(['success' => false, 'message' => 'Cette conversation est fermée']);
        exit;
    }
    
    // Insérer le message
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, body) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $message_body]);
    $message_id = $pdo->lastInsertId();
    
    // Mettre à jour la conversation
    $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$conversation_id]);
    
    // Récupérer les informations du message créé
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, u.avatar_url
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$message_id]);
    $message_data = $stmt->fetch();
    
    // Déterminer le destinataire pour Discord
    $recipient_discord_id = null;
    $recipient_username = '';

    if ($user_id === $conversation['buyer_id']) {
        // L'acheteur envoie un message au vendeur
        if (!empty($conversation['seller_discord_enabled'])) {
            $recipient_discord_id = $conversation['seller_discord'];
            $recipient_username = $conversation['seller_username'];
        }
    } else {
        // Le vendeur envoie un message à l'acheteur
        if (!empty($conversation['buyer_discord_enabled'])) {
            $recipient_discord_id = $conversation['buyer_discord'];
            $recipient_username = $conversation['buyer_username'];
        }
    }

    // Envoyer notification Discord si configuré
    if ($recipient_discord_id) {
        require_once '../includes/discord-notification.php';
        sendDiscordMessage($recipient_discord_id, [
            'type' => 'new_message',
            'sender' => $message_data['username'],
            'message_preview' => substr($message_body, 0, 100),
            'conversation_url' => SITE_URL . "/conversation.php?id=" . $conversation_id
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $message_data['id'],
            'sender_id' => $message_data['sender_id'],
            'sender_name' => $message_data['username'],
            'sender_avatar' => $message_data['avatar_url'],
            'body' => $message_data['body'],
            'created_at' => $message_data['created_at'],
            'is_own' => true
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erreur envoi message: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi du message']);
}
?>