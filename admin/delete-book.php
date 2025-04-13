<?php
// Prevent any output before our JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session and include required files
session_start();
require_once '../helpers/activity_logger.php';

// Set JSON content type header
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $pdo = require '../database/db_connection.php';

    // Get JSON data from request body
    $json = file_get_contents('php://input');
    if ($json === false) {
        throw new Exception('Failed to read request data');
    }

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    if (!isset($data['book_id'])) {
        throw new Exception('Book ID is required');
    }

    $book_id = filter_var($data['book_id'], FILTER_VALIDATE_INT);
    if ($book_id === false) {
        throw new Exception('Invalid book ID');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Get book title for logging
    $stmt = $pdo->prepare("SELECT title FROM books WHERE book_id = ?");
    $stmt->execute([$book_id]);
    $bookTitle = $stmt->fetchColumn();

    if (!$bookTitle) {
        throw new Exception('Book not found');
    }

    // Delete the book
    $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = ?");
    $result = $stmt->execute([$book_id]);

    if ($result) {
        // Log the activity
        logActivity(
            $pdo,
            'DELETE',
            "Deleted book: {$bookTitle}",
            $_SESSION['admin_first_name'] . ' ' . $_SESSION['admin_last_name'],
            $book_id
        );

        $pdo->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete book');
    }
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting book: ' . $e->getMessage()
    ]);
}
