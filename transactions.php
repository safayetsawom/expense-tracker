<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/functions.php';
requireLogin();

$user = getCurrentUser();

// Filters
$filter_type  = $_GET['type']  ?? '';
$filter_month = $_GET['month'] ?? date('m');
$filter_year  = $_GET['year']  ?? date('Y');

// Build query dynamically
$params = [$user['id'], $filter_month, $filter_year];
$type_condition = '';

if (in_array($filter_type, ['income','expense'])) {
    $type_condition = "AND t.type = ?";
    $params[] = $filter_type;
}

$stmt = $pdo->prepare("
    SELECT t.*, c.name AS category_name
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
      AND MONTH(t.date) = ?
      AND YEAR(t.date)  = ?
      $type_condition
    ORDER BY t.date DESC, t.created_at DESC
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$stats = getMonthlyStats($pdo, $user['id'], $filter_month, $filter_year);

// Build month list for filter dropdown
$months = [
    '01'=>'January','02'=>'February','03'=>'March',
    '04'=>'April','05'=>'May','06'=>'June',
    '07'=>'July','08'=>'August','09'=>'September',
    '10'=>'October','11'=>'November','12'=>'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions — Expense Tracker</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
    <span class="logo">💰 Expense Tracker</span>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="add_transaction.php">Add</a>
        <a href="transactions.php" class="active">Transactions</a>
        <a href="categories.php">Categories</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="page-wrapper">
    <h2 class="page-title">Transactions</h2>

    <!-- Filters -->
    <div class="card" style="padding:1rem 1.5rem;">
        <form method="GET" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="margin:0; min-width:140px;">
                <label style="font-size:.8rem;">Month</label>
                <select name="month">
                    <?php foreach ($months as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $filter_month == $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0; min-width:100px;">
                <label style="font-size:.8rem;">Year</label>
                <select name="year">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0; min-width:130px;">
                <label style="font-size:.8rem;">Type</label>
                <select name="type">
                    <option value="">All</option>
                    <option value="income"  <?= $filter_type === 'income'  ? 'selected' : '' ?>>Income</option>
                    <option value="expense" <?= $filter_type === 'expense' ? 'selected' : '' ?>>Expense</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" 
                    style="width:auto; padding:.6rem 1.2rem; margin-bottom:1px;">
                Filter
            </button>
            <a href="transactions.php" class="btn" 
               style="background:var(--border); color:var(--text);
                      padding:.6rem 1.2rem; margin-bottom:1px;">
                Reset
            </a>
            <a href="export.php?month=<?= $filter_month ?>&year=<?= $filter_year ?>&type=<?= $filter_type ?>"
               class="btn btn-success"
               style="width:auto; padding:.6rem 1.2rem; margin-bottom:1px; margin-left:auto;">
                ⬇ Export CSV
            </a>
        </form>
    </div>

    <!-- Monthly mini-stats -->
    <div class="stats-grid">
        <div class="stat-card income">
            <div class="label">Income</div>
            <div class="amount"><?= formatMoney($stats['income']) ?></div>
        </div>
        <div class="stat-card expense">
            <div class="label">Expense</div>
            <div class="amount"><?= formatMoney($stats['expense']) ?></div>
        </div>
        <div class="stat-card" style="border-left-color: var(--primary)">
            <div class="label">Balance</div>
            <div class="amount" style="color:<?= $stats['balance'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                <?= formatMoney($stats['balance']) ?>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <?php if (empty($transactions)): ?>
            <p style="color:var(--muted); text-align:center; padding:2rem 0;">
                No transactions found for this period.
            </p>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td style="color:var(--muted); font-size:.88rem;">
                        <?= date('d M Y', strtotime($tx['date'])) ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($tx['title']) ?></strong>
                        <?php if ($tx['note']): ?>
                            <br><small style="color:var(--muted);">
                                <?= htmlspecialchars($tx['note']) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($tx['category_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $tx['type'] ?>">
                            <?= ucfirst($tx['type']) ?>
                        </span>
                    </td>
                    <td style="text-align:right; font-weight:600;
                               color:<?= $tx['type'] === 'income' ? 'var(--success)' : 'var(--danger)' ?>">
                        <?= ($tx['type'] === 'income' ? '+' : '−') . formatMoney($tx['amount']) ?>
                    </td>
                    <td>
                        <a href="delete_transaction.php?id=<?= $tx['id'] ?>"
                           class="btn btn-danger"
                           style="padding:.3rem .7rem; font-size:.8rem;"
                           onclick="return confirm('Delete this transaction?')">
                           Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>