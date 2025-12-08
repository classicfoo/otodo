<?php
require_once __DIR__ . '/db.php';

function normalize_hashtag($tag) {
    $normalized = trim((string)$tag);
    $normalized = ltrim($normalized, '#');
    if ($normalized === '') {
        return '';
    }
    return mb_strtolower($normalized, 'UTF-8');
}

function extract_hashtags_from_text($text) {
    if (!is_string($text) || $text === '') {
        return [];
    }
    $matches = [];
    preg_match_all('/#([\p{L}\p{N}_-]+)/u', $text, $matches);
    if (empty($matches[1])) {
        return [];
    }
    $found = [];
    foreach ($matches[1] as $match) {
        $normalized = normalize_hashtag($match);
        if ($normalized !== '') {
            $found[$normalized] = true;
        }
    }
    return array_keys($found);
}

function collect_hashtags_from_texts(...$texts) {
    $all = [];
    foreach ($texts as $text) {
        foreach (extract_hashtags_from_text($text) as $tag) {
            $all[$tag] = true;
        }
    }
    return array_keys($all);
}

function get_user_hashtags(PDO $db, $userId) {
    $stmt = $db->prepare('SELECT name FROM hashtags WHERE user_id = :uid ORDER BY name COLLATE NOCASE');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function get_task_hashtags(PDO $db, $taskId, $userId) {
    $stmt = $db->prepare('SELECT h.name FROM task_hashtags th INNER JOIN hashtags h ON h.id = th.hashtag_id WHERE th.task_id = :tid AND h.user_id = :uid ORDER BY h.name COLLATE NOCASE');
    $stmt->execute([':tid' => $taskId, ':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function get_hashtags_for_tasks(PDO $db, $userId, array $taskIds) {
    if (empty($taskIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $params = array_merge([$userId], $taskIds);
    $sql = "SELECT th.task_id, h.name FROM task_hashtags th INNER JOIN hashtags h ON h.id = th.hashtag_id WHERE h.user_id = ? AND th.task_id IN ($placeholders) ORDER BY h.name COLLATE NOCASE";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $taskId = (int)$row['task_id'];
        $map[$taskId][] = $row['name'];
    }
    return $map;
}

function ensure_hashtag_ids(PDO $db, $userId, array $hashtags) {
    if (empty($hashtags)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($hashtags), '?'));
    $query = $db->prepare("SELECT id, name FROM hashtags WHERE user_id = ? AND name IN ($placeholders)");
    $query->execute(array_merge([$userId], $hashtags));
    $existing = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['name']] = (int)$row['id'];
    }

    $insert = $db->prepare('INSERT OR IGNORE INTO hashtags (user_id, name) VALUES (:uid, :name)');
    foreach ($hashtags as $tag) {
        if (!isset($existing[$tag])) {
            $insert->execute([':uid' => $userId, ':name' => $tag]);
            $existing[$tag] = (int)$db->lastInsertId();
        }
    }

    $missing = array_diff($hashtags, array_keys($existing));
    if (!empty($missing)) {
        $reload = $db->prepare("SELECT id, name FROM hashtags WHERE user_id = ? AND name IN ($placeholders)");
        $reload->execute(array_merge([$userId], $hashtags));
        foreach ($reload->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['name']] = (int)$row['id'];
        }
    }

    return $existing;
}

function sync_task_hashtags(PDO $db, $taskId, $userId, array $hashtags) {
    $unique = [];
    foreach ($hashtags as $tag) {
        $normalized = normalize_hashtag($tag);
        if ($normalized !== '') {
            $unique[$normalized] = true;
        }
    }
    $tags = array_keys($unique);

    $db->beginTransaction();
    try {
        if (empty($tags)) {
            $delete = $db->prepare('DELETE FROM task_hashtags WHERE task_id = :tid');
            $delete->execute([':tid' => $taskId]);
            $db->commit();
            return [];
        }

        $ids = ensure_hashtag_ids($db, $userId, $tags);

        $currentStmt = $db->prepare('SELECT h.id, h.name FROM task_hashtags th INNER JOIN hashtags h ON h.id = th.hashtag_id WHERE th.task_id = :tid');
        $currentStmt->execute([':tid' => $taskId]);
        $current = [];
        foreach ($currentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $current[$row['name']] = (int)$row['id'];
        }

        $toRemove = array_diff(array_keys($current), $tags);
        if (!empty($toRemove)) {
            $idsToRemove = array_map(function($name) use ($current) { return $current[$name]; }, $toRemove);
            $placeholders = implode(',', array_fill(0, count($idsToRemove), '?'));
            $delete = $db->prepare("DELETE FROM task_hashtags WHERE task_id = ? AND hashtag_id IN ($placeholders)");
            $delete->execute(array_merge([$taskId], $idsToRemove));
        }

        $toAdd = array_diff($tags, array_keys($current));
        if (!empty($toAdd)) {
            $insertLink = $db->prepare('INSERT OR IGNORE INTO task_hashtags (task_id, hashtag_id) VALUES (:tid, :hid)');
            foreach ($toAdd as $tagName) {
                $hid = $ids[$tagName] ?? null;
                if ($hid) {
                    $insertLink->execute([':tid' => $taskId, ':hid' => $hid]);
                }
            }
        }

        $db->commit();
        return $tags;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

