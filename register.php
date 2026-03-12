<?php
require 'includes/db.php';
require 'includes/auth.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password || !$confirm) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            // Insert new user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hashed]);

            $userId = $pdo->lastInsertId();

            // Insert default categories for new user
            $defaults = [
                ['Salary',       'income'],
                ['Freelance',    'income'],
                ['Food',         'expense'],
                ['Transport',    'expense'],
                ['Shopping',     'expense'],
                ['Bills',        'expense'],
                ['Health',       'expense'],
                ['Entertainment','expense'],
            ];

            $catStmt = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
            foreach ($defaults as $cat) {
                $catStmt->execute([$userId, $cat[0], $cat[1]]);
            }

            $success = "Account created! You can now log in.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Expense Tracker</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <h1>💰 Expense Tracker</h1>
        <p class="subtitle">Create your free account</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?> 
                <a href="login.php" style="color:inherit;font-weight:700;">Login →</a>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="John Doe" 
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="john@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm" placeholder="Repeat password" required>
            </div>
            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>

        <p style="text-align:center; margin-top:1.2rem; font-size:.9rem; color:var(--muted)">
            Already have an account? <a href="login.php" style="color:var(--primary);font-weight:600;">Log in</a>
        </p>
    </div>
</div>
</body>
</html>