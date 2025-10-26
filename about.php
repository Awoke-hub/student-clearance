<?php 
include 'includes/menu.php'; 
?>

<style>
    .main-content {
        margin-left: 300px; /* Moved closer to sidebar */
        margin-top: 80px;
        padding: 20px;
        min-height: calc(100vh - 80px);
        background: var(--content-bg);
        color: var(--content-text);
    }

    .about-container {
        max-width: 900px;
        margin: 0; /* Removed auto margin to align left */
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        line-height: 1.6;
    }

    .about-container h2 {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 20px;
        font-size: 24px;
        padding-bottom: 10px;
        border-bottom: 2px solid #bdc3c7;
    }

    .about-container p {
        font-size: 16px;
        margin-bottom: 15px;
        color: var(--content-text);
    }

    .about-highlight {
        background: #f8f9fa;
        padding: 20px;
        border-left: 4px solid var(--primary-color);
        border-radius: 6px;
        margin-top: 20px;
    }

    .about-container ul {
        margin: 15px 0 0 20px;
    }

    .about-container ul li {
        margin-bottom: 10px;
        color: var(--content-text);
    }

    .about-container strong {
        color: var(--primary-color);
    }

    /* Responsive adjustments */
    @media (max-width: 1100px) {
        .main-content {
            margin-left: 270px;
            width: calc(100vw - 270px);
        }
    }

    @media (max-width: 900px) {
        .main-content {
            margin-left: 240px;
            width: calc(100vw - 240px);
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            margin-top: 70px;
            padding: 15px;
        }
        
        .about-container {
            margin: 0 auto; /* Re-center on mobile */
            padding: 25px;
        }
        
        .about-container h2 {
            font-size: 22px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .about-container {
            padding: 20px;
            margin: 10px auto; /* Re-center on mobile */
        }
        
        .about-container h2 {
            font-size: 20px;
        }
        
        .about-container p {
            font-size: 15px;
        }
        
        .about-highlight {
            padding: 15px;
        }
    }
</style>

<div class="main-content">
    <div class="about-container">
        <h2>About Debre Berhan University Online Student Clearance System</h2>
        <p>
            The <strong>Debre Berhan University Clearance Management System</strong> is designed to streamline and digitize the student clearance process.
            Instead of physically visiting multiple offices, students can now request clearance online and track their approval status in real time.
        </p>
        <p>
            This system ensures <strong>faster processing</strong>, <strong>greater transparency</strong>, and <strong>accurate record-keeping</strong>, benefiting both students and staff.
        </p>

        <div class="about-highlight">
            <h3 style="color: var(--primary-color);">Key Features:</h3>
            <ul>
                <li>✔ Online clearance requests (Library, Cafeteria, Dormitory, etc.)</li>
                <li>✔ Real-time status tracking of each clearance request</li>
                <li>✔ Secure student authentication & password reset</li>
                <li>✔ Ability for staff to approve or reject requests with reasons</li>
            </ul>
        </div>

        <p style="margin-top:20px;">
            <strong>Our Mission:</strong> To simplify the clearance process, save time, and make the experience more convenient for every student.
        </p>
    </div>
</div>