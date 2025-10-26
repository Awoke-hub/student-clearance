<?php
session_start();
include '../includes/db.php';
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'personal_protector') {
    header("Location: login.php");
    exit();
}

// Get current academic year
$current_year = date('Y');
$current_academic_year = $current_year . '-' . ($current_year + 1);

// Initialize variables
$search_term = '';
$clearance_records = [];

// Process search - now searches by both student_id and name
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
    $query = "SELECT * FROM final_clearance WHERE status = 'approved' AND academic_year = ?";
    
    $params = [$current_academic_year];
    $types = "s";
    
    if (!empty($search_term)) {
        $query .= " AND (student_id LIKE ? OR name LIKE ? OR last_name LIKE ?)";
        $search_pattern = "%" . $conn->real_escape_string($search_term) . "%";
        $params = array_merge($params, [$search_pattern, $search_pattern, $search_pattern]);
        $types .= "sss";
    }
    
    $query .= " ORDER BY date_sent DESC";
    
    // Use prepared statement
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $clearance_records = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Show only approved students by default for current academic year
    $query = "SELECT * FROM final_clearance WHERE status = 'approved' AND academic_year = ? ORDER BY date_sent DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $current_academic_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $clearance_records = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Clearance Records</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --dark-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, var(--dark-color), #34495e);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(40%, -40%);
        }

        .header h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .logout-btn {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-50%) scale(1.05);
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

        .search-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
        }

        .search-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .search-header i {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .search-header h2 {
            color: var(--dark-color);
            font-size: 1.4rem;
        }

        .search-form {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .search-btn, .clear-btn {
            padding: 14px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        }

        .clear-btn {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            text-decoration: none;
        }

        .clear-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(149, 165, 166, 0.4);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
        }

        thead {
            background: linear-gradient(135deg, var(--dark-color), #34495e);
        }

        th {
            padding: 18px 20px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            position: relative;
        }

        th::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 60%;
            background: rgba(255, 255, 255, 0.3);
        }

        th:last-child::after {
            display: none;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f2f6;
            color: #2d3748;
        }

        tr {
            transition: background-color 0.3s ease;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-approved {
            background: #e8f6f3;
            color: var(--success-color);
            border: 1px solid #a3e4d7;
        }

        .status-pending {
            background: #fef9e7;
            color: var(--warning-color);
            border: 1px solid #fad7a0;
        }

        .status-rejected {
            background: #fdedec;
            color: var(--danger-color);
            border: 1px solid #f5b7b1;
        }

        .no-records {
            text-align: center;
            padding: 60px 30px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
        }

        .no-records i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        .no-records h3 {
            color: var(--dark-color);
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .no-records p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .student-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }

        .info-message {
            background: linear-gradient(135deg, #e8f6f3, #d1f2eb);
            border: 1px solid #a3e4d7;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #27ae60;
            font-weight: 500;
        }

        .info-message i {
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .header {
                padding: 20px;
                text-align: center;
            }

            .header h1 {
                font-size: 1.8rem;
                justify-content: center;
            }

            .logout-btn {
                position: relative;
                right: auto;
                top: auto;
                transform: none;
                margin-top: 15px;
                display: inline-flex;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input, .search-btn, .clear-btn {
                width: 100%;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            .academic-year-badge {
                font-size: 0.7rem;
                padding: 0.3rem 0.6rem;
                margin-left: 0.5rem;
            }
        }

        /* Animation for table rows */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        tbody tr {
            animation: fadeIn 0.5s ease-out;
        }

        tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        tbody tr:nth-child(even):hover {
            background-color: #f1f3f4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>
                    <i class="fas fa-user-shield"></i> Final Clearance Records
                    <span class="academic-year-badge">
                        <i class="fas fa-calendar-alt"></i> <?php echo $current_academic_year; ?>
                    </span>
                </h1>
                <p>View students approved by registrar for university exit - <?php echo $current_academic_year; ?> Academic Year</p>
            </div>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>

        <div class="info-message">
            <i class="fas fa-info-circle"></i>
            <span>Showing only <?php echo $current_academic_year; ?> students approved by registrar. Search by Student ID or Name.</span>
        </div>

        <div class="search-container">
            <div class="search-header">
                <i class="fas fa-search"></i>
                <h2>Search Approved Students</h2>
            </div>
            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" 
                       placeholder="Search by Student ID or Name..." 
                       value="<?= htmlspecialchars($search_term) ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="?" class="clear-btn">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($clearance_records)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Department</th>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Approval Date</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clearance_records as $record): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center;">
                                <div class="student-avatar">
                                    <?= strtoupper(substr($record['student_id'], -2)) ?>
                                </div>
                                <?= htmlspecialchars($record['student_id']) ?>
                            </div>
                        </td>
                        <td><strong><?= htmlspecialchars($record['name']) ?> <?= htmlspecialchars($record['last_name']) ?></strong></td>
                        <td><?= htmlspecialchars($record['department']) ?></td>
                        <td>
                            <?php 
                            // Display academic_year if available, otherwise fallback to year
                            $display_year = !empty($record['academic_year']) ? $record['academic_year'] : $record['year'];
                            echo htmlspecialchars($display_year); 
                            ?>
                        </td>
                        <td>
                            <span class="status status-<?= htmlspecialchars($record['status']) ?>">
                                <i class="fas fa-check"></i>
                                <?= ucfirst(htmlspecialchars($record['status'])) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($record['date_sent'])) ?></td>
                        <td><?= htmlspecialchars($record['message']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-records">
                <i class="fas fa-inbox"></i>
                <h3>No approved records found</h3>
                <p><?= empty($search_term) ? 
                    'No students have been approved by registrar for the ' . $current_academic_year . ' academic year yet' : 
                    'No approved students found for "' . htmlspecialchars($search_term) . '" in ' . $current_academic_year . ' academic year' ?></p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to search button
            const searchBtn = document.querySelector('.search-btn');
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-search"></i> Search';
                    }, 2000);
                });
            }
        });
    </script>
</body>
</html>