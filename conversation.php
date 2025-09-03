<?php
$page_title = 'Conversation';
require_once 'header.php';

// Vérifier l'authentification
requireLogin();

$conversation_id = (int)($_GET['id'] ?? 0);
$errors = [];

if (!$conversation_id) {
    header('Location: inbox.php');
    exit;
}

// Récupérer la conversation
$stmt = $pdo->prepare("
    SELECT c.*, 
           l.id as listing_id, l.price_real, l.currency, l.price_auec, l.sale_type,
           l.region, l.meet_location, l.availability, l.notes as listing_notes,
           i.name as item_name, i.image_url as item_image,
           iv.variant_name, iv.color_name,
           buyer.username as buyer_username, buyer.rating_avg as buyer_rating,
           seller.username as seller_username, seller.rating_avg as seller_rating
    FROM conversations c
    JOIN listings l ON c.listing_id = l.id
    JOIN items i ON l.item_id = i.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    JOIN users buyer ON c.buyer_id = buyer.id
    JOIN users seller ON c.seller_id = seller.id
    WHERE c.id = ? AND (c.buyer_id = ? OR c.seller_id = ?)
    AND buyer.is_banned = FALSE AND seller.is_banned = FALSE
");

$stmt->execute([$conversation_id, $current_user['id'], $current_user['id']]);
$conversation = $stmt->fetch();

if (!$conversation) {
    header('Location: inbox.php?error=not_found');
    exit;
}

// Déterminer le rôle de l'utilisateur
$is_buyer = $conversation['buyer_id'] === $current_user['id'];
$is_seller = $conversation['seller_id'] === $current_user['id'];
$other_user = $is_buyer ? $conversation['seller_username'] : $conversation['buyer_username'];
$other_user_rating = $is_buyer ? $conversation['seller_rating'] : $conversation['buyer_rating'];

// Marquer les messages comme lus
$stmt = $pdo->prepare("
    UPDATE messages 
    SET is_read = TRUE 
    WHERE conversation_id = ? AND sender_id != ?
");
$stmt->execute([$conversation_id, $current_user['id']]);

// Récupérer les messages
$stmt = $pdo->prepare("
    SELECT m.*, u.username 
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$conversation_id]);
$messages = $stmt->fetchAll();

// Traitement du formulaire d'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validation CSRF
    if (!validateCSRFToken($csrf_token)) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        if ($action === 'send_message') {
            $message_body = trim($_POST['message'] ?? '');
            
            if (empty($message_body)) {
                $errors[] = 'Le message ne peut pas être vide.';
            } elseif (strlen($message_body) > 2000) {
                $errors[] = 'Le message ne peut pas dépasser 2000 caractères.';
            } else {
                // Envoyer le message
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO messages (conversation_id, sender_id, body) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$conversation_id, $current_user['id'], $message_body]);
                    
                    // Mettre à jour la conversation
                    $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$conversation_id]);
                    
                    header("Location: conversation.php?id={$conversation_id}&success=message_sent");
                    exit;
                    
                } catch (Exception $e) {
                    $errors[] = 'Erreur lors de l\'envoi du message.';
                }
            }
            
        } elseif ($action === 'update_status') {
            $new_status = $_POST['status'] ?? '';
            $meeting_details = trim($_POST['meeting_details'] ?? '');
            
            if (!in_array($new_status, ['OPEN', 'SCHEDULED', 'DONE', 'DISPUTED', 'CLOSED'])) {
                $errors[] = 'Statut invalide.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE conversations 
                        SET status = ?, meeting_details = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_status, $meeting_details, $conversation_id]);
                    
                    header("Location: conversation.php?id={$conversation_id}&success=status_updated");
                    exit;
                    
                } catch (Exception $e) {
                    $errors[] = 'Erreur lors de la mise à jour du statut.';
                }
            }
        }
    }
}

$page_title = "Conversation - {$conversation['item_name']}";
?>

<div class="container">
    <div class="conversation-header">
        <div class="breadcrumb">
            <a href="inbox.php">← Retour à la messagerie</a>
        </div>
        
        <div class="conversation-info">
            <div class="conversation-item-info">
                <img src="<?= sanitizeOutput($conversation['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                     alt="<?= sanitizeOutput($conversation['item_name']) ?>"
                     class="item-image">
                
                <div class="item-details">
                    <h1><?= sanitizeOutput($conversation['item_name']) ?></h1>
                    
                    <?php if ($conversation['variant_name']): ?>
                    <p class="variant-info">
                        <?= sanitizeOutput($conversation['variant_name']) ?>
                        <?php if ($conversation['color_name']): ?>
                        - <?= sanitizeOutput($conversation['color_name']) ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="price-info">
                        <?php if ($conversation['sale_type'] === 'REAL_MONEY'): ?>
                        <span class="price"><?= formatPrice($conversation['price_real'], $conversation['currency']) ?></span>
                        <span class="sale-type">💰 Argent réel</span>
                        <?php else: ?>
                        <span class="price"><?= formatPrice($conversation['price_auec'], 'aUEC') ?></span>
                        <span class="sale-type">🎮 In-Game</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="conversation-participants">
                <div class="participant">
                    <span class="role">🛒 Acheteur</span>
                    <a href="profile.php?u=<?= urlencode($conversation['buyer_username']) ?>" class="username">
                        <?= sanitizeOutput($conversation['buyer_username']) ?>
                        <?php if ($is_buyer): ?><span class="you">(Vous)</span><?php endif; ?>
                    </a>
                    <?php if ($conversation['buyer_rating'] > 0): ?>
                    <span class="rating">⭐ <?= number_format($conversation['buyer_rating'], 1) ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="participant">
                    <span class="role">💰 Vendeur</span>
                    <a href="profile.php?u=<?= urlencode($conversation['seller_username']) ?>" class="username">
                        <?= sanitizeOutput($conversation['seller_username']) ?>
                        <?php if ($is_seller): ?><span class="you">(Vous)</span><?php endif; ?>
                    </a>
                    <?php if ($conversation['seller_rating'] > 0): ?>
                    <span class="rating">⭐ <?= number_format($conversation['seller_rating'], 1) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statut de la conversation -->
    <div class="conversation-status-section">
        <div class="status-card">
            <div class="current-status">
                <span class="status-label">Statut actuel:</span>
                <span class="status-badge status-<?= strtolower($conversation['status']) ?>">
                    <?php
                    switch ($conversation['status']) {
                        case 'OPEN':
                            echo '🟢 Conversation ouverte';
                            break;
                        case 'SCHEDULED':
                            echo '📅 Rendez-vous programmé';
                            break;
                        case 'DONE':
                            echo '✅ Transaction terminée';
                            break;
                        case 'DISPUTED':
                            echo '⚠️ Litige en cours';
                            break;
                        case 'CLOSED':
                            echo '🔒 Conversation fermée';
                            break;
                    }
                    ?>
                </span>
            </div>
            
            <?php if ($conversation['meeting_details']): ?>
            <div class="meeting-details">
                <strong>Détails du RDV:</strong>
                <p><?= sanitizeOutput($conversation['meeting_details']) ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Actions de statut -->
            <div class="status-actions">
                <?php if ($conversation['status'] === 'OPEN'): ?>
                <button class="btn btn-outline btn-sm" onclick="showStatusModal('SCHEDULED')">
                    📅 Programmer un RDV
                </button>
                <?php endif; ?>
                
                <?php if ($conversation['status'] === 'SCHEDULED'): ?>
                <button class="btn btn-success btn-sm" onclick="showStatusModal('DONE')">
                    ✅ Marquer comme terminé
                </button>
                <?php endif; ?>
                
                <?php if (in_array($conversation['status'], ['OPEN', 'SCHEDULED'])): ?>
                <button class="btn btn-warning btn-sm" onclick="showStatusModal('DISPUTED')">
                    ⚠️ Signaler un problème
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <div class="messages-section">
        <div class="messages-container" id="messages-container">
            <?php if (empty($messages)): ?>
            <div class="no-messages">
                <p>Aucun message dans cette conversation.</p>
            </div>
            <?php else: ?>
            <?php foreach ($messages as $message): ?>
            <div class="message <?= $message['sender_id'] === $current_user['id'] ? 'message-own' : 'message-other' ?>">
                <div class="message-header">
                    <span class="message-sender">
                        <?= $message['sender_id'] === $current_user['id'] ? 'Vous' : sanitizeOutput($message['username']) ?>
                    </span>
                    <span class="message-date">
                        <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                    </span>
                </div>
                <div class="message-body">
                    <?= nl2br(sanitizeOutput($message['body'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulaire d'envoi de message -->
    <?php if (in_array($conversation['status'], ['OPEN', 'SCHEDULED'])): ?>
    <div class="message-form-section">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?= sanitizeOutput($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="message-form">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="send_message">
            
            <div class="form-group">
                <label for="message" class="form-label">Votre message</label>
                <textarea id="message" 
                          name="message" 
                          class="form-textarea" 
                          rows="4" 
                          placeholder="Tapez votre message..."
                          maxlength="2000"
                          required></textarea>
                <div class="char-counter">
                    <span id="char-count">0</span> / 2000 caractères
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    📩 Envoyer le Message
                </button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="conversation-closed">
        <p>Cette conversation est fermée. Aucun nouveau message ne peut être envoyé.</p>
    </div>
    <?php endif; ?>

    <!-- Informations supplémentaires -->
    <div class="conversation-sidebar">
        <div class="info-card">
            <h3>📋 Détails de l'annonce</h3>
            
            <?php if ($conversation['sale_type'] === 'REAL_MONEY'): ?>
            <div class="info-item">
                <strong>Type:</strong> Argent réel
            </div>
            <div class="info-item">
                <strong>Prix:</strong> <?= formatPrice($conversation['price_real'], $conversation['currency']) ?>
            </div>
            <div class="info-item">
                <strong>Région:</strong> <?= sanitizeOutput($conversation['region']) ?>
            </div>
            <?php else: ?>
            <div class="info-item">
                <strong>Type:</strong> In-Game (aUEC)
            </div>
            <div class="info-item">
                <strong>Prix:</strong> <?= formatPrice($conversation['price_auec'], 'aUEC') ?>
            </div>
            <div class="info-item">
                <strong>Lieu de RDV:</strong> <?= sanitizeOutput($conversation['meet_location']) ?>
            </div>
            <div class="info-item">
                <strong>Disponibilité:</strong> <?= sanitizeOutput($conversation['availability']) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($conversation['listing_notes']): ?>
            <div class="info-item">
                <strong>Notes du vendeur:</strong>
                <p><?= nl2br(sanitizeOutput($conversation['listing_notes'])) ?></p>
            </div>
            <?php endif; ?>
            
            <div class="info-actions">
                <a href="item.php?id=<?= $conversation['listing_id'] ?>" class="btn btn-outline btn-sm">
                    👁️ Voir l'annonce complète
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal de changement de statut -->
<div id="status-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Changer le statut</h3>
            <button type="button" class="modal-close" onclick="hideStatusModal()">&times;</button>
        </div>
        
        <form method="POST" id="status-form">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="status" id="new-status">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="meeting_details" class="form-label">Détails (optionnel)</label>
                    <textarea id="meeting_details" 
                              name="meeting_details" 
                              class="form-textarea" 
                              rows="3" 
                              placeholder="Ajoutez des détails sur le rendez-vous, problème, etc."></textarea>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Confirmer</button>
                <button type="button" class="btn btn-outline" onclick="hideStatusModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<style>
.conversation-header {
    margin-bottom: 2rem;
}

.breadcrumb {
    margin-bottom: 1rem;
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.875rem;
}

.conversation-info {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2rem;
    align-items: center;
}

.conversation-item-info {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.item-image {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: var(--radius);
}

.item-details h1 {
    margin-bottom: 0.5rem;
    font-size: 1.5rem;
}

.variant-info {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.price-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.price {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--primary);
}

.sale-type {
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.75rem;
    background-color: var(--bg-tertiary);
}

.conversation-participants {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.participant {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-align: right;
}

.role {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.username {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.username:hover {
    text-decoration: underline;
}

.you {
    color: var(--text-muted);
    font-weight: normal;
    font-size: 0.875rem;
}

.rating {
    color: var(--accent);
    font-size: 0.75rem;
}

.conversation-status-section {
    margin-bottom: 2rem;
}

.status-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.current-status {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.status-label {
    font-weight: 500;
    color: var(--text-secondary);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius);
    font-size: 0.875rem;
    font-weight: 500;
}

.status-open {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-scheduled {
    background-color: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-done {
    background-color: rgba(14, 165, 233, 0.1);
    color: var(--primary);
}

.status-disputed {
    background-color: rgba(239, 68, 68, 0.1);
    color: var(--error);
}

.status-closed {
    background-color: rgba(100, 116, 139, 0.1);
    color: var(--text-muted);
}

.meeting-details {
    margin-bottom: 1rem;
    padding: 1rem;
    background-color: var(--bg-tertiary);
    border-radius: var(--radius);
}

.meeting-details strong {
    color: var(--text-primary);
}

.meeting-details p {
    margin: 0.5rem 0 0 0;
    color: var(--text-secondary);
}

.status-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.messages-section {
    margin-bottom: 2rem;
}

.messages-container {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    max-height: 600px;
    overflow-y: auto;
}

.no-messages {
    text-align: center;
    color: var(--text-muted);
    padding: 2rem;
}

.message {
    margin-bottom: 1.5rem;
}

.message:last-child {
    margin-bottom: 0;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.message-sender {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.message-date {
    color: var(--text-muted);
    font-size: 0.75rem;
}

.message-body {
    background-color: var(--bg-tertiary);
    padding: 1rem;
    border-radius: var(--radius);
    line-height: 1.5;
}

.message-own .message-header {
    flex-direction: row-reverse;
}

.message-own .message-body {
    background-color: var(--primary);
    color: white;
    margin-left: 2rem;
}

.message-other .message-body {
    margin-right: 2rem;
}

.message-form-section {
    margin-bottom: 2rem;
}

.message-form {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.char-counter {
    text-align: right;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 1rem;
}

.conversation-closed {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-align: center;
    color: var(--text-muted);
}

.conversation-sidebar {
    position: sticky;
    top: 2rem;
}

.info-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.info-card h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.info-item {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.info-item:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
}

.info-item strong {
    color: var(--text-primary);
    display: block;
    margin-bottom: 0.25rem;
}

.info-item p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 0.875rem;
}

.info-actions {
    margin-top: 1.5rem;
    text-align: center;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted);
    cursor: pointer;
}

.modal-body {
    padding: 1.5rem;
}

.modal-actions {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

@media (max-width: 1024px) {
    .conversation-info {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .conversation-participants {
        text-align: left;
    }
    
    .participant {
        text-align: left;
    }
    
    .conversation-sidebar {
        position: static;
    }
}

@media (max-width: 768px) {
    .conversation-item-info {
        flex-direction: column;
        text-align: center;
    }
    
    .price-info {
        justify-content: center;
    }
    
    .status-actions {
        justify-content: center;
    }
    
    .message-own .message-body {
        margin-left: 0;
    }
    
    .message-other .message-body {
        margin-right: 0;
    }
    
    .modal-actions {
        flex-direction: column;
    }
}
</style>

<script>
// Compteur de caractères
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('message');
    const charCount = document.getElementById('char-count');
    
    if (textarea && charCount) {
        function updateCharCount() {
            const count = textarea.value.length;
            charCount.textContent = count;
            
            if (count > 2000) {
                charCount.style.color = 'var(--error)';
            } else if (count > 1600) {
                charCount.style.color = 'var(--warning)';
            } else {
                charCount.style.color = 'var(--text-muted)';
            }
        }
        
        textarea.addEventListener('input', updateCharCount);
        updateCharCount(); // Initial count
    }
    
    // Scroll vers le bas des messages
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
});

// Gestion du modal de statut
function showStatusModal(status) {
    const modal = document.getElementById('status-modal');
    const modalTitle = document.getElementById('modal-title');
    const newStatus = document.getElementById('new-status');
    const meetingDetails = document.getElementById('meeting_details');
    
    newStatus.value = status;
    
    switch (status) {
        case 'SCHEDULED':
            modalTitle.textContent = 'Programmer un rendez-vous';
            meetingDetails.placeholder = 'Détails du rendez-vous (date, heure, lieu précis...)';
            break;
        case 'DONE':
            modalTitle.textContent = 'Marquer comme terminé';
            meetingDetails.placeholder = 'Commentaires sur la transaction (optionnel)';
            break;
        case 'DISPUTED':
            modalTitle.textContent = 'Signaler un problème';
            meetingDetails.placeholder = 'Décrivez le problème rencontré...';
            break;
    }
    
    modal.style.display = 'flex';
}

function hideStatusModal() {
    const modal = document.getElementById('status-modal');
    modal.style.display = 'none';
}

// Fermer le modal en cliquant en dehors
document.addEventListener('click', function(e) {
    const modal = document.getElementById('status-modal');
    if (e.target === modal) {
        hideStatusModal();
    }
});

// Auto-refresh pour les nouveaux messages (toutes les 30 secondes)
setInterval(function() {
    const lastMessage = document.querySelector('.message:last-child');
    const lastMessageTime = lastMessage ? lastMessage.dataset.timestamp : 0;
    
    fetch(`api/check-conversation-updates.php?conversation_id=<?= $conversation_id ?>&last_message=${lastMessageTime}`)
        .then(response => response.json())
        .then(data => {
            if (data.hasNew) {
                location.reload();
            }
        })
        .catch(error => console.error('Erreur lors de la vérification des nouveaux messages:', error));
}, 30000);
</script>

<?php require_once 'footer.php'; ?>