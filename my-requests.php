<?php

include 'includes/menu.php'; 
include 'includes/db.php';

// Consistent session check with login.php
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get current academic year
$current_year = date('Y');
$current_academic_year = $current_year . '-' . ($current_year + 1);

$tables = [
    'Library' => 'library_clearance',
    'Cafeteria' => 'cafeteria_clearance',
    'Dormitory' => 'dormitory_clearance',
    'Department' => 'department_clearance',
    'Registrar' => 'academicstaff_clearance'
];
?>

<style>
    .main-content {
        margin-left: 250px;
        margin-top: 80px;
        padding: 30px;
        min-height: calc(100vh - 80px);
        background: var(--content-bg);
        color: var(--content-text);
        width: calc(100% - 250px);
    }

    .clearance-container {
        max-width: min(1200px, 98%);
        margin: 0 auto;
        background: white;
        padding: 35px;
        border-radius: 12px;
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
    
    .clearance-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid #3498db;
    }

    .clearance-title {
        color: var(--primary-color);
        font-size: 2.5rem;
        margin-bottom: 10px;
        font-weight: bold;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    .academic-year-badge {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 0.6rem 1.2rem;
        border-radius: 25px;
        font-size: 1rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        margin-left: 1rem;
        box-shadow: 0 3px 8px rgba(52, 152, 219, 0.3);
    }
    
    .clearance-table {
        width: 100%;
        border-collapse: collapse;
        text-align: center;
        font-size: 16px;
        margin-top: 25px;
    }
    
    .clearance-table th {
        background: var(--primary-color);
        color: white;
        padding: 16px 12px;
        font-weight: 600;
        border: 1px solid var(--secondary-color);
        font-size: 1rem;
    }
    
    .clearance-table td {
        padding: 14px 12px;
        background: white;
        color: var(--content-text);
        border: 1px solid #e0e0e0;
        font-size: 0.95rem;
    }
    
    .clearance-table tr:hover td {
        background-color: #f8f9fa;
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
    
    /* Enhanced Status Styling with Icons and Better Colors */
    .status-pending {
        background-color: #fff3cd;
        color: #856404;
        font-weight: bold;
        border-radius: 6px;
        padding: 8px 12px;
        display: inline-block;
        border-left: 4px solid #ffc107;
        font-size: 0.9rem;
    }
    
    .status-pending::before {
        content: "🟡 ";
        font-size: 14px;
    }
    
    .status-approved {
        background-color: #d4edda;
        color: #155724;
        font-weight: bold;
        border-radius: 6px;
        padding: 8px 12px;
        display: inline-block;
        border-left: 4px solid #28a745;
        font-size: 0.9rem;
    }
    
    .status-approved::before {
        content: "🟢 ";
        font-size: 14px;
    }
    
    .status-rejected {
        background-color: #f8d7da;
        color: #721c24;
        font-weight: bold;
        border-radius: 6px;
        padding: 8px 12px;
        display: inline-block;
        border-left: 4px solid #dc3545;
        font-size: 0.9rem;
    }
    
    .status-rejected::before {
        content: "🔴 ";
        font-size: 14px;
    }

    .request-date {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 0.2rem;
        font-weight: 500;
    }
    
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-top: 25px;
        border-radius: 8px;
        border: 2px solid #e0e0e0;
        padding: 5px;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #6c757d;
        background: #f8f9fa;
        border-radius: 10px;
        margin-top: 1rem;
        border: 2px dashed #dee2e6;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }

    .empty-state h3 {
        margin-bottom: 1rem;
        color: #495057;
        font-size: 1.5rem;
    }

    .empty-state p {
        margin-bottom: 0;
        font-size: 1.1rem;
        line-height: 1.6;
    }

    /* Reason column styling */
    .reason-text {
        max-width: 250px;
        word-wrap: break-word;
        line-height: 1.4;
        text-align: left;
        padding: 8px;
    }

    /* Reject reason styling */
    .reject-reason {
        max-width: 200px;
        word-wrap: break-word;
        line-height: 1.4;
        text-align: left;
        padding: 8px;
        font-style: italic;
        color: #dc3545;
        background: #f8f9fa;
        border-radius: 4px;
        border-left: 3px solid #dc3545;
    }
    
    /* Responsive adjustments */
    @media (max-width: 1100px) {
        .main-content {
            margin-left: 220px;
            width: calc(100% - 220px);
        }
    }

    @media (max-width: 900px) {
        .main-content {
            margin-left: 200px;
            width: calc(100% - 200px);
        }

        .clearance-container {
            max-width: 95%;
            padding: 25px;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            margin-top: 70px;
            padding: 20px;
        }

        .clearance-table th, 
        .clearance-table td {
            padding: 12px 8px;
            font-size: 14px;
        }
        
        .clearance-container {
            margin: 0 auto;
            padding: 20px;
        }
        
        .clearance-title {
            font-size: 2rem;
        }
        
        .status-pending::before,
        .status-approved::before,
        .status-rejected::before {
            font-size: 12px;
        }

        .academic-year-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            margin-left: 0.8rem;
        }

        .clearance-header {
            margin-bottom: 25px;
        }

        .empty-state {
            padding: 2rem 1.5rem;
        }

        .empty-state i {
            font-size: 3rem;
        }

        .empty-state h3 {
            font-size: 1.3rem;
        }

        .empty-state p {
            font-size: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .main-content {
            padding: 15px;
        }
        
        .clearance-table th, 
        .clearance-table td {
            padding: 10px 6px;
            font-size: 13px;
        }
        
        .clearance-title {
            font-size: 1.8rem;
        }
        
        .status-pending,
        .status-approved,
        .status-rejected {
            padding: 6px 8px;
            font-size: 12px;
        }
        
        .clearance-container {
            padding: 15px;
        }
        
        .status-pending::before,
        .status-approved::before,
        .status-rejected::before {
            font-size: 10px;
        }

        .academic-year-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            margin-left: 0.5rem;
        }

        .request-date {
            font-size: 0.8rem;
        }

        .reason-text,
        .reject-reason {
            max-width: 150px;
            font-size: 0.8rem;
        }

        .empty-state {
            padding: 1.5rem 1rem;
        }

        .empty-state i {
            font-size: 2.5rem;
        }

        .empty-state h3 {
            font-size: 1.2rem;
        }

        .empty-state p {
            font-size: 0.9rem;
        }
    }

    @media (max-width: 360px) {
        .clearance-table th, 
        .clearance-table td {
            padding: 8px 4px;
            font-size: 12px;
        }
        
        .clearance-title {
            font-size: 1.6rem;
        }
        
        .academic-year-badge {
            display: block;
            margin: 10px auto 0 auto;
            width: fit-content;
        }

        .reason-text,
        .reject-reason {
            max-width: 120px;
            font-size: 0.75rem;
        }
    }
</style>

<div class="main-content">
    <div class="clearance-container">
        <div class="clearance-header">
            <h3 class="clearance-title">
                My Clearance Requests 
                <span class="academic-year-badge">
                    <i class="fas fa-calendar-alt"></i> <?php echo $current_academic_year; ?>
                </span>
            </h3>
        </div>
        <div class="table-container">
            <table class="clearance-table">
                <tr>
                    <th>Clearance Type</th>
                    <th>Department</th>
                    <th>Clearance Reason</th>
                    <th>Status</th>
                    <th>Request Date</th>
                    <th>Reject Reason</th>
                </tr>
                <?php 
                $has_requests = false;
                
                foreach ($tables as $type => $table) {
                    // Check if the table has academic_year column
                    $check_column_stmt = $conn->prepare("SHOW COLUMNS FROM $table LIKE 'academic_year'");
                    $check_column_stmt->execute();
                    $column_exists = $check_column_stmt->get_result()->num_rows > 0;
                    
                    if ($column_exists) {
                        // Query with academic_year filter
                        $stmt = $conn->prepare("SELECT * FROM $table WHERE student_id = ? AND academic_year = ? ORDER BY requested_at DESC, id DESC");
                        $stmt->bind_param("ss", $student_id, $current_academic_year);
                    } else {
                        // Fallback query without academic_year filter (for backward compatibility)
                        $stmt = $conn->prepare("SELECT * FROM $table WHERE student_id = ? ORDER BY requested_at DESC, id DESC");
                        $stmt->bind_param("s", $student_id);
                    }
                    
                    $stmt->execute();
                    $res = $stmt->get_result();
                    
                    while ($row = $res->fetch_assoc()):
                        $has_requests = true;
                        $statusClass = '';
                        if (strtolower($row['status']) == 'pending') {
                            $statusClass = 'status-pending';
                        } elseif (strtolower($row['status']) == 'approved') {
                            $statusClass = 'status-approved';
                        } else {
                            $statusClass = 'status-rejected';
                        }

                        // Format the request date
                        $request_date = '';
                        if (isset($row['requested_at']) && !empty($row['requested_at'])) {
                            $request_date = date('M j, Y', strtotime($row['requested_at']));
                        } elseif (isset($row['created_at']) && !empty($row['created_at'])) {
                            $request_date = date('M j, Y', strtotime($row['created_at']));
                        } else {
                            $request_date = 'N/A';
                        }
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($type); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                    <td class="reason-text"><?php echo htmlspecialchars($row['reason']); ?></td>
                    <td>
                        <span class="<?php echo $statusClass; ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="request-date">
                            <?php echo $request_date; ?>
                        </span>
                    </td>
                    <td class="reject-reason">
                        <?php 
                        echo !empty($row['reject_reason']) ? htmlspecialchars($row['reject_reason']) : '-'; 
                        ?>
                    </td>
                </tr>
                <?php endwhile; 
                } 
                
                if (!$has_requests): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Clearance Requests Found</h3>
                            <p>You haven't submitted any clearance requests for the <?php echo $current_academic_year; ?> academic year.</p>
                            <p style="margin-top: 10px; font-size: 0.9rem; color: #6c757d;">
                                Visit the Clearance Request page to submit your clearance applications.
                            </p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>