<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();

$description = ucwords(strtolower(trim($_POST['description'] ?? '')));

if ($description !== '') {
    // Determine today's date based on user location
    $tz = $_SESSION['location'] ?? null;
    if ($tz === null) {
        $stmt = $db->prepare('SELECT location FROM users WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $tz = $stmt->fetchColumn() ?: 'UTC';
        $_SESSION['location'] = $tz;
    }
    try {
        $now = new DateTime('now', new DateTimeZone($tz));
    } catch (Exception $e) {
        $now = new DateTime('now');
    }
    $due_date = $now->format('Y-m-d');

    // Insert new task using user's default priority
    $priority = (int)($_SESSION['default_priority'] ?? 0);
    $stmt = $db->prepare('INSERT INTO tasks (user_id, description, priority, due_date) VALUES (:uid, :description, :priority, :due_date)');
    $stmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':description' => $description,
        ':priority' => $priority,
        ':due_date' => $due_date,
    ]);
}

header('Location: index.php');
exit();
