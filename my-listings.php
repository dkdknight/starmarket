<?php
$page_title = 'Mes Annonces';
require_once 'header.php';

// Vérifier l'authentification
requireLogin();

$status_filter = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

// Construction de la requête
$where_conditions = ['l.seller_id = ?'];
$params = [$current_user['id']];

if ($status_filter !== 'all') {
    $where_conditions[] = 'l.status = ?';
    $params[] = strtoupper($status_filter);
}

$where_clause = implode(' AND ', $where_conditions);

// Compter le total
$count_sql = "
    SELECT COUNT(*)
    FROM listings l
    JOIN items i ON l.item_id = i.id
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
           iv.variant_name, iv.color_name,
           COUNT(c.id) as conversation_count,
           COUNT(CASE WHEN m.is_read = FALSE AND m.sender_id != l.seller_id THEN 1 END) as unread_messages
    FROM listings l
    JOIN items i ON l.item_id = i.id
    LEFT JOIN item_variants iv ON l.variant_id = iv.id
    LEFT JOIN conversations c ON l.id = c.listing_id
    LEFT JOIN messages m ON c.id = m.conversation_id
    WHERE {$where_clause}
    GROUP BY l.id
    ORDER BY l.created_at DESC
    LIMIT {$per_page} OFFSET {$pagination['offset']}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// Statistiques
$stats_sql = "
    SELECT 
        COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_count,
        COUNT(CASE WHEN status = 'PAUSED' THEN 1 END) as paused_count,
        COUNT(CASE WHEN status = 'SOLD' THEN 1 END) as sold_count,
        COUNT(CASE WHEN status = 'REMOVED' THEN 1 END) as removed_count,
        COUNT(*) as total_count
    FROM listings
    WHERE seller_id = ?
";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$current_user['id']]);
$stats = $stmt->fetch();
?>

<div class="container">
    <div class="page-header">
        <h1>📋 Mes Annonces</h1>
        <p>Gérez toutes vos annonces en cours et terminées</p>
        <div class="header-actions">
            <a href="sell.php" class="btn btn-primary">
                ➕ Créer une nouvelle annonce
            </a>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="listings-stats">
        <div class="stats-grid">
            <div class="stat-card active">
                <div class="stat-icon">🟢</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['active_count'] ?></div>
                    <div class="stat-label">Actives</div>
                </div>
            </div>
            
            <div class="stat-card paused">
                <div class="stat-icon">⏸️</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['paused_count'] ?></div>
                    <div class="stat-label">En pause</div>
                </div>
            </div>
            
            <div class="stat-card sold">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['sold_count'] ?></div>
                    <div class="stat-label">Vendues</div>
                </div>
            </div>
            
            <div class="stat-card total">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_count'] ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="listings-filters">
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?= $status_filter === 'all' ? 'active' : '' ?>">
                Toutes les annonces (<?= $stats['total_count'] ?>)
            </a>
            <a href="?status=active" class="filter-tab <?= $status_filter === 'active' ? 'active' : '' ?>">
                Actives (<?= $stats['active_count'] ?>)
            </a>
            <a href="?status=paused" class="filter-tab <?= $status_filter === 'paused' ? 'active' : '' ?>">
                En pause (<?= $stats['paused_count'] ?>)
            </a>
            <a href="?status=sold" class="filter-tab <?= $status_filter === 'sold' ? 'active' : '' ?>">
                Vendues (<?= $stats['sold_count'] ?>)
            </a>
            <a href="?status=removed" class="filter-tab <?= $status_filter === 'removed' ? 'active' : '' ?>">
                Supprimées (<?= $stats['removed_count'] ?>)
            </a>
        </div>
    </div>

    <!-- Liste des annonces -->
    <?php if (!empty($listings)): ?>
    <div class="listings-grid">
        <?php foreach ($listings as $listing): ?>
        <div class="listing-card status-<?= strtolower($listing['status']) ?>">
            <div class="listing-image">
                <img src="<?= sanitizeOutput($listing['item_image'] ?: 'assets/img/placeholder.jpg') ?>" 
                     alt="<?= sanitizeOutput($listing['item_name']) ?>">
                
                <div class="listing-badges">
                    <span class="status-badge status-<?= strtolower($listing['status']) ?>">
                        <?php
                        switch ($listing['status']) {
                            case 'ACTIVE':
                                echo '🟢 Active';
                                break;
                            case 'PAUSED':
                                echo '⏸️ Pause';
                                break;
                            case 'SOLD':
                                echo '✅ Vendue';
                                break;
                            case 'REMOVED':
                                echo '🗑️ Supprimée';
                                break;
                        }
                        ?>
                    </span>
                    
                    <span class="sale-type-badge <?= $listing['sale_type'] === 'REAL_MONEY' ? 'real-money' : 'in-game' ?>">
                        <?= $listing['sale_type'] === 'REAL_MONEY' ? '💰' : '🎮' ?>
                    </span>
                </div>
                
                <?php if ($listing['unread_messages'] > 0): ?>
                <div class="unread-badge">
                    <?= $listing['unread_messages'] ?> nouveau<?= $listing['unread_messages'] > 1 ? 'x' : '' ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="listing-content">
                <h3 class="listing-title">
                    <a href="item.php?id=<?= $listing['item_id'] ?>">
                        <?= sanitizeOutput($listing['item_name']) ?>
                    </a>
                </h3>
                
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

                <div class="listing-meta">
                    <div class="listing-stats">
                        <span class="views">👁️ <?= $listing['views_count'] ?> vues</span>
                        <span class="conversations">💬 <?= $listing['conversation_count'] ?> contact<?= $listing['conversation_count'] > 1 ? 's' : '' ?></span>
                    </div>
                    
                    <div class="listing-date">
                        📅 <?= date('d/m/Y', strtotime($listing['created_at'])) ?>
                    </div>
                </div>
            </div>

            <div class="listing-actions">
                <?php if ($listing['status'] === 'ACTIVE'): ?>
                <button class="btn btn-warning btn-sm" onclick="pauseListing(<?= $listing['id'] ?>)">
                    ⏸️ Pause
                </button>
                <?php elseif ($listing['status'] === 'PAUSED'): ?>
                <button class="btn btn-success btn-sm" onclick="activateListing(<?= $listing['id'] ?>)">
                    ▶️ Réactiver
                </button>
                <?php endif; ?>
                
                <?php if (in_array($listing['status'], ['ACTIVE', 'PAUSED'])): ?>
                <a href="edit-listing.php?id=<?= $listing['id'] ?>" class="btn btn-outline btn-sm">
                    ✏️ Modifier
                </a>
                <?php endif; ?>
                
                <?php if ($listing['conversation_count'] > 0): ?>
                <a href="inbox.php?listing=<?= $listing['id'] ?>" class="btn btn-primary btn-sm">
                    💬 Messages
                    <?php if ($listing['unread_messages'] > 0): ?>
                    <span class="message-count"><?= $listing['unread_messages'] ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                
                <?php if ($listing['status'] === 'ACTIVE'): ?>
                <button class="btn btn-success btn-sm" onclick="markAsSold(<?= $listing['id'] ?>)">
                    ✅ Marquer vendue
                </button>
                <?php endif; ?>
                
                <div class="more-actions">
                    <button class="btn btn-outline btn-sm dropdown-toggle" onclick="toggleDropdown(<?= $listing['id'] ?>)">
                        ⋯ Plus
                    </button>
                    <div class="dropdown-menu" id="dropdown-<?= $listing['id'] ?>">
                        <a href="item.php?id=<?= $listing['item_id'] ?>" class="dropdown-item">
                            👁️ Voir la page publique
                        </a>
                        <a href="duplicate-listing.php?id=<?= $listing['id'] ?>" class="dropdown-item">
                            📋 Dupliquer
                        </a>
                        <?php if ($listing['status'] !== 'REMOVED'): ?>
                        <button class="dropdown-item danger" onclick="removeListing(<?= $listing['id'] ?>)">
                            🗑️ Supprimer
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
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
        </div>
        
        <?php if ($pagination['has_next']): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>" 
           class="btn btn-outline">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-listings">
        <div class="empty-content">
            <span class="empty-icon">📦</span>
            <h3>
                <?php if ($status_filter === 'all'): ?>
                Aucune annonce créée
                <?php else: ?>
                Aucune annonce <?= $status_filter === 'active' ? 'active' : $status_filter ?>
                <?php endif; ?>
            </h3>
            <p>
                <?php if ($status_filter === 'all'): ?>
                Commencez à vendre vos items Star Citizen en créant votre première annonce.
                <?php else: ?>
                Vous n'avez pas d'annonces avec ce statut actuellement.
                <?php endif; ?>
            </p>
            <div class="empty-actions">
                <?php if ($status_filter === 'all'): ?>
                <a href="sell.php" class="btn btn-primary">Créer ma première annonce</a>
                <?php else: ?>
                <a href="?status=all" class="btn btn-outline">Voir toutes mes annonces</a>
                <a href="sell.php" class="btn btn-primary">Créer une nouvelle annonce</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 2rem;
}

.header-actions {
    flex-shrink: 0;
}

.listings-stats {
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

.stat-card.active {
    border-color: var(--success);
    background-color: rgba(16, 185, 129, 0.05);
}

.stat-card.paused {
    border-color: var(--warning);
    background-color: rgba(245, 158, 11, 0.05);
}

.stat-card.sold {
    border-color: var(--primary);
    background-color: rgba(14, 165, 233, 0.05);
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

.listings-filters {
    margin-bottom: 2rem;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: center;
}

.filter-tab {
    padding: 0.75rem 1.5rem;
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s;
}

.filter-tab:hover {
    background-color: var(--bg-hover);
    color: var(--text-primary);
}

.filter-tab.active {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
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
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.listing-card.status-paused {
    opacity: 0.8;
    border-color: var(--warning);
}

.listing-card.status-sold {
    opacity: 0.9;
    border-color: var(--success);
}

.listing-card.status-removed {
    opacity: 0.6;
    border-color: var(--error);
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
    flex-direction: column;
    gap: 0.25rem;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.75rem;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

.status-badge.status-active {
    background-color: rgba(16, 185, 129, 0.9);
    color: white;
}

.status-badge.status-paused {
    background-color: rgba(245, 158, 11, 0.9);
    color: white;
}

.status-badge.status-sold {
    background-color: rgba(14, 165, 233, 0.9);
    color: white;
}

.status-badge.status-removed {
    background-color: rgba(239, 68, 68, 0.9);
    color: white;
}

.sale-type-badge {
    padding: 0.25rem;
    border-radius: 50%;
    font-size: 1rem;
    backdrop-filter: blur(10px);
}

.sale-type-badge.real-money {
    background-color: rgba(14, 165, 233, 0.9);
}

.sale-type-badge.in-game {
    background-color: rgba(16, 185, 129, 0.9);
}

.unread-badge {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    background-color: var(--error);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.75rem;
    font-weight: bold;
    animation: pulse 2s infinite;
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

.listing-price {
    margin-bottom: 1rem;
}

.price {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--primary);
}

.listing-meta {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.listing-stats {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.listing-actions {
    padding: 1rem 1.5rem;
    background-color: var(--bg-tertiary);
    border-top: 1px solid var(--border);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.message-count {
    background-color: var(--error);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    margin-left: 0.25rem;
}

.more-actions {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    bottom: 100%;
    right: 0;
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    min-width: 180px;
    display: none;
    z-index: 100;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    color: var(--text-secondary);
    text-decoration: none;
    background: none;
    border: none;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background-color: var(--bg-hover);
    color: var(--text-primary);
}

.dropdown-item.danger:hover {
    background-color: var(--error);
    color: white;
}

.empty-listings {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    display: block;
    margin-bottom: 1rem;
}

.empty-content h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.empty-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.empty-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-tabs {
        flex-direction: column;
        align-items: center;
    }
    
    .listings-grid {
        grid-template-columns: 1fr;
    }
    
    .listing-actions {
        flex-direction: column;
    }
    
    .empty-actions {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<script>
function pauseListing(listingId) {
    if (confirm('Voulez-vous mettre cette annonce en pause ?')) {
        updateListingStatus(listingId, 'PAUSED');
    }
}

function activateListing(listingId) {
    if (confirm('Voulez-vous réactiver cette annonce ?')) {
        updateListingStatus(listingId, 'ACTIVE');
    }
}

function markAsSold(listingId) {
    if (confirm('Voulez-vous marquer cette annonce comme vendue ?')) {
        updateListingStatus(listingId, 'SOLD');
    }
}

function removeListing(listingId) {
    if (confirm('Voulez-vous supprimer définitivement cette annonce ?')) {
        updateListingStatus(listingId, 'REMOVED');
    }
}

function updateListingStatus(listingId, status) {
    fetch('api/update-listing-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            listing_id: listingId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showNotification(data.message || 'Erreur lors de la mise à jour', 'error');
        }
    })
    .catch(error => {
        showNotification('Erreur de connexion', 'error');
    });
}

function toggleDropdown(listingId) {
    const dropdown = document.getElementById(`dropdown-${listingId}`);
    
    // Fermer tous les autres dropdowns
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if (menu.id !== `dropdown-${listingId}`) {
            menu.classList.remove('show');
        }
    });
    
    dropdown.classList.toggle('show');
}

// Fermer les dropdowns en cliquant ailleurs
document.addEventListener('click', function(e) {
    if (!e.target.closest('.more-actions')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>