<?php
$page_title = 'Ma Watchlist';
require_once 'header.php';

requireLogin();

$stmt = $pdo->prepare("
    SELECT w.id AS watch_id, w.variant_id, i.id AS item_id, i.name, i.image_url,
           i.category, i.source,
           iv.variant_name, iv.color_name,
           COUNT(l.id) AS listing_count
    FROM watchlist w
    JOIN items i ON w.item_id = i.id
    LEFT JOIN item_variants iv ON w.variant_id = iv.id
    LEFT JOIN listings l ON l.item_id = i.id
        AND l.status = 'ACTIVE'
        AND (w.variant_id IS NULL OR l.variant_id = w.variant_id)
    WHERE w.user_id = ?
    GROUP BY w.id, w.variant_id, i.id, i.name, i.image_url, i.category, i.source, iv.variant_name, iv.color_name
    ORDER BY w.created_at DESC
");
$stmt->execute([$current_user['id']]);
$watch_items = $stmt->fetchAll();

$max_stmt = $pdo->prepare("SELECT MAX(l.id) FROM listings l JOIN watchlist w ON w.item_id = l.item_id AND (w.variant_id IS NULL OR w.variant_id = l.variant_id) WHERE w.user_id = ?");
$max_stmt->execute([$current_user['id']]);
$last_listing_id = (int)$max_stmt->fetchColumn();
?>

<div class="container">
    <div class="page-header">
        <h1>⭐ Ma Watchlist</h1>
    </div>

<?php if (!empty($watch_items)): ?>
    <div class="items-grid">
        <?php foreach ($watch_items as $item): ?>
        <div class="item-card">
            <div class="item-image">
                <img src="<?= sanitizeOutput($item['image_url'] ?: 'assets/img/placeholder.jpg') ?>" alt="<?= sanitizeOutput($item['name']) ?>">
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
                    <span class="source-badge source-<?= strtolower($item['source']) ?>">
                        <?php
                        switch ($item['source']) {
                            case 'INGAME': echo '🎮 In-Game'; break;
                            case 'PLEDGE': echo '💰 Pledge'; break;
                            case 'BOTH': echo '🎮💰 Les deux'; break;
                        }
                        ?>
                    </span>
                </div>
            </div>
            <div class="item-content">
                <h3 class="item-name"><a href="item.php?id=<?= $item['item_id'] ?>"><?= sanitizeOutput($item['name']) ?></a></h3>
                <?php if ($item['variant_name']): ?>
                <p class="item-variant">
                    <?= sanitizeOutput($item['variant_name']) ?>
                    <?php if ($item['color_name']): ?>- <?= sanitizeOutput($item['color_name']) ?><?php endif; ?>
                </p>
                <?php endif; ?>
                <div class="item-meta">
                    <?php if ($item['listing_count'] > 0): ?>
                        <span class="listings-count"><?= $item['listing_count'] ?> annonce<?= $item['listing_count'] > 1 ? 's' : '' ?></span>
                    <?php else: ?>
                        <span class="no-listings">Aucune annonce</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="item-actions">
                <a href="item.php?id=<?= $item['item_id'] ?>" class="btn btn-primary btn-sm">Voir</a>
                <button class="btn btn-outline btn-sm" onclick="removeFromWatchlist(<?= $item['item_id'] ?>, <?= $item['variant_id'] ?? 'null' ?>, this.closest('.item-card'))">Retirer</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>Vous ne suivez aucun item pour le moment.</p>
<?php endif; ?>
</div>

<style>
.page-header {
    text-align: center;
    margin-bottom: 2rem;
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

.source-badge {
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 0.75rem;
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

.item-content {
    flex: 1;
    padding: 1.5rem;
}

.item-name {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.item-variant {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.item-meta {
    font-size: 0.75rem;
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
</style>

<script>
let lastListingId = <?= $last_listing_id ?>;
function checkWatchlistUpdates(){
    fetch('api/watchlist-new-listings.php?last_id=' + lastListingId)
        .then(r => r.json())
        .then(data => {
            if (data.success && Array.isArray(data.listings)) {
                data.listings.forEach(l => {
                    lastListingId = Math.max(lastListingId, l.id);
                    if (typeof showNotification === 'function') {
                        showNotification('Nouvelle annonce pour ' + l.name + ' !', 'info');
                    }
                });
            }
        });
}

function removeFromWatchlist(itemId, variantId, card){
    fetch('api/watchlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            item_id: itemId,
            variant_id: variantId,
            action: 'remove'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            card.remove();
            if (!document.querySelector('.items-grid .item-card')) {
                const grid = document.querySelector('.items-grid');
                if (grid) grid.remove();
                const msg = document.createElement('p');
                msg.textContent = 'Vous ne suivez aucun item pour le moment.';
                document.querySelector('.container').appendChild(msg);
            }
            if (typeof showNotification === 'function') {
                showNotification('Item retiré de votre watchlist', 'success');
            }
        } else if (typeof showNotification === 'function') {
            showNotification(data.message || 'Erreur lors du retrait', 'error');
        }
    })
    .catch(() => {
        if (typeof showNotification === 'function') {
            showNotification('Erreur de connexion', 'error');
        }
    });
}

setInterval(checkWatchlistUpdates, 15000);
</script>

<?php require_once 'footer.php'; ?>