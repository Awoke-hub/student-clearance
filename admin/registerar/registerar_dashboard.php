<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
include '../../includes/db.php';

// Add PHPMailer for email functionality
require '../../PHPMailer/src/Exception.php';
require '../../PHPMailer/src/PHPMailer.php';
require '../../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'registrar_admin') {
    header("Location: ../login.php");
    exit();
}

// Function to send email notification to student
function sendClearanceDecisionEmail($studentEmail, $studentName, $decision, $reason = '') {
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
        
        if ($decision === 'approved') {
            $mail->Subject = 'Final Clearance Approved - DBU Clearance System';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #2c3e50;'>🎉 Final Clearance Approved!</h2>
                    <p>Dear <strong>{$studentName}</strong>,</p>
                    <p>We are pleased to inform you that your final clearance has been <strong>APPROVED</strong> by the Registrar Office.</p>
                    
                    <div style='background: #d4edda; padding: 20px; border-radius: 8px; border: 2px solid #c3e6cb; margin: 20px 0;'>
                        <h3 style='margin: 0; color: #155724;'>✅ Clearance Status: Approved</h3>
                        <p style='margin: 10px 0 0 0; color: #155724;'>
                            Your clearance process is now complete. You have successfully cleared all requirements.
                        </p>
                    </div>
                    
                    <p><strong>Important Notes:</strong></p>
                    <ul>
                        <li>You can download your clearance certificate from the student portal</li>
                        <li> <a href='https://dbu.free.nf/clearance-management/index.php'>Click here to access your certificate</a></li>
                        <li>You have completed all clearance requirements</li>
                        <li>This completes your clearance process at DBU</li>
                    </ul>
                    
                    <p>If you have any questions, please contact the Registrar Office.</p>
                    
                    <hr style='border: none; border-top: 1px solid #ddd;'>
                    <p style='color: #7f8c8d; font-size: 12px;'>
                        This is an automated message. Please do not reply to this email.
                    </p>
                </div>
            ";
            
            $mail->AltBody = "Final Clearance Approved: Dear {$studentName}, your final clearance has been APPROVED. Your student status has been updated to inactive. This completes your clearance process at DBU.";
            
        } else { // rejected
            $mail->Subject = 'Final Clearance Rejected - DBU Clearance System';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #2c3e50;'>⚠️ Final Clearance Requires Attention</h2>
                    <p>Dear <strong>{$studentName}</strong>,</p>
                    <p>Your final clearance request has been <strong>REJECTED</strong> by the Registrar Office.</p>
                    
                    <div style='background: #f8d7da; padding: 20px; border-radius: 8px; border: 2px solid #f5c6cb; margin: 20px 0;'>
                        <h3 style='margin: 0; color: #721c24;'>❌ Clearance Status: Rejected</h3>
                        <p style='margin: 10px 0 0 0; color: #721c24;'>
                            <strong>Reason:</strong> {$reason}
                        </p>
                    </div>
                    
                    <p><strong>Next Steps:</strong></p>
                    <ul>
                        <li>Please address the issue mentioned above</li>
                        <li>Your student status remains <strong>active</strong></li>
                        <li>You may reapply for clearance after resolving the issue</li>
                        <li>Contact the relevant department for assistance</li>
                    </ul>
                    
                    <p>If you need clarification, please visit the Registrar Office.</p>
                    
                    <hr style='border: none; border-top: 1px solid #ddd;'>
                    <p style='color: #7f8c8d; font-size: 12px;'>
                        This is an automated message. Please do not reply to this email.
                    </p>
                </div>
            ";
            
            $mail->AltBody = "Final Clearance Rejected: Dear {$studentName}, your final clearance has been REJECTED. Reason: {$reason}. Please address the issue and reapply. Your student status remains active.";
        }

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Get current academic year
$current_year = date('Y');
$current_academic_year = $current_year . '-' . ($current_year + 1);

// Initialize messages
$success_message = '';
$error_message = '';

// Handle Single Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_action'])) {
    if (isset($_POST['approve'])) {
        $student_id = $conn->real_escape_string($_POST['student_id']);
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // 1. Get student details from academicstaff_clearance table
            $student_query = $conn->query("SELECT * FROM academicstaff_clearance WHERE student_id = '$student_id'");
            if (!$student_query || $student_query->num_rows === 0) {
                throw new Exception("Student clearance record not found");
            }
            
            $student_data = $student_query->fetch_assoc();
            
            // 2. Get student email from student table
            $email_query = $conn->query("SELECT email FROM student WHERE student_id = '$student_id'");
            $student_email = '';
            if ($email_query && $email_query->num_rows > 0) {
                $email_data = $email_query->fetch_assoc();
                $student_email = $email_data['email'];
            }
            
            // 3. ONLY check department status (since department already checks library, cafeteria, and dormitory)
            $approval_check = $conn->query("
                SELECT status FROM department_clearance 
                WHERE student_id = '$student_id' 
                ORDER BY id DESC 
                LIMIT 1
            ");
            
            if (!$approval_check) {
                throw new Exception("Failed to verify department approval");
            }
            
            $department_data = $approval_check->fetch_assoc();
            $department_status = $department_data['status'] ?? 'not_requested';
            
            // Check if department is approved
            if ($department_status === 'approved') {
                // Department has approved (which means all previous departments are also approved), proceed with final approval
                
                // 4. Check if final clearance record exists
                $final_check = $conn->query("SELECT id FROM final_clearance WHERE student_id = '$student_id'");
                
                if ($final_check && $final_check->num_rows > 0) {
                    // Update existing record
                    $update = $conn->query("
                        UPDATE final_clearance 
                        SET 
                            status = 'approved',
                            message = 'Final clearance approved by Registrar',
                            reject_reason = NULL,
                            date_sent = NOW()
                        WHERE student_id = '$student_id'
                    ");
                    
                    if (!$update) {
                        throw new Exception("Failed to update final clearance record: " . $conn->error);
                    }
                } else {
                    // Insert new record
                    $insert = $conn->query("
                        INSERT INTO final_clearance (
                            student_id, 
                            name, 
                            last_name, 
                            department,
                            year, 
                            message, 
                            status,
                            date_sent
                        ) VALUES (
                            '{$student_data['student_id']}',
                            '{$student_data['name']}',
                            '{$student_data['last_name']}',
                            '{$student_data['department']}',
                            '" . date('Y') . "',
                            'Final clearance approved by Registrar',
                            'approved',
                            NOW()
                        )
                    ");
                    
                    if (!$insert) {
                        throw new Exception("Failed to create final clearance record: " . $conn->error);
                    }
                }
                
                // 5. Update academic clearance status and clear reject reason
                $update = $conn->query("
                    UPDATE academicstaff_clearance 
                    SET 
                        status = 'approved',
                        reject_reason = NULL
                    WHERE student_id = '$student_id'
                ");
                
                if (!$update) {
                    throw new Exception("Failed to update academic clearance");
                }
                
                // 6. UPDATE STUDENT STATUS FROM ACTIVE TO INACTIVE IN STUDENT TABLE
                $update_student = $conn->query("
                    UPDATE student 
                    SET status = 'inactive' 
                    WHERE student_id = '$student_id'
                ");
                
                if (!$update_student) {
                    error_log("Could not update student status for student ID: $student_id");
                }
                
                // 7. Send approval email notification
                $student_name = $student_data['name'] . ' ' . $student_data['last_name'];
                $email_sent = false;
                if (!empty($student_email)) {
                    $email_sent = sendClearanceDecisionEmail($student_email, $student_name, 'approved');
                }
                
                // Commit transaction
                $conn->commit();

                $email_status = $email_sent ? " and email notification sent" : " but email notification failed";
                $_SESSION['success_message'] = "Final clearance approved for {$student_data['name']} {$student_data['last_name']} (ID: $student_id). Student status updated to inactive{$email_status}.";
                
            } else {
                throw new Exception("Cannot approve: Student must be cleared by Department first.");
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
    // Handle Single Rejection
    elseif (isset($_POST['reject'])) {
        $student_id = $conn->real_escape_string($_POST['student_id']);
        $reject_reason = $conn->real_escape_string($_POST['reject_reason']);
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // 1. Get student details from academicstaff_clearance table
            $student_query = $conn->query("SELECT * FROM academicstaff_clearance WHERE student_id = '$student_id'");
            if (!$student_query || $student_query->num_rows === 0) {
                throw new Exception("Student clearance record not found");
            }
            
            $student_data = $student_query->fetch_assoc();
            
            // 2. Get student email from student table
            $email_query = $conn->query("SELECT email FROM student WHERE student_id = '$student_id'");
            $student_email = '';
            if ($email_query && $email_query->num_rows > 0) {
                $email_data = $email_query->fetch_assoc();
                $student_email = $email_data['email'];
            }
            
            // 3. Check if final clearance record exists
            $final_check = $conn->query("SELECT id FROM final_clearance WHERE student_id = '$student_id'");
            
            if ($final_check && $final_check->num_rows > 0) {
                // Update existing record
                $update = $conn->query("
                    UPDATE final_clearance 
                    SET 
                        status = 'rejected',
                        message = 'Final clearance rejected by Registrar',
                        reject_reason = '$reject_reason',
                        date_sent = NOW()
                    WHERE student_id = '$student_id'
                ");
                
                if (!$update) {
                    throw new Exception("Failed to update final clearance record: " . $conn->error);
                }
            } else {
                // Insert new record
                $insert = $conn->query("
                    INSERT INTO final_clearance (
                        student_id, 
                        name, 
                        last_name, 
                        department,
                        year, 
                        message, 
                        status,
                        reject_reason,
                        date_sent
                    ) VALUES (
                        '{$student_data['student_id']}',
                        '{$student_data['name']}',
                        '{$student_data['last_name']}',
                        '{$student_data['department']}',
                        '" . date('Y') . "',
                        'Final clearance rejected by Registrar',
                        'rejected',
                        '$reject_reason',
                        NOW()
                    )
                ");
                
                if (!$insert) {
                    throw new Exception("Failed to create final clearance record: " . $conn->error);
                }
            }
            
            // 4. Update academic clearance status AND store reject reason
            $update = $conn->query("
                UPDATE academicstaff_clearance 
                SET 
                    status = 'rejected',
                    reject_reason = '$reject_reason'
                WHERE student_id = '$student_id'
            ");
            
            if (!$update) {
                throw new Exception("Failed to update academic clearance");
            }
            
            // 5. UPDATE STUDENT STATUS TO ACTIVE IF REJECTING (in case it was previously set to inactive)
            $update_student = $conn->query("
                UPDATE student 
                SET status = 'active' 
                WHERE student_id = '$student_id'
            ");
            
            if (!$update_student) {
                error_log("Could not update student status for student ID: $student_id");
            }
            
            // 6. Send rejection email notification
            $student_name = $student_data['name'] . ' ' . $student_data['last_name'];
            $email_sent = false;
            if (!empty($student_email)) {
                $email_sent = sendClearanceDecisionEmail($student_email, $student_name, 'rejected', $reject_reason);
            }
            
            // Commit transaction
            $conn->commit();
            
            $email_status = $email_sent ? " and email notification sent" : " but email notification failed";
            $_SESSION['success_message'] = "Final clearance rejected for {$student_data['name']} {$student_data['last_name']} (ID: $student_id){$email_status}";
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
}

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_students = $_POST['selected_students'] ?? [];
    
    if (!empty($selected_students)) {
        if ($bulk_action === 'approve') {
            // Bulk Approve - Only approve students that have department approval
            $success_count = 0;
            $failed_count = 0;
            $email_success_count = 0;
            $email_failed_count = 0;
            $failed_reasons = [];
            
            foreach ($selected_students as $student_id) {
                $student_id = $conn->real_escape_string($student_id);
                
                // Begin transaction for each student
                $conn->begin_transaction();
                
                try {
                    // 1. Get student details
                    $student_query = $conn->query("SELECT * FROM academicstaff_clearance WHERE student_id = '$student_id'");
                    if (!$student_query || $student_query->num_rows === 0) {
                        throw new Exception("Student clearance record not found");
                    }
                    
                    $student_data = $student_query->fetch_assoc();
                    
                    // 2. Get student email
                    $email_query = $conn->query("SELECT email FROM student WHERE student_id = '$student_id'");
                    $student_email = '';
                    if ($email_query && $email_query->num_rows > 0) {
                        $email_data = $email_query->fetch_assoc();
                        $student_email = $email_data['email'];
                    }
                    
                    // 3. ONLY check department status
                    $approval_check = $conn->query("
                        SELECT status FROM department_clearance 
                        WHERE student_id = '$student_id' 
                        ORDER BY id DESC 
                        LIMIT 1
                    ");
                    
                    if (!$approval_check) {
                        throw new Exception("Failed to verify department approval");
                    }
                    
                    $department_data = $approval_check->fetch_assoc();
                    $department_status = $department_data['status'] ?? 'not_requested';
                    
                    // Check if department is approved
                    if ($department_status === 'approved') {
                        // Department has approved, proceed with final approval
                        
                        // 4. Check if final clearance record exists
                        $final_check = $conn->query("SELECT id FROM final_clearance WHERE student_id = '$student_id'");
                        
                        if ($final_check && $final_check->num_rows > 0) {
                            // Update existing record
                            $update = $conn->query("
                                UPDATE final_clearance 
                                SET 
                                    status = 'approved',
                                    message = 'Final clearance approved by Registrar',
                                    reject_reason = NULL,
                                    date_sent = NOW()
                                WHERE student_id = '$student_id'
                            ");
                            
                            if (!$update) {
                                throw new Exception("Failed to update final clearance record");
                            }
                        } else {
                            // Insert new record
                            $insert = $conn->query("
                                INSERT INTO final_clearance (
                                    student_id, 
                                    name, 
                                    last_name, 
                                    department,
                                    year, 
                                    message, 
                                    status,
                                    date_sent
                                ) VALUES (
                                    '{$student_data['student_id']}',
                                    '{$student_data['name']}',
                                    '{$student_data['last_name']}',
                                    '{$student_data['department']}',
                                    '" . date('Y') . "',
                                    'Final clearance approved by Registrar',
                                    'approved',
                                    NOW()
                                )
                            ");
                            
                            if (!$insert) {
                                throw new Exception("Failed to create final clearance record");
                            }
                        }
                        
                        // 5. Update academic clearance status
                        $update = $conn->query("
                            UPDATE academicstaff_clearance 
                            SET 
                                status = 'approved',
                                reject_reason = NULL
                            WHERE student_id = '$student_id'
                        ");
                        
                        if (!$update) {
                            throw new Exception("Failed to update academic clearance");
                        }
                        
                        // 6. Update student status to inactive
                        $update_student = $conn->query("
                            UPDATE student 
                            SET status = 'inactive' 
                            WHERE student_id = '$student_id'
                        ");
                        
                        if (!$update_student) {
                            error_log("Could not update student status for student ID: $student_id");
                        }
                        
                        // 7. Send approval email notification
                        $student_name = $student_data['name'] . ' ' . $student_data['last_name'];
                        if (!empty($student_email)) {
                            if (sendClearanceDecisionEmail($student_email, $student_name, 'approved')) {
                                $email_success_count++;
                            } else {
                                $email_failed_count++;
                            }
                        }
                        
                        // Commit transaction for this student
                        $conn->commit();
                        $success_count++;
                        
                    } else {
                        throw new Exception("Department clearance required");
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $failed_count++;
                    $failed_reasons[] = "Student $student_id: " . $e->getMessage();
                }
            }
            
            if ($success_count > 0) {
                $email_status = "";
                if ($email_success_count > 0) {
                    $email_status .= ", {$email_success_count} email(s) sent";
                }
                if ($email_failed_count > 0) {
                    $email_status .= ", {$email_failed_count} email(s) failed";
                }
                
                $_SESSION['success_message'] = $success_count . " student(s) approved successfully{$email_status}!";
                if ($failed_count > 0) {
                    $_SESSION['success_message'] .= " " . $failed_count . " student(s) failed (missing department approval).";
                    if (count($failed_reasons) > 0) {
                        $_SESSION['success_message'] .= " Issues: " . implode('; ', array_slice($failed_reasons, 0, 3));
                        if (count($failed_reasons) > 3) {
                            $_SESSION['success_message'] .= " and " . (count($failed_reasons) - 3) . " more";
                        }
                    }
                }
            } else {
                $_SESSION['error_message'] = "No students could be approved. All selected students require Department clearance first.";
            }
        } 
        elseif ($bulk_action === 'reject') {
            $bulk_reject_reason = $conn->real_escape_string($_POST['bulk_reject_reason'] ?? '');
            
            // Validate that reject reason is not empty for bulk reject
            if (!empty(trim($bulk_reject_reason))) {
                $success_count = 0;
                $failed_count = 0;
                $email_success_count = 0;
                $email_failed_count = 0;
                
                foreach ($selected_students as $student_id) {
                    $student_id = $conn->real_escape_string($student_id);
                    
                    // Begin transaction for each student
                    $conn->begin_transaction();
                    
                    try {
                        // 1. Get student details
                        $student_query = $conn->query("SELECT * FROM academicstaff_clearance WHERE student_id = '$student_id'");
                        if (!$student_query || $student_query->num_rows === 0) {
                            throw new Exception("Student clearance record not found");
                        }
                        
                        $student_data = $student_query->fetch_assoc();
                        
                        // 2. Get student email
                        $email_query = $conn->query("SELECT email FROM student WHERE student_id = '$student_id'");
                        $student_email = '';
                        if ($email_query && $email_query->num_rows > 0) {
                            $email_data = $email_query->fetch_assoc();
                            $student_email = $email_data['email'];
                        }
                        
                        // 3. Check if final clearance record exists
                        $final_check = $conn->query("SELECT id FROM final_clearance WHERE student_id = '$student_id'");
                        
                        if ($final_check && $final_check->num_rows > 0) {
                            // Update existing record
                            $update = $conn->query("
                                UPDATE final_clearance 
                                SET 
                                    status = 'rejected',
                                    message = 'Final clearance rejected by Registrar',
                                    reject_reason = '$bulk_reject_reason',
                                    date_sent = NOW()
                                WHERE student_id = '$student_id'
                            ");
                            
                            if (!$update) {
                                throw new Exception("Failed to update final clearance record");
                            }
                        } else {
                            // Insert new record
                            $insert = $conn->query("
                                INSERT INTO final_clearance (
                                    student_id, 
                                    name, 
                                    last_name, 
                                    department,
                                    year, 
                                    message, 
                                    status,
                                    reject_reason,
                                    date_sent
                                ) VALUES (
                                    '{$student_data['student_id']}',
                                    '{$student_data['name']}',
                                    '{$student_data['last_name']}',
                                    '{$student_data['department']}',
                                    '" . date('Y') . "',
                                    'Final clearance rejected by Registrar',
                                    'rejected',
                                    '$bulk_reject_reason',
                                    NOW()
                                )
                            ");
                            
                            if (!$insert) {
                                throw new Exception("Failed to create final clearance record");
                            }
                        }
                        
                        // 4. Update academic clearance status
                        $update = $conn->query("
                            UPDATE academicstaff_clearance 
                            SET 
                                status = 'rejected',
                                reject_reason = '$bulk_reject_reason'
                            WHERE student_id = '$student_id'
                        ");
                        
                        if (!$update) {
                            throw new Exception("Failed to update academic clearance");
                        }
                        
                        // 5. Update student status to active
                        $update_student = $conn->query("
                            UPDATE student 
                            SET status = 'active' 
                            WHERE student_id = '$student_id'
                        ");
                        
                        if (!$update_student) {
                            error_log("Could not update student status for student ID: $student_id");
                        }
                        
                        // 6. Send rejection email notification
                        $student_name = $student_data['name'] . ' ' . $student_data['last_name'];
                        if (!empty($student_email)) {
                            if (sendClearanceDecisionEmail($student_email, $student_name, 'rejected', $bulk_reject_reason)) {
                                $email_success_count++;
                            } else {
                                $email_failed_count++;
                            }
                        }
                        
                        // Commit transaction for this student
                        $conn->commit();
                        $success_count++;
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $failed_count++;
                    }
                }
                
                if ($success_count > 0) {
                    $email_status = "";
                    if ($email_success_count > 0) {
                        $email_status .= ", {$email_success_count} email(s) sent";
                    }
                    if ($email_failed_count > 0) {
                        $email_status .= ", {$email_failed_count} email(s) failed";
                    }
                    
                    $_SESSION['success_message'] = $success_count . " student(s) rejected successfully{$email_status}!";
                    if ($failed_count > 0) {
                        $_SESSION['success_message'] .= " " . $failed_count . " student(s) failed to process.";
                    }
                }
            } else {
                $_SESSION['error_message'] = "Reject reason is required for bulk rejection!";
            }
        }
    } else {
        $_SESSION['error_message'] = "Please select at least one student!";
    }
}

// Get messages from session if they exist
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get comprehensive statistics for dashboard - ONLY FOR DEPARTMENT APPROVED STUDENTS AND CURRENT ACADEMIC YEAR
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM academicstaff_clearance ac 
         INNER JOIN department_clearance dc ON ac.student_id = dc.student_id 
         WHERE dc.status = 'approved' AND ac.academic_year = '$current_academic_year') as total_eligible,
        (SELECT COUNT(*) FROM final_clearance WHERE status = 'approved' AND academic_year = '$current_academic_year') as cleared_students,
        (SELECT COUNT(*) FROM academicstaff_clearance ac 
         LEFT JOIN final_clearance fc ON ac.student_id = fc.student_id 
         INNER JOIN department_clearance dc ON ac.student_id = dc.student_id 
         WHERE fc.status IS NULL AND dc.status = 'approved' AND ac.academic_year = '$current_academic_year') as pending_requests,
        (SELECT COUNT(*) FROM final_clearance WHERE status = 'rejected' AND academic_year = '$current_academic_year') as rejected_clearances
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Build query for students based on filters - ONLY DEPARTMENT APPROVED STUDENTS AND CURRENT ACADEMIC YEAR
$query = "
    SELECT ac.*,
           (SELECT status FROM library_clearance WHERE student_id = ac.student_id ORDER BY id DESC LIMIT 1) as library_status,
           (SELECT status FROM cafeteria_clearance WHERE student_id = ac.student_id ORDER BY id DESC LIMIT 1) as cafeteria_status,
           (SELECT status FROM dormitory_clearance WHERE student_id = ac.student_id ORDER BY id DESC LIMIT 1) as dormitory_status,
           (SELECT status FROM department_clearance WHERE student_id = ac.student_id ORDER BY id DESC LIMIT 1) as department_status,
           fc.status as final_status,
           fc.reject_reason as final_reject_reason,
           ac.reject_reason as academic_reject_reason
    FROM academicstaff_clearance ac 
    INNER JOIN department_clearance dc ON ac.student_id = dc.student_id AND dc.status = 'approved'
    LEFT JOIN final_clearance fc ON ac.student_id = fc.student_id
    WHERE ac.academic_year = '$current_academic_year'
";

// Apply search filter
if (!empty($search)) {
    $query .= " AND (ac.name LIKE '%$search%' OR ac.last_name LIKE '%$search%' OR ac.student_id LIKE '%$search%' OR ac.department LIKE '%$search%')";
}

// Apply status filter
if ($status_filter === 'pending') {
    $query .= " AND fc.status IS NULL AND ac.status = 'pending'";
} elseif ($status_filter === 'approved') {
    $query .= " AND fc.status = 'approved'";
} elseif ($status_filter === 'rejected') {
    $query .= " AND fc.status = 'rejected'";
}

$query .= " ORDER BY 
    (CASE 
        WHEN fc.status IS NULL AND 
             (SELECT status FROM department_clearance WHERE student_id = ac.student_id ORDER BY id DESC LIMIT 1) = 'approved'
        THEN 1
        ELSE 2
    END) ASC,
    ac.student_id ASC";

$result = $conn->query($query);

// Get approved final clearances for history - CURRENT ACADEMIC YEAR ONLY
$history_query = "
    SELECT fc.*, ac.department, ac.reject_reason as academic_reject_reason 
    FROM final_clearance fc 
    LEFT JOIN academicstaff_clearance ac ON fc.student_id = ac.student_id 
    WHERE fc.academic_year = '$current_academic_year'
    ORDER BY fc.date_sent DESC 
    LIMIT 50
";
$history_result = $conn->query($history_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Admin - Final Clearance Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2E8B57;
            --primary-dark: #276749;
            --secondary: #48BB78;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f4f8fb;
            --dark: #2c3e50;
            --gray: #6c757d;
            --border: #dee2e6;
            --info: #3498db;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #333;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-nav {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box, .status-filter {
            padding: 0.6rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .search-box:focus, .status-filter:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
        }

        .main-content {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        .overview-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .overview-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 5px solid var(--secondary);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .overview-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }

        .overview-card.total { border-left-color: var(--info); }
        .overview-card.cleared { border-left-color: var(--success); }
        .overview-card.pending { border-left-color: var(--warning); }
        .overview-card.rejected { border-left-color: var(--danger); }

        .overview-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: block;
        }

        .overview-label {
            color: var(--gray);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .section:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            margin-top: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1300px;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--light);
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: rgba(0,0,0,0.02);
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            text-decoration: none;
        }

        .btn-approve {
            background: var(--success);
            color: white;
        }

        .btn-reject {
            background: var(--danger);
            color: white;
        }

        .btn-change {
            background: var(--warning);
            color: white;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn:hover:not(:disabled) {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 90px;
            justify-content: center;
        }

        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-ready { background: #cce7ff; color: #004085; }
        .status-not_requested { background: #e9ecef; color: #495057; }

        .clearance-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
            justify-content: center;
            min-width: 80px;
        }

        .clearance-approved { background: #d4edda; color: #155724; }
        .clearance-pending { background: #fff3cd; color: #856404; }
        .clearance-rejected { background: #f8d7da; color: #721c24; }
        .clearance-not_requested { background: #e9ecef; color: #495057; }

        .priority-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--success);
            color: white;
            font-size: 0.7rem;
            margin-right: 0.5rem;
        }

        /* Academic Year Badge */
        .academic-year-badge {
            background: linear-gradient(135deg, #2E8B57, #276749);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-left: 1rem;
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
            background: var(--primary);
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .reject-modal, .bulk-reject-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 450px;
            max-width: 90vw;
            max-height: 90vh;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .modal-body {
            flex: 1;
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .reject-textarea {
            width: 100%;
            padding: 0.75rem;
            margin-top: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.3s ease;
        }

        .reject-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
        }

        .reject-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .reject-hint {
            color: #6c757d;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: block;
        }

        .error-message {
            color: var(--danger);
            background: #f8d7da;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--danger);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .success-message {
            color: var(--success);
            background: #d4edda;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--success);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .history-section {
            margin-top: 2rem;
        }

        .toggle-history {
            background: var(--light);
            border: 1px solid var(--border);
            color: var(--primary);
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .toggle-history:hover {
            background: var(--primary);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border);
        }

        .final-approve-notice {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .final-approve-notice i {
            color: var(--success);
            font-size: 1.2rem;
        }

        .clearance-status {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .clearance-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .clearance-label {
            font-size: 0.75rem;
            color: var(--gray);
            min-width: 80px;
        }

        @media (max-width: 768px) {
            .admin-header, .admin-nav {
                padding: 1rem 1.5rem;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .overview-container {
                grid-template-columns: 1fr;
            }
            
            .section {
                padding: 1rem;
            }
            
            .filters {
                flex-direction: column;
                width: 100%;
            }
            
            .search-box, .status-filter {
                width: 100%;
            }
            
            table {
                font-size: 0.85rem;
                min-width: 1200px;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .modal-content {
                min-width: 95%;
                padding: 1.5rem;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-footer .btn {
                width: 100%;
                justify-content: center;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .academic-year-badge {
                font-size: 0.7rem;
                padding: 0.3rem 0.6rem;
                margin-left: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <h1><i class="fas fa-graduation-cap"></i> Debre Berhan University - Registrar Admin</h1>
        <div>
            <span><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
            <a href="../logout.php" style="color: white; margin-left: 1rem; text-decoration: none;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <nav class="admin-nav">
        <h2><i class="fas fa-tachometer-alt"></i> Final Clearance Dashboard 
            <span class="academic-year-badge">
                <i class="fas fa-calendar-alt"></i> <?php echo $current_academic_year; ?>
            </span>
        </h2>
        <div class="filters">
            <form method="GET" id="filter-form" style="display: flex; gap: 1rem; align-items: center;">
                <input type="text" class="search-box" name="search" placeholder="Search students..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       oninput="this.form.submit()">
                <select class="status-filter" name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    <a href="?" class="btn" style="background: var(--gray); color: white;">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </nav>

    <div class="main-content">
        <!-- Overview Section -->
        <div class="overview-container">
            <div class="overview-card total">
                <span class="overview-number"><?php echo $stats['total_eligible'] ?? 0; ?></span>
                <div class="overview-label">
                    <i class="fas fa-users"></i> Eligible Students
                </div>
            </div>
            <div class="overview-card cleared">
                <span class="overview-number"><?php echo $stats['cleared_students'] ?? 0; ?></span>
                <div class="overview-label">
                    <i class="fas fa-check-circle"></i> Cleared Students
                </div>
            </div>
            <div class="overview-card pending">
                <span class="overview-number"><?php echo $stats['pending_requests'] ?? 0; ?></span>
                <div class="overview-label">
                    <i class="fas fa-clock"></i> Pending Requests
                </div>
            </div>
            <div class="overview-card rejected">
                <span class="overview-number"><?php echo $stats['rejected_clearances'] ?? 0; ?></span>
                <div class="overview-label">
                    <i class="fas fa-times-circle"></i> Rejected Clearances
                </div>
            </div>
        </div>

        <!-- Final Approval Notice -->
        <div class="final-approve-notice">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Final Clearance Management:</strong> Only showing <?php echo $current_academic_year; ?> academic year students approved by Department. Department approval implies all previous departments (Library, Cafeteria, Dormitory) are also approved. When approving, student status will be changed from active to inactive in the student table. Students will receive email notifications for both approval and rejection decisions.
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Students List Section -->
        <div class="section">
            <div class="section-header">
                <h3><i class="fas fa-list-alt"></i> Student Clearance Requests (<?php echo $current_academic_year; ?> Academic Year)</h3>
                <div>
                    <span style="color: var(--gray); font-size: 0.9rem;">
                        Showing: 
                        <?php 
                        if ($status_filter === 'all') {
                            echo 'All Department-Approved Students';
                        } elseif ($status_filter === 'pending') {
                            echo 'Pending Requests';
                        } elseif ($status_filter === 'approved') {
                            echo 'Approved Clearances';
                        } elseif ($status_filter === 'rejected') {
                            echo 'Rejected Clearances';
                        }
                        ?>
                        (<?php echo $result->num_rows; ?> records)
                    </span>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions" style="display: none;">
                <span class="selected-count" id="selectedCount">0 selected</span>
                <select id="bulkActionSelect">
                    <option value="">Choose action...</option>
                    <option value="approve">Approve Selected</option>
                    <option value="reject">Reject Selected</option>
                </select>
                <button type="button" class="btn btn-bulk" onclick="applyBulkAction()">
                    <i class="fas fa-play"></i> Apply
                </button>
                <button type="button" class="btn" onclick="clearSelection()" style="background: #6c757d; color: white;">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <form id="bulkActionForm" method="POST">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Department</th>
                                    <th>Library Status</th>
                                    <th>Cafeteria Status</th>
                                    <th>Dormitory Status</th>
                                    <th>Department Status</th>
                                    <th>Final Status</th>
                                    <th>Reject Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): 
                                    $student_id = $row['student_id'];
                                    $student_name = $row['name'] . ' ' . $row['last_name'];
                                    $department = $row['department'];
                                    $final_status = $row['final_status'];
                                    
                                    // Get clearance statuses
                                    $library_status = $row['library_status'] ?? 'not_requested';
                                    $cafeteria_status = $row['cafeteria_status'] ?? 'not_requested';
                                    $dormitory_status = $row['dormitory_status'] ?? 'not_requested';
                                    $dept_status = $row['department_status'] ?? 'not_requested';
                                    
                                    // Check if department is approved (this is already filtered in the query)
                                    $ready_for_approval = ($dept_status === 'approved') && !$final_status;
                                    $can_change_decision = $final_status && ($dept_status === 'approved');
                                ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="selected_students[]" value="<?php echo $student_id; ?>" 
                                               class="student-checkbox" onchange="updateBulkActions()">
                                    </td>
                                    <td>
                                        <?php if ($ready_for_approval): ?>
                                            <span class="priority-indicator" title="Ready for final approval">
                                                <i class="fas fa-star"></i>
                                            </span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($student_name); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student_id); ?></td>
                                    <td><?php echo htmlspecialchars($department); ?></td>
                                    <td>
                                        <span class="clearance-badge clearance-<?php echo $library_status; ?>">
                                            <i class="fas fa-<?php 
                                                echo $library_status === 'approved' ? 'check' : 
                                                     ($library_status === 'pending' ? 'clock' : 
                                                     ($library_status === 'rejected' ? 'times' : 'question')); 
                                            ?>"></i>
                                            <?php echo ucfirst($library_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="clearance-badge clearance-<?php echo $cafeteria_status; ?>">
                                            <i class="fas fa-<?php 
                                                echo $cafeteria_status === 'approved' ? 'check' : 
                                                     ($cafeteria_status === 'pending' ? 'clock' : 
                                                     ($cafeteria_status === 'rejected' ? 'times' : 'question')); 
                                            ?>"></i>
                                            <?php echo ucfirst($cafeteria_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="clearance-badge clearance-<?php echo $dormitory_status; ?>">
                                            <i class="fas fa-<?php 
                                                echo $dormitory_status === 'approved' ? 'check' : 
                                                     ($dormitory_status === 'pending' ? 'clock' : 
                                                     ($dormitory_status === 'rejected' ? 'times' : 'question')); 
                                            ?>"></i>
                                            <?php echo ucfirst($dormitory_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="clearance-badge clearance-<?php echo $dept_status; ?>">
                                            <i class="fas fa-<?php 
                                                echo $dept_status === 'approved' ? 'check' : 
                                                     ($dept_status === 'pending' ? 'clock' : 
                                                     ($dept_status === 'rejected' ? 'times' : 'question')); 
                                            ?>"></i>
                                            <?php echo ucfirst($dept_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($final_status === 'approved'): ?>
                                            <span class="status-badge status-approved">
                                                <i class="fas fa-check"></i> Approved
                                            </span>
                                        <?php elseif ($final_status === 'rejected'): ?>
                                            <span class="status-badge status-rejected">
                                                <i class="fas fa-times"></i> Rejected
                                            </span>
                                        <?php elseif ($ready_for_approval): ?>
                                            <span class="status-badge status-ready">
                                                <i class="fas fa-check-circle"></i> Ready
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $reject_reason = !empty($row['final_reject_reason']) ? $row['final_reject_reason'] : $row['academic_reject_reason'];
                                        if (!empty($reject_reason)): 
                                        ?>
                                            <span style="font-size: 0.8rem; color: var(--danger); background: #f8d7da; padding: 0.3rem 0.6rem; border-radius: 4px; display: inline-block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($reject_reason); ?>">
                                                <?php echo htmlspecialchars(substr($reject_reason, 0, 30) . (strlen($reject_reason) > 30 ? '...' : '')); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--gray); font-size: 0.8rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <?php if (!$final_status && $ready_for_approval): ?>
                                            <!-- Initial decision - both approve and reject available -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                                <button type="submit" name="approve" class="btn btn-approve">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-reject" onclick="showRejectModal('<?php echo $student_id; ?>', '<?php echo $student_name; ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php elseif ($final_status === 'approved' && $can_change_decision): ?>
                                            <!-- Change from approved to rejected -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                                <button type="submit" name="approve" class="btn btn-approve" disabled style="opacity: 0.6;">
                                                    <i class="fas fa-check"></i> Approved
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-reject" onclick="showRejectModal('<?php echo $student_id; ?>', '<?php echo $student_name; ?>')">
                                                <i class="fas fa-exchange-alt"></i> Change to Reject
                                            </button>
                                        <?php elseif ($final_status === 'rejected' && $can_change_decision): ?>
                                            <!-- Change from rejected to approved -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                                <button type="submit" name="approve" class="btn btn-approve">
                                                    <i class="fas fa-exchange-alt"></i> Change to Approve
                                                </button>
                                            </form>
                                            <span class="status-badge status-rejected" style="min-width: auto;">
                                                <i class="fas fa-times"></i> Rejected
                                            </span>
                                        <?php elseif ($final_status && !$can_change_decision): ?>
                                            <!-- Has final status but missing department approval -->
                                            <span class="status-badge status-<?php echo $final_status; ?>" style="min-width: auto;">
                                                <i class="fas fa-<?php echo $final_status === 'approved' ? 'check' : 'times'; ?>"></i>
                                                <?php echo ucfirst($final_status); ?>
                                            </span>
                                            <button class="btn" disabled style="background: #6c757d; color: white;">
                                                <i class="fas fa-ban"></i> Cannot Change
                                            </button>
                                        <?php else: ?>
                                            <!-- Not ready for approval -->
                                            <button class="btn" disabled style="background: #6c757d; color: white;">
                                                <i class="fas fa-ban"></i> Cannot Approve
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <input type="hidden" name="bulk_action" id="bulkActionInput">
                    <input type="hidden" name="bulk_reject_reason" id="bulkRejectReasonInput">
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No students found</h3>
                    <p>There are currently no <?php echo $current_academic_year; ?> students approved by Department matching your current filters.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- History Section -->
        <div class="section history-section">
            <div class="section-header">
                <h3><i class="fas fa-history"></i> Final Clearance History (<?php echo $current_academic_year; ?>)</h3>
                <button class="toggle-history" onclick="toggleSection('history-content')">
                    <span id="history-toggle-text">Show History</span>
                    <span id="history-arrow">▼</span>
                </button>
            </div>
            
            <div id="history-content" style="display: none;">
                <?php if ($history_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Department</th>
                                    <th>Decision</th>
                                    <th>Date Processed</th>
                                    <th>Reason (if rejected)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($history = $history_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($history['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($history['name'] . ' ' . $history['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($history['department'] ?? '-'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $history['status']; ?>">
                                            <i class="fas fa-<?php 
                                                echo $history['status'] == 'approved' ? 'check' : 'times'; 
                                            ?>"></i>
                                            <?php echo ucfirst($history['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($history['date_sent'])); ?></td>
                                    <td>
                                        <?php 
                                        $display_reason = !empty($history['reject_reason']) ? $history['reject_reason'] : $history['academic_reject_reason'];
                                        echo !empty($display_reason) ? htmlspecialchars($display_reason) : '-'; 
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No history available</h3>
                        <p>Final clearance history for <?php echo $current_academic_year; ?> will appear here once you start approving or rejecting requests.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Single Reject Modal -->
    <div id="rejectModal" class="reject-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title"><i class="fas fa-times-circle"></i> Reject Final Clearance</h3>
            </div>
            
            <div class="modal-body">
                <div id="error-message" class="error-message" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> Reject reason is required!
                </div>
                
                <form method="POST" id="reject-form">
                    <input type="hidden" name="student_id" id="reject-student-id">
                    <input type="hidden" name="reject" value="1">
                    
                    <p id="student-info" style="margin-bottom: 1rem;"></p>
                    
                    <label for="reject_reason" class="reject-label">
                        Reason for rejection: <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea name="reject_reason" id="reject_reason" class="reject-textarea" 
                              placeholder="Please provide a clear reason for rejecting this final clearance..." 
                              oninput="validateRejectReason()"></textarea>
                    <small class="reject-hint">
                        * Please provide a clear reason for rejecting this request
                    </small>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="hideRejectModal()" style="background: #6c757d; color: white;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-reject" id="confirm-reject-btn" onclick="submitRejectForm()" disabled>
                    <i class="fas fa-check"></i> Confirm Reject
                </button>
            </div>
        </div>
    </div>

    <!-- Bulk Reject Modal -->
    <div id="bulkRejectModal" class="bulk-reject-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Reject Selected Students</h3>
            </div>
            
            <div class="modal-body">
                <div id="bulk-error-message" class="error-message" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> Reject reason is required!
                </div>
                
                <p id="bulk-student-info" style="margin-bottom: 1rem;"></p>
                
                <label for="bulk_reject_reason" class="reject-label">
                    Reason for rejection (will apply to all selected students): <span style="color: var(--danger);">*</span>
                </label>
                <textarea name="bulk_reject_reason" id="bulk_reject_reason" class="reject-textarea" 
                          placeholder="Please provide a clear reason for rejecting these final clearances..." 
                          oninput="validateBulkRejectReason()"></textarea>
                <small class="reject-hint">
                    * This reason will be applied to all selected students
                </small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="hideBulkRejectModal()" style="background: #6c757d; color: white;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-reject" id="confirm-bulk-reject-btn" onclick="confirmBulkReject()" disabled>
                    <i class="fas fa-check"></i> Reject All Selected
                </button>
            </div>
        </div>
    </div>

    <script>
        // Toggle section visibility
        function toggleSection(sectionId) {
            const content = document.getElementById(sectionId);
            const toggleText = document.getElementById(sectionId.replace('content', 'toggle-text'));
            const arrow = document.getElementById(sectionId.replace('content', 'arrow'));
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                toggleText.textContent = 'Hide Details';
                arrow.textContent = '▲';
            } else {
                content.style.display = 'none';
                toggleText.textContent = sectionId === 'history-content' ? 'Show History' : 'Show Details';
                arrow.textContent = '▼';
            }
        }

        // Single reject modal functions
        function showRejectModal(studentId, studentName) {
            document.getElementById('reject-student-id').value = studentId;
            document.getElementById('student-info').textContent = `Student: ${studentName} (ID: ${studentId})`;
            document.getElementById('rejectModal').style.display = 'block';
            document.getElementById('error-message').style.display = 'none';
            document.getElementById('reject_reason').value = '';
            document.getElementById('confirm-reject-btn').disabled = true;
            
            setTimeout(() => {
                document.getElementById('reject_reason').focus();
            }, 100);
        }

        function hideRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('reject_reason').value = '';
            document.getElementById('error-message').style.display = 'none';
        }

        function validateRejectReason() {
            const rejectReason = document.getElementById('reject_reason').value.trim();
            const confirmBtn = document.getElementById('confirm-reject-btn');
            const errorMessage = document.getElementById('error-message');
            
            if (rejectReason.length > 0) {
                confirmBtn.disabled = false;
                errorMessage.style.display = 'none';
            } else {
                confirmBtn.disabled = true;
                errorMessage.style.display = 'none';
            }
        }

        function submitRejectForm() {
            const rejectReason = document.getElementById('reject_reason').value.trim();
            const errorMessage = document.getElementById('error-message');
            
            if (rejectReason.length === 0) {
                errorMessage.style.display = 'block';
                return;
            }
            
            document.getElementById('reject-form').submit();
        }

        // Bulk actions functions
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
            
            if (action === 'approve') {
                if (confirm(`Are you sure you want to approve ${selectedCount} student(s)? Only students with Department approval will be processed. Students will receive email notifications.`)) {
                    document.getElementById('bulkActionInput').value = 'approve';
                    document.getElementById('bulkActionForm').submit();
                }
            } else if (action === 'reject') {
                showBulkRejectModal();
            } else {
                alert('Please select an action.');
            }
        }

        function showBulkRejectModal() {
            const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
            document.getElementById('bulk-student-info').textContent = `Rejecting ${selectedCount} student(s)`;
            document.getElementById('bulkRejectModal').style.display = 'block';
            document.getElementById('bulk-error-message').style.display = 'none';
            document.getElementById('bulk_reject_reason').value = '';
            document.getElementById('confirm-bulk-reject-btn').disabled = true;
        }

        function hideBulkRejectModal() {
            document.getElementById('bulkRejectModal').style.display = 'none';
            document.getElementById('bulk_reject_reason').value = '';
            document.getElementById('bulk-error-message').style.display = 'none';
        }

        function validateBulkRejectReason() {
            const rejectReason = document.getElementById('bulk_reject_reason').value.trim();
            const confirmBtn = document.getElementById('confirm-bulk-reject-btn');
            
            if (rejectReason.length > 0) {
                confirmBtn.disabled = false;
            } else {
                confirmBtn.disabled = true;
            }
        }

        function confirmBulkReject() {
            const rejectReason = document.getElementById('bulk_reject_reason').value.trim();
            const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
            
            if (rejectReason.length === 0) {
                document.getElementById('bulk-error-message').style.display = 'block';
                return;
            }
            
            if (confirm(`Are you sure you want to reject ${selectedCount} student(s) with this reason? Students will receive email notifications.`)) {
                document.getElementById('bulkActionInput').value = 'reject';
                document.getElementById('bulkRejectReasonInput').value = rejectReason;
                document.getElementById('bulkActionForm').submit();
            }
        }

        // Event listeners
        document.getElementById('reject_reason').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                submitRejectForm();
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            const bulkModal = document.getElementById('bulkRejectModal');
            if (event.target === modal) {
                hideRejectModal();
            }
            if (event.target === bulkModal) {
                hideBulkRejectModal();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActions();
            
            // Add animation to stat cards on load
            const statCards = document.querySelectorAll('.overview-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>