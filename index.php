<?php
$page_title = 'Accueil';
require_once 'header.php';

// Récupérer les dernières annonces
$stmt = $pdo->prepare("
    SELECT l.*, i.name as item_name, i.image_url as item_image, 
           u.username as seller_name, u.rating_avg,
           iv.variant_name, iv.color_name
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    WHERE l.status = 'ACTIVE' AND u.is_banned = FALSE
    ORDER BY l.created_at DESC
    LIMIT 6
");
$stmt->execute();
$latest_listings = $stmt->fetchAll();

// Récupérer les bonnes affaires
$stmt = $pdo->prepare("
    SELECT l.*, i.name as item_name, i.image_url as item_image,
           u.username as seller_name, u.rating_avg,
           iv.variant_name, iv.color_name,
           pr.ref_price_real, pr.ref_price_auec,
           CASE 
               WHEN l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 
               THEN (pr.ref_price_real - l.price_real) / pr.ref_price_real
               WHEN l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 
               THEN (pr.ref_price_auec - l.price_auec) / pr.ref_price_auec
               ELSE 0
           END as deal_score
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    LEFT JOIN price_reference pr ON i.id = pr.item_id
    WHERE l.status = 'ACTIVE' AND u.is_banned = FALSE
    HAVING deal_score > 0.1
    ORDER BY deal_score DESC
    LIMIT 4
");
$stmt->execute();
$deals = $stmt->fetchAll();

// Statistiques du site
$stmt = $pdo->query("SELECT COUNT(*) as total FROM listings WHERE status = 'ACTIVE'");
$total_listings = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_banned = FALSE");
$total_users = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM items WHERE is_active = TRUE");
$total_items = $stmt->fetch()['total'];
?>

<div class="container">
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title">StarMarket</h1>
            <p class="hero-subtitle">
                Le marketplace communautaire pour Star Citizen<br>
                Achetez et vendez vos items, vaisseaux et équipements
            </p>
            <div class="hero-stats">
                <div class="stat">
                    <span class="stat-number"><?= number_format($total_listings) ?></span>
                    <span class="stat-label">Annonces actives</span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?= number_format($total_users) ?></span>
                    <span class="stat-label">Membres</span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?= number_format($total_items) ?></span>
                    <span class="stat-label">Items référencés</span>
                </div>
            </div>
            <div class="hero-actions">
                <a href="items.php" class="btn btn-primary">Parcourir le Catalogue</a>
                <a href="browse.php" class="btn btn-outline">Voir les Annonces</a>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="quick-actions">
        <div class="grid grid-2">
            <div class="card">
                <div class="card-body">
                    <h3>💰 Argent Réel</h3>
                    <p>Échangez vos items contre de l'argent réel via le système de "gifting" de Star Citizen.</p>
                    <a href="browse.php?sale_type=REAL_MONEY" class="btn btn-primary btn-sm">Voir les offres</a>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3>🎮 In-Game (aUEC)</h3>
                    <p>Achetez et vendez avec la monnaie du jeu. Organisez vos rendez-vous directement.</p>
                    <a href="browse.php?sale_type=IN_GAME" class="btn btn-primary btn-sm">Voir les offres</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Bonnes Affaires -->
    <?php if (!empty($deals)): ?>
    <section class="deals-section">
        <div class="section-header">
            <h2>🔥 Bonnes Affaires</h2>
            <a href="deals.php" class="btn btn-outline btn-sm">Voir toutes</a>
        </div>
        <div class="grid grid-4">
            <?php foreach ($deals as $deal): ?>
            <div class="card listing-card">
                <div class="listing-image">
                    <img src="<?= sanitizeOutput($deal['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                         alt="<?= sanitizeOutput($deal['item_name']) ?>">
                    <div class="deal-badge">
                        -<?= round($deal['deal_score'] * 100) ?>%
                    </div>
                </div>
                <div class="card-body">
                    <h4><?= sanitizeOutput($deal['item_name']) ?></h4>
                    <?php if ($deal['variant_name']): ?>
                    <p class="variant-info">
                        <?= sanitizeOutput($deal['variant_name']) ?>
                        <?php if ($deal['color_name']): ?>
                        <span class="color-info">(<?= sanitizeOutput($deal['color_name']) ?>)</span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    <div class="price-info">
                        <?php if ($deal['sale_type'] === 'REAL_MONEY'): ?>
                        <span class="price"><?= formatPrice($deal['price_real'], $deal['currency']) ?></span>
                        <?php else: ?>
                        <span class="price"><?= formatPrice($deal['price_auec'], 'aUEC') ?></span>
                        <?php endif; ?>
                        <span class="badge badge-<?= $deal['sale_type'] === 'REAL_MONEY' ? 'primary' : 'success' ?>">
                            <?= $deal['sale_type'] === 'REAL_MONEY' ? 'Argent réel' : 'In-Game' ?>
                        </span>
                    </div>
                    <div class="seller-info">
                        <span>Vendeur: <?= sanitizeOutput($deal['seller_name']) ?></span>
                        <?php if ($deal['rating_avg'] > 0): ?>
                        <span class="rating">⭐ <?= number_format($deal['rating_avg'], 1) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="item.php?id=<?= $deal['item_id'] ?>" class="btn btn-primary btn-sm">Voir l'item</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Dernières Annonces -->
    <?php if (!empty($latest_listings)): ?>
    <section class="latest-section">
        <div class="section-header">
            <h2>📅 Dernières Annonces</h2>
            <a href="latest.php" class="btn btn-outline btn-sm">Voir toutes</a>
        </div>
        <div class="grid grid-3">
            <?php foreach ($latest_listings as $listing): ?>
            <div class="card listing-card">
                <div class="listing-image">
                    <img src="<?= sanitizeOutput($listing['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                         alt="<?= sanitizeOutput($listing['item_name']) ?>">
                </div>
                <div class="card-body">
                    <h4><?= sanitizeOutput($listing['item_name']) ?></h4>
                    <?php if ($listing['variant_name']): ?>
                    <p class="variant-info">
                        <?= sanitizeOutput($listing['variant_name']) ?>
                        <?php if ($listing['color_name']): ?>
                        <span class="color-info">(<?= sanitizeOutput($listing['color_name']) ?>)</span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    <div class="price-info">
                        <?php if ($listing['sale_type'] === 'REAL_MONEY'): ?>
                        <span class="price"><?= formatPrice($listing['price_real'], $listing['currency']) ?></span>
                        <?php else: ?>
                        <span class="price"><?= formatPrice($listing['price_auec'], 'aUEC') ?></span>
                        <?php endif; ?>
                        <span class="badge badge-<?= $listing['sale_type'] === 'REAL_MONEY' ? 'primary' : 'success' ?>">
                            <?= $listing['sale_type'] === 'REAL_MONEY' ? 'Argent réel' : 'In-Game' ?>
                        </span>
                    </div>
                    <div class="seller-info">
                        <span>Vendeur: <?= sanitizeOutput($listing['seller_name']) ?></span>
                        <?php if ($listing['rating_avg'] > 0): ?>
                        <span class="rating">⭐ <?= number_format($listing['rating_avg'], 1) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="listing-meta">
                        <small><?= date('d/m/Y H:i', strtotime($listing['created_at'])) ?></small>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="item.php?id=<?= $listing['item_id'] ?>" class="btn btn-primary btn-sm">Voir l'item</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Call to Action -->
    <?php if (!$current_user): ?>
    <section class="cta-section">
        <div class="card">
            <div class="card-body text-center">
                <h2>Rejoignez la communauté StarMarket</h2>
                <p>Créez votre compte pour commencer à acheter et vendre dans l'univers de Star Citizen</p>
                <div class="cta-actions">
                    <a href="register.php" class="btn btn-primary">Créer un compte</a>
                    <a href="login.php" class="btn btn-outline">Se connecter</a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<style>
.hero {
    text-align: center;
    padding: 4rem 0;
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
    border-radius: var(--radius-lg);
    margin-bottom: 3rem;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: bold;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 1.25rem;
    color: var(--text-secondary);
    margin-bottom: 2rem;
    line-height: 1.6;
}

.hero-stats {
    display: flex;
    justify-content: center;
    gap: 3rem;
    margin-bottom: 2rem;
}

.stat {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary);
}

.stat-label {
    display: block;
    font-size: 0.875rem;
    color: var(--text-muted);
}

.hero-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.quick-actions {
    margin-bottom: 3rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.section-header h2 {
    font-size: 1.75rem;
    color: var(--text-primary);
}

.deals-section,
.latest-section {
    margin-bottom: 3rem;
}

.listing-card {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.listing-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.listing-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.deal-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background-color: var(--error);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.75rem;
    font-weight: bold;
}

.variant-info {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.color-info {
    color: var(--text-muted);
}

.price-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.price {
    font-size: 1.125rem;
    font-weight: bold;
    color: var(--primary);
}

.seller-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.rating {
    color: var(--accent);
}

.listing-meta {
    color: var(--text-muted);
    font-size: 0.75rem;
}

.cta-section {
    margin-top: 4rem;
}

.cta-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .section-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}
</style>

<?php require_once 'footer.php'; ?>