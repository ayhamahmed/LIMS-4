<?php
// Prevent any output before our JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../helpers/activity_logger.php';

// Set JSON content type header
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database connection
$pdo = require '../database/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $language = trim($_POST['language'] ?? '');

    // Validate input
    if (empty($title) || empty($type) || empty($author) || empty($language)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Insert new book
        $stmt = $pdo->prepare("
            INSERT INTO books (title, author, type, language, availability) 
            VALUES (:title, :author, :type, :language, 'Available')
        ");

        $stmt->execute([
            'title' => $title,
            'author' => $author,
            'type' => $type,
            'language' => $language
        ]);

        $bookId = $pdo->lastInsertId();

        // Log the activity
        logActivity(
            $pdo,
            'ADD',
            "Added new book: {$title}",
            $_SESSION['admin_first_name'] . ' ' . $_SESSION['admin_last_name'],
            $bookId
        );

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Book added successfully']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error adding book. Please try again.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("General error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
