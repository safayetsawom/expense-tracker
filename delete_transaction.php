<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$id   = (int)($_GET['id'] ?? 0);

if ($id) {
    // Only delete if it belongs to this user
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
}

header("Location: transactions.php");
exit();