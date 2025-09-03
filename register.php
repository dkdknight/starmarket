<?php
$page_title = 'Inscription';
require_once 'header.php';

// Rediriger si déjà connecté
if ($current_user) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validation CSRF
    if (!validateCSRFToken($csrf_token)) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        // Validation des champs
        if (empty($email)) {
            $errors[] = 'L\'email est requis.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format d\'email invalide.';
        }
        
        if (empty($username)) {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } elseif (strlen($username) < USERNAME_MIN_LENGTH) {
            $errors[] = 'Le nom d\'utilisateur doit contenir au moins ' . USERNAME_MIN_LENGTH . ' caractères.';
        } elseif (strlen($username) > USERNAME_MAX_LENGTH) {
            $errors[] = 'Le nom d\'utilisateur ne peut pas dépasser ' . USERNAME_MAX_LENGTH . ' caractères.';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores.';
        }
        
        if (empty($password)) {
            $errors[] = 'Le mot de passe est requis.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères.';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }
        
        // Vérifier l'unicité de l'email et du nom d'utilisateur
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Ce nom d\'utilisateur est déjà utilisé.';
            }
        }
        
        // Créer le compte
        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (email, username, password_hash) 
                VALUES (?, ?, ?)
            ");
            
            if ($stmt->execute([$email, $username, $password_hash])) {
                $user_id = $pdo->lastInsertId();
                logAuth($pdo, $user_id, 'REGISTER');
                
                header('Location: login.php?success=registered');
                exit;
            } else {
                $errors[] = 'Erreur lors de la création du compte. Veuillez réessayer.';
            }
        }
    }
}
?>

<div class="container">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Inscription</h1>
                <p>Créez votre compte StarMarket</p>
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
            
            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input" 
                           value="<?= sanitizeOutput($_POST['email'] ?? '') ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           value="<?= sanitizeOutput($_POST['username'] ?? '') ?>" 
                           minlength="<?= USERNAME_MIN_LENGTH ?>"
                           maxlength="<?= USERNAME_MAX_LENGTH ?>"
                           pattern="[a-zA-Z0-9_-]+"
                           required>
                    <div class="form-help">
                        <?= USERNAME_MIN_LENGTH ?>-<?= USERNAME_MAX_LENGTH ?> caractères, lettres, chiffres, tirets et underscores uniquement.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           minlength="<?= PASSWORD_MIN_LENGTH ?>"
                           required>
                    <div class="form-help">
                        Minimum <?= PASSWORD_MIN_LENGTH ?> caractères.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-input" 
                           required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Créer mon compte</button>
            </form>
            
            <div class="auth-footer">
                <p>Déjà un compte ? <a href="login.php">Se connecter</a></p>
            </div>
        </div>
    </div>
</div>

<style>
.auth-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60vh;
    padding: 2rem 0;
}

.auth-card {
    background-color: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    width: 100%;
    max-width: 400px;
    box-shadow: var(--shadow-lg);
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-header h1 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.auth-header p {
    color: var(--text-secondary);
}

.auth-form {
    margin-bottom: 2rem;
}

.btn-full {
    width: 100%;
}

.auth-footer {
    text-align: center;
    font-size: 0.875rem;
}

.auth-footer p {
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
}

.auth-footer a {
    color: var(--primary);
    text-decoration: none;
}

.auth-footer a:hover {
    text-decoration: underline;
}
</style>

<?php require_once 'footer.php'; ?>