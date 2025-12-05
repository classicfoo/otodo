<?php
require_once 'db.php';
require_once 'line_rules.php';

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
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $defaultRules = encode_line_rules_for_storage(get_default_line_rules());
            $stmt = get_db()->prepare('INSERT INTO users (username, password, default_priority, line_rules, details_color) VALUES (:username, :password, 0, :rules, :color)');
            $stmt->execute([
                ':username' => $username,
                ':password' => $hash,
                ':rules' => $defaultRules,
                ':color' => '#212529',
            ]);
            header('Location: login.php');
            exit();
        } catch (PDOException $e) {
            $error = 'Username already taken';
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
    <title>Register</title>
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Register</h2>
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
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login</a></p>
</div>
<script src="prevent-save-shortcut.js"></script>
<script src="sw-register.js"></script>
</body>
</html>
