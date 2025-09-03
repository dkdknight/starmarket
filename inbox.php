<?php
$page_title = 'Messagerie';
require_once 'header.php';

// Vérifier l'authentification
requireLogin();

// Récupérer les conversations
$stmt = $pdo->prepare("
    SELECT c.*, 
           l.id as listing_id,
           i.name as item_name, i.image_url as item_image,
           iv.variant_name, iv.color_name,
           CASE 
               WHEN c.buyer_id = ? THEN seller.username
               ELSE buyer.username
           END as other_user,
           CASE 
               WHEN c.buyer_id = ? THEN seller.rating_avg
               ELSE buyer.rating_avg
           END as other_user_rating,
           (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_date,
           (SELECT sender_id FROM messages m WHERE m.conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_sender_id,
           (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = FALSE) as unread_count
    FROM conversations c
    JOIN listings l ON c.listing_id = l.id
    JOIN items i ON l.item_id = i.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    JOIN users buyer ON c.buyer_id = buyer.id
    JOIN users seller ON c.seller_id = seller.id
    WHERE (c.buyer_id = ? OR c.seller_id = ?) 
    AND buyer.is_banned = FALSE AND seller.is_banned = FALSE
    ORDER BY last_message_date DESC
");

$user_id = $current_user['id'];
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$conversations = $stmt->fetchAll();

// Statistiques
$total_conversations = count($conversations);
$unread_conversations = count(array_filter($conversations, fn($c) => $c['unread_count'] > 0));
$active_conversations = count(array_filter($conversations, fn($c) => $c['status'] === 'OPEN'));
?>

<div class="container">
    <div class="inbox-header">
        <h1>📬 Messagerie</h1>
        <p>Gérez vos conversations avec les acheteurs et vendeurs</p>
    </div>

    <!-- Statistiques rapides -->
    <div class="inbox-stats">
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-icon">💬</span>
                <div class="stat-content">
                    <span class="stat-number"><?= $total_conversations ?></span>
                    <span class="stat-label">Conversations</span>
                </div>
            </div>
            <div class="stat-item">
                <span class="stat-icon">📩</span>
                <div class="stat-content">
                    <span class="stat-number"><?= $unread_conversations ?></span>
                    <span class="stat-label">Non lues</span>
                </div>
            </div>
            <div class="stat-item">
                <span class="stat-icon">🟢</span>
                <div class="stat-content">
                    <span class="stat-number"><?= $active_conversations ?></span>
                    <span class="stat-label">Actives</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres rapides -->
    <div class="inbox-filters">
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterConversations('all')">
                Toutes les conversations
            </button>
            <button class="filter-tab" onclick="filterConversations('unread')">
                Non lues (<?= $unread_conversations ?>)
            </button>
            <button class="filter-tab" onclick="filterConversations('buying')">
                En tant qu'acheteur
            </button>
            <button class="filter-tab" onclick="filterConversations('selling')">
                En tant que vendeur
            </button>
        </div>
    </div>

    <!-- Liste des conversations -->
    <?php if (!empty($conversations)): ?>
    <div class="conversations-list">
        <?php foreach ($conversations as $conversation): ?>
        <div class="conversation-item" 
             data-status="<?= $conversation['status'] ?>"
             data-role="<?= $conversation['buyer_id'] === $user_id ? 'buying' : 'selling' ?>"
             data-unread="<?= $conversation['unread_count'] > 0 ? 'true' : 'false' ?>">
            
            <div class="conversation-image">
                <img src="<?= sanitizeOutput($conversation['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                     alt="<?= sanitizeOutput($conversation['item_name']) ?>">
                
                <?php if ($conversation['unread_count'] > 0): ?>
                <div class="unread-badge"><?= $conversation['unread_count'] ?></div>
                <?php endif; ?>
            </div>

            <div class="conversation-content">
                <div class="conversation-header">
                    <h3 class="conversation-title">
                        <a href="conversation.php?id=<?= $conversation['id'] ?>">
                            <?= sanitizeOutput($conversation['item_name']) ?>
                            <?php if ($conversation['variant_name']): ?>
                            <span class="variant-info">
                                - <?= sanitizeOutput($conversation['variant_name']) ?>
                                <?php if ($conversation['color_name']): ?>
                                (<?= sanitizeOutput($conversation['color_name']) ?>)
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </h3>
                    
                    <div class="conversation-meta">
                        <span class="conversation-role">
                            <?php if ($conversation['buyer_id'] === $user_id): ?>
                            🛒 Vous achetez
                            <?php else: ?>
                            💰 Vous vendez
                            <?php endif; ?>
                        </span>
                        
                        <span class="conversation-status status-<?= strtolower($conversation['status']) ?>">
                            <?php
                            switch ($conversation['status']) {
                                case 'OPEN':
                                    echo '🟢 Ouverte';
                                    break;
                                case 'SCHEDULED':
                                    echo '📅 RDV programmé';
                                    break;
                                case 'DONE':
                                    echo '✅ Terminée';
                                    break;
                                case 'DISPUTED':
                                    echo '⚠️ Litige';
                                    break;
                                case 'CLOSED':
                                    echo '🔒 Fermée';
                                    break;
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <div class="conversation-preview">
                    <div class="other-user">
                        <span class="user-name">
                            <?= $conversation['buyer_id'] === $user_id ? 'Vendeur' : 'Acheteur' ?> : 
                            <a href="profile.php?u=<?= urlencode($conversation['other_user']) ?>">
                                <?= sanitizeOutput($conversation['other_user']) ?>
                            </a>
                        </span>
                        <?php if ($conversation['other_user_rating'] > 0): ?>
                        <span class="user-rating">⭐ <?= number_format($conversation['other_user_rating'], 1) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($conversation['last_message']): ?>
                    <div class="last-message">
                        <?php if ($conversation['last_sender_id'] === $user_id): ?>
                        <span class="message-sender">Vous:</span>
                        <?php endif; ?>
                        <span class="message-preview">
                            <?= sanitizeOutput(substr($conversation['last_message'], 0, 100)) ?>
                            <?= strlen($conversation['last_message']) > 100 ? '...' : '' ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="conversation-actions">
                <div class="conversation-date">
                    <?php
                    if ($conversation['last_message_date']) {
                        $time_diff = time() - strtotime($conversation['last_message_date']);
                        if ($time_diff < 60) {
                            echo 'À l\'instant';
                        } elseif ($time_diff < 3600) {
                            echo floor($time_diff / 60) . ' min';
                        } elseif ($time_diff < 86400) {
                            echo floor($time_diff / 3600) . 'h';
                        } elseif ($time_diff < 604800) {
                            echo floor($time_diff / 86400) . 'j';
                        } else {
                            echo date('d/m/Y', strtotime($conversation['last_message_date']));
                        }
                    }
                    ?>
                </div>
                
                <a href="conversation.php?id=<?= $conversation['id'] ?>" 
                   class="btn btn-primary btn-sm">
                    💬 Ouvrir
                </a>
                
                <a href="item.php?id=<?= $conversation['listing_id'] ?>" 
                   class="btn btn-outline btn-sm">
                    👁️ Voir l'annonce
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="empty-inbox">
        <div class="empty-content">
            <span class="empty-icon">📭</span>
            <h3>Aucune conversation</h3>
            <p>Vous n'avez pas encore de conversations. Contactez des vendeurs ou créez vos propres annonces !</p>
            <div class="empty-actions">
                <a href="browse.php" class="btn btn-primary">Parcourir les annonces</a>
                <a href="sell.php" class="btn btn-success">Créer une annonce</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.inbox-header {
    margin-bottom: 2rem;
    text-align: center;
}

.inbox-stats {
    margin-bottom: 2rem;
}

.stats-row {
    display: flex;
    justify-content: center;
    gap: 2rem;
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stat-icon {
    font-size: 1.5rem;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--primary);
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.inbox-filters {
    margin-bottom: 2rem;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: center;
}

.filter-tab {
    padding: 0.5rem 1rem;
    background: none;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
}

.filter-tab:hover {
    background-color: var(--bg-hover);
    color: var(--text-primary);
}

.filter-tab.active {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.conversations-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.conversation-item {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 1.5rem;
    align-items: center;
    transition: all 0.2s;
}

.conversation-item:hover {
    border-color: var(--border-light);
    box-shadow: var(--shadow);
}

.conversation-item[data-unread="true"] {
    border-left: 4px solid var(--primary);
    background-color: rgba(14, 165, 233, 0.02);
}

.conversation-image {
    position: relative;
}

.conversation-image img {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: var(--radius);
}

.unread-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: var(--error);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    font-weight: bold;
}

.conversation-content {
    flex: 1;
}

.conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.conversation-title a {
    color: var(--text-primary);
    text-decoration: none;
    font-size: 1.125rem;
    font-weight: 500;
}

.conversation-title a:hover {
    color: var(--primary);
}

.variant-info {
    color: var(--text-muted);
    font-size: 0.875rem;
    font-weight: normal;
}

.conversation-meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-align: right;
}

.conversation-role {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.conversation-status {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: var(--radius);
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

.conversation-preview {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.other-user {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.user-name a {
    color: var(--primary);
    text-decoration: none;
}

.user-name a:hover {
    text-decoration: underline;
}

.user-rating {
    color: var(--accent);
    font-size: 0.75rem;
}

.last-message {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.message-sender {
    font-weight: 500;
    color: var(--text-primary);
}

.conversation-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.conversation-date {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: center;
}

.empty-inbox {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    display: block;
    margin-bottom: 1rem;
}

.empty-content h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.empty-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

.empty-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

/* Styles pour les filtres */
.conversation-item.hidden {
    display: none;
}

@media (max-width: 1024px) {
    .conversation-item {
        grid-template-columns: 60px 1fr;
        gap: 1rem;
    }
    
    .conversation-actions {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }
}

@media (max-width: 768px) {
    .stats-row {
        flex-direction: column;
        text-align: center;
    }
    
    .filter-tabs {
        flex-direction: column;
    }
    
    .conversation-header {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .conversation-meta {
        text-align: left;
    }
    
    .empty-actions {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<script>
function filterConversations(filter) {
    const conversations = document.querySelectorAll('.conversation-item');
    const tabs = document.querySelectorAll('.filter-tab');
    
    // Update active tab
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter conversations
    conversations.forEach(conversation => {
        let show = false;
        
        switch (filter) {
            case 'all':
                show = true;
                break;
            case 'unread':
                show = conversation.dataset.unread === 'true';
                break;
            case 'buying':
                show = conversation.dataset.role === 'buying';
                break;
            case 'selling':
                show = conversation.dataset.role === 'selling';
                break;
        }
        
        if (show) {
            conversation.classList.remove('hidden');
        } else {
            conversation.classList.add('hidden');
        }
    });
}

// Auto-refresh pour les nouveaux messages (toutes les 60 secondes)
setInterval(function() {
    fetch('api/check-new-messages.php')
        .then(response => response.json())
        .then(data => {
            if (data.hasNew) {
                location.reload();
            }
        })
        .catch(error => console.error('Erreur lors de la vérification des nouveaux messages:', error));
}, 60000);
</script>

<?php require_once 'footer.php'; ?>