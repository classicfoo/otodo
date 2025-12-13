<?php
require_once 'db.php';
require_once 'hashtags.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$stmt = $db->prepare('SELECT id, description, due_date, details, done, priority FROM tasks WHERE user_id = :uid AND done = 1 ORDER BY due_date IS NULL, due_date, priority DESC, id DESC');
$stmt->execute([':uid' => $_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$priority_labels = [0 => 'None', 1 => 'Low', 2 => 'Medium', 3 => 'High'];
$priority_classes = [0 => 'bg-secondary-subtle text-secondary', 1 => 'bg-success-subtle text-success', 2 => 'bg-warning-subtle text-warning', 3 => 'bg-danger-subtle text-danger'];
$task_ids = array_column($tasks, 'id');
$task_hashtags = get_hashtags_for_tasks($db, (int)$_SESSION['user_id'], $task_ids);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Completed Tasks</title>
    <style>
        .empty-state { color: #6c757d; }
        .navbar-toggler { border: 1px solid #e9ecef; }
        .task-hashtags { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.25rem; }
        .hashtag-badge { background-color: #f3e8ff; color: #6f42c1; border: 1px solid #e5d4ff; }
    </style>
    </head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="navbar-brand">Otodo</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-controls="menu">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
    </nav>

<div class="offcanvas offcanvas-start" tabindex="-1" id="menu" aria-labelledby="menuLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="menuLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <p class="mb-4">Hello, <?=htmlspecialchars($_SESSION['username'] ?? '')?></p>
        <div class="list-group">
            <a href="index.php" class="list-group-item list-group-item-action" data-route>Active Tasks</a>
            <a href="completed.php" class="list-group-item list-group-item-action" data-route>Completed Tasks</a>
            <a href="settings.php" class="list-group-item list-group-item-action" data-route>Settings</a>
            <a href="logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
        <div class="mt-3 small text-muted" id="sync-status" aria-live="polite">All changes saved</div>
    </div>
</div>
<div id="view-root" data-view-root data-view="completed">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Completed Tasks</h5>
        <?php if (!empty($tasks)) : ?>
        <form action="delete_completed.php" method="post" onsubmit="return confirm('Delete all completed tasks?');">
            <button class="btn btn-danger btn-sm" type="submit">Delete All</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if (empty($tasks)) : ?>
        <div class="text-center py-5 empty-state">No completed tasks yet.</div>
    <?php else: ?>
    <div class="list-group">
        <?php foreach ($tasks as $task): ?>
            <?php
                $p = (int)($task['priority'] ?? 0);
                if ($p < 0 || $p > 3) { $p = 0; }
                $due = $task['due_date'] ?? '';
                $dueClass = 'text-muted';
                if ($due !== '') {
                    try {
                        $dueDate = new DateTime($due, $tzObj);
                        if ($dueDate < $today) {
                            $due = 'Overdue';
                            $dueClass = 'text-danger';
                        } else {
                            $dueFmt = $dueDate->format('Y-m-d');
                            if ($dueFmt === $todayFmt) {
                                $due = 'Today';
                                $dueClass = 'text-success';
                            } elseif ($dueFmt === $tomorrowFmt) {
                                $due = 'Tomorrow';
                                $dueClass = 'text-primary';
                            } else {
                                $due = 'Later';
                                $dueClass = 'text-primary';
                            }
                        }
                    } catch (Exception $e) {
                        // leave $due unchanged if parsing fails
                    }
                }
            ?>
            <?php $hashtags = $task_hashtags[$task['id']] ?? []; ?>
            <div class="list-group-item d-flex align-items-start list-group-item-action" onclick="location.href='task.php?id=<?=$task['id']?>'" style="cursor: pointer;">
                <div class="flex-grow-1 text-break">
                    <div class="text-decoration-line-through"><?=htmlspecialchars(ucwords(strtolower($task['description'] ?? '')))?></div>
                    <?php if (!empty($hashtags)): ?>
                        <div class="task-hashtags mt-1">
                            <?php foreach ($hashtags as $tag): ?>
                                <span class="badge hashtag-badge">#<?=htmlspecialchars($tag)?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="d-flex align-items-center gap-2 ms-3 flex-shrink-0 text-nowrap">
                    <?php if ($due !== ''): ?>
                        <span class="small <?=$dueClass?>"><?=htmlspecialchars($due)?></span>

                    <?php endif; ?>
                    <?php if ($p > 0): ?>
                        <span class="badge <?=$priority_classes[$p]?>"><?=$priority_labels[$p]?></span>
                    <?php endif; ?>
                    <a href="delete_task.php?id=<?=$task['id']?>&redirect=completed" class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); return confirm('Delete this task?');">Delete</a>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>
<script src="prevent-save-shortcut.js"></script>
<script src="sw-register.js"></script>
<script src="sync-status.js"></script>
<script src="app-api.js"></script>
<script src="app-router.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.viewRouter = window.viewRouter || new ViewRouter('#view-root');
</script>
</body>
</html>

