<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $stmt = get_db()->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $_SESSION['user_id']]);
}

header('Location: index.php');
exit();
