<?php
$page_title = 'Créer une annonce';
require_once 'header.php';

// Vérifier l'authentification
requireLogin();

$errors = [];
$success = false;

// Récupérer les items pour le select
$items_stmt = $pdo->query("SELECT * FROM items WHERE is_active = TRUE ORDER BY category, name");
$items = $items_stmt->fetchAll();

// Grouper par catégorie
$items_by_category = [];
foreach ($items as $item) {
    $items_by_category[$item['category']][] = $item;
}

// Pré-sélection d'item si fourni dans l'URL
$preselected_item_id = (int)($_GET['item_id'] ?? 0);
$preselected_type = $_GET['type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $variant_id = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
    $sale_type = $_POST['sale_type'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validation CSRF
    if (!validateCSRFToken($csrf_token)) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        // Validation de base
        if ($item_id <= 0) {
            $errors[] = 'Veuillez sélectionner un item.';
        }
        
        if (!in_array($sale_type, ['REAL_MONEY', 'IN_GAME'])) {
            $errors[] = 'Type de vente invalide.';
        }
        
        // Vérifier que l'item existe
        if ($item_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? AND is_active = TRUE");
            $stmt->execute([$item_id]);
            $selected_item = $stmt->fetch();
            
            if (!$selected_item) {
                $errors[] = 'Item sélectionné invalide.';
            }
        }
        
        // Vérifier la variante si fournie
        if ($variant_id) {
            $stmt = $pdo->prepare("SELECT * FROM item_variants WHERE id = ? AND item_id = ? AND is_active = TRUE");
            $stmt->execute([$variant_id, $item_id]);
            if (!$stmt->fetch()) {
                $errors[] = 'Variante sélectionnée invalide.';
            }
        }
        
        // Validation spécifique au type de vente
        if ($sale_type === 'REAL_MONEY') {
            $price_real = (float)($_POST['price_real'] ?? 0);
            $currency = $_POST['currency'] ?? 'EUR';
            $region = trim($_POST['region'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if ($price_real <= 0) {
                $errors[] = 'Le prix doit être supérieur à 0.';
            }
            
            if ($price_real > 10000) {
                $errors[] = 'Le prix ne peut pas dépasser 10 000 €.';
            }
            
            if (!in_array($currency, ['EUR', 'USD', 'GBP'])) {
                $errors[] = 'Devise non supportée.';
            }
            
            if (empty($region)) {
                $errors[] = 'La région est requise pour les ventes en argent réel.';
            }
            
        } elseif ($sale_type === 'IN_GAME') {
            $price_auec = (int)($_POST['price_auec'] ?? 0);
            $meet_location = trim($_POST['meet_location'] ?? '');
            $availability = trim($_POST['availability'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if ($price_auec <= 0) {
                $errors[] = 'Le prix aUEC doit être supérieur à 0.';
            }
            
            if ($price_auec > 100000000) {
                $errors[] = 'Le prix aUEC ne peut pas dépasser 100 millions.';
            }
            
            if (empty($meet_location)) {
                $errors[] = 'Le lieu de rendez-vous est requis pour les ventes in-game.';
            }
            
            if (empty($availability)) {
                $errors[] = 'Les créneaux de disponibilité sont requis.';
            }
        }
        
        // Créer l'annonce si pas d'erreurs
        if (empty($errors)) {
            try {
                $sql = "
                    INSERT INTO listings (
                        seller_id, item_id, variant_id, sale_type,
                        price_real, currency, price_auec,
                        region, meet_location, availability, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $params = [
                    $current_user['id'],
                    $item_id,
                    $variant_id,
                    $sale_type,
                    $sale_type === 'REAL_MONEY' ? $price_real : null,
                    $sale_type === 'REAL_MONEY' ? $currency : null,
                    $sale_type === 'IN_GAME' ? $price_auec : null,
                    $sale_type === 'REAL_MONEY' ? $region : null,
                    $sale_type === 'IN_GAME' ? $meet_location : null,
                    $sale_type === 'IN_GAME' ? $availability : null,
                    $notes
                ];
                
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    $listing_id = $pdo->lastInsertId();
                    header("Location: my-listings.php?success=created&id={$listing_id}");
                    exit;
                } else {
                    $errors[] = 'Erreur lors de la création de l\'annonce.';
                }
                
            } catch (PDOException $e) {
                $errors[] = 'Erreur de base de données : ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="container">
    <div class="page-header">
        <h1>📝 Créer une Annonce</h1>
        <p>Vendez vos items Star Citizen à la communauté</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $error): ?>
            <li><?= sanitizeOutput($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="sell-form-container">
        <form method="POST" class="sell-form">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <!-- Étape 1: Sélection de l'item -->
            <div class="form-section">
                <h2>1️⃣ Sélectionner l'Item</h2>
                
                <div class="form-group">
                    <label for="item_id" class="form-label">Item à vendre *</label>
                    <select id="item_id" name="item_id" class="form-select" required onchange="loadVariants(this.value)">
                        <option value="">-- Choisir un item --</option>
                        <?php foreach ($items_by_category as $category => $category_items): ?>
                        <optgroup label="<?php
                            switch ($category) {
                                case 'SHIP': echo '🚀 Vaisseaux'; break;
                                case 'ARMOR': echo '🛡️ Armures'; break;
                                case 'WEAPON': echo '⚔️ Armes'; break;
                                case 'COMPONENT': echo '🔧 Composants'; break;
                                case 'PAINT': echo '🎨 Peintures'; break;
                                case 'OTHER': echo '📦 Autres'; break;
                            }
                        ?>">
                            <?php foreach ($category_items as $item): ?>
                            <option value="<?= $item['id'] ?>" 
                                    data-image="<?= sanitizeOutput($item['image_url']) ?>"
                                    <?= $preselected_item_id === (int)$item['id'] ? 'selected' : '' ?>>
                                <?= sanitizeOutput($item['name']) ?>
                                <?php if ($item['manufacturer']): ?>
                                - <?= sanitizeOutput($item['manufacturer']) ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="variant_id" class="form-label">Variante / Couleur (optionnel)</label>
                    <select id="variant_id" name="variant_id" class="form-select">
                        <option value="">Version standard</option>
                    </select>
                    <div class="form-help">Sélectionnez une variante spécifique si applicable (couleur, skin, etc.)</div>
                </div>
                
                <div id="item_preview" class="item-preview" style="display: none;">
                    <img id="preview_image" src="" alt="Aperçu de l'item">
                    <div class="preview-info">
                        <h4 id="preview_name"></h4>
                        <p id="preview_description"></p>
                    </div>
                </div>
            </div>

            <!-- Étape 2: Type de vente -->
            <div class="form-section">
                <h2>2️⃣ Type de Vente</h2>
                
                <div class="sale-type-selector">
                    <div class="radio-group">
                        <label class="radio-card">
                            <input type="radio" name="sale_type" value="REAL_MONEY" 
                                   <?= $preselected_type === 'REAL_MONEY' ? 'checked' : '' ?> required>
                            <div class="radio-content">
                                <span class="radio-icon">💰</span>
                                <h3>Argent Réel</h3>
                                <p>Vente via le système de "gifting" RSI</p>
                                <small>⚠️ Transactions à vos risques et périls</small>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sale_type" value="IN_GAME" 
                                   <?= $preselected_type === 'IN_GAME' ? 'checked' : '' ?> required>
                            <div class="radio-content">
                                <span class="radio-icon">🎮</span>
                                <h3>In-Game (aUEC)</h3>
                                <p>Échange avec la monnaie du jeu</p>
                                <small>💫 Rendez-vous in-game requis</small>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Étape 3: Détails de la vente -->
            <div class="form-section">
                <h2>3️⃣ Détails de la Vente</h2>
                
                <!-- Champs pour argent réel -->
                <div id="real-money-fields" class="conditional-fields" style="display: none;">
                    <div class="fields-grid">
                        <div class="form-group">
                            <label for="price_real" class="form-label">Prix *</label>
                            <input type="number" 
                                   id="price_real" 
                                   name="price_real" 
                                   class="form-input" 
                                   step="0.01" 
                                   min="0.01" 
                                   max="10000"
                                   placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label for="currency" class="form-label">Devise *</label>
                            <select id="currency" name="currency" class="form-select">
                                <option value="EUR">EUR (€)</option>
                                <option value="USD">USD ($)</option>
                                <option value="GBP">GBP (£)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="region" class="form-label">Région *</label>
                            <select id="region" name="region" class="form-select">
                                <option value="">-- Choisir une région --</option>
                                <option value="EU">Europe (EU)</option>
                                <option value="NA">Amérique du Nord (NA)</option>
                                <option value="ASIA">Asie</option>
                                <option value="OCE">Océanie</option>
                                <option value="SA">Amérique du Sud</option>
                                <option value="GLOBAL">Global</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes_real" class="form-label">Instructions pour l'acheteur</label>
                        <textarea id="notes_real" 
                                  name="notes" 
                                  class="form-textarea" 
                                  placeholder="Instructions pour le paiement, méthode de livraison, conditions spéciales..."></textarea>
                        <div class="form-help">
                            Précisez vos conditions de paiement, méthode de livraison, etc.
                        </div>
                    </div>
                </div>
                
                <!-- Champs pour in-game -->
                <div id="in-game-fields" class="conditional-fields" style="display: none;">
                    <div class="fields-grid">
                        <div class="form-group">
                            <label for="price_auec" class="form-label">Prix aUEC *</label>
                            <input type="number" 
                                   id="price_auec" 
                                   name="price_auec" 
                                   class="form-input" 
                                   min="1" 
                                   max="100000000"
                                   placeholder="0">
                            <div class="form-help">Prix en aUEC (monnaie in-game)</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="meet_location" class="form-label">Lieu de RDV *</label>
                            <select id="meet_location" name="meet_location" class="form-select">
                                <option value="">-- Choisir un lieu --</option>
                                <option value="Area18 - ArcCorp">Area18 - ArcCorp</option>
                                <option value="Lorville - Hurston">Lorville - Hurston</option>
                                <option value="New Babbage - microTech">New Babbage - microTech</option>
                                <option value="Orison - Crusader">Orison - Crusader</option>
                                <option value="Port Olisar - Crusader">Port Olisar - Crusader</option>
                                <option value="Everus Harbor - Hurston">Everus Harbor - Hurston</option>
                                <option value="Port Tressler - microTech">Port Tressler - microTech</option>
                                <option value="Baijini Point - ArcCorp">Baijini Point - ArcCorp</option>
                                <option value="Grim HEX - Yela">Grim HEX - Yela</option>
                                <option value="Autre lieu">Autre lieu (préciser en notes)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="availability" class="form-label">Disponibilité *</label>
                            <input type="text" 
                                   id="availability" 
                                   name="availability" 
                                   class="form-input" 
                                   placeholder="Ex: Tous les soirs 20h-23h CET">
                            <div class="form-help">Indiquez vos créneaux de disponibilité pour les RDV</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes_ingame" class="form-label">Notes additionnelles</label>
                        <textarea id="notes_ingame" 
                                  name="notes" 
                                  class="form-textarea" 
                                  placeholder="Conditions spéciales, informations sur l'item, etc."></textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    🚀 Publier l'Annonce
                </button>
                <a href="my-listings.php" class="btn btn-outline">
                    ❌ Annuler
                </a>
            </div>
        </form>
    </div>

    <!-- Conseils -->
    <div class="selling-tips">
        <div class="card">
            <div class="card-header">
                <h3>💡 Conseils pour bien vendre</h3>
            </div>
            <div class="card-body">
                <div class="tips-grid">
                    <div class="tip-item">
                        <span class="tip-icon">💰</span>
                        <div class="tip-content">
                            <h4>Prix Compétitif</h4>
                            <p>Vérifiez les prix du marché dans la section "Bonnes Affaires" pour positionner votre annonce.</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">📸</span>
                        <div class="tip-content">
                            <h4>Description Détaillée</h4>
                            <p>Ajoutez des notes précises sur l'état de l'item et vos conditions de vente.</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">⭐</span>
                        <div class="tip-content">
                            <h4>Réputation</h4>
                            <p>Construisez votre réputation en étant ponctuel et honnête dans vos transactions.</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🛡️</span>
                        <div class="tip-content">
                            <h4>Sécurité</h4>
                            <p>Pour les ventes en argent réel, utilisez des méthodes de paiement sécurisées.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sell-form-container {
    max-width: 800px;
    margin: 0 auto;
}

.sell-form {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
}

.form-section {
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 2rem;
}

.form-section h2 {
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    font-size: 1.25rem;
}

.item-preview {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    padding: 1rem;
    background-color: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: var(--radius);
}

.item-preview img {
    width: 120px;
    height: 80px;
    object-fit: cover;
    border-radius: var(--radius);
}

.preview-info {
    flex: 1;
}

.preview-info h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.preview-info p {
    color: var(--text-secondary);
    font-size: 0.875rem;
    margin: 0;
}

.sale-type-selector {
    margin-bottom: 2rem;
}

.radio-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.radio-card {
    cursor: pointer;
    display: block;
}

.radio-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.radio-content {
    background-color: var(--bg-tertiary);
    border: 2px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-align: center;
    transition: all 0.2s;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.radio-card:hover .radio-content {
    border-color: var(--border-light);
}

.radio-card input[type="radio"]:checked + .radio-content {
    border-color: var(--primary);
    background-color: rgba(14, 165, 233, 0.1);
}

.radio-icon {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
}

.radio-content h3 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-size: 1.125rem;
}

.radio-content p {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.radio-content small {
    color: var(--text-muted);
    font-size: 0.75rem;
}

.conditional-fields {
    margin-top: 1rem;
}

.fields-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.selling-tips {
    margin-top: 3rem;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.tip-item {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.tip-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
    margin-top: 0.25rem;
}

.tip-content h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-size: 1rem;
}

.tip-content p {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
    margin: 0;
}

@media (max-width: 768px) {
    .radio-group {
        grid-template-columns: 1fr;
    }
    
    .fields-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .tips-grid {
        grid-template-columns: 1fr;
    }
    
    .tip-item {
        text-align: center;
        flex-direction: column;
    }
}
</style>

<script>
// Données des items pour le JavaScript
const itemsData = <?= json_encode($items) ?>;
const itemsVariants = {};

// Charger les variantes quand un item est sélectionné
function loadVariants(itemId) {
    const variantSelect = document.getElementById('variant_id');
    const itemPreview = document.getElementById('item_preview');
    const previewImage = document.getElementById('preview_image');
    const previewName = document.getElementById('preview_name');
    const previewDescription = document.getElementById('preview_description');
    
    // Vider les variantes
    variantSelect.innerHTML = '<option value="">Version standard</option>';
    
    if (!itemId) {
        itemPreview.style.display = 'none';
        return;
    }
    
    // Trouver l'item sélectionné
    const selectedItem = itemsData.find(item => item.id == itemId);
    if (!selectedItem) return;
    
    // Afficher l'aperçu
    previewImage.src = selectedItem.image_url || 'assets/img/placeholder.jpg';
    previewName.textContent = selectedItem.name;
    previewDescription.textContent = selectedItem.description || '';
    itemPreview.style.display = 'flex';
    
    // Charger les variantes via AJAX
    fetch(`api/item-variants.php?item_id=${itemId}`)
        .then(response => response.json())
        .then(variants => {
            variants.forEach(variant => {
                const option = document.createElement('option');
                option.value = variant.id;
                option.textContent = variant.variant_name + 
                    (variant.color_name ? ` - ${variant.color_name}` : '');
                variantSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Erreur lors du chargement des variantes:', error));
}

// Initialiser si un item est pré-sélectionné
document.addEventListener('DOMContentLoaded', function() {
    const itemSelect = document.getElementById('item_id');
    if (itemSelect.value) {
        loadVariants(itemSelect.value);
    }
    
    // Vérifier le type de vente pré-sélectionné
    const checkedRadio = document.querySelector('input[name="sale_type"]:checked');
    if (checkedRadio) {
        checkedRadio.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once 'footer.php'; ?>