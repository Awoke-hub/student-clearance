<?php
include 'includes/db.php';
include 'includes/menu.php'; 

// Add PHPMailer at the top level (outside functions)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";

// Function to send verification code email
function sendVerificationCode($studentEmail, $studentName, $verificationCode) {
    $mail = new PHPMailer(true);

    try {
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
        $mail->addAddress($studentEmail, $studentName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Verification Code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #2c3e50;'>Password Reset Request</h2>
                <p>Dear <strong>{$studentName}</strong>,</p>
                <p>You have requested to reset your password for the DBU Clearance System.</p>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px dashed #2c3e50; text-align: center; margin: 20px 0;'>
                    <h3 style='margin: 0; color: #2c3e50;'>Your Verification Code:</h3>
                    <div style='font-size: 32px; font-weight: bold; color: #e74c3c; letter-spacing: 5px; margin: 15px 0;'>
                        {$verificationCode}
                    </div>
                    <p style='color: #7f8c8d; font-size: 14px; margin: 0;'>
                        This code will expire in 10 minutes
                    </p>
                </div>
                
                <p style='color: #e74c3c; font-weight: bold;'>
                    ⚠️ If you didn't request this reset, please ignore this email.
                </p>
                
                <hr style='border: none; border-top: 1px solid #ddd;'>
                <p style='color: #7f8c8d; font-size: 12px;'>
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        ";

        $mail->AltBody = "Password Reset Verification Code: {$verificationCode}. This code will expire in 10 minutes. If you didn't request this reset, please ignore this email.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id, name, email FROM student WHERE name = ? AND email = ?");
    $stmt->bind_param("ss", $name, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Generate 6-digit verification code
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        
        // Store code in session with expiration time (10 minutes)
        $_SESSION['verification_code'] = $verification_code;
        $_SESSION['verification_expiry'] = time() + 600; // 10 minutes
        $_SESSION['reset_student_id'] = $user['id'];
        $_SESSION['reset_email'] = $email;
        
        // Send verification code via email
        if (sendVerificationCode($email, $user['name'], $verification_code)) {
            header("Location: verify-code.php");
            exit();
        } else {
            $message = "<div class='message error-msg'>❌ Failed to send verification code. Please try again.</div>";
        }
    } else {
        $message = "<div class='message error-msg'>❌ No account found with that Name and Email.</div>";
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

    .reset-container {
        max-width: 400px;
        margin: 20px 0;
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
            margin: 0 auto;
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
            margin: 10px auto;
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

            <button type="submit" name="submit">Send Verification Code</button>
            <div class="back-link">
                <a href="login.php">⬅ Back to Login</a>
            </div>
        </form>
    </div>
</div>