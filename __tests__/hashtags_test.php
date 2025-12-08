<?php
require __DIR__ . '/../hashtags.php';

function assert_equal($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function create_memory_db() {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE hashtags (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, name TEXT NOT NULL, UNIQUE(user_id, name))');
    $db->exec('CREATE TABLE task_hashtags (task_id INTEGER NOT NULL, hashtag_id INTEGER NOT NULL, PRIMARY KEY (task_id, hashtag_id))');
    return $db;
}

// extraction keeps unique, normalized tags and skips trailing punctuation
$extracted = extract_hashtags_from_text('Finish #Build v1. Start #build-Again and then #Test1, plus #Test1.');
assert_equal(['build', 'build-again', 'test1'], $extracted, 'extract_hashtags_from_text should normalize, deduplicate, and keep order of discovery');
assert_equal([], extract_hashtags_from_text('No tags here #'), 'extract_hashtags_from_text should ignore stray hash characters');
assert_equal([], extract_hashtags_from_text(''), 'extract_hashtags_from_text should handle empty strings');

// syncing ensures normalized uniqueness and updates links
$db = create_memory_db();
$userId = 7;
$taskId = 1;

$first = sync_task_hashtags($db, $taskId, $userId, ['Build', 'test', 'Build']);
assert_equal(['build', 'test'], $first, 'sync_task_hashtags should normalize and deduplicate incoming tags');

$hashtags = $db->query('SELECT name FROM hashtags WHERE user_id = 7 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
assert_equal(['build', 'test'], $hashtags, 'sync_task_hashtags should persist normalized hashtags for the user');

$links = $db->query('SELECT hashtag_id FROM task_hashtags WHERE task_id = 1 ORDER BY hashtag_id')->fetchAll(PDO::FETCH_COLUMN);
assert_equal([1, 2], array_map('intval', $links), 'sync_task_hashtags should link task to each hashtag exactly once');

$second = sync_task_hashtags($db, $taskId, $userId, ['test', 'NewTag']);
assert_equal(['test', 'newtag'], $second, 'sync_task_hashtags should return the updated normalized set in input order');

$hashtagsAfterUpdate = $db->query('SELECT name FROM hashtags WHERE user_id = 7 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
assert_equal(['newtag', 'test'], $hashtagsAfterUpdate, 'sync_task_hashtags should drop unused hashtags for the user to avoid stale suggestions');

$linksAfterUpdate = $db->query('SELECT h.name FROM task_hashtags th JOIN hashtags h ON h.id = th.hashtag_id WHERE th.task_id = 1 ORDER BY h.name')->fetchAll(PDO::FETCH_COLUMN);
assert_equal(['newtag', 'test'], $linksAfterUpdate, 'sync_task_hashtags should remove unreferenced links and add new ones');

sync_task_hashtags($db, $taskId, $userId, ['test']);
$hashtagsAfterCleanup = $db->query('SELECT name FROM hashtags WHERE user_id = 7 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
assert_equal(['test'], $hashtagsAfterCleanup, 'sync_task_hashtags should prune hashtags that are no longer referenced anywhere');

// transient hashtags created mid-edit are removed when the final text drops them
$firstPass = sync_task_hashtags($db, $taskId, $userId, ['agl']);
assert_equal(['agl'], $firstPass, 'first pass stores the initial hashtag');

$midEdit = sync_task_hashtags($db, $taskId, $userId, ['aglon']);
assert_equal(['aglon'], $midEdit, 'mid-edit updates to the temporary merged hashtag');

$finalPass = sync_task_hashtags($db, $taskId, $userId, ['agl']);
assert_equal(['agl'], $finalPass, 'final pass restores the intended hashtag');

$hashtagsAfterTransient = $db->query('SELECT name FROM hashtags WHERE user_id = 7 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
assert_equal(['agl'], $hashtagsAfterTransient, 'transient hashtags are pruned so only the intended tag remains for the user');

echo "All hashtag tests passed\n";
