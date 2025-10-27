<?php
include 'includes/menu.php'; 
include 'includes/db.php';

if (!isset($_SESSION['reset_student_id'])) {
    header("Location: forgot-password.php");
    exit();
}

$message = "";

if (isset($_POST['reset'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password match
    if ($new_password !== $confirm_password) {
        $message = "<div class='message error'>❌ New password and confirm password do not match!</div>";
    } else {
        // Strong password validation using the same function as change-password
        $password_errors = validatePassword($new_password);
        
        if (!empty($password_errors)) {
            $errorMessages = "❌ Password requirements not met:<br>" . implode("<br>", $password_errors);
            $message = "<div class='message error'>$errorMessages</div>";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $student_id = $_SESSION['reset_student_id'];

            $stmt = $conn->prepare("UPDATE student SET password=? WHERE id=?");
            $stmt->bind_param("si", $hashed, $student_id);

            if ($stmt->execute()) {
                $message = "<div class='message success'>✅ Password reset successfully! <a href='login.php'>Login now</a></div>";
                unset($_SESSION['reset_student_id']);
            } else {
                $message = "<div class='message error'>❌ Something went wrong. Try again.</div>";
            }
        }
    }
}

// Strong password validation function (EXACT SAME AS change-password.php)
function validatePassword($password) {
    $errors = [];
    
    // Minimum length
    if (strlen($password) < 8) {
        $errors[] = "• At least 8 characters long";
    }
    
    // Maximum length - CHANGED FROM 64 TO 16
    if (strlen($password) > 16) {
        $errors[] = "• Maximum 16 characters allowed";
    }
    
    // At least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "• At least one uppercase letter (A-Z)";
    }
    
    // At least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "• At least one lowercase letter (a-z)";
    }
    
    // At least one number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "• At least one number (0-9)";
    }
    
    // At least one special character
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        $errors[] = "• At least one special character (!@#$%^&*()_-+=)";
    }
    
    // No spaces allowed
    if (preg_match('/\s/', $password)) {
        $errors[] = "• No spaces allowed";
    }
    
    // Check for common weak passwords
    $common_passwords = ['password', '12345678', 'qwerty123', 'admin123', 'welcome1'];
    if (in_array(strtolower($password), $common_passwords)) {
        $errors[] = "• Password is too common, choose a stronger one";
    }
    
    return $errors;
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
        max-width: 500px;
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

    .password-input-wrapper {
        position: relative;
        margin: 10px 0 15px 0;
    }

    .reset-container input {
        width: 100%;
        padding: 12px 40px 12px 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 15px;
        transition: all 0.3s;
        box-sizing: border-box;
    }

    .reset-container input:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.2);
    }

    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #6c757d;
        font-size: 16px;
        padding: 4px;
        transition: color 0.3s;
        z-index: 2;
    }

    .password-toggle:hover {
        color: var(--primary-color);
    }

    .reset-container button[type="submit"] {
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
        margin-top: 20px;
    }

    .reset-container button[type="submit"]:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
    }

    .reset-container button[type="submit"]:disabled {
        background-color: #95a5a6;
        cursor: not-allowed;
        transform: none;
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
        text-align: left;
    }

    .success a {
        color: #155724;
        font-weight: bold;
        text-decoration: underline;
        margin-left: 5px;
    }

    .success a:hover {
        color: #0d3515;
        text-decoration: none;
    }

    .password-strength {
        margin: 10px 0;
        height: 5px;
        border-radius: 3px;
        background: #e9ecef;
        overflow: hidden;
    }

    .strength-bar {
        height: 100%;
        width: 0%;
        transition: all 0.3s ease;
        border-radius: 3px;
    }

    .strength-weak { background: #dc3545; width: 25%; }
    .strength-fair { background: #fd7e14; width: 50%; }
    .strength-good { background: #ffc107; width: 75%; }
    .strength-strong { background: #28a745; width: 100%; }

    .strength-text {
        font-size: 12px;
        text-align: center;
        margin-top: 5px;
        font-weight: bold;
    }

    #passwordMatch {
        font-size: 12px;
        margin: -10px 0 15px 0;
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
            padding: 10px 35px 10px 12px;
        }
        
        .reset-container button[type="submit"] {
            padding: 12px;
        }
        
        .password-toggle {
            right: 10px;
            font-size: 14px;
        }
    }
</style>

<div class="main-content">
    <div class="reset-container">
        <h2>Reset Password</h2>
        
        <?php echo $message; ?>

        <form method="POST" id="passwordForm">
            <!-- New Password -->
            <div class="password-input-wrapper">
                <input type="password" name="new_password" id="new_password" placeholder="New Password" required 
                       oninput="checkPasswordStrength()">
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password')">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            
            <div class="password-strength">
                <div class="strength-bar" id="strengthBar"></div>
            </div>
            <div class="strength-text" id="strengthText"></div>
            
            <!-- Confirm Password -->
            <div class="password-input-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm New Password" required 
                       oninput="checkPasswordMatch()">
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            
            <div id="passwordMatch"></div>
            
            <button type="submit" name="reset" id="submitBtn">Reset Password</button>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = passwordInput.nextElementSibling.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
        toggleIcon.title = 'Hide password';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'fas fa-eye';
        toggleIcon.title = 'Show password';
    }
}

function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    let strength = 0;
    
    // Check individual requirements
    const hasMinLength = password.length >= 8;
    const hasMaxLength = password.length <= 16;
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
    const hasNoSpace = !/\s/.test(password);
    
    // Calculate strength based on met requirements
    if (hasMinLength) strength += 1;
    if (hasUppercase) strength += 1;
    if (hasLowercase) strength += 1;
    if (hasNumber) strength += 1;
    if (hasSpecial) strength += 1;
    if (hasNoSpace) strength += 1;
    
    // Set strength level
    switch(true) {
        case (strength <= 2):
            strengthBar.className = 'strength-bar strength-weak';
            strengthText.textContent = 'Weak Password';
            strengthText.style.color = '#dc3545';
            break;
        case (strength <= 4):
            strengthBar.className = 'strength-bar strength-fair';
            strengthText.textContent = 'Fair Password';
            strengthText.style.color = '#fd7e14';
            break;
        case (strength <= 5):
            strengthBar.className = 'strength-bar strength-good';
            strengthText.textContent = 'Good Password';
            strengthText.style.color = '#ffc107';
            break;
        case (strength === 6):
            strengthBar.className = 'strength-bar strength-strong';
            strengthText.textContent = 'Strong Password';
            strengthText.style.color = '#28a745';
            break;
    }
    
    checkPasswordMatch();
}

function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchDiv = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');
    
    if (confirmPassword === '') {
        matchDiv.textContent = '';
        matchDiv.style.color = '';
        submitBtn.disabled = false;
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchDiv.textContent = '✓ Passwords match';
        matchDiv.style.color = '#28a745';
        submitBtn.disabled = false;
    } else {
        matchDiv.textContent = '✗ Passwords do not match';
        matchDiv.style.color = '#dc3545';
        submitBtn.disabled = true;
    }
}

// Initial check
document.addEventListener('DOMContentLoaded', function() {
    checkPasswordStrength();
    checkPasswordMatch();
});
</script>