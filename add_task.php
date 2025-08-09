<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$description = trim($_POST['description'] ?? '');
$due_date = trim($_POST['due_date'] ?? '');
$details = trim($_POST['details'] ?? '');
if ($description !== '') {
    $stmt = get_db()->prepare('INSERT INTO tasks (user_id, description, due_date, details) VALUES (:uid, :description, :due_date, :details)');
    $stmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':description' => $description,
        ':due_date' => $due_date !== '' ? $due_date : null,
        ':details' => $details !== '' ? $details : null,
    ]);
}

header('Location: index.php');
exit();
