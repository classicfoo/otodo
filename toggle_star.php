<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$db = get_db();
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$starred = isset($_POST['starred']) ? (int)$_POST['starred'] : 0;
$starred = $starred ? 1 : 0;

$stmt = $db->prepare('UPDATE tasks SET starred = :starred WHERE id = :id AND user_id = :uid');
$stmt->execute([
    ':starred' => $starred,
    ':id' => $id,
    ':uid' => $_SESSION['user_id'],
]);

header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'starred' => $starred]);
exit();
