<?php

function getCategories($pdo, $user_id, $type = null) {
    if ($type) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? AND type = ? ORDER BY name");
        $stmt->execute([$user_id, $type]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY type, name");
        $stmt->execute([$user_id]);
    }
    return $stmt->fetchAll();
}

function getMonthlyStats($pdo, $user_id, $month = null, $year = null) {
    $month = $month ?? date('m');
    $year  = $year  ?? date('Y');

    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense
        FROM transactions
        WHERE user_id = ?
          AND MONTH(date) = ?
          AND YEAR(date)  = ?
    ");
    $stmt->execute([$user_id, $month, $year]);
    $row = $stmt->fetch();

    $income  = (float)($row['total_income']  ?? 0);
    $expense = (float)($row['total_expense'] ?? 0);

    return [
        'income'  => $income,
        'expense' => $expense,
        'balance' => $income - $expense
    ];
}

function getRecentTransactions($pdo, $user_id, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name AS category_name
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ?
        ORDER BY t.date DESC, t.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit,   PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function formatMoney($amount) {
    return '৳ ' . number_format((float)$amount, 2);
}