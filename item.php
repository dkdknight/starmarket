<?php
$page_title = 'Détails de l\'item';
require_once 'header.php';

$item_id = (int)($_GET['id'] ?? 0);
$selected_tab = $_GET['tab'] ?? 'REAL_MONEY';

if (!$item_id) {
    header('Location: items.php');
    exit;
}

// Récupérer l'item
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? AND is_active = TRUE");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: items.php?error=not_found');
    exit;
}

// Récupérer les variantes
$stmt = $pdo->prepare("
    SELECT * FROM item_variants 
    WHERE item_id = ? AND is_active = TRUE 
    ORDER BY variant_name, color_name
");
$stmt->execute([$item_id]);
$variants = $stmt->fetchAll();

// Récupérer les annonces par type
$listings_real = [];
$listings_ingame = [];

// Annonces argent réel
$stmt = $pdo->prepare("
    SELECT l.*, u.username, u.rating_avg, u.rating_count,
           iv.variant_name, iv.color_name
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    WHERE l.item_id = ? AND l.sale_type = 'REAL_MONEY' 
    AND l.status = 'ACTIVE' AND u.is_banned = FALSE
    ORDER BY l.price_real ASC
");
$stmt->execute([$item_id]);
$listings_real = $stmt->fetchAll();

// Annonces in-game
$stmt = $pdo->prepare("
    SELECT l.*, u.username, u.rating_avg, u.rating_count,
           iv.variant_name, iv.color_name
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    WHERE l.item_id = ? AND l.sale_type = 'IN_GAME' 
    AND l.status = 'ACTIVE' AND u.is_banned = FALSE
    ORDER BY l.price_auec ASC
");
$stmt->execute([$item_id]);
$listings_ingame = $stmt->fetchAll();

// Prix de référence pour calculer les bonnes affaires
$stmt = $pdo->prepare("SELECT * FROM price_reference WHERE item_id = ?");
$stmt->execute([$item_id]);
$price_reference = $stmt->fetch();

// Titre de la page
$page_title = $item['name'];
?>

<div class="container">
    <div class="item-detail">
        <!-- Navigation de retour -->
        <div class="breadcrumb">
            <a href="items.php">← Retour au catalogue</a>
        </div>

        <!-- Header de l'item -->
        <div class="item-header">
            <div class="item-image-section">
                <img id="item-image" 
                     src="<?= sanitizeOutput($item['image_url'] ?: 'assets/img/placeholder.jpg') ?>" 
                     alt="<?= sanitizeOutput($item['name']) ?>"
                     class="item-main-image">
                
                <div class="item-badges">
                    <span class="badge badge-category category-<?= strtolower($item['category']) ?>">
                        <?php
                        switch ($item['category']) {
                            case 'SHIP': echo '🚀 Vaisseau'; break;
                            case 'ARMOR': echo '🛡️ Armure'; break;
                            case 'WEAPON': echo '⚔️ Arme'; break;
                            case 'COMPONENT': echo '🔧 Composant'; break;
                            case 'PAINT': echo '🎨 Peinture'; break;
                            case 'OTHER': echo '📦 Autre'; break;
                        }
                        ?>
                    </span>
                    <span class="source-badge source-<?= strtolower($item['source']) ?>">
                        <?php
                        switch ($item['source']) {
                            case 'INGAME': echo '🎮 In-Game'; break;
                            case 'PLEDGE': echo '💰 Pledge Store'; break;
                            case 'BOTH': echo '🎮💰 Les deux'; break;
                        }
                        ?>
                    </span>
                </div>
            </div>

            <div class="item-info">
                <h1 class="item-title"><?= sanitizeOutput($item['name']) ?></h1>
                
                <?php if ($item['manufacturer']): ?>
                <p class="item-manufacturer">Par <?= sanitizeOutput($item['manufacturer']) ?></p>
                <?php endif; ?>
                
                <p class="item-description"><?= sanitizeOutput($item['description']) ?></p>

                <!-- Sélecteur de variantes -->
                <?php if (!empty($variants)): ?>
                <div class="variant-selector">
                    <label for="variant-select" class="form-label">Variante / Couleur</label>
                    <select id="variant-select" class="form-select">
                        <option value="" data-image="<?= sanitizeOutput($item['image_url']) ?>">Version standard</option>
                        <?php foreach ($variants as $variant): ?>
                        <option value="<?= $variant['id'] ?>" 
                                data-image="<?= sanitizeOutput($variant['image_url'] ?: $item['image_url']) ?>">
                            <?= sanitizeOutput($variant['variant_name']) ?>
                            <?php if ($variant['color_name']): ?>
                            - <?= sanitizeOutput($variant['color_name']) ?>
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Actions utilisateur -->
                <?php if ($current_user): ?>
                <div class="item-actions">
                    <button class="btn btn-outline btn-sm" onclick="addToWatchlist(<?= $item['id'] ?>)">
                        ⭐ Ajouter à ma watchlist
                    </button>
                    <a href="sell.php?item_id=<?= $item['id'] ?>" class="btn btn-primary btn-sm">
                        Vendre cet item
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Onglets des annonces -->
        <div class="listings-tabs">
            <div class="tab-buttons">
                <button class="tab-button <?= $selected_tab === 'REAL_MONEY' ? 'active' : '' ?>" 
                        data-tab="real-money-tab">
                    💰 Argent Réel (<?= count($listings_real) ?>)
                </button>
                <button class="tab-button <?= $selected_tab === 'IN_GAME' ? 'active' : '' ?>" 
                        data-tab="in-game-tab">
                    🎮 In-Game aUEC (<?= count($listings_ingame) ?>)
                </button>
            </div>

            <!-- Onglet Argent Réel -->
            <div id="real-money-tab" class="tab-content <?= $selected_tab === 'REAL_MONEY' ? 'active' : '' ?>">
                <div class="tab-header">
                    <h3>Annonces en Argent Réel</h3>
                    <p class="tab-description">
                        Ces transactions utilisent le système de "gifting" de Star Citizen. 
                        Soyez prudent et utilisez uniquement des vendeurs de confiance.
                    </p>
                </div>

                <?php if (!empty($listings_real)): ?>
                <div class="listings-grid">
                    <?php foreach ($listings_real as $listing): ?>
                    <div class="listing-card">
                        <div class="listing-header">
                            <div class="price-info">
                                <span class="price"><?= formatPrice($listing['price_real'], $listing['currency']) ?></span>
                                <?php if ($price_reference && $price_reference['ref_price_real']): ?>
                                <?php 
                                $deal_score = calculateDealScore($listing, $price_reference);
                                if ($deal_score && $deal_score > 0.1): ?>
                                <span class="deal-badge">-<?= round($deal_score * 100) ?>%</span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($listing['variant_name']): ?>
                            <div class="variant-info">
                                <span class="variant"><?= sanitizeOutput($listing['variant_name']) ?></span>
                                <?php if ($listing['color_name']): ?>
                                <span class="color">(<?= sanitizeOutput($listing['color_name']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="listing-body">
                            <div class="seller-info">
                                <a href="profile.php?u=<?= urlencode($listing['username']) ?>" class="seller-link">
                                    <?= sanitizeOutput($listing['username']) ?>
                                </a>
                                <?php if ($listing['rating_avg'] > 0): ?>
                                <span class="rating">
                                    ⭐ <?= number_format($listing['rating_avg'], 1) ?> 
                                    (<?= $listing['rating_count'] ?> avis)
                                </span>
                                <?php else: ?>
                                <span class="rating-new">Nouveau vendeur</span>
                                <?php endif; ?>
                            </div>

                            <div class="listing-details">
                                <p><strong>Région:</strong> <?= sanitizeOutput($listing['region']) ?></p>
                                <?php if ($listing['notes']): ?>
                                <p><strong>Notes:</strong> <?= sanitizeOutput($listing['notes']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="listing-footer">
                            <?php if ($current_user && $current_user['id'] !== $listing['seller_id']): ?>
                            <a href="contact-seller.php?listing_id=<?= $listing['id'] ?>" 
                               class="btn btn-primary btn-sm">
                                💬 Contacter le vendeur
                            </a>
                            <?php elseif (!$current_user): ?>
                            <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn btn-outline btn-sm">
                                Connectez-vous pour contacter
                            </a>
                            <?php else: ?>
                            <span class="own-listing">Votre annonce</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-listings">
                    <p>Aucune annonce en argent réel pour cet item actuellement.</p>
                    <?php if ($current_user): ?>
                    <a href="sell.php?item_id=<?= $item['id'] ?>&type=REAL_MONEY" class="btn btn-primary">
                        Être le premier à vendre
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Onglet In-Game -->
            <div id="in-game-tab" class="tab-content <?= $selected_tab === 'IN_GAME' ? 'active' : '' ?>">
                <div class="tab-header">
                    <h3>Annonces In-Game (aUEC)</h3>
                    <p class="tab-description">
                        Transactions en monnaie du jeu. Organisez vos rendez-vous via la messagerie intégrée.
                    </p>
                </div>

                <?php if (!empty($listings_ingame)): ?>
                <div class="listings-grid">
                    <?php foreach ($listings_ingame as $listing): ?>
                    <div class="listing-card">
                        <div class="listing-header">
                            <div class="price-info">
                                <span class="price"><?= formatPrice($listing['price_auec'], 'aUEC') ?></span>
                                <?php if ($price_reference && $price_reference['ref_price_auec']): ?>
                                <?php 
                                $deal_score = calculateDealScore($listing, $price_reference);
                                if ($deal_score && $deal_score > 0.1): ?>
                                <span class="deal-badge">-<?= round($deal_score * 100) ?>%</span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($listing['variant_name']): ?>
                            <div class="variant-info">
                                <span class="variant"><?= sanitizeOutput($listing['variant_name']) ?></span>
                                <?php if ($listing['color_name']): ?>
                                <span class="color">(<?= sanitizeOutput($listing['color_name']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="listing-body">
                            <div class="seller-info">
                                <a href="profile.php?u=<?= urlencode($listing['username']) ?>" class="seller-link">
                                    <?= sanitizeOutput($listing['username']) ?>
                                </a>
                                <?php if ($listing['rating_avg'] > 0): ?>
                                <span class="rating">
                                    ⭐ <?= number_format($listing['rating_avg'], 1) ?> 
                                    (<?= $listing['rating_count'] ?> avis)
                                </span>
                                <?php else: ?>
                                <span class="rating-new">Nouveau vendeur</span>
                                <?php endif; ?>
                            </div>

                            <div class="listing-details">
                                <p><strong>Lieu de RDV:</strong> <?= sanitizeOutput($listing['meet_location']) ?></p>
                                <p><strong>Disponibilité:</strong> <?= sanitizeOutput($listing['availability']) ?></p>
                                <?php if ($listing['notes']): ?>
                                <p><strong>Notes:</strong> <?= sanitizeOutput($listing['notes']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="listing-footer">
                            <?php if ($current_user && $current_user['id'] !== $listing['seller_id']): ?>
                            <a href="contact-seller.php?listing_id=<?= $listing['id'] ?>" 
                               class="btn btn-success btn-sm">
                                🎮 Organiser un RDV
                            </a>
                            <?php elseif (!$current_user): ?>
                            <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn btn-outline btn-sm">
                                Connectez-vous pour organiser un RDV
                            </a>
                            <?php else: ?>
                            <span class="own-listing">Votre annonce</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-listings">
                    <p>Aucune annonce in-game pour cet item actuellement.</p>
                    <?php if ($current_user): ?>
                    <a href="sell.php?item_id=<?= $item['id'] ?>&type=IN_GAME" class="btn btn-success">
                        Être le premier à vendre
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.breadcrumb {
    margin-bottom: 1.5rem;
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.875rem;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.item-header {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    margin-bottom: 3rem;
}

.item-image-section {
    position: relative;
}

.item-main-image {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
}

.item-badges {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.item-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.item-title {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.item-manufacturer {
    color: var(--text-muted);
    font-size: 1.125rem;
    margin-bottom: 1rem;
}

.item-description {
    color: var(--text-secondary);
    font-size: 1.125rem;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.variant-selector {
    margin-bottom: 2rem;
}

.item-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.listings-tabs {
    margin-top: 3rem;
}

.tab-buttons {
    display: flex;
    border-bottom: 1px solid var(--border);
    margin-bottom: 2rem;
}

.tab-button {
    background: none;
    border: none;
    padding: 1rem 2rem;
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 1rem;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-button:hover {
    color: var(--text-primary);
    background-color: var(--bg-hover);
}

.tab-button.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-header {
    margin-bottom: 2rem;
}

.tab-header h3 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.tab-description {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.listings-grid {
    display: grid;
    gap: 1.5rem;
}

.listing-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.listing-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.price-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.price {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary);
}

.deal-badge {
    background-color: var(--error);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.75rem;
    font-weight: bold;
}

.variant-info {
    text-align: right;
    font-size: 0.875rem;
}

.variant {
    color: var(--text-primary);
    font-weight: 500;
}

.color {
    color: var(--text-muted);
}

.listing-body {
    padding: 1.5rem;
}

.seller-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.seller-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.seller-link:hover {
    text-decoration: underline;
}

.rating {
    color: var(--accent);
    font-size: 0.875rem;
}

.rating-new {
    color: var(--text-muted);
    font-size: 0.875rem;
}

.listing-details p {
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.listing-footer {
    padding: 1rem 1.5rem;
    background-color: var(--bg-tertiary);
    border-top: 1px solid var(--border);
}

.own-listing {
    color: var(--text-muted);
    font-style: italic;
}

.no-listings {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.no-listings p {
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .item-header {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .item-title {
        font-size: 2rem;
    }
    
    .tab-buttons {
        flex-direction: column;
    }
    
    .tab-button {
        padding: 0.75rem 1rem;
    }
    
    .item-actions {
        flex-direction: column;
    }
    
    .listing-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .variant-info {
        text-align: left;
    }
    
    .seller-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<?php require_once 'footer.php'; ?>