<?php
$page_title = 'Contacter le vendeur';
require_once 'header.php';

// Vérifier l'authentification
requireLogin();

$listing_id = (int)($_GET['listing_id'] ?? 0);
$errors = [];

if (!$listing_id) {
    header('Location: browse.php');
    exit;
}

// Récupérer l'annonce
$stmt = $pdo->prepare("
    SELECT l.*, i.name as item_name, i.image_url as item_image,
           u.username as seller_username, u.id as seller_id, u.rating_avg,
           u.discord_user_id as seller_discord_id, u.discord_notifications as seller_discord_enabled,
           iv.variant_name, iv.color_name
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    WHERE l.id = ? AND l.status = 'ACTIVE' AND u.is_banned = FALSE
");
$stmt->execute([$listing_id]);
$listing = $stmt->fetch();

if (!$listing) {
    header('Location: browse.php?error=not_found');
    exit;
}

// Vérifier que ce n'est pas sa propre annonce
if ($listing['seller_id'] === $current_user['id']) {
    header('Location: my-listings.php');
    exit;
}

// Vérifier s'il existe déjà une conversation
$stmt = $pdo->prepare("
    SELECT id FROM conversations 
    WHERE listing_id = ? AND buyer_id = ?
");
$stmt->execute([$listing_id, $current_user['id']]);
$existing_conversation = $stmt->fetch();

if ($existing_conversation) {
    header('Location: conversation.php?id=' . $existing_conversation['id']);
    exit;
}

// Traitement du formulaire de contact
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validation CSRF
    if (!validateCSRFToken($csrf_token)) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        if (empty($message)) {
            $errors[] = 'Le message est requis.';
        }
        
        if (strlen($message) < 10) {
            $errors[] = 'Le message doit contenir au moins 10 caractères.';
        }
        
        if (strlen($message) > 1000) {
            $errors[] = 'Le message ne peut pas dépasser 1000 caractères.';
        }
        
        // Créer la conversation et envoyer le message
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Créer la conversation
                $stmt = $pdo->prepare("
                    INSERT INTO conversations (listing_id, buyer_id, seller_id) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$listing_id, $current_user['id'], $listing['seller_id']]);
                $conversation_id = $pdo->lastInsertId();
                
                // Envoyer le premier message
                $stmt = $pdo->prepare("
                    INSERT INTO messages (conversation_id, sender_id, body) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$conversation_id, $current_user['id'], $message]);
                
                $pdo->commit();

                if (!empty($listing['seller_discord_enabled']) && !empty($listing['seller_discord_id'])) {
                    require_once 'includes/discord-notification.php';
                    sendDiscordMessage($listing['seller_discord_id'], [
                        'type' => 'new_message',
                        'sender' => $current_user['username'],
                        'message_preview' => substr($message, 0, 100),
                        'conversation_url' => SITE_URL . "/conversation.php?id=" . $conversation_id
                    ]);
                }

                header('Location: conversation.php?id=' . $conversation_id . '&success=message_sent');
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Erreur lors de l\'envoi du message.';
            }
        }
    }
}
?>

<div class="container">
    <div class="contact-header">
        <div class="breadcrumb">
            <a href="browse.php">← Retour aux annonces</a>
        </div>
        
        <h1>💬 Contacter le Vendeur</h1>
    </div>

    <!-- Détails de l'annonce -->
    <div class="listing-summary">
        <div class="listing-card">
            <div class="listing-image">
                <img src="<?= sanitizeOutput($listing['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                     alt="<?= sanitizeOutput($listing['item_name']) ?>">
            </div>
            
            <div class="listing-info">
                <h2><?= sanitizeOutput($listing['item_name']) ?></h2>
                
                <?php if ($listing['variant_name']): ?>
                <p class="variant">
                    <?= sanitizeOutput($listing['variant_name']) ?>
                    <?php if ($listing['color_name']): ?>
                    - <?= sanitizeOutput($listing['color_name']) ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                
                <div class="price-info">
                    <?php if ($listing['sale_type'] === 'REAL_MONEY'): ?>
                    <span class="price"><?= formatPrice($listing['price_real'], $listing['currency']) ?></span>
                    <span class="sale-type real-money">💰 Argent réel</span>
                    <?php else: ?>
                    <span class="price"><?= formatPrice($listing['price_auec'], 'aUEC') ?></span>
                    <span class="sale-type in-game">🎮 In-Game</span>
                    <?php endif; ?>
                </div>
                
                <div class="seller-info">
                    <span class="seller-name">Vendeur: <?= sanitizeOutput($listing['seller_username']) ?></span>
                    <?php if ($listing['rating_avg'] > 0): ?>
                    <span class="rating">⭐ <?= number_format($listing['rating_avg'], 1) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de contact -->
    <div class="contact-form-section">
        <div class="form-container">
            <h2>📝 Envoyer un Message</h2>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?= sanitizeOutput($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="contact-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="form-group">
                    <label for="message" class="form-label">Votre message *</label>
                    <textarea id="message" 
                              name="message" 
                              class="form-textarea" 
                              rows="6" 
                              placeholder="Bonjour,&#10;&#10;Je suis intéressé(e) par votre <?= sanitizeOutput($listing['item_name']) ?>.&#10;&#10;Pouvez-vous me donner plus d'informations sur les modalités de <?= $listing['sale_type'] === 'REAL_MONEY' ? 'paiement et de livraison' : 'rendez-vous in-game' ?> ?&#10;&#10;Merci !"
                              maxlength="1000"
                              required><?= sanitizeOutput($_POST['message'] ?? '') ?></textarea>
                    <div class="char-counter">
                        <span id="char-count">0</span> / 1000 caractères
                    </div>
                </div>
                
                <!-- Suggestions de messages -->
                <div class="message-templates">
                    <h4>💡 Messages suggérés :</h4>
                    <div class="templates-list">
                        <?php if ($listing['sale_type'] === 'REAL_MONEY'): ?>
                        <button type="button" class="template-btn" onclick="useTemplate(this)">
                            Demander des détails sur le paiement et la livraison
                        </button>
                        <button type="button" class="template-btn" onclick="useTemplate(this)">
                            Proposer une négociation de prix
                        </button>
                        <button type="button" class="template-btn" onclick="useTemplate(this)">
                            Demander des garanties sur la transaction
                        </button>
                        <?php else: ?>
                        <button type="button" class="template-btn" onclick="useTemplate(this)">
                            Proposer un horaire de rendez-vous
                        </button>
                        <button type="button" class="template-btn" onclick="useTemplate(this)">
                            Demander des précisions sur le lieu de RDV
                        </button>
                        <button type="button" class="template-btn" onclick="useTemplate(this)">
                            Négocier le prix aUEC
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        📩 Envoyer le Message
                    </button>
                    <a href="item.php?id=<?= $listing['item_id'] ?>" class="btn btn-outline">
                        ❌ Annuler
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Conseils -->
        <div class="contact-tips">
            <div class="card">
                <div class="card-header">
                    <h3>💡 Conseils pour bien communiquer</h3>
                </div>
                <div class="card-body">
                    <div class="tips-list">
                        <div class="tip">
                            <span class="tip-icon">🤝</span>
                            <div class="tip-content">
                                <strong>Soyez courtois</strong>
                                <p>Un message poli augmente vos chances d'obtenir une réponse positive.</p>
                            </div>
                        </div>
                        
                        <div class="tip">
                            <span class="tip-icon">❓</span>
                            <div class="tip-content">
                                <strong>Posez des questions précises</strong>
                                <p>Demandez des détails sur les modalités, délais, conditions, etc.</p>
                            </div>
                        </div>
                        
                        <?php if ($listing['sale_type'] === 'REAL_MONEY'): ?>
                        <div class="tip">
                            <span class="tip-icon">🛡️</span>
                            <div class="tip-content">
                                <strong>Sécurité des paiements</strong>
                                <p>Utilisez des méthodes de paiement sécurisées et vérifiez l'identité du vendeur.</p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="tip">
                            <span class="tip-icon">🕐</span>
                            <div class="tip-content">
                                <strong>Planifiez le RDV</strong>
                                <p>Proposez des créneaux précis et confirmez avant de vous connecter.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="tip">
                            <span class="tip-icon">⭐</span>
                            <div class="tip-content">
                                <strong>Laissez un avis</strong>
                                <p>Après la transaction, pensez à évaluer le vendeur pour aider la communauté.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.contact-header {
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

.listing-summary {
    margin-bottom: 2rem;
}

.listing-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    gap: 1.5rem;
    align-items: center;
}

.listing-image img {
    width: 120px;
    height: 80px;
    object-fit: cover;
    border-radius: var(--radius);
}

.listing-info {
    flex: 1;
}

.listing-info h2 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.variant {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.price-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.price {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary);
}

.sale-type {
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius);
    font-size: 0.75rem;
    font-weight: 500;
}

.sale-type.real-money {
    background-color: rgba(14, 165, 233, 0.1);
    color: var(--primary);
}

.sale-type.in-game {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.seller-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.seller-name {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.rating {
    color: var(--accent);
    font-size: 0.875rem;
}

.contact-form-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.form-container {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
}

.form-container h2 {
    margin-bottom: 1.5rem;
    color: var(--text-primary);
}

.char-counter {
    text-align: right;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.message-templates {
    margin: 1.5rem 0;
    padding: 1rem;
    background-color: var(--bg-tertiary);
    border-radius: var(--radius);
}

.message-templates h4 {
    margin-bottom: 1rem;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.templates-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.template-btn {
    text-align: left;
    padding: 0.5rem;
    background: none;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text-secondary);
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.template-btn:hover {
    background-color: var(--bg-hover);
    color: var(--text-primary);
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.contact-tips {
    position: sticky;
    top: 2rem;
    height: fit-content;
}

.tips-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.tip {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.tip-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.tip-content strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.tip-content p {
    color: var(--text-secondary);
    font-size: 0.75rem;
    line-height: 1.4;
    margin: 0;
}

@media (max-width: 1024px) {
    .contact-form-section {
        grid-template-columns: 1fr;
    }
    
    .contact-tips {
        position: static;
    }
}

@media (max-width: 768px) {
    .listing-card {
        flex-direction: column;
        text-align: center;
    }
    
    .price-info {
        justify-content: center;
    }
    
    .seller-info {
        justify-content: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
// Compteur de caractères
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('message');
    const charCount = document.getElementById('char-count');
    
    function updateCharCount() {
        const count = textarea.value.length;
        charCount.textContent = count;
        
        if (count > 1000) {
            charCount.style.color = 'var(--error)';
        } else if (count > 800) {
            charCount.style.color = 'var(--warning)';
        } else {
            charCount.style.color = 'var(--text-muted)';
        }
    }
    
    textarea.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial count
});

// Utiliser un template de message
function useTemplate(button) {
    const textarea = document.getElementById('message');
    const templateText = button.textContent.trim();
    
    let message = '';
    
    if (templateText.includes('paiement et livraison')) {
        message = `Bonjour,

Je suis intéressé(e) par votre ${<?= json_encode($listing['item_name']) ?>}.

Pouvez-vous me donner plus d'informations sur :
- Les méthodes de paiement acceptées
- Les délais de livraison
- Les garanties offertes

Merci pour votre réponse !`;
    } else if (templateText.includes('négociation')) {
        message = `Bonjour,

Votre ${<?= json_encode($listing['item_name']) ?>} m'intéresse beaucoup.

Seriez-vous ouvert(e) à une négociation sur le prix ? Je peux proposer [VOTRE_OFFRE].

Merci de me faire savoir si cela vous convient.`;
    } else if (templateText.includes('garanties')) {
        message = `Bonjour,

Je souhaite acheter votre ${<?= json_encode($listing['item_name']) ?>}.

Pourriez-vous me donner des détails sur :
- Vos références de ventes précédentes
- Les garanties que vous proposez
- La procédure de transaction

Merci !`;
    } else if (templateText.includes('horaire')) {
        message = `Bonjour,

Je suis intéressé(e) par votre ${<?= json_encode($listing['item_name']) ?>} pour ${<?= json_encode(formatPrice($listing['price_auec'], 'aUEC')) ?>}.

Êtes-vous disponible pour un RDV :
- [Proposez vos créneaux]

Lieu : ${<?= json_encode($listing['meet_location']) ?>}

Merci !`;
    } else if (templateText.includes('lieu')) {
        message = `Bonjour,

Votre ${<?= json_encode($listing['item_name']) ?>} m'intéresse.

Concernant le lieu de RDV "${<?= json_encode($listing['meet_location']) ?>}", pourriez-vous préciser :
- L'endroit exact dans cette zone
- Des repères pour se retrouver facilement

Merci pour ces détails !`;
    } else if (templateText.includes('prix aUEC')) {
        message = `Bonjour,

Je suis intéressé(e) par votre ${<?= json_encode($listing['item_name']) ?>}.

Seriez-vous ouvert(e) à accepter [VOTRE_OFFRE] aUEC au lieu de ${<?= json_encode(formatPrice($listing['price_auec'], 'aUEC')) ?>} ?

Merci de me dire si c'est envisageable.`;
    }
    
    if (message) {
        textarea.value = message;
        textarea.dispatchEvent(new Event('input')); // Trigger char count update
        textarea.focus();
    }
}
</script>

<?php require_once 'footer.php'; ?>