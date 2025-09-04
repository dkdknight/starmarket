<?php
// Script pour créer des images par défaut
require_once 'config.php';

// Fonction pour créer une image placeholder
function createPlaceholderImage($text, $filename, $width = 400, $height = 300) {
    $image = imagecreate($width, $height);
    
    // Couleurs
    $background = imagecolorallocate($image, 45, 55, 72); // Fond sombre
    $text_color = imagecolorallocate($image, 203, 213, 225); // Texte clair
    $border = imagecolorallocate($image, 100, 116, 139); // Bordure
    
    // Fond
    imagefill($image, 0, 0, $background);
    
    // Bordure
    imagerectangle($image, 0, 0, $width-1, $height-1, $border);
    
    // Texte centré
    $font_size = 5;
    $text_width = imagefontwidth($font_size) * strlen($text);
    $text_height = imagefontheight($font_size);
    
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2;
    
    imagestring($image, $font_size, $x, $y, $text, $text_color);
    
    // Sauvegarder
    $filepath = UPLOADS_DIR . '/' . $filename;
    imagepng($image, $filepath);
    imagedestroy($image);
    
    return $filepath;
}

// Créer les images par défaut
$default_images = [
    'default-ship.png' => 'SHIP',
    'default-armor.png' => 'ARMOR', 
    'default-weapon.png' => 'WEAPON',
    'default-paint.png' => 'PAINT',
    'default-component.png' => 'COMPONENT',
    'default-item.png' => 'OTHER'
];

foreach ($default_images as $filename => $category) {
    $filepath = createPlaceholderImage($category, $filename);
    echo "Créé: $filepath\n";
}

// Créer aussi les autres images nécessaires
$other_images = [
    'placeholder.jpg' => 'NO IMAGE',
    'default-avatar.png' => 'USER',
    'logo.png' => 'STARMARKET'
];

foreach ($other_images as $filename => $text) {
    $filepath = createPlaceholderImage($text, $filename, 200, 200);
    echo "Créé: $filepath\n";
}

echo "Toutes les images par défaut ont été créées !\n";
?>