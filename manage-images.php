<?php
$page_title = 'Gestion des images d\'items';
require_once 'header.php';

// Vérifier l'authentification et les permissions
requireLogin();
requireRole($pdo, 'MODERATOR');

// Récupérer les statistiques
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_items,
        COUNT(CASE WHEN image_url IS NULL OR image_url = '' THEN 1 END) as missing_images,
        COUNT(CASE WHEN image_status = 'BROKEN' THEN 1 END) as broken_images,
        COUNT(CASE WHEN image_status = 'OK' AND image_url IS NOT NULL AND image_url != '' THEN 1 END) as ok_images
    FROM items
");
$stmt->execute();
$stats = $stmt->fetch();
?>

<div class="container">
    <div class="admin-header">
        <div class="breadcrumb">
            <a href="<?= $current_user['role'] === 'ADMIN' ? 'admin.php' : 'moderation.php' ?>">
                ← Retour à l'administration
            </a>
        </div>
        
        <h1>🖼️ Gestion des images d'items</h1>
        <p>Gérez les images des items du catalogue StarMarket</p>
    </div>

    <!-- Statistiques -->
    <div class="image-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_items'] ?></div>
                    <div class="stat-label">Items total</div>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['ok_images'] ?></div>
                    <div class="stat-label">Images OK</div>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['missing_images'] ?></div>
                    <div class="stat-label">Images manquantes</div>
                </div>
            </div>
            
            <div class="stat-card error">
                <div class="stat-icon">💥</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['broken_images'] ?></div>
                    <div class="stat-label">Images cassées</div>
                </div>
            </div>
        </div>
        
        <div class="stats-actions">
            <button id="set-default-images-btn" class="btn btn-primary">
                🎨 Définir images par défaut
            </button>
            <button id="refresh-stats-btn" class="btn btn-outline">
                🔄 Actualiser
            </button>
        </div>
    </div>

    <!-- Onglets de gestion -->
    <div class="management-tabs">
        <button class="tab-button active" data-tab="missing">
            Images manquantes (<?= $stats['missing_images'] ?>)
        </button>
        <button class="tab-button" data-tab="broken">
            Images cassées (<?= $stats['broken_images'] ?>)
        </button>
        <button class="tab-button" data-tab="history">
            Historique des modifications
        </button>
    </div>

    <!-- Onglet Images manquantes -->
    <div id="missing-tab" class="tab-content active">
        <div class="tab-header">
            <h2>📤 Items sans images</h2>
            <p>Items prioritaires basés sur le nombre d'annonces actives</p>
        </div>
        
        <div id="missing-items-list" class="items-list">
            <div class="loading-placeholder">
                <div class="spinner"></div>
                <p>Chargement des items...</p>
            </div>
        </div>
    </div>

    <!-- Onglet Images cassées -->
    <div id="broken-tab" class="tab-content">
        <div class="tab-header">
            <h2>💥 Images cassées</h2>
            <p>Items avec des images qui ne se chargent plus</p>
        </div>
        
        <div id="broken-items-list" class="items-list">
            <div class="loading-placeholder">
                <div class="spinner"></div>
                <p>Chargement des items...</p>
            </div>
        </div>
    </div>

    <!-- Onglet Historique -->
    <div id="history-tab" class="tab-content">
        <div class="tab-header">
            <h2>📋 Historique des modifications</h2>
            <p>Dernières modifications d'images d'items</p>
        </div>
        
        <div id="history-list" class="history-list">
            <div class="loading-placeholder">
                <div class="spinner"></div>
                <p>Chargement de l'historique...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'édition d'image -->
<div id="edit-image-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🖼️ Modifier l'image d'item</h3>
            <button type="button" class="modal-close" onclick="closeImageModal()">&times;</button>
        </div>
        
        <form id="edit-image-form" enctype="multipart/form-data">
            <input type="hidden" id="edit-item-id" name="item_id">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="update_item_image">
            
            <div class="modal-body">
                <div class="item-info">
                    <h4 id="edit-item-name">Nom de l'item</h4>
                    <div class="current-image">
                        <img id="edit-current-image" src="" alt="Image actuelle" style="max-width: 200px;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nouvelle image</label>
                    <div class="image-options">
                        <div class="option-group">
                            <input type="radio" id="upload-file" name="image_type" value="upload" checked>
                            <label for="upload-file">📁 Upload de fichier</label>
                            
                            <div class="option-content">
                                <input type="file" 
                                       id="image-file" 
                                       name="image" 
                                       accept="image/*"
                                       class="form-input">
                                <div class="form-help">
                                    Formats acceptés: JPG, PNG, WEBP (max 5MB)
                                </div>
                            </div>
                        </div>
                        
                        <div class="option-group">
                            <input type="radio" id="image-url-option" name="image_type" value="url">
                            <label for="image-url-option">🔗 URL d'image</label>
                            
                            <div class="option-content">
                                <input type="url" 
                                       id="image-url" 
                                       name="image_url" 
                                       placeholder="https://exemple.com/image.jpg"
                                       class="form-input">
                                <div class="form-help">
                                    URL directe vers une image
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="image-preview">
                    <img id="new-image-preview" src="" alt="Prévisualisation" style="display: none; max-width: 200px;">
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">
                    💾 Sauvegarder
                </button>
                <button type="button" class="btn btn-outline" onclick="closeImageModal()">
                    Annuler
                </button>
                <button type="button" id="delete-image-btn" class="btn btn-error">
                    🗑️ Supprimer l'image
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.admin-header {
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

.image-stats {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background-color: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card.success {
    border-left: 4px solid var(--success);
}

.stat-card.warning {
    border-left: 4px solid var(--warning);
}

.stat-card.error {
    border-left: 4px solid var(--error);
}

.stat-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-primary);
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.stats-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.management-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border);
}

.tab-button {
    padding: 1rem 1.5rem;
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1rem;
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.tab-button:hover {
    color: var(--text-primary);
    background-color: var(--bg-tertiary);
}

.tab-button.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background-color: var(--bg-tertiary);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-header {
    text-align: center;
    margin-bottom: 2rem;
}

.tab-header h2 {
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.tab-header p {
    color: var(--text-secondary);
    margin: 0;
}

.items-list {
    display: grid;
    gap: 1rem;
}

.item-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 1.5rem;
    align-items: center;
}

.item-image {
    width: 80px;
    height: 60px;
    background-color: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 0.75rem;
    text-align: center;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: var(--radius);
}

.item-details h4 {
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.item-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.item-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.item-actions {
    display: flex;
    gap: 0.5rem;
}

.loading-placeholder {
    text-align: center;
    padding: 3rem;
    color: var(--text-muted);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--border);
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.history-list {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.history-item {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

.history-item:last-child {
    border-bottom: none;
}

.history-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.history-content {
    flex: 1;
}

.history-action {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.history-details {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.history-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: right;
}

/* Modal styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted);
    cursor: pointer;
}

.modal-body {
    padding: 1.5rem;
}

.item-info {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.item-info h4 {
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.current-image {
    margin: 0 auto;
}

.image-options {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.option-group {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
}

.option-group input[type="radio"] {
    margin-right: 0.5rem;
}

.option-group label {
    font-weight: 500;
    color: var(--text-primary);
    cursor: pointer;
}

.option-content {
    margin-top: 1rem;
    margin-left: 1.5rem;
}

.image-preview {
    text-align: center;
    margin-top: 1rem;
}

.modal-actions {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .item-card {
        grid-template-columns: 60px 1fr;
        gap: 1rem;
    }
    
    .item-actions {
        grid-column: 1 / -1;
        margin-top: 1rem;
        justify-content: center;
    }
    
    .management-tabs {
        flex-direction: column;
    }
    
    .modal-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentTab = 'missing';
    
    // Gestion des onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            // Mise à jour des boutons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Mise à jour du contenu
            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById(tabId + '-tab').classList.add('active');
            
            currentTab = tabId;
            loadTabContent(tabId);
        });
    });
    
    // Charger le contenu initial
    loadTabContent('missing');
    
    // Fonction pour charger le contenu d'un onglet
    async function loadTabContent(tab) {
        const container = document.getElementById(tab + '-items-list') || document.getElementById(tab + '-list');
        
        if (!container) return;
        
        container.innerHTML = `
            <div class="loading-placeholder">
                <div class="spinner"></div>
                <p>Chargement...</p>
            </div>
        `;
        
        try {
            let endpoint = '';
            switch(tab) {
                case 'missing':
                    endpoint = 'api/item-images.php?action=get_missing_images';
                    break;
                case 'broken':
                    endpoint = 'api/item-images.php?action=get_broken_images';
                    break;
                case 'history':
                    endpoint = 'api/item-images.php?action=get_image_history';
                    break;
            }
            
            const response = await fetch(endpoint);
            const data = await response.json();
            
            if (data.success) {
                if (tab === 'history') {
                    renderHistory(data.history);
                } else {
                    renderItems(data.items, tab);
                }
            } else {
                container.innerHTML = `<div class="alert alert-error">Erreur: ${data.message}</div>`;
            }
        } catch (error) {
            console.error('Erreur lors du chargement:', error);
            container.innerHTML = `<div class="alert alert-error">Erreur de connexion</div>`;
        }
    }
    
    // Rendu des items
    function renderItems(items, type) {
        const container = document.getElementById(type + '-items-list');
        
        if (items.length === 0) {
            container.innerHTML = `
                <div class="empty-content">
                    <p>Aucun item ${type === 'missing' ? 'sans image' : 'avec image cassée'} trouvé.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = items.map(item => `
            <div class="item-card">
                <div class="item-image">
                    ${item.image_url ? 
                        `<img src="${item.image_url}" alt="${item.name}" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                         <div style="display: none;">❌</div>` : 
                        '📷'
                    }
                </div>
                
                <div class="item-details">
                    <h4>${item.name}</h4>
                    <div class="item-meta">
                        <span>📦 ${item.category}</span>
                        ${item.manufacturer ? `<span>🏭 ${item.manufacturer}</span>` : ''}
                    </div>
                    <div class="item-stats">
                        <span>📊 ${item.active_listings} annonces actives</span>
                        <span>📈 ${item.listings_count} annonces total</span>
                    </div>
                </div>
                
                <div class="item-actions">
                    <button class="btn btn-primary btn-sm" onclick="editItemImage(${item.id}, '${item.name}', '${item.image_url || ''}')">
                        🖼️ Modifier
                    </button>
                    ${type === 'missing' ? '' : `
                        <button class="btn btn-error btn-sm" onclick="markAsBroken(${item.id})">
                            💥 Marquer cassée
                        </button>
                    `}
                </div>
            </div>
        `).join('');
    }
    
    // Rendu de l'historique
    function renderHistory(history) {
        const container = document.getElementById('history-list');
        
        if (history.length === 0) {
            container.innerHTML = `
                <div class="empty-content">
                    <p>Aucun historique trouvé.</p>
                </div>
            `;
            return;
        }
        
        const icons = {
            'UPLOAD': '📤',
            'UPDATE': '🔄',
            'DELETE': '🗑️'
        };
        
        container.innerHTML = history.map(item => `
            <div class="history-item">
                <div class="history-icon">${icons[item.action] || '📝'}</div>
                <div class="history-content">
                    <div class="history-action">
                        ${item.action === 'UPLOAD' ? 'Upload' : 
                          item.action === 'UPDATE' ? 'Mise à jour' : 'Suppression'} 
                        d'image ${item.item_name ? `pour "${item.item_name}"` : ''}
                    </div>
                    <div class="history-details">
                        Par ${item.username}
                        ${item.new_image_url ? ` - Nouvelle image: ${item.new_image_url.substring(0, 50)}...` : ''}
                    </div>
                </div>
                <div class="history-meta">
                    ${new Date(item.created_at).toLocaleString('fr-FR')}
                </div>
            </div>
        `).join('');
    }
    
    // Définir les images par défaut
    document.getElementById('set-default-images-btn').addEventListener('click', async function() {
        if (!confirm('Définir des images par défaut pour tous les items sans image ?')) {
            return;
        }
        
        this.disabled = true;
        this.textContent = 'Configuration...';
        
        try {
            const response = await fetch('api/item-images.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=set_default_images&csrf_token=${encodeURIComponent('<?= generateCSRFToken() ?>')}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        } catch (error) {
            alert('Erreur de connexion');
        }
        
        this.disabled = false;
        this.textContent = '🎨 Définir images par défaut';
    });
    
    // Actualiser les statistiques
    document.getElementById('refresh-stats-btn').addEventListener('click', function() {
        location.reload();
    });
});

// Fonction globale pour éditer une image d'item
function editItemImage(itemId, itemName, currentImageUrl) {
    document.getElementById('edit-item-id').value = itemId;
    document.getElementById('edit-item-name').textContent = itemName;
    
    const currentImg = document.getElementById('edit-current-image');
    if (currentImageUrl) {
        currentImg.src = currentImageUrl;
        currentImg.style.display = 'block';
    } else {
        currentImg.style.display = 'none';
    }
    
    // Réinitialiser le formulaire
    document.getElementById('edit-image-form').reset();
    document.getElementById('edit-item-id').value = itemId;
    document.getElementById('new-image-preview').style.display = 'none';
    
    document.getElementById('edit-image-modal').style.display = 'flex';
}

function closeImageModal() {
    document.getElementById('edit-image-modal').style.display = 'none';
}

// Gestion du formulaire d'édition d'image
document.getElementById('edit-image-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sauvegarde...';
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('api/item-images.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            closeImageModal();
            location.reload();
        } else {
            alert('Erreur: ' + data.message);
        }
    } catch (error) {
        alert('Erreur de connexion');
    }
    
    submitBtn.disabled = false;
    submitBtn.textContent = '💾 Sauvegarder';
});

// Prévisualisation d'image
document.getElementById('image-file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('new-image-preview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
        
        // Sélectionner l'option upload
        document.getElementById('upload-file').checked = true;
    }
});

document.getElementById('image-url').addEventListener('input', function() {
    const preview = document.getElementById('new-image-preview');
    
    if (this.value) {
        preview.src = this.value;
        preview.style.display = 'block';
        
        // Sélectionner l'option URL
        document.getElementById('image-url-option').checked = true;
    } else {
        preview.style.display = 'none';
    }
});
</script>

<?php require_once 'footer.php'; ?>