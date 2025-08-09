<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$description = trim($_POST['description'] ?? '');
$due_date = trim($_POST['due_date'] ?? '');
$details = trim($_POST['details'] ?? '');
$priority = (int)($_POST['priority'] ?? 2);
if ($priority < 1 || $priority > 3) {
    $priority = 2;
}
if ($description !== '') {
    $stmt = get_db()->prepare('INSERT INTO tasks (user_id, description, due_date, details, priority) VALUES (:uid, :description, :due_date, :details, :priority)');
    $stmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':description' => $description,
        ':due_date' => $due_date !== '' ? $due_date : null,
        ':details' => $details !== '' ? $details : null,
        ':priority' => $priority,
    ]);
}

header('Location: index.php');
exit();
