<?php
session_start();
include '../includes/db.php';

// Get statistics data
$total_students = $conn->query("SELECT COUNT(*) as total FROM student")->fetch_assoc()['total'];
$total_admins = $conn->query("SELECT COUNT(*) as total FROM admin")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Dashboard</title>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        @keyframes colorChange {
            0% { background-color: #008B8B; }
            50% { background-color: #0b105a; }
            100% { background-color: #008B8B; }
        }
        
        body { 
            font-family: 'Arial', sans-serif; 
            background: #f5f7fa; 
            margin: 0;
            color: #333;
        }
        .header {
            background: #0b105a;
            color: white;
            padding: 20px;
            text-align: center;
            animation: colorChange 10s infinite;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            animation: fadeIn 0.8s ease-out;
        }
        h2 {
            text-align: center;
            color: #0b105a;
            margin-bottom: 30px;
            font-size: 2.2rem;
        }
        
        /* Statistics Section */
        .stats-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 25px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            width: 220px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(11, 16, 90, 0.1);
            border-top: 4px solid #008B8B;
            transition: all 0.3s ease;
            animation: fadeIn 0.8s ease-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(11, 16, 90, 0.15);
        }
        .stat-value {
            font-size: 2.8rem;
            font-weight: bold;
            color: #0b105a;
            margin: 15px 0;
            transition: all 0.3s ease;
        }
        .stat-card:hover .stat-value {
            animation: pulse 1s;
        }
        .stat-label {
            color: #666;
            font-size: 1rem;
            letter-spacing: 0.5px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-container {
                flex-direction: column;
                align-items: center;
            }
            .stat-card {
                width: 80%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="header">
    <h1>System Dashboard</h1>
</div>

<div class="container">
    <h2>System Overview</h2>
    
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($total_students) ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($total_admins) ?></div>
            <div class="stat-label">Administrators</div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

</body>
</html>