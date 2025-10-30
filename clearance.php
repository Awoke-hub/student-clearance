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

// Get current academic year - FIXED to match your database format
$current_year = date('Y');
$next_year = $current_year + 1;
$academic_year = $current_year . '-' . $next_year; 

// Debug academic year
echo "<!-- Debug: Academic year = " . $academic_year . " -->";

// =================== CHECK CLEARANCE SYSTEM STATUS ===================
$system_active = false;
$system_message = "";
$current_server_time = time(); // Server timestamp

// Get clearance settings
try {
    // Check if clearance_settings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'clearance_settings'");
    
    if ($table_check->num_rows > 0) {
        // Get clearance system status for current academic year
        $settings_stmt = $conn->prepare("SELECT start_date, end_date, is_active FROM clearance_settings WHERE academic_year = ?");
        $settings_stmt->bind_param("s", $academic_year);
        $settings_stmt->execute();
        $settings_result = $settings_stmt->get_result();
        
        if ($settings_result->num_rows > 0) {
            $settings = $settings_result->fetch_assoc();
            
            // Convert dates to timestamps for comparison
            $start_timestamp = strtotime($settings['start_date']);
            $end_timestamp = strtotime($settings['end_date']);
            
            // Debug the settings found
            echo "<!-- Debug: Settings found - start_date: " . $settings['start_date'] . ", end_date: " . $settings['end_date'] . ", is_active: " . $settings['is_active'] . " -->";
            echo "<!-- Debug: Start timestamp: " . $start_timestamp . " -->";
            echo "<!-- Debug: End timestamp: " . $end_timestamp . " -->";
            echo "<!-- Debug: Current server timestamp: " . $current_server_time . " -->";
            
            if ($settings['is_active']) {
                if ($current_server_time >= $start_timestamp && $current_server_time <= $end_timestamp) {
                    $system_active = true;
                    $system_message = "Clearance system is OPEN until " . date('F j, Y g:i A', $end_timestamp);
                } elseif ($current_server_time < $start_timestamp) {
                    $system_active = false;
                    $system_message = "Clearance system opens on " . date('F j, Y g:i A', $start_timestamp);
                } else {
                    $system_active = false;
                    $system_message = "Clearance system closed on " . date('F j, Y g:i A', $end_timestamp);
                }
            } else {
                $system_active = false;
                $system_message = "Clearance system is currently CLOSED by administration";
            }
        } else {
            // If no settings found for current academic year
            $system_message = "Clearance system settings not configured for academic year " . $academic_year;
        }
        $settings_stmt->close();
    } else {
        $system_message = "Clearance system not configured. Please contact administrator.";
    }
} catch (Exception $e) {
    $system_message = "Error checking system status: " . $e->getMessage();
}

// Debug system status
echo "<!-- Debug: System active = " . ($system_active ? 'YES' : 'NO') . " -->";
echo "<!-- Debug: System message = " . htmlspecialchars($system_message) . " -->";

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
    // Use the same academic year format
    $stmt = $conn->prepare("SELECT status FROM final_clearance WHERE student_id = ? AND academic_year = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $_SESSION['student_id'], $academic_year);
    $stmt->execute();
    $clearance_result = $stmt->get_result();

    $has_current_clearance = ($clearance_result->num_rows > 0);
    
    // Debug clearance status
    echo "<!-- Debug: Has current clearance = " . ($has_current_clearance ? 'YES' : 'NO') . " -->";
    
} catch (Exception $e) {
    die("Clearance check error: " . $e->getMessage());
}

$is_student_active = ($student['status'] === 'active');

// UPDATED LOGIC: Student can submit requests if:
// 1. Account is active AND 
// 2. Doesn't have clearance for current academic year
// 3. Clearance system is active
$can_submit_requests = $is_student_active && !$has_current_clearance && $system_active;

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
                
                $check->bind_param("ss", $student['student_id'], $academic_year);
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
                
                $stmt->bind_param("ssssss",
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

// Calculate time remaining for display (using server time)
$time_remaining = 0;
$days_remaining = 0;
$hours_remaining = 0;
$minutes_remaining = 0;

if ($system_active && isset($settings)) {
    $end_timestamp = strtotime($settings['end_date']);
    $time_remaining = $end_timestamp - $current_server_time;
    
    if ($time_remaining > 0) {
        $days_remaining = floor($time_remaining / (60 * 60 * 24));
        $hours_remaining = floor(($time_remaining % (60 * 60 * 24)) / (60 * 60));
        $minutes_remaining = floor(($time_remaining % (60 * 60)) / 60);
    }
}

// Debug final state
echo "<!-- Debug: Final message = " . htmlspecialchars($message) . " -->";
echo "<!-- Debug: Time remaining: " . $time_remaining . " seconds -->";
echo "<!-- Debug: Days remaining: " . $days_remaining . " -->";
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
        max-width: min(1200px, 95%);
        margin: 0;
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        height: auto;
    }

    .clearance-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid #3498db;
    }

    .clearance-header h2 {
        color: var(--primary-color);
        font-size: 2.5rem;
        margin-bottom: 10px;
        font-weight: bold;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    .clearance-header .subtitle {
        color: #666;
        font-size: 1.1rem;
        font-style: italic;
    }

    /* Simplified Status Display */
    .status-summary {
        background: #f8f9fa;
        padding: 18px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
        border-left: 4px solid #3498db;
    }

    .status-icon {
        font-size: 2.5rem;
        margin-bottom: 12px;
    }

    .status-title {
        font-size: 1.3rem;
        font-weight: bold;
        margin-bottom: 8px;
        color: #2c3e50;
    }

    .status-message {
        font-size: 1rem;
        color: #666;
        line-height: 1.4;
    }

    /* Deadline Information */
    .deadline-info {
        background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        text-align: center;
    }

    .deadline-info.urgent {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        border: 1px solid #dc3545;
    }

    .deadline-info.expired {
        background: linear-gradient(135deg, #e9ecef, #dee2e6);
        border: 1px solid #6c757d;
        color: #6c757d;
    }

    .deadline-icon {
        font-size: 1.5rem;
        margin-bottom: 8px;
    }

    .deadline-text {
        font-size: 0.9rem;
        color: #856404;
        font-weight: 500;
        line-height: 1.4;
    }

    .deadline-info.urgent .deadline-text {
        color: #721c24;
    }

    .deadline-info.expired .deadline-text {
        color: #495057;
    }

    .deadline-command {
        font-size: 0.85rem;
        margin-top: 8px;
        font-weight: 600;
        color: #856404;
    }

    .deadline-info.urgent .deadline-command {
        color: #721c24;
    }

    .deadline-info.expired .deadline-command {
        color: #495057;
    }

    .time-remaining {
        font-size: 1rem;
        font-weight: bold;
        margin: 5px 0;
        color: #d35400;
        font-family: 'Courier New', monospace;
    }

    .deadline-info.urgent .time-remaining {
        color: #c0392b;
    }

    .deadline-info.expired .time-remaining {
        color: #6c757d;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        color: var(--content-text);
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
        transition: all 0.3s ease;
        box-sizing: border-box;
        background: white;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 2px rgba(44, 62, 80, 0.2);
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
        min-height: 90px;
        resize: vertical;
    }

    .char-counter {
        font-size: 0.8rem;
        color: #666;
        text-align: right;
        margin-top: 5px;
    }

    .char-counter.warning {
        color: #ffc107;
        font-weight: 600;
    }

    .char-counter.error {
        color: #dc3545;
        font-weight: 600;
    }

    .btn {
        display: block;
        width: 100%;
        padding: 12px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 18px;
    }

    .btn:hover {
        background: var(--hover-color);
        transform: translateY(-1px);
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
        padding: 12px 15px;
        margin-bottom: 18px;
        border-radius: 5px;
        font-weight: bold;
        position: relative;
        animation: slideIn 0.5s ease-out;
        display: flex;
        align-items: center;
        gap: 8px;
        border-left: 4px solid;
        font-size: 0.9rem;
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
        font-size: 1.1rem;
        cursor: pointer;
        color: inherit;
        opacity: 0.7;
        padding: 0 4px;
    }

    .alert-close:hover {
        opacity: 1;
    }

    .alert-timer {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        background-color: rgba(0,0,0,0.1);
        width: 100%;
    }

    .alert-timer-progress {
        height: 100%;
        width: 100%;
        background-color: inherit;
        animation: timer 5s linear forwards;
    }

    /* New styles for single clearance system */
    .bulk-request-info {
        background: linear-gradient(135deg, #e8f5e8, #d4edda);
        border: 1px solid #28a745;
        border-radius: 8px;
        padding: 18px;
        margin-bottom: 20px;
    }
    
    .bulk-request-info h4 {
        color: #155724;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1.1rem;
    }

    .bulk-request-info ul {
        margin: 12px 0;
        padding-left: 20px;
    }

    .bulk-request-info li {
        margin-bottom: 6px;
        font-size: 0.9rem;
    }

    .bulk-request-info p {
        font-size: 0.85rem;
        margin: 8px 0;
    }

    /* Progress bar for time remaining */
    .time-progress {
        margin: 10px 0;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: #28a745;
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    .progress-fill.urgent {
        background: #dc3545;
    }

    .progress-fill.warning {
        background: #ffc107;
    }

    @keyframes timer {
        from { width: 100%; }
        to { width: 0%; }
    }

    @keyframes slideIn {
        from { transform: translateY(-15px); opacity: 0; }
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
            padding: 20px;
            margin: 0 auto;
            max-width: 95%;
        }
        
        .clearance-header h2 {
            font-size: 2rem;
        }
        
        .status-icon {
            font-size: 2rem;
        }
        
        .status-title {
            font-size: 1.1rem;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .clearance-container {
            padding: 18px;
            margin: 10px auto;
        }
        
        .clearance-header h2 {
            font-size: 1.8rem;
        }
        
        .btn {
            padding: 10px;
            font-size: 14px;
        }
        
        .form-control {
            padding: 8px;
            font-size: 13px;
        }
    }
</style>

<div class="main-content">
    <div class="clearance-container">
        <!-- Header Section -->
        <div class="clearance-header">
            <h3>🎓 Clearance Request System</h3>
            <div class="subtitle">Submit your clearance requests for all departments in one go</div>
        </div>
        
        <!-- Deadline Information -->
        <?php if ($system_active && isset($settings)): 
            $end_timestamp = strtotime($settings['end_date']);
            $start_timestamp = strtotime($settings['start_date']);
            $total_duration = $end_timestamp - $start_timestamp;
            $progress = $total_duration > 0 ? min(100, max(0, (($end_timestamp - $current_server_time) / $total_duration) * 100)) : 0;
            
            $is_urgent = $days_remaining <= 2;
            $is_expired = $time_remaining <= 0;
        ?>
            <div class="deadline-info <?= $is_expired ? 'expired' : ($is_urgent ? 'urgent' : '') ?>">
                <div class="deadline-icon">
                    <?= $is_expired ? '⏰' : ($is_urgent ? '🚨' : '⏰') ?>
                </div>
                <div class="deadline-text">
                    <strong>Clearance Deadline:</strong> 
                    <?= date('F j, Y \a\t g:i A', $end_timestamp) ?>
                </div>
                <div class="time-remaining" id="timeRemaining">
                    <?php if (!$is_expired): ?>
                        <?= $days_remaining ?> days, <?= $hours_remaining ?> hours, <?= $minutes_remaining ?> minutes remaining
                    <?php else: ?>
                        Time Expired
                    <?php endif; ?>
                </div>
                
                <?php if (!$is_expired): ?>
                <div class="time-progress">
                    <div class="progress-bar">
                        <div class="progress-fill <?= $is_urgent ? 'urgent' : ($days_remaining <= 7 ? 'warning' : '') ?>" 
                             id="timeProgress" 
                             style="width: <?= $progress ?>%">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="deadline-command">
                    <?php if ($is_expired): ?>
                        ❌ <strong>Deadline Passed:</strong> Clearance system is no longer accepting submissions
                    <?php elseif ($days_remaining > 7): ?>
                        📋 <strong>Plan ahead:</strong> Submit your clearance request early to avoid last-minute issues
                    <?php elseif ($days_remaining > 3): ?>
                        ⚡ <strong>Time to act:</strong> Complete your clearance submission this week
                    <?php elseif ($days_remaining > 1): ?>
                        🚨 <strong>Urgent:</strong> Submit your clearance immediately to meet the deadline
                    <?php else: ?>
                        🔥 <strong>Critical:</strong> Final day! Submit your clearance NOW before time runs out
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Single Status Summary -->
        <div class="status-summary">
            <?php if (!$system_active): ?>
                <div class="status-icon">🔒</div>
                <div class="status-title">Clearance System Closed</div>
                <div class="status-message">
                    The clearance system is currently not available for submissions.<br>
                    <strong>Reason:</strong> <?= htmlspecialchars($system_message) ?>
                </div>
            <?php elseif (!$is_student_active): ?>
                <div class="status-icon">⚠️</div>
                <div class="status-title">Account Inactive</div>
                <div class="status-message">
                    You cannot submit clearance requests with an inactive account.<br>
                    Please contact the registrar office to activate your student account.
                </div>
            <?php elseif (!$can_submit_requests): ?>
                <div class="status-icon">✅</div>
                <div class="status-title">Clearance Completed</div>
                <div class="status-message">
                    You have successfully completed the clearance process for the <?= $academic_year ?> academic year.<br>
                    New clearance requests will open when the next academic year begins.
                </div>
            <?php else: ?>
                <!-- No content shown when student is eligible to submit requests -->
            <?php endif; ?>
        </div>
        
        <!-- Success/Error Messages -->
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
        <?php if ($system_active && $is_student_active && $can_submit_requests): ?>
            <div class="bulk-request-info">
                <h4>🚀 Submit Once, Clear Everywhere</h4>
                <p>Submit a single clearance request that will automatically create clearance records for all departments:</p>
                <ul>
                    <li>📚 Library Clearance</li>
                    <li>🍽️ Cafeteria Clearance</li>
                    <li>🏠 Dormitory Clearance</li>
                    <li>🏛️ Department Clearance</li>
                    <li>🎓 Registrar Clearance</li>
                </ul>
                <?php if (isset($days_remaining) && $time_remaining > 0): ?>
                    <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #ffc107;">
                        <strong>⏰ Deadline Alert:</strong> 
                        <?php if ($days_remaining > 7): ?>
                            You have <strong><?= $days_remaining ?> days</strong> to complete your clearance. Submit early!
                        <?php elseif ($days_remaining > 3): ?>
                            Only <strong><?= $days_remaining ?> days</strong> left! Complete your clearance this week.
                        <?php elseif ($days_remaining > 1): ?>
                            <strong>Urgent:</strong> Only <?= $days_remaining ?> days remaining! Submit immediately.
                        <?php else: ?>
                            <strong>Final Day:</strong> Submit NOW before the deadline closes!
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <p style="margin-top: 8px; font-style: italic; color: #155724;">
                    <strong>Note:</strong> Your request will be reviewed by each department separately.
                </p>
            </div>

            <form method="POST" id="clearanceForm">
                <input type="hidden" name="submit_all_clearance" value="1">
                
                <div class="form-group">
                    <label>👤 First Name:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['name']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>👤 Last Name:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['last_name']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>🏛️ Department:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['department']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="reason">📝 Reason for Clearance:</label>
                    <textarea class="form-control" name="reason" id="reason" rows="4" required 
                              maxlength="500"
                              placeholder="Please explain why you need clearance Maximum 500 characters..."><?= isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : '' ?></textarea>
                    <div class="char-counter" id="charCounter">
                        <span id="charCount">0</span>/500 characters
                    </div>
                </div>

                <button type="submit" class="btn" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    🚀 Submit Clearance to All Departments
                </button>
                
                <?php if (isset($days_remaining) && $days_remaining <= 3 && $time_remaining > 0): ?>
                    <div style="text-align: center; margin-top: 10px; font-size: 0.85rem; color: #d35400; font-weight: 600;">
                        ⏰ Don't delay! Complete your submission now to meet the deadline.
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // Server time in milliseconds (from PHP)
    const serverTime = <?= $current_server_time * 1000 ?>;
    const endTime = <?= isset($end_timestamp) ? $end_timestamp * 1000 : 0 ?>;
    const startTime = <?= isset($start_timestamp) ? $start_timestamp * 1000 : 0 ?>;
    
    // Calculate client-server time difference
    const clientTime = new Date().getTime();
    const timeDiff = clientTime - serverTime;
    
    console.log('Server Time:', new Date(serverTime));
    console.log('Client Time:', new Date(clientTime));
    console.log('Time Difference:', timeDiff + 'ms');
    console.log('End Time:', new Date(endTime));
    
    // Real-time countdown update using SERVER TIME as reference
    function updateCountdown() {
        if (endTime === 0) return;
        
        const now = new Date().getTime() - timeDiff; // Adjust for time difference
        const timeRemaining = endTime - now;
        
        if (timeRemaining > 0) {
            const days = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeRemaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeRemaining % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);
            
            const timeRemainingElement = document.getElementById('timeRemaining');
            if (timeRemainingElement) {
                timeRemainingElement.textContent = days + ' days, ' + hours + ' hours, ' + minutes + ' minutes remaining';
            }
            
            // Update progress bar
            const totalDuration = endTime - startTime;
            if (totalDuration > 0) {
                const progress = Math.min(100, Math.max(0, (timeRemaining / totalDuration) * 100));
                const progressFill = document.getElementById('timeProgress');
                if (progressFill) {
                    progressFill.style.width = progress + '%';
                    
                    // Update progress bar color based on urgency
                    if (days <= 2) {
                        progressFill.className = 'progress-fill urgent';
                    } else if (days <= 7) {
                        progressFill.className = 'progress-fill warning';
                    } else {
                        progressFill.className = 'progress-fill';
                    }
                }
            }
        } else {
            const timeRemainingElement = document.getElementById('timeRemaining');
            if (timeRemainingElement) {
                timeRemainingElement.textContent = 'Time Expired';
            }
            const progressFill = document.getElementById('timeProgress');
            if (progressFill) {
                progressFill.style.width = '0%';
                progressFill.className = 'progress-fill urgent';
            }
            
            // Disable form if time expired
            const form = document.getElementById('clearanceForm');
            if (form) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = '❌ Deadline Passed - Submissions Closed';
                    submitBtn.style.background = '#6c757d';
                }
            }
        }
    }
    
    // Update countdown every second for accuracy
    setInterval(updateCountdown, 1000);
    
    // Initial update
    updateCountdown();

    // Auto-dismiss alert after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                dismissAlert(alert.id);
            }, 5000);
        });

        // Character counter for reason textarea
        const reasonTextarea = document.getElementById('reason');
        const charCounter = document.getElementById('charCounter');
        const charCount = document.getElementById('charCount');

        if (reasonTextarea) {
            // Initialize character count
            updateCharCount();

            // Update on input
            reasonTextarea.addEventListener('input', updateCharCount);

            function updateCharCount() {
                const length = reasonTextarea.value.length;
                charCount.textContent = length;

                // Update counter color based on length
                if (length > 450) {
                    charCounter.className = 'char-counter error';
                } else if (length > 400) {
                    charCounter.className = 'char-counter warning';
                } else {
                    charCounter.className = 'char-counter';
                }
            }
        }

        // Form validation
        const form = document.getElementById('clearanceForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const reason = document.getElementById('reason').value.trim();
                
                if (reason.length === 0) {
                    e.preventDefault();
                    alert('Please enter a reason for clearance.');
                    return false;
                }

                if (reason.length > 500) {
                    e.preventDefault();
                    alert('Reason must be 500 characters or less.');
                    return false;
                }
                
                // Urgent deadline warning
                const timeRemaining = endTime - (new Date().getTime() - timeDiff);
                const daysRemaining = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));
                
                if (daysRemaining <= 1) {
                    if (!confirm('🚨 FINAL DAY ALERT! This is your last chance to submit clearance. Are you sure you want to proceed?')) {
                        e.preventDefault();
                        return false;
                    }
                } else if (daysRemaining <= 3) {
                    if (!confirm('⚠️ URGENT: Only ' + daysRemaining + ' days left until deadline. Are you ready to submit?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
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
</script>