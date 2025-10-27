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
        // Strong password validation
        $password_errors = validatePassword($new_password);
        
        if (!empty($password_errors)) {
            $error = "❌ Password requirements not met:<br>" . implode("<br>", $password_errors);
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
                    // Check if new password is same as current password
                    if (password_verify($new_password, $user['password'])) {
                        $error = "❌ New password cannot be the same as current password!";
                    } else {
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
                    }
                } else {
                    $error = "❌ Current password is incorrect!";
                }
            }
            $stmt->close();
        }
    }
}

// Strong password validation function
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
        max-width: 500px;
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

    .password-input-wrapper {
        position: relative;
        margin: 10px 0 15px 0;
    }

    .password-container input {
        width: 100%;
        padding: 12px 40px 12px 15px;
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

    .password-container button[type="submit"] {
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

    .password-container button[type="submit"]:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
    }

    .password-container button[type="submit"]:disabled {
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

    /* Password requirements styling */
    .password-requirements {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 15px;
        margin: 10px 0 20px 0;
        font-size: 13px;
    }

    .password-requirements h4 {
        margin: 0 0 10px 0;
        color: #2c3e50;
        font-size: 14px;
        font-weight: 600;
    }

    .password-requirements ul {
        margin: 0;
        padding-left: 20px;
    }

    .password-requirements li {
        margin-bottom: 5px;
        transition: color 0.3s ease;
    }

    .requirement-met {
        color: #28a745;
        font-weight: 500;
    }

    .requirement-not-met {
        color: #6c757d;
    }

    .requirement-error {
        color: #dc3545;
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
            padding: 10px 35px 10px 12px;
        }
        
        .password-container button[type="submit"] {
            padding: 12px;
        }
        
        .password-toggle {
            right: 10px;
            font-size: 14px;
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

        <form method="POST" id="passwordForm">
            <!-- Current Password -->
            <div class="password-input-wrapper">
                <input type="password" name="current_password" id="current_password" placeholder="Current Password" required>
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password')">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            
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
            
            <div id="passwordMatch" style="font-size: 12px; margin: -10px 0 15px 0;"></div>
            
            <div class="password-requirements">
                <h4>Password Requirements:</h4>
                <ul>
                    <li id="req-length" class="requirement-not-met"> At least 8 characters long</li>
                    <li id="req-maxlength" class="requirement-not-met"> Maximum 16 characters</li>
                    <li id="req-uppercase" class="requirement-not-met"> At least one uppercase letter (A-Z)</li>
                    <li id="req-lowercase" class="requirement-not-met"> At least one lowercase letter (a-z)</li>
                    <li id="req-number" class="requirement-not-met"> At least one number (0-9)</li>
                    <li id="req-special" class="requirement-not-met"> At least one special character (!@#$%^&*()_-+=)</li>
                    <li id="req-nospace" class="requirement-not-met"> No spaces allowed</li>
                </ul>
            </div>
            
            <button type="submit" name="change" id="submitBtn">Change Password</button>
        </form>

        <a class="back-link" href="index.php">⬅ Back to Dashboard</a>
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
    
    // Check individual requirements
    const hasMinLength = password.length >= 8;
    const hasMaxLength = password.length <= 16;
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
    const hasNoSpace = !/\s/.test(password);
    
    // Update requirement colors
    updateRequirement('req-length', hasMinLength);
    updateRequirement('req-maxlength', hasMaxLength);
    updateRequirement('req-uppercase', hasUppercase);
    updateRequirement('req-lowercase', hasLowercase);
    updateRequirement('req-number', hasNumber);
    updateRequirement('req-special', hasSpecial);
    updateRequirement('req-nospace', hasNoSpace);
    
    let strength = 0;
    
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

function updateRequirement(elementId, isMet) {
    const element = document.getElementById(elementId);
    if (isMet) {
        element.className = 'requirement-met';
    } else {
        element.className = 'requirement-not-met';
    }
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