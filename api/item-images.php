<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

// Vérifier l'authentification et les permissions
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Seuls les modérateurs et admins peuvent gérer les images d'items
requireRole($pdo, 'MODERATOR');

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_missing_images':
            // Récupérer les items sans images
            $stmt = $pdo->prepare("
                SELECT i.id, i.name, i.category, i.manufacturer, i.image_url, i.image_status,
                       COUNT(l.id) as listings_count,
                       COUNT(CASE WHEN l.status = 'ACTIVE' THEN 1 END) as active_listings
                FROM items i
                LEFT JOIN listings l ON i.id = l.item_id
                WHERE i.image_url IS NULL OR i.image_url = '' OR i.image_status = 'MISSING'
                GROUP BY i.id
                ORDER BY active_listings DESC, listings_count DESC, i.name ASC
            ");
            $stmt->execute();
            $missing_items = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'items' => $missing_items,
                'count' => count($missing_items)
            ]);
            break;
            
        case 'get_broken_images':
            // Récupérer les items avec des images cassées
            $stmt = $pdo->prepare("
                SELECT i.id, i.name, i.category, i.manufacturer, i.image_url, i.image_status,
                       COUNT(l.id) as listings_count,
                       COUNT(CASE WHEN l.status = 'ACTIVE' THEN 1 END) as active_listings
                FROM items i
                LEFT JOIN listings l ON i.id = l.item_id
                WHERE i.image_status = 'BROKEN'
                GROUP BY i.id
                ORDER BY active_listings DESC, listings_count DESC, i.name ASC
            ");
            $stmt->execute();
            $broken_items = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'items' => $broken_items,
                'count' => count($broken_items)
            ]);
            break;
            
        case 'update_item_image':
            $item_id = (int)($_POST['item_id'] ?? 0);
            $csrf_token = $_POST['csrf_token'] ?? '';
            
            // Validation CSRF
            if (!validateCSRFToken($csrf_token)) {
                echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
                exit;
            }
            
            if (!$item_id) {
                echo json_encode(['success' => false, 'message' => 'ID item requis']);
                exit;
            }
            
            // Vérifier que l'item existe
            $stmt = $pdo->prepare("SELECT name, image_url FROM items WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                echo json_encode(['success' => false, 'message' => 'Item non trouvé']);
                exit;
            }
            
            $old_image_url = $item['image_url'];
            $new_image_url = null;
            $action_type = 'UPDATE';
            
            // Traitement de l'upload d'image
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = processImageUpload($_FILES['image'], 'items');
                
                if (!$upload_result['success']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erreur upload: ' . implode(', ', $upload_result['errors'])
                    ]);
                    exit;
                }
                
                $new_image_url = $upload_result['file_url'];
                $action_type = empty($old_image_url) ? 'UPLOAD' : 'UPDATE';
                
            } elseif (isset($_POST['image_url'])) {
                // URL d'image fournie
                $new_image_url = trim($_POST['image_url']);
                if (empty($new_image_url)) {
                    $new_image_url = null;
                    $action_type = 'DELETE';
                }
            }
            
            // Mettre à jour l'item
            $stmt = $pdo->prepare("
                UPDATE items 
                SET image_url = ?, image_status = 'OK', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_image_url, $item_id]);
            
            // Enregistrer dans l'historique
            $stmt = $pdo->prepare("
                INSERT INTO image_history (item_id, old_image_url, new_image_url, uploaded_by, action) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$item_id, $old_image_url, $new_image_url, $user_id, $action_type]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Image mise à jour avec succès',
                'item_name' => $item['name'],
                'new_image_url' => $new_image_url
            ]);
            break;
            
        case 'mark_as_broken':
            $item_id = (int)($_POST['item_id'] ?? 0);
            $csrf_token = $_POST['csrf_token'] ?? '';
            
            if (!validateCSRFToken($csrf_token) || !$item_id) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE items SET image_status = 'BROKEN' WHERE id = ?");
            $stmt->execute([$item_id]);
            
            echo json_encode(['success' => true, 'message' => 'Image marquée comme cassée']);
            break;
            
        case 'set_default_images':
            // Définir des images par défaut pour tous les items sans image
            $default_images = [
                'SHIP' => 'assets/img/default-ship.jpg',
                'ARMOR' => 'assets/img/default-armor.jpg',
                'WEAPON' => 'assets/img/default-weapon.jpg',
                'PAINT' => 'assets/img/default-paint.jpg',
                'COMPONENT' => 'assets/img/default-component.jpg',
                'OTHER' => 'assets/img/default-item.jpg'
            ];
            
            $updated_count = 0;
            
            foreach ($default_images as $category => $default_url) {
                $stmt = $pdo->prepare("
                    UPDATE items 
                    SET image_url = ?, image_status = 'OK' 
                    WHERE category = ? AND (image_url IS NULL OR image_url = '')
                ");
                $stmt->execute([$default_url, $category]);
                $updated_count += $stmt->rowCount();
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Images par défaut définies pour {$updated_count} items",
                'updated_count' => $updated_count
            ]);
            break;
            
        case 'get_image_history':
            $item_id = (int)($_GET['item_id'] ?? 0);
            
            if ($item_id) {
                $stmt = $pdo->prepare("
                    SELECT ih.*, u.username
                    FROM image_history ih
                    JOIN users u ON ih.uploaded_by = u.id
                    WHERE ih.item_id = ?
                    ORDER BY ih.created_at DESC
                ");
                $stmt->execute([$item_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT ih.*, u.username, i.name as item_name
                    FROM image_history ih
                    JOIN users u ON ih.uploaded_by = u.id
                    JOIN items i ON ih.item_id = i.id
                    ORDER BY ih.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute();
            }
            
            $history = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erreur Item Images API: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>