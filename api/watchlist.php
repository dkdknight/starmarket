<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$action = $input['action'];
$item_id = (int)($input['item_id'] ?? 0);
$variant_id = !empty($input['variant_id']) ? (int)$input['variant_id'] : null;

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Item ID invalide']);
    exit;
}

try {
    if ($action === 'add') {
        // Vérifier si déjà dans la watchlist
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM watchlist 
            WHERE user_id = ? AND item_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL))
        ");
        $stmt->execute([$user_id, $item_id, $variant_id, $variant_id]);
        
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Item déjà dans votre watchlist']);
            exit;
        }
        
        // Ajouter à la watchlist
        $stmt = $pdo->prepare("
            INSERT INTO watchlist (user_id, item_id, variant_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $item_id, $variant_id]);
        
        echo json_encode(['success' => true, 'message' => 'Item ajouté à votre watchlist']);
        
    } elseif ($action === 'remove') {
        $stmt = $pdo->prepare("
            DELETE FROM watchlist 
            WHERE user_id = ? AND item_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL))
        ");
        $stmt->execute([$user_id, $item_id, $variant_id, $variant_id]);
        
        echo json_encode(['success' => true, 'message' => 'Item retiré de votre watchlist']);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>