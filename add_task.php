<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$description = trim($_POST['description'] ?? '');
if ($description !== '') {
    $stmt = get_db()->prepare('INSERT INTO tasks (user_id, description) VALUES (:uid, :description)');
    $stmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':description' => $description,
    ]);
}

header('Location: index.php');
exit();
