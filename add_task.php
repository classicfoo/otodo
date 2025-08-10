<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$description = trim($_POST['description'] ?? '');
if ($description !== '') {
    // Default new tasks to no priority (0)
    $stmt = get_db()->prepare('INSERT INTO tasks (user_id, description, priority) VALUES (:uid, :description, :priority)');
    $stmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':description' => $description,
        ':priority' => 0,
    ]);
}

header('Location: index.php');
exit();
