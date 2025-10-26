<?php
include 'includes/db.php';
include 'includes/menu.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Get student data
$stmt = $conn->prepare("SELECT * FROM student WHERE student_id = ?");
$stmt->bind_param("s", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

$message = '';
$message_type = '';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = 'uploads/profile_pictures/';
    
    // Create upload directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['profile_picture'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed file types
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    
    // Check for upload errors
    if ($file_error !== UPLOAD_ERR_OK) {
        switch ($file_error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = 'File size too large. Maximum allowed size is 1MB.';
                $message_type = 'error';
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = 'File was only partially uploaded.';
                $message_type = 'error';
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = 'No file was uploaded.';
                $message_type = 'error';
                break;
            default:
                $message = 'Upload failed with error code: ' . $file_error;
                $message_type = 'error';
        }
    }
    // Check file extension
    elseif (!in_array($file_ext, $allowed_ext)) {
        $message = 'Only JPG and PNG files are allowed.';
        $message_type = 'error';
    }
    // Check file size (1MB = 1048576 bytes)
    elseif ($file_size > 1048576) {
        $message = 'File size must be less than 1MB.';
        $message_type = 'error';
    }
    // Check if file is actually an image
    elseif (!getimagesize($file_tmp)) {
        $message = 'File is not a valid image.';
        $message_type = 'error';
    }
    // Everything is valid, proceed with upload
    else {
        // Generate unique filename
        $new_filename = 'profile_' . $student['student_id'] . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file_tmp, $destination)) {
            // Update database with new profile picture path
            $update_stmt = $conn->prepare("UPDATE student SET profile_picture = ? WHERE student_id = ?");
            $update_stmt->bind_param("ss", $destination, $_SESSION['student_id']);
            
            if ($update_stmt->execute()) {
                // Delete old profile picture if it exists and is not the default
                if (!empty($student['profile_picture']) && file_exists($student['profile_picture']) && 
                    strpos($student['profile_picture'], 'uploads/profile_pictures/') !== false) {
                    unlink($student['profile_picture']);
                }
                
                $message = 'Profile picture updated successfully!';
                $message_type = 'success';
                
                // UPDATE THE SESSION WITH NEW PROFILE PICTURE - THIS IS THE FIX
                $_SESSION['profile_picture'] = $destination;
                
                // Refresh student data
                $stmt = $conn->prepare("SELECT * FROM student WHERE student_id = ?");
                $stmt->bind_param("s", $_SESSION['student_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $student = $result->fetch_assoc();
            } else {
                $message = 'Failed to update profile picture in database. Error: ' . $conn->error;
                $message_type = 'error';
                // Delete the uploaded file since database update failed
                if (file_exists($destination)) {
                    unlink($destination);
                }
            }
            
            $update_stmt->close();
        } else {
            $message = 'Failed to upload file. Please check directory permissions.';
            $message_type = 'error';
        }
    }
}

// Handle profile picture removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_picture'])) {
    if (!empty($student['profile_picture']) && file_exists($student['profile_picture']) && 
        strpos($student['profile_picture'], 'uploads/profile_pictures/') !== false) {
        unlink($student['profile_picture']);
    }
    
    $update_stmt = $conn->prepare("UPDATE student SET profile_picture = NULL WHERE student_id = ?");
    $update_stmt->bind_param("s", $_SESSION['student_id']);
    
    if ($update_stmt->execute()) {
        $message = 'Profile picture removed successfully!';
        $message_type = 'success';
        // Update session
        $_SESSION['profile_picture'] = null;
        // Refresh student data
        $student['profile_picture'] = null;
    } else {
        $message = 'Failed to remove profile picture. Error: ' . $conn->error;
        $message_type = 'error';
    }
    
    $update_stmt->close();
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
}

.profile-container {
    max-width: 600px;
    margin: 0;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.profile-header {
    text-align: center;
    margin-bottom: 30px;
    position: relative;
}

.profile-picture-container {
    position: relative;
    display: inline-block;
    margin-bottom: 15px;
}

.profile-picture-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--primary-color);
    transition: all 0.3s ease;
}

.profile-picture-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    cursor: pointer;
}

.profile-picture-container:hover .profile-picture-overlay {
    opacity: 1;
}

.profile-picture-overlay i {
    color: white;
    font-size: 24px;
}

.upload-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 2px dashed #dee2e6;
}

.upload-form h4 {
    margin-bottom: 15px;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.file-input-wrapper {
    position: relative;
    margin-bottom: 10px;
}

.file-input-wrapper input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}

.upload-hint {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 5px;
}

.upload-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.profile-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.info-group {
    margin-bottom: 15px;
}

.info-label {
    font-weight: bold;
    color: var(--primary-color);
    display: block;
    margin-bottom: 5px;
}

.info-value {
    color: var(--content-text);
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.alert {
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 6px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.5s ease-out;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
}

.alert-close:hover {
    opacity: 1;
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        margin-top: 70px;
        padding: 15px;
    }
    
    .profile-container {
        margin: 0 auto;
        padding: 25px;
    }
    
    .profile-info {
        grid-template-columns: 1fr;
    }
    
    .upload-actions {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .main-content {
        padding: 10px;
    }
    
    .profile-container {
        padding: 20px;
    }
    
    .profile-picture-large {
        width: 120px;
        height: 120px;
    }
    
    .upload-form {
        padding: 15px;
    }
}
</style>

<div class="main-content">
    <div class="profile-container">
        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>" id="messageAlert">
                <?= $message_type === 'success' ? '✅' : '❌' ?>
                <span><?= htmlspecialchars($message) ?></span>
                <button class="alert-close" onclick="dismissAlert('messageAlert')" aria-label="Close message">&times;</button>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-picture-container">
                <?php if (!empty($student['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($student['profile_picture']) ?>" 
                         alt="Profile Picture" 
                         class="profile-picture-large">
                    <div class="profile-picture-overlay" onclick="document.getElementById('profilePictureInput').click()">
                        <i class="fas fa-camera"></i>
                    </div>
                <?php else: ?>
                    <div style="width: 150px; height: 150px; border-radius: 50%; background: #f8f9fa; border: 4px solid var(--primary-color); display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                        <i class="fas fa-user-circle" style="font-size: 80px; color: var(--primary-color);"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h2><?= htmlspecialchars($student['name'] . ' ' . $student['last_name']) ?></h2>
            <p>Student ID: <?= htmlspecialchars($student['student_id']) ?></p>
        </div>

        <!-- Profile Picture Upload Form -->
        <div class="upload-form">
            <h4><i class="fas fa-camera"></i> Update Profile Picture</h4>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="file-input-wrapper">
                    <input type="file" name="profile_picture" id="profilePictureInput" 
                           accept=".jpg,.jpeg,.png" required>
                </div>
                <div class="upload-hint">
                    <i class="fas fa-info-circle"></i> 
                    Maximum file size: 1MB | Allowed formats: JPG, PNG
                </div>
                <div class="upload-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Picture
                    </button>
                    <?php if (!empty($student['profile_picture'])): ?>
                        <button type="submit" name="remove_picture" class="btn btn-danger" 
                                onclick="return confirm('Are you sure you want to remove your profile picture?')">
                            <i class="fas fa-trash"></i> Remove Picture
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="profile-info">
            <div class="info-group">
                <span class="info-label">Username:</span>
                <span class="info-value"><?= htmlspecialchars($student['username']) ?></span>
            </div>
            
            <div class="info-group">
                <span class="info-label">Email:</span>
                <span class="info-value"><?= htmlspecialchars($student['email']) ?></span>
            </div>
            
            <div class="info-group">
                <span class="info-label">Department:</span>
                <span class="info-value"><?= htmlspecialchars($student['department']) ?></span>
            </div>
            
            <div class="info-group">
                <span class="info-label">Phone:</span>
                <span class="info-value"><?= htmlspecialchars($student['phone']) ?></span>
            </div>
            
            <div class="info-group">
                <span class="info-label">Year:</span>
                <span class="info-value"><?= htmlspecialchars($student['year']) ?></span>
            </div>
            
            <div class="info-group">
                <span class="info-label">Semester:</span>
                <span class="info-value"><?= htmlspecialchars($student['semester']) ?></span>
            </div>
            
            <div class="info-group">
                <span class="info-label">Status:</span>
                <span class="info-value"><?= htmlspecialchars($student['status']) ?></span>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-dismiss alert after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.getElementById('messageAlert');
    if (alert) {
        setTimeout(() => {
            dismissAlert('messageAlert');
        }, 5000);
    }
});

function dismissAlert(alertId) {
    const alert = document.getElementById(alertId);
    if (alert) {
        alert.style.opacity = '0';
        alert.style.maxHeight = '0';
        alert.style.margin = '0';
        alert.style.padding = '0';
        alert.style.border = '0';
        setTimeout(() => {
            alert.remove();
        }, 300);
    }
}

// File size validation before upload
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('profilePictureInput');
    const file = fileInput.files[0];
    
    if (file) {
        // Check file size (1MB = 1048576 bytes)
        if (file.size > 1048576) {
            e.preventDefault();
            alert('File size must be less than 1MB. Your file is ' + (file.size / 1024 / 1024).toFixed(2) + 'MB.');
            return false;
        }
        
        // Check file extension
        const fileName = file.name.toLowerCase();
        const validExtensions = ['.jpg', '.jpeg', '.png'];
        const hasValidExtension = validExtensions.some(ext => fileName.endsWith(ext));
        
        if (!hasValidExtension) {
            e.preventDefault();
            alert('Only JPG and PNG files are allowed.');
            return false;
        }
    }
});

// Show file name when selected
document.getElementById('profilePictureInput').addEventListener('change', function(e) {
    const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
    console.log('Selected file:', fileName);
});

// Trigger file input when clicking on profile picture overlay
document.querySelector('.profile-picture-overlay')?.addEventListener('click', function() {
    document.getElementById('profilePictureInput').click();
});
</script>