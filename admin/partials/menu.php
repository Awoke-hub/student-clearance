<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure only system admins can access
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'system_admin') {
    header("Location: login.php");
    exit();
}

// Full Name from Session
$full_name = htmlspecialchars($_SESSION['admin_name'] . ' ' . $_SESSION['admin_last_name']);
?>

<style>
    :root {
        --primary-color: #0b105aff;
        --secondary-color: #008B8B;
        --error-color: #ff4444;
        --error-hover: #cc0000;
        --text-light: rgba(255,255,255,0.9);
        --hover-bg: rgba(255,255,255,0.1);
        --shadow: 0 2px 15px rgba(0,0,0,0.2);
        --transition: all 0.3s ease;
    }
    
    .navbar {
        background-color: var(--primary-color);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        padding: 0 clamp(20px, 5vw, 40px);
        height: 80px;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: var(--shadow);
    }
    
    .navbar .logo img {
        height: 60px;
        width: auto;
        margin-right: clamp(15px, 3vw, 30px);
        transition: transform 0.3s ease;
    }
    
    .navbar .logo img:hover {
        transform: scale(1.05);
    }
    
    .mobile-menu-btn {
        display: none;
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        margin-left: auto;
        padding: 10px;
    }
    
    .nav-container {
        display: flex;
        align-items: center;
        flex-grow: 1;
    }
    
    .nav-links {
        display: flex;
        align-items: center;
        gap: clamp(15px, 2vw, 30px);
        margin-right: auto;
    }
    
    .navbar a {
        text-decoration: none;
        color: white;
        font-weight: 500;
        font-size: clamp(16px, 1.1vw, 18px);
        padding: 10px 15px;
        border-radius: 5px;
        transition: var(--transition);
        white-space: nowrap;
    }
    
    .navbar a:hover {
        background-color: var(--hover-bg);
        color: #fff;
    }
    
    .navbar a.active {
        background-color: var(--secondary-color);
    }
    
    .user-section {
        display: flex;
        align-items: center;
        gap: clamp(10px, 1.5vw, 20px);
        margin-left: auto;
    }
    
    .navbar .welcome-text {
        font-weight: 500;
        color: var(--text-light);
        font-size: clamp(14px, 1vw, 16px);
        white-space: nowrap;
    }
    
    .navbar .logout-btn {
        background-color: var(--error-color);
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        font-weight: 500;
        font-size: clamp(14px, 1vw, 16px);
        transition: var(--transition);
        white-space: nowrap;
    }
    
    .navbar .logout-btn:hover {
        background-color: var(--error-hover);
        text-decoration: none;
        transform: translateY(-2px);
    }

    /* Responsive Styles */
    @media (max-width: 992px) {
        .nav-links {
            gap: 10px;
        }
        
        .navbar a {
            padding: 10px 12px;
        }
    }
    
    @media (max-width: 768px) {
        .navbar {
            height: auto;
            padding: 15px;
        }
        
        .mobile-menu-btn {
            display: block;
        }
        
        .nav-container {
            width: 100%;
            display: none;
            flex-direction: column;
            align-items: flex-start;
            margin-top: 15px;
        }
        
        .nav-container.active {
            display: flex;
        }
        
        .nav-links {
            flex-direction: column;
            width: 100%;
            gap: 5px;
            margin: 10px 0;
        }
        
        .navbar a {
            width: 100%;
            padding: 12px 15px;
            border-radius: 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .user-section {
            width: 100%;
            justify-content: space-between;
            margin: 15px 0 0;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
    }
</style>

<div class="navbar">
    <!-- Logo -->
    <div class="logo">
        <a href="system_admin_panel.php" aria-label="Dashboard">
            <img src="../images/logo.png" alt="University Logo" width="60" height="60">
        </a>
    </div>
    
    <button class="mobile-menu-btn" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="nav-container" id="navContainer">
        <div class="nav-links">
            <a href="system_admin_panel.php" class="<?= basename($_SERVER['PHP_SELF']) == 'system_admin_panel.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage-students.php" class="<?= basename($_SERVER['PHP_SELF']) == 'manage-students.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Manage Students
            </a>
            <a href="manage-admins.php" class="<?= basename($_SERVER['PHP_SELF']) == 'manage-admins.php' ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i> Manage Admins
            </a>
        </div>
        
        <div class="user-section">
            <span class="welcome-text">
                <i class="fas fa-user-circle"></i> Welcome, <?= $full_name; ?>
            </span>
            <a class="logout-btn" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</div>

<script>
    // Mobile menu toggle functionality
    document.getElementById('menuToggle').addEventListener('click', function() {
        const navContainer = document.getElementById('navContainer');
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        
        navContainer.classList.toggle('active');
        this.setAttribute('aria-expanded', !isExpanded);
        
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    });
</script>