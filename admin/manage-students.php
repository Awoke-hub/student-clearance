<?php
session_start();
ob_start();
include '../includes/db.php';
include 'partials/menu.php';

// Add PHPMailer for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$form_errors = [];
$form_data = [];
$success_msg = '';

// Clear previous messages
if (!isset($_SESSION['flash_messages'])) {
    $_SESSION['flash_messages'] = [];
}

// Helper function to add flash messages
function add_flash_message($type, $msg) {
    $_SESSION['flash_messages'][] = ['type' => $type, 'msg' => $msg];
}

// Function to send email notification to student
function sendStudentCredentials($studentEmail, $studentFullName, $studentUsername, $studentPassword) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tomasderese49@gmail.com'; // Your Gmail
        $mail->Password   = 'njcv gmam lsda ejlf';     // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0;  // Set to 0 for production

        // Recipients
        $mail->setFrom('tomasderese49@gmail.com', 'DBU Clearance System');
        $mail->addAddress($studentEmail, $studentFullName);
        $mail->addReplyTo('tomasderese49@gmail.com', 'DBU Clearance System');

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your DBU Clearance System Login Credentials';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #2c3e50;'>Welcome to DBU Clearance System!</h2>
                <p>Dear <strong>{$studentFullName}</strong>,</p>
                <p>Your account has been created successfully in the DBU Clearance Management System.</p>
                
                <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #2c3e50;'>
                    <h3 style='margin-top: 0;'>Your Login Credentials:</h3>
                    <p><strong>Username:</strong> {$studentUsername}</p>
                    <p><strong>Password:</strong> {$studentPassword}</p>
                </div>
                
                <p style='color: #e74c3c; font-weight: bold;'>
                    ⚠️ For security reasons, please change your password immediately after first login.
                </p>
                
                <p>You can access the system at: <a href='http://dbu.free.nf/clearance-management/login.php'>Clearance System Login</a></p>
                
                <hr style='border: none; border-top: 1px solid #ddd;'>
                <p style='color: #7f8c8d; font-size: 12px;'>
                    This is an automated message. Please do not reply to this email.<br>
                    If you have any questions, contact the system administrator.
                </p>
            </div>
        ";

        $mail->AltBody = "Welcome {$studentFullName}! Your DBU Clearance System account has been created. Username: {$studentUsername}, Password: {$studentPassword}. Please log in and change your password immediately.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to verify if email exists using SMTP
function verifyEmailExists($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    list($user, $domain) = explode('@', $email);
    
    // Get MX records for the domain
    if (!getmxrr($domain, $mxhosts, $weight)) {
        // If no MX records, try the domain itself as fallback
        $mxhosts = array($domain);
    }
    
    $timeout = 10;
    $valid = false;
    
    foreach ($mxhosts as $host) {
        $sock = @fsockopen($host, 25, $errno, $errstr, $timeout);
        
        if ($sock) {
            // Set timeout for socket operations
            stream_set_timeout($sock, $timeout);
            
            // Wait for server greeting
            $response = fgets($sock, 1024);
            if (!preg_match('/^220/', $response)) {
                fclose($sock);
                continue;
            }
            
            // Send HELO
            fputs($sock, "HELO example.com\r\n");
            $response = fgets($sock, 1024);
            if (!preg_match('/^250/', $response)) {
                fclose($sock);
                continue;
            }
            
            // Send MAIL FROM
            fputs($sock, "MAIL FROM: <check@example.com>\r\n");
            $response = fgets($sock, 1024);
            if (!preg_match('/^250/', $response)) {
                fclose($sock);
                continue;
            }
            
            // Send RCPT TO (this is where we check if email exists)
            fputs($sock, "RCPT TO: <$email>\r\n");
            $response = fgets($sock, 1024);
            
            // Send QUIT
            fputs($sock, "QUIT\r\n");
            fclose($sock);
            
            // Check response for RCPT TO command
            if (preg_match('/^250/', $response)) {
                $valid = true;
                break;
            }
        }
    }
    
    return $valid;
}

// Enhanced email verification function
function validateRealEmail($email) {
    // Step 1: Basic format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Invalid email format'];
    }
    
    list($local, $domain) = explode('@', $email);
    
    // Step 2: Check for disposable email domains
    $disposableDomains = [
        'tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
        'throwawaymail.com', 'fakeinbox.com', 'temp-mail.org', 'yopmail.com',
        'getairmail.com', 'maildrop.cc', 'tempail.com', 'trashmail.com'
    ];
    
    if (in_array(strtolower($domain), $disposableDomains)) {
        return ['valid' => false, 'message' => 'Disposable email addresses are not allowed'];
    }
    
    // Step 3: Check DNS records
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return ['valid' => false, 'message' => 'Email domain does not exist'];
    }
    
    // Step 4: Try SMTP verification (this is the real check)
    add_flash_message('info', 'Verifying email address...');
    
    if (verifyEmailExists($email)) {
        return ['valid' => true, 'message' => 'Email verification passed'];
    } else {
        return ['valid' => false, 'message' => 'Email address does not exist or cannot receive emails'];
    }
}

// =================== BULK ACTIONS ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_students = $_POST['selected_students'] ?? [];
    
    if (!empty($selected_students)) {
        if ($bulk_action === 'activate') {
            // Bulk Activate students
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($selected_students as $student_id) {
                $student_id = $conn->real_escape_string($student_id);
                $stmt = $conn->prepare("UPDATE student SET status = 'active' WHERE student_id = ?");
                $stmt->bind_param("s", $student_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $failed_count++;
                }
                $stmt->close();
            }
            
            if ($success_count > 0) {
                add_flash_message('success', $success_count . " student(s) activated successfully!");
                if ($failed_count > 0) {
                    add_flash_message('error', $failed_count . " student(s) failed to activate.");
                }
            }
        } 
        elseif ($bulk_action === 'deactivate') {
            // Bulk Deactivate students
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($selected_students as $student_id) {
                $student_id = $conn->real_escape_string($student_id);
                $stmt = $conn->prepare("UPDATE student SET status = 'inactive' WHERE student_id = ?");
                $stmt->bind_param("s", $student_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $failed_count++;
                }
                $stmt->close();
            }
            
            if ($success_count > 0) {
                add_flash_message('success', $success_count . " student(s) deactivated successfully!");
                if ($failed_count > 0) {
                    add_flash_message('error', $failed_count . " student(s) failed to deactivate.");
                }
            }
        }
        elseif ($bulk_action === 'delete') {
            // Bulk Delete students
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($selected_students as $student_id) {
                $student_id = $conn->real_escape_string($student_id);
                
                // Get profile picture path to delete the file
                $stmt = $conn->prepare("SELECT profile_picture FROM student WHERE student_id=?");
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $stmt->bind_result($profile_picture);
                $stmt->fetch();
                $stmt->close();
                
                // Delete the profile picture file if it exists
                if (!empty($profile_picture) && file_exists('../' . $profile_picture)) {
                    unlink('../' . $profile_picture);
                }
                
                $stmt = $conn->prepare("DELETE FROM student WHERE student_id=?");
                $stmt->bind_param("s", $student_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $failed_count++;
                }
                $stmt->close();
            }
            
            if ($success_count > 0) {
                add_flash_message('success', $success_count . " student(s) deleted successfully!");
                if ($failed_count > 0) {
                    add_flash_message('error', $failed_count . " student(s) failed to delete.");
                }
            }
        }
    } else {
        add_flash_message('error', "Please select at least one student!");
    }
    
    header("Location: manage-students.php");
    exit();
}

// =================== UPDATE STUDENT STATUS ===================
if (isset($_GET['toggle_status'])) {
    $student_id = $_GET['toggle_status'];
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM student WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->bind_result($current_status);
    $stmt->fetch();
    $stmt->close();
    
    // Toggle status
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';
    
    $stmt = $conn->prepare("UPDATE student SET status = ? WHERE student_id = ?");
    $stmt->bind_param("ss", $new_status, $student_id);
    
    if ($stmt->execute()) {
        add_flash_message('success', "Student status changed to " . $new_status . " successfully.");
    } else {
        add_flash_message('error', "Failed to update student status.");
    }
    $stmt->close();
    
    header("Location: manage-students.php");
    exit();
}

// =================== ADD STUDENT ===================
if (isset($_POST['add_student'])) {
    $name = trim($_POST['name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password_raw = $_POST['password'];
    $department = "Information technology"; // Hardcoded department
    $phone = trim($_POST['phone']);
    $year = trim($_POST['year']);
    $semester = trim($_POST['semester']);
    $status = 'active'; // Default status for new students

    $form_data = compact('name', 'last_name', 'username', 'email', 'department', 'phone', 'year', 'semester', 'status');

    // Validation
    if (empty($name) || !preg_match("/^[a-zA-Z]+$/", $name)) {
        $form_errors[] = "First name is required and must contain letters only.";
    }

    if (empty($last_name) || !preg_match("/^[a-zA-Z]+$/", $last_name)) {
        $form_errors[] = "Last name is required and must contain letters only.";
    }

    if (empty($username)) {
        $form_errors[] = "Username is required.";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM student WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            $form_errors[] = "Username '$username' is already taken.";
        }
    }

    if (empty($email)) {
        $form_errors[] = "Email is required.";
    } else {
        // Check if email already exists in database
        $stmt = $conn->prepare("SELECT COUNT(*) FROM student WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            $form_errors[] = "Email '$email' is already registered.";
        } else {
            // REAL EMAIL VERIFICATION - This is what you wanted
            $emailValidation = validateRealEmail($email);
            if (!$emailValidation['valid']) {
                $form_errors[] = $emailValidation['message'];
            }
        }
    }

    if (empty($phone) || !preg_match("/^\d{10}$/", $phone)) {
        $form_errors[] = "Phone number is required and must be exactly 10 digits.";
    }

    // Updated year validation for dropdown (1-4)
    if (empty($year) || !preg_match("/^[1-4]$/", $year)) {
        $form_errors[] = "Year is required and must be selected from the dropdown.";
    }

    // Semester validation
    if (empty($semester) || !in_array($semester, ['1', '2'])) {
        $form_errors[] = "Semester is required and must be selected from the dropdown.";
    }

    if (empty($password_raw) || strlen($password_raw) < 8) {
        $form_errors[] = "Password is required and must be at least 8 characters long.";
    }

    // Handle profile picture upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size < 2 * 1024 * 1024) { // 2MB max
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = '../uploads/' . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $profile_picture = 'uploads/' . $filename;
            } else {
                $form_errors[] = "Failed to upload profile picture. Please try again.";
            }
        } else {
            $form_errors[] = "Profile picture must be JPEG, PNG, or GIF and less than 2MB.";
        }
    }

    // If no errors, generate student_id and insert
    if (empty($form_errors)) {
        // Generate student_id like DBU001, DBU002...
        $result = $conn->query("SELECT student_id FROM student ORDER BY student_id DESC LIMIT 1");
        $last_id = $result->fetch_assoc()['student_id'] ?? null;

        if ($last_id) {
            $last_num = (int)substr($last_id, 3); // get numeric part
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }

        $student_id = 'DBU' . str_pad($new_num, 3, '0', STR_PAD_LEFT); // e.g., DBU001

        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO student (student_id, name, last_name, phone, email, department, username, password, year, semester, profile_picture, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssss", $student_id, $name, $last_name, $phone, $email, $department, $username, $password, $year, $semester, $profile_picture, $status);
        
        if ($stmt->execute()) {
            // Send email notification to student with credentials
            $studentFullName = $name . ' ' . $last_name;
            $emailSent = sendStudentCredentials($email, $studentFullName, $username, $password_raw);
            
            if ($emailSent) {
                add_flash_message('success', 'Student added successfully and login credentials sent via email.');
            } else {
                add_flash_message('success', 'Student added successfully but failed to send email notification.');
            }
            
            header("Location: manage-students.php");
            exit();
        } else {
            add_flash_message('error', 'Failed to add student. Please try again.');
        }
        $stmt->close();
    }
}

// =================== UPDATE STUDENT ===================
if (isset($_POST['update_student'])) {
    $student_id = $_POST['student_id'];
    $username = trim($_POST['username']);
    $password_raw = $_POST['password'];
    $status = trim($_POST['status']); // Get status from form
    $year = trim($_POST['year']);
    $semester = trim($_POST['semester']);

    // Handle profile picture update
    $profile_picture_update = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size < 2 * 1024 * 1024) {
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = '../uploads/' . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $profile_picture_update = 'uploads/' . $filename;
            } else {
                $form_errors[] = "Failed to upload profile picture. Please try again.";
            }
        } else {
            $form_errors[] = "Profile picture must be JPEG, PNG, or GIF and less than 2MB.";
        }
    }

    // Validation for update
    if (empty($username)) {
        $form_errors[] = "Username is required.";
    } else {
        // Check if username is already taken by another student
        $stmt = $conn->prepare("SELECT COUNT(*) FROM student WHERE username = ? AND student_id != ?");
        $stmt->bind_param("ss", $username, $student_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            $form_errors[] = "Username '$username' is already taken.";
        }
    }

    if (!empty($password_raw) && strlen($password_raw) < 8) {
        $form_errors[] = "Password must be at least 8 characters long.";
    }

    if (empty($status) || !in_array($status, ['active', 'inactive'])) {
        $form_errors[] = "Status is required and must be either active or inactive.";
    }

    if (empty($year) || !preg_match("/^[1-4]$/", $year)) {
        $form_errors[] = "Year is required and must be selected from the dropdown.";
    }

    if (empty($semester) || !in_array($semester, ['1', '2'])) {
        $form_errors[] = "Semester is required and must be selected from the dropdown.";
    }

    if (empty($form_errors)) {
        $password = !empty($password_raw) ? password_hash($password_raw, PASSWORD_DEFAULT) : null;

        if ($password && $profile_picture_update) {
            // Update password, profile picture, status, year, and semester
            $stmt = $conn->prepare("UPDATE student SET username=?, password=?, profile_picture=?, status=?, year=?, semester=? WHERE student_id=?");
            $stmt->bind_param("sssssss", $username, $password, $profile_picture_update, $status, $year, $semester, $student_id);
        } elseif ($password) {
            // Update only password, status, year, and semester
            $stmt = $conn->prepare("UPDATE student SET username=?, password=?, status=?, year=?, semester=? WHERE student_id=?");
            $stmt->bind_param("ssssss", $username, $password, $status, $year, $semester, $student_id);
        } elseif ($profile_picture_update) {
            // Update only profile picture, status, year, and semester
            $stmt = $conn->prepare("UPDATE student SET username=?, profile_picture=?, status=?, year=?, semester=? WHERE student_id=?");
            $stmt->bind_param("ssssss", $username, $profile_picture_update, $status, $year, $semester, $student_id);
        } else {
            // Update only username, status, year, and semester
            $stmt = $conn->prepare("UPDATE student SET username=?, status=?, year=?, semester=? WHERE student_id=?");
            $stmt->bind_param("sssss", $username, $status, $year, $semester, $student_id);
        }
        
        if ($stmt->execute()) {
            add_flash_message('success', 'Student updated successfully.');
            header("Location: manage-students.php");
            exit();
        } else {
            add_flash_message('error', 'Failed to update student. Please try again.');
        }
        $stmt->close();
    }
}

// =================== DELETE STUDENT ===================
if (isset($_GET['delete_student'])) {
    $student_id = $_GET['delete_student'];
    
    // Get profile picture path to delete the file
    $stmt = $conn->prepare("SELECT profile_picture FROM student WHERE student_id=?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->bind_result($profile_picture);
    $stmt->fetch();
    $stmt->close();
    
    // Delete the profile picture file if it exists
    if (!empty($profile_picture) && file_exists('../' . $profile_picture)) {
        unlink('../' . $profile_picture);
    }
    
    $stmt = $conn->prepare("DELETE FROM student WHERE student_id=?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->close();

    add_flash_message('success', 'Student deleted successfully.');
    header("Location: manage-students.php");
    exit();
}

// =================== SEARCH FUNCTIONALITY ===================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search) {
    // Search by both student_id AND name (first name or last name)
    $search_term = "%$search%";
    $stmt = $conn->prepare("SELECT * FROM student WHERE student_id LIKE ? OR name LIKE ? OR last_name LIKE ? ORDER BY student_id ASC");
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $students = $stmt->get_result();

    if ($students->num_rows === 0) {
        add_flash_message('error', 'No student found with ID or name: ' . htmlspecialchars($search));
    }
} else {
    $students = $conn->query("SELECT * FROM student ORDER BY student_id ASC");
}

// =================== EDIT STUDENT ===================
$edit_student = null;
if (isset($_GET['edit_student'])) {
    $student_id = $_GET['edit_student'];
    $stmt = $conn->prepare("SELECT * FROM student WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_student = $result->fetch_assoc();
    $stmt->close();
}

// Show form if errors occurred or editing
$showAddForm = !empty($form_errors) || $edit_student !== null;
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Manage Students</title>
    <style>
        /* Student Management Specific Styles */
        .student-management-body { 
            font-family: Arial, sans-serif; 
            background: white; 
            margin: 0; 
            padding: 0; 
        }
        
        .student-management-header { 
            background: #008B8B; 
            color: white; 
            padding: 15px; 
            text-align: center; 
            position: relative; 
        }
        
        .student-management-logout { 
            position: absolute; 
            right: 20px; 
            top: 15px; 
            background: #ff4444; 
            color: white; 
            padding: 8px 12px; 
            text-decoration: none; 
            border-radius: 3px; 
        }
        
        .student-management-logout:hover { 
            background: #cc0000; 
        }
        
        .student-management-main { 
            max-width: 1200px; 
            margin: 20px auto; 
            background: white; 
            padding: 20px; 
            border-radius: 5px; 
        }

        /* Top actions */
        .student-management-actions { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
        }

        /* Add Student button */
        .student-management-add {
            background: #008B8B;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-block;
            margin-left: 0;
        }
        .student-management-add:hover { 
            background: #006B6B; 
        }

        /* Search bar */
        .student-management-search { 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }
        
        .student-management-search input { 
            padding: 6px 10px; 
            width: 250px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        
        .student-management-search-btn { 
            background: #008B8B; 
            color: white; 
            padding: 6px 10px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 14px; 
        }
        
        .student-management-search-btn:hover { 
            background: #006B6B; 
        }

        /* Bulk Actions Styles */
        .bulk-actions {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            border: 1px solid #dee2e6;
        }

        .bulk-actions select {
            padding: 0.6rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: white;
            font-size: 0.85rem;
        }

        .selected-count {
            background: #008B8B;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: none;
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .btn-bulk {
            background: linear-gradient(135deg, #008B8B, #006B6B);
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-bulk:hover {
            background: linear-gradient(135deg, #006B6B, #004B4B);
            transform: translateY(-2px);
        }

        /* Form */
        .student-management-form { 
            max-width: 400px; 
            margin: 20px auto; 
            padding: 20px; 
            background: #0b105aff; 
            border-radius: 8px; 
            display: none; 
        }
        
        .student-management-form h2 { 
            color: white; 
            text-align: center; 
        }
        
        .student-management-form input, 
        .student-management-form select,
        .student-management-form .file-input { 
            width: 100%; 
            padding: 10px; 
            margin: 8px 0; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        
        .student-management-form button { 
            background: #008B8B; 
            color: white; 
            padding: 10px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            width: 100%; 
        }
        
        .student-management-form button:hover { 
            background: #006B6B; 
        }

        /* Semester container */
        .semester-container {
            display: none;
        }

        /* Current profile picture display */
        .current-profile-picture {
            text-align: center;
            margin: 10px 0;
        }
        
        .profile-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #008B8B;
            margin: 10px auto;
            display: block;
        }
        
        .no-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px auto;
            color: #666;
            font-size: 12px;
            text-align: center;
        }

        /* File input styling */
        .file-input {
            background: white;
            color: #333;
        }

        /* Table */
        .student-management-table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        .student-management-table th, 
        .student-management-table td { 
            border: 1px solid #ddd; 
            padding: 10px; 
            text-align: center; 
            background: white;
            color: #333;
        }
        
        .student-management-table th { 
            background: #555; 
            color: white; 
        }
        
        .student-management-table tr:nth-child(even) td { 
            background: #f9f9f9; 
        }

        /* Profile picture in table */
        .table-profile-picture {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #008B8B;
        }

        /* Update/Delete icons */
        .student-management-icons img { 
            width: 24px; 
            cursor: pointer; 
            margin: 0 5px; 
        }

        /* Status buttons */
        .status-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            transition: all 0.3s ease;
            min-width: 80px;
        }
        
        .status-btn-active {
            background: #28a745;
            color: white;
        }
        
        .status-btn-active:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .status-btn-inactive {
            background: #dc3545;
            color: white;
        }
        
        .status-btn-inactive:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        /* Semester display - same as other columns */
        .semester-display {
            font-size: 14px;
            color: #333;
            font-weight: normal;
        }

        /* Error & Success Messages */
        .student-management-alerts { 
            max-width: 400px; 
            margin: 10px auto; 
            padding: 10px; 
            border-radius: 5px; 
        }
        
        .student-management-error { 
            background: #fdd; 
            color: #a33; 
            border: 1px solid #a33; 
        }
        
        .student-management-success { 
            background: #dfd; 
            color: #383; 
            border: 1px solid #383; 
        }
        
        .student-management-info { 
            background: #d0e7ff; 
            color: #0066cc; 
            border: 1px solid #0066cc; 
        }
        
        /* Disabled fields */
        .disabled-field {
            background-color: #f0f0f0;
            color: #666;
            cursor: not-allowed;
        }
        
        /* Email validation status */
        .email-status {
            font-size: 12px;
            margin-top: 5px;
            padding: 5px;
            border-radius: 3px;
        }
        
        .email-valid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .email-invalid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .email-checking {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        @media (max-width: 768px) {
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .student-management-table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 0.5rem;
            }
            
            .status-btn {
                min-width: 60px;
                font-size: 10px;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body class="student-management-body">
    <div class="student-management-header">
        <h1>Manage Students</h1>
    </div>

    <div class="student-management-main">
        <div class="student-management-actions" style="<?= $showAddForm ? 'display:none;' : '' ?>">
            <button class="student-management-add" onclick="showStudentForm()">+ Add Student</button>
            <form method="GET" class="student-management-search">
                <input type="text" name="search" placeholder="Search by ID or Name" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="student-management-search-btn">Search</button>
                <?php if ($search): ?>
                    <button type="button" onclick="window.location='?';" style="background:#888;margin-left:10px;">Clear</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Display flash messages -->
        <?php if (!empty($_SESSION['flash_messages'])): ?>
            <div class="student-management-alerts">
                <?php foreach ($_SESSION['flash_messages'] as $msg): ?>
                    <div class="student-management-<?= $msg['type'] ?>">
                        <?= htmlspecialchars($msg['msg']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php $_SESSION['flash_messages'] = []; ?>
        <?php endif; ?>

        <!-- Add / Update Form -->
        <div class="student-management-form" id="studentForm" style="<?= $showAddForm ? 'display:block;' : 'display:none;' ?>">
            <h2><?= $edit_student ? "Update Student" : "Add Student" ?></h2>

            <?php if (!empty($form_errors)): ?>
                <div class="student-management-alerts">
                    <div class="student-management-error">
                        <ul>
                            <?php foreach ($form_errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="studentFormElement">
                <?php if ($edit_student): ?>
                    <input type="hidden" name="student_id" value="<?= $edit_student['student_id'] ?>">
                    
                    <!-- Show current profile picture for update -->
                    <div class="current-profile-picture">
                        <strong style="color: white;">Current Profile Picture:</strong>
                        <?php if (!empty($edit_student['profile_picture'])): ?>
                            <img src="../<?= htmlspecialchars($edit_student['profile_picture']) ?>" 
                                 alt="Current Profile Picture" 
                                 class="profile-preview">
                            <div style="color: white; font-size: 12px;">Current picture</div>
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                            <div style="color: white; font-size: 12px;">No profile picture</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
               
                <?php if ($edit_student): ?>
                    <!-- Update Form - Username, password, status, year, semester, and profile picture -->
                    <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($form_data['username'] ?? $edit_student['username'] ?? '') ?>" required>
                    <input type="password" name="password" placeholder="Leave blank to keep current password">
                    
                    <!-- Year Dropdown -->
                    <select name="year" id="year" required onchange="toggleSemester()">
                        <option value="">Select Year</option>
                        <option value="1" <?= (isset($form_data['year']) && $form_data['year'] == '1') || (isset($edit_student['year']) && $edit_student['year'] == '1') ? 'selected' : '' ?>>First Year</option>
                        <option value="2" <?= (isset($form_data['year']) && $form_data['year'] == '2') || (isset($edit_student['year']) && $edit_student['year'] == '2') ? 'selected' : '' ?>>Second Year</option>
                        <option value="3" <?= (isset($form_data['year']) && $form_data['year'] == '3') || (isset($edit_student['year']) && $edit_student['year'] == '3') ? 'selected' : '' ?>>Third Year</option>
                        <option value="4" <?= (isset($form_data['year']) && $form_data['year'] == '4') || (isset($edit_student['year']) && $edit_student['year'] == '4') ? 'selected' : '' ?>>Fourth Year</option>
                    </select>
                    
                    <!-- Semester Dropdown (shown based on year selection) -->
                    <div id="semesterContainer" class="semester-container">
                        <select name="semester" id="semester" required>
                            <option value="">Select Semester</option>
                            <option value="1" <?= (isset($form_data['semester']) && $form_data['semester'] == '1') || (isset($edit_student['semester']) && $edit_student['semester'] == '1') ? 'selected' : '' ?>>First Semester</option>
                            <option value="2" <?= (isset($form_data['semester']) && $form_data['semester'] == '2') || (isset($edit_student['semester']) && $edit_student['semester'] == '2') ? 'selected' : '' ?>>Second Semester</option>
                        </select>
                    </div>
                    
                    <!-- Status Dropdown -->
                    <select name="status" required>
                        <option value="active" <?= (isset($form_data['status']) && $form_data['status'] == 'active') || (isset($edit_student['status']) && $edit_student['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (isset($form_data['status']) && $form_data['status'] == 'inactive') || (isset($edit_student['status']) && $edit_student['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    
                    <input type="file" name="profile_picture" class="file-input" accept="image/*">
                    <small style="color: white; font-size: 12px;">Max 2MB - JPG, PNG, GIF (Leave blank to keep current)</small>
                <?php else: ?>
                    <!-- Add Form - All fields including profile picture -->
                    <input type="text" name="name" placeholder="First Name" value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" required>
                    <input type="text" name="last_name" placeholder="Last Name" value="<?= htmlspecialchars($form_data['last_name'] ?? '') ?>" required>
                    <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($form_data['username'] ?? '') ?>" required>
                    
                    <!-- Email field with REAL validation -->
                    <input type="email" name="email" id="email" placeholder="Email" value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" required 
                           onblur="validateRealEmail(this.value)">
                    <div id="email-status" class="email-status"></div>
                    
                    <input type="text" name="department" placeholder="Department" value="Information technology" class="disabled-field" readonly required>
                    <input type="text" name="phone" placeholder="Phone" value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>" required>
                    
                    <!-- Year Dropdown (1st to 4th) -->
                    <select name="year" id="year" required onchange="toggleSemester()">
                        <option value="">Select Year</option>
                        <option value="1" <?= (isset($form_data['year']) && $form_data['year'] == '1') ? 'selected' : '' ?>>First Year</option>
                        <option value="2" <?= (isset($form_data['year']) && $form_data['year'] == '2') ? 'selected' : '' ?>>Second Year</option>
                        <option value="3" <?= (isset($form_data['year']) && $form_data['year'] == '3') ? 'selected' : '' ?>>Third Year</option>
                        <option value="4" <?= (isset($form_data['year']) && $form_data['year'] == '4') ? 'selected' : '' ?>>Fourth Year</option>
                    </select>
                    
                    <!-- Semester Dropdown (shown based on year selection) -->
                    <div id="semesterContainer" class="semester-container">
                        <select name="semester" id="semester" required>
                            <option value="">Select Semester</option>
                            <option value="1" <?= (isset($form_data['semester']) && $form_data['semester'] == '1') ? 'selected' : '' ?>>First Semester</option>
                            <option value="2" <?= (isset($form_data['semester']) && $form_data['semester'] == '2') ? 'selected' : '' ?>>Second Semester</option>
                        </select>
                    </div>
                    
                    <input type="password" name="password" placeholder="Password" required>
                    <input type="file" name="profile_picture" class="file-input" accept="image/*">
                    <small style="color: white; font-size: 12px;">Max 2MB - JPG, PNG, GIF (Optional)</small>
                <?php endif; ?>
                
                <button type="submit" name="<?= $edit_student ? 'update_student' : 'add_student' ?>">
                    <?= $edit_student ? 'Update Student' : 'Add Student' ?>
                </button>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions" style="<?= $showAddForm ? 'display:none;' : 'display:none;' ?>">
            <span class="selected-count" id="selectedCount">0 selected</span>
            <select id="bulkActionSelect">
                <option value="">Choose action...</option>
                <option value="activate">Activate Selected</option>
                <option value="deactivate">Deactivate Selected</option>
                <option value="delete">Delete Selected</option>
            </select>
            <button type="button" class="btn-bulk" onclick="applyBulkAction()">
                <i class="fas fa-play"></i> Apply
            </button>
            <button type="button" class="btn-bulk" onclick="clearSelection()" style="background: #6c757d;">
                <i class="fas fa-times"></i> Clear
            </button>
        </div>

        <!-- Student Table -->
        <form id="bulkActionForm" method="POST">
            <table class="student-management-table" id="studentTable" style="<?= $showAddForm ? 'display:none;' : '' ?>">
                <tr>
                    <th class="checkbox-cell">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                    </th>
                    <th>Profile</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Last Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Username</th>
                    <th>Year</th>
                    <th>Semester</th>
                    <th>Status</th>
                    <th>Update</th>
                    <th>Delete</th>
                </tr>
                <?php 
                if ($students && $students->num_rows > 0): 
                    while ($s = $students->fetch_assoc()): 
                ?>
                <tr>
                    <td class="checkbox-cell">
                        <input type="checkbox" name="selected_students[]" value="<?= $s['student_id'] ?>" 
                               class="student-checkbox" onchange="updateBulkActions()">
                    </td>
                    <td>
                        <?php if (!empty($s['profile_picture'])): ?>
                            <img src="../<?= htmlspecialchars($s['profile_picture']) ?>" 
                                 alt="Profile Picture" 
                                 class="table-profile-picture"
                                 title="Profile Picture">
                        <?php else: ?>
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-user" style="color: #666; font-size: 14px;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($s['student_id']) ?></td>
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= htmlspecialchars($s['last_name']) ?></td>
                    <td><?= htmlspecialchars($s['phone']) ?></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td><?= htmlspecialchars($s['department']) ?></td>
                    <td><?= htmlspecialchars($s['username']) ?></td>
                    <td>
                        <?php 
                        // Display year in readable format
                        $year_display = [
                            '1' => '1st Year',
                            '2' => '2nd Year', 
                            '3' => '3rd Year',
                            '4' => '4th Year'
                        ];
                        echo htmlspecialchars($year_display[$s['year']] ?? $s['year']);
                        ?>
                    </td>
                    <td>
                        <?php 
                        // Display semester in readable format
                        $semester_display = [
                            '1' => '1st Semester',
                            '2' => '2nd Semester'
                        ];
                        ?>
                        <span class="semester-display">
                            <?= htmlspecialchars($semester_display[$s['semester']] ?? $s['semester']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="status-btn status-btn-<?= htmlspecialchars($s['status']) ?>" 
                                onclick="toggleStatus('<?= $s['student_id'] ?>')">
                            <?= strtoupper(htmlspecialchars($s['status'])) ?>
                        </button>
                    </td>
                    <td class="student-management-icons">
                        <a href="?edit_student=<?= $s['student_id'] ?>"><img src="../images/update.png" title="Update"></a>
                    </td>
                    <td class="student-management-icons">
                        <a href="?delete_student=<?= $s['student_id'] ?>" onclick="return confirm('Delete this student?')">
                            <img src="../images/delete.png" title="Delete">
                        </a>
                    </td>
                </tr>
                <?php 
                    endwhile;
                elseif ($search): ?>
                <tr>
                    <td colspan="14" style="text-align: center;">No student found with ID or name: <?= htmlspecialchars($search) ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <input type="hidden" name="bulk_action" id="bulkActionInput">
        </form>
    </div>

    <script>
        function showStudentForm() {
            document.getElementById('studentForm').style.display = 'block';
            document.getElementById('studentTable').style.display = 'none';
            document.querySelector('.student-management-actions').style.display = 'none';
            document.getElementById('bulkActions').style.display = 'none';
        }

        // Auto-show form if editing
        <?php if ($edit_student): ?>
            document.getElementById('studentForm').style.display = 'block';
            document.getElementById('studentTable').style.display = 'none';
            document.querySelector('.student-management-actions').style.display = 'none';
            document.getElementById('bulkActions').style.display = 'none';
            // Show semester container if year is already selected in edit mode
            setTimeout(function() {
                toggleSemester();
            }, 100);
        <?php endif; ?>

        // Toggle student status
        function toggleStatus(studentId) {
            if (confirm('Are you sure you want to toggle this student\'s status?')) {
                window.location.href = '?toggle_status=' + studentId;
            }
        }

        // Toggle semester dropdown based on year selection
        function toggleSemester() {
            const yearSelect = document.getElementById('year');
            const semesterContainer = document.getElementById('semesterContainer');
            const semesterSelect = document.getElementById('semester');
            
            if (yearSelect.value) {
                semesterContainer.style.display = 'block';
                // Reset semester selection when year changes
                semesterSelect.value = '';
            } else {
                semesterContainer.style.display = 'none';
                semesterSelect.value = '';
            }
        }

        // Bulk Actions Functions
        function toggleSelectAll(selectAllCheckbox) {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            const allChecked = selectAllCheckbox.checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = allChecked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
            const totalCheckboxes = document.querySelectorAll('.student-checkbox').length;
            const selectedCountElement = document.getElementById('selectedCount');
            const bulkActionsElement = document.getElementById('bulkActions');
            const selectAllCheckbox = document.getElementById('selectAll');
            
            if (selectedCount > 0) {
                selectedCountElement.textContent = selectedCount + ' selected';
                bulkActionsElement.style.display = 'flex';
                
                // Update select all checkbox state
                if (selectedCount === totalCheckboxes) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else if (selectedCount > 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
            } else {
                bulkActionsElement.style.display = 'none';
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            document.getElementById('selectAll').indeterminate = false;
            updateBulkActions();
        }

        function applyBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
            
            if (selectedCount === 0) {
                alert('Please select at least one student.');
                return;
            }
            
            let confirmMessage = '';
            switch(action) {
                case 'activate':
                    confirmMessage = `Are you sure you want to activate ${selectedCount} student(s)?`;
                    break;
                case 'deactivate':
                    confirmMessage = `Are you sure you want to deactivate ${selectedCount} student(s)?`;
                    break;
                case 'delete':
                    confirmMessage = `Are you sure you want to delete ${selectedCount} student(s)? This action cannot be undone!`;
                    break;
                default:
                    alert('Please select an action.');
                    return;
            }
            
            if (confirm(confirmMessage)) {
                document.getElementById('bulkActionInput').value = action;
                document.getElementById('bulkActionForm').submit();
            }
        }

        // REAL Email validation function - checks if email actually exists
        function validateRealEmail(email) {
            const emailStatus = document.getElementById('email-status');
            
            if (!email) {
                emailStatus.innerHTML = '';
                return;
            }
            
            // Basic email format validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                emailStatus.innerHTML = '<div class="email-invalid">✗ Invalid email format</div>';
                return;
            }
            
            // Show checking message
            emailStatus.innerHTML = '<div class="email-checking">⏳ Checking if email exists...</div>';
            
            // Make AJAX call to verify email
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'verify-email.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.valid) {
                                emailStatus.innerHTML = '<div class="email-valid">✓ Valid email address</div>';
                            } else {
                                emailStatus.innerHTML = '<div class="email-invalid">✗ ' + response.message + '</div>';
                            }
                        } catch (e) {
                            emailStatus.innerHTML = '<div class="email-invalid">✗ Error verifying email</div>';
                        }
                    } else {
                        emailStatus.innerHTML = '<div class="email-invalid">✗ Verification service unavailable</div>';
                    }
                }
            };
            
            xhr.send('email=' + encodeURIComponent(email));
        }

        // Preview image before upload (for better UX)
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.querySelector('input[name="profile_picture"]');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            // Create or update preview image
                            let preview = document.querySelector('.profile-preview');
                            if (!preview || preview.classList.contains('no-image')) {
                                const previewContainer = document.querySelector('.current-profile-picture');
                                if (previewContainer) {
                                    preview = document.createElement('img');
                                    preview.className = 'profile-preview';
                                    previewContainer.appendChild(preview);
                                }
                            }
                            if (preview && preview.tagName === 'IMG') {
                                preview.src = e.target.result;
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Initialize bulk actions
            updateBulkActions();
        });
    </script>

    <?php include 'partials/footer.php'; ?>
</body>
</html>