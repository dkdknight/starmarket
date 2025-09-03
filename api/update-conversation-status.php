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

if (!$input || !isset($input['conversation_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$conversation_id = (int)$input['conversation_id'];
$new_status = $input['status'];
$meeting_details = trim($input['meeting_details'] ?? '');
$csrf_token = $input['csrf_token'] ?? '';

// Validation CSRF
if (!validateCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
    exit;
}

// Vérifier que le statut est valide
if (!in_array($new_status, ['OPEN', 'SCHEDULED', 'DONE', 'DISPUTED', 'CLOSED'])) {
    echo json_encode(['success' => false, 'message' => 'Statut invalide']);
    exit;
}

try {
    // Vérifier que l'utilisateur fait partie de cette conversation
    $stmt = $pdo->prepare("
        SELECT c.*, l.id as listing_id, l.seller_id, i.name as item_name
        FROM conversations c
        JOIN listings l ON c.listing_id = l.id
        JOIN items i ON l.item_id = i.id
        WHERE c.id = ? AND (c.buyer_id = ? OR c.seller_id = ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        echo json_encode(['success' => false, 'message' => 'Conversation non trouvée']);
        exit;
    }
    
    $old_status = $conversation['status'];
    
    // Mettre à jour le statut de la conversation
    $stmt = $pdo->prepare("
        UPDATE conversations 
        SET status = ?, meeting_details = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$new_status, $meeting_details, $conversation_id]);
    
    // Actions spéciales selon le nouveau statut
    if ($new_status === 'DONE') {
        // Marquer l'annonce comme vendue automatiquement
        $stmt = $pdo->prepare("UPDATE listings SET status = 'SOLD' WHERE id = ?");
        $stmt->execute([$conversation['listing_id']]);
        
        // Créer les entrées pour permettre les avis mutuels
        // L'acheteur peut noter le vendeur
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO pending_reviews (conversation_id, rater_id, rated_id, role) 
            VALUES (?, ?, ?, 'SELLER')
        ");
        $stmt->execute([$conversation_id, $conversation['buyer_id'], $conversation['seller_id']]);
        
        // Le vendeur peut noter l'acheteur
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO pending_reviews (conversation_id, rater_id, rated_id, role) 
            VALUES (?, ?, ?, 'BUYER')
        ");
        $stmt->execute([$conversation_id, $conversation['seller_id'], $conversation['buyer_id']]);
    }
    
    // Notification Discord pour changement de statut important
    if (in_array($new_status, ['SCHEDULED', 'DONE', 'DISPUTED'])) {
        $other_user_id = ($user_id === $conversation['buyer_id']) ? $conversation['seller_id'] : $conversation['buyer_id'];
        
        $stmt = $pdo->prepare("SELECT discord_user_id, username FROM users WHERE id = ?");
        $stmt->execute([$other_user_id]);
        $other_user = $stmt->fetch();
        
        if ($other_user && $other_user['discord_user_id']) {
            require_once '../includes/discord-notification.php';
            
            $notification_data = [
                'type' => 'status_change',
                'item_name' => $conversation['item_name'],
                'old_status' => $old_status,
                'new_status' => $new_status,
                'meeting_details' => $meeting_details,
                'conversation_url' => SITE_URL . "/conversation.php?id=" . $conversation_id
            ];
            
            sendDiscordMessage($other_user['discord_user_id'], $notification_data);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Statut mis à jour avec succès',
        'new_status' => $new_status,
        'meeting_details' => $meeting_details,
        'listing_updated' => $new_status === 'DONE'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur mise à jour statut: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}
?>