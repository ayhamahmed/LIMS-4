<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = require '../database/db_connection.php';

try {
    $branchId = $_POST['branch_id'] ?? null;
    $branchName = $_POST['branch_name'] ?? '';
    $branchLocation = $_POST['branch_location'] ?? '';

    if (!$branchId || !$branchName || !$branchLocation) {
        throw new Exception('All fields are required');
    }

    $stmt = $pdo->prepare("UPDATE branches SET branch_name = ?, branch_location = ? WHERE branch_id = ?");
    $stmt->execute([$branchName, $branchLocation, $branchId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}