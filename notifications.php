<?php
include 'includes/db.php';
include 'includes/menu.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT student_id, name, last_name, department, year FROM student WHERE student_id = ?");
$stmt->bind_param("s", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$student = $result->fetch_assoc();
$actual_student_id = $student['student_id'];

$update_stmt = $conn->prepare("UPDATE final_clearance SET is_read = 1 WHERE student_id = ? AND is_read = 0");
$update_stmt->bind_param("s", $actual_student_id);
$update_stmt->execute();

$stmt = $conn->prepare("SELECT * FROM final_clearance WHERE student_id = ? ORDER BY date_sent DESC");
$stmt->bind_param("s", $actual_student_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if there's any approved clearance to show download button
$show_download_button = false;
$notifications = [];
if ($result->num_rows > 0) {
    while ($notification = $result->fetch_assoc()) {
        $notifications[] = $notification;
        if ($notification['status'] === 'approved') {
            $show_download_button = true;
        }
    }
    // Reset pointer for the display loop
    $result->data_seek(0);
}
?>

<style>
    .main-content {
        margin-left: 250px;
        margin-top: 70px;
        padding: 15px;
        min-height: calc(100vh - 70px);
        background: var(--content-bg);
        color: var(--content-text);
    }

    .notification-content-wrapper {
        max-width: min(750px, 95%);
        margin: 0 auto;
        padding: 15px;
        background: white;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding-top: 30px;
    }

    .page-title {
        color: #2c3e50;
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        position: relative;
        display: inline-block;
        padding-bottom: 10px;
    }

    .page-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 3px;
        background: linear-gradient(135deg, #3498db, #2c3e50);
        border-radius: 2px;
    }

    .download-section {
        text-align: center;
        margin: 30px 0 20px 0;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
    }

    .download-btn {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(52, 152, 219, 0.3);
    }

    .download-btn:hover {
        background: linear-gradient(135deg, #2980b9, #21618c);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(52, 152, 219, 0.4);
    }

    .download-btn:active {
        transform: translateY(0);
    }

    .clearance-card {
        background: white;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 6px;
        border: 1px solid #e0e0e0;
        position: relative;
    }

    .clearance-card.unread {
        border-color: #3498db;
        background: #f8fdff;
    }

    .clearance-card.approved {
        border-color: #28a745;
        background: #f8fff9;
    }

    .clearance-card.rejected {
        border-color: #dc3545;
        background: #fff5f5;
    }

    .status-result {
        font-size: 18px;
        font-weight: bold;
        padding: 12px 0;
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 1px solid #ddd;
    }

    .status-approved { color: #28a745; }
    .status-rejected { color: #dc3545; }

    .student-info {
        margin-bottom: 20px;
        position: relative;
    }

    /* Logo styling for both web and print */
    .university-logo {
        position: absolute;
        top: 0;
        right: 0;
        width: 80px;
        height: 80px;
        overflow: hidden;
    }

    .university-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .info-line {
        display: flex;
        margin-bottom: 8px;
        padding: 6px 0;
        border-bottom: 1px dashed #eee;
    }

    .info-label {
        font-weight: bold;
        width: 120px;
        color: #2c3e50;
        font-size: 14px;
    }

    .info-value {
        flex: 1;
        color: #555;
        font-size: 14px;
        margin-right: 90px;
    }

    .signature-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 25px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
    }

    .registrar-info {
        text-align: left;
    }

    .registrar-name {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 3px;
    }

    .registrar-title {
        color: #666;
        font-size: 12px;
    }

    .signature-circle {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        position: relative;
        overflow: hidden;
        border: 1px solid #2c3e50;
    }

    .signature-image {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 8px;
    }

    .signature-fallback {
        font-family: Arial, sans-serif;
        font-size: 9px;
        color: #2c3e50;
        text-align: center;
        line-height: 1.2;
        font-weight: bold;
        padding: 8px;
    }

    .date-section {
        text-align: center;
        margin-top: 15px;
        color: #777;
        font-style: italic;
        font-size: 13px;
    }

    /* Simplified No Notifications Styling */
    .no-notifications {
        text-align: center;
        padding: 50px 30px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        border: 2px dashed #dee2e6;
        margin: 30px 0;
    }

    .no-notifications-icon {
        font-size: 64px;
        margin-bottom: 20px;
        color: #6c757d;
        animation: bounce 2s ease-in-out infinite;
        display: block;
    }

    .no-notifications h3 {
        color: #495057;
        font-size: 22px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .no-notifications p {
        color: #6c757d;
        font-size: 16px;
        line-height: 1.5;
        margin: 0;
    }

    .new-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #3498db;
        color: white;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: bold;
    }

    /* Animations */
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-10px);
        }
        60% {
            transform: translateY(-5px);
        }
    }

    /* Print styles for download */
    @media print {
        @page {
            margin: 0.5cm;
            size: A4;
        }
        
        body {
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            background: white !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 20px !important;
            background: white !important;
            width: 100% !important;
        }
        
        .notification-content-wrapper {
            box-shadow: none !important;
            border: none !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        
        /* Hide web-only elements */
        .download-btn, .new-badge, .download-section {
            display: none !important;
        }
        
        /* KEEP page-header visible for downloads */
        .page-header {
            display: block !important;
            visibility: visible !important;
            margin-bottom: 25px !important;
            padding-top: 10px !important;
        }
        
        .page-title {
            font-size: 22px !important;
            color: #000 !important;
        }
        
        /* Show all important content for print */
        .clearance-card {
            break-inside: avoid;
            margin-bottom: 25px;
            border: 2px solid #000 !important;
            page-break-inside: avoid;
            padding: 25px !important;
        }
        
        .status-result {
            font-size: 20px !important;
            font-weight: bold !important;
            margin-bottom: 25px !important;
            display: block !important;
            visibility: visible !important;
        }
        
        /* Ensure logo is visible */
        .university-logo {
            display: block !important;
            visibility: visible !important;
        }
        
        .university-logo img {
            visibility: visible !important;
        }
        
        /* Ensure proper spacing for print */
        .info-value {
            margin-right: 90px;
        }

        /* Hide no notifications in print */
        .no-notifications {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            margin-top: 60px;
            padding: 10px;
        }
        
        .clearance-card {
            padding: 15px;
        }
        
        .university-logo {
            position: relative;
            margin: 0 auto 15px auto;
        }
        
        .info-line {
            flex-direction: column;
        }
        
        .info-label {
            width: 100%;
            margin-bottom: 3px;
        }
        
        .info-value {
            margin-right: 0;
        }
        
        .signature-section {
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        
        .registrar-info {
            text-align: center;
        }
        
        .signature-circle {
            width: 80px;
            height: 80px;
        }

        .no-notifications {
            padding: 40px 20px;
            margin: 20px 0;
        }

        .no-notifications-icon {
            font-size: 48px;
        }

        .no-notifications h3 {
            font-size: 20px;
        }

        .no-notifications p {
            font-size: 14px;
        }
    }
</style>

<div class="main-content">
    <div class="notification-content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Clearance Status Notifications</h1>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($notification = $result->fetch_assoc()): ?>
                <div class="clearance-card <?php echo htmlspecialchars($notification['status']); ?> <?php echo ($notification['is_read'] == 0) ? 'unread' : ''; ?>">
                    
                    <?php if ($notification['is_read'] == 0): ?>
                        <div class="new-badge">NEW</div>
                    <?php endif; ?>

                    <!-- This status text WILL appear in downloads -->
                    <div class="status-result status-<?php echo htmlspecialchars($notification['status']); ?>">
                        Final clearance <?php echo htmlspecialchars($notification['status']); ?> by Registrar
                    </div>

                    <div class="student-info">
                        <!-- University Logo - No border, just the actual logo -->
                        <div class="university-logo">
                            <?php
                            // Replace with your actual logo path
                            $logoPath = 'images/dbu-logo.png';
                            if (file_exists($logoPath) && filesize($logoPath) > 0): 
                            ?>
                                <img src="<?php echo $logoPath; ?>" alt="Debre Berhan University Logo">
                            <?php else: ?>
                                <!-- If logo doesn't exist, show nothing -->
                            <?php endif; ?>
                        </div>
                        
                        <div class="info-line">
                            <div class="info-label">Student ID:</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['student_id']); ?></div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Name:</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['name'] . ' ' . $student['last_name']); ?></div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Department:</div>
                            <div class="info-value"><?php echo htmlspecialchars($notification['department']); ?></div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Academic Year:</div>
                            <div class="info-value"><?php echo htmlspecialchars($notification['year']); ?></div>
                        </div>
                    </div>

                    <div class="signature-section">
                        <div class="registrar-info">
                            <div class="registrar-name">Registrar Office</div>
                            <div class="registrar-title">Debre Berhan University</div>
                        </div>
                        <div class="signature-circle">
                            <?php
                            $signaturePath = 'uploads/signature.png';
                            if (file_exists($signaturePath)): 
                            ?>
                                <img src="<?php echo $signaturePath; ?>" alt="Registrar Signature" class="signature-image">
                            <?php else: ?>
                                <div class="signature-fallback">
                                    DEBRE BERHAN<br>
                                    UNIVERSITY<br>
                                    REGISTRAR<br>
                                    OFFICE
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="date-section">
                        Date: <?php echo date("F j, Y", strtotime($notification['date_sent'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <!-- Simplified No Notifications Section -->
            <div class="no-notifications">
                <div class="no-notifications-icon">📋</div>
                <h3>You have no clearance notifications yet</h3>
                <p>Wait until all departments approve you</p>
            </div>
        <?php endif; ?>

        <!-- Download button at the bottom - Only show if there's an approved clearance -->
        <?php if ($show_download_button): ?>
        <div class="download-section">
            <button class="download-btn" onclick="downloadClearance()">
                <span>📥</span> Download PDF
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function downloadClearance() {
    // Use browser's print functionality
    window.print();
}

// Add keyboard shortcut (Ctrl + P or Cmd + P)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        downloadClearance();
    }
});
</script>