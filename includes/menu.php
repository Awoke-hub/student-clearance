<?php
// Start session with output buffering to prevent header errors
session_start();
ob_start();
$current_page = basename($_SERVER['PHP_SELF']);

// Check if student is in final_clearance table (for notification badge)
$has_final_approval = false;
$unread_count = 0;

if (isset($_SESSION['student_id'])) {
    include 'includes/db.php'; // Make sure to include your database connection
    
    $student_id = $_SESSION['student_id'];
    
    // Check for final approval
    $check_final_stmt = $conn->prepare("SELECT COUNT(*) as count FROM final_clearance WHERE student_id = ? AND status = 'approved'");
    $check_final_stmt->bind_param("s", $student_id);
    $check_final_stmt->execute();
    $final_result = $check_final_stmt->get_result();
    $final_data = $final_result->fetch_assoc();
    
    $has_final_approval = ($final_data['count'] > 0);
    
    // Check for unread notifications
    $check_unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM final_clearance WHERE student_id = ? AND is_read = 0");
    $check_unread_stmt->bind_param("s", $_SESSION['student_id']);
    $check_unread_stmt->execute();
    $unread_result = $check_unread_stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    
    $unread_count = $unread_data['count'];
    
    // Store in session for menu display
    $_SESSION['has_final_approval'] = $has_final_approval;
    $_SESSION['unread_notifications'] = $unread_count;
    
    // Refresh profile picture from database if not set in session
    if (!isset($_SESSION['profile_picture'])) {
        $profile_stmt = $conn->prepare("SELECT profile_picture FROM student WHERE student_id = ?");
        $profile_stmt->bind_param("s", $_SESSION['student_id']);
        $profile_stmt->execute();
        $profile_result = $profile_stmt->get_result();
        $profile_data = $profile_result->fetch_assoc();
        $_SESSION['profile_picture'] = $profile_data['profile_picture'];
        $profile_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Debre Berhan University Online Student Clearance System">
    <title>Debre Berhan University - Online Student Clearance</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <link rel="icon" href="images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --hover-color: #3498db;
            --text-color: white;
            --shadow: 0px 3px 8px rgba(0,0,0,0.3);
            --content-bg: #ecf0f1;
            --content-text: #2c3e50;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: var(--content-bg);
            color: var(--content-text);
            display: flex;
            min-height: 100vh;
        }

        /* Header Styles */
        header {
            background: var(--primary-color);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 80px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo img {
            height: 70px;
            transition: transform 0.3s ease;
        }

        .logo img:hover {
            transform: scale(1.05);
        }

        .university-text {
            color: var(--text-color);
            font-weight: bold;
            font-size: 18px;
            line-height: 1.2;
        }

        .university-text .main {
            display: block;
            font-size: 23px;
        }
        .nav-right {
            font-size: 14px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-right span {
            font-weight: bold;
        }

        .nav-right a {
            color: #00ffcc;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .nav-right a:hover {
            background: rgba(255,255,255,0.1);
            text-decoration: none;
        }

        /* User Account Dropdown */
        .user-account {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .account-dropdown {
            position: relative;
            display: inline-block;
        }

        .account-btn {
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 32px;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .account-btn:hover {
            background: rgba(255,255,255,0.1);
            transform: scale(1.05);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 180px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 6px;
            z-index: 1001;
            margin-top: 5px;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 16px;
            color: var(--content-text);
            text-decoration: none;
            border-bottom: 1px solid #f1f1f1;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .dropdown-menu a:hover {
            background: #f8f9fa;
            color: var(--hover-color);
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }
        /* Profile Picture Styles */
        .profile-picture {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
        }

        /* Vertical Navigation Styles */
        .vertical-nav {
            width: 280px;
            background: var(--secondary-color);
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-shadow: var(--shadow);
            z-index: 999;
            overflow-y: auto;
        }

        .nav-container {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav-left {
            flex: 1;
        }

        .nav-left > ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        .nav-left ul li {
            margin-bottom: 5px;
        }

        .nav-left ul li a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: bold;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            position: relative;
        }

        .nav-left ul li a i {
            margin-right: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }

        .nav-left ul li a:hover,
        .nav-left ul li.active a {
            background: var(--hover-color);
            color: var(--text-color);
            border-left-color: #00ffcc;
            padding-left: 30px;
        }

        /* Notification Badge Styles - Updated to Blue */
        .notification-badge {
            position: absolute;
            top: 12px;
            right: 20px;
            background: red; 
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            animation: pulse 2s infinite;
            box-shadow: 0 2px 4px rgba(52, 152, 219, 0.3); /* Blue shadow */
            z-index: 1;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Main Content Area */
        .main-content {
            margin-left: 280px;
            margin-top: 80px;
            flex: 1;
            padding: 20px;
            min-height: calc(100vh - 80px);
            background: var(--content-bg);
            width: calc(100vw - 280px);
        }

        /* Content Styles to Match Your Image */
        .content-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 2px solid #bdc3c7;
            padding-bottom: 10px;
        }

        h2 {
            color: #e74c3c;
            margin: 20px 0 15px 0;
            font-size: 18px;
        }

        .clearance-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .clearance-list li {
            padding: 12px 15px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-left: 4px solid #e74c3c;
            border-radius: 4px;
        }

        .user-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .user-info p {
            margin-bottom: 10px;
            font-size: 16px;
        }

        hr {
            border: none;
            border-top: 2px solid #bdc3c7;
            margin: 25px 0;
        }

        .clearance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .clearance-table th {
            background: var(--primary-color);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }

        .clearance-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ecf0f1;
        }

        .clearance-table tr:hover {
            background: #f8f9fa;
        }

        .status-not-cleared {
            color: #e74c3c;
            font-weight: bold;
        }

        .issues-cell {
            color: #e74c3c;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
            transition: transform 0.3s ease;
            order: 1;
            flex: 0 0 auto;
        }

        .mobile-menu-btn:focus {
            outline: 2px solid var(--hover-color);
        }

        /* Responsive Styles */
        @media (max-width: 1100px) {
            header {
                padding: 15px 20px;
            }
            .logo img {
                height: 60px;
            }
            .university-text {
                font-size: 16px;
            }
            .university-text .main {
                font-size: 15px;
            }
            .vertical-nav {
                width: 250px;
            }
            .main-content {
                margin-left: 250px;
                width: calc(100vw - 250px);
            }
        }

        @media (max-width: 900px) {
            .vertical-nav {
                width: 220px;
            }
            .main-content {
                margin-left: 220px;
                width: calc(100vw - 220px);
            }
            .nav-right {
                gap: 12px;
            }
            .university-text {
                font-size: 15px;
            }
            .university-text .main {
                font-size: 14px;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            header {
                position: fixed;
                width: 100%;
                padding: 12px 15px;
                height: 70px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .logo-container {
                order: 2;
                flex: 1;
                justify-content: flex-start;
                margin-left: 10px;
                min-width: 0;
            }
            
            .university-text {
                display: none;
            }
            
            .nav-right {
                order: 3;
                flex: 0 0 auto;
                gap: 8px;
            }
            
            .nav-right span {
                display: none;
            }
            
            .account-btn {
                font-size: 28px;
                width: 44px;
                height: 44px;
                padding: 8px;
            }
            
            .vertical-nav {
                position: fixed;
                top: 70px;
                left: -100%;
                width: 280px;
                height: calc(100vh - 70px);
                transition: left 0.3s ease;
                z-index: 998;
            }
            
            .vertical-nav.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                width: 100%;
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 10px 15px;
            }
            
            .logo img {
                height: 50px;
            }
            
            .vertical-nav {
                width: 100%;
                left: -100%;
            }
            
            .nav-right {
                gap: 8px;
            }
            
            .nav-right a {
                padding: 6px 8px;
                font-size: 13px;
            }
            
            .main-content {
                padding: 10px;
            }
        }

        /* Overlay for mobile menu */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 997;
        }
        
        .mobile-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
<header>
    <button class="mobile-menu-btn" id="menuToggle" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="logo-container">
        <div class="logo">
            <img src="images/logo.png" alt="Debre Berhan University Logo">
        </div>
        <div class="university-text">
            <span class="main">Debre Berhan University Online Student Clearance</span>
        </div>
    </div>
    
    <div class="nav-right">
        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-account">
                <span>Welcome <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                <div class="account-dropdown">
                    <button class="account-btn" id="accountBtn" aria-label="Account menu">
                        <?php if (!empty($_SESSION['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($_SESSION['profile_picture']) ?>?t=<?= time() ?>" 
                                 alt="Profile Picture" 
                                 class="profile-picture"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <i class="fas fa-user-circle" style="display: none; font-size: 32px;"></i>
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="profile.php">
                            <i class="fas fa-user" style="margin-right: 8px;"></i>My Profile
                        </a>
                        <a href="change-password.php">
                            <i class="fas fa-key" style="margin-right: 8px;"></i>Change Password
                        </a>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt" style="margin-right: 8px;"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <a class="<?= ($current_page == 'login.php') ? 'active' : '' ?>" href="login.php">LOGIN</a>
        <?php endif; ?>
    </div>
</header>

<!-- Vertical Navigation -->
<nav class="vertical-nav" id="verticalNav">
    <div class="nav-container">
        <div class="nav-left">
            <ul>
                <li class="<?= ($current_page == 'index.php') ? 'active' : '' ?>">
                    <a href="index.php">
                        <i class="fas fa-home"></i>HOME
                    </a>
                </li>

                <?php if (isset($_SESSION['username'])): ?>
                    <!-- Single Clearance Menu Item - No Submenu -->
                    <li class="<?= ($current_page == 'clearance.php') ? 'active' : '' ?>">
                        <a href="clearance.php">
                            <i class="fas fa-clipboard-check"></i>
                            CLEARANCE
                        </a>
                    </li>
                    
                    <li class="<?= ($current_page == 'my-requests.php') ? 'active' : '' ?>">
                        <a href="my-requests.php">
                            <i class="fas fa-list-alt"></i>MY REQUESTS
                        </a>
                    </li>
                    
                    <!-- Notifications with unread count badge -->
                    <li class="<?= ($current_page == 'notifications.php') ? 'active' : '' ?>">
                        <a href="notifications.php">
                            <i class="fas fa-bell"></i>NOTIFICATIONS
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge" title="<?= $unread_count ?> unread notification(s)"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="<?= ($current_page == 'contact_us.php') ? 'active' : '' ?>">
                        <a href="contact_us.php">
                            <i class="fas fa-envelope"></i>CONTACT
                        </a>
                    </li>
                    <li class="<?= ($current_page == 'about.php') ? 'active' : '' ?>">
                        <a href="about.php">
                            <i class="fas fa-info-circle"></i>ABOUT
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Mobile overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- Main Content Area will be populated by individual pages -->

<script>
    // Mobile menu toggle functionality
    document.getElementById('menuToggle').addEventListener('click', function() {
        const verticalNav = document.getElementById('verticalNav');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        
        verticalNav.classList.toggle('active');
        mobileOverlay.classList.toggle('active');
        this.setAttribute('aria-expanded', !isExpanded);
        
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    });

    // Account dropdown functionality
    document.getElementById('accountBtn')?.addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdownMenu = document.getElementById('dropdownMenu');
        dropdownMenu.classList.toggle('show');
    });

    // Close account dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdownMenu = document.getElementById('dropdownMenu');
        if (dropdownMenu && !e.target.closest('.account-dropdown')) {
            dropdownMenu.classList.remove('show');
        }
    });

    // Close mobile menu when clicking on overlay
    document.getElementById('mobileOverlay').addEventListener('click', function() {
        const verticalNav = document.getElementById('verticalNav');
        const menuToggle = document.getElementById('menuToggle');
        const icon = menuToggle.querySelector('i');
        
        verticalNav.classList.remove('active');
        this.classList.remove('active');
        menuToggle.setAttribute('aria-expanded', 'false');
        
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
    });

    // Close mobile menu when clicking on a menu item (for better UX)
    document.querySelectorAll('.nav-left ul li a').forEach(link => {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const verticalNav = document.getElementById('verticalNav');
                const mobileOverlay = document.getElementById('mobileOverlay');
                const menuToggle = document.getElementById('menuToggle');
                const icon = menuToggle.querySelector('i');
                
                verticalNav.classList.remove('active');
                mobileOverlay.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
                
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    });

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Menu loaded successfully');
    });
</script>