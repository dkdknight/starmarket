<?php
require_once 'config.php';
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    logAuth($pdo, $_SESSION['user_id'], 'LOGOUT');
    session_destroy();
}

header('Location: index.php');
exit;
?>