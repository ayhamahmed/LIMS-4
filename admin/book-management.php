<?php
// Start session at the very beginning of the file
session_start();

// At the top of the file, after session_start()
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin/admin-login.php');
    exit();
}

// Get admin name from session
$adminFirstName = $_SESSION['admin_first_name'] ?? 'Admin';
$adminLastName = $_SESSION['admin_last_name'] ?? '';

// Include the database connection
$pdo = require '../database/db_connection.php';

// Fetch books from database outside of the HTML
try {
    // Fetch books with their categories and status
    $stmt = $pdo->query('
        SELECT b.*, c.category_name,
            (SELECT COUNT(*) FROM borrowed_books bb 
             WHERE bb.book_id = b.book_id AND bb.return_date IS NULL) as is_borrowed
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.category_id
        ORDER BY b.book_id DESC
    ');
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch categories for the filter dropdown
    $categories = $pdo->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $books = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Book Management - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <style>
        .book-management-container {
            padding: 20px;
            background: #FEF3E8;
        }

        .controls-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            color: #B07154;
            font-size: 24px;
            font-weight: 600;
        }

        .controls-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 10px;
        }

        select.filter-select {
            padding: 10px 15px;
            border: 2px solid #B07154;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            color: #495057;
            background: white;
            cursor: pointer;
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

        .books-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .books-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .books-table th {
            background: #F4DECB;
            color: #B07154;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            font-size: 14px;
        }

        .books-table td {
            padding: 15px;
            border-bottom: 1px solid #F4DECB;
            color: #495057;
            font-size: 14px;
        }

        .books-table tr:last-child td {
            border-bottom: none;
        }

        .book-cover {
            width: 60px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }

        .book-title {
            font-weight: 600;
            color: #B07154;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-available {
            background: #DEF7EC;
            color: #03543F;
        }

        .status-borrowed {
            background: #FEF3C7;
            color: #92400E;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
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

        .mobile-card {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .mobile-card-header {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .mobile-card-info {
            flex: 1;
        }

        .mobile-card-actions {
            margin-top: 15px;
        }

        @media (max-width: 1024px) {
            .controls-header {
                flex-direction: column;
                align-items: stretch;
            }

            .controls-container {
                flex-direction: column;
            }

            .filter-group {
                flex-wrap: wrap;
            }

            .search-input {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .books-table {
                display: none;
            }

            .mobile-card {
                display: block;
            }

            .page-title {
                font-size: 20px;
            }

            select.filter-select {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .book-management-container {
                padding: 15px;
            }

            .mobile-card {
                padding: 12px;
            }

            .action-btn {
                padding: 5px 10px;
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
            <a href="../admin/catalog.php" class="nav-item">
                <div class="icon">
                    <img src="../images/Vector.svg" alt="Catalog" width="20" height="20">
                </div>
                <div class="text">Catalog</div>
            </a>
            <a href="../admin/book-management.php" class="nav-item active">
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
        <a href="../admin/admin-logout.php" class="nav-item logout">
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
                    <span class="admin-name-1">Welcome, <?= htmlspecialchars($adminFirstName . ' ' . $adminLastName) ?></span>
                </div>
            </div>
        </div>
        <div class="book-management-container">
            <div class="controls-header">
                <h1 class="page-title">Book Management</h1>
                <div class="controls-container">
                    <div class="filter-group">
                        <select class="filter-select" id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['category_name']) ?>">
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="borrowed">Borrowed</option>
                        </select>
                    </div>
                    <div class="search-box">
                        <input type="text" class="search-input" id="searchInput" placeholder="Search books...">
                    </div>
                    <button class="add-book-btn" onclick="location.href='add-book.php'">Add New Book</button>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="books-table">
                <table>
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($book['cover_image_url'] ?? '../images/default-book-cover.jpg') ?>" 
                                         alt="<?= htmlspecialchars($book['title']) ?>" 
                                         class="book-cover">
                                </td>
                                <td class="book-title"><?= htmlspecialchars($book['title']) ?></td>
                                <td><?= htmlspecialchars($book['author']) ?></td>
                                <td><?= htmlspecialchars($book['category_name'] ?? 'Uncategorized') ?></td>
                                <td>
                                    <span class="status-badge <?= $book['is_borrowed'] ? 'status-borrowed' : 'status-available' ?>">
                                        <?= $book['is_borrowed'] ? 'Borrowed' : 'Available' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn edit-btn" 
                                                onclick="location.href='edit-book.php?id=<?= $book['book_id'] ?>'">
                                            Edit
                                        </button>
                                        <button class="action-btn delete-btn" 
                                                onclick="confirmDelete(<?= $book['book_id'] ?>)">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <?php foreach ($books as $book): ?>
                <div class="mobile-card">
                    <div class="mobile-card-header">
                        <img src="<?= htmlspecialchars($book['cover_image_url'] ?? '../images/default-book-cover.jpg') ?>" 
                             alt="<?= htmlspecialchars($book['title']) ?>" 
                             class="book-cover">
                        <div class="mobile-card-info">
                            <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
                            <div><?= htmlspecialchars($book['author']) ?></div>
                            <div><?= htmlspecialchars($book['category_name'] ?? 'Uncategorized') ?></div>
                            <span class="status-badge <?= $book['is_borrowed'] ? 'status-borrowed' : 'status-available' ?>">
                                <?= $book['is_borrowed'] ? 'Borrowed' : 'Available' ?>
                            </span>
                        </div>
                    </div>
                    <div class="mobile-card-actions">
                        <div class="action-buttons">
                            <button class="action-btn edit-btn" 
                                    onclick="location.href='edit-book.php?id=<?= $book['book_id'] ?>'">
                                Edit
                            </button>
                            <button class="action-btn delete-btn" 
                                    onclick="confirmDelete(<?= $book['book_id'] ?>)">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Mobile menu functionality
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

            // Search and filter functionality
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const statusFilter = document.getElementById('statusFilter');
            const bookElements = document.querySelectorAll('.books-table tr:not(:first-child), .mobile-card');

            function filterBooks() {
                const searchTerm = searchInput.value.toLowerCase();
                const categoryValue = categoryFilter.value.toLowerCase();
                const statusValue = statusFilter.value.toLowerCase();

                bookElements.forEach(element => {
                    const isTableRow = element.tagName === 'TR';
                    const title = isTableRow 
                        ? element.querySelector('.book-title').textContent.toLowerCase()
                        : element.querySelector('.mobile-card-info .book-title').textContent.toLowerCase();
                    const category = isTableRow
                        ? element.children[3].textContent.toLowerCase()
                        : element.querySelector('.mobile-card-info div:nth-child(3)').textContent.toLowerCase();
                    const status = isTableRow
                        ? element.querySelector('.status-badge').textContent.toLowerCase()
                        : element.querySelector('.status-badge').textContent.toLowerCase();

                    const matchesSearch = title.includes(searchTerm);
                    const matchesCategory = !categoryValue || category === categoryValue;
                    const matchesStatus = !statusValue || status === statusValue;

                    element.style.display = 
                        matchesSearch && matchesCategory && matchesStatus ? '' : 'none';
                });
            }

            searchInput.addEventListener('input', filterBooks);
            categoryFilter.addEventListener('change', filterBooks);
            statusFilter.addEventListener('change', filterBooks);
        });

        function confirmDelete(bookId) {
            if (confirm('Are you sure you want to delete this book?')) {
                location.href = `delete-book.php?id=${bookId}`;
            }
        }
    </script>
</body>

</html>