<?php
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields';
    } else {
        $stmt = get_db()->prepare('SELECT id, password, location, default_priority FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['location'] = $user['location'] ?? 'UTC';
            $_SESSION['default_priority'] = (int)($user['default_priority'] ?? 0);
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Login</title>
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Login</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?=$error?></div>
    <?php endif; ?>
    <form method="post" class="mb-3">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register</a></p>
</div>
<script src="prevent-save-shortcut.js"></script>
<script src="sw-register.js"></script>
</body>
</html>
