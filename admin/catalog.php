<?php
// Start session at the very beginning of the file
session_start();

// At the top of the file, after session_start()
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

// Get admin name from session
$adminFirstName = $_SESSION['admin_first_name'] ?? 'Admin';
$adminLastName = $_SESSION['admin_last_name'] ?? '';
$adminName = $adminFirstName . ' ' . $adminLastName;

// Include the database connection
$pdo = require '../database/db_connection.php';

// Include the activity logger
require '../helpers/activity_logger.php';

// Replace the existing confirmation handling code
if (isset($_POST['confirm_return'])) {
    $log_id = $_POST['log_id'];
    $book_id = $_POST['book_id'];
    $user_id = $_POST['user_id'];

    try {
        $pdo->beginTransaction();

        // Update borrowed_books table
        $stmt = $pdo->prepare("
            UPDATE borrowed_books 
            SET return_date = CURRENT_TIMESTAMP 
            WHERE book_id = ? AND user_id = ? AND return_date IS NULL
        ");
        $stmt->execute([$book_id, $user_id]);

        // Update log status
        $stmt = $pdo->prepare("
            UPDATE activity_logs 
            SET status = 'completed' 
            WHERE log_id = ?
        ");
        $stmt->execute([$log_id]);

        $pdo->commit();

        // Set success message in session instead of using alert
        $_SESSION['return_success'] = true;
        header('Location: catalog.php');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error confirming return: " . $e->getMessage());
        $_SESSION['return_error'] = true;
        header('Location: ../catalog.php');
        exit();
    }
}

// Fetch logs from database outside of the HTML
try {
    $stmt = $pdo->query('
        SELECT 
            l.log_id,
            l.action_type,
            l.description,
            l.performed_by,
            l.timestamp,
            l.status,
            l.related_id,
            CASE 
                WHEN l.action_type IN ("RETURN_REQUEST", "BOOK_RETURN", "BORROW") 
                THEN (
                    SELECT user_id 
                    FROM borrowed_books 
                    WHERE book_id = l.related_id 
                    ORDER BY borrow_date DESC 
                    LIMIT 1
                )
                ELSE NULL
            END as user_id
        FROM activity_logs l
        ORDER BY l.timestamp DESC
        LIMIT 100
    ');
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching logs: " . $e->getMessage());
    $logs = [];
}

// Add this after your existing query
try {
    $debug_stmt = $pdo->query("
        SELECT * FROM activity_logs 
        WHERE action_type = 'RETURN_REQUEST' 
        AND status = 'pending' 
        LIMIT 1
    ");
    $debug_result = $debug_stmt->fetch(PDO::FETCH_ASSOC);
    if ($debug_result) {
        error_log("Found pending return request: " . print_r($debug_result, true));
    } else {
        error_log("No pending return requests found");
    }
} catch (PDOException $e) {
    error_log("Debug query error: " . $e->getMessage());
}

// Get total books count
try {
    $bookCount = $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
    
    // Fetch all books with their details
    $booksQuery = $pdo->query('
        SELECT b.*, c.category_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.category_id 
        ORDER BY b.book_id DESC
    ');
    $books = $booksQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching books: " . $e->getMessage());
    $bookCount = 0;
    $books = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Book Catalog - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <style>
        .catalog-container {
            padding: 20px;
            background: #FEF3E8;
        }

        .catalog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .catalog-title {
            color: #B07154;
            font-size: 24px;
            font-weight: 600;
        }

        .search-add-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
        }

        .search-input {
            width: 300px;
            padding: 10px 15px;
            border: 2px solid #B07154;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
        }

        .add-book-btn {
            padding: 10px 20px;
            background: #B07154;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .add-book-btn:hover {
            background: #8B5B43;
        }

        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .book-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .book-cover {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .book-title {
            color: #B07154;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .book-author {
            color: #8B5B43;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .book-category {
            display: inline-block;
            padding: 4px 8px;
            background: #F4DECB;
            color: #B07154;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .book-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 6px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .edit-btn {
            background: #F4DECB;
            color: #B07154;
        }

        .edit-btn:hover {
            background: #E4C4A9;
        }

        .delete-btn {
            background: #FFE5E5;
            color: #FF4D4D;
        }

        .delete-btn:hover {
            background: #FFD1D1;
        }

        @media (max-width: 768px) {
            .catalog-header {
                flex-direction: column;
                align-items: stretch;
            }

            .search-add-container {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .catalog-container {
                padding: 15px;
            }

            .catalog-title {
                font-size: 20px;
            }

            .book-card {
                padding: 15px;
            }

            .book-cover {
                height: 150px;
            }

            .book-title {
                font-size: 14px;
            }

            .book-author {
                font-size: 12px;
            }

            .action-btn {
                padding: 6px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
    <div class="mobile-menu-btn">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="sidebar">
        <div class="logo">
            <img src="../images/logo.png" alt="Book King Logo">
        </div>
        <div class="nav-group">
            <a href="../admin/admin-dashboard.php" class="nav-item">
                <div class="icon">
                    <img src="../images/element-2 2.svg" alt="Dashboard" width="24" height="24">
                </div>
                <div class="text">Dashboard</div>
            </a>
            <a href="../admin/catalog.php" class="nav-item active">
                <div class="icon">
                    <img src="../images/Vector.svg" alt="Catalog" width="20" height="20">
                </div>
                <div class="text">Catalog</div>
            </a>
            <a href="../admin/book-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/book.png" alt="Books" width="24" height="24">
                </div>
                <div class="text">Books</div>
            </a>
            <a href="../admin/user-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/people 3.png" alt="Users" width="24" height="24">
                </div>
                <div class="text">Users</div>
            </a>
            <a href="../admin/branch-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/buildings-2 1.png" alt="Branches" width="24" height="24">
                </div>
                <div class="text">Branches</div>
            </a>
            <a href="../admin/borrowers-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/user.png" alt="Borrowers" width="24" height="24">
                </div>
                <div class="text">Borrowers</div>
            </a>
        </div>
        <a href="../admin/admin-logout.php" class="nav-item">
            <div class="icon">
                <img src="../images/logout 3.png" alt="Log Out" width="24" height="24">
            </div>
            <div class="text">Log Out</div>
        </a>
    </div>
    <div class="content">
        <div class="header">
            <div class="admin-profile">
                <div class="admin-info">
                    <span class="admin-name-1">Welcome, <?= htmlspecialchars($adminFirstName) ?></span>
                </div>
            </div>
        </div>
        <div class="catalog-container">
            <div class="catalog-header">
                <h1 class="catalog-title">Book Catalog</h1>
                <div class="search-add-container">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search books...">
                    </div>
                    <button class="add-book-btn">Add New Book</button>
                </div>
            </div>
            <div class="books-grid">
                <?php foreach ($books as $book): ?>
                <div class="book-card">
                    <img src="<?= htmlspecialchars($book['cover_image_url'] ?? '../images/default-book-cover.jpg') ?>" 
                         alt="<?= htmlspecialchars($book['title']) ?>" 
                         class="book-cover">
                    <h3 class="book-title"><?= htmlspecialchars($book['title']) ?></h3>
                    <p class="book-author"><?= htmlspecialchars($book['author']) ?></p>
                    <span class="book-category"><?= htmlspecialchars($book['category_name'] ?? 'Uncategorized') ?></span>
                    <div class="book-actions">
                        <button class="action-btn edit-btn">Edit</button>
                        <button class="action-btn delete-btn">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div id="confirmationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2 style="color: #B07154;">Confirm Book Return</h2>
            <p style="color: #495057;">Are you sure you want to confirm this book return?</p>
            <form id="confirmReturnForm" method="POST">
                <input type="hidden" name="log_id" id="confirmLogId">
                <input type="hidden" name="book_id" id="confirmBookId">
                <input type="hidden" name="user_id" id="confirmUserId">
                <input type="hidden" name="confirm_return" value="1">
                <div class="button-group">
                    <button type="submit" class="confirm-btn" style="background-color: #22C55E !important;">Confirm Return</button>
                    <button type="button" class="cancel-btn" style="background-color: #EF4444 !important;" onclick="closeConfirmationModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            const body = document.body;

            // Create overlay element
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            body.appendChild(overlay);

            function toggleMenu() {
                mobileMenuBtn.classList.toggle('active');
                sidebar.classList.toggle('active');
                content.classList.toggle('sidebar-active');
                overlay.classList.toggle('active');
                body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }

            mobileMenuBtn.addEventListener('click', toggleMenu);
            overlay.addEventListener('click', toggleMenu);

            // Close menu when clicking a nav item on mobile
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                        toggleMenu();
                    }
                });
            });

            // Handle resize events
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (window.innerWidth > 768) {
                        mobileMenuBtn.classList.remove('active');
                        sidebar.classList.remove('active');
                        content.classList.remove('sidebar-active');
                        overlay.classList.remove('active');
                        body.style.overflow = '';
                    }
                }, 250);
            });

            // Search functionality
            const searchInput = document.querySelector('.search-input');
            const bookCards = document.querySelectorAll('.book-card');

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                
                bookCards.forEach(card => {
                    const title = card.querySelector('.book-title').textContent.toLowerCase();
                    const author = card.querySelector('.book-author').textContent.toLowerCase();
                    const category = card.querySelector('.book-category').textContent.toLowerCase();
                    
                    if (title.includes(searchTerm) || 
                        author.includes(searchTerm) || 
                        category.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>

</html>