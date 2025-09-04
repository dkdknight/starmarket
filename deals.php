<?php
$page_title = 'Bonnes Affaires';
require_once 'header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// Récupérer les bonnes affaires avec calcul du deal score
$sql = "
    SELECT l.*, i.name as item_name, i.image_url as item_image, i.category,
           u.username, u.rating_avg, u.rating_count,
           iv.variant_name, iv.color_name,
           pr.ref_price_real, pr.ref_price_auec,
           CASE 
               WHEN l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 
               THEN (pr.ref_price_real - l.price_real) / pr.ref_price_real
               WHEN l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 
               THEN (pr.ref_price_auec - l.price_auec) / pr.ref_price_auec
               ELSE 0
           END as deal_score,
           CASE 
               WHEN l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 
               THEN pr.ref_price_real - l.price_real
               WHEN l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 
               THEN (pr.ref_price_auec - l.price_auec) / 1000
               ELSE 0
           END as savings_amount
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    LEFT JOIN price_reference pr ON i.id = pr.item_id
    WHERE l.status = 'ACTIVE' AND u.is_banned = FALSE
    AND (
        (l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 AND l.price_real < pr.ref_price_real)
        OR
        (l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 AND l.price_auec < pr.ref_price_auec)
    )
    HAVING deal_score > 0.05
    ORDER BY deal_score DESC
    LIMIT {$per_page} OFFSET " . (($page - 1) * $per_page);

$stmt = $pdo->query($sql);
$deals = $stmt->fetchAll();

// Compter le total pour la pagination
$count_sql = "
    SELECT COUNT(*)
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN price_reference pr ON i.id = pr.item_id
    WHERE l.status = 'ACTIVE' AND u.is_banned = FALSE
    AND (
        (l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 AND l.price_real < pr.ref_price_real)
        OR
        (l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 AND l.price_auec < pr.ref_price_auec)
    )
    AND (
        CASE 
            WHEN l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 
            THEN (pr.ref_price_real - l.price_real) / pr.ref_price_real
            WHEN l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 
            THEN (pr.ref_price_auec - l.price_auec) / pr.ref_price_auec
            ELSE 0
        END
    ) > 0.05
";

$stmt = $pdo->query($count_sql);
$total_deals = $stmt->fetchColumn();

$pagination = getPaginationData($page, $total_deals, $per_page);

// Statistiques des bonnes affaires
$stats_sql = "
    SELECT 
        COUNT(*) as total_deals,
        AVG(CASE 
            WHEN l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 
            THEN (pr.ref_price_real - l.price_real) / pr.ref_price_real
            WHEN l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 
            THEN (pr.ref_price_auec - l.price_auec) / pr.ref_price_auec
            ELSE 0
        END) as avg_discount,
        MAX(CASE 
            WHEN l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 
            THEN (pr.ref_price_real - l.price_real) / pr.ref_price_real
            WHEN l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 
            THEN (pr.ref_price_auec - l.price_auec) / pr.ref_price_auec
            ELSE 0
        END) as max_discount
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN price_reference pr ON i.id = pr.item_id
    WHERE l.status = 'ACTIVE' AND u.is_banned = FALSE
    AND (
        (l.sale_type = 'REAL_MONEY' AND pr.ref_price_real > 0 AND l.price_real < pr.ref_price_real)
        OR
        (l.sale_type = 'IN_GAME' AND pr.ref_price_auec > 0 AND l.price_auec < pr.ref_price_auec)
    )
";

$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();
?>

<div class="container">
    <div class="page-header">
        <h1>🔥 Bonnes Affaires</h1>
        <p>Découvrez les meilleures offres du moment avec des réductions exceptionnelles</p>
    </div>

    <!-- Statistiques des bonnes affaires -->
    <div class="deals-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_deals']) ?></div>
                <div class="stat-label">Bonnes affaires disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= round($stats['avg_discount'] * 100) ?>%</div>
                <div class="stat-label">Réduction moyenne</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= round($stats['max_discount'] * 100) ?>%</div>
                <div class="stat-label">Meilleure réduction</div>
            </div>
        </div>
    </div>

    <!-- Explication du système -->
    <div class="deals-explanation">
        <div class="card">
            <div class="card-body">
                <h3>💡 Comment fonctionnent nos bonnes affaires ?</h3>
                <p>
                    Nous comparons automatiquement les prix des annonces avec nos prix de référence basés sur les valeurs du marché.
                    Seules les annonces offrant une réduction d'au moins <strong>5%</strong> sont affichées ici.
                </p>
                <div class="deal-legend">
                    <span class="deal-badge excellent">-50%+</span> Affaire exceptionnelle
                    <span class="deal-badge great">-25%+</span> Très bonne affaire
                    <span class="deal-badge good">-10%+</span> Bonne affaire
                    <span class="deal-badge normal">-5%+</span> Petite réduction
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des bonnes affaires -->
    <?php if (!empty($deals)): ?>
    <div class="deals-list">
        <?php foreach ($deals as $deal): ?>
        <div class="deal-card">
            <div class="deal-header">
                <div class="deal-discount">
                    <span class="discount-percentage 
                        <?php 
                        if ($deal['deal_score'] >= 0.5) echo 'excellent';
                        elseif ($deal['deal_score'] >= 0.25) echo 'great';
                        elseif ($deal['deal_score'] >= 0.1) echo 'good';
                        else echo 'normal';
                        ?>">
                        -<?= round($deal['deal_score'] * 100) ?>%
                    </span>
                    <div class="savings-amount">
                        Économie: 
                        <?php if ($deal['sale_type'] === 'REAL_MONEY'): ?>
                        <?= formatPrice($deal['savings_amount'], $deal['currency']) ?>
                        <?php else: ?>
                        <?= formatPrice($deal['savings_amount'] * 1000, 'aUEC') ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="deal-badges">
                    <span class="badge badge-category">
                        <?php
                        switch ($deal['category']) {
                            case 'SHIP': echo '🚀'; break;
                            case 'ARMOR': echo '🛡️'; break;
                            case 'WEAPON': echo '⚔️'; break;
                            case 'COMPONENT': echo '🔧'; break;
                            case 'PAINT': echo '🎨'; break;
                            case 'OTHER': echo '📦'; break;
                        }
                        ?>
                    </span>
                    <span class="badge badge-<?= $deal['sale_type'] === 'REAL_MONEY' ? 'primary' : 'success' ?>">
                        <?= $deal['sale_type'] === 'REAL_MONEY' ? '💰 Argent réel' : '🎮 In-Game' ?>
                    </span>
                </div>
            </div>

            <div class="deal-content">
                <div class="deal-image">
                    <img src="<?= sanitizeOutput($deal['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                         alt="<?= sanitizeOutput($deal['item_name']) ?>">
                </div>

                <div class="deal-info">
                    <h3 class="deal-title">
                        <a href="item.php?id=<?= $deal['item_id'] ?>"><?= sanitizeOutput($deal['item_name']) ?></a>
                    </h3>
                    
                    <?php if ($deal['variant_name']): ?>
                    <p class="deal-variant">
                        <?= sanitizeOutput($deal['variant_name']) ?>
                        <?php if ($deal['color_name']): ?>
                        - <?= sanitizeOutput($deal['color_name']) ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>

                    <div class="deal-seller">
                        <a href="profile.php?u=<?= urlencode($deal['username']) ?>">
                            👤 <?= sanitizeOutput($deal['username']) ?>
                        </a>
                        <?php if ($deal['rating_avg'] > 0): ?>
                        <span class="rating">⭐ <?= number_format($deal['rating_avg'], 1) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="deal-pricing">
                    <div class="price-comparison">
                        <div class="current-price">
                            <?php if ($deal['sale_type'] === 'REAL_MONEY'): ?>
                            <span class="price-label">Prix actuel</span>
                            <span class="price"><?= formatPrice($deal['price_real'], $deal['currency']) ?></span>
                            <?php else: ?>
                            <span class="price-label">Prix actuel</span>
                            <span class="price"><?= formatPrice($deal['price_auec'], 'aUEC') ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="reference-price">
                            <?php if ($deal['sale_type'] === 'REAL_MONEY'): ?>
                            <span class="price-label">Prix référence</span>
                            <span class="price-crossed"><?= formatPrice($deal['ref_price_real'], $deal['currency']) ?></span>
                            <?php else: ?>
                            <span class="price-label">Prix référence</span>
                            <span class="price-crossed"><?= formatPrice($deal['ref_price_auec'], 'aUEC') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="deal-actions">
                    <?php if ($current_user && $current_user['id'] !== $deal['seller_id']): ?>
                    <a href="contact-seller.php?listing_id=<?= $deal['id'] ?>" 
                       class="btn btn-primary">
                        🚀 Profiter de l'offre
                    </a>
                    <a href="item.php?id=<?= $deal['item_id'] ?>" 
                       class="btn btn-outline btn-sm">
                        Voir détails
                    </a>
                    <?php elseif (!$current_user): ?>
                    <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                       class="btn btn-primary">
                        Se connecter pour acheter
                    </a>
                    <?php else: ?>
                    <span class="own-listing">Votre annonce</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="deal-footer">
                <div class="deal-meta">
                    <span class="deal-date">📅 Publié le <?= date('d/m/Y', strtotime($deal['created_at'])) ?></span>
                    <?php if ($deal['sale_type'] === 'REAL_MONEY'): ?>
                    <span class="deal-location">📍 <?= sanitizeOutput($deal['region']) ?></span>
                    <?php else: ?>
                    <span class="deal-location">🎮 <?= sanitizeOutput($deal['meet_location']) ?></span>
                    <?php endif; ?>
                </div>
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
        </div>
        
        <?php if ($pagination['has_next']): ?>
        <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="btn btn-outline">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="no-deals">
        <div class="no-deals-content">
            <h3>😔 Aucune bonne affaire disponible</h3>
            <p>Il n'y a pas de bonnes affaires en ce moment. Revenez plus tard ou consultez toutes les annonces.</p>
            <div class="no-deals-actions">
                <a href="browse.php" class="btn btn-primary">Voir toutes les annonces</a>
                <a href="items.php" class="btn btn-outline">Parcourir le catalogue</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.deals-stats {
    margin: 2rem 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.deals-explanation {
    margin: 2rem 0;
}

.deals-explanation h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.deal-legend {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.deal-legend .deal-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.deals-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.deal-card {
    background-color: var(--bg-card);
    border: 2px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.2s;
}

.deal-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-lg);
}

.deal-header {
    background: linear-gradient(135deg, var(--error) 0%, var(--warning) 100%);
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.deal-discount {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.discount-percentage {
    font-size: 1.5rem;
    font-weight: bold;
    color: white;
    background-color: rgba(0, 0, 0, 0.3);
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius);
}

.discount-percentage.excellent {
    background-color: var(--error);
}

.discount-percentage.great {
    background-color: var(--warning);
}

.discount-percentage.good {
    background-color: var(--success);
}

.discount-percentage.normal {
    background-color: var(--primary);
}

.savings-amount {
    color: white;
    font-size: 0.875rem;
    font-weight: 500;
}

.deal-badges {
    display: flex;
    gap: 0.5rem;
}

.deal-content {
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 120px 1fr auto auto;
    gap: 1.5rem;
    align-items: center;
}

.deal-image img {
    width: 120px;
    height: 80px;
    object-fit: cover;
    border-radius: var(--radius);
}

.deal-title a {
    color: var(--text-primary);
    text-decoration: none;
    font-size: 1.25rem;
    font-weight: 500;
}

.deal-title a:hover {
    color: var(--primary);
}

.deal-variant {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin: 0.25rem 0;
}

.deal-seller a {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.875rem;
}

.deal-seller .rating {
    color: var(--accent);
    margin-left: 0.5rem;
}

.deal-pricing {
    text-align: center;
}

.price-comparison {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.price-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: block;
}

.price {
    font-size: 1.125rem;
    font-weight: bold;
    color: var(--success);
}

.price-crossed {
    font-size: 1rem;
    color: var(--text-muted);
    text-decoration: line-through;
}

.deal-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.deal-footer {
    padding: 1rem 1.5rem;
    background-color: var(--bg-tertiary);
    border-top: 1px solid var(--border);
}

.deal-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.no-deals {
    text-align: center;
    padding: 4rem 2rem;
}

.no-deals-content h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.no-deals-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

.no-deals-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

@media (max-width: 1024px) {
    .deal-content {
        grid-template-columns: 100px 1fr;
        gap: 1rem;
    }
    
    .deal-pricing,
    .deal-actions {
        grid-column: 1 / -1;
        margin-top: 1rem;
    }
    
    .deal-actions {
        flex-direction: row;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .deal-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .deal-content {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .deal-meta {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .no-deals-actions {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<?php require_once 'footer.php'; ?>