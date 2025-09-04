<?php
$page_title = 'Dernières Annonces';
require_once 'header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 24;

// Calculer la pagination
$count_sql = "
    SELECT COUNT(*)
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    WHERE l.status = 'ACTIVE' AND u.is_banned = FALSE
";
$stmt = $pdo->query($count_sql);
$total_listings = $stmt->fetchColumn();

$pagination = getPaginationData($page, $total_listings, $per_page);

// Récupérer les dernières annonces
$sql = "
    SELECT l.*, i.name as item_name, i.image_url as item_image, i.category,
           u.username, u.rating_avg, u.rating_count,
           iv.variant_name, iv.color_name
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    WHERE l.status = 'ACTIVE' AND u.is_banned = FALSE
    ORDER BY l.created_at DESC
    LIMIT {$per_page} OFFSET {$pagination['offset']}
";

$stmt = $pdo->query($sql);
$listings = $stmt->fetchAll();

// Statistiques rapides
$stats_sql = "
    SELECT 
        COUNT(CASE WHEN l.sale_type = 'REAL_MONEY' THEN 1 END) as real_money_count,
        COUNT(CASE WHEN l.sale_type = 'IN_GAME' THEN 1 END) as in_game_count,
        COUNT(CASE WHEN DATE(l.created_at) = CURDATE() THEN 1 END) as today_count,
        COUNT(CASE WHEN DATE(l.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_count
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    WHERE l.status = 'ACTIVE' AND u.is_banned = FALSE
";
$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();
?>

<div class="container">
    <div class="page-header">
        <h1>📅 Dernières Annonces</h1>
        <p>Découvrez les annonces les plus récentes publiées par la communauté</p>
    </div>

    <!-- Statistiques rapides -->
    <div class="latest-stats">
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-icon">📈</span>
                <div class="stat-content">
                    <span class="stat-number"><?= number_format($total_listings) ?></span>
                    <span class="stat-label">Annonces actives</span>
                </div>
            </div>
            <div class="stat-item">
                <span class="stat-icon">🔥</span>
                <div class="stat-content">
                    <span class="stat-number"><?= number_format($stats['today_count']) ?></span>
                    <span class="stat-label">Aujourd'hui</span>
                </div>
            </div>
            <div class="stat-item">
                <span class="stat-icon">⏰</span>
                <div class="stat-content">
                    <span class="stat-number"><?= number_format($stats['week_count']) ?></span>
                    <span class="stat-label">Cette semaine</span>
                </div>
            </div>
            <div class="stat-item">
                <span class="stat-icon">💰</span>
                <div class="stat-content">
                    <span class="stat-number"><?= number_format($stats['real_money_count']) ?></span>
                    <span class="stat-label">Argent réel</span>
                </div>
            </div>
            <div class="stat-item">
                <span class="stat-icon">🎮</span>
                <div class="stat-content">
                    <span class="stat-number"><?= number_format($stats['in_game_count']) ?></span>
                    <span class="stat-label">In-Game</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="quick-actions">
        <div class="actions-row">
            <a href="browse.php?sale_type=REAL_MONEY" class="action-card">
                <span class="action-icon">💰</span>
                <div class="action-content">
                    <h3>Argent Réel</h3>
                    <p>Voir les annonces payantes</p>
                </div>
            </a>
            <a href="browse.php?sale_type=IN_GAME" class="action-card">
                <span class="action-icon">🎮</span>
                <div class="action-content">
                    <h3>In-Game aUEC</h3>
                    <p>Voir les échanges in-game</p>
                </div>
            </a>
            <a href="deals.php" class="action-card">
                <span class="action-icon">🔥</span>
                <div class="action-content">
                    <h3>Bonnes Affaires</h3>
                    <p>Les meilleures réductions</p>
                </div>
            </a>
            <?php if ($current_user): ?>
            <a href="sell.php" class="action-card create-listing">
                <span class="action-icon">➕</span>
                <div class="action-content">
                    <h3>Créer une Annonce</h3>
                    <p>Vendez vos items</p>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Liste des annonces -->
    <?php if (!empty($listings)): ?>
    <div class="listings-grid">
        <?php foreach ($listings as $listing): ?>
        <div class="listing-card">
            <div class="listing-image">
                <img src="<?= sanitizeOutput($listing['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                     alt="<?= sanitizeOutput($listing['item_name']) ?>"
                     loading="lazy">
                <div class="listing-badges">
                    <span class="badge badge-category">
                        <?php
                        switch ($listing['category']) {
                            case 'SHIP': echo '🚀'; break;
                            case 'ARMOR': echo '🛡️'; break;
                            case 'WEAPON': echo '⚔️'; break;
                            case 'COMPONENT': echo '🔧'; break;
                            case 'PAINT': echo '🎨'; break;
                            case 'OTHER': echo '📦'; break;
                        }
                        ?>
                    </span>
                    <span class="badge badge-<?= $listing['sale_type'] === 'REAL_MONEY' ? 'primary' : 'success' ?>">
                        <?= $listing['sale_type'] === 'REAL_MONEY' ? '💰' : '🎮' ?>
                    </span>
                </div>
                
                <!-- Indicateur de fraicheur -->
                <?php 
                $listing_age = time() - strtotime($listing['created_at']);
                if ($listing_age < 3600): // Moins d'1 heure
                ?>
                <div class="fresh-badge">
                    NOUVEAU
                </div>
                <?php elseif ($listing_age < 86400): // Moins de 24h ?>
                <div class="recent-badge">
                    RÉCENT
                </div>
                <?php endif; ?>
            </div>

            <div class="listing-content">
                <h3 class="listing-title">
                    <a href="item.php?id=<?= $listing['item_id'] ?>"><?= sanitizeOutput($listing['item_name']) ?></a>
                </h3>
                
                <?php if ($listing['variant_name']): ?>
                <p class="listing-variant">
                    <?= sanitizeOutput($listing['variant_name']) ?>
                    <?php if ($listing['color_name']): ?>
                    <span class="color-info">(<?= sanitizeOutput($listing['color_name']) ?>)</span>
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

                <div class="listing-seller">
                    <a href="profile.php?u=<?= urlencode($listing['username']) ?>" class="seller-link">
                        👤 <?= sanitizeOutput($listing['username']) ?>
                    </a>
                    <?php if ($listing['rating_avg'] > 0): ?>
                    <span class="rating">⭐ <?= number_format($listing['rating_avg'], 1) ?></span>
                    <?php endif; ?>
                </div>

                <div class="listing-meta">
                    <span class="listing-time" title="<?= date('d/m/Y H:i', strtotime($listing['created_at'])) ?>">
                        <?php
                        $time_diff = time() - strtotime($listing['created_at']);
                        if ($time_diff < 60) {
                            echo 'Il y a quelques secondes';
                        } elseif ($time_diff < 3600) {
                            echo 'Il y a ' . floor($time_diff / 60) . ' min';
                        } elseif ($time_diff < 86400) {
                            echo 'Il y a ' . floor($time_diff / 3600) . 'h';
                        } elseif ($time_diff < 604800) {
                            echo 'Il y a ' . floor($time_diff / 86400) . 'j';
                        } else {
                            echo date('d/m/Y', strtotime($listing['created_at']));
                        }
                        ?>
                    </span>
                    
                    <?php if ($listing['sale_type'] === 'REAL_MONEY'): ?>
                    <span class="listing-location">📍 <?= sanitizeOutput($listing['region']) ?></span>
                    <?php else: ?>
                    <span class="listing-location">🎮 <?= sanitizeOutput($listing['meet_location']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="listing-actions">
                <?php if ($current_user && $current_user['id'] !== $listing['seller_id']): ?>
                <a href="contact-seller.php?listing_id=<?= $listing['id'] ?>" 
                   class="btn btn-primary btn-sm">
                    📩 Contacter
                </a>
                <a href="item.php?id=<?= $listing['item_id'] ?>" 
                   class="btn btn-outline btn-sm">
                    👁️ Voir
                </a>
                <?php elseif (!$current_user): ?>
                <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                   class="btn btn-primary btn-sm">
                    Se connecter
                </a>
                <?php else: ?>
                <span class="own-listing">Votre annonce</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination">
        <?php if ($pagination['has_prev']): ?>
        <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="btn btn-outline">← Précédent</a>
        <?php endif; ?>
        
        <div class="pagination-info">
            Page <?= $pagination['current_page'] ?> sur <?= $pagination['total_pages'] ?>
            (<?= number_format($total_listings) ?> annonce<?= $total_listings > 1 ? 's' : '' ?>)
        </div>
        
        <?php if ($pagination['has_next']): ?>
        <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="btn btn-outline">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="no-listings">
        <div class="no-listings-content">
            <h3>😔 Aucune annonce disponible</h3>
            <p>Il n'y a pas d'annonces actives en ce moment. Soyez le premier à publier !</p>
            <?php if ($current_user): ?>
            <a href="sell.php" class="btn btn-primary">Créer la première annonce</a>
            <?php else: ?>
            <a href="register.php" class="btn btn-primary">S'inscrire pour vendre</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.latest-stats {
    margin: 2rem 0;
}

.stats-row {
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 1rem;
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 120px;
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

.quick-actions {
    margin: 2rem 0;
}

.actions-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.action-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s;
}

.action-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.action-card.create-listing {
    border-color: var(--success);
}

.action-card.create-listing:hover {
    border-color: var(--success);
    background-color: rgba(16, 185, 129, 0.05);
}

.action-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.action-content h3 {
    margin-bottom: 0.25rem;
    font-size: 1rem;
    color: var(--text-primary);
}

.action-content p {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin: 0;
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.listing-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
}

.listing-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.listing-image {
    position: relative;
    height: 180px;
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
    display: flex;
    gap: 0.25rem;
}

.fresh-badge {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    background-color: var(--error);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.625rem;
    font-weight: bold;
    animation: pulse 2s infinite;
}

.recent-badge {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    background-color: var(--warning);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.625rem;
    font-weight: bold;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.listing-content {
    flex: 1;
    padding: 1.5rem;
}

.listing-title {
    margin-bottom: 0.5rem;
}

.listing-title a {
    color: var(--text-primary);
    text-decoration: none;
    font-size: 1.125rem;
    font-weight: 500;
}

.listing-title a:hover {
    color: var(--primary);
}

.listing-variant {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.color-info {
    color: var(--text-muted);
}

.listing-price {
    margin-bottom: 1rem;
}

.price {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--primary);
}

.listing-seller {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.seller-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.875rem;
}

.seller-link:hover {
    text-decoration: underline;
}

.rating {
    color: var(--accent);
    font-size: 0.75rem;
}

.listing-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.listing-actions {
    padding: 1rem 1.5rem;
    background-color: var(--bg-tertiary);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 0.5rem;
}

.own-listing {
    color: var(--text-muted);
    font-style: italic;
    font-size: 0.875rem;
}

.no-listings {
    text-align: center;
    padding: 4rem 2rem;
}

.no-listings-content h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.no-listings-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .stats-row {
        flex-direction: column;
        text-align: center;
    }
    
    .stat-item {
        justify-content: center;
        min-width: auto;
    }
    
    .actions-row {
        grid-template-columns: 1fr;
    }
    
    .listings-grid {
        grid-template-columns: 1fr;
    }
    
    .listing-meta {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .listing-actions {
        flex-direction: column;
    }
}
</style>

<?php require_once 'footer.php'; ?>