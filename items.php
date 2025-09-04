<?php
$page_title = 'Catalogue';
require_once 'header.php';

// Paramètres de recherche et filtres
$search = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$source = $_GET['source'] ?? '';
$manufacturer = $_GET['manufacturer'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

// Construction de la requête
$where_conditions = ['i.is_active = TRUE'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = '(i.name LIKE ? OR i.description LIKE ?)';
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($category)) {
    $where_conditions[] = 'i.category = ?';
    $params[] = $category;
}

if (!empty($source)) {
    $where_conditions[] = 'i.source = ?';
    $params[] = $source;
}

if (!empty($manufacturer)) {
    $where_conditions[] = 'i.manufacturer = ?';
    $params[] = $manufacturer;
}

$where_clause = implode(' AND ', $where_conditions);

// Compter le total
$count_sql = "SELECT COUNT(*) FROM items i WHERE {$where_clause}";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_items = $stmt->fetchColumn();

// Calculer la pagination
$pagination = getPaginationData($page, $total_items, $per_page);

// Récupérer les items
$sql = "
    SELECT i.*, 
           COUNT(DISTINCT iv.id) as variant_count,
           COUNT(DISTINCT l.id) as listing_count
    FROM items i
    LEFT JOIN item_variants iv ON i.id = iv.item_id AND iv.is_active = TRUE
    LEFT JOIN listings l ON i.id = l.item_id AND l.status = 'ACTIVE'
    WHERE {$where_clause}
    GROUP BY i.id
    ORDER BY i.name ASC
    LIMIT {$per_page} OFFSET {$pagination['offset']}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Récupérer les options pour les filtres
$categories = $pdo->query("SELECT DISTINCT category FROM items WHERE is_active = TRUE ORDER BY category")->fetchAll();
$sources = $pdo->query("SELECT DISTINCT source FROM items WHERE is_active = TRUE ORDER BY source")->fetchAll();
$manufacturers = $pdo->query("SELECT DISTINCT manufacturer FROM items WHERE is_active = TRUE AND manufacturer IS NOT NULL ORDER BY manufacturer")->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>Catalogue Star Citizen</h1>
        <p>Découvrez tous les items, vaisseaux et équipements disponibles dans l'univers de Star Citizen</p>
    </div>

    <!-- Barre de recherche et filtres -->
    <div class="search-filters">
        <form method="GET" class="search-form">
            <div class="search-bar">
                <input type="text" 
                       name="q" 
                       value="<?= sanitizeOutput($search) ?>" 
                       placeholder="Rechercher un item, vaisseau..."
                       class="form-input search-input">
                <button type="submit" class="btn btn-primary">Rechercher</button>
            </div>
            
            <div class="filters-row">
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
                                default: echo $cat['category'];
                            }
                            ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Source</label>
                    <select name="source" class="form-select">
                        <option value="">Toutes les sources</option>
                        <?php foreach ($sources as $src): ?>
                        <option value="<?= $src['source'] ?>" <?= $source === $src['source'] ? 'selected' : '' ?>>
                            <?php
                            switch ($src['source']) {
                                case 'INGAME': echo '🎮 In-Game'; break;
                                case 'PLEDGE': echo '💰 Pledge Store'; break;
                                case 'BOTH': echo '🎮💰 Les deux'; break;
                                default: echo $src['source'];
                            }
                            ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Constructeur</label>
                    <select name="manufacturer" class="form-select">
                        <option value="">Tous les constructeurs</option>
                        <?php foreach ($manufacturers as $manu): ?>
                        <option value="<?= sanitizeOutput($manu['manufacturer']) ?>" <?= $manufacturer === $manu['manufacturer'] ? 'selected' : '' ?>>
                            <?= sanitizeOutput($manu['manufacturer']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
                    <a href="items.php" class="btn btn-outline btn-sm">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Résultats -->
    <div class="results-header">
        <div class="results-info">
            <strong><?= number_format($total_items) ?></strong> item(s) trouvé(s)
            <?php if (!empty($search)): ?>
            pour "<strong><?= sanitizeOutput($search) ?></strong>"
            <?php endif; ?>
        </div>
    </div>

    <!-- Grille des items -->
    <?php if (!empty($items)): ?>
    <div class="items-grid">
        <?php foreach ($items as $item): ?>
        <div class="item-card">
            <div class="item-image">
                <img src="<?= sanitizeOutput($item['image_url'] ?: 'assets/img/placeholder.jpg') ?>" 
                     alt="<?= sanitizeOutput($item['name']) ?>"
                     loading="lazy">
                <div class="item-badges">
                    <span class="badge badge-category category-<?= strtolower($item['category']) ?>">
                        <?php
                        switch ($item['category']) {
                            case 'SHIP': echo '🚀'; break;
                            case 'ARMOR': echo '🛡️'; break;
                            case 'WEAPON': echo '⚔️'; break;
                            case 'COMPONENT': echo '🔧'; break;
                            case 'PAINT': echo '🎨'; break;
                            case 'OTHER': echo '📦'; break;
                        }
                        ?>
                    </span>
                    <?php if ($item['variant_count'] > 0): ?>
                    <span class="badge badge-variants">
                        <?= $item['variant_count'] ?> variant<?= $item['variant_count'] > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="item-content">
                <h3 class="item-name"><?= sanitizeOutput($item['name']) ?></h3>
                
                <?php if ($item['manufacturer']): ?>
                <p class="item-manufacturer"><?= sanitizeOutput($item['manufacturer']) ?></p>
                <?php endif; ?>
                
                <p class="item-description">
                    <?= sanitizeOutput(substr($item['description'], 0, 120)) ?><?= strlen($item['description']) > 120 ? '...' : '' ?>
                </p>
                
                <div class="item-meta">
                    <span class="source-badge source-<?= strtolower($item['source']) ?>">
                        <?php
                        switch ($item['source']) {
                            case 'INGAME': echo '🎮 In-Game'; break;
                            case 'PLEDGE': echo '💰 Pledge'; break;
                            case 'BOTH': echo '🎮💰 Les deux'; break;
                        }
                        ?>
                    </span>
                    
                    <?php if ($item['listing_count'] > 0): ?>
                    <span class="listings-count">
                        <?= $item['listing_count'] ?> annonce<?= $item['listing_count'] > 1 ? 's' : '' ?>
                    </span>
                    <?php else: ?>
                    <span class="no-listings">Aucune annonce</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="item-actions">
                <a href="item.php?id=<?= $item['id'] ?>" class="btn btn-primary btn-sm">
                    Voir les détails
                </a>
                <?php if ($current_user): ?>
                <button class="btn btn-outline btn-sm" onclick="addToWatchlist(<?= $item['id'] ?>)">
                    ⭐ Suivre
                </button>
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
           class="btn btn-outline btn-sm">← Précédent</a>
        <?php endif; ?>
        
        <div class="pagination-info">
            Page <?= $pagination['current_page'] ?> sur <?= $pagination['total_pages'] ?>
        </div>
        
        <?php if ($pagination['has_next']): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>" 
           class="btn btn-outline btn-sm">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="no-results">
        <div class="no-results-content">
            <h3>Aucun item trouvé</h3>
            <p>Essayez de modifier vos critères de recherche ou parcourez toutes les catégories.</p>
            <a href="items.php" class="btn btn-primary">Voir tout le catalogue</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.page-header {
    text-align: center;
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: var(--text-secondary);
    font-size: 1.125rem;
}

.search-filters {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.search-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.search-input {
    flex: 1;
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
    color: var(--text-secondary);
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.results-info {
    color: var(--text-secondary);
}

.items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.item-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
}

.item-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.item-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-badges {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.badge-category {
    font-size: 1.5rem;
    background-color: rgba(0, 0, 0, 0.7);
    padding: 0.25rem;
    border-radius: var(--radius);
}

.badge-variants {
    background-color: var(--primary);
    color: white;
    font-size: 0.75rem;
}

.item-content {
    flex: 1;
    padding: 1.5rem;
}

.item-name {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.item-manufacturer {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.item-description {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.item-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
}

.source-badge {
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-weight: 500;
}

.source-ingame {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.source-pledge {
    background-color: rgba(14, 165, 233, 0.1);
    color: var(--primary);
}

.source-both {
    background-color: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.listings-count {
    color: var(--success);
    font-weight: 500;
}

.no-listings {
    color: var(--text-muted);
}

.item-actions {
    padding: 1rem 1.5rem;
    background-color: var(--bg-tertiary);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 0.5rem;
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
}

.pagination-info {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.no-results {
    text-align: center;
    padding: 4rem 0;
}

.no-results-content h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.no-results-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .search-bar {
        flex-direction: column;
    }
    
    .filters-row {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .items-grid {
        grid-template-columns: 1fr;
    }
    
    .results-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .pagination {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}
</style>

<script>
function addToWatchlist(itemId) {
    fetch('api/watchlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            item_id: itemId,
            action: 'add'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Item ajouté à votre watchlist !', 'success');
        } else {
            showNotification(data.message || 'Erreur lors de l\'ajout', 'error');
        }
    })
    .catch(error => {
        showNotification('Erreur de connexion', 'error');
    });
}
</script>

<?php require_once 'footer.php'; ?>