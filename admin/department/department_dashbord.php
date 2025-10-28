<?php
// Turn on all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
include '../../includes/db.php';

// Check if user is logged in and is a department admin
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'department_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_department = $_SESSION['admin_department']; // Get the admin's department

// Get current academic year
$current_year = date('Y');
$current_academic_year = $current_year . '-' . ($current_year + 1);

// Handle Approve/Reject actions (Single and Bulk)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Single Approve/Reject
    if (isset($_POST['action_type']) && !isset($_POST['bulk_action'])) {
        $request_id = $_POST['request_id'];
        $action_type = $_POST['action_type'];
        
        // Check if student is already approved by academic staff (final clearance)
        $final_check = $conn->prepare("
            SELECT status FROM academicstaff_clearance 
            WHERE student_id = (
                SELECT student_id FROM department_clearance WHERE id = ?
            ) 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $final_check->bind_param("i", $request_id);
        $final_check->execute();
        $final_result = $final_check->get_result();
        $final_data = $final_result->fetch_assoc();
        
        $final_status = $final_data['status'] ?? 'pending';
        
        // Prevent modification if final clearance is granted
        if ($final_status === 'approved') {
            $error_message = "Cannot modify: Student has already been granted final clearance by Registrar.";
        } else {
            if ($action_type === 'approve') {
                // Check if student is approved by dormitory (which implies library and cafeteria are also approved)
                $check_stmt = $conn->prepare("
                    SELECT dc.student_id 
                    FROM department_clearance dc 
                    WHERE dc.id = ?
                ");
                $check_stmt->bind_param("i", $request_id);
                $check_stmt->execute();
                $student_result = $check_stmt->get_result();
                $student_data = $student_result->fetch_assoc();
                
                if ($student_data) {
                    $student_id = $student_data['student_id'];
                    
                    // ONLY check dormitory status (since dormitory already checks library and cafeteria)
                    $clearance_check = $conn->prepare("
                        SELECT status FROM dormitory_clearance 
                        WHERE student_id = ? 
                        ORDER BY id DESC 
                        LIMIT 1
                    ");
                    $clearance_check->bind_param("s", $student_id);
                    $clearance_check->execute();
                    $clearance_result = $clearance_check->get_result();
                    $clearance_data = $clearance_result->fetch_assoc();
                    
                    $dormitory_status = $clearance_data['status'] ?? 'not_requested';
                    
                    if ($dormitory_status === 'approved') {
                        // Dormitory has approved (which means library and cafeteria are also approved), proceed with department approval
                        $stmt = $conn->prepare("UPDATE department_clearance SET status='approved', reject_reason=NULL WHERE id=?");
                        $stmt->bind_param("i", $request_id);
                        if ($stmt->execute()) {
                            $success_message = "Student approved successfully!";
                        } else {
                            $error_message = "Error updating approval status.";
                        }
                    } else {
                        $error_message = "Cannot approve: Student must be cleared by Dormitory first.";
                    }
                }
            } elseif ($action_type === 'reject') {
                $reject_reason = $_POST['reject_reason'] ?? '';
                
                // Validate that reject reason is not empty
                if (!empty(trim($reject_reason))) {
                    $stmt = $conn->prepare("UPDATE department_clearance SET status='rejected', reject_reason=? WHERE id=?");
                    $stmt->bind_param("si", $reject_reason, $request_id);
                    if ($stmt->execute()) {
                        $success_message = "Request rejected successfully!";
                    } else {
                        $error_message = "Error updating rejection status.";
                    }
                } else {
                    // Set error message for empty reject reason
                    $error_message = "Reject reason is required!";
                }
            }
        }
    }
    // Bulk Actions
    elseif (isset($_POST['bulk_action'])) {
        $bulk_action = $_POST['bulk_action'];
        $selected_requests = $_POST['selected_requests'] ?? [];
        
        if (!empty($selected_requests)) {
            if ($bulk_action === 'approve') {
                // Bulk Approve - Only approve requests that have dormitory approval AND are not finalized
                $success_count = 0;
                $failed_count = 0;
                $finalized_count = 0;
                
                foreach ($selected_requests as $request_id) {
                    // Check if student is already approved by academic staff (final clearance)
                    $final_check = $conn->prepare("
                        SELECT status FROM academicstaff_clearance 
                        WHERE student_id = (
                            SELECT student_id FROM department_clearance WHERE id = ?
                        ) 
                        ORDER BY id DESC 
                        LIMIT 1
                    ");
                    $final_check->bind_param("i", $request_id);
                    $final_check->execute();
                    $final_result = $final_check->get_result();
                    $final_data = $final_result->fetch_assoc();
                    
                    $final_status = $final_data['status'] ?? 'pending';
                    
                    if ($final_status === 'approved') {
                        $finalized_count++;
                        continue; // Skip this request
                    }
                    
                    // Check dormitory status for each request
                    $check_stmt = $conn->prepare("
                        SELECT dc.student_id 
                        FROM department_clearance dc 
                        WHERE dc.id = ?
                    ");
                    $check_stmt->bind_param("i", $request_id);
                    $check_stmt->execute();
                    $student_result = $check_stmt->get_result();
                    $student_data = $student_result->fetch_assoc();
                    
                    if ($student_data) {
                        $student_id = $student_data['student_id'];
                        
                        // ONLY check dormitory status
                        $clearance_check = $conn->prepare("
                            SELECT status FROM dormitory_clearance 
                            WHERE student_id = ? 
                            ORDER BY id DESC 
                            LIMIT 1
                        ");
                        $clearance_check->bind_param("s", $student_id);
                        $clearance_check->execute();
                        $clearance_result = $clearance_check->get_result();
                        $clearance_data = $clearance_result->fetch_assoc();
                        
                        $dormitory_status = $clearance_data['status'] ?? 'not_requested';
                        
                        if ($dormitory_status === 'approved') {
                            // Dormitory has approved, proceed with department approval
                            $stmt = $conn->prepare("UPDATE department_clearance SET status='approved', reject_reason=NULL WHERE id=?");
                            $stmt->bind_param("i", $request_id);
                            if ($stmt->execute()) {
                                $success_count++;
                            } else {
                                $failed_count++;
                            }
                        } else {
                            $failed_count++;
                        }
                    } else {
                        $failed_count++;
                    }
                }
                
                if ($success_count > 0) {
                    $success_message = $success_count . " request(s) approved successfully!";
                    if ($failed_count > 0 || $finalized_count > 0) {
                        $success_message .= " " . $failed_count . " request(s) failed (Dormitory clearance required).";
                        if ($finalized_count > 0) {
                            $success_message .= " " . $finalized_count . " request(s) skipped (Already finalized by Academic Staff).";
                        }
                    }
                } else if ($finalized_count > 0) {
                    $error_message = "No requests could be approved. " . $finalized_count . " request(s) are already finalized by Academic Staff.";
                } else {
                    $error_message = "No requests could be approved. All selected requests require Dormitory clearance first.";
                }
            } 
            elseif ($bulk_action === 'reject') {
                $reject_reason = $_POST['bulk_reject_reason'] ?? '';
                
                // Validate that reject reason is not empty for bulk reject
                if (!empty(trim($reject_reason))) {
                    $success_count = 0;
                    $failed_count = 0;
                    
                    foreach ($selected_requests as $request_id) {
                        // Check if student is already approved by academic staff (final clearance)
                        $final_check = $conn->prepare("
                            SELECT status FROM academicstaff_clearance 
                            WHERE student_id = (
                                SELECT student_id FROM department_clearance WHERE id = ?
                            ) 
                            ORDER BY id DESC 
                            LIMIT 1
                        ");
                        $final_check->bind_param("i", $request_id);
                        $final_check->execute();
                        $final_result = $final_check->get_result();
                        $final_data = $final_result->fetch_assoc();
                        
                        $final_status = $final_data['status'] ?? 'pending';
                        
                        if ($final_status === 'approved') {
                            $failed_count++;
                            continue; // Skip this request
                        }
                        
                        $stmt = $conn->prepare("UPDATE department_clearance SET status='rejected', reject_reason=? WHERE id=?");
                        $stmt->bind_param("si", $reject_reason, $request_id);
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            $failed_count++;
                        }
                    }
                    
                    if ($success_count > 0) {
                        $success_message = $success_count . " request(s) rejected successfully!";
                        if ($failed_count > 0) {
                            $success_message .= " " . $failed_count . " request(s) failed (Already finalized by Academic Staff).";
                        }
                    } else {
                        $error_message = "No requests could be rejected. All selected requests are already finalized by Academic Staff.";
                    }
                } else {
                    $error_message = "Reject reason is required for bulk rejection!";
                }
            }
        } else {
            $error_message = "Please select at least one request!";
        }
    }
}

// Get statistics from department_clearance table - ONLY FOR APPROVED BY DORMITORY AND CURRENT ACADEMIC YEAR AND SAME DEPARTMENT
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(dc.status = 'pending') as pending,
        SUM(dc.status = 'approved') as approved,
        SUM(dc.status = 'rejected') as rejected
    FROM department_clearance dc
    INNER JOIN (
        SELECT student_id 
        FROM dormitory_clearance 
        WHERE status = 'approved'
        GROUP BY student_id
    ) dorm ON dc.student_id = dorm.student_id
    WHERE dc.academic_year = ? AND dc.department = ?
");
$stats_stmt->bind_param("ss", $current_academic_year, $admin_department);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get requests for the main table - ONLY STUDENTS APPROVED BY DORMITORY AND CURRENT ACADEMIC YEAR AND SAME DEPARTMENT
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Query to get department requests with clearance status - ONLY FOR DORMITORY APPROVED STUDENTS AND CURRENT ACADEMIC YEAR AND SAME DEPARTMENT
$main_query = "
    SELECT 
        dc.*, 
        CONCAT(dc.name, ' ', dc.last_name) as student_name,
        (SELECT status FROM library_clearance WHERE student_id = dc.student_id ORDER BY id DESC LIMIT 1) as library_status,
        (SELECT status FROM cafeteria_clearance WHERE student_id = dc.student_id ORDER BY id DESC LIMIT 1) as cafeteria_status,
        (SELECT status FROM dormitory_clearance WHERE student_id = dc.student_id ORDER BY id DESC LIMIT 1) as dormitory_status,
        (SELECT status FROM academicstaff_clearance WHERE student_id = dc.student_id ORDER BY id DESC LIMIT 1) as academic_status,
        (CASE 
            WHEN dc.status = 'pending' AND 
                 (SELECT status FROM dormitory_clearance WHERE student_id = dc.student_id ORDER BY id DESC LIMIT 1) = 'approved'
            THEN 1 
            WHEN dc.status = 'pending' THEN 0 
            ELSE 2 
        END) as priority_order
    FROM department_clearance dc 
    INNER JOIN (
        SELECT student_id 
        FROM dormitory_clearance 
        WHERE status = 'approved'
        GROUP BY student_id
    ) dorm ON dc.student_id = dorm.student_id
    WHERE dc.academic_year = ? AND dc.department = ?
";

if (!empty($search)) {
    $search_term = "%$search%";
    $main_query .= " AND (dc.name LIKE ? OR dc.student_id LIKE ? OR dc.last_name LIKE ?)";
}

if ($status_filter !== 'all') {
    $main_query .= " AND dc.status = ?";
}

// ORDER BY to prioritize pending requests with dormitory approved
$main_query .= " ORDER BY 
    priority_order DESC,
    dc.status = 'pending' DESC,
    dc.requested_at DESC";

$main_stmt = $conn->prepare($main_query);

// Dynamic parameter binding
$param_types = 'ss'; // Start with academic_year and department parameters
$param_values = [$current_academic_year, $admin_department];

if (!empty($search)) {
    $search_term = "%$search%";
    $param_types .= 'sss';
    $param_values[] = $search_term;
    $param_values[] = $search_term;
    $param_values[] = $search_term;
}

if ($status_filter !== 'all') {
    $param_types .= 's';
    $param_values[] = $status_filter;
}

// Bind parameters if we have any
if (!empty($param_types)) {
    $main_stmt->bind_param($param_types, ...$param_values);
}

$main_stmt->execute();
$all_requests = $main_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Admin Dashboard - <?php echo htmlspecialchars($admin_department); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #8e44ad;
            --primary-dark: #7d3c98;
            --secondary: #9b59b6;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f8f5fc;
            --dark: #2c3e50;
            --gray: #6c757d;
            --border: #dee2e6;
            --info: #3498db;
            --gradient-primary: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
            --gradient-success: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            --gradient-warning: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            --gradient-danger: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-hover: 0 6px 20px rgba(0,0,0,0.12);
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
            line-height: 1.5;
            min-height: 100vh;
        }

        .admin-header {
            background: var(--gradient-primary);
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 70px;
        }

        .admin-nav {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            position: sticky;
            top: 70px;
            z-index: 999;
            height: 70px;
        }

        .main-content {
            padding: 1rem 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
            min-height: calc(100vh - 140px);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.2rem 1rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
        }

        .stat-card.pending::before { background: var(--warning); }
        .stat-card.approved::before { background: var(--success); }
        .stat-card.rejected::before { background: var(--danger); }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
            display: block;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .stat-card.pending .stat-number {
            background: var(--gradient-warning);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card.approved .stat-number {
            background: var(--gradient-success);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card.rejected .stat-number {
            background: var(--gradient-danger);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .section:hover {
            box-shadow: var(--shadow-hover);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .section-title i {
            color: var(--primary);
        }

        .filters {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box, .status-filter {
            padding: 0.6rem 1rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            height: 42px;
        }

        .search-box:focus, .status-filter:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.12);
        }

        .action-dropdown {
            padding: 0.6rem 0.9rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            background: white;
            cursor: pointer;
            min-width: 140px;
            height: 38px;
        }

        .action-dropdown:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(142, 68, 173, 0.1);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            margin-top: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1300px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        th, td {
            padding: 0.9rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid var(--light);
        }

        th {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 0.9rem;
        }

        th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, var(--secondary), transparent);
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .actions {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 18px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 85px;
            justify-content: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }

        .status-pending { 
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-approved { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-rejected { 
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .clearance-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
            justify-content: center;
            min-width: 100px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }

        .clearance-approved { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .clearance-pending { 
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .clearance-rejected { 
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .clearance-not_requested { 
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .priority-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--success);
            color: white;
            font-size: 0.7rem;
            margin-right: 0.4rem;
            box-shadow: 0 2px 6px rgba(39, 174, 96, 0.3);
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

        .btn {
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .btn-bulk {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .reject-modal, .bulk-reject-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            z-index: 1100;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            padding: 1.8rem;
            border-radius: 14px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            min-width: 450px;
            max-width: 90vw;
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            animation: slideUp 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translate(-50%, -45%);
            }
            to { 
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .error-message {
            color: var(--danger);
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            border-left: 4px solid var(--danger);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
        }

        .success-message {
            color: var(--success);
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            border-left: 4px solid var(--success);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
        }

        .clearance-notice {
            background: linear-gradient(135deg, #e8f5e9, #d4edda);
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 0.9rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .clearance-notice i {
            color: var(--success);
            font-size: 1.3rem;
        }

        .empty-state {
            text-align: center;
            padding: 2.5rem 1.5rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .action-form {
            display: flex;
            gap: 0.4rem;
            align-items: center;
        }

        .action-option:disabled {
            color: #6c757d;
            background-color: #e9ecef;
            opacity: 0.6;
        }

        /* New styles for locked requests */
        .finalized-badge {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
            justify-content: center;
            min-width: 100px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border: 1px solid #138496;
        }

        .action-locked {
            color: #6c757d;
            background-color: #e9ecef;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .locked-row {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
        }

        .locked-row:hover {
            background: linear-gradient(135deg, #f1f3f4, #dee2e6) !important;
        }

        /* Academic Year Badge */
        .academic-year-badge {
            background: linear-gradient(135deg, #8e44ad, #7d3c98);
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

        /* Department Badge */
        .department-badge {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-left: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                padding: 1rem;
            }
        }

        @media (max-width: 992px) {
            .admin-header, .admin-nav {
                padding: 0.8rem 1.2rem;
                height: 60px;
            }
            
            .admin-nav {
                top: 60px;
            }
            
            .main-content {
                padding: 0.8rem;
                min-height: calc(100vh - 120px);
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .admin-nav {
                flex-direction: column;
                align-items: flex-start;
                height: auto;
                padding: 1rem;
            }
            
            .filters {
                width: 100%;
                justify-content: space-between;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }
            
            .section {
                padding: 1.2rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }
            
            .search-box, .status-filter {
                width: 100%;
            }
            
            table {
                font-size: 0.8rem;
                min-width: 1200px;
            }
            
            th, td {
                padding: 0.7rem 0.6rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .modal-content {
                min-width: 95%;
                padding: 1.5rem;
            }
            
            .action-dropdown {
                min-width: 120px;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .academic-year-badge, .department-badge {
                font-size: 0.7rem;
                padding: 0.3rem 0.6rem;
                margin-left: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .admin-header {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
                height: auto;
                padding: 0.8rem;
            }
            
            .stat-card {
                padding: 1rem 0.8rem;
                min-height: 100px;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
            
            .section {
                padding: 1rem;
            }
            
            .modal-content {
                padding: 1.2rem;
            }

            .academic-year-badge, .department-badge {
                font-size: 0.6rem;
                padding: 0.2rem 0.4rem;
                margin-left: 0.3rem;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 6px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <h1 style="font-size: 1.4rem;"><i class="fas fa-university"></i> Department Admin Dashboard</h1>
        <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.9rem;">
            <span><i class="fas fa-user-circle"></i> Welcome <?php echo htmlspecialchars($_SESSION['admin_name'] . ' ' . $_SESSION['admin_last_name']); ?></span>
            <span class="department-badge">
                <i class="fas fa-building"></i> <?php echo htmlspecialchars($admin_department); ?>
            </span>
            <a href="../logout.php" style="color: white; text-decoration: none; padding: 0.4rem 0.8rem; background: rgba(255,255,255,0.2); border-radius: 6px;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <nav class="admin-nav">
        <h2 style="font-size: 1.2rem; margin: 0;"><i class="fas fa-tachometer-alt"></i> Department Clearance 
            <span class="academic-year-badge">
                <i class="fas fa-calendar-alt"></i> <?php echo $current_academic_year; ?>
            </span>
        </h2>
        <div class="filters">
            <input type="text" class="search-box" placeholder="🔍 Search students..." 
                   name="search"
                   value="<?php echo htmlspecialchars($search); ?>"
                   onchange="this.form.submit()" form="filter-form">
            <select class="status-filter" name="status" onchange="this.form.submit()" form="filter-form">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
    </nav>

    <div class="main-content">
        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card total">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-icon">❌</div>
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Clearance Notice -->
        <div class="clearance-notice">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Clearance Rules for <?php echo htmlspecialchars($admin_department); ?> Department:</strong> 
                • Only showing <?php echo $current_academic_year; ?> academic year requests
                • Only showing students from <strong><?php echo htmlspecialchars($admin_department); ?></strong> department
                • Only showing students already approved by Dormitory
                • Dormitory approval implies Library and Cafeteria are also approved
                • Look for the <span style="color: var(--success);">⭐ star indicator</span> on ready-to-approve requests
                • <span style="color: var(--danger);">Requests are locked once Academic Staff grants final clearance</span>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <!-- All Requests Section -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-list-alt"></i>
                    Department Clearance Requests - <?php echo htmlspecialchars($admin_department); ?> 
                    (<?php echo $current_academic_year; ?> Academic Year)
                </h3>
                <form id="filter-form" method="GET" style="display: none;">
                    <!-- No hidden inputs needed - form fields are handled by the visible inputs -->
                </form>
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

            <?php if ($all_requests->num_rows > 0): ?>
                <form id="bulkActionForm" method="POST">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th><i class="fas fa-user"></i> Student</th>
                                    <th><i class="fas fa-id-card"></i> ID</th>
                                    <th><i class="fas fa-building"></i> Department</th>
                                    <th><i class="fas fa-calendar"></i> Date</th>
                                    <th><i class="fas fa-comment"></i> Reason</th>
                                    <th><i class="fas fa-book"></i> Library</th>
                                    <th><i class="fas fa-utensils"></i> Cafeteria</th>
                                    <th><i class="fas fa-bed"></i> Dormitory</th>
                                    <th><i class="fas fa-graduation-cap"></i> Academic Staff</th>
                                    <th><i class="fas fa-tag"></i> Status</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($request = $all_requests->fetch_assoc()): 
                                    // Get actual status for each department
                                    $library_status = $request['library_status'] ?? 'not_requested';
                                    $cafeteria_status = $request['cafeteria_status'] ?? 'not_requested';
                                    $dormitory_status = $request['dormitory_status'] ?? 'not_requested';
                                    $academic_status = $request['academic_status'] ?? 'pending';
                                    
                                    // Check if request is locked (final clearance granted)
                                    $is_locked = ($academic_status === 'approved');
                                    
                                    // Only check dormitory status for approval (since dormitory already checks library and cafeteria)
                                    $can_approve = ($dormitory_status === 'approved') && !$is_locked;
                                    $priority = ($request['status'] == 'pending' && $can_approve);
                                    
                                    // Determine available actions
                                    $current_status = $request['status'];
                                    $show_approve = ($current_status !== 'approved') && !$is_locked;
                                    $show_reject = ($current_status !== 'rejected') && !$is_locked;
                                ?>
                                <tr class="<?php echo $is_locked ? 'locked-row' : ''; ?>">
                                    <td class="checkbox-cell">
                                        <?php if (!$is_locked): ?>
                                            <input type="checkbox" name="selected_requests[]" value="<?php echo $request['id']; ?>" 
                                                   class="request-checkbox" onchange="updateBulkActions()">
                                        <?php else: ?>
                                            <input type="checkbox" disabled title="Request locked - Final clearance granted">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($priority): ?>
                                            <span class="priority-indicator" title="Ready for approval - Dormitory cleared">
                                                <i class="fas fa-star"></i>
                                            </span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($request['student_name']); ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($request['student_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($request['department']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></td>
                                    <td><?php echo !empty($request['reason']) ? htmlspecialchars($request['reason']) : '-'; ?></td>
                                    <td>
                                        <span class="clearance-badge clearance-<?php echo $library_status; ?>" title="Library: <?php echo ucfirst($library_status); ?>">
                                            <i class="fas fa-<?php 
                                                echo $library_status === 'approved' ? 'check' : 
                                                     ($library_status === 'pending' ? 'clock' : 
                                                     ($library_status === 'rejected' ? 'times' : 'question')); 
                                            ?>"></i>
                                            <?php echo ucfirst($library_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="clearance-badge clearance-<?php echo $cafeteria_status; ?>" title="Cafeteria: <?php echo ucfirst($cafeteria_status); ?>">
                                            <i class="fas fa-<?php 
                                                echo $cafeteria_status === 'approved' ? 'check' : 
                                                     ($cafeteria_status === 'pending' ? 'clock' : 
                                                     ($cafeteria_status === 'rejected' ? 'times' : 'question')); 
                                            ?>"></i>
                                            <?php echo ucfirst($cafeteria_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="clearance-badge clearance-<?php echo $dormitory_status; ?>" title="Dormitory: <?php echo ucfirst($dormitory_status); ?>">
                                            <i class="fas fa-<?php 
                                                echo $dormitory_status === 'approved' ? 'check' : 
                                                     ($dormitory_status === 'pending' ? 'clock' : 
                                                     ($dormitory_status === 'rejected' ? 'times' : 'question')); 
                                            ?>"></i>
                                            <?php echo ucfirst($dormitory_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($academic_status === 'approved'): ?>
                                            <span class="finalized-badge" title="Final clearance granted by Academic Staff">
                                                <i class="fas fa-lock"></i>
                                                Finalized
                                            </span>
                                        <?php else: ?>
                                            <span class="clearance-badge clearance-<?php echo $academic_status; ?>" title="Academic Staff: <?php echo ucfirst($academic_status); ?>">
                                                <i class="fas fa-<?php 
                                                    echo $academic_status === 'approved' ? 'check' : 
                                                         ($academic_status === 'pending' ? 'clock' : 
                                                         ($academic_status === 'rejected' ? 'times' : 'question')); 
                                                ?>"></i>
                                                <?php echo ucfirst($academic_status); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <i class="fas fa-clock"></i>
                                            <?php elseif ($request['status'] === 'approved'): ?>
                                                <i class="fas fa-check-circle"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle"></i>
                                            <?php endif; ?>
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <div class="action-form">
                                            <?php if ($is_locked): ?>
                                                <select class="action-dropdown action-locked" disabled title="Request locked - Final clearance granted">
                                                    <option value="">Locked</option>
                                                </select>
                                            <?php else: ?>
                                                <select class="action-dropdown" 
                                                        id="action-<?php echo $request['id']; ?>" 
                                                        onchange="handleActionChange(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>', <?php echo $can_approve ? 'true' : 'false'; ?>, <?php echo $show_approve ? 'true' : 'false'; ?>, <?php echo $show_reject ? 'true' : 'false'; ?>)">
                                                    <option value="">Action</option>
                                                    <?php if ($show_approve): ?>
                                                        <option value="approve" <?php echo !$can_approve ? 'disabled' : ''; ?> class="action-option">
                                                            <?php echo $current_status === 'rejected' ? 'Change to Approve' : 'Approve'; ?>
                                                        </option>
                                                    <?php endif; ?>
                                                    <?php if ($show_reject): ?>
                                                        <option value="reject" class="action-option">
                                                            <?php echo $current_status === 'approved' ? 'Change to Reject' : 'Reject'; ?>
                                                        </option>
                                                    <?php endif; ?>
                                                </select>
                                                
                                                <form method="POST" id="approve-form-<?php echo $request['id']; ?>" style="display: none;">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <input type="hidden" name="action_type" value="approve">
                                                </form>
                                            <?php endif; ?>
                                        </div>
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
                    <h3>No clearance requests found</h3>
                    <p>There are currently no <?php echo $current_academic_year; ?> clearance requests from <?php echo htmlspecialchars($admin_department); ?> students who have been approved by Dormitory.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Single Reject Modal -->
    <div id="rejectModal" class="reject-modal">
        <div class="modal-content">
            <h3 id="modal-title" style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1.2rem;">
                <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                Reject Clearance Request
            </h3>
            
            <!-- Error Message -->
            <div id="error-message" class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span>Reject reason is required!</span>
            </div>
            
            <form method="POST" action="" id="reject-form">
                <input type="hidden" name="request_id" id="reject-request-id">
                <input type="hidden" name="action_type" value="reject">
                <div style="margin: 1.2rem 0;">
                    <label for="reject_reason" style="display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem;">
                        Reason for rejection: <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea name="reject_reason" id="reject_reason" 
                              style="width: 100%; padding: 0.8rem; border: 1px solid #e1e5e9; border-radius: 8px; font-family: inherit; resize: vertical; font-size: 0.9rem;"
                              rows="3" placeholder="Please provide a clear reason for rejecting this clearance request..." 
                              oninput="validateRejectReason()"></textarea>
                    <small style="color: #6c757d; display: block; margin-top: 0.4rem; font-size: 0.8rem;">
                        <i class="fas fa-info-circle"></i> Reject reason is required and will be visible to the student
                    </small>
                </div>
                <div style="display: flex; gap: 0.8rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="hideRejectModal()" style="background: #6c757d; color: white; padding: 0.6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn" id="confirm-reject-btn" disabled style="background: var(--danger); color: white; padding: 0.6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem;">
                        <i class="fas fa-ban"></i> Confirm Reject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Reject Modal -->
    <div id="bulkRejectModal" class="bulk-reject-modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1.2rem;">
                <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                Reject Selected Requests
            </h3>
            
            <!-- Error Message -->
            <div id="bulk-error-message" class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span>Reject reason is required!</span>
            </div>
            
            <div style="margin: 1.2rem 0;">
                <label for="bulk_reject_reason" style="display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem;">
                    Reason for rejection (will apply to all selected requests): <span style="color: var(--danger);">*</span>
                </label>
                <textarea name="bulk_reject_reason" id="bulk_reject_reason" 
                          style="width: 100%; padding: 0.8rem; border: 1px solid #e1e5e9; border-radius: 8px; font-family: inherit; resize: vertical; font-size: 0.9rem;"
                          rows="3" placeholder="Please provide a clear reason for rejecting these clearance requests..." 
                          oninput="validateBulkRejectReason()"></textarea>
                <small style="color: #6c757d; display: block; margin-top: 0.4rem; font-size: 0.8rem;">
                    <i class="fas fa-info-circle"></i> This reason will be applied to all selected requests
                </small>
            </div>
            <div style="display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn" onclick="hideBulkRejectModal()" style="background: #6c757d; color: white; padding: 0.6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn" id="confirm-bulk-reject-btn" onclick="confirmBulkReject()" disabled style="background: var(--danger); color: white; padding: 0.6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem;">
                    <i class="fas fa-ban"></i> Reject All Selected
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentRequestId = null;
        let currentRequestStatus = null;

        // Single action functions
        function handleActionChange(requestId, currentStatus, canApprove, showApprove, showReject) {
            const dropdown = document.getElementById('action-' + requestId);
            const selectedAction = dropdown.value;
            
            currentRequestId = requestId;
            currentRequestStatus = currentStatus;
            
            // Check if the row is locked (has final clearance)
            const row = dropdown.closest('tr');
            const isLocked = row.classList.contains('locked-row');
            
            if (isLocked) {
                alert('This request is locked because final clearance has been granted by Academic Staff.');
                dropdown.value = '';
                return;
            }
            
            if (selectedAction === 'approve') {
                if (canApprove) {
                    document.getElementById('approve-form-' + requestId).submit();
                } else {
                    alert('Cannot approve: Student must be cleared by Dormitory first. Please check the Dormitory status column.');
                    dropdown.value = '';
                }
            } else if (selectedAction === 'reject') {
                showRejectModal(requestId, currentStatus);
            }
            
            // Reset dropdown after action
            setTimeout(() => {
                dropdown.value = '';
            }, 100);
        }

        function showRejectModal(requestId, currentStatus) {
            document.getElementById('reject-request-id').value = requestId;
            document.getElementById('rejectModal').style.display = 'block';
            document.getElementById('error-message').style.display = 'none';
            document.getElementById('reject_reason').value = '';
            document.getElementById('confirm-reject-btn').disabled = true;
            
            // Update modal title based on current status
            const modalTitle = document.getElementById('modal-title');
            if (currentStatus === 'approved') {
                modalTitle.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Change Approved to Rejected';
            } else {
                modalTitle.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Reject Clearance Request';
            }
        }

        function hideRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('reject_reason').value = '';
            document.getElementById('error-message').style.display = 'none';
            
            // Reset the dropdown
            if (currentRequestId) {
                const dropdown = document.getElementById('action-' + currentRequestId);
                if (dropdown) {
                    dropdown.value = '';
                }
            }
        }

        function validateRejectReason() {
            const rejectReason = document.getElementById('reject_reason').value.trim();
            const confirmBtn = document.getElementById('confirm-reject-btn');
            
            if (rejectReason.length > 0) {
                confirmBtn.disabled = false;
            } else {
                confirmBtn.disabled = true;
            }
        }

        function validateRejectForm() {
            const rejectReason = document.getElementById('reject_reason').value.trim();
            const errorMessage = document.getElementById('error-message');
            
            if (rejectReason.length === 0) {
                errorMessage.style.display = 'block';
                return false; // Prevent form submission
            }
            
            return true; // Allow form submission
        }

        // Bulk actions functions
        function toggleSelectAll(selectAllCheckbox) {
            const checkboxes = document.querySelectorAll('.request-checkbox:not(:disabled)');
            const allChecked = selectAllCheckbox.checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = allChecked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCount = document.querySelectorAll('.request-checkbox:checked').length;
            const totalCheckboxes = document.querySelectorAll('.request-checkbox:not(:disabled)').length;
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
            const checkboxes = document.querySelectorAll('.request-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            document.getElementById('selectAll').indeterminate = false;
            updateBulkActions();
        }

        function applyBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            const selectedCount = document.querySelectorAll('.request-checkbox:checked').length;
            
            if (selectedCount === 0) {
                alert('Please select at least one request.');
                return;
            }
            
            if (action === 'approve') {
                if (confirm(`Are you sure you want to approve ${selectedCount} request(s)? Only requests with Dormitory approval will be processed.`)) {
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
            const selectedCount = document.querySelectorAll('.request-checkbox:checked').length;
            
            if (rejectReason.length === 0) {
                document.getElementById('bulk-error-message').style.display = 'block';
                return;
            }
            
            if (confirm(`Are you sure you want to reject ${selectedCount} request(s) with this reason?`)) {
                document.getElementById('bulkActionInput').value = 'reject';
                document.getElementById('bulkRejectReasonInput').value = rejectReason;
                document.getElementById('bulkActionForm').submit();
            }
        }

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

        // Show error message if there was a PHP validation error
        <?php if (isset($error_message)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const rejectReason = document.getElementById('reject_reason');
                if (rejectReason) {
                    document.getElementById('error-message').style.display = 'block';
                    document.getElementById('rejectModal').style.display = 'block';
                }
            });
        <?php endif; ?>

        // Initialize the select all checkbox state
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActions();
        });
    </script>
</body>
</html>