<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$stmt = $db->prepare('SELECT id, description, due_date, details, done, priority FROM tasks WHERE user_id = :uid AND done = 1 ORDER BY due_date IS NULL, due_date, priority DESC, id DESC');
$stmt->execute([':uid' => $_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$priority_labels = [0 => 'None', 1 => 'Low', 2 => 'Medium', 3 => 'High'];
$priority_classes = [0 => 'bg-secondary', 1 => 'bg-success', 2 => 'bg-warning', 3 => 'bg-danger'];
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
    </style>
    </head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container">
        <a href="index.php" class="navbar-brand">Todo App</a>
        <div class="d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Active</a>
            <span class="me-3">Hello, <?=htmlspecialchars($_SESSION['username'] ?? '')?></span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>
    </nav>
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
            <?php $p = (int)($task['priority'] ?? 0); if ($p < 0 || $p > 3) { $p = 0; } ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span class="text-decoration-line-through"><?=htmlspecialchars($task['description'] ?? '')?></span>
                <span class="d-flex align-items-center gap-2">
                    <?php if (!empty($task['due_date'])): ?>
                        <span class="text-muted small"><?=htmlspecialchars($task['due_date'])?></span>
                    <?php endif; ?>
                    <?php if ($p > 0): ?>
                        <span class="badge <?=$priority_classes[$p]?>"><?=$priority_labels[$p]?></span>
                    <?php endif; ?>
                    <a href="delete_task.php?id=<?=$task['id']?>&redirect=completed" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this task?');">Delete</a>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

