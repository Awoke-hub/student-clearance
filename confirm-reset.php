<?php
include 'includes/db.php';
include 'includes/menu.php';

$message = "";
$show_form = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Validate token - join with student table to get student info
    $stmt = $conn->prepare("SELECT prt.*, s.name, s.student_id 
                           FROM password_reset_tokens prt 
                           JOIN student s ON prt.student_id = s.student_id 
                           WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $token_data = $result->fetch_assoc();
        $_SESSION['reset_student_id'] = $token_data['student_id']; // Store DBU001
        $_SESSION['reset_token'] = $token;
        $show_form = true;
        $message = "<div class='message success-msg'>✅ Email confirmed! You can now reset your password for Student ID: <strong>{$token_data['student_id']}</strong></div>";
    } else {
        $message = "<div class='message error-msg'>❌ Invalid or expired confirmation link. Please request a new password reset.</div>";
    }
} else {
    $message = "<div class='message error-msg'>❌ No confirmation token provided.</div>";
}

if (isset($_POST['reset']) && $show_form) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password === $confirm_password) {
        if (strlen($new_password) >= 8) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $student_id = $_SESSION['reset_student_id']; // This is DBU001
            $token = $_SESSION['reset_token'];

            // Update password using student_id (DBU001)
            $stmt = $conn->prepare("UPDATE student SET password = ? WHERE student_id = ?");
            $stmt->bind_param("ss", $hashed, $student_id);

            if ($stmt->execute()) {
                // Mark token as used
                $update_stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
                $update_stmt->bind_param("s", $token);
                $update_stmt->execute();
                
                $message = "<div class='message success-msg'>✅ Password reset successfully for Student ID: <strong>{$student_id}</strong>! <a href='login.php'>Login now</a></div>";
                unset($_SESSION['reset_student_id']);
                unset($_SESSION['reset_token']);
                $show_form = false;
            } else {
                $message = "<div class='message error-msg'>❌ Something went wrong. Try again.</div>";
            }
        } else {
            $message = "<div class='message error-msg'>❌ Password must be at least 8 characters long.</div>";
        }
    } else {
        $message = "<div class='message error-msg'>❌ Passwords do not match!</div>";
    }
}
?>