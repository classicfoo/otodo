<?php
if (isset($_SERVER['HTTP_X_OTODO_SESSION_ID']) && !empty($_SERVER['HTTP_X_OTODO_SESSION_ID'])) {
    session_id($_SERVER['HTTP_X_OTODO_SESSION_ID']);
}
session_start();

function get_db() {
    static $db = null;
    if ($db === null) {
        $databaseFile = __DIR__ . '/.database.sqlite';
        $legacyDatabaseFile = __DIR__ . '/database.sqlite';
        $previousHiddenFile = __DIR__ . '/.database.sql';

        if (!file_exists($databaseFile)) {
            if (file_exists($previousHiddenFile)) {
                rename($previousHiddenFile, $databaseFile);
            } elseif (file_exists($legacyDatabaseFile)) {
                rename($legacyDatabaseFile, $databaseFile);
            }
        }

        $db = new PDO('sqlite:' . $databaseFile);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            location TEXT,
            default_priority INTEGER NOT NULL DEFAULT 0,
            line_rules TEXT,
            details_color TEXT,
            hashtag_color TEXT,
            date_color TEXT,
            capitalize_sentences INTEGER NOT NULL DEFAULT 1,
            date_formats TEXT,
            text_expanders TEXT
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            description TEXT NOT NULL,
            due_date TEXT,
            details TEXT,
            priority INTEGER NOT NULL DEFAULT 2,
            starred INTEGER NOT NULL DEFAULT 0,
            done INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS hashtags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            UNIQUE(user_id, name),
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS task_hashtags (
            task_id INTEGER NOT NULL,
            hashtag_id INTEGER NOT NULL,
            PRIMARY KEY (task_id, hashtag_id),
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(hashtag_id) REFERENCES hashtags(id) ON DELETE CASCADE
        )");

        // Ensure new columns exist for older databases
        $columns = $db->query('PRAGMA table_info(tasks)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('due_date', $columns, true)) {
            $db->exec('ALTER TABLE tasks ADD COLUMN due_date TEXT');
        }
        if (!in_array('details', $columns, true)) {
            $db->exec('ALTER TABLE tasks ADD COLUMN details TEXT');
        }
        if (!in_array('priority', $columns, true)) {
            $db->exec('ALTER TABLE tasks ADD COLUMN priority INTEGER NOT NULL DEFAULT 2');
        }
        if (!in_array('starred', $columns, true)) {
            $db->exec('ALTER TABLE tasks ADD COLUMN starred INTEGER NOT NULL DEFAULT 0');
        }

        // Ensure user columns exist for older databases
        $userColumns = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('location', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN location TEXT');
        }
        if (!in_array('default_priority', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN default_priority INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('line_rules', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN line_rules TEXT');
        }
        if (!in_array('details_color', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN details_color TEXT');
        }
        if (!in_array('hashtag_color', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN hashtag_color TEXT');
        }
        if (!in_array('date_color', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN date_color TEXT');
        }
        if (!in_array('capitalize_sentences', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN capitalize_sentences INTEGER NOT NULL DEFAULT 1');
        }
        if (!in_array('date_formats', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN date_formats TEXT');
        }
        if (!in_array('text_expanders', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN text_expanders TEXT');
        }

        if (PHP_OS_FAMILY === 'Windows' && file_exists($databaseFile)) {
            exec('attrib +h ' . escapeshellarg($databaseFile));
        }
    }
    return $db;
}
