<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/functions.php';
requireLogin();

$user = getCurrentUser();

$month = $_GET['month'] ?? date('m');
$year  = $_GET['year']  ?? date('Y');
$type  = $_GET['type']  ?? '';

$params = [$user['id'], $month, $year];
$type_condition = '';

if (in_array($type, ['income', 'expense'])) {
    $type_condition = "AND t.type = ?";
    $params[] = $type;
}

$stmt = $pdo->prepare("
    SELECT 
        t.date,
        t.title,
        c.name   AS category,
        t.type,
        t.amount,
        t.note
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
      AND MONTH(t.date) = ?
      AND YEAR(t.date)  = ?
      $type_condition
    ORDER BY t.date DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Set headers to trigger file download
$filename = "transactions_{$year}_{$month}.csv";
header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// CSV Header row
fputcsv($out, ['Date', 'Title', 'Category', 'Type', 'Amount (৳)', 'Note']);

// Data rows
foreach ($rows as $row) {
    fputcsv($out, [
        $row['date'],
        $row['title'],
        $row['category'],
        ucfirst($row['type']),
        number_format($row['amount'], 2),
        $row['note'] ?? ''
    ]);
}

fclose($out);
exit();