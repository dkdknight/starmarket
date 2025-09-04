<?php
$page_title = 'Laisser un avis';
require_once 'header.php';

// Vérifier l'authentification
requireLogin();

$pending_review_id = (int)($_GET['id'] ?? 0);
$errors = [];

if (!$pending_review_id) {
    header('Location: inbox.php');
    exit;
}

// Récupérer l'avis en attente
$stmt = $pdo->prepare("
    SELECT pr.*, c.status, l.id as listing_id,
           i.name as item_name, i.image_url as item_image,
           iv.variant_name, iv.color_name,
           rated_user.username as rated_username,
           rated_user.rating_avg as rated_avg,
           rated_user.rating_count as rated_count
    FROM pending_reviews pr
    JOIN conversations c ON pr.conversation_id = c.id
    JOIN listings l ON c.listing_id = l.id
    JOIN items i ON l.item_id = i.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    JOIN users rated_user ON pr.rated_id = rated_user.id
    WHERE pr.id = ? AND pr.rater_id = ? AND pr.is_completed = FALSE
");
$stmt->execute([$pending_review_id, $current_user['id']]);
$pending_review = $stmt->fetch();

if (!$pending_review) {
    header('Location: inbox.php?error=review_not_found');
    exit;
}

// Vérifier que la transaction est terminée
if ($pending_review['status'] !== 'DONE') {
    header('Location: conversation.php?id=' . $pending_review['conversation_id']);
    exit;
}

// Traitement du formulaire d'avis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stars = (int)($_POST['stars'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validation CSRF
    if (!validateCSRFToken($csrf_token)) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        // Validation des données
        if ($stars < 1 || $stars > 5) {
            $errors[] = 'Veuillez choisir une note entre 1 et 5 étoiles.';
        }
        
        if (strlen($comment) > 1000) {
            $errors[] = 'Le commentaire ne peut pas dépasser 1000 caractères.';
        }
        
        // Créer l'avis
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Insérer l'avis
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (rater_id, rated_id, listing_id, role, stars, comment, pending_review_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $current_user['id'],
                    $pending_review['rated_id'],
                    $pending_review['listing_id'],
                    $pending_review['role'],
                    $stars,
                    $comment,
                    $pending_review_id
                ]);
                
                // Marquer l'avis en attente comme terminé
                $stmt = $pdo->prepare("UPDATE pending_reviews SET is_completed = TRUE WHERE id = ?");
                $stmt->execute([$pending_review_id]);
                
                // Recalculer la moyenne des avis pour l'utilisateur noté
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET rating_avg = (
                        SELECT AVG(stars) FROM reviews WHERE rated_id = ?
                    ),
                    rating_count = (
                        SELECT COUNT(*) FROM reviews WHERE rated_id = ?
                    )
                    WHERE id = ?
                ");
                $stmt->execute([
                    $pending_review['rated_id'],
                    $pending_review['rated_id'],
                    $pending_review['rated_id']
                ]);
                
                $pdo->commit();
                
                // Notification Discord
                $stmt = $pdo->prepare("SELECT discord_user_id FROM users WHERE id = ?");
                $stmt->execute([$pending_review['rated_id']]);
                $rated_user_discord = $stmt->fetchColumn();
                
                if ($rated_user_discord) {
                    require_once 'includes/discord-notification.php';
                    sendDiscordMessage($rated_user_discord, [
                        'type' => 'new_review',
                        'reviewer' => $current_user['username'],
                        'stars' => $stars,
                        'item_name' => $pending_review['item_name'],
                        'role' => $pending_review['role'],
                        'profile_url' => SITE_URL . "/profile.php?u=" . urlencode($pending_review['rated_username'])
                    ]);
                }
                
                header('Location: profile.php?u=' . urlencode($pending_review['rated_username']) . '&success=review_added');
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Erreur lors de l\'enregistrement de l\'avis.';
                error_log("Erreur création avis: " . $e->getMessage());
            }
        }
    }
}
?>

<div class="container">
    <div class="review-header">
        <div class="breadcrumb">
            <a href="inbox.php">← Retour à la messagerie</a>
        </div>
        
        <h1>⭐ Laisser un avis</h1>
        <p>Partagez votre expérience pour aider la communauté</p>
    </div>

    <!-- Détails de la transaction -->
    <div class="transaction-summary">
        <div class="summary-card">
            <div class="transaction-item">
                <img src="<?= sanitizeOutput($pending_review['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                     alt="<?= sanitizeOutput($pending_review['item_name']) ?>"
                     class="item-image">
                
                <div class="item-details">
                    <h3><?= sanitizeOutput($pending_review['item_name']) ?></h3>
                    
                    <?php if ($pending_review['variant_name']): ?>
                    <p class="variant-info">
                        <?= sanitizeOutput($pending_review['variant_name']) ?>
                        <?php if ($pending_review['color_name']): ?>
                        - <?= sanitizeOutput($pending_review['color_name']) ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="transaction-role">
                        <?php if ($pending_review['role'] === 'SELLER'): ?>
                        <span class="role-badge seller">Vous évaluez le vendeur</span>
                        <?php else: ?>
                        <span class="role-badge buyer">Vous évaluez l'acheteur</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="rated-user-info">
                <h4><?= sanitizeOutput($pending_review['rated_username']) ?></h4>
                <?php if ($pending_review['rated_count'] > 0): ?>
                <div class="current-rating">
                    <span class="rating-stars">
                        <?php
                        $rating = $pending_review['rated_avg'];
                        for ($i = 1; $i <= 5; $i++) {
                            echo $rating >= $i ? '⭐' : '☆';
                        }
                        ?>
                    </span>
                    <span class="rating-text">
                        <?= number_format($pending_review['rated_avg'], 1) ?>/5 
                        (<?= $pending_review['rated_count'] ?> avis)
                    </span>
                </div>
                <?php else: ?>
                <p class="no-rating">Premier avis pour cet utilisateur</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulaire d'avis -->
    <div class="review-form-section">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?= sanitizeOutput($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="review-form">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div class="form-section">
                <h2>⭐ Votre évaluation</h2>
                
                <div class="form-group">
                    <label class="form-label">Note *</label>
                    <div class="star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <input type="radio" 
                               id="star<?= $i ?>" 
                               name="stars" 
                               value="<?= $i ?>" 
                               <?= (($_POST['stars'] ?? 0) == $i) ? 'checked' : '' ?>
                               required>
                        <label for="star<?= $i ?>" class="star-label">⭐</label>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-help">
                        <span class="rating-description" id="rating-desc">Cliquez sur les étoiles pour noter</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="comment" class="form-label">Commentaire (optionnel)</label>
                    <textarea id="comment" 
                              name="comment" 
                              class="form-textarea" 
                              rows="5" 
                              placeholder="Décrivez votre expérience avec ce <?= $pending_review['role'] === 'SELLER' ? 'vendeur' : 'client' ?>..."
                              maxlength="1000"><?= sanitizeOutput($_POST['comment'] ?? '') ?></textarea>
                    <div class="char-counter">
                        <span id="char-count">0</span> / 1000 caractères
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>💡 Conseils pour un bon avis</h3>
                <div class="tips-grid">
                    <div class="tip-item">
                        <span class="tip-icon">🤝</span>
                        <div class="tip-content">
                            <strong>Communication</strong>
                            <p>Comment s'est passée la communication ?</p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <span class="tip-icon">⏰</span>
                        <div class="tip-content">
                            <strong>Ponctualité</strong>
                            <p>A-t-il respecté les délais convenus ?</p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <span class="tip-icon">✅</span>
                        <div class="tip-content">
                            <strong>Transaction</strong>
                            <p>La transaction s'est-elle bien passée ?</p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <span class="tip-icon">🌟</span>
                        <div class="tip-content">
                            <strong>Recommandation</strong>
                            <p>Recommanderiez-vous cet utilisateur ?</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    ⭐ Publier l'avis
                </button>
                <a href="conversation.php?id=<?= $pending_review['conversation_id'] ?>" class="btn btn-outline">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.review-header {
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

.transaction-summary {
    margin-bottom: 2rem;
}

.summary-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2rem;
    align-items: center;
}

.transaction-item {
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

.item-details h3 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.variant-info {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.transaction-role {
    margin-top: 1rem;
}

.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius);
    font-size: 0.875rem;
    font-weight: 500;
}

.role-badge.seller {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.role-badge.buyer {
    background-color: rgba(14, 165, 233, 0.1);
    color: var(--primary);
}

.rated-user-info {
    text-align: center;
}

.rated-user-info h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-size: 1.25rem;
}

.current-rating {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: center;
}

.rating-stars {
    font-size: 1.25rem;
}

.rating-text {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.no-rating {
    color: var(--text-muted);
    font-size: 0.875rem;
}

.review-form-section {
    max-width: 800px;
    margin: 0 auto;
}

.review-form {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
}

.form-section h2 {
    margin-bottom: 1.5rem;
    color: var(--text-primary);
}

.star-rating {
    display: flex;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.star-rating input[type="radio"] {
    display: none;
}

.star-label {
    font-size: 2rem;
    cursor: pointer;
    color: #ddd;
    transition: all 0.2s;
}

.star-label:hover,
.star-rating input[type="radio"]:checked ~ .star-label,
.star-rating input[type="radio"]:checked + .star-label {
    color: var(--accent);
    transform: scale(1.1);
}

.rating-help {
    margin-top: 0.5rem;
}

.rating-description {
    color: var(--text-muted);
    font-size: 0.875rem;
    font-style: italic;
}

.char-counter {
    text-align: right;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.tip-item {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 1rem;
    background-color: var(--bg-tertiary);
    border-radius: var(--radius);
}

.tip-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
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
    margin: 0;
}

.form-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

@media (max-width: 768px) {
    .summary-card {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 1rem;
    }
    
    .transaction-item {
        flex-direction: column;
        text-align: center;
    }
    
    .tips-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des étoiles interactives
    const starInputs = document.querySelectorAll('input[name="stars"]');
    const ratingDesc = document.getElementById('rating-desc');
    
    const descriptions = {
        1: '⭐ Très mauvaise expérience',
        2: '⭐⭐ Expérience décevante',
        3: '⭐⭐⭐ Expérience correcte',
        4: '⭐⭐⭐⭐ Bonne expérience',
        5: '⭐⭐⭐⭐⭐ Excellente expérience'
    };
    
    starInputs.forEach(input => {
        input.addEventListener('change', function() {
            const rating = this.value;
            ratingDesc.textContent = descriptions[rating] || 'Cliquez sur les étoiles pour noter';
        });
    });
    
    // Effet visuel sur survol des étoiles
    const starLabels = document.querySelectorAll('.star-label');
    
    starLabels.forEach((label, index) => {
        label.addEventListener('mouseenter', function() {
            // Colorer toutes les étoiles jusqu'à celle survolée
            for (let i = 0; i <= index; i++) {
                starLabels[i].style.color = 'var(--accent)';
            }
            // Décolorer les suivantes
            for (let i = index + 1; i < starLabels.length; i++) {
                starLabels[i].style.color = '#ddd';
            }
        });
        
        label.addEventListener('mouseleave', function() {
            // Restaurer l'état basé sur la sélection
            const checkedInput = document.querySelector('input[name="stars"]:checked');
            const checkedValue = checkedInput ? parseInt(checkedInput.value) : 0;
            
            starLabels.forEach((lbl, i) => {
                lbl.style.color = i < checkedValue ? 'var(--accent)' : '#ddd';
            });
        });
    });
    
    // Compteur de caractères
    const textarea = document.getElementById('comment');
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
</script>

<?php require_once 'footer.php'; ?>