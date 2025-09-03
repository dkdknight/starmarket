<?php
$page_title = 'Parcourir les annonces';
require_once 'header.php';

// Paramètres de recherche et filtres
$search = trim($_GET['q'] ?? '');
$sale_type = $_GET['sale_type'] ?? '';
$category = $_GET['category'] ?? '';
$item_id = (int)($_GET['item_id'] ?? 0);
$variant_id = (int)($_GET['variant_id'] ?? 0);
$min_price = (float)($_GET['min_price'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);
$region = $_GET['region'] ?? '';
$sort = $_GET['sort'] ?? 'recent';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// Construction de la requête
$where_conditions = ['l.status = "ACTIVE"', 'u.is_banned = FALSE'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = '(i.name LIKE ? OR i.description LIKE ? OR u.username LIKE ?)';
    $search_term = '%' . $search . '%';
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($sale_type)) {
    $where_conditions[] = 'l.sale_type = ?';
    $params[] = $sale_type;
}

if (!empty($category)) {
    $where_conditions[] = 'i.category = ?';
    $params[] = $category;
}

if ($item_id > 0) {
    $where_conditions[] = 'l.item_id = ?';
    $params[] = $item_id;
}

if ($variant_id > 0) {
    $where_conditions[] = 'l.variant_id = ?';
    $params[] = $variant_id;
}

if (!empty($region)) {
    $where_conditions[] = 'l.region = ?';
    $params[] = $region;
}

// Filtres de prix selon le type
if ($min_price > 0 || $max_price > 0) {
    if (!empty($sale_type)) {
        if ($sale_type === 'REAL_MONEY') {
            if ($min_price > 0) {
                $where_conditions[] = 'l.price_real >= ?';
                $params[] = $min_price;
            }
            if ($max_price > 0) {
                $where_conditions[] = 'l.price_real <= ?';
                $params[] = $max_price;
            }
        } elseif ($sale_type === 'IN_GAME') {
            if ($min_price > 0) {
                $where_conditions[] = 'l.price_auec >= ?';
                $params[] = $min_price;
            }
            if ($max_price > 0) {
                $where_conditions[] = 'l.price_auec <= ?';
                $params[] = $max_price;
            }
        }
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Tri
$order_clause = match($sort) {
    'price_asc' => 'COALESCE(l.price_real, l.price_auec/1000) ASC',
    'price_desc' => 'COALESCE(l.price_real, l.price_auec/1000) DESC',
    'oldest' => 'l.created_at ASC',
    'name' => 'i.name ASC',
    default => 'l.created_at DESC'
};

// Compter le total
$count_sql = "
    SELECT COUNT(*)
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    WHERE {$where_clause}
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_listings = $stmt->fetchColumn();

// Calculer la pagination
$pagination = getPaginationData($page, $total_listings, $per_page);

// Récupérer les annonces
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
           END as deal_score
    FROM listings l
    JOIN items i ON l.item_id = i.id
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    LEFT JOIN price_reference pr ON i.id = pr.item_id
    WHERE {$where_clause}
    ORDER BY {$order_clause}
    LIMIT {$per_page} OFFSET {$pagination['offset']}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// Options pour les filtres
$categories = $pdo->query("SELECT DISTINCT category FROM items WHERE is_active = TRUE ORDER BY category")->fetchAll();
$regions = $pdo->query("SELECT DISTINCT region FROM listings WHERE region IS NOT NULL AND region != '' ORDER BY region")->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>Parcourir les Annonces</h1>
        <p>Trouvez les meilleures offres pour vos items Star Citizen préférés</p>
    </div>

    <!-- Filtres avancés -->
    <div class="advanced-filters">
        <form method="GET" class="filters-form">
            <div class="filters-section">
                <h3>🔍 Recherche et filtres</h3>
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Rechercher</label>
                        <input type="text" 
                               name="q" 
                               value="<?= sanitizeOutput($search) ?>" 
                               placeholder="Item, vendeur..."
                               class="form-input">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Type de vente</label>
                        <select name="sale_type" class="form-select">
                            <option value="">Tous les types</option>
                            <option value="REAL_MONEY" <?= $sale_type === 'REAL_MONEY' ? 'selected' : '' ?>>
                                💰 Argent réel
                            </option>
                            <option value="IN_GAME" <?= $sale_type === 'IN_GAME' ? 'selected' : '' ?>>
                                🎮 In-Game (aUEC)
                            </option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Catégorie</label>
                        <select name="category" class="form-select">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category'] ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                <?php
                                switch ($cat['category']) {
                                    case 'SHIP': echo '🚀 Vaisseaux'; break;
                                    case 'ARMOR': echo '🛡️ Armures'; break;
                                    case 'WEAPON': echo '⚔️ Armes'; break;
                                    case 'COMPONENT': echo '🔧 Composants'; break;
                                    case 'PAINT': echo '🎨 Peintures'; break;
                                    case 'OTHER': echo '📦 Autres'; break;
                                }
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Région</label>
                        <select name="region" class="form-select">
                            <option value="">Toutes les régions</option>
                            <?php foreach ($regions as $reg): ?>
                            <option value="<?= sanitizeOutput($reg['region']) ?>" <?= $region === $reg['region'] ? 'selected' : '' ?>>
                                <?= sanitizeOutput($reg['region']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="price-filters">
                    <div class="filter-group">
                        <label class="filter-label">Prix minimum</label>
                        <input type="number" 
                               name="min_price" 
                               value="<?= $min_price > 0 ? $min_price : '' ?>" 
                               placeholder="0"
                               step="0.01"
                               class="form-input">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Prix maximum</label>
                        <input type="number" 
                               name="max_price" 
                               value="<?= $max_price > 0 ? $max_price : '' ?>" 
                               placeholder="Illimité"
                               step="0.01"
                               class="form-input">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Rechercher</button>
                    <a href="browse.php" class="btn btn-outline">🔄 Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Résultats et tri -->
    <div class="results-section">
        <div class="results-header">
            <div class="results-info">
                <strong><?= number_format($total_listings) ?></strong> annonce(s) trouvée(s)
                <?php if (!empty($search)): ?>
                pour "<strong><?= sanitizeOutput($search) ?></strong>"
                <?php endif; ?>
            </div>
            
            <div class="sort-options">
                <label for="sort-select">Trier par:</label>
                <select id="sort-select" name="sort" class="form-select" onchange="updateSort(this.value)">
                    <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récentes</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Plus anciennes</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nom A-Z</option>
                </select>
            </div>
        </div>

        <!-- Liste des annonces -->
        <?php if (!empty($listings)): ?>
        <div class="listings-list">
            <?php foreach ($listings as $listing): ?>
            <div class="listing-row">
                <div class="listing-image">
                    <img src="<?= sanitizeOutput($listing['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                         alt="<?= sanitizeOutput($listing['item_name']) ?>">
                    <div class="category-badge">
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
                    </div>
                </div>
                
                <div class="listing-content">
                    <div class="listing-header">
                        <h3 class="item-name">
                            <a href="item.php?id=<?= $listing['item_id'] ?>"><?= sanitizeOutput($listing['item_name']) ?></a>
                        </h3>
                        
                        <?php if ($listing['variant_name']): ?>
                        <span class="variant-info">
                            <?= sanitizeOutput($listing['variant_name']) ?>
                            <?php if ($listing['color_name']): ?>
                            - <?= sanitizeOutput($listing['color_name']) ?>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="listing-details">
                        <div class="seller-info">
                            <a href="profile.php?u=<?= urlencode($listing['username']) ?>" class="seller-link">
                                👤 <?= sanitizeOutput($listing['username']) ?>
                            </a>
                            <?php if ($listing['rating_avg'] > 0): ?>
                            <span class="rating">⭐ <?= number_format($listing['rating_avg'], 1) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="listing-meta">
                            <?php if ($listing['sale_type'] === 'REAL_MONEY'): ?>
                            <span class="location">📍 <?= sanitizeOutput($listing['region']) ?></span>
                            <?php else: ?>
                            <span class="location">🎮 <?= sanitizeOutput($listing['meet_location']) ?></span>
                            <?php endif; ?>
                            <span class="date">📅 <?= date('d/m/Y', strtotime($listing['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="listing-price">
                    <div class="price-section">
                        <?php if ($listing['sale_type'] === 'REAL_MONEY'): ?>
                        <div class="price"><?= formatPrice($listing['price_real'], $listing['currency']) ?></div>
                        <div class="price-type">💰 Argent réel</div>
                        <?php else: ?>
                        <div class="price"><?= formatPrice($listing['price_auec'], 'aUEC') ?></div>
                        <div class="price-type">🎮 In-Game</div>
                        <?php endif; ?>
                        
                        <?php if ($listing['deal_score'] > 0.1): ?>
                        <div class="deal-badge">-<?= round($listing['deal_score'] * 100) ?>%</div>
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
                    <a href="my-listings.php" class="btn btn-outline btn-sm">
                        Mes annonces
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pagination['has_prev']): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>" 
               class="btn btn-outline">← Précédent</a>
            <?php endif; ?>
            
            <div class="pagination-info">
                Page <?= $pagination['current_page'] ?> sur <?= $pagination['total_pages'] ?>
                (<?= number_format($total_listings) ?> annonce<?= $total_listings > 1 ? 's' : '' ?>)
            </div>
            
            <?php if ($pagination['has_next']): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>" 
               class="btn btn-outline">Suivant →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-results">
            <div class="no-results-content">
                <h3>😔 Aucune annonce trouvée</h3>
                <p>Essayez de modifier vos critères de recherche ou parcourez toutes les annonces.</p>
                <div class="no-results-actions">
                    <a href="browse.php" class="btn btn-primary">Voir toutes les annonces</a>
                    <?php if ($current_user): ?>
                    <a href="sell.php" class="btn btn-success">Créer une annonce</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.advanced-filters {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
}

.filters-section h3 {
    margin-bottom: 1.5rem;
    color: var(--text-primary);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.price-filters {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.filter-actions {
    display: flex;
    gap: 1rem;
}

.results-section {
    margin-top: 2rem;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.sort-options {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sort-options label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.listings-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.listing-row {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 80px 1fr auto auto;
    gap: 1.5rem;
    align-items: center;
    transition: all 0.2s;
}

.listing-row:hover {
    border-color: var(--border-light);
    box-shadow: var(--shadow);
}

.listing-image {
    position: relative;
}

.listing-image img {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: var(--radius);
}

.category-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}

.listing-content {
    flex: 1;
}

.listing-header {
    margin-bottom: 0.5rem;
}

.item-name a {
    color: var(--text-primary);
    text-decoration: none;
    font-weight: 500;
    font-size: 1.125rem;
}

.item-name a:hover {
    color: var(--primary);
}

.variant-info {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-left: 0.5rem;
}

.listing-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
    margin-left: 0.5rem;
}

.listing-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.listing-price {
    text-align: center;
}

.price {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.price-type {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.deal-badge {
    background-color: var(--error);
    color: white;
    padding: 0.125rem 0.375rem;
    border-radius: var(--radius);
    font-size: 0.625rem;
    font-weight: bold;
    margin-top: 0.25rem;
}

.listing-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.no-results {
    text-align: center;
    padding: 4rem 2rem;
}

.no-results-content h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.no-results-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

.no-results-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

@media (max-width: 1024px) {
    .listing-row {
        grid-template-columns: 60px 1fr;
        gap: 1rem;
    }
    
    .listing-price,
    .listing-actions {
        grid-column: 1 / -1;
        justify-self: center;
        margin-top: 1rem;
    }
    
    .listing-actions {
        flex-direction: row;
    }
}

@media (max-width: 768px) {
    .results-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .price-filters {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .no-results-actions {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<script>
function updateSort(sortValue) {
    const url = new URL(window.location);
    url.searchParams.set('sort', sortValue);
    url.searchParams.delete('page'); // Reset page when sorting
    window.location.href = url.toString();
}
</script>

<?php require_once 'footer.php'; ?>