<?php
session_start();
ob_start();
include '../includes/db.php';
include 'partials/menu.php';

// Ensure only system admins can access
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'system_admin') {
    header("Location: ../login.php");
    exit();
}

$form_errors = [];
$success_msg = '';

// Flash messages function
if (!isset($_SESSION['flash_messages'])) {
    $_SESSION['flash_messages'] = [];
}
function add_flash_message($type, $msg) {
    $_SESSION['flash_messages'][] = ['type' => $type, 'msg' => $msg];
}

// Get current academic year
$current_year = date('Y');
$next_year = $current_year + 1;
$academic_year = $current_year . '-' . $next_year;

// Get current clearance settings
$settings_result = $conn->query("SELECT * FROM clearance_settings WHERE academic_year = '$academic_year' LIMIT 1");
$clearance_settings = $settings_result->fetch_assoc();

// If no settings exist for current academic year, create default ones
if (!$clearance_settings) {
    $default_start = date('Y-m-d H:i:s');
    $default_end = date('Y-m-d H:i:s', strtotime('+7 days')); // Changed from 30 to 7 days
    
    $stmt = $conn->prepare("INSERT INTO clearance_settings (academic_year, start_date, end_date, is_active) VALUES (?, ?, ?, ?)");
    $is_active = 0; // Inactive by default
    $stmt->bind_param("sssi", $academic_year, $default_start, $default_end, $is_active);
    $stmt->execute();
    $stmt->close();
    
    // Reload settings
    $settings_result = $conn->query("SELECT * FROM clearance_settings WHERE academic_year = '$academic_year' LIMIT 1");
    $clearance_settings = $settings_result->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_clearance_settings'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Convert datetime-local format to MySQL datetime format
    $start_date = date('Y-m-d H:i:s', strtotime($start_date));
    $end_date = date('Y-m-d H:i:s', strtotime($end_date));
    
    // Validation
    if (empty($start_date) || empty($end_date)) {
        $form_errors[] = "Start date and end date are required.";
    }
    
    if (strtotime($start_date) >= strtotime($end_date)) {
        $form_errors[] = "End date must be after start date.";
    }
    
    if (empty($form_errors)) {
        // Update clearance settings
        $stmt = $conn->prepare("UPDATE clearance_settings SET start_date = ?, end_date = ?, is_active = ?, updated_at = NOW() WHERE academic_year = ?");
        $stmt->bind_param("ssis", $start_date, $end_date, $is_active, $academic_year);
        
        if ($stmt->execute()) {
            add_flash_message('success', 'Clearance settings updated successfully!');
            
            // Update the local settings variable
            $clearance_settings['start_date'] = $start_date;
            $clearance_settings['end_date'] = $end_date;
            $clearance_settings['is_active'] = $is_active;
            
            // Log the change
            error_log("Clearance settings updated for $academic_year: Start=$start_date, End=$end_date, Active=$is_active");
        } else {
            $form_errors[] = "Failed to update clearance settings. Please try again.";
        }
        $stmt->close();
    }
}

// Handle quick actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'activate':
            $stmt = $conn->prepare("UPDATE clearance_settings SET is_active = 1 WHERE academic_year = ?");
            $stmt->bind_param("s", $academic_year);
            if ($stmt->execute()) {
                add_flash_message('success', 'Clearance system activated successfully!');
                $clearance_settings['is_active'] = 1;
            }
            $stmt->close();
            break;
            
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE clearance_settings SET is_active = 0 WHERE academic_year = ?");
            $stmt->bind_param("s", $academic_year);
            if ($stmt->execute()) {
                add_flash_message('success', 'Clearance system deactivated successfully!');
                $clearance_settings['is_active'] = 0;
            }
            $stmt->close();
            break;
            
        case 'extend_1_day':
            $new_end_date = date('Y-m-d H:i:s', strtotime($clearance_settings['end_date'] . ' +1 day'));
            $stmt = $conn->prepare("UPDATE clearance_settings SET end_date = ? WHERE academic_year = ?");
            $stmt->bind_param("ss", $new_end_date, $academic_year);
            if ($stmt->execute()) {
                add_flash_message('success', 'Clearance period extended by 1 day!');
                $clearance_settings['end_date'] = $new_end_date;
            }
            $stmt->close();
            break;
            
        case 'extend_3_days':
            $new_end_date = date('Y-m-d H:i:s', strtotime($clearance_settings['end_date'] . ' +3 days'));
            $stmt = $conn->prepare("UPDATE clearance_settings SET end_date = ? WHERE academic_year = ?");
            $stmt->bind_param("ss", $new_end_date, $academic_year);
            if ($stmt->execute()) {
                add_flash_message('success', 'Clearance period extended by 3 days!');
                $clearance_settings['end_date'] = $new_end_date;
            }
            $stmt->close();
            break;
            
        case 'start_now':
            $new_start_date = date('Y-m-d H:i:s');
            $new_end_date = date('Y-m-d H:i:s', strtotime('+7 days')); // Changed from 30 to 7 days
            $stmt = $conn->prepare("UPDATE clearance_settings SET start_date = ?, end_date = ?, is_active = 1 WHERE academic_year = ?");
            $stmt->bind_param("sss", $new_start_date, $new_end_date, $academic_year);
            if ($stmt->execute()) {
                add_flash_message('success', 'Clearance system started immediately for 7 days!'); // Updated message
                $clearance_settings['start_date'] = $new_start_date;
                $clearance_settings['end_date'] = $new_end_date;
                $clearance_settings['is_active'] = 1;
            }
            $stmt->close();
            break;
    }
    
    header("Location: clearance-settings.php");
    exit();
}

// Get current server time
$server_time = time();
$server_time_display = date('Y-m-d H:i:s');

// Calculate time status using SERVER TIME
$start_timestamp = strtotime($clearance_settings['start_date']);
$end_timestamp = strtotime($clearance_settings['end_date']);
$is_active_setting = $clearance_settings['is_active'];

// Determine system status based on SERVER TIME
$system_status = 'INACTIVE';
$status_class = 'status-inactive';
$status_icon = '🔴';

if ($is_active_setting) {
    if ($server_time < $start_timestamp) {
        $system_status = 'SCHEDULED';
        $status_class = 'status-scheduled';
        $status_icon = '🟡';
    } elseif ($server_time <= $end_timestamp) {
        $system_status = 'ACTIVE';
        $status_class = 'status-active';
        $status_icon = '🟢';
    } else {
        $system_status = 'EXPIRED';
        $status_class = 'status-expired';
        $status_icon = '🔴';
    }
}

// Calculate time remaining using SERVER TIME
$time_remaining = $end_timestamp - $server_time;
$total_duration = $end_timestamp - $start_timestamp;

if ($time_remaining > 0) {
    $days_remaining = floor($time_remaining / (60 * 60 * 24));
    $hours_remaining = floor(($time_remaining % (60 * 60 * 24)) / (60 * 60));
} else {
    $days_remaining = 0;
    $hours_remaining = 0;
}

// Calculate time until start (if scheduled)
$time_until_start = $start_timestamp - $server_time;
if ($time_until_start > 0) {
    $days_until_start = floor($time_until_start / (60 * 60 * 24));
    $hours_until_start = floor(($time_until_start % (60 * 60 * 24)) / (60 * 60));
} else {
    $days_until_start = 0;
    $hours_until_start = 0;
}

// Get clearance statistics - CORRECTED SUBMISSION PROGRESS CALCULATION
$total_students = $conn->query("SELECT COUNT(*) as total FROM student WHERE status = 'active'")->fetch_assoc()['total'];

// Count students with pending requests (not approved) from library_clearance
$request_students = $conn->query("
    SELECT COUNT(DISTINCT student_id) as requested 
    FROM library_clearance 
    WHERE academic_year = '$academic_year' 
    AND status != 'approved' 
    AND status != 'cleared'
")->fetch_assoc()['requested'];

// Count students with approved clearance from final_clearance table
$approved_students = $conn->query("
    SELECT COUNT(DISTINCT student_id) as approved 
    FROM final_clearance 
    WHERE academic_year = '$academic_year' 
    AND status = 'approved'
")->fetch_assoc()['approved'];

// Calculate total students in clearance process (requested + approved)
$students_in_process = $request_students + $approved_students;

// Calculate submission progress - ensure it never exceeds 100%
$submission_rate = $total_students > 0 ? min(100, round(($students_in_process / $total_students) * 100, 1)) : 0;

// For display in the card
$submitted_clearances = $students_in_process;
$pending_students = $total_students - $students_in_process;
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Clearance Settings - Admin Panel</title>
    <style>
        body { font-family: Arial, sans-serif; background: white; margin: 0; padding: 0; }
        .cs-header { background: #008B8B; color: white; padding: 15px; text-align: center; position: relative; }
        .cs-logout-btn { position: absolute; right: 20px; top: 15px; background: #ff4444; color: white; padding: 8px 12px; text-decoration: none; border-radius: 3px; }
        .cs-logout-btn:hover { background: #cc0000; }
        .cs-container { max-width: 1200px; margin: 20px auto; background: white; padding: 20px; border-radius: 5px; }
        
        /* Server Time Display */
        .cs-server-time {
            background: #e7f3ff;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #0b105aff;
        }
        
        /* Status Cards */
        .cs-status-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .cs-status-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #008B8B;
        }
        
        .cs-status-card.system-status { border-left-color: #0b105aff; }
        .cs-status-card.time-remaining { border-left-color: #28a745; }
        .cs-status-card.completion { border-left-color: #ffc107; }
        .cs-status-card.time-until { border-left-color: #fd7e14; }
        
        .cs-card-icon { font-size: 2rem; margin-bottom: 10px; }
        .cs-card-value { font-size: 1.5rem; font-weight: bold; margin: 10px 0; color: #0b105aff; }
        .cs-card-label { color: #666; font-size: 0.9rem; margin-bottom: 10px; font-weight: 600; }
        
        .cs-status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.8rem;
            margin-top: 8px;
        }
        
        .cs-status-active { background: #d4edda; color: #155724; }
        .cs-status-inactive { background: #f8d7da; color: #721c24; }
        .cs-status-expired { background: #fff3cd; color: #856404; }
        .cs-status-scheduled { background: #ffe5d0; color: #e65100; }
        
        /* Quick Actions */
        .cs-quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .cs-action-btn {
            padding: 12px 15px;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            color: white;
        }
        
        .cs-action-btn.start-now { background: #6f42c1; }
        .cs-action-btn.activate { background: #28a745; }
        .cs-action-btn.deactivate { background: #dc3545; }
        .cs-action-btn.extend { background: #ffc107; color: #212529; }
        .cs-action-btn.schedule { background: #fd7e14; }
        
        .cs-action-btn:hover { opacity: 0.9; }
        
        /* Settings Form */
        .cs-settings-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .cs-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .cs-form-group {
            margin-bottom: 15px;
        }
        
        .cs-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #0b105aff;
            font-size: 0.9rem;
        }
        
        .cs-form-group input[type="datetime-local"],
        .cs-form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .cs-checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .cs-checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #008B8B;
        }
        
        .cs-checkbox-group label {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .cs-submit-btn {
            background: #008B8B;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .cs-submit-btn:hover { background: #006B6B; }
        
        /* Current Settings Info */
        .cs-current-settings {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            margin-top: 20px;
        }
        
        .cs-current-settings h3 {
            color: #007bff;
            margin-bottom: 10px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .cs-settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            font-size: 0.85rem;
        }
        
        .cs-settings-grid div {
            padding: 8px;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .cs-settings-grid strong {
            color: #0b105aff;
            display: block;
            margin-bottom: 3px;
            font-size: 0.8rem;
        }
        
        .cs-time-display {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #0b105aff;
            font-size: 0.8rem;
        }
        
        /* Progress Bar */
        .cs-progress-container { margin-top: 8px; }
        .cs-progress-bar { width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .cs-progress-fill { height: 100%; background: #28a745; transition: width 0.3s ease; border-radius: 4px; }
        .cs-progress-text { font-size: 0.75rem; margin-top: 5px; color: #666; font-weight: 600; }
        
        /* Alerts */
        .cs-messages { max-width: 100%; margin: 10px 0; padding: 10px; border-radius: 4px; }
        .cs-success-msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .cs-error-msg { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cs-form-grid { grid-template-columns: 1fr; }
            .cs-status-cards { grid-template-columns: 1fr; }
            .cs-quick-actions { grid-template-columns: 1fr; }
            .cs-settings-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="cs-header">
        <h1>Clearance System Settings</h1>
    </div>

    <div class="cs-container">
        <!-- Flash messages -->
        <?php if (!empty($_SESSION['flash_messages'])): ?>
            <div class="cs-messages">
                <?php foreach ($_SESSION['flash_messages'] as $msg): ?>
                    <div class="<?= $msg['type'] === 'success' ? 'cs-success-msg' : 'cs-error-msg' ?>">
                        <?= htmlspecialchars($msg['msg']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php $_SESSION['flash_messages'] = []; ?>
        <?php endif; ?>

        <!-- Server Time Display -->
        <div class="cs-server-time">
            <i class="fas fa-server"></i> Server Time: <?= $server_time_display ?>
        </div>

        <!-- Status Cards -->
        <div class="cs-status-cards">
            <div class="cs-status-card system-status">
                <div class="cs-card-icon">⚙️</div>
                <div class="cs-card-value"><?= $system_status ?></div>
                <div class="cs-card-label">System Status</div>
                <div class="cs-status-badge <?= $status_class ?>">
                    <?= $status_icon ?> <?= $system_status ?>
                </div>
            </div>
            
            <div class="cs-status-card time-remaining">
                <div class="cs-card-icon">⏰</div>
                <div class="cs-card-value cs-time-display" id="timeRemaining">
                    <?= $days_remaining ?>d <?= $hours_remaining ?>h
                </div>
                <div class="cs-card-label">Time Remaining</div>
                <div class="cs-progress-container">
                    <?php if ($total_duration > 0): ?>
                        <?php $progress = min(100, max(0, (($end_timestamp - $server_time) / $total_duration) * 100)); ?>
                        <div class="cs-progress-bar">
                            <div class="cs-progress-fill" id="timeProgress" style="width: <?= $progress ?>%"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($system_status === 'SCHEDULED'): ?>
            <div class="cs-status-card time-until">
                <div class="cs-card-icon">📅</div>
                <div class="cs-card-value cs-time-display" id="timeUntilStart">
                    <?= $days_until_start ?>d <?= $hours_until_start ?>h
                </div>
                <div class="cs-card-label">Starts In</div>
                <div class="cs-status-badge cs-status-scheduled">
                    🟡 SCHEDULED
                </div>
            </div>
            <?php endif; ?>
            
            <div class="cs-status-card completion">
                <div class="cs-card-icon">📊</div>
                <div class="cs-card-value"><?= $submission_rate ?>%</div>
                <div class="cs-card-label">Submission Progress</div>
                <div class="cs-progress-container">
                    <div class="cs-progress-bar">
                        <div class="cs-progress-fill" style="width: <?= $submission_rate ?>%"></div>
                    </div>
                </div>
                <div class="cs-progress-text">
                    <?= $submitted_clearances ?> of <?= $total_students ?> students
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="cs-quick-actions">
            <a href="?action=start_now" class="cs-action-btn start-now" onclick="return confirm('Start clearance system immediately for 7 days?')">
                <i class="fas fa-rocket"></i> Start Now (7 Days)
            </a>
            <a href="?action=activate" class="cs-action-btn activate" onclick="return confirm('Activate clearance system?')">
                <i class="fas fa-play"></i> Activate
            </a>
            <a href="?action=deactivate" class="cs-action-btn deactivate" onclick="return confirm('Deactivate clearance system?')">
                <i class="fas fa-stop"></i> Deactivate
            </a>
            <a href="?action=extend_1_day" class="cs-action-btn extend" onclick="return confirm('Extend clearance period by 1 day?')">
                <i class="fas fa-calendar-plus"></i> +1 Day
            </a>
            <a href="?action=extend_3_days" class="cs-action-btn schedule" onclick="return confirm('Extend clearance period by 3 days?')">
                <i class="fas fa-calendar-alt"></i> +3 Days
            </a>
        </div>
        
        <!-- Settings Form -->
        <form method="POST" class="cs-settings-form">
            <h2 style="color: #0b105aff; margin-bottom: 15px; font-size: 1.2rem;">
                <i class="fas fa-sliders-h"></i> Clearance Period Settings
            </h2>
            
            <div class="cs-form-grid">
                <div class="cs-form-group">
                    <label for="start_date"><i class="fas fa-play-circle"></i> Start Date & Time</label>
                    <input type="datetime-local" id="start_date" name="start_date" 
                           value="<?= date('Y-m-d\TH:i', strtotime($clearance_settings['start_date'])) ?>" 
                           required>
                    <small style="color: #666; font-size: 0.75rem; display: block; margin-top: 3px;">
                        When students can start submitting clearance requests
                    </small>
                </div>
                
                <div class="cs-form-group">
                    <label for="end_date"><i class="fas fa-stop-circle"></i> End Date & Time</label>
                    <input type="datetime-local" id="end_date" name="end_date" 
                           value="<?= date('Y-m-d\TH:i', strtotime($clearance_settings['end_date'])) ?>" 
                           required>
                    <small style="color: #666; font-size: 0.75rem; display: block; margin-top: 3px;">
                        Deadline for clearance submissions
                    </small>
                </div>
            </div>
            
            <div class="cs-form-group">
                <div class="cs-checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" value="1" 
                           <?= $clearance_settings['is_active'] ? 'checked' : '' ?>>
                    <label for="is_active">Enable Clearance System</label>
                </div>
                <small style="color: #666; display: block; margin-top: 3px; font-size: 0.75rem;">
                    When enabled, students can submit clearance requests according to the schedule
                </small>
            </div>
            
            <button type="submit" name="update_clearance_settings" class="cs-submit-btn">
                <i class="fas fa-save"></i> Save Clearance Settings
            </button>
        </form>
        
        <!-- Current Settings Info -->
        <div class="cs-current-settings">
            <h3><i class="fas fa-info-circle"></i> Current Settings Overview</h3>
            <div class="cs-settings-grid">
                <div>
                    <strong>Start Date & Time:</strong>
                    <span class="cs-time-display"><?= date('M j, Y g:i A', strtotime($clearance_settings['start_date'])) ?></span>
                </div>
                <div>
                    <strong>End Date & Time:</strong>
                    <span class="cs-time-display"><?= date('M j, Y g:i A', strtotime($clearance_settings['end_date'])) ?></span>
                </div>
                <div>
                    <strong>Total Duration:</strong>
                    <?php
                    $duration = strtotime($clearance_settings['end_date']) - strtotime($clearance_settings['start_date']);
                    $duration_days = floor($duration / (60 * 60 * 24));
                    $duration_hours = floor(($duration % (60 * 60 * 24)) / (60 * 60));
                    echo $duration_days . ' day(s) ' . $duration_hours . ' hour(s)';
                    ?>
                </div>
                <div>
                    <strong>System Status:</strong>
                    <span class="cs-status-badge <?= $status_class ?>"><?= $system_status ?></span>
                </div>
                <div>
                    <strong>Last Updated:</strong>
                    <?= date('M j, Y g:i A', strtotime($clearance_settings['updated_at'])) ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Server time in milliseconds (from PHP)
        const serverTime = <?= $server_time * 1000 ?>;
        const startTime = <?= $start_timestamp * 1000 ?>;
        const endTime = <?= $end_timestamp * 1000 ?>;
        
        // Calculate client-server time difference
        const clientTime = new Date().getTime();
        const timeDiff = clientTime - serverTime;
        
        // Real-time countdown update using SERVER TIME as reference
        function updateCountdown() {
            const now = new Date().getTime() - timeDiff; // Adjust for time difference
            const timeRemaining = endTime - now;
            const timeUntilStart = startTime - now;
            const totalDuration = endTime - startTime;
            
            // Update time remaining
            if (timeRemaining > 0) {
                const days = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeRemaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                
                const timeRemainingElement = document.getElementById('timeRemaining');
                if (timeRemainingElement) {
                    timeRemainingElement.textContent = days + 'd ' + hours + 'h';
                }
                
                // Update progress bar
                const progress = Math.min(100, Math.max(0, (timeRemaining / totalDuration) * 100));
                const progressFill = document.getElementById('timeProgress');
                if (progressFill) {
                    progressFill.style.width = progress + '%';
                }
            } else {
                const timeRemainingElement = document.getElementById('timeRemaining');
                if (timeRemainingElement) {
                    timeRemainingElement.textContent = 'EXPIRED';
                }
                const progressFill = document.getElementById('timeProgress');
                if (progressFill) {
                    progressFill.style.width = '0%';
                }
            }
            
            // Update time until start if scheduled
            if (timeUntilStart > 0) {
                const daysUntil = Math.floor(timeUntilStart / (1000 * 60 * 60 * 24));
                const hoursUntil = Math.floor((timeUntilStart % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                
                const timeUntilElement = document.getElementById('timeUntilStart');
                if (timeUntilElement) {
                    timeUntilElement.textContent = daysUntil + 'd ' + hoursUntil + 'h';
                }
            }
        }
        
        // Update countdown every second for accuracy
        setInterval(updateCountdown, 1000);
        
        // Initial update
        updateCountdown();
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (startDate >= endDate) {
                e.preventDefault();
                alert('Error: End date must be after start date.');
                return false;
            }
            
            // Confirm save
            if (!confirm('Are you sure you want to save these clearance settings?')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-set minimum datetime for end date based on start date
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = this.value;
            document.getElementById('end_date').min = startDate;
            
            // If end date is before new start date, update it
            const endDateInput = document.getElementById('end_date');
            if (endDateInput.value && new Date(endDateInput.value) <= new Date(startDate)) {
                const newEndDate = new Date(startDate);
                newEndDate.setDate(newEndDate.getDate() + 1);
                endDateInput.value = newEndDate.toISOString().slice(0, 16);
            }
        });
        
        // Initialize end date min value
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date').value;
            document.getElementById('end_date').min = startDate;
        });
    </script>

    <?php include 'partials/footer.php'; ?>
</body>
</html>