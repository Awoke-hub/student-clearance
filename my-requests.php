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
        margin-left: 280px;
        margin-top: 80px;
        padding: 20px;
        min-height: calc(100vh - 80px);
        background: var(--content-bg);
        color: var(--content-text);
    }

    .clearance-container {
        max-width: 900px;
        margin: 0 auto;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .clearance-title {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 20px;
        font-size: 24px;
        padding-bottom: 10px;
        border-bottom: 2px solid #bdc3c7;
    }

    .academic-year-badge {
        background: linear-gradient(135deg, #3498db, #2980b9);
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
    
    .clearance-table {
        width: 100%;
        border-collapse: collapse;
        text-align: center;
        font-size: 14px;
        margin-top: 20px;
    }
    
    .clearance-table th {
        background: var(--primary-color);
        color: white;
        padding: 12px 8px;
        font-weight: 500;
        border: 1px solid var(--secondary-color);
    }
    
    .clearance-table td {
        padding: 10px 8px;
        background: white;
        color: var(--content-text);
        border: 1px solid #e0e0e0;
    }
    
    .clearance-table tr:hover td {
        background-color: #f8f9fa;
    }
    
    /* Enhanced Status Styling with Icons and Better Colors */
    .status-pending {
        background-color: #fff3cd;
        color: #856404;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
        display: inline-block;
        border-left: 3px solid #ffc107;
    }
    
    .status-pending::before {
        content: "🟡 ";
        font-size: 12px;
    }
    
    .status-approved {
        background-color: #d4edda;
        color: #155724;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
        display: inline-block;
        border-left: 3px solid #28a745;
    }
    
    .status-approved::before {
        content: "🟢 ";
        font-size: 12px;
    }
    
    .status-rejected {
        background-color: #f8d7da;
        color: #721c24;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
        display: inline-block;
        border-left: 3px solid #dc3545;
    }
    
    .status-rejected::before {
        content: "🔴 ";
        font-size: 12px;
    }

    .request-date {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 0.2rem;
    }
    
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-top: 20px;
        border-radius: 6px;
        border: 1px solid #e0e0e0;
    }

    .empty-state {
        text-align: center;
        padding: 2.5rem 1.5rem;
        color: #6c757d;
        background: #f8f9fa;
        border-radius: 8px;
        margin-top: 1rem;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state h3 {
        margin-bottom: 0.5rem;
        color: #495057;
    }

    .empty-state p {
        margin-bottom: 0;
        font-size: 0.9rem;
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

        .clearance-table th, 
        .clearance-table td {
            padding: 8px 6px;
            font-size: 13px;
        }
        
        .clearance-container {
            margin: 0 auto;
            padding: 15px;
        }
        
        .clearance-title {
            font-size: 20px;
        }
        
        .status-pending::before,
        .status-approved::before,
        .status-rejected::before {
            font-size: 10px;
        }

        .academic-year-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            margin-left: 0.5rem;
        }
    }
    
    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .clearance-table th, 
        .clearance-table td {
            padding: 6px 4px;
            font-size: 12px;
        }
        
        .clearance-title {
            font-size: 18px;
        }
        
        .status-pending,
        .status-approved,
        .status-rejected {
            padding: 2px 4px;
            font-size: 11px;
        }
        
        .clearance-container {
            padding: 10px;
        }
        
        .status-pending::before,
        .status-approved::before,
        .status-rejected::before {
            font-size: 9px;
        }

        .academic-year-badge {
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
            margin-left: 0.3rem;
        }

        .request-date {
            font-size: 0.7rem;
        }
    }
</style>

<div class="main-content">
    <div class="clearance-container">
        <h2 class="clearance-title">
            My Clearance Requests 
            <span class="academic-year-badge">
                <i class="fas fa-calendar-alt"></i> <?php echo $current_academic_year; ?>
            </span>
        </h2>
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
                    <td><?php echo htmlspecialchars($type); ?></td>
                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                    <td><?php echo htmlspecialchars($row['reason']); ?></td>
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
                    <td>
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
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>