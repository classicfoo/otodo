<?php
require_once 'db.php';
require_once 'hashtags.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

function respond($status, $message = '', $extra = [], $code = 200) {
    if ($code !== 200) {
        http_response_code($code);
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra));
    exit();
}

$db = get_db();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tags = get_user_hashtags_with_counts($db, $userId);
    respond('ok', '', ['hashtags' => $tags]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Method not allowed', [], 405);
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $name = normalize_hashtag($_POST['name'] ?? '');
        if ($name === '') {
            respond('error', 'Hashtag cannot be empty', [], 400);
        }
        ensure_hashtag_ids($db, $userId, [$name]);
        $hashtags = get_user_hashtags_with_counts($db, $userId);
        respond('ok', '', ['hashtags' => $hashtags]);

    case 'rename':
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = normalize_hashtag($_POST['name'] ?? '');
        if ($id <= 0) {
            respond('error', 'Invalid hashtag id', [], 400);
        }
        if ($name === '') {
            respond('error', 'Hashtag cannot be empty', [], 400);
        }

        $exists = $db->prepare('SELECT id FROM hashtags WHERE id = :id AND user_id = :uid');
        $exists->execute([':id' => $id, ':uid' => $userId]);
        if (!$exists->fetchColumn()) {
            respond('error', 'Hashtag not found', [], 404);
        }

        try {
            $update = $db->prepare('UPDATE hashtags SET name = :name WHERE id = :id AND user_id = :uid');
            $update->execute([':name' => $name, ':id' => $id, ':uid' => $userId]);
        } catch (Exception $e) {
            respond('error', 'Hashtag already exists', [], 409);
        }

        $hashtags = get_user_hashtags_with_counts($db, $userId);
        respond('ok', '', ['hashtags' => $hashtags]);

    case 'delete':
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            respond('error', 'Invalid hashtag id', [], 400);
        }
        $delete = $db->prepare('DELETE FROM hashtags WHERE id = :id AND user_id = :uid');
        $delete->execute([':id' => $id, ':uid' => $userId]);
        $hashtags = get_user_hashtags_with_counts($db, $userId);
        respond('ok', '', ['hashtags' => $hashtags]);

    default:
        respond('error', 'Unknown action', [], 400);
}
