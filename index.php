<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/functions.php';
requireLogin();

$user  = getCurrentUser();
$month = $_GET['month'] ?? date('m');
$year  = $_GET['year']  ?? date('Y');

$stats  = getMonthlyStats($pdo, $user['id'], $month, $year);
$recent = getRecentTransactions($pdo, $user['id'], 8);

// --- Data for charts ---

// 1. Expense by category (pie chart)
$stmt = $pdo->prepare("
    SELECT c.name, SUM(t.amount) AS total
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense'
      AND MONTH(t.date) = ? AND YEAR(t.date) = ?
    GROUP BY c.name
    ORDER BY total DESC
");
$stmt->execute([$user['id'], $month, $year]);
$expense_by_cat = $stmt->fetchAll();

// 2. Income vs Expense — last 6 months (bar chart)
$bar_labels   = [];
$bar_income   = [];
$bar_expense  = [];

for ($i = 5; $i >= 0; $i--) {
    $ts  = mktime(0, 0, 0, date('m') - $i, 1, date('Y'));
    $m   = date('m', $ts);
    $y   = date('Y', $ts);
    $lbl = date('M y', $ts);

    $s = getMonthlyStats($pdo, $user['id'], $m, $y);
    $bar_labels[]  = $lbl;
    $bar_income[]  = $s['income'];
    $bar_expense[] = $s['expense'];
}

// Month list for filter
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
    <title>Dashboard — Expense Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<header class="navbar">
    <span class="logo">💰 Expense Tracker</span>
    <nav>
        <a href="index.php" class="active">Dashboard</a>
        <a href="add_transaction.php">+ Add</a>
        <a href="transactions.php">Transactions</a>
        <a href="categories.php">Categories</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="page-wrapper">

    <!-- Header row -->
    <div style="display:flex; align-items:center; justify-content:space-between;
                flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <h2 class="page-title" style="margin:0;">
                👋 Welcome, <?= htmlspecialchars($user['name']) ?>
            </h2>
            <p style="color:var(--muted); font-size:.9rem; margin-top:.2rem;">
                <?= $months[$month] ?> <?= $year ?> overview
            </p>
        </div>

        <!-- Month filter -->
        <form method="GET" style="display:flex; gap:.6rem; align-items:center;">
            <select name="month" style="padding:.5rem .8rem; border-radius:7px;
                    border:1.5px solid var(--border); font-size:.9rem;">
                <?php foreach ($months as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $month == $val ? 'selected':'' ?>>
                        <?= $lbl ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="year" style="padding:.5rem .8rem; border-radius:7px;
                    border:1.5px solid var(--border); font-size:.9rem;">
                <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
                    <option value="<?= $y ?>" <?= $year==$y ? 'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary"
                    style="width:auto; padding:.5rem 1rem;">Go</button>
        </form>
    </div>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card income">
            <div class="label">Total Income</div>
            <div class="amount"><?= formatMoney($stats['income']) ?></div>
        </div>
        <div class="stat-card expense">
            <div class="label">Total Expenses</div>
            <div class="amount"><?= formatMoney($stats['expense']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Net Balance</div>
            <div class="amount"
                 style="color:<?= $stats['balance'] >= 0 ? 'var(--success)':'var(--danger)' ?>">
                <?= formatMoney($stats['balance']) ?>
            </div>
        </div>
        <div class="stat-card" style="border-left-color:#f59e0b;">
            <div class="label">Savings Rate</div>
            <div class="amount" style="color:#f59e0b;">
                <?php
                    $rate = $stats['income'] > 0
                        ? round(($stats['balance'] / $stats['income']) * 100, 1)
                        : 0;
                    echo $rate . '%';
                ?>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">

        <!-- Bar Chart -->
        <div class="card">
            <h3 style="font-size:1rem; margin-bottom:1rem; color:var(--text);">
                Income vs Expense — Last 6 Months
            </h3>
            <canvas id="barChart" height="220"></canvas>
        </div>

        <!-- Pie Chart -->
        <div class="card">
            <h3 style="font-size:1rem; margin-bottom:1rem; color:var(--text);">
                Expenses by Category
            </h3>
            <?php if (empty($expense_by_cat)): ?>
                <p style="color:var(--muted); text-align:center; padding:3rem 0;">
                    No expense data this month.
                </p>
            <?php else: ?>
                <canvas id="pieChart" height="220"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; 
                    align-items:center; margin-bottom:1rem;">
            <h3 style="font-size:1rem;">Recent Transactions</h3>
            <a href="transactions.php" style="font-size:.85rem; color:var(--primary); 
                                              font-weight:600;">View all →</a>
        </div>

        <?php if (empty($recent)): ?>
            <p style="color:var(--muted); text-align:center; padding:2rem 0;">
                No transactions yet. 
                <a href="add_transaction.php" style="color:var(--primary);">Add one →</a>
            </p>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $tx): ?>
                <tr>
                    <td style="color:var(--muted); font-size:.88rem;">
                        <?= date('d M', strtotime($tx['date'])) ?>
                    </td>
                    <td><?= htmlspecialchars($tx['title']) ?></td>
                    <td>
                        <span class="badge badge-<?= $tx['type'] ?>">
                            <?= htmlspecialchars($tx['category_name']) ?>
                        </span>
                    </td>
                    <td style="text-align:right; font-weight:600;
                               color:<?= $tx['type']==='income' ? 'var(--success)':'var(--danger)' ?>">
                        <?= ($tx['type']==='income' ? '+' : '−') . formatMoney($tx['amount']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Chart.js Scripts -->
<script>
// Bar Chart
const barCtx = document.getElementById('barChart');
if (barCtx) {
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($bar_labels) ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?= json_encode($bar_income) ?>,
                    backgroundColor: '#4ade8033',
                    borderColor: '#4ade80',
                    borderWidth: 2,
                    borderRadius: 5,
                },
                {
                    label: 'Expense',
                    data: <?= json_encode($bar_expense) ?>,
                    backgroundColor: '#ef444433',
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    borderRadius: 5,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#86efac',
                        callback: v => '৳' + v.toLocaleString()
                    },
                    grid: { color: '#14532d' }
                },
                x: {
                    ticks: { color: '#86efac' },
                    grid: { color: '#14532d' }
                }
            }
        }
    });
}

// Pie Chart
const pieCtx = document.getElementById('pieChart');
if (pieCtx) {
    const pieColors = [
        '#4f46e5','#ef4444','#10b981','#f59e0b',
        '#3b82f6','#ec4899','#8b5cf6','#14b8a6'
    ];
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($expense_by_cat, 'name')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($expense_by_cat, 'total')) ?>,
                backgroundColor: pieColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right', labels: { font: { size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ৳' + parseFloat(ctx.raw).toLocaleString()
                    }
                }
            }
        }
    });
}
</script>

</body>
</html>