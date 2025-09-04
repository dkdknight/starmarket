<?php
require_once 'config.php';
require_once 'db.php';

$current_user = getCurrentUser($pdo);
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? sanitizeOutput($page_title) . ' - ' : '' ?><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">
                        <img src="assets/img/logo.png" alt="<?= SITE_NAME ?>" class="logo-img">
                        <span class="logo-text"><?= SITE_NAME ?></span>
                    </a>
                </div>
                
                <nav class="nav">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="index.php" class="nav-link <?= $current_page === 'index' ? 'active' : '' ?>">
                                Accueil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="items.php" class="nav-link <?= $current_page === 'items' ? 'active' : '' ?>">
                                Catalogue
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="browse.php" class="nav-link <?= $current_page === 'browse' ? 'active' : '' ?>">
                                Annonces
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="deals.php" class="nav-link <?= $current_page === 'deals' ? 'active' : '' ?>">
                                Bonnes Affaires
                            </a>
                        </li>
                        <?php if ($current_user): ?>
                        <li class="nav-item">
                            <a href="sell.php" class="nav-link <?= $current_page === 'sell' ? 'active' : '' ?>">
                                Vendre
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="inbox.php" class="nav-link <?= $current_page === 'inbox' ? 'active' : '' ?>">
                                Messages
                                <?php
                                // Compter les messages non lus
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(*) as unread 
                                    FROM messages m 
                                    JOIN conversations c ON m.conversation_id = c.id 
                                    WHERE (c.buyer_id = ? OR c.seller_id = ?) 
                                    AND m.sender_id != ? 
                                    AND m.is_read = FALSE
                                ");
                                $stmt->execute([$current_user['id'], $current_user['id'], $current_user['id']]);
                                $unread = $stmt->fetch()['unread'];
                                if ($unread > 0): ?>
                                <span class="badge badge-notification"><?= $unread ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="user-menu">
                    <?php if ($current_user): ?>
                        <div class="dropdown">
                            <button class="dropdown-toggle">
                                <img src="<?= $current_user['avatar_url'] ?: 'assets/img/default-avatar.png' ?>" 
                                     alt="<?= sanitizeOutput($current_user['username']) ?>" 
                                     class="avatar-small">
                                <?= sanitizeOutput($current_user['username']) ?>
                                <?php if ($current_user['role'] !== 'USER'): ?>
                                <span class="role-badge role-<?= strtolower($current_user['role']) ?>">
                                    <?= $current_user['role'] ?>
                                </span>
                                <?php endif; ?>
                            </button>
                            <div class="dropdown-menu">
                                <a href="profile.php?u=<?= urlencode($current_user['username']) ?>" class="dropdown-item">
                                    Mon Profil
                                </a>
                                <a href="my-listings.php" class="dropdown-item">
                                    Mes Annonces
                                </a>
                                <a href="watchlist.php" class="dropdown-item">
                                    Ma Watchlist
                                </a>
                                <a href="discord-settings.php" class="dropdown-item">
                                    🔗 Paramètres Discord
                                </a>
                                <?php if ($current_user['role'] === 'MODERATOR' || $current_user['role'] === 'ADMIN'): ?>
                                <div class="dropdown-divider"></div>
                                <a href="manage-images.php" class="dropdown-item">
                                    🖼️ Gestion Images
                                </a>
                                <?php endif; ?>
                                <?php if ($current_user['role'] === 'ADMIN'): ?>
                                <a href="admin.php" class="dropdown-item">
                                    Administration
                                </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="logout.php" class="dropdown-item">
                                    Déconnexion
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="auth-buttons">
                            <a href="login.php" class="btn btn-outline">Connexion</a>
                            <a href="register.php" class="btn btn-primary">Inscription</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            switch ($_GET['success']) {
                case 'registered':
                    echo 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
                    break;
                case 'logged_in':
                    echo 'Connexion réussie ! Bienvenue sur StarMarket.';
                    break;
                case 'listing_created':
                    echo 'Votre annonce a été créée avec succès.';
                    break;
                case 'message_sent':
                    echo 'Votre message a été envoyé.';
                    break;
                default:
                    echo 'Opération réussie.';
            }
            ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <?php
            switch ($_GET['error']) {
                case 'login_failed':
                    echo 'Email ou mot de passe incorrect.';
                    break;
                case 'access_denied':
                    echo 'Accès refusé. Vous n\'avez pas les permissions nécessaires.';
                    break;
                case 'banned':
                    echo 'Votre compte a été suspendu. Contactez un administrateur.';
                    break;
                case 'not_found':
                    echo 'Élément non trouvé.';
                    break;
                default:
                    echo 'Une erreur est survenue.';
            }
            ?>
        </div>
        <?php endif; ?>