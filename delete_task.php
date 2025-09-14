<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$redirect = $_GET['redirect'] ?? '';
$deleted = false;
if ($id) {
    $stmt = get_db()->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $_SESSION['user_id']]);
    $deleted = true;
}

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode($deleted ? ['status' => 'ok'] : ['status' => 'error']);
    exit();
}

// Allow redirect back to completed page when specified
if ($redirect === 'completed') {
    header('Location: completed.php');
} else {
    header('Location: index.php');
}
exit();
