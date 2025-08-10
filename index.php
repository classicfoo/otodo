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
$priority_classes = [0 => 'bg-secondary-subtle text-secondary', 1 => 'bg-success-subtle text-success', 2 => 'bg-warning-subtle text-warning', 3 => 'bg-danger-subtle text-danger'];
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
    <div class="container d-flex justify-content-between align-items-center">
        <span class="navbar-brand mb-0 h1">Otodo</span>
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
            <a href="index.php" class="list-group-item list-group-item-action">Active Tasks</a>
            <a href="completed.php" class="list-group-item list-group-item-action">Completed Tasks</a>
            <a href="settings.php" class="list-group-item list-group-item-action">Settings</a>
            <a href="logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
    </div>
</div>
<div class="container">
    <form action="add_task.php" method="post" class="mb-3">
        <input type="text" name="description" class="form-control" placeholder="New task" required autocapitalize="none">
        <input type="submit" hidden>
    </form>
    <div class="list-group">
        <?php foreach ($tasks as $task): ?>
            <?php
                $p = (int)($task['priority'] ?? 0);
                if ($p < 0 || $p > 3) { $p = 0; }
                $due = $task['due_date'] ?? '';
                if ($due !== '') {
                    try {
                        $due = (new DateTime($due))->format('j M Y');
                    } catch (Exception $e) {
                        // leave $due unchanged if parsing fails
                    }
                }
            ?>
            <a href="task.php?id=<?=$task['id']?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span class="<?php if ($task['done']) echo 'text-decoration-line-through'; ?>"><?=htmlspecialchars(mb_convert_case($task['description'] ?? '', MB_CASE_TITLE, 'UTF-8'))?></span>
                <span class="d-flex align-items-center gap-2">
                    <span class="text-muted small due-date text-end"><?=htmlspecialchars($due)?></span>
                    <span class="badge <?=$priority_classes[$p]?> priority-badge"><?=$priority_labels[$p]?></span>

                </span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
