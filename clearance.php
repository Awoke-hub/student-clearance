<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
include 'includes/menu.php';
include 'includes/db.php';

// Debug: Check if files are included
if (!function_exists('mysqli_connect')) {
    die("MySQLi functions not available. Check your PHP configuration.");
}

// Session check - consistent with login.php
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Debug session
echo "<!-- Debug: Session student_id = " . ($_SESSION['student_id'] ?? 'NOT SET') . " -->";

// Get current academic year (Academic year starts in September)
$current_month = date('n');
$current_year = date('Y');
$academic_year = ($current_month >= 9) ? $current_year : $current_year - 1;
$next_academic_year = $academic_year + 1;

// Get student data from database INCLUDING STATUS
$student = null;
try {
    $stmt = $conn->prepare("SELECT id, student_id, name, last_name, department, status FROM student WHERE student_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $_SESSION['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Student record not found for ID: " . $_SESSION['student_id']);
    }

    $student = $result->fetch_assoc();
    
    // Debug student data
    echo "<!-- Debug: Student found - " . htmlspecialchars($student['name']) . " -->";
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$message = "";
$selected_option = "";

// NEW LOGIC: Check if student has clearance for CURRENT academic year
$has_current_clearance = false;
try {
    $stmt = $conn->prepare("SELECT status FROM final_clearance WHERE student_id = ? AND academic_year = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("si", $_SESSION['student_id'], $academic_year);
    $stmt->execute();
    $clearance_result = $stmt->get_result();

    $has_current_clearance = ($clearance_result->num_rows > 0);
    
    // Debug clearance status
    echo "<!-- Debug: Has current clearance = " . ($has_current_clearance ? 'YES' : 'NO') . " -->";
    
} catch (Exception $e) {
    die("Clearance check error: " . $e->getMessage());
}

$is_student_active = ($student['status'] === 'active');

// NEW LOGIC: Student can submit requests if:
// 1. Account is active AND 
// 2. Doesn't have clearance for current academic year
$can_submit_requests = $is_student_active && !$has_current_clearance;

// Debug submission status
echo "<!-- Debug: Can submit requests = " . ($can_submit_requests ? 'YES' : 'NO') . " -->";

// Handle Single Clearance Request for ALL departments
if (isset($_POST['submit_all_clearance']) && $can_submit_requests) {
    echo "<!-- Debug: Processing single clearance request -->";
    
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        $message = "Please enter a reason for clearance.";
        $message_type = "error";
    } else {
        // Define all clearance tables
        $clearance_tables = [
            'Library' => 'library_clearance',
            'Cafeteria' => 'cafeteria_clearance', 
            'Dormitory' => 'dormitory_clearance',
            'Department' => 'department_clearance',
            'Registerar' => 'academicstaff_clearance'
        ];
        
        $success_count = 0;
        $error_count = 0;
        $already_exists_count = 0;
        $errors = [];
        
        // Start transaction to ensure data consistency
        $conn->begin_transaction();
        
        try {
            foreach ($clearance_tables as $clearance_type => $table_name) {
                echo "<!-- Debug: Processing $clearance_type -->";
                
                // Check if student already submitted for this academic year for this department
                $check_sql = "SELECT id FROM $table_name WHERE student_id = ? AND academic_year = ?";
                $check = $conn->prepare($check_sql);
                
                if (!$check) {
                    throw new Exception("Prepare failed for $table_name: " . $conn->error);
                }
                
                $check->bind_param("si", $student['student_id'], $academic_year);
                $check->execute();
                $check_result = $check->get_result();
                
                if ($check_result->num_rows > 0) {
                    $already_exists_count++;
                    echo "<!-- Debug: $clearance_type already exists -->";
                    continue; // Skip if already exists
                }
                
                // Insert into each clearance table
                $insert_sql = "INSERT INTO $table_name (student_id, name, last_name, department, reason, academic_year, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($insert_sql);
                
                if (!$stmt) {
                    throw new Exception("Prepare failed for insert into $table_name: " . $conn->error);
                }
                
                $stmt->bind_param("sssssi", 
                    $student['student_id'],
                    $student['name'],
                    $student['last_name'],
                    $student['department'],
                    $reason,
                    $academic_year
                );
                
                if ($stmt->execute()) {
                    $success_count++;
                    echo "<!-- Debug: $clearance_type inserted successfully -->";
                } else {
                    $error_count++;
                    $errors[] = "$clearance_type: " . $stmt->error;
                    echo "<!-- Debug: $clearance_type failed: " . $stmt->error . " -->";
                }
            }
            
            // Commit transaction if all inserts were successful
            $conn->commit();
            echo "<!-- Debug: Transaction committed -->";
            
            if ($success_count > 0) {
                $message = "Clearance requests submitted successfully! ";
                $message .= "Created $success_count new clearance requests. ";
                
                if ($already_exists_count > 0) {
                    $message .= "$already_exists_count departments already had pending requests. ";
                }
                
                if ($error_count > 0) {
                    $message .= "$error_count departments failed to process. ";
                    $message .= "Errors: " . implode(", ", $errors);
                    $message_type = "warning";
                } else {
                    $message_type = "success";
                }
                
                // Clear the form data
                $_POST = array();
            } else {
                $message = "No new clearance requests were created. You may have already submitted requests for all departments.";
                $message_type = "info";
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = "Error submitting clearance requests: " . $e->getMessage();
            $message_type = "error";
            echo "<!-- Debug: Transaction rolled back: " . $e->getMessage() . " -->";
        }
    }
}

// Get messages from session (for backward compatibility)
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = "error";
    unset($_SESSION['error_message']);
}

// Debug final state
echo "<!-- Debug: Final message = " . htmlspecialchars($message) . " -->";
echo "<!-- Debug: Selected option = " . htmlspecialchars($selected_option) . " -->";
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

    .clearance-container {
        max-width: min(800px, 95%);
        margin: 0;
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        height: auto;
    }

    .clearance-container h2 {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 20px;
        font-size: 24px;
        padding-bottom: 10px;
        border-bottom: 2px solid #bdc3c7;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--content-text);
    }

    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 15px;
        transition: all 0.3s ease;
        box-sizing: border-box;
        background: white;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.2);
    }

    .form-control[readonly] {
        background-color: #f8f9fa;
        cursor: not-allowed;
        color: #6c757d;
    }

    .form-control[disabled] {
        background-color: #e9ecef;
        cursor: not-allowed;
        color: #6c757d;
        opacity: 0.7;
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

    .btn {
        display: block;
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
        margin-top: 20px;
    }

    .btn:hover {
        background: var(--hover-color);
        transform: translateY(-2px);
    }

    .btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
        transform: none;
        opacity: 0.6;
    }

    .btn:disabled:hover {
        background: #6c757d;
        transform: none;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 6px;
        font-weight: bold;
        position: relative;
        animation: slideIn 0.5s ease-out;
        display: flex;
        align-items: center;
        gap: 10px;
        border-left: 4px solid;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border-left-color: #28a745;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border-left-color: #dc3545;
    }

    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border-left-color: #ffc107;
    }

    .alert-info {
        background-color: #cce7ff;
        color: #004085;
        border-left-color: #3498db;
    }

    .alert-close {
        margin-left: auto;
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        color: inherit;
        opacity: 0.7;
        padding: 0 5px;
    }

    .alert-close:hover {
        opacity: 1;
    }

    .alert-timer {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 4px;
        background-color: rgba(0,0,0,0.1);
        width: 100%;
    }

    .alert-timer-progress {
        height: 100%;
        width: 100%;
        background-color: inherit;
        animation: timer 5s linear forwards;
    }

    .academic-year-badge {
        display: inline-block;
        padding: 8px 16px;
        background: #3498db;
        color: white;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .clearance-status {
        text-align: center;
        padding: 15px;
        margin-bottom: 20px;
        background: #f8f9fa;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        font-weight: 500;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 10px;
    }

    .status-available {
        background-color: #d4edda;
        color: #155724;
    }

    .status-completed {
        background-color: #f8d7da;
        color: #721c24;
    }

    .status-inactive {
        background-color: #fff3cd;
        color: #856404;
    }

    .student-status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 10px;
    }

    .student-active {
        background-color: #d4edda;
        color: #155724;
    }

    .student-inactive {
        background-color: #f8d7da;
        color: #721c24;
    }

    /* New styles for single clearance system */
    .bulk-request-info {
        background: #e8f5e8;
        border: 1px solid #28a745;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .bulk-request-info h4 {
        color: #155724;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    @keyframes timer {
        from { width: 100%; }
        to { width: 0%; }
    }

    @keyframes slideIn {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
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
        
        .clearance-container {
            padding: 25px;
            margin: 0 auto;
            max-width: 95%;
        }
        
        .clearance-container h2 {
            font-size: 20px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .clearance-container {
            padding: 20px;
            margin: 10px auto;
        }
        
        .clearance-container h2 {
            font-size: 18px;
        }
        
        .btn {
            padding: 12px;
        }
        
        .form-control {
            padding: 10px;
        }
    }
</style>

<div class="main-content">
    <div class="clearance-container">
        <h2>Clearance Request System</h2>
        
        <!-- Display Academic Year -->
        <div style="text-align: center; margin-bottom: 20px;">
            <span class="academic-year-badge">
                Academic Year: <?= $academic_year ?>-<?= $next_academic_year ?>
            </span>
        </div>
        
        <!-- Display Student Status -->
        <div class="clearance-status">
            Your Account Status: 
            <span class="student-status-badge student-<?= htmlspecialchars($student['status']) ?>">
                <?= strtoupper(htmlspecialchars($student['status'])) ?>
            </span>
        </div>
        
        <!-- Display Clearance Status -->
        <div class="clearance-status">
            Clearance Status: 
            <span class="status-badge status-<?= 
                !$is_student_active ? 'inactive' : 
                ($can_submit_requests ? 'available' : 'completed') 
            ?>">
                <?= 
                    !$is_student_active ? 'ACCOUNT INACTIVE' : 
                    ($can_submit_requests ? 'AVAILABLE' : 'COMPLETED') 
                ?>
            </span>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert <?= 
                $message_type == 'success' ? 'alert-success' : 
                ($message_type == 'error' ? 'alert-error' : 
                ($message_type == 'warning' ? 'alert-warning' : 'alert-info')) 
            ?>" id="messageAlert">
                <?= 
                    $message_type == 'success' ? '✅' : 
                    ($message_type == 'error' ? '❌' : 
                    ($message_type == 'warning' ? '⚠️' : 'ℹ️')) 
                ?>
                <span><?= htmlspecialchars($message) ?></span>
                <button class="alert-close" onclick="dismissAlert('messageAlert')" aria-label="Close message">&times;</button>
                <div class="alert-timer">
                    <div class="alert-timer-progress"></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Single Clearance Request System -->
        <div class="bulk-request-info">
            <h4>🚀 Submit Once, Clear Everywhere</h4>
            <p>Submit a single clearance request that will automatically create clearance records for all departments:</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>📚 Library Clearance</li>
                <li>🍽️ Cafeteria Clearance</li>
                <li>🏠 Dormitory Clearance</li>
                <li>🏛️ Department Clearance</li>
                <li>🎓 Registrar Clearance</li>
            </ul>
        </div>

        <?php if (!$is_student_active): ?>
            <div class="alert alert-warning">
                ⚠️ <strong>Account Inactive</strong><br>
                You cannot submit clearance requests. Please contact the registrar office to activate your account.
            </div>
        <?php elseif (!$can_submit_requests): ?>
            <div class="alert alert-info">
                ✅ <strong>Clearance Completed</strong><br>
                You have successfully completed the clearance process for the <?= $academic_year ?>-<?= $next_academic_year ?> academic year. 
                New clearance requests will automatically open when the next academic year begins in September.
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="submit_all_clearance" value="1">
                
                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['name']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['last_name']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Department:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['department']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Clearance:</label>
                    <textarea class="form-control" name="reason" id="reason" rows="4" required 
                              placeholder="Enter your reason for clearance ..."><?= isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : '' ?></textarea>
                </div>

                <button type="submit" class="btn" style="background: #28a745;">
                    🚀 Submit Clearance to All Departments
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // Auto-dismiss alert after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                dismissAlert(alert.id);
            }, 5000);
        });
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
</script>