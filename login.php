<?php
$page_title = 'Connexion';
require_once 'header.php';

// Rediriger si déjà connecté
if ($current_user) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
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
        
        if (empty($password)) {
            $errors[] = 'Le mot de passe est requis.';
        }
        
        if (empty($errors)) {
            // Vérifier les identifiants
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['is_banned']) {
                    $errors[] = 'Votre compte a été suspendu. Contactez un administrateur.';
                    logAuth($pdo, $user['id'], 'FAILED_LOGIN');
                } else {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    logAuth($pdo, $user['id'], 'LOGIN');
                    
                    // Redirection vers la page demandée ou l'accueil
                    $redirect = $_GET['redirect'] ?? 'index.php';
                    header('Location: ' . $redirect . '?success=logged_in');
                    exit;
                }
            } else {
                $errors[] = 'Email ou mot de passe incorrect.';
                if ($user) {
                    logAuth($pdo, $user['id'], 'FAILED_LOGIN');
                } else {
                    logAuth($pdo, null, 'FAILED_LOGIN');
                }
            }
        }
    }
}
?>

<div class="container">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Connexion</h1>
                <p>Connectez-vous à votre compte StarMarket</p>
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
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Se connecter</button>
            </form>
            
            <div class="auth-footer">
                <p>Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
                <p><a href="forgot-password.php">Mot de passe oublié ?</a></p>
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