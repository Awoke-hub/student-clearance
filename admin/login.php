<?php
session_start();
include '../includes/db.php'; 

$error = "";

// Handle login
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Select admin by username
    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        // Verify password
        if (password_verify($password, $admin['password'])) {
            // ✅ Save sessions for all admin types
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['name'];           // ✅ Save first name
            $_SESSION['admin_last_name'] = $admin['last_name']; // ✅ Save last name
            $_SESSION['admin_role'] = strtolower($admin['role']); // store in lowercase

            // ✅ Redirect based on role
            switch ($_SESSION['admin_role']) {
                case 'library_admin':
                    header("Location: library/librarian_dashboard.php");
                    break;
                case 'cafeteria_admin':
                    header("Location: cafeteria/cafeteria_dashboard.php");
                    break;
                case 'department_admin':
                    header("Location: department/department_dashbord.php");
                    break;
                case 'registrar_admin':
                    header("Location: registerar/registerar_dashboard.php");
                    break;
                case 'dormitory_admin':
                    header("Location: dormitory/dormitory_dashbord.php");
                    break;
                case 'system_admin': 
                    header("Location: system_admin_panel.php");
                    break;
                case 'personal_protector': 
                    header("Location: personal_protector.php");
                    break;
                default:
                    $error = "Invalid role assigned!";
            }
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - Debre Berhan University</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --primary: #2E8B57;
        --primary-dark: #276749;
        --secondary: #48BB78;
        --accent: #38A169;
        --light: #F7FAFC;
        --dark: #2D3748;
        --gray: #718096;
        --error: #E53E3E;
        --success: #38A169;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
        position: relative;
        overflow: hidden;
    }

    body::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(1deg); }
    }

    .login-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 50px 40px;
        width: 100%;
        max-width: 420px;
        border-radius: 20px;
        box-shadow: 
            0 20px 40px rgba(0, 0, 0, 0.1),
            0 0 0 1px rgba(255, 255, 255, 0.2);
        text-align: center;
        animation: fadeInUp 0.8s ease-out;
        position: relative;
        z-index: 1;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .university-logo {
        margin-bottom: 30px;
        animation: slideDown 0.8s ease-out;
    }

    .university-logo i {
        font-size: 3.5rem;
        color: var(--primary);
        margin-bottom: 15px;
        display: block;
        text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .university-logo h1 {
        font-size: 1.4rem;
        color: var(--dark);
        font-weight: 600;
        margin-bottom: 5px;
    }

    .university-logo p {
        font-size: 0.9rem;
        color: var(--gray);
        font-weight: 400;
    }

    .login-header {
        margin-bottom: 35px;
        animation: slideDown 0.8s ease-out 0.2s both;
    }

    .login-header h2 {
        font-size: 1.8rem;
        color: var(--dark);
        font-weight: 600;
        margin-bottom: 8px;
    }

    .login-header p {
        color: var(--gray);
        font-size: 0.95rem;
    }

    .input-group {
        position: relative;
        margin-bottom: 25px;
        animation: fadeIn 0.8s ease-out 0.4s both;
    }

    .input-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray);
        font-size: 1.1rem;
        transition: all 0.3s ease;
        z-index: 2;
    }

    .input-group input {
        width: 100%;
        padding: 15px 15px 15px 45px;
        border: 2px solid #E2E8F0;
        border-radius: 12px;
        outline: none;
        transition: all 0.3s ease;
        font-size: 15px;
        background: var(--light);
        color: var(--dark);
        font-weight: 500;
    }

    .input-group input::placeholder {
        color: #A0AEC0;
        font-weight: 400;
    }

    .input-group input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
        transform: translateY(-2px);
    }

    .input-group input:focus + i {
        color: var(--primary);
        transform: translateY(-50%) scale(1.1);
    }

    .login-btn {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border: none;
        color: white;
        font-size: 16px;
        font-weight: 600;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
        animation: fadeIn 0.8s ease-out 0.6s both;
        position: relative;
        overflow: hidden;
    }

    .login-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    .login-btn:hover {
        transform: translateY(-3px);
        box-shadow: 
            0 10px 25px rgba(46, 139, 87, 0.3),
            0 0 0 1px rgba(255, 255, 255, 0.1);
    }

    .login-btn:hover::before {
        left: 100%;
    }

    .login-btn:active {
        transform: translateY(-1px);
    }

    .login-btn i {
        margin-right: 8px;
        font-size: 0.9em;
    }

    .error-message {
        background: linear-gradient(135deg, #FED7D7 0%, #FEB2B2 100%);
        color: var(--error);
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 25px;
        font-size: 14px;
        font-weight: 500;
        border-left: 4px solid var(--error);
        animation: shake 0.5s ease-in-out, fadeIn 0.3s ease-out;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(229, 62, 62, 0.1);
    }

    .error-message i {
        font-size: 1.1rem;
    }

    .security-notice {
        margin-top: 30px;
        padding: 15px;
        background: rgba(46, 139, 87, 0.05);
        border-radius: 12px;
        border: 1px solid rgba(46, 139, 87, 0.1);
        animation: fadeIn 0.8s ease-out 0.8s both;
    }

    .security-notice p {
        color: var(--gray);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .security-notice i {
        color: var(--success);
        font-size: 1rem;
    }

    /* Animations */
    @keyframes fadeInUp {
        0% { 
            opacity: 0; 
            transform: translateY(30px); 
        }
        100% { 
            opacity: 1; 
            transform: translateY(0); 
        }
    }

    @keyframes slideDown {
        0% { 
            opacity: 0; 
            transform: translateY(-20px); 
        }
        100% { 
            opacity: 1; 
            transform: translateY(0); 
        }
    }

    @keyframes fadeIn {
        0% { opacity: 0; }
        100% { opacity: 1; }
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    /* Floating particles */
    .particles {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: 0;
    }

    .particle {
        position: absolute;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        animation: float-particle 15s infinite linear;
    }

    @keyframes float-particle {
        0% { transform: translateY(100vh) rotate(0deg); }
        100% { transform: translateY(-100px) rotate(360deg); }
    }

    /* Responsive Design */
    @media (max-width: 480px) {
        .login-container {
            padding: 40px 25px;
            margin: 20px;
        }

        .university-logo i {
            font-size: 3rem;
        }

        .university-logo h1 {
            font-size: 1.2rem;
        }

        .login-header h2 {
            font-size: 1.6rem;
        }

        .input-group input {
            padding: 14px 14px 14px 42px;
        }
    }

    @media (max-width: 360px) {
        .login-container {
            padding: 30px 20px;
        }

        .university-logo i {
            font-size: 2.5rem;
        }

        .login-header h2 {
            font-size: 1.4rem;
        }
    }
</style>
</head>
<body>

<!-- Floating Particles -->
<div class="particles" id="particles"></div>

<div class="login-container">
    <div class="university-logo">
        <i class="fas fa-graduation-cap"></i>
        <h1>Debre Berhan University</h1>
        <p>Administrative System</p>
    </div>

    <div class="login-header">
        <h2>Admin Login</h2>
        <p>Access your administrative dashboard</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="username" placeholder="Enter Username" required>
        </div>

        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Enter Password" required>
        </div>

        <button type="submit" name="login" class="login-btn">
            <i class="fas fa-sign-in-alt"></i> Login to Dashboard
        </button>
    </form>

    <div class="security-notice">
        <p>
            <i class="fas fa-shield-alt"></i>
            Secure administrative access only
        </p>
    </div>
</div>

<script>
    // Create floating particles
    document.addEventListener('DOMContentLoaded', function() {
        const particlesContainer = document.getElementById('particles');
        const particleCount = 15;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            
            // Random properties
            const size = Math.random() * 6 + 2;
            const left = Math.random() * 100;
            const animationDuration = Math.random() * 20 + 10;
            const animationDelay = Math.random() * 5;
            const opacity = Math.random() * 0.4 + 0.1;
            
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            particle.style.left = `${left}%`;
            particle.style.animationDuration = `${animationDuration}s`;
            particle.style.animationDelay = `${animationDelay}s`;
            particle.style.opacity = opacity;
            
            particlesContainer.appendChild(particle);
        }

        // Add input focus effects
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    });
</script>

</body>
</html>