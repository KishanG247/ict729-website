<?php
session_start();
require 'config.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return $_SESSION;
    }
    return null;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: index.php");
        exit();
    }
}

function updateCustomerSegment($conn, $user_id) {
    $stmt = $conn->prepare("SELECT SUM(total_price) as total, COUNT(*) as count, MAX(purchase_date) as last_purchase FROM purchases WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $total = $data['total'] ?? 0;
    $count = $data['count'] ?? 0;
    
    $total = $data['total'] ?? 0;
    $count = $data['count'] ?? 0;
    
    if ($count >= 5 && $total >= 1000) {
        $segment = 'Premium Customer';
    } elseif ($count >= 3 && $total >= 500) {
        $segment = 'Regular Customer';
    } elseif ($count >= 1) {
        $segment = 'Active Customer';
    } else {
        $segment = 'New Customer';
    }
    
    $stmt = $conn->prepare("INSERT INTO customer_segments (user_id, segment_name, total_purchases, purchase_count, last_purchase_date) 
                  VALUES (?, ?, ?, ?, NOW())
                  ON DUPLICATE KEY UPDATE 
                  segment_name=?, total_purchases=?, purchase_count=?, last_purchase_date=NOW()");
    $stmt->bind_param("isdisdi", $user_id, $segment, $total, $count, $segment, $total, $count);
    $stmt->execute();
    $stmt->close();
    
    return $segment;
}

?>
