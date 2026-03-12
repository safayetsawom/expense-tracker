<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /expense-tracker/login.php");
        exit();
    }
}

function getCurrentUser() {
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['user_name']
    ];
}