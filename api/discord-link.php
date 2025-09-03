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

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action non spécifiée']);
    exit;
}

$action = $input['action'];
$csrf_token = $input['csrf_token'] ?? '';

// Validation CSRF
if (!validateCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
    exit;
}

try {
    switch ($action) {
        case 'link_discord':
            $discord_user_id = trim($input['discord_user_id'] ?? '');
            
            if (empty($discord_user_id)) {
                echo json_encode(['success' => false, 'message' => 'ID Discord requis']);
                exit;
            }
            
            // Valider le format de l'ID Discord (doit être numérique et entre 17-19 caractères)
            if (!preg_match('/^\d{17,19}$/', $discord_user_id)) {
                echo json_encode(['success' => false, 'message' => 'Format d\'ID Discord invalide']);
                exit;
            }
            
            // Vérifier que cet ID Discord n'est pas déjà utilisé
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE discord_user_id = ? AND id != ?");
            $stmt->execute([$discord_user_id, $user_id]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cet ID Discord est déjà utilisé par ' . $existing_user['username']
                ]);
                exit;
            }
            
            // Tester la connexion Discord avant de lier
            require_once '../includes/discord-notification.php';
            if (!isDiscordConfigured()) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Discord n\'est pas configuré sur le serveur'
                ]);
                exit;
            }
            
            // Lier le compte Discord
            $stmt = $pdo->prepare("
                UPDATE users 
                SET discord_user_id = ?, discord_notifications = TRUE, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$discord_user_id, $user_id]);
            
            // Envoyer un message de test
            $test_notification = [
                'type' => 'discord_linked',
                'username' => $_SESSION['username'] ?? 'Utilisateur',
                'test_message' => 'Votre compte Discord a été lié avec succès à StarMarket !'
            ];
            
            $discord_sent = sendDiscordMessage($discord_user_id, $test_notification);
            
            // Log de l'événement
            $stmt = $pdo->prepare("
                INSERT INTO discord_logs (user_id, discord_user_id, notification_type, success, error_message) 
                VALUES (?, ?, 'link_test', ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $discord_user_id,
                $discord_sent,
                $discord_sent ? null : 'Impossible d\'envoyer le message de test'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Compte Discord lié avec succès',
                'test_sent' => $discord_sent,
                'discord_user_id' => $discord_user_id
            ]);
            break;
            
        case 'unlink_discord':
            // Délier le compte Discord
            $stmt = $pdo->prepare("
                UPDATE users 
                SET discord_user_id = NULL, discord_notifications = FALSE, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Compte Discord délié avec succès'
            ]);
            break;
            
        case 'toggle_notifications':
            $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET discord_notifications = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$enabled, $user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => $enabled ? 'Notifications activées' : 'Notifications désactivées',
                'enabled' => $enabled
            ]);
            break;
            
        case 'test_notification':
            // Récupérer l'ID Discord de l'utilisateur
            $stmt = $pdo->prepare("SELECT discord_user_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $discord_user_id = $stmt->fetchColumn();
            
            if (!$discord_user_id) {
                echo json_encode(['success' => false, 'message' => 'Aucun compte Discord lié']);
                exit;
            }
            
            require_once '../includes/discord-notification.php';
            
            $test_notification = [
                'type' => 'test_notification',
                'message' => 'Ceci est un message de test depuis StarMarket !',
                'timestamp' => date('d/m/Y H:i:s')
            ];
            
            $discord_sent = sendDiscordMessage($discord_user_id, $test_notification);
            
            // Log de l'événement
            $stmt = $pdo->prepare("
                INSERT INTO discord_logs (user_id, discord_user_id, notification_type, success, error_message) 
                VALUES (?, ?, 'manual_test', ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $discord_user_id,
                $discord_sent,
                $discord_sent ? null : 'Échec de l\'envoi du message de test'
            ]);
            
            echo json_encode([
                'success' => $discord_sent,
                'message' => $discord_sent ? 'Message de test envoyé avec succès' : 'Échec de l\'envoi du message de test'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erreur Discord API: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>