<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$stmt = $db->prepare('SELECT id, description, due_date, details, done, priority FROM tasks WHERE user_id = :uid AND done = 0 ORDER BY due_date IS NULL, due_date, priority DESC, id DESC');

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
    <style>
        .due-date { display: inline-block; width: 100px; text-align: right; }
        .priority-badge { display: inline-block; width: 70px; text-align: center; }
    </style>
    <title>Todo List</title>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Todo App</span>
        <div class="d-flex align-items-center gap-2">
            <a href="completed.php" class="btn btn-outline-secondary btn-sm">Completed</a>
            <span class="me-3">Hello, <?=htmlspecialchars($_SESSION['username'] ?? '')?></span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>
</nav>
<div class="container">
    <form action="add_task.php" method="post" class="mb-3">
        <input type="text" name="description" class="form-control" placeholder="New task" required>
        <input type="submit" hidden>
    </form>
    <div class="list-group">
        <?php foreach ($tasks as $task): ?>
            <?php $p = (int)($task['priority'] ?? 0); if ($p < 0 || $p > 3) { $p = 0; } ?>
            <a href="task.php?id=<?=$task['id']?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span class="<?php if ($task['done']) echo 'text-decoration-line-through'; ?>"><?=htmlspecialchars($task['description'] ?? '')?></span>
                <span class="d-flex align-items-center gap-2">
                    <span class="text-muted small due-date text-end"><?=htmlspecialchars($task['due_date'] ?? '')?></span>
                    <span class="badge <?=$priority_classes[$p]?> priority-badge"><?=$priority_labels[$p]?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
