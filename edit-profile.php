<?php
// app/edit-profile.php
session_start();
require_once __DIR__ . '/includes/db.php';

if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

// -------- CSRF helpers ----------
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check($token) {
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// -------- Utils  ----------
function redirect_with($url, array $flash = []) {
  if (!empty($flash)) $_SESSION['_flash'] = $flash;
  header("Location: $url");
  exit;
}
function get_flash() {
  $f = $_SESSION['_flash'] ?? null;
  unset($_SESSION['_flash']);
  return $f;
}
function ensure_dir($path) {
  if (!is_dir($path)) @mkdir($path, 0777, true);
}

// -------- Charge l'utilisateur courant ----------
$st = $pdo->prepare("SELECT id, username, email, avatar_url, bio, discord_user_id, COALESCE(discord_dm_opt_in,0) AS discord_dm_opt_in, password_hash FROM users WHERE id=?");
$st->execute([$_SESSION['user_id']]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  redirect_with('login.php', ['err' => 'Utilisateur introuvable']);
}

// -------- POST: sauvegarde ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!csrf_check($token)) {
    redirect_with('edit-profile.php', ['err' => 'Session expirée. Réessayez.']);
  }

  $newUsername = trim($_POST['username'] ?? $user['username']);
  $newEmail    = trim($_POST['email'] ?? $user['email']);
  $newBio      = trim($_POST['bio'] ?? ($user['bio'] ?? ''));
  $dmOptIn     = isset($_POST['discord_dm_opt_in']) ? 1 : 0;

  $curPwd      = $_POST['current_password'] ?? '';
  $newPwd      = $_POST['new_password'] ?? '';
  $newPwd2     = $_POST['new_password_confirm'] ?? '';

  $errors = [];
  $updates = [];
  $params  = [];

  // ---- validation username
  if ($newUsername === '') {
    $errors[] = 'Le pseudonyme est requis.';
  } elseif (mb_strlen($newUsername) > 40) {
    $errors[] = 'Le pseudonyme est trop long (max 40).';
  } elseif ($newUsername !== $user['username']) {
    $chk = $pdo->prepare("SELECT id FROM users WHERE username=? AND id<>?");
    $chk->execute([$newUsername, $user['id']]);
    if ($chk->fetch()) $errors[] = 'Ce pseudonyme est déjà utilisé.';
  }

  // ---- validation email
  if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email invalide.';
  } elseif ($newEmail !== $user['email']) {
    $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>?");
    $chk->execute([$newEmail, $user['id']]);
    if ($chk->fetch()) $errors[] = 'Cet email est déjà utilisé.';
  }

  // ---- avatar upload (optionnel)
  $avatarUrl = null;
  if (!empty($_FILES['avatar']['name'])) {
    $f = $_FILES['avatar'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      if ($f['size'] > 1024 * 1024) { // 1MB
        $errors[] = 'Avatar trop volumineux (max 1 Mo).';
      } else {
        $fi   = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $f['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png'])) {
          $errors[] = 'Format avatar non supporté (jpg ou png).';
        } else {
          $ext = $mime === 'image/png' ? 'png' : 'jpg';
          $dir = __DIR__ . '/uploads/avatars';
          ensure_dir($dir);
          $fn  = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
          $dst = $dir . '/' . $fn;
          if (!move_uploaded_file($f['tmp_name'], $dst)) {
            $errors[] = 'Échec upload avatar.';
          } else {
            $avatarUrl = '/app/uploads/avatars/' . $fn;
          }
        }
      }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = 'Erreur upload avatar (code '.$f['error'].').';
    }
  }

  // ---- changement mot de passe (optionnel)
  $newPwdHash = null;
  if ($newPwd !== '' || $newPwd2 !== '') {
    if ($newPwd !== $newPwd2) {
      $errors[] = 'La confirmation du mot de passe ne correspond pas.';
    } elseif (mb_strlen($newPwd) < 8) {
      $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } else {
      // vérifier l'actuel
      if (empty($user['password_hash'])) {
        $errors[] = 'Impossible de vérifier le mot de passe actuel.';
      } elseif (!password_verify($curPwd, $user['password_hash'])) {
        $errors[] = 'Mot de passe actuel incorrect.';
      } else {
        $newPwdHash = password_hash($newPwd, PASSWORD_DEFAULT);
      }
    }
  }

  if (!empty($errors)) {
    redirect_with('edit-profile.php', ['err' => implode("\n", $errors)]);
  }

  // ---- build update
  if ($newUsername !== $user['username']) { $updates[] = 'username=?'; $params[] = $newUsername; }
  if ($newEmail    !== $user['email'])    { $updates[] = 'email=?';    $params[] = $newEmail; }
  if ($newBio      !== ($user['bio'] ?? '')) { $updates[] = 'bio=?';   $params[] = $newBio; }
  if ((int)$dmOptIn !== (int)$user['discord_dm_opt_in']) { $updates[] = 'discord_dm_opt_in=?'; $params[] = (int)$dmOptIn; }
  if ($avatarUrl) { $updates[] = 'avatar_url=?'; $params[] = $avatarUrl; }
  if ($newPwdHash) { $updates[] = 'password_hash=?'; $params[] = $newPwdHash; }

  if (!empty($updates)) {
    $params[] = $user['id'];
    $sql = "UPDATE users SET ".implode(', ', $updates)." WHERE id=?";
    $upd = $pdo->prepare($sql);
    $upd->execute($params);
  }

  redirect_with('edit-profile.php', ['ok' => 'Profil mis à jour.']);
}

// -------- GET: affiche le formulaire ----------
$flash = get_flash();
$pageTitle = 'Modifier mon profil';
include __DIR__ . '/header.php';
?>
<div class="container" style="max-width:880px;margin:0 auto;">
  <h1>Modifier mon profil</h1>

  <?php if(!empty($flash['ok'])): ?>
    <div class="alert alert-success"><?= nl2br(htmlspecialchars($flash['ok'])) ?></div>
  <?php endif; ?>
  <?php if(!empty($flash['err'])): ?>
    <div class="alert alert-danger"><?= nl2br(htmlspecialchars($flash['err'])) ?></div>
  <?php endif; ?>

  <form action="edit-profile.php" method="post" enctype="multipart/form-data" style="display:grid;gap:18px;margin-top:14px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <!-- Avatar -->
    <div class="card" style="padding:16px;">
      <h3>Avatar</h3>
      <div style="display:flex;gap:16px;align-items:center;">
        <div>
          <?php if(!empty($user['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Avatar" style="height:96px;width:96px;border-radius:50%;object-fit:cover;">
          <?php else: ?>
            <div style="height:96px;width:96px;border-radius:50%;background:#2b2f36;display:flex;align-items:center;justify-content:center;color:#9aa4b2;">A</div>
          <?php endif; ?>
        </div>
        <div>
          <input type="file" name="avatar" accept="image/png,image/jpeg">
          <div style="font-size:12px;opacity:.8;">Formats: JPG/PNG — 1 Mo max</div>
        </div>
      </div>
    </div>

    <!-- Identité -->
    <div class="card" style="padding:16px;">
      <h3>Identité</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <label> Pseudonyme
          <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        </label>
        <label> Email
          <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
        </label>
      </div>
    </div>

    <!-- Bio -->
    <div class="card" style="padding:16px;">
      <h3>Bio</h3>
      <textarea name="bio" rows="5" style="width:100%;" placeholder="Quelques mots sur vous..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
    </div>

    <!-- Discord -->
    <div class="card" style="padding:16px;">
      <h3>Discord</h3>
      <div style="display:flex;align-items:center;gap:12px;">
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="discord_dm_opt_in" <?= ((int)$user['discord_dm_opt_in']===1?'checked':'') ?>>
          Recevoir des notifications privées (DM) sur Discord
        </label>
      </div>
      <div style="margin-top:8px;font-size:14px;opacity:.9;">
        Statut :
        <?php if (!empty($user['discord_user_id'])): ?>
          ✅ Compte Discord lié (ID: <?= htmlspecialchars($user['discord_user_id']) ?>).
          <a href="discord-settings.php">Gérer la liaison</a>
        <?php else: ?>
          ⚠️ Non lié. <a href="discord-settings.php">Lier mon compte</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mot de passe -->
    <div class="card" style="padding:16px;">
      <h3>Changer le mot de passe</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <label> Mot de passe actuel
          <input type="password" name="current_password" autocomplete="current-password">
        </label>
        <div></div>
        <label> Nouveau mot de passe
          <input type="password" name="new_password" autocomplete="new-password" placeholder="Min 8 caractères">
        </label>
        <label> Confirmer le nouveau mot de passe
          <input type="password" name="new_password_confirm" autocomplete="new-password">
        </label>
      </div>
      <div style="font-size:12px;opacity:.8;margin-top:6px;">
        Laissez vide pour ne pas changer votre mot de passe.
      </div>
    </div>

    <div>
      <button class="btn" type="submit">Enregistrer</button>
      <a class="btn btn-secondary" href="profile.php?id=<?= (int)$user['id'] ?>">Annuler</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
