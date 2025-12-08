<?php
require_once 'db.php';
require_once 'hashtags.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();

$description = ucwords(strtolower(trim($_POST['description'] ?? '')));
$accepts_json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

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
    $stmt = $db->prepare('INSERT INTO tasks (user_id, description, priority, due_date, starred) VALUES (:uid, :description, :priority, :due_date, 0)');
    $stmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':description' => $description,
        ':priority' => $priority,
        ':due_date' => $due_date,
    ]);

    $id = (int)$db->lastInsertId();
    $hashtags = collect_hashtags_from_texts($description);
    sync_task_hashtags($db, $id, (int)$_SESSION['user_id'], $hashtags);

    if ($accepts_json) {
        $priority_labels = [0 => 'None', 1 => 'Low', 2 => 'Medium', 3 => 'High'];
        $priority_classes = [0 => 'text-secondary', 1 => 'text-success', 2 => 'text-warning', 3 => 'text-danger'];

        $tz = $_SESSION['location'] ?? 'UTC';
        try {
            $tzObj = new DateTimeZone($tz);
        } catch (Exception $e) {
            $tzObj = new DateTimeZone('UTC');
        }
        $today = new DateTime('today', $tzObj);
        $tomorrow = (clone $today)->modify('+1 day');
        $todayFmt = $today->format('Y-m-d');
        $tomorrowFmt = $tomorrow->format('Y-m-d');

        $due = $due_date;
        $dueClass = 'bg-secondary-subtle text-secondary';
        if ($due !== '') {
            try {
                $dueDate = new DateTime($due, $tzObj);
                if ($dueDate < $today) {
                    $due = 'Overdue';
                    $dueClass = 'bg-danger-subtle text-danger';
                } else {
                    $dueFmt = $dueDate->format('Y-m-d');
                    if ($dueFmt === $todayFmt) {
                        $due = 'Today';
                        $dueClass = 'bg-success-subtle text-success';
                    } elseif ($dueFmt === $tomorrowFmt) {
                        $due = 'Tomorrow';
                        $dueClass = 'bg-primary-subtle text-primary';
                    } else {
                        $due = 'Later';
                        $dueClass = 'bg-primary-subtle text-primary';

                    }
                }
            } catch (Exception $e) {
                // leave $due unchanged if parsing fails
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'id' => $id,
            'description' => $description,
            'due_date' => $due_date,
            'due_label' => $due,
            'due_class' => $dueClass,
            'priority' => $priority,
            'priority_label' => $priority_labels[$priority] ?? 'None',
            'priority_class' => $priority_classes[$priority] ?? 'text-secondary',
            'starred' => 0,
            'hashtags' => $hashtags,
        ]);
        exit();
    }
}

header('Location: index.php');
exit();
