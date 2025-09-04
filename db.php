<?php
// Configuration de la base de données
$host = 'localhost';
$dbname = 'starmarket';
$username = 'root';
$password = ''; // À renseigner selon votre configuration

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonctions utilitaires pour la base de données
function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

function sanitizeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function logAuth($pdo, $user_id, $action) {
    $stmt = $pdo->prepare("INSERT INTO auth_logs (user_id, ip, user_agent, action) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $action
    ]);
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole($pdo, $required_role) {
    requireLogin();
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    $roles = ['USER' => 1, 'MODERATOR' => 2, 'ADMIN' => 3];
    
    if (!$user || $roles[$user['role']] < $roles[$required_role]) {
        header('Location: index.php?error=access_denied');
        exit;
    }
}

function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_banned = FALSE");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>