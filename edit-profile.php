<?php
$page_title = 'Modifier mon profil';
require_once 'header.php';

// L'utilisateur doit être connecté pour modifier son profil
requireLogin();

$errors = [];

// Pré-remplir les champs avec les données actuelles
$email = $current_user['email'] ?? '';
$username = $current_user['username'] ?? '';
$bio = $current_user['bio'] ?? '';
$discord_user_id = $current_user['discord_user_id'] ?? '';
$discord_notifications = (bool)($current_user['discord_notifications'] ?? true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $discord_user_id = trim($_POST['discord_user_id'] ?? '');
    $discord_notifications = isset($_POST['discord_notifications']);
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Validation CSRF
    if (!validateCSRFToken($csrf_token)) {
        $errors[] = 'Token de sécurité invalide.';
    }

    // Validation de l'email
    if (empty($email)) {
        $errors[] = "L'email est requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide.";
    } elseif ($email !== $current_user['email']) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Cette adresse email est déjà utilisée.';
        }
    }

    // Validation du nom d'utilisateur
    if (empty($username)) {
        $errors[] = "Le nom d'utilisateur est requis.";
    } elseif (strlen($username) < USERNAME_MIN_LENGTH) {
        $errors[] = 'Le nom d\'utilisateur doit contenir au moins ' . USERNAME_MIN_LENGTH . ' caractères.';
    } elseif (strlen($username) > USERNAME_MAX_LENGTH) {
        $errors[] = 'Le nom d\'utilisateur ne peut pas dépasser ' . USERNAME_MAX_LENGTH . ' caractères.';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $errors[] = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores.';
    } elseif ($username !== $current_user['username']) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Ce nom d\'utilisateur est déjà utilisé.';
        }
    }

    // Validation de la bio
    if (strlen($bio) > 1000) {
        $errors[] = 'La bio ne peut pas dépasser 1000 caractères.';
    }

    // Validation de l'ID Discord
    if (!empty($discord_user_id) && !preg_match('/^[0-9]{17,19}$/', $discord_user_id)) {
        $errors[] = "L'ID Discord doit contenir entre 17 et 19 chiffres.";
    }
    if (empty($discord_user_id)) {
        $discord_user_id = null;
        $discord_notifications = false;
    }

    // Gestion du mot de passe
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $update_password = false;
    if (!empty($new_password) || !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères.';
        } else {
            $update_password = true;
        }
    }

    // Upload de l'avatar
    $avatar_url = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_result = processImageUpload($_FILES['avatar'], 'avatars');
        if (!$upload_result['success']) {
            $errors = array_merge($errors, $upload_result['errors']);
        } else {
            $avatar_url = $upload_result['file_url'];
        }
    }

    if (empty($errors)) {
        $params = [$email, $username, $bio, $discord_user_id, $discord_notifications];
        $sql = 'UPDATE users SET email = ?, username = ?, bio = ?, discord_user_id = ?, discord_notifications = ?';
        if ($avatar_url) {
            $sql .= ', avatar_url = ?';
            $params[] = $avatar_url;
        }
        if ($update_password) {
            $sql .= ', password_hash = ?';
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = ?';
        $params[] = $current_user['id'];

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            header('Location: profile.php?u=' . urlencode($username) . '&success=profile_updated');
            exit;
        } else {
            $errors[] = 'Erreur lors de la mise à jour du profil.';
        }
    }
}
?>

<div class="container">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Modifier mon profil</h1>
                <p>Mettez à jour vos informations</p>
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

            <form method="POST" class="auth-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="form-group">
                    <label for="avatar" class="form-label">Photo de profil</label>
                    <input type="file" id="avatar" name="avatar" class="form-input" accept="image/*">
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input" value="<?= sanitizeOutput($email) ?>" required>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-input"
                           value="<?= sanitizeOutput($username) ?>"
                           minlength="<?= USERNAME_MIN_LENGTH ?>"
                           maxlength="<?= USERNAME_MAX_LENGTH ?>"
                           pattern="[a-zA-Z0-9_-]+" required>
                </div>

                <div class="form-group">
                    <label for="bio" class="form-label">Bio</label>
                    <textarea id="bio" name="bio" class="form-input" rows="4" maxlength="1000"><?= sanitizeOutput($bio) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="discord_user_id" class="form-label">ID Discord</label>
                    <input type="text" id="discord_user_id" name="discord_user_id" class="form-input"
                           value="<?= sanitizeOutput($discord_user_id) ?>" pattern="[0-9]{17,19}" placeholder="123456789012345678">
                    <div class="form-help">17-19 chiffres</div>
                </div>

                <div class="form-group form-checkbox">
                    <label>
                        <input type="checkbox" name="discord_notifications" <?= $discord_notifications ? 'checked' : '' ?>>
                        Activer les notifications Discord
                    </label>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password" class="form-input" minlength="<?= PASSWORD_MIN_LENGTH ?>">
                    <div class="form-help">Laissez vide pour conserver votre mot de passe actuel</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input">
                </div>

                <button type="submit" class="btn btn-primary btn-full">Mettre à jour</button>
            </form>
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
    max-width: 500px;
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

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.form-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background-color: var(--bg-input);
    color: var(--text-primary);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 1px var(--primary);
}

.form-help {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.btn-full {
    width: 100%;
}

.form-checkbox {
    display: flex;
    align-items: center;
}

.form-checkbox label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
</style>

<?php require_once 'footer.php'; ?>