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

if (!$input || !isset($input['listing_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$listing_id = (int)$input['listing_id'];
$new_status = $input['status'];

// Vérifier que le statut est valide
$valid_statuses = ['ACTIVE', 'PAUSED', 'SOLD', 'REMOVED'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Statut invalide']);
    exit;
}

try {
    // Vérifier que l'annonce appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT seller_id FROM listings WHERE id = ?");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch();
    
    if (!$listing || $listing['seller_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Annonce non trouvée ou accès refusé']);
        exit;
    }
    
    // Mettre à jour le statut
    $stmt = $pdo->prepare("UPDATE listings SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $listing_id]);
    
    echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>