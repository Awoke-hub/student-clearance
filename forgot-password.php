<?php
include 'includes/db.php';
include 'includes/menu.php'; 

$message = "";

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM student WHERE name = ? AND email = ?");
    $stmt->bind_param("ss", $name, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['reset_student_id'] = $user['id'];
        header("Location: reset-password.php");
        exit();
    } else {
        $message = "<div class='message error-msg'>❌ No account found with that Name and Email.</div>";
    }
}
?>

<style>
    .main-content {
        margin-left: 300px; /* Moved closer to sidebar */
        margin-top: 80px;
        padding: 20px;
        min-height: calc(100vh - 80px);
        background: var(--content-bg);
        color: var(--content-text);
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .reset-container {
        max-width: 400px;
        margin: 20px 0; /* Removed auto margin to align with sidebar */
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        color: var(--content-text);
        width: 100%;
    }

    .reset-container h2 {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 20px;
        font-size: 24px;
        padding-bottom: 10px;
        border-bottom: 2px solid #bdc3c7;
    }

    .reset-container label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: var(--content-text);
    }

    .reset-container input {
        width: 100%;
        padding: 12px;
        margin: 8px 0 15px 0;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 15px;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    .reset-container input:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.2);
    }

    .reset-container button {
        width: 100%;
        padding: 14px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
    }

    .reset-container button:hover {
        background: var(--hover-color);
        transform: translateY(-2px);
    }

    .message {
        text-align: center;
        margin-bottom: 20px;
        padding: 15px;
        border-radius: 6px;
        font-weight: bold;
        border-left: 4px solid;
    }

    .error-msg {
        background-color: #f8d7da;
        color: #721c24;
        border-left-color: #dc3545;
    }

    .back-link {
        text-align: center;
        margin-top: 20px;
    }

    .back-link a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: bold;
        padding: 10px;
        border-radius: 4px;
        transition: all 0.3s ease;
        display: inline-block;
    }

    .back-link a:hover {
        color: var(--hover-color);
        background-color: #f8f9fa;
        text-decoration: none;
    }

    /* Responsive adjustments */
    @media (max-width: 1100px) {
        .main-content {
            margin-left: 270px;
            width: calc(100vw - 270px);
        }
    }

    @media (max-width: 900px) {
        .main-content {
            margin-left: 240px;
            width: calc(100vw - 240px);
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            margin-top: 70px;
            padding: 15px;
        }
        
        .reset-container {
            margin: 0 auto; /* Re-center on mobile */
            padding: 25px;
        }
        
        .reset-container h2 {
            font-size: 22px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .reset-container {
            padding: 20px;
            margin: 10px auto; /* Re-center on mobile */
        }
        
        .reset-container h2 {
            font-size: 20px;
        }
        
        .reset-container input {
            padding: 10px;
        }
        
        .reset-container button {
            padding: 12px;
        }
    }
</style>

<div class="main-content">
    <div class="reset-container">
        <h2>Forgot Password</h2>
        <?php echo $message; ?>
        <form method="POST">
            <label><strong>Name:</strong></label>
            <input type="text" name="name" placeholder="Enter your Name" required>

            <label><strong>Email:</strong></label>
            <input type="email" name="email" placeholder="Enter your Email" required>

            <button type="submit" name="submit">Verify</button>
            <div class="back-link">
                <a href="login.php">⬅ Back to Login</a>
            </div>
        </form>
    </div>
</div>