<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = require '../database/db_connection.php';

try {
    $userId = $_POST['user_id'] ?? null;
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    $contactNo = $_POST['contactNo'] ?? '';

    // Validate required fields
    if (!$userId || !$firstName || !$lastName || !$email || !$username || !$contactNo) {
        throw new Exception('All fields are required');
    }

    // Check if email already exists for other users
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }

    // Check if username already exists for other users
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $stmt->execute([$username, $userId]);
    if ($stmt->fetch()) {
        throw new Exception('Username already exists');
    }

    // Update user
    $stmt = $pdo->prepare("UPDATE users SET FirstName = ?, LastName = ?, email = ?, username = ?, contactNo = ? WHERE user_id = ?");
    $stmt->execute([$firstName, $lastName, $email, $username, $contactNo, $userId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
