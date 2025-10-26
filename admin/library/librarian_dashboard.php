<?php
// Turn on all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
include '../../includes/db.php';

// Check if user is logged in and is a library admin
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'library_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// === ADD THIS: Get current year for filtering ===
$currentYear = date("Y");

// CORRECTED FUNCTION: Check if cafeteria has approved this student (ONLY cafeteria matters for library lock)
function isLockedForLibrary($student_id, $conn, $academic_year) {
    $stmt = $conn->prepare("SELECT status FROM cafeteria_clearance WHERE student_id = ? AND academic_year = ? AND status = 'approved'");
    $stmt->bind_param("si", $student_id, $academic_year);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Handle Approve/Reject actions (Single and Bulk)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Single Approve
    if (isset($_POST['approve'])) {
        $request_id = $_POST['request_id'];
        
        // Get student_id for this request
        $stmt = $conn->prepare("SELECT student_id FROM library_clearance WHERE id = ? AND academic_year = ?");
        $stmt->bind_param("ii", $request_id, $currentYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $request_data = $result->fetch_assoc();
            $student_id = $request_data['student_id'];
            
            // Check if locked (ONLY check if cafeteria has approved)
            if (isLockedForLibrary($student_id, $conn, $currentYear)) {
                $error_message = "Cannot modify: Cafeteria has already approved this student for $currentYear!";
            } else {
                $stmt = $conn->prepare("UPDATE library_clearance SET status='approved', reject_reason=NULL WHERE id=? AND academic_year = ?");
                $stmt->bind_param("ii", $request_id, $currentYear);
                if ($stmt->execute()) {
                    $success_message = "Request approved successfully for $currentYear!";
                } else {
                    $error_message = "Error approving request: " . $stmt->error;
                }
            }
        } else {
            $error_message = "Request not found for $currentYear!";
        }
    } 
    // Single Reject
    elseif (isset($_POST['reject'])) {
        $request_id = $_POST['request_id'];
        $reject_reason = $_POST['reject_reason'] ?? '';
        
        // Get student_id for this request
        $stmt = $conn->prepare("SELECT student_id FROM library_clearance WHERE id = ? AND academic_year = ?");
        $stmt->bind_param("ii", $request_id, $currentYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $request_data = $result->fetch_assoc();
            $student_id = $request_data['student_id'];
            
            // Check if locked (ONLY check if cafeteria has approved)
            if (isLockedForLibrary($student_id, $conn, $currentYear)) {
                $error_message = "Cannot modify: Cafeteria has already approved this student for $currentYear!";
            } else {
                // Validate that reject reason is not empty
                if (!empty(trim($reject_reason))) {
                    $stmt = $conn->prepare("UPDATE library_clearance SET status='rejected', reject_reason=? WHERE id=? AND academic_year = ?");
                    $stmt->bind_param("sii", $reject_reason, $request_id, $currentYear);
                    if ($stmt->execute()) {
                        $success_message = "Request rejected successfully for $currentYear!";
                    } else {
                        $error_message = "Error rejecting request: " . $stmt->error;
                    }
                } else {
                    // Set error message for empty reject reason
                    $error_message = "Reject reason is required!";
                }
            }
        } else {
            $error_message = "Request not found for $currentYear!";
        }
    }
    // Bulk Actions
    elseif (isset($_POST['bulk_action'])) {
        $bulk_action = $_POST['bulk_action'];
        $selected_requests = $_POST['selected_requests'] ?? [];
        
        if (!empty($selected_requests)) {
            $processed_count = 0;
            $locked_count = 0;
            $error_count = 0;
            
            if ($bulk_action === 'approve') {
                // Process each request individually to check locks
                foreach ($selected_requests as $request_id) {
                    // Get student_id for this request
                    $stmt = $conn->prepare("SELECT student_id FROM library_clearance WHERE id = ? AND academic_year = ?");
                    $stmt->bind_param("ii", $request_id, $currentYear);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $request_data = $result->fetch_assoc();
                        $student_id = $request_data['student_id'];
                        
                        // Check if locked (ONLY check cafeteria)
                        if (!isLockedForLibrary($student_id, $conn, $currentYear)) {
                            $stmt = $conn->prepare("UPDATE library_clearance SET status='approved', reject_reason=NULL WHERE id=? AND academic_year = ?");
                            $stmt->bind_param("ii", $request_id, $currentYear);
                            if ($stmt->execute()) {
                                $processed_count++;
                            } else {
                                $error_count++;
                            }
                        } else {
                            $locked_count++;
                        }
                    } else {
                        $error_count++;
                    }
                }
                
                if ($processed_count > 0) {
                    $success_message = $processed_count . " request(s) approved successfully for $currentYear!";
                    if ($locked_count > 0) {
                        $error_message = $locked_count . " request(s) could not be processed (approved by cafeteria).";
                    }
                    if ($error_count > 0) {
                        $error_message = ($error_message ? $error_message . " " : "") . $error_count . " request(s) had errors.";
                    }
                } elseif ($locked_count > 0) {
                    $error_message = "Selected requests are locked and cannot be modified (approved by cafeteria).";
                } else {
                    $error_message = "No requests could be processed. Please check if requests exist for $currentYear.";
                }
            } 
            elseif ($bulk_action === 'reject') {
                $reject_reason = $_POST['bulk_reject_reason'] ?? '';
                
                // Validate that reject reason is not empty for bulk reject
                if (!empty(trim($reject_reason))) {
                    // Process each request individually to check locks
                    foreach ($selected_requests as $request_id) {
                        // Get student_id for this request
                        $stmt = $conn->prepare("SELECT student_id FROM library_clearance WHERE id = ? AND academic_year = ?");
                        $stmt->bind_param("ii", $request_id, $currentYear);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $request_data = $result->fetch_assoc();
                            $student_id = $request_data['student_id'];
                            
                            // Check if locked (ONLY check cafeteria)
                            if (!isLockedForLibrary($student_id, $conn, $currentYear)) {
                                $stmt = $conn->prepare("UPDATE library_clearance SET status='rejected', reject_reason=? WHERE id=? AND academic_year = ?");
                                $stmt->bind_param("sii", $reject_reason, $request_id, $currentYear);
                                if ($stmt->execute()) {
                                    $processed_count++;
                                } else {
                                    $error_count++;
                                }
                            } else {
                                $locked_count++;
                            }
                        } else {
                            $error_count++;
                        }
                    }
                    
                    if ($processed_count > 0) {
                        $success_message = $processed_count . " request(s) rejected successfully for $currentYear!";
                        if ($locked_count > 0) {
                            $error_message = $locked_count . " request(s) could not be processed (approved by cafeteria).";
                        }
                        if ($error_count > 0) {
                            $error_message = ($error_message ? $error_message . " " : "") . $error_count . " request(s) had errors.";
                        }
                    } elseif ($locked_count > 0) {
                        $error_message = "Selected requests are locked and cannot be modified (approved by cafeteria).";
                    } else {
                        $error_message = "No requests could be processed. Please check if requests exist for $currentYear.";
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

// Get statistics - MODIFIED to include year filter
$stats_stmt = $conn->prepare("
    SELECT 
        SUM(status = 'pending') as pending,
        SUM(status = 'approved') as approved,
        SUM(status = 'rejected') as rejected
    FROM library_clearance
    WHERE academic_year = ?
");
$stats_stmt->bind_param("i", $currentYear);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Initialize stats if null
if (!$stats) {
    $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
}

// Get ALL requests (pending, approved, rejected) for the main table - MODIFIED query
$search = $_GET['search'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

$main_query = "
    SELECT lc.*, s.name, s.department 
    FROM library_clearance lc 
    JOIN student s ON lc.student_id = s.student_id 
    WHERE lc.academic_year = ?
";

// Apply search filter
if (!empty($search)) {
    $search_term = "%$search%";
    $main_query .= " AND (s.name LIKE ? OR s.student_id LIKE ?)";
}

// Apply date filter
if (!empty($filter_date)) {
    $main_query .= " AND DATE(lc.requested_at) = ?";
}

// Apply status filter
if ($status_filter !== 'all') {
    $main_query .= " AND lc.status = ?";
}

$main_query .= " ORDER BY lc.requested_at DESC";

$main_stmt = $conn->prepare($main_query);

// Dynamic parameter binding - MODIFIED to include currentYear
$param_types = 'i';
$param_values = [$currentYear];

if (!empty($search)) {
    $param_types .= 'ss';
    $param_values[] = "%$search%";
    $param_values[] = "%$search%";
}

if (!empty($filter_date)) {
    $param_types .= 's';
    $param_values[] = $filter_date;
}

if ($status_filter !== 'all') {
    $param_types .= 's';
    $param_values[] = $status_filter;
}

// Bind parameters
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
    <title>Library Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-danger: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --shadow-hover: 0 15px 40px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            min-height: 100vh;
        }

        .admin-header {
            background: var(--primary);
            color: white;
            padding: 1.2rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: relative;
            z-index: 100;
        }

        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--secondary), var(--success), var(--warning));
        }

        .admin-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.2rem 2rem;
            border-bottom: 1px solid rgba(222, 226, 230, 0.8);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        }

        .main-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Year Indicator */
        .year-indicator {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #1976d2;
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.15);
        }

        .year-indicator h3 {
            margin: 0;
            color: #1976d2;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .year-indicator p {
            margin: 5px 0 0 0;
            color: #555;
            font-size: 14px;
            opacity: 0.9;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.2rem 1rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: none;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--secondary);
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
            margin-bottom: 0.6rem;
            opacity: 0.8;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 0.4rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.8rem;
            margin-bottom: 2rem;
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
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--secondary);
        }

        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box, .status-filter {
            padding: 0.7rem 1rem;
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .search-box:focus, .status-filter:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-1px);
        }

        .btn {
            padding: 0.7rem 1.3rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn-approve {
            background: linear-gradient(135deg, var(--success), #2ecc71);
            color: white;
        }

        .btn-reject {
            background: linear-gradient(135deg, var(--danger), #e74c3c);
            color: white;
        }

        .btn-bulk {
            background: linear-gradient(135deg, var(--secondary), #3498db);
            color: white;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        th, td {
            padding: 1.1rem;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
        }

        th {
            background: linear-gradient(135deg, var(--primary), #34495e);
            color: white;
            font-weight: 600;
            position: relative;
        }

        th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--secondary), transparent);
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 0.45rem 0.9rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 90px;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
        .status-locked { 
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            color: #6c757d;
            border: 1px solid #ced4da;
        }

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
            background: var(--secondary);
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

        .reject-modal, .bulk-reject-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            min-width: 450px;
            max-width: 90vw;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: slideUp 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to { 
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .error-message {
            color: var(--danger);
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            padding: 0.9rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            border-left: 4px solid var(--danger);
            display: none;
            animation: shake 0.5s ease-in-out;
        }

        .success-message {
            color: var(--success);
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            padding: 0.9rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            border-left: 4px solid var(--success);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 2.5rem 1.5rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 0.8rem;
            opacity: 0.5;
        }

        .locked-info {
            background: #f8f9fa;
            color: #6c757d;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            border-left: 3px solid #6c757d;
        }

        /* Floating background elements */
        .floating-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(155, 89, 182, 0.1));
            animation: float 6s ease-in-out infinite;
        }

        .circle-1 {
            width: 250px;
            height: 250px;
            top: -120px;
            right: -80px;
            animation-delay: 0s;
        }

        .circle-2 {
            width: 180px;
            height: 180px;
            bottom: -80px;
            left: -40px;
            animation-delay: 2s;
        }

        .circle-3 {
            width: 120px;
            height: 120px;
            top: 50%;
            right: 8%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(180deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box, .status-filter {
                width: 100%;
            }

            table {
                font-size: 0.75rem;
            }

            th, td {
                padding: 0.7rem;
            }

            .actions {
                flex-direction: column;
            }

            .modal-content {
                min-width: 90%;
                margin: 1rem;
                padding: 1.5rem;
            }

            .admin-header, .admin-nav {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .section {
                padding: 1.3rem;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @media (max-width: 480px) {
            .section {
                padding: 1rem;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            .stat-card {
                padding: 1rem 0.8rem;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            border-radius: 8px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }
    </style>
</head>
<body>
    <!-- Floating Background Elements -->
    <div class="floating-bg">
        <div class="floating-circle circle-1"></div>
        <div class="floating-circle circle-2"></div>
        <div class="floating-circle circle-3"></div>
    </div>

    <header class="admin-header">
        <h1><i class="fas fa-book-reader"></i> Debre Berhan University - Library Admin</h1>
        <div>
            <span><i class="fas fa-user-shield"></i> Welcome, Library Admin</span>
            <a href="../logout.php" style="color: white; margin-left: 1.5rem; text-decoration: none;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <nav class="admin-nav">
        <h2><i class="fas fa-tachometer-alt"></i> Clearance Dashboard</h2>
        <div class="filters">
            <input type="text" class="search-box" placeholder="🔍 Search students..." 
                   name="search"
                   value="<?php echo htmlspecialchars($search); ?>"
                   onchange="this.form.submit()" form="filter-form">
            <select class="status-filter" name="status" onchange="this.form.submit()" form="filter-form">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>📊 All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>✅ Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>❌ Rejected</option>
            </select>
        </div>
    </nav>

    <div class="main-content">
        <!-- Year Indicator -->
        <div class="year-indicator">
            <h3>
                <i class="fas fa-calendar-alt"></i>
                Currently Viewing: <strong><?php echo $currentYear; ?> Academic Year</strong> Clearance Requests
            </h3>
            <p>Only showing clearance requests submitted for <?php echo $currentYear; ?>-<?php echo $currentYear + 1; ?> academic year</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="error-message" style="display: block;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending Requests (<?php echo $currentYear; ?>)</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved Requests (<?php echo $currentYear; ?>)</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-icon">❌</div>
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected Requests (<?php echo $currentYear; ?>)</div>
            </div>
        </div>

        <!-- All Requests Section -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-list-alt"></i>
                    All Clearance Requests (<?php echo $currentYear; ?>)
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
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
                                <th><i class="fas fa-user"></i> Student Name</th>
                                <th><i class="fas fa-id-card"></i> Student ID</th>
                                <th><i class="fas fa-building"></i> Department</th>
                                <th><i class="fas fa-calendar"></i> Request Date</th>
                                <th><i class="fas fa-tag"></i> Status</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $all_requests->fetch_assoc()): 
                                $is_locked = isLockedForLibrary($request['student_id'], $conn, $currentYear);
                            ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="selected_requests[]" value="<?php echo $request['id']; ?>" 
                                           class="request-checkbox" onchange="updateBulkActions()"
                                           <?php echo $is_locked ? 'disabled' : ''; ?>>
                                </td>
                                <td><?php echo htmlspecialchars($request['name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($request['student_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($request['department']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></td>
                                <td>
                                    <?php if ($is_locked): ?>
                                        <span class="status-badge status-locked">
                                            <i class="fas fa-lock"></i>
                                            Locked
                                        </span>
                                        <div class="locked-info">
                                            <i class="fas fa-info-circle"></i>
                                            Cafeteria approved (<?php echo $currentYear; ?>)
                                        </div>
                                        <?php if ($request['status'] === 'approved'): ?>
                                            <div style="margin-top: 4px;">
                                                <span class="status-badge status-approved" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;">
                                                    <i class="fas fa-check-circle"></i>
                                                    Approved
                                                </span>
                                            </div>
                                        <?php elseif ($request['status'] === 'rejected'): ?>
                                            <div style="margin-top: 4px;">
                                                <span class="status-badge status-rejected" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;">
                                                    <i class="fas fa-times-circle"></i>
                                                    Rejected
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
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
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <?php if (!$is_locked): ?>
                                        <?php if ($request['status'] !== 'approved'): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="approve" class="btn btn-approve">
                                                <i class="fas fa-check"></i> APPROVE
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($request['status'] !== 'rejected'): ?>
                                        <button type="button" class="btn btn-reject" 
                                                onclick="showRejectModal(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>')">
                                            <i class="fas fa-times"></i> REJECT
                                        </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-size: 0.85rem;">
                                            <i class="fas fa-lock"></i> Locked
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <input type="hidden" name="bulk_action" id="bulkActionInput">
                    <input type="hidden" name="bulk_reject_reason" id="bulkRejectReasonInput">
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No clearance requests found for <?php echo $currentYear; ?></h3>
                    <p>There are currently no clearance requests for the <?php echo $currentYear; ?>-<?php echo $currentYear + 1; ?> academic year.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Single Reject Modal -->
    <div id="rejectModal" class="reject-modal">
        <div class="modal-content">
            <h3 id="modal-title" style="margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.6rem;">
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
                <div style="margin: 1.2rem 0;">
                    <label for="reject_reason" style="display: block; margin-bottom: 0.4rem; font-weight: 500;">
                        Reason for rejection: <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea name="reject_reason" id="reject_reason" 
                              style="width: 100%; padding: 0.8rem; border: 2px solid #e1e5e9; border-radius: 8px; font-family: inherit; resize: vertical;"
                              rows="3" placeholder="Please provide a clear reason for rejecting this clearance request..." 
                              oninput="validateRejectReason()"></textarea>
                    <small style="color: #6c757d; display: block; margin-top: 0.4rem;">
                        <i class="fas fa-info-circle"></i> Reject reason is required and will be visible to the student
                    </small>
                </div>
                <div style="display: flex; gap: 0.8rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="hideRejectModal()" style="background: #6c757d; color: white;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="reject" class="btn btn-reject" id="confirm-reject-btn" onclick="return validateRejectForm()">
                        <i class="fas fa-ban"></i> Confirm Reject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Reject Modal -->
    <div id="bulkRejectModal" class="bulk-reject-modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.6rem;">
                <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                Reject Selected Requests
            </h3>
            
            <!-- Error Message -->
            <div id="bulk-error-message" class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span>Reject reason is required!</span>
            </div>
            
            <div style="margin: 1.2rem 0;">
                <label for="bulk_reject_reason" style="display: block; margin-bottom: 0.4rem; font-weight: 500;">
                    Reason for rejection (will apply to all selected requests): <span style="color: var(--danger);">*</span>
                </label>
                <textarea name="bulk_reject_reason" id="bulk_reject_reason" 
                          style="width: 100%; padding: 0.8rem; border: 2px solid #e1e5e9; border-radius: 8px; font-family: inherit; resize: vertical;"
                          rows="3" placeholder="Please provide a clear reason for rejecting these clearance requests..." 
                          oninput="validateBulkRejectReason()"></textarea>
                <small style="color: #6c757d; display: block; margin-top: 0.4rem;">
                    <i class="fas fa-info-circle"></i> This reason will be applied to all selected requests
                </small>
            </div>
            <div style="display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn" onclick="hideBulkRejectModal()" style="background: #6c757d; color: white;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-reject" id="confirm-bulk-reject-btn" onclick="confirmBulkReject()">
                    <i class="fas fa-ban"></i> Reject All Selected
                </button>
            </div>
        </div>
    </div>

    <script>
        // Single reject modal functions
        function showRejectModal(requestId, currentStatus) {
            document.getElementById('reject-request-id').value = requestId;
            document.getElementById('rejectModal').style.display = 'block';
            document.getElementById('error-message').style.display = 'none';
            document.getElementById('reject_reason').value = '';
            document.getElementById('confirm-reject-btn').disabled = true;
            
            // Update modal title based on current status
            const modalTitle = document.getElementById('modal-title');
            if (currentStatus === 'approved') {
                modalTitle.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Reject Approved Request';
            } else {
                modalTitle.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Reject Clearance Request';
            }
        }

        function hideRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('reject_reason').value = '';
            document.getElementById('error-message').style.display = 'none';
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
                if (selectedCount === totalCheckboxes && totalCheckboxes > 0) {
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
                if (confirm(`Are you sure you want to approve ${selectedCount} request(s)? This will change the status of all selected requests to "approved".`)) {
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
            
            if (confirm(`Are you sure you want to reject ${selectedCount} request(s) with this reason? This will change the status of all selected requests to "rejected".`)) {
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