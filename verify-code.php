<?php
include 'includes/db.php';
include 'includes/menu.php';

// Add PHPMailer at the top level
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Redirect if not coming from forgot password
if (!isset($_SESSION['verification_code']) || !isset($_SESSION['reset_student_id'])) {
    header("Location: forgot-password.php");
    exit();
}

// Check if verification code has expired
if (time() > $_SESSION['verification_expiry']) {
    session_destroy();
    header("Location: forgot-password.php?error=Code expired");
    exit();
}

$message = "";

if (isset($_POST['verify'])) {
    $entered_code = trim($_POST['verification_code']);
    
    if ($entered_code === $_SESSION['verification_code']) {
        // Code is correct, redirect to reset password
        header("Location: reset-password.php");
        exit();
    } else {
        $message = "<div class='message error-msg'>❌ Invalid verification code. Please try again.</div>";
    }
}

// Resend code functionality
if (isset($_POST['resend'])) {
    $mail = new PHPMailer(true);

    try {
        // Get user info
        $stmt = $conn->prepare("SELECT name, email FROM student WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['reset_student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Generate new code
        $new_verification_code = sprintf("%06d", mt_rand(1, 999999));
        $_SESSION['verification_code'] = $new_verification_code;
        $_SESSION['verification_expiry'] = time() + 600; // 10 minutes

        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tomasderese49@gmail.com';
        $mail->Password   = 'njcv gmam lsda ejlf';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0;

        // Recipients
        $mail->setFrom('tomasderese49@gmail.com', 'DBU Clearance System');
        $mail->addAddress($user['email'], $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Password Reset Verification Code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #2c3e50;'>New Verification Code</h2>
                <p>Dear <strong>{$user['name']}</strong>,</p>
                <p>Here is your new verification code for password reset:</p>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px dashed #2c3e50; text-align: center; margin: 20px 0;'>
                    <h3 style='margin: 0; color: #2c3e50;'>Your New Verification Code:</h3>
                    <div style='font-size: 32px; font-weight: bold; color: #e74c3c; letter-spacing: 5px; margin: 15px 0;'>
                        {$new_verification_code}
                    </div>
                    <p style='color: #7f8c8d; font-size: 14px; margin: 0;'>
                        This code will expire in 10 minutes
                    </p>
                </div>
            </div>
        ";

        $mail->AltBody = "New Verification Code: {$new_verification_code}. This code will expire in 10 minutes.";

        if ($mail->send()) {
            $message = "<div class='message success-msg'>✅ New verification code sent to your email!</div>";
        } else {
            $message = "<div class='message error-msg'>❌ Failed to send new code. Please try again.</div>";
        }
    } catch (Exception $e) {
        $message = "<div class='message error-msg'>❌ Failed to send new code. Please try again.</div>";
    }
}
?>

<style>
    .main-content {
        margin-left: 300px;
        margin-top: 80px;
        padding: 20px;
        min-height: calc(100vh - 80px);
        background: var(--content-bg);
        color: var(--content-text);
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .verify-container {
        max-width: 400px;
        margin: 20px 0;
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        color: var(--content-text);
        width: 100%;
        text-align: center;
    }

    .verify-container h2 {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 20px;
        font-size: 24px;
        padding-bottom: 10px;
        border-bottom: 2px solid #bdc3c7;
    }

    .verify-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        border-left: 4px solid var(--primary-color);
    }

    .verify-info p {
        margin: 5px 0;
        font-size: 14px;
    }

    .code-input {
        width: 100%;
        padding: 12px;
        margin: 8px 0 15px 0;
        border: 2px solid #ddd;
        border-radius: 6px;
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        letter-spacing: 5px;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    .code-input:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.2);
    }

    .verify-container button {
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

    .verify-container button:hover {
        background: var(--hover-color);
        transform: translateY(-2px);
    }

    .resend-btn {
        background: #6c757d !important;
    }

    .resend-btn:hover {
        background: #545b62 !important;
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

    .success-msg {
        background-color: #d4edda;
        color: #155724;
        border-left-color: #28a745;
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

    .timer {
        color: #e74c3c;
        font-weight: bold;
        margin: 10px 0;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            margin-top: 70px;
            padding: 15px;
        }
        
        .verify-container {
            margin: 0 auto;
            padding: 25px;
        }
    }
</style>

<div class="main-content">
    <div class="verify-container">
        <h2>Enter Verification Code</h2>
        
        <div class="verify-info">
            <p>We sent a 6-digit verification code to:</p>
            <p><strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong></p>
            <p class="timer" id="timer">Code expires in: 10:00</p>
        </div>
        
        <?php echo $message; ?>
        
        <form method="POST">
            <label><strong>Enter 6-digit Code:</strong></label>
            <input type="text" name="verification_code" class="code-input" 
                   placeholder="000000" maxlength="6" pattern="[0-9]{6}" 
                   title="Please enter 6 digits" required>

            <button type="submit" name="verify">Verify Code</button>
            <button type="submit" name="resend" class="resend-btn">Resend Code</button>
            
            <div class="back-link">
                <a href="forgot-password.php">⬅ Back to Forgot Password</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Timer countdown
    let timeLeft = 600; // 10 minutes in seconds
    
    function updateTimer() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        document.getElementById('timer').textContent = 
            `Code expires in: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft > 0) {
            timeLeft--;
            setTimeout(updateTimer, 1000);
        } else {
            document.getElementById('timer').textContent = 'Code expired!';
            document.getElementById('timer').style.color = '#dc3545';
        }
    }
    
    updateTimer();
</script>