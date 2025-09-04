<?php
$page_title = 'Paramètres Discord';
require_once 'header.php';

// Vérifier l'authentification
requireLogin();

// Vérifier si Discord est configuré
require_once 'includes/discord-notification.php';
$discord_configured = isDiscordConfigured();

// Récupérer les informations Discord de l'utilisateur
$stmt = $pdo->prepare("SELECT discord_user_id, discord_notifications FROM users WHERE id = ?");
$stmt->execute([$current_user['id']]);
$discord_info = $stmt->fetch();

$has_discord = !empty($discord_info['discord_user_id']);
$notifications_enabled = (bool)$discord_info['discord_notifications'];

// Récupérer les logs Discord récents
$stmt = $pdo->prepare("
    SELECT * FROM discord_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$current_user['id']]);
$discord_logs = $stmt->fetchAll();
?>

<div class="container">
    <div class="discord-settings-header">
        <div class="breadcrumb">
            <a href="profile.php?u=<?= urlencode($current_user['username']) ?>">← Retour au profil</a>
        </div>
        
        <h1>🔗 Paramètres Discord</h1>
        <p>Liez votre compte Discord pour recevoir des notifications en temps réel</p>
    </div>

    <?php if (!$discord_configured): ?>
    <div class="alert alert-warning">
        <h3>⚠️ Discord non configuré</h3>
        <p>Les notifications Discord ne sont pas disponibles car le bot n'est pas configuré sur ce serveur.</p>
        <p>Contactez l'administrateur pour plus d'informations.</p>
    </div>
    <?php else: ?>
    
    <!-- État actuel -->
    <div class="discord-status-card">
        <div class="status-header">
            <h2>📊 État de la liaison Discord</h2>
        </div>
        
        <div class="status-content">
            <?php if ($has_discord): ?>
            <div class="status-connected">
                <div class="status-icon">✅</div>
                <div class="status-info">
                    <h3>Compte lié</h3>
                    <p>ID Discord: <code><?= sanitizeOutput($discord_info['discord_user_id']) ?></code></p>
                    <p>Notifications: 
                        <span class="status-badge <?= $notifications_enabled ? 'enabled' : 'disabled' ?>">
                            <?= $notifications_enabled ? '🔔 Activées' : '🔕 Désactivées' ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="status-actions">
                <button id="test-notification-btn" class="btn btn-primary">
                    🧪 Tester les notifications
                </button>
                
                <button id="toggle-notifications-btn" class="btn btn-outline">
                    <?= $notifications_enabled ? '🔕 Désactiver' : '🔔 Activer' ?> les notifications
                </button>
                
                <button id="unlink-discord-btn" class="btn btn-error">
                    🔗 Délier le compte
                </button>
            </div>
            
            <?php else: ?>
            <div class="status-disconnected">
                <div class="status-icon">❌</div>
                <div class="status-info">
                    <h3>Aucun compte lié</h3>
                    <p>Vous ne recevez pas de notifications Discord</p>
                </div>
            </div>
            
            <div class="link-discord-section">
                <h3>🔗 Lier votre compte Discord</h3>
                
                <div class="instructions">
                    <h4>Comment obtenir votre ID Discord :</h4>
                    <ol>
                        <li>Ouvrez Discord et allez dans Paramètres utilisateur (⚙️)</li>
                        <li>Dans l'onglet "Avancé", activez le "Mode développeur"</li>
                        <li>Retournez sur votre profil, clic droit sur votre nom, puis "Copier l'ID"</li>
                    </ol>
                </div>
                
                <form id="link-discord-form" class="link-form">
                    <div class="form-group">
                        <label for="discord_user_id" class="form-label">ID Utilisateur Discord *</label>
                        <input type="text" 
                               id="discord_user_id" 
                               name="discord_user_id" 
                               class="form-input" 
                               placeholder="123456789012345678"
                               pattern="[0-9]{17,19}"
                               title="L'ID Discord doit contenir entre 17 et 19 chiffres"
                               required>
                        <div class="form-help">
                            Format: 17-19 chiffres (ex: 123456789012345678)
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            🔗 Lier mon compte Discord
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Informations sur les notifications -->
    <div class="notifications-info">
        <h2>📩 Types de notifications Discord</h2>
        <div class="notification-types">
            <div class="notification-type">
                <div class="type-icon">💬</div>
                <div class="type-content">
                    <h4>Nouveaux messages</h4>
                    <p>Recevez une notification quand quelqu'un vous envoie un message</p>
                </div>
            </div>
            
            <div class="notification-type">
                <div class="type-icon">🔄</div>
                <div class="type-content">
                    <h4>Changements de statut</h4>
                    <p>Soyez informé des changements de statut de vos transactions</p>
                </div>
            </div>
            
            <div class="notification-type">
                <div class="type-icon">⭐</div>
                <div class="type-content">
                    <h4>Nouveaux avis</h4>
                    <p>Recevez vos avis dès qu'ils sont publiés</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Historique des notifications -->
    <?php if (!empty($discord_logs)): ?>
    <div class="discord-logs">
        <h2>📋 Historique des notifications</h2>
        <div class="logs-list">
            <?php foreach ($discord_logs as $log): ?>
            <div class="log-item">
                <div class="log-status">
                    <span class="status-indicator <?= $log['success'] ? 'success' : 'error' ?>">
                        <?= $log['success'] ? '✅' : '❌' ?>
                    </span>
                </div>
                
                <div class="log-content">
                    <div class="log-type"><?= sanitizeOutput($log['notification_type']) ?></div>
                    <div class="log-date"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></div>
                    <?php if (!$log['success'] && $log['error_message']): ?>
                    <div class="log-error"><?= sanitizeOutput($log['error_message']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<style>
.discord-settings-header {
    text-align: center;
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

.discord-status-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
}

.status-header h2 {
    color: var(--text-primary);
    margin-bottom: 1.5rem;
}

.status-connected, .status-disconnected {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.status-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.status-info h3 {
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.status-info p {
    color: var(--text-secondary);
    margin: 0.25rem 0;
}

.status-info code {
    background-color: var(--bg-tertiary);
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-family: monospace;
    font-size: 0.875rem;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.875rem;
    font-weight: 500;
}

.status-badge.enabled {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.disabled {
    background-color: rgba(107, 114, 128, 0.1);
    color: var(--text-muted);
}

.status-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.link-discord-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.link-discord-section h3 {
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.instructions {
    background-color: var(--bg-tertiary);
    padding: 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 2rem;
}

.instructions h4 {
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.instructions ol {
    color: var(--text-secondary);
    margin: 0;
    padding-left: 1.5rem;
}

.instructions li {
    margin-bottom: 0.5rem;
}

.link-form {
    max-width: 400px;
}

.form-help {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.notifications-info {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
}

.notifications-info h2 {
    color: var(--text-primary);
    margin-bottom: 1.5rem;
}

.notification-types {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.notification-type {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    padding: 1rem;
    background-color: var(--bg-tertiary);
    border-radius: var(--radius);
}

.type-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.type-content h4 {
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.type-content p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 0.875rem;
}

.discord-logs {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
}

.discord-logs h2 {
    color: var(--text-primary);
    margin-bottom: 1.5rem;
}

.logs-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.log-item {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    padding: 1rem;
    background-color: var(--bg-tertiary);
    border-radius: var(--radius);
}

.log-status {
    flex-shrink: 0;
}

.status-indicator {
    font-size: 1.25rem;
}

.log-content {
    flex: 1;
}

.log-type {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.log-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.log-error {
    font-size: 0.75rem;
    color: var(--error);
    margin-top: 0.25rem;
}

@media (max-width: 768px) {
    .status-connected, .status-disconnected {
        flex-direction: column;
        text-align: center;
    }
    
    .status-actions {
        justify-content: center;
        flex-direction: column;
    }
    
    .notification-types {
        grid-template-columns: 1fr;
    }
    
    .log-item {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const linkForm = document.getElementById('link-discord-form');
    const testBtn = document.getElementById('test-notification-btn');
    const toggleBtn = document.getElementById('toggle-notifications-btn');
    const unlinkBtn = document.getElementById('unlink-discord-btn');
    
    // Fonction pour faire un appel API
    async function apiCall(action, data = {}) {
        try {
            const response = await fetch('api/discord-link.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    csrf_token: '<?= generateCSRFToken() ?>',
                    ...data
                })
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Erreur API:', error);
            return { success: false, message: 'Erreur de connexion' };
        }
    }
    
    // Lier le compte Discord
    if (linkForm) {
        linkForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Liaison en cours...';
            
            const discordUserId = document.getElementById('discord_user_id').value;
            
            const result = await apiCall('link_discord', {
                discord_user_id: discordUserId
            });
            
            if (result.success) {
                alert(result.message + (result.test_sent ? '\nMessage de test envoyé !' : '\nAttention: Le message de test n\'a pas pu être envoyé.'));
                location.reload();
            } else {
                alert('Erreur: ' + result.message);
            }
            
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    }
    
    // Tester les notifications
    if (testBtn) {
        testBtn.addEventListener('click', async function() {
            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = 'Envoi en cours...';
            
            const result = await apiCall('test_notification');
            
            if (result.success) {
                alert('Message de test envoyé avec succès ! Vérifiez vos DM Discord.');
            } else {
                alert('Erreur: ' + result.message);
            }
            
            this.disabled = false;
            this.textContent = originalText;
        });
    }
    
    // Basculer les notifications
    if (toggleBtn) {
        toggleBtn.addEventListener('click', async function() {
            const isCurrentlyEnabled = this.textContent.includes('Désactiver');
            
            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = 'Mise à jour...';
            
            const result = await apiCall('toggle_notifications', {
                enabled: !isCurrentlyEnabled
            });
            
            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Erreur: ' + result.message);
            }
            
            this.disabled = false;
            this.textContent = originalText;
        });
    }
    
    // Délier le compte
    if (unlinkBtn) {
        unlinkBtn.addEventListener('click', async function() {
            if (!confirm('Êtes-vous sûr de vouloir délier votre compte Discord ?\nVous ne recevrez plus de notifications.')) {
                return;
            }
            
            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = 'Suppression...';
            
            const result = await apiCall('unlink_discord');
            
            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Erreur: ' + result.message);
            }
            
            this.disabled = false;
            this.textContent = originalText;
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>