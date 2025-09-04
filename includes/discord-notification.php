<?php
// ============================================================================
// StarMarket — Notifications Discord (DM via REST)
// ============================================================================

// ⚠️ Sécurité : ne commit JAMAIS un token réel. Utilise un fichier ignoré (.env/env.local.php)
// OU une variable d'environnement.
define('DISCORD_BOT_TOKEN', 'MTQxMjg2MzU4MjIzNjA1MzUwNA.GM4ov1.DNJRFISEy243uzY7gsAy2bkDFWeUfrL7-WXpXA'); // ← change ton token compromis
define('DISCORD_API_URL', 'https://discord.com/api/v10');

// (optionnel) journal de debug
define('DISCORD_DEBUG_LOG', __DIR__ . '/../logs/discord.log');

// (OBLIGATOIRE) chemin ABSOLU vers cacert.pem (bundle CA)
// Mets-y le chemin où TU as placé le fichier téléchargé depuis https://curl.se/ca/cacert.pem
// Exemple Windows WAMP : C:/wamp64/cacert/cacert.pem
// Exemple Linux : /etc/ssl/certs/cacert.pem
define('DISCORD_CA_BUNDLE_PATH', 'C:/wamp64/cacert/cacert.pem'); // ← ADAPTE CE CHEMIN

// ----------------------------------------------------------------------------
// Utils logging
function discord_log($data) {
    if (!DISCORD_DEBUG_LOG) return;
    @mkdir(dirname(DISCORD_DEBUG_LOG), 0777, true);
    $line = '[' . date('c') . '] ' . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE)) . PHP_EOL;
    @file_put_contents(DISCORD_DEBUG_LOG, $line, FILE_APPEND);
}

// ----------------------------------------------------------------------------
// Résolution du bundle CA : renvoie un chemin absolu lisible, ou null
function discord_resolve_ca_bundle() {
    // 1) php.ini
    $candidates = [];
    if ($iniCurl = ini_get('curl.cainfo'))      $candidates[] = $iniCurl;
    if ($iniOpen = ini_get('openssl.cafile'))   $candidates[] = $iniOpen;

    // 2) constante configurée ici
    if (defined('DISCORD_CA_BUNDLE_PATH') && DISCORD_CA_BUNDLE_PATH) {
        $candidates[] = DISCORD_CA_BUNDLE_PATH;
    }

    // 3) fallback projet (si tu veux déposer le fichier dans app/config)
    $candidates[] = __DIR__ . '/../config/cacert.pem';

    foreach ($candidates as $c) {
        $rp = @realpath($c);
        if ($rp && is_readable($rp) && filesize($rp) > 100000) { // ~ > 100KB pour un bundle complet
            return $rp;
        }
    }
    return null;
}

// ----------------------------------------------------------------------------
// HTTP wrapper POST (gère 429 avec 1 retry, suit redirections)
function discord_http_post($endpoint, array $payload, $retryOn429 = true) {
    if (!DISCORD_BOT_TOKEN) return [0, null, 'Missing bot token'];

    $url = (strpos($endpoint, 'http') === 0)
        ? $endpoint
        : rtrim(DISCORD_API_URL, '/') . '/' . ltrim($endpoint, '/');

    $ca = discord_resolve_ca_bundle();
    if (!$ca) {
        discord_log(['error' => 'CA bundle introuvable/illisible', 'cainfo_ini' => ini_get('curl.cainfo'), 'cafile_ini' => ini_get('openssl.cafile'), 'fallback' => __DIR__ . '/../config/cacert.pem', 'const' => (defined('DISCORD_CA_BUNDLE_PATH') ? DISCORD_CA_BUNDLE_PATH : null)]);
        return [0, null, 'CA bundle not found'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bot ' . DISCORD_BOT_TOKEN,
            'Content-Type: application/json',
            'User-Agent: StarMarket-Bot/1.0 (+starmarket)'
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 20,

        // SSL vérif + bundle
        CURLOPT_CAINFO         => $ca,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,

        // Suivre 301/302/303 et conserver POST/corps
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_POSTREDIR      => 7,
    ]);

    $response  = curl_exec($ch);
    $curlErr   = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 429 → retry une fois après "retry_after"
    if ($httpCode == 429 && $retryOn429) {
        $bodyArr = json_decode($response, true);
        $retryAfter = isset($bodyArr['retry_after']) ? (float)$bodyArr['retry_after'] : 1.5;
        usleep((int)($retryAfter * 1_000_000));
        return discord_http_post($endpoint, $payload, false);
    }

    return [$httpCode, $response, $curlErr];
}

// ----------------------------------------------------------------------------
// Créer (ou récupérer) un canal DM avec un utilisateur (discord_user_id)
function createDMChannel($discord_user_id) {
    if (!preg_match('/^\d+$/', (string)$discord_user_id)) {
        discord_log(['error' => 'Invalid discord_user_id', 'value' => $discord_user_id]);
        return false;
    }

    [$code, $body, $err] = discord_http_post('users/@me/channels', ['recipient_id' => (string)$discord_user_id]);

    discord_log(['step' => 'create_dm', 'http' => $code, 'resp' => json_decode($body, true), 'curl_error' => $err]);

    if ($code === 200 || $code === 201) {
        $data = json_decode($body, true);
        return $data; // ['id' => '...']
    }

    // 401 => token; 403+50007 => DMs bloqués; 400/404 => mauvais ID; 0 => SSL / réseau
    return false;
}

// ----------------------------------------------------------------------------
// Envoyer un message (contenu/embeds/components) dans un salon (DM) existant
function sendDiscordMessageToChannel($channel_id, array $message) {
    if (!preg_match('/^\d+$/', (string)$channel_id)) {
        discord_log(['error' => 'Invalid channel_id', 'value' => $channel_id]);
        return false;
    }

    [$code, $body, $err] = discord_http_post("channels/{$channel_id}/messages", $message);

    discord_log(['step' => 'send_message', 'http' => $code, 'resp' => json_decode($body, true), 'curl_error' => $err]);

    return ($code === 200 || $code === 201);
}

// ----------------------------------------------------------------------------
// Bâtit le payload Discord selon le type de notif
function buildDiscordMessage(array $data) {
    $data += [
        'sender'           => '',
        'message_preview'  => '',
        'conversation_url' => '',
        'item_name'        => '',
        'new_status'       => '',
        'meeting_details'  => '',
        'username'         => '',
        'message'          => '',
        'timestamp'        => date('c'),
        'stars'            => 0,
        'role'             => '',
        'profile_url'      => (defined('SITE_URL') ? SITE_URL : '')
    ];

    switch ($data['type'] ?? '') {
        case 'new_message':
            $preview = mb_substr($data['message_preview'], 0, 200);
            return [
                'embeds' => [[
                    'title'       => '💬 Nouveau message sur StarMarket',
                    'description' => "**{$data['sender']}** vous a envoyé un message :",
                    'fields'      => [[ 'name' => 'Message', 'value' => $preview . (mb_strlen($data['message_preview']) > 200 ? '…' : '') ]],
                    'color'       => 0x0ea5e9,
                    'footer'      => ['text' => 'StarMarket - Marketplace Star Citizen'],
                    'timestamp'   => date('c'),
                ]],
                'components' => [[
                    'type' => 1,
                    'components' => [[ 'type' => 2, 'style' => 5, 'label' => 'Répondre sur StarMarket', 'url' => $data['conversation_url'] ]]
                ]]
            ];

        case 'status_change':
            $status_emoji = [ 'SCHEDULED'=>'📅','DONE'=>'✅','DISPUTED'=>'⚠️','CLOSED'=>'🔒','OPEN'=>'🟢' ];
            $status_names = [ 'OPEN'=>'Ouverte','SCHEDULED'=>'RDV Programmé','DONE'=>'Transaction Terminée','DISPUTED'=>'Litige','CLOSED'=>'Fermée' ];
            $embed = [
                'title'       => ($status_emoji[$data['new_status']] ?? '🔄').' Statut de conversation mis à jour',
                'description' => "La conversation pour **{$data['item_name']}** a changé de statut.",
                'fields'      => [[ 'name'=>'Nouveau statut', 'value'=>$status_names[$data['new_status']] ?? $data['new_status'], 'inline'=>true ]],
                'color'       => $data['new_status'] === 'DONE' ? 0x10b981 : 0xf59e0b,
                'footer'      => ['text' => 'StarMarket - Marketplace Star Citizen'],
                'timestamp'   => date('c'),
            ];
            if (!empty($data['meeting_details'])) $embed['fields'][] = ['name'=>'Détails','value'=>$data['meeting_details']];
            if ($data['new_status'] === 'DONE')   $embed['fields'][] = ['name'=>'⭐ Évaluation','value'=>'Vous pouvez maintenant laisser un avis sur cette transaction !'];
            return [
                'embeds' => [ $embed ],
                'components' => [[ 'type'=>1, 'components'=>[[ 'type'=>2,'style'=>5,'label'=>'Voir la conversation','url'=>$data['conversation_url'] ]] ]]
            ];

        case 'discord_linked':
            return [
                'embeds' => [[
                    'title'       => '🔗 Compte Discord lié avec succès !',
                    'description' => "Félicitations **{$data['username']}** ! Votre compte Discord est maintenant lié à StarMarket.",
                    'fields'      => [[ 'name'=>'📩 Notifications activées', 'value'=>"Vous recevrez désormais des notifications pour :\n• Nouveaux messages\n• Changements de statut\n• Nouveaux avis" ]],
                    'color'       => 0x10b981,
                    'footer'      => ['text' => 'StarMarket - Marketplace Star Citizen'],
                    'timestamp'   => date('c'),
                ]],
                'components' => [[ 'type'=>1,'components'=>[[ 'type'=>2,'style'=>5,'label'=>'Accéder à StarMarket', 'url'=>(defined('SITE_URL')?SITE_URL:'#') ]] ]]
            ];

        case 'test_notification':
            return [
                'embeds' => [[
                    'title'=>'🧪 Message de test StarMarket',
                    'description'=>$data['message'],
                    'fields'=>[
                        ['name'=>'Heure du test','value'=>$data['timestamp'],'inline'=>true],
                        ['name'=>'Statut','value'=>'✅ Connexion fonctionnelle','inline'=>true]
                    ],
                    'color'=>0x0ea5e9,
                    'footer'=>['text'=>'StarMarket - Test de notification'],
                    'timestamp'=>date('c'),
                ]]
            ];

        case 'new_review':
            $stars = (int)max(0, min(5, $data['stars']));
            $stars_display = str_repeat('⭐', $stars) . str_repeat('☆', 5 - $stars);
            return [
                'embeds' => [[
                    'title'=>'⭐ Nouvel avis reçu sur StarMarket',
                    'description'=>"**{$data['reviewer']}** a laissé un avis sur votre transaction !",
                    'fields'=>[
                        ['name'=>'Item','value'=>$data['item_name'],'inline'=>true],
                        ['name'=>'Note','value'=>"$stars_display ({$stars}/5)",'inline'=>true],
                        ['name'=>'Rôle','value'=>($data['role']==='SELLER'?'En tant que vendeur':'En tant qu’acheteur'),'inline'=>true],
                    ],
                    'color'=> $stars >= 4 ? 0x10b981 : ($stars >= 3 ? 0xf59e0b : 0xef4444),
                    'footer'=>['text'=>'StarMarket - Marketplace Star Citizen'],
                    'timestamp'=>date('c')
                ]],
                'components'=>[[ 'type'=>1,'components'=>[[ 'type'=>2,'style'=>5,'label'=>'Voir votre profil','url'=>$data['profile_url'] ]] ]]
            ];

        default:
            return [ 'content' => '🔔 Vous avez une nouvelle notification sur StarMarket !' ];
    }
}

// ----------------------------------------------------------------------------
// API principale : envoyer une notif DM à un utilisateur Discord
function sendDiscordMessage($discord_user_id, array $notification_data) {
    if (!DISCORD_BOT_TOKEN || empty($discord_user_id)) {
        discord_log('sendDiscordMessage: missing token or discord_user_id');
        return false;
    }

    try {
        $dm = createDMChannel($discord_user_id);
        if (!$dm || empty($dm['id'])) {
            discord_log('createDMChannel failed');
            return false;
        }
        $message = buildDiscordMessage($notification_data);

        $ok = sendDiscordMessageToChannel($dm['id'], $message);
        if (!$ok) discord_log('sendDiscordMessageToChannel failed');

        return $ok;

    } catch (Throwable $e) {
        discord_log(['exception' => $e->getMessage()]);
        return false;
    }
}

// ----------------------------------------------------------------------------
function isDiscordConfigured() {
    return (bool)DISCORD_BOT_TOKEN;
}

function testDiscordConnection() {
    if (!isDiscordConfigured()) {
        return ['success' => false, 'message' => 'Token Discord non configuré'];
    }

    $ca = discord_resolve_ca_bundle();
    if (!$ca) {
        discord_log(['step'=>'whoami','error'=>'CA bundle introuvable']);
        return ['success'=>false,'message'=>'CA bundle introuvable — configure DISCORD_CA_BUNDLE_PATH ou php.ini'];
        }

    $ch = curl_init(DISCORD_API_URL . '/users/@me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bot ' . DISCORD_BOT_TOKEN,
            'User-Agent' => 'StarMarket-Bot/1.0'
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CAINFO         => $ca,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $bot = json_decode($response, true);
        return ['success'=>true, 'message'=>'Connexion réussie', 'bot_name'=>$bot['username'] ?? 'Bot'];
    }
    discord_log(['step'=>'whoami','http'=>$code,'resp'=>json_decode($response,true)]);
    return ['success'=>false,'message'=>'Erreur de connexion Discord (voir logs)'];
}
