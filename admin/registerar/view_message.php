<?php
// Enable all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
session_start();
include '../../includes/db.php';

// Only allow registrar access
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'academic_admin') {
    header("Location: ../login.php");
    exit();
}

// Mark messages as read when viewed
if (!isset($_GET['no_mark_read'])) {
    $conn->query("UPDATE contact_messages SET is_read = 1 WHERE is_read = 0");
}

// Get all messages
$messages = $conn->query("SELECT id, name, email, message, submitted_at, is_read FROM contact_messages ORDER BY submitted_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Messages - Registrar Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #7209b7;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --text-color: #2b2d42;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            background-color: #f0f2f5;
            color: var(--text-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: var(--dark-color);
            font-size: 2rem;
            position: relative;
        }
        
        .header h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }
        
        .back-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
        }
        
        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .messages-container {
            background: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .message-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .message-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: left;
        }
        
        .message-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .message-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .message-table tr:hover {
            background-color: #f1f1f1;
        }
        
        .message-content {
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .no-messages {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .unread {
            font-weight: bold;
            background-color: #e6f7ff !important;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .message-table {
                display: block;
                overflow-x: auto;
            }
            
            .message-content {
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-envelope"></i> Contact Messages</h1>
            <a href="registerar_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="messages-container">
            <?php if ($messages->num_rows > 0): ?>
                <table class="message-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Message</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($message = $messages->fetch_assoc()): ?>
                            <tr class="<?= ($message['is_read'] ?? 0) ? '' : 'unread' ?>">
                                <td><?= htmlspecialchars($message['id']) ?></td>
                                <td><?= htmlspecialchars($message['name']) ?></td>
                                <td><?= htmlspecialchars($message['email']) ?></td>
                                <td class="message-content" title="<?= htmlspecialchars($message['message']) ?>">
                                    <?= htmlspecialchars($message['message']) ?>
                                </td>
                                <td><?= date('M j, Y g:i A', strtotime($message['submitted_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-messages">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <h3>No messages found</h3>
                    <p>No contact messages have been submitted yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>