<?php
// Server-Sent Events pour la messagerie temps réel
require_once '../config.php';
require_once '../db.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

// Headers pour SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

$user_id = $_SESSION['user_id'];
$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$last_message_id = (int)($_GET['last_message_id'] ?? 0);

// Fonction pour envoyer un événement SSE
function sendSSE($event, $data) {
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Fonction pour vérifier les nouveaux messages
function checkNewMessages($pdo, $conversation_id, $user_id, $last_message_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, u.avatar_url
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversation_id, $last_message_id]);
    return $stmt->fetchAll();
}

// Fonction pour vérifier les changements de statut
function checkStatusChanges($pdo, $conversation_id, $last_check_time) {
    $stmt = $pdo->prepare("
        SELECT status, meeting_details, updated_at
        FROM conversations
        WHERE id = ? AND updated_at > ?
    ");
    $stmt->execute([$conversation_id, $last_check_time]);
    return $stmt->fetch();
}

// Marquer les messages comme lus pour cet utilisateur
if ($conversation_id > 0) {
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = TRUE 
        WHERE conversation_id = ? AND sender_id != ?
    ");
    $stmt->execute([$conversation_id, $user_id]);
}

// Garder la dernière vérification
$last_check_time = date('Y-m-d H:i:s');

// Boucle de vérification continue
$start_time = time();
$max_execution_time = 30; // 30 secondes max

while (time() - $start_time < $max_execution_time) {
    try {
        if ($conversation_id > 0) {
            // Vérifier les nouveaux messages
            $new_messages = checkNewMessages($pdo, $conversation_id, $user_id, $last_message_id);
            
            if (!empty($new_messages)) {
                foreach ($new_messages as $message) {
                    sendSSE('new_message', [
                        'id' => $message['id'],
                        'sender_id' => $message['sender_id'],
                        'sender_name' => $message['username'],
                        'sender_avatar' => $message['avatar_url'],
                        'body' => $message['body'],
                        'created_at' => $message['created_at'],
                        'is_own' => $message['sender_id'] == $user_id
                    ]);
                    $last_message_id = max($last_message_id, $message['id']);
                }
            }
            
            // Vérifier les changements de statut
            $status_change = checkStatusChanges($pdo, $conversation_id, $last_check_time);
            if ($status_change) {
                sendSSE('status_change', [
                    'status' => $status_change['status'],
                    'meeting_details' => $status_change['meeting_details'],
                    'updated_at' => $status_change['updated_at']
                ]);
                $last_check_time = $status_change['updated_at'];
            }
        } else {
            // Mode global : vérifier les nouveaux messages dans toutes les conversations
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count,
                       MAX(m.created_at) as last_message_time
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                WHERE (c.buyer_id = ? OR c.seller_id = ?)
                AND m.sender_id != ?
                AND m.is_read = FALSE
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            $global_stats = $stmt->fetch();
            
            if ($global_stats['unread_count'] > 0) {
                sendSSE('global_notification', [
                    'unread_count' => $global_stats['unread_count'],
                    'last_message_time' => $global_stats['last_message_time']
                ]);
            }
        }
        
        // Heartbeat pour maintenir la connexion
        sendSSE('heartbeat', ['timestamp' => time()]);
        
        // Attendre 2 secondes avant la prochaine vérification
        sleep(2);
        
    } catch (Exception $e) {
        sendSSE('error', ['message' => 'Erreur de connexion']);
        break;
    }
}

// Fermer la connexion
sendSSE('close', ['message' => 'Connexion fermée']);
?>