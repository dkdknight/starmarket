<?php
// Configuration globale de l'application
session_start();

// Configuration du site
define('SITE_NAME', 'StarMarket');
define('SITE_URL', 'http://knight-hd.fr/starmarket/starmarket');
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('UPLOADS_URL', SITE_URL . '/uploads');

// Configuration des uploads
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Configuration de sécurité
define('PASSWORD_MIN_LENGTH', 8);
define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 50);

// Créer le dossier uploads s'il n'existe pas
if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}

// Fonction pour générer des noms de fichiers uniques
function generateUniqueFileName($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return uniqid() . '_' . time() . '.' . $extension;
}

// Fonction pour valider les uploads d'images
function validateImageUpload($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Erreur lors de l'upload du fichier.";
        return $errors;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "Le fichier est trop volumineux (max 5MB).";
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = "Extension de fichier non autorisée. Extensions autorisées : " . implode(', ', ALLOWED_EXTENSIONS);
    }
    
    // Vérifier que c'est vraiment une image
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        $errors[] = "Le fichier n'est pas une image valide.";
    }
    
    return $errors;
}

// Fonction pour traiter l'upload d'une image
function processImageUpload($file, $subfolder = '') {
    $errors = validateImageUpload($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    $uploadDir = UPLOADS_DIR;
    if ($subfolder) {
        $uploadDir .= '/' . $subfolder;
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
    }
    
    $fileName = generateUniqueFileName($file['name']);
    $filePath = $uploadDir . '/' . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $fileUrl = UPLOADS_URL . ($subfolder ? '/' . $subfolder : '') . '/' . $fileName;
        return ['success' => true, 'file_url' => $fileUrl, 'file_path' => $filePath];
    } else {
        return ['success' => false, 'errors' => ['Erreur lors de la sauvegarde du fichier.']];
    }
}

// Fonction pour formater les prix
function formatPrice($price, $currency = 'EUR') {
    if ($currency === 'aUEC') {
        return number_format($price, 0, ',', ' ') . ' aUEC';
    }
    return number_format($price, 2, ',', ' ') . ' ' . $currency;
}

// Fonction pour calculer les bonnes affaires
function calculateDealScore($listing, $reference) {
    if (!$reference) return null;
    
    if ($listing['sale_type'] === 'REAL_MONEY' && $reference['ref_price_real']) {
        return ($reference['ref_price_real'] - $listing['price_real']) / $reference['ref_price_real'];
    } elseif ($listing['sale_type'] === 'IN_GAME' && $reference['ref_price_auec']) {
        return ($reference['ref_price_auec'] - $listing['price_auec']) / $reference['ref_price_auec'];
    }
    
    return null;
}

// Fonction pour gérer la pagination
function getPaginationData($page, $totalItems, $itemsPerPage = 20) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($page, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}
?>