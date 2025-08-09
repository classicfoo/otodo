<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare('SELECT id, description, due_date, details, done FROM tasks WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $id, ':uid' => $_SESSION['user_id']]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $stmt = $db->prepare('UPDATE tasks SET description = :description, due_date = :due_date, details = :details WHERE id = :id AND user_id = :uid');
    $stmt->execute([
        ':description' => $description,
        ':due_date' => $due_date !== '' ? $due_date : null,
        ':details' => $details !== '' ? $details : null,
        ':id' => $id,
        ':uid' => $_SESSION['user_id'],
    ]);
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Task Details</title>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container">
        <a href="index.php" class="navbar-brand">Todo App</a>
    </div>
</nav>
<div class="container">
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="description" class="form-control" value="<?=htmlspecialchars($task['description'])?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control" value="<?=htmlspecialchars($task['due_date'])?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="details" class="form-control" rows="4"><?=htmlspecialchars($task['details'])?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="toggle_task.php?id=<?=$task['id']?>" class="btn btn-success ms-2"><?=$task['done'] ? 'Undo' : 'Done'?></a>
        <a href="delete_task.php?id=<?=$task['id']?>" class="btn btn-danger ms-2">Delete</a>
    </form>
</div>
</body>
</html>
