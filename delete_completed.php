<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = get_db()->prepare('DELETE FROM tasks WHERE user_id = :uid AND done = 1');
    $stmt->execute([':uid' => $_SESSION['user_id']]);
}

header('Location: completed.php');
exit();

