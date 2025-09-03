<?php
$page_title = 'Profil utilisateur';
require_once 'header.php';

$username = $_GET['u'] ?? '';
if (empty($username)) {
    header('Location: index.php');
    exit;
}

// Récupérer l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_banned = FALSE");
$stmt->execute([$username]);
$profile_user = $stmt->fetch();

if (!$profile_user) {
    header('Location: index.php?error=user_not_found');
    exit;
}

$is_own_profile = $current_user && $current_user['id'] === $profile_user['id'];

// Récupérer les statistiques
$stats_sql = "
    SELECT 
        COUNT(CASE WHEN l.status = 'ACTIVE' THEN 1 END) as active_listings,
        COUNT(CASE WHEN l.status = 'SOLD' THEN 1 END) as sold_listings,
        COUNT(CASE WHEN l.sale_type = 'REAL_MONEY' THEN 1 END) as real_money_listings,
        COUNT(CASE WHEN l.sale_type = 'IN_GAME' THEN 1 END) as in_game_listings
    FROM listings l
    WHERE l.seller_id = ?
";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$profile_user['id']]);
$seller_stats = $stmt->fetch();

// Récupérer les avis reçus en tant que vendeur
$seller_reviews_sql = "
    SELECT r.*, u.username as rater_username, i.name as item_name
    FROM reviews r
    JOIN users u ON r.rater_id = u.id
    JOIN listings l ON r.listing_id = l.id
    JOIN items i ON l.item_id = i.id
    WHERE r.rated_id = ? AND r.role = 'SELLER'
    ORDER BY r.created_at DESC
    LIMIT 10
";
$stmt = $pdo->prepare($seller_reviews_sql);
$stmt->execute([$profile_user['id']]);
$seller_reviews = $stmt->fetchAll();

// Récupérer les avis reçus en tant qu'acheteur
$buyer_reviews_sql = "
    SELECT r.*, u.username as rater_username, i.name as item_name
    FROM reviews r
    JOIN users u ON r.rater_id = u.id
    JOIN listings l ON r.listing_id = l.id
    JOIN items i ON l.item_id = i.id
    WHERE r.rated_id = ? AND r.role = 'BUYER'
    ORDER BY r.created_at DESC
    LIMIT 10
";
$stmt = $pdo->prepare($buyer_reviews_sql);
$stmt->execute([$profile_user['id']]);
$buyer_reviews = $stmt->fetchAll();

// Récupérer les annonces actives du vendeur
$listings_sql = "
    SELECT l.*, i.name as item_name, i.image_url as item_image, i.category,
           iv.variant_name, iv.color_name
    FROM listings l
    JOIN items i ON l.item_id = i.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    WHERE l.seller_id = ? AND l.status = 'ACTIVE'
    ORDER BY l.created_at DESC
    LIMIT 12
";
$stmt = $pdo->prepare($listings_sql);
$stmt->execute([$profile_user['id']]);
$user_listings = $stmt->fetchAll();

$page_title = "Profil de " . $profile_user['username'];
?>

<div class="container">
    <div class="profile-header">
        <div class="profile-info">
            <div class="profile-avatar">
                <img src="<?= $profile_user['avatar_url'] ?: 'assets/img/default-avatar.png' ?>" 
                     alt="Avatar de <?= sanitizeOutput($profile_user['username']) ?>"
                     class="avatar-large">
                     
                <?php if ($profile_user['role'] !== 'USER'): ?>
                <div class="role-badge role-<?= strtolower($profile_user['role']) ?>">
                    <?= $profile_user['role'] ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-details">
                <h1 class="profile-username">
                    <?= sanitizeOutput($profile_user['username']) ?>
                    <?php if ($is_own_profile): ?>
                    <span class="own-profile">(Votre profil)</span>
                    <?php endif; ?>
                </h1>
                
                <div class="profile-rating">
                    <?php if ($profile_user['rating_count'] > 0): ?>
                    <div class="rating-display">
                        <span class="rating-stars">
                            <?php
                            $rating = $profile_user['rating_avg'];
                            for ($i = 1; $i <= 5; $i++) {
                                if ($rating >= $i) {
                                    echo '⭐';
                                } elseif ($rating >= $i - 0.5) {
                                    echo '🌟';
                                } else {
                                    echo '☆';
                                }
                            }
                            ?>
                        </span>
                        <span class="rating-text">
                            <?= number_format($profile_user['rating_avg'], 1) ?>/5 
                            (<?= $profile_user['rating_count'] ?> avis)
                        </span>
                    </div>
                    <?php else: ?>
                    <div class="no-rating">
                        <span class="rating-text">Aucun avis pour le moment</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-meta">
                    <span class="member-since">
                        📅 Membre depuis <?= date('F Y', strtotime($profile_user['created_at'])) ?>
                    </span>
                </div>
                
                <?php if ($profile_user['bio']): ?>
                <div class="profile-bio">
                    <p><?= nl2br(sanitizeOutput($profile_user['bio'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-actions">
            <?php if ($is_own_profile): ?>
            <a href="edit-profile.php" class="btn btn-primary">
                ✏️ Modifier mon profil
            </a>
            <a href="my-listings.php" class="btn btn-outline">
                📋 Mes annonces
            </a>
            <?php elseif ($current_user): ?>
            <a href="inbox.php" class="btn btn-primary">
                💬 Messages privés
            </a>
            <button class="btn btn-outline" onclick="reportUser(<?= $profile_user['id'] ?>)">
                ⚠️ Signaler
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques du vendeur -->
    <div class="profile-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $seller_stats['active_listings'] ?></div>
                    <div class="stat-label">Annonces actives</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $seller_stats['sold_listings'] ?></div>
                    <div class="stat-label">Ventes réalisées</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $seller_stats['real_money_listings'] ?></div>
                    <div class="stat-label">Argent réel</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎮</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $seller_stats['in_game_listings'] ?></div>
                    <div class="stat-label">In-Game</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglets du contenu -->
    <div class="profile-content">
        <div class="content-tabs">
            <button class="tab-button active" data-tab="listings">
                📦 Annonces (<?= count($user_listings) ?>)
            </button>
            <button class="tab-button" data-tab="seller-reviews">
                ⭐ Avis Vendeur (<?= count($seller_reviews) ?>)
            </button>
            <button class="tab-button" data-tab="buyer-reviews">
                🛒 Avis Acheteur (<?= count($buyer_reviews) ?>)
            </button>
        </div>

        <!-- Onglet Annonces -->
        <div id="listings-tab" class="tab-content active">
            <?php if (!empty($user_listings)): ?>
            <div class="listings-grid">
                <?php foreach ($user_listings as $listing): ?>
                <div class="listing-card">
                    <div class="listing-image">
                        <img src="<?= sanitizeOutput($listing['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                             alt="<?= sanitizeOutput($listing['item_name']) ?>">
                        <div class="listing-badges">
                            <span class="badge badge-<?= $listing['sale_type'] === 'REAL_MONEY' ? 'primary' : 'success' ?>">
                                <?= $listing['sale_type'] === 'REAL_MONEY' ? '💰' : '🎮' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="listing-content">
                        <h4 class="listing-title">
                            <a href="item.php?id=<?= $listing['item_id'] ?>">
                                <?= sanitizeOutput($listing['item_name']) ?>
                            </a>
                        </h4>
                        
                        <?php if ($listing['variant_name']): ?>
                        <p class="listing-variant">
                            <?= sanitizeOutput($listing['variant_name']) ?>
                            <?php if ($listing['color_name']): ?>
                            - <?= sanitizeOutput($listing['color_name']) ?>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="listing-price">
                            <?php if ($listing['sale_type'] === 'REAL_MONEY'): ?>
                            <span class="price"><?= formatPrice($listing['price_real'], $listing['currency']) ?></span>
                            <?php else: ?>
                            <span class="price"><?= formatPrice($listing['price_auec'], 'aUEC') ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="listing-date">
                            📅 <?= date('d/m/Y', strtotime($listing['created_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="listing-actions">
                        <?php if ($current_user && $current_user['id'] !== $profile_user['id']): ?>
                        <a href="contact-seller.php?listing_id=<?= $listing['id'] ?>" 
                           class="btn btn-primary btn-sm">
                            📩 Contacter
                        </a>
                        <?php endif; ?>
                        <a href="item.php?id=<?= $listing['item_id'] ?>" 
                           class="btn btn-outline btn-sm">
                            👁️ Voir
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($user_listings) >= 12): ?>
            <div class="see-more">
                <a href="browse.php?seller=<?= urlencode($profile_user['username']) ?>" 
                   class="btn btn-outline">
                    Voir toutes les annonces
                </a>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-content">
                <p>Aucune annonce active pour le moment.</p>
                <?php if ($is_own_profile): ?>
                <a href="sell.php" class="btn btn-primary">Créer une annonce</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Avis Vendeur -->
        <div id="seller-reviews-tab" class="tab-content">
            <?php if (!empty($seller_reviews)): ?>
            <div class="reviews-list">
                <?php foreach ($seller_reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $review['stars'] ? 'filled' : '' ?>">⭐</span>
                            <?php endfor; ?>
                        </div>
                        <div class="review-meta">
                            <span class="reviewer">
                                Par <a href="profile.php?u=<?= urlencode($review['rater_username']) ?>">
                                    <?= sanitizeOutput($review['rater_username']) ?>
                                </a>
                            </span>
                            <span class="review-date">
                                <?= date('d/m/Y', strtotime($review['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="review-item">
                        <strong>Item:</strong> <?= sanitizeOutput($review['item_name']) ?>
                    </div>
                    
                    <?php if ($review['comment']): ?>
                    <div class="review-comment">
                        <p><?= nl2br(sanitizeOutput($review['comment'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-content">
                <p>Aucun avis en tant que vendeur pour le moment.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Avis Acheteur -->
        <div id="buyer-reviews-tab" class="tab-content">
            <?php if (!empty($buyer_reviews)): ?>
            <div class="reviews-list">
                <?php foreach ($buyer_reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $review['stars'] ? 'filled' : '' ?>">⭐</span>
                            <?php endfor; ?>
                        </div>
                        <div class="review-meta">
                            <span class="reviewer">
                                Par <a href="profile.php?u=<?= urlencode($review['rater_username']) ?>">
                                    <?= sanitizeOutput($review['rater_username']) ?>
                                </a>
                            </span>
                            <span class="review-date">
                                <?= date('d/m/Y', strtotime($review['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="review-item">
                        <strong>Item:</strong> <?= sanitizeOutput($review['item_name']) ?>
                    </div>
                    
                    <?php if ($review['comment']): ?>
                    <div class="review-comment">
                        <p><?= nl2br(sanitizeOutput($review['comment'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-content">
                <p>Aucun avis en tant qu'acheteur pour le moment.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.profile-header {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.profile-info {
    display: flex;
    gap: 2rem;
    flex: 1;
}

.profile-avatar {
    position: relative;
    flex-shrink: 0;
}

.avatar-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--border);
}

.profile-details {
    flex: 1;
}

.profile-username {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.own-profile {
    font-size: 1rem;
    color: var(--text-muted);
    font-weight: normal;
}

.profile-rating {
    margin-bottom: 1rem;
}

.rating-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.rating-stars {
    font-size: 1.25rem;
}

.rating-text {
    color: var(--text-secondary);
    font-weight: 500;
}

.no-rating .rating-text {
    color: var(--text-muted);
}

.profile-meta {
    margin-bottom: 1rem;
    color: var(--text-muted);
    font-size: 0.875rem;
}

.profile-bio {
    background-color: var(--bg-tertiary);
    padding: 1rem;
    border-radius: var(--radius);
    border-left: 3px solid var(--primary);
}

.profile-bio p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.5;
}

.profile-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-shrink: 0;
}

.profile-stats {
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.stat-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary);
    display: block;
}

.stat-label {
    color: var(--text-muted);
    font-size: 0.875rem;
}

.profile-content {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.content-tabs {
    display: flex;
    border-bottom: 1px solid var(--border);
}

.tab-button {
    flex: 1;
    padding: 1rem 1.5rem;
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 2px solid transparent;
}

.tab-button:hover {
    background-color: var(--bg-hover);
    color: var(--text-primary);
}

.tab-button.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background-color: var(--bg-tertiary);
}

.tab-content {
    padding: 2rem;
    display: none;
}

.tab-content.active {
    display: block;
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.listing-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.2s;
}

.listing-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.listing-image {
    position: relative;
    height: 160px;
    overflow: hidden;
}

.listing-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.listing-badges {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}

.listing-content {
    padding: 1rem;
}

.listing-title {
    margin-bottom: 0.5rem;
}

.listing-title a {
    color: var(--text-primary);
    text-decoration: none;
    font-size: 1rem;
    font-weight: 500;
}

.listing-title a:hover {
    color: var(--primary);
}

.listing-variant {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.listing-price {
    margin-bottom: 0.5rem;
}

.price {
    font-size: 1.125rem;
    font-weight: bold;
    color: var(--primary);
}

.listing-date {
    color: var(--text-muted);
    font-size: 0.75rem;
}

.listing-actions {
    padding: 1rem;
    background-color: var(--bg-tertiary);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 0.5rem;
}

.see-more {
    text-align: center;
    margin-top: 2rem;
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.review-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.review-rating {
    display: flex;
    gap: 0.125rem;
}

.star {
    opacity: 0.3;
}

.star.filled {
    opacity: 1;
}

.review-meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-align: right;
    font-size: 0.875rem;
}

.reviewer a {
    color: var(--primary);
    text-decoration: none;
}

.reviewer a:hover {
    text-decoration: underline;
}

.review-date {
    color: var(--text-muted);
}

.review-item {
    margin-bottom: 1rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.review-comment {
    background-color: var(--bg-tertiary);
    padding: 1rem;
    border-radius: var(--radius);
    border-left: 3px solid var(--primary);
}

.review-comment p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.5;
}

.empty-content {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}

.empty-content p {
    margin-bottom: 1rem;
}

@media (max-width: 1024px) {
    .profile-header {
        flex-direction: column;
        gap: 2rem;
    }
    
    .profile-actions {
        flex-direction: row;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .profile-info {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .content-tabs {
        flex-direction: column;
    }
    
    .listings-grid {
        grid-template-columns: 1fr;
    }
    
    .review-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .review-meta {
        text-align: center;
    }
}
</style>

<script>
// Gestion des onglets
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Retirer active de tous les boutons et contenus
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Ajouter active au bouton cliqué et son contenu
            this.classList.add('active');
            const targetContent = document.getElementById(targetTab + '-tab');
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
});

function reportUser(userId) {
    if (confirm('Voulez-vous signaler cet utilisateur aux modérateurs ?')) {
        fetch('api/report-user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                reason: 'Signalement depuis le profil'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Utilisateur signalé aux modérateurs', 'success');
            } else {
                showNotification(data.message || 'Erreur lors du signalement', 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion', 'error');
        });
    }
}
</script>

<?php require_once 'footer.php'; ?>