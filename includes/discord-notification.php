<?php
// Système de notifications Discord

// Configuration Discord (à personnaliser)
define('DISCORD_BOT_TOKEN', ''); // À remplir avec votre token bot
define('DISCORD_API_URL', 'https://discord.com/api/v10');

/**
 * Envoyer un message Discord à un utilisateur
 */
function sendDiscordMessage($discord_user_id, $notification_data) {
    if (empty(DISCORD_BOT_TOKEN) || empty($discord_user_id)) {
        return false;
    }
    
    try {
        // Créer le canal DM avec l'utilisateur
        $dm_channel = createDMChannel($discord_user_id);
        if (!$dm_channel) {
            return false;
        }
        
        // Construire le message selon le type
        $message = buildDiscordMessage($notification_data);
        
        // Envoyer le message
        return sendDiscordMessageToChannel($dm_channel['id'], $message);
        
    } catch (Exception $e) {
        error_log("Erreur Discord notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Créer un canal DM avec un utilisateur
 */
function createDMChannel($user_id) {
    $url = DISCORD_API_URL . '/users/@me/channels';
    
    $data = json_encode([
        'recipient_id' => $user_id
    ]);
    
    $headers = [
        'Authorization: Bot ' . DISCORD_BOT_TOKEN,
        'Content-Type: application/json',
        'User-Agent: StarMarket-Bot/1.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return false;
}

/**
 * Construire le message Discord selon le type de notification
 */
function buildDiscordMessage($data) {
    switch ($data['type']) {
        case 'new_message':
            return [
                'embeds' => [[
                    'title' => '💬 Nouveau message sur StarMarket',
                    'description' => "**{$data['sender']}** vous a envoyé un message :",
                    'fields' => [
                        [
                            'name' => 'Message',
                            'value' => strlen($data['message_preview']) > 100 
                                ? substr($data['message_preview'], 0, 100) . '...' 
                                : $data['message_preview'],
                            'inline' => false
                        ]
                    ],
                    'color' => 0x0ea5e9, // Bleu StarMarket
                    'footer' => [
                        'text' => 'StarMarket - Marketplace Star Citizen'
                    ],
                    'timestamp' => date('c')
                ]],
                'components' => [[
                    'type' => 1,
                    'components' => [[
                        'type' => 2,
                        'style' => 5,
                        'label' => 'Répondre sur StarMarket',
                        'url' => $data['conversation_url']
                    ]]
                ]]
            ];
            
        case 'status_change':
            $status_emoji = [
                'SCHEDULED' => '📅',
                'DONE' => '✅',
                'DISPUTED' => '⚠️',
                'CLOSED' => '🔒'
            ];
            
            $status_names = [
                'OPEN' => 'Ouverte',
                'SCHEDULED' => 'RDV Programmé',
                'DONE' => 'Transaction Terminée',
                'DISPUTED' => 'Litige',
                'CLOSED' => 'Fermée'
            ];
            
            $embed = [
                'title' => ($status_emoji[$data['new_status']] ?? '🔄') . ' Statut de conversation mis à jour',
                'description' => "La conversation pour **{$data['item_name']}** a changé de statut.",
                'fields' => [
                    [
                        'name' => 'Nouveau statut',
                        'value' => $status_names[$data['new_status']] ?? $data['new_status'],
                        'inline' => true
                    ]
                ],
                'color' => $data['new_status'] === 'DONE' ? 0x10b981 : 0xf59e0b,
                'footer' => [
                    'text' => 'StarMarket - Marketplace Star Citizen'
                ],
                'timestamp' => date('c')
            ];
            
            if (!empty($data['meeting_details'])) {
                $embed['fields'][] = [
                    'name' => 'Détails',
                    'value' => $data['meeting_details'],
                    'inline' => false
                ];
            }
            
            if ($data['new_status'] === 'DONE') {
                $embed['fields'][] = [
                    'name' => '⭐ Évaluation',
                    'value' => 'Vous pouvez maintenant laisser un avis sur cette transaction !',
                    'inline' => false
                ];
            }
            
            return [
                'embeds' => [$embed],
                'components' => [[
                    'type' => 1,
                    'components' => [[
                        'type' => 2,
                        'style' => 5,
                        'label' => 'Voir la conversation',
                        'url' => $data['conversation_url']
                    ]]
                ]]
            ];
            
        default:
            return [
                'content' => '🔔 Vous avez une nouvelle notification sur StarMarket !',
                'components' => [[
                    'type' => 1,
                    'components' => [[
                        'type' => 2,
                        'style' => 5,
                        'label' => 'Voir sur StarMarket',
                        'url' => SITE_URL
                    ]]
                ]]
            ];
    }
}

/**
 * Envoyer un message à un canal Discord
 */
function sendDiscordMessageToChannel($channel_id, $message) {
    $url = DISCORD_API_URL . "/channels/{$channel_id}/messages";
    
    $headers = [
        'Authorization: Bot ' . DISCORD_BOT_TOKEN,
        'Content-Type: application/json',
        'User-Agent: StarMarket-Bot/1.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}

/**
 * Vérifier si les notifications Discord sont configurées
 */
function isDiscordConfigured() {
    return !empty(DISCORD_BOT_TOKEN);
}

/**
 * Tester la connexion Discord
 */
function testDiscordConnection() {
    if (!isDiscordConfigured()) {
        return ['success' => false, 'message' => 'Token Discord non configuré'];
    }
    
    $url = DISCORD_API_URL . '/users/@me';
    
    $headers = [
        'Authorization: Bot ' . DISCORD_BOT_TOKEN,
        'User-Agent: StarMarket-Bot/1.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $bot_info = json_decode($response, true);
        return [
            'success' => true, 
            'message' => 'Connexion réussie',
            'bot_name' => $bot_info['username'] ?? 'StarMarket Bot'
        ];
    }
    
    return ['success' => false, 'message' => 'Erreur de connexion Discord'];
}
?>