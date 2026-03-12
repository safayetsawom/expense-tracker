<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/functions.php';
requireLogin();

$user = getCurrentUser();
$error   = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $amount      = trim($_POST['amount']      ?? '');
    $type        = $_POST['type']             ?? '';
    $category_id = $_POST['category_id']      ?? '';
    $date        = $_POST['date']             ?? '';
    $note        = trim($_POST['note']        ?? '');

    if (!$title || !$amount || !$type || !$category_id || !$date) {
        $error = "All required fields must be filled.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Amount must be a positive number.";
    } elseif (!in_array($type, ['income', 'expense'])) {
        $error = "Invalid transaction type.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, category_id, title, amount, type, note, date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'], $category_id, $title,
            $amount, $type, $note, $date
        ]);
        $success = "Transaction added successfully!";
    }
}

// Load categories for dropdowns
$income_cats  = getCategories($pdo, $user['id'], 'income');
$expense_cats = getCategories($pdo, $user['id'], 'expense');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction — Expense Tracker</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Navbar -->
<header class="navbar">
    <span class="logo">💰 Expense Tracker</span>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="add_transaction.php" class="active">Add</a>
        <a href="transactions.php">Transactions</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="page-wrapper">
    <h2 class="page-title">Add Transaction</h2>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:560px;">
        <form method="POST" id="txForm">

            <!-- Type Toggle -->
            <div class="form-group">
                <label>Transaction Type</label>
                <div class="type-toggle">
                    <label class="toggle-opt">
                        <input type="radio" name="type" value="expense"
                            <?= (($_POST['type'] ?? 'expense') === 'expense') ? 'checked' : '' ?>>
                        <span class="expense">− Expense</span>
                    </label>
                    <label class="toggle-opt">
                        <input type="radio" name="type" value="income"
                            <?= (($_POST['type'] ?? '') === 'income') ? 'checked' : '' ?>>
                        <span class="income">+ Income</span>
                    </label>
                </div>
            </div>

            <!-- Title -->
            <div class="form-group">
                <label>Title <span style="color:var(--danger)">*</span></label>
                <input type="text" name="title" placeholder="e.g. Grocery shopping"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
            </div>

            <!-- Amount -->
            <div class="form-group">
                <label>Amount (৳) <span style="color:var(--danger)">*</span></label>
                <input type="number" name="amount" placeholder="0.00" min="0.01"
                       step="0.01" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" required>
            </div>

            <!-- Category (dynamic via JS) -->
            <div class="form-group">
                <label>Category <span style="color:var(--danger)">*</span></label>
                <select name="category_id" id="categorySelect" required>
                    <option value="">— Select category —</option>
                </select>
            </div>

            <!-- Date -->
            <div class="form-group">
                <label>Date <span style="color:var(--danger)">*</span></label>
                <input type="date" name="date"
                       value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>" required>
            </div>

            <!-- Note -->
            <div class="form-group">
                <label>Note <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
                <textarea name="note" rows="2"
                          placeholder="Any extra details..."><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Add Transaction</button>
        </form>
    </div>
</div>

<!-- Pass PHP categories to JS -->
<script>
const incomeCategories = <?= json_encode($income_cats) ?>;
const expenseCategories = <?= json_encode($expense_cats) ?>;
const savedCategory = <?= json_encode($_POST['category_id'] ?? '') ?>;

function updateCategories() {
    const type = document.querySelector('input[name="type"]:checked').value;
    const cats = type === 'income' ? incomeCategories : expenseCategories;
    const select = document.getElementById('categorySelect');

    select.innerHTML = '<option value="">— Select category —</option>';
    cats.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.id;
        opt.textContent = cat.name;
        if (cat.id == savedCategory) opt.selected = true;
        select.appendChild(opt);
    });
}

// Run on page load + on type change
updateCategories();
document.querySelectorAll('input[name="type"]').forEach(r => {
    r.addEventListener('change', updateCategories);
});
</script>

<!-- Extra CSS for this page -->
<style>
.type-toggle {
    display: flex;
    gap: .75rem;
}
.toggle-opt input { display: none; }
.toggle-opt span {
    display: block;
    padding: .55rem 1.4rem;
    border-radius: 7px;
    border: 2px solid var(--border);
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    font-size: .95rem;
}
.toggle-opt input:checked + span.expense {
    background: #fee2e2;
    border-color: var(--danger);
    color: var(--danger);
}
.toggle-opt input:checked + span.income {
    background: #d1fae5;
    border-color: var(--success);
    color: var(--success);
}
</style>

</body>
</html>