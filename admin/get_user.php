<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = require '../database/db_connection.php';

try {
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }

    $stmt = $pdo->prepare("SELECT user_id, FirstName, LastName, email, username, contactNo FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    echo json_encode($user);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}