<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$stmt = $db->prepare('SELECT id, description, done FROM tasks WHERE user_id = :uid ORDER BY id DESC');
$stmt->execute([':uid' => $_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Todo List</title>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Todo App</span>
        <div class="d-flex align-items-center">
            <span class="me-3">Hello, <?=htmlspecialchars($_SESSION['username'])?></span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>
</nav>
<div class="container">
    <form action="add_task.php" method="post" class="d-flex mb-3">
        <input type="text" name="description" class="form-control me-2" placeholder="New task" required>
        <button class="btn btn-primary" type="submit">Add</button>
    </form>
    <ul class="list-group">
        <?php foreach ($tasks as $task): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="<?php if ($task['done']) echo 'text-decoration-line-through'; ?>">
                    <?=htmlspecialchars($task['description'])?>
                </span>
                <span>
                    <a href="toggle_task.php?id=<?=$task['id']?>" class="btn btn-sm btn-success me-1">
                        <?=$task['done'] ? 'Undo' : 'Done'?>
                    </a>
                    <a href="delete_task.php?id=<?=$task['id']?>" class="btn btn-sm btn-danger">Delete</a>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
</body>
</html>
