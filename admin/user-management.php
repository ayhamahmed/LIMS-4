<?php
// Start session at the beginning of the file
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin/admin-login.php');
    exit();
}

// Get admin name from session
$adminFirstName = $_SESSION['admin_first_name'] ?? 'Admin';
$adminLastName = $_SESSION['admin_last_name'] ?? '';

// Include the database connection
$pdo = require '../database/db_connection.php';

// Fetch users with their borrowing stats
try {
    $stmt = $pdo->query('
        SELECT u.*, 
            COUNT(DISTINCT bb.borrow_id) as total_borrows,
            SUM(CASE WHEN bb.return_date IS NULL THEN 1 ELSE 0 END) as current_borrows
        FROM users u
        LEFT JOIN borrowed_books bb ON u.user_id = bb.user_id
        GROUP BY u.user_id
        ORDER BY u.user_id DESC
    ');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>User Management - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <style>
        .user-management-container {
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

        .add-user-btn {
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

        .add-user-btn:hover {
            background: #8B5B43;
        }

        .users-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #F4DECB;
            color: #B07154;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            font-size: 14px;
        }

        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #F4DECB;
            color: #495057;
            font-size: 14px;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .user-name {
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

        .status-active {
            background: #DEF7EC;
            color: #03543F;
        }

        .status-inactive {
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
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .mobile-card-info {
            flex: 1;
        }

        .mobile-card-stats {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            font-size: 13px;
        }

        .stat-item {
            background: #F4DECB;
            padding: 4px 8px;
            border-radius: 4px;
            color: #B07154;
        }

        .mobile-card-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        @media (max-width: 1024px) {
            .controls-header {
                flex-direction: column;
                align-items: stretch;
            }

            .controls-container {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .users-table {
                display: none;
            }

            .mobile-card {
                display: block;
            }

            .page-title {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .user-management-container {
                padding: 15px;
            }

            .mobile-card {
                padding: 12px;
            }

            .mobile-card-stats {
                flex-direction: column;
                gap: 8px;
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
            <a href="../admin/book-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/book.png" alt="Books" width="24" height="24">
                </div>
                <div class="text">Books</div>
            </a>
            <a href="../admin/user-management.php" class="nav-item active">
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
        <div class="user-management-container">
            <div class="controls-header">
                <h1 class="page-title">User Management</h1>
                <div class="controls-container">
                    <div class="search-box">
                        <input type="text" class="search-input" id="searchInput" placeholder="Search users...">
                    </div>
                    <button class="add-user-btn" onclick="location.href='add_user.php'">Add New User</button>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Total Borrows</th>
                            <th>Current Borrows</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="user-name">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><?= $user['total_borrows'] ?></td>
                                <td><?= $user['current_borrows'] ?></td>
                                <td>
                                    <span class="status-badge <?= $user['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn edit-btn" 
                                                onclick="location.href='update_user.php?id=<?= $user['user_id'] ?>'">
                                            Edit
                                        </button>
                                        <button class="action-btn delete-btn" 
                                                onclick="confirmDelete(<?= $user['user_id'] ?>)">
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
            <?php foreach ($users as $user): ?>
                <div class="mobile-card">
                    <div class="mobile-card-header">
                        <div class="mobile-card-info">
                            <div class="user-name">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                            </div>
                            <div><?= htmlspecialchars($user['email']) ?></div>
                            <div><?= htmlspecialchars($user['phone']) ?></div>
                        </div>
                        <span class="status-badge <?= $user['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <div class="mobile-card-stats">
                        <div class="stat-item">
                            Total Borrows: <?= $user['total_borrows'] ?>
                        </div>
                        <div class="stat-item">
                            Current Borrows: <?= $user['current_borrows'] ?>
                        </div>
                    </div>
                    <div class="mobile-card-actions">
                        <button class="action-btn edit-btn" 
                                onclick="location.href='update_user.php?id=<?= $user['user_id'] ?>'">
                            Edit
                        </button>
                        <button class="action-btn delete-btn" 
                                onclick="confirmDelete(<?= $user['user_id'] ?>)">
                            Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
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
            const searchInput = document.getElementById('searchInput');
            const userElements = document.querySelectorAll('.users-table tr:not(:first-child), .mobile-card');

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                
                userElements.forEach(element => {
                    const isTableRow = element.tagName === 'TR';
                    const name = isTableRow 
                        ? element.querySelector('.user-name').textContent.toLowerCase()
                        : element.querySelector('.mobile-card-info .user-name').textContent.toLowerCase();
                    const email = isTableRow
                        ? element.children[1].textContent.toLowerCase()
                        : element.querySelector('.mobile-card-info div:nth-child(2)').textContent.toLowerCase();
                    const phone = isTableRow
                        ? element.children[2].textContent.toLowerCase()
                        : element.querySelector('.mobile-card-info div:nth-child(3)').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || 
                        email.includes(searchTerm) || 
                        phone.includes(searchTerm)) {
                        element.style.display = '';
                    } else {
                        element.style.display = 'none';
                    }
                });
            });
        });

        function confirmDelete(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                location.href = `delete_user.php?id=${userId}`;
            }
        }
    </script>
</body>

</html>