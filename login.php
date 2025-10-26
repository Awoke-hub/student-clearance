<?php
include 'includes/menu.php';
include 'includes/db.php';

if (isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM student WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['full_name'] = $user['name'] . " " . $user['last_name'];
            $_SESSION['profile_picture'] = $user['profile_picture'];

            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<style>
    /* Only adjust colors and positioning to match menu.php */
    body {
        background: var(--content-bg) !important;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    .main-content {
        margin-left: 280px;
        margin-top: 80px;
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 80px);
        flex: 1;
        width: calc(100vw - 280px);
    }

    .login-container {
        background-color: white;
        padding: 40px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 400px;
        text-align: center;
    }

    .login-container h2 {
        color: var(--primary-color);
        margin-bottom: 20px;
        border-bottom: 2px solid #bdc3c7;
        padding-bottom: 10px;
        font-size: 24px;
    }

    .login-container input[type="text"],
    .login-container input[type="password"] {
        width: 100%;
        padding: 12px;
        margin: 12px 0;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 16px;
        box-sizing: border-box;
        transition: border-color 0.3s ease;
    }

    .login-container input[type="text"]:focus,
    .login-container input[type="password"]:focus {
        border-color: var(--hover-color);
        outline: none;
        box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
    }

    .login-container button {
        width: 100%;
        padding: 12px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s ease;
        margin-top: 10px;
    }

    .login-container button:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .login-container .forgot {
        margin-top: 15px;
    }

    .login-container .forgot a {
        color: var(--primary-color);
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s ease;
    }

    .login-container .forgot a:hover {
        text-decoration: underline;
        color: var(--hover-color);
    }

    .login-container p.error {
        color: #e74c3c;
        font-weight: bold;
        background-color: #ffeaea;
        padding: 10px;
        border-radius: 4px;
        border-left: 4px solid #e74c3c;
        margin-bottom: 15px;
        font-size: 14px;
    }

    /* Responsive adjustments for login page */
    @media (max-width: 1100px) {
        .main-content {
            margin-left: 250px;
            width: calc(100vw - 250px);
        }
    }

    @media (max-width: 900px) {
        .main-content {
            margin-left: 220px;
            width: calc(100vw - 220px);
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            width: 100%;
            padding: 20px 15px;
            min-height: calc(100vh - 70px);
        }
        
        .login-container {
            padding: 30px 25px;
            margin: 0 auto;
            max-width: 350px;
        }
        
        .login-container h2 {
            font-size: 22px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 15px 10px;
        }
        
        .login-container {
            padding: 25px 20px;
            max-width: 100%;
        }
        
        .login-container h2 {
            font-size: 20px;
        }
        
        .login-container input[type="text"],
        .login-container input[type="password"] {
            padding: 10px;
            font-size: 15px;
        }
        
        .login-container button {
            padding: 10px;
            font-size: 15px;
        }
    }

    /* Animation for login form */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .login-container {
        animation: fadeIn 0.5s ease-out;
    }
</style>

<div class="main-content">
    <div class="login-container">
        <h2>Student Login</h2>
        <?php if (!empty($error)) { echo "<p class='error'>$error</p>"; } ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Enter Username" required autocomplete="username">
            <input type="password" name="password" placeholder="Enter Password" required autocomplete="current-password">
            <button type="submit" name="login">Login</button>
            <p class="forgot"><a href="forgot-password.php">Forgot Password?</a></p>
        </form>
    </div>
</div>