<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/functions.php';
requireLogin();

$user  = getCurrentUser();
$error   = '';
$success = '';

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';

    if (!$name || !in_array($type, ['income','expense'])) {
        $error = "Please fill all fields correctly.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $name, $type]);
        $success = "Category added!";
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $cat_id = (int)$_GET['delete'];
    // Make sure it belongs to this user
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$cat_id, $user['id']]);
    header("Location: categories.php?deleted=1");
    exit();
}

$categories = getCategories($pdo, $user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories — Expense Tracker</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
    <span class="logo">💰 Expense Tracker</span>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="add_transaction.php">Add</a>
        <a href="transactions.php">Transactions</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="page-wrapper">
    <h2 class="page-title">Manage Categories</h2>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success || isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            <?= isset($_GET['deleted']) ? "Category deleted." : htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Add Category Form -->
    <div class="card" style="max-width:460px; margin-bottom:2rem;">
        <h3 style="margin-bottom:1rem; font-size:1rem;">Add New Category</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="name" placeholder="e.g. Rent" required>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type" required>
                    <option value="">— Select —</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;">Add Category</button>
        </form>
    </div>

    <!-- Categories List -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="3" style="color:var(--muted)">No categories yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td>
                            <span class="badge badge-<?= $cat['type'] ?>">
                                <?= ucfirst($cat['type']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="categories.php?delete=<?= $cat['id'] ?>"
                               class="btn btn-danger"
                               style="padding:.3rem .8rem; font-size:.8rem;"
                               onclick="return confirm('Delete this category?')">
                               Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>