<?php
include 'includes/db.php';
include 'includes/menu.php';

// Redirect if not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if (isset($_POST['change'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $student_id = $_SESSION['student_id'];

    // Validate password match
    if ($new_password !== $confirm_password) {
        $error = "❌ New password and confirm password do not match!";
    } else {
        // Get student data using student_id from session
        $stmt = $conn->prepare("SELECT id, student_id, password FROM student WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "❌ Student record not found!";
        } else {
            $user = $result->fetch_assoc();
            
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update using the correct ID column (id instead of student_id)
                $update = $conn->prepare("UPDATE student SET password = ? WHERE id = ?");
                $update->bind_param("si", $hashed_password, $user['id']);
                
                if ($update->execute()) {
                    $success = "✅ Password changed successfully!";
                } else {
                    $error = "❌ Error updating password: " . $conn->error;
                }
                $update->close();
            } else {
                $error = "❌ Current password is incorrect!";
            }
        }
        $stmt->close();
    }
}
?>

<style>
    .main-content {
        margin-left: 280px;
        margin-top: 80px;
        padding: 20px;
        min-height: calc(100vh - 80px);
        background: var(--content-bg);
        color: var(--content-text);
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .password-container {
        max-width: 450px;
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        width: 100%;
        margin-top: 20px;
    }

    .password-container h2 {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 25px;
        font-size: 24px;
        padding-bottom: 10px;
        border-bottom: 2px solid #bdc3c7;
    }

    .password-container input {
        width: 100%;
        padding: 12px 15px;
        margin: 10px 0 20px 0;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 15px;
        transition: all 0.3s;
        box-sizing: border-box;
    }

    .password-container input:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.2);
    }

    .password-container button {
        width: 100%;
        padding: 14px;
        background-color: var(--primary-color);
        color: white;
        font-size: 16px;
        font-weight: bold;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 10px;
    }

    .password-container button:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
    }

    .message {
        text-align: center;
        margin-bottom: 20px;
        padding: 12px;
        border-radius: 5px;
        font-weight: bold;
        border-left: 4px solid;
    }

    .success {
        background-color: #d4edda;
        color: #155724;
        border-left-color: #28a745;
    }

    .error {
        background-color: #f8d7da;
        color: #721c24;
        border-left-color: #dc3545;
    }

    .back-link {
        display: block;
        text-align: center;
        margin-top: 20px;
        text-decoration: none;
        color: var(--primary-color);
        font-weight: bold;
        transition: all 0.3s;
        padding: 10px;
        border-radius: 4px;
    }

    .back-link:hover {
        color: var(--hover-color);
        background-color: #f8f9fa;
        text-decoration: none;
    }

    /* Responsive adjustments */
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
            width: 100%;
            margin-top: 70px;
            padding: 15px;
        }
        
        .password-container {
            margin: 0 auto;
            padding: 25px;
        }
        
        .password-container h2 {
            font-size: 20px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .password-container {
            padding: 20px;
            margin: 10px auto;
        }
        
        .password-container h2 {
            font-size: 18px;
        }
        
        .password-container input {
            padding: 10px 12px;
        }
        
        .password-container button {
            padding: 12px;
        }
    }
</style>

<div class="main-content">
    <div class="password-container">
        <h2>Change Password</h2>

        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="password" name="current_password" placeholder="Current Password" required>
            <input type="password" name="new_password" placeholder="New Password" required minlength="8">
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required minlength="8">
            <button type="submit" name="change">Change Password</button>
        </form>

        <a class="back-link" href="index.php">⬅ Back to Dashboard</a>
    </div>
</div>