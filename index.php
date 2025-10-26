<?php include 'includes/menu.php'; ?>
<style>
    .main-content {
        margin-left: 280px;
        margin-top: 80px;
        padding: 20px;
        min-height: calc(100vh - 80px);
        background: var(--content-bg);
        color: var(--content-text);
    }

    .hero-section {
        text-align: center;
        padding: 40px 15px; 
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .hero-section h1 {
        font-size: 28px; 
        margin-bottom: 15px;
        letter-spacing: 1px;
        line-height: 1.3; 
        color: var(--primary-color);
        border-bottom: 2px solid #bdc3c7;
        padding-bottom: 10px;
    }

    .hero-section p {
        max-width: 700px;
        margin: 15px auto; 
        font-size: 16px; 
        line-height: 1.6;
        opacity: 0.9;
        padding: 0 15px; 
        color: var(--content-text);
    }

    .features-section {
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
        padding: 30px 15px;
        margin-bottom: 40px; 
    }

    .feature-card {
        background: white;
        color: var(--content-text);
        padding: 20px;
        border-radius: 8px;
        width: 100%;
        max-width: 300px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
        transition: 0.3s;
        margin-bottom: 20px;
        border-left: 4px solid var(--primary-color);
    }

    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        border-left-color: var(--hover-color);
    }

    .feature-card h3 {
        color: var(--primary-color);
        font-size: 18px;
        margin-bottom: 10px;
    }

    .feature-card p {
        font-size: 14px;
        line-height: 1.6;
        color: var(--content-text);
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

        .hero-section {
            padding: 30px 15px;
        }
        
        .hero-section h1 {
            font-size: 24px;
        }
        
        .features-section {
            gap: 15px;
            padding: 20px 10px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .hero-section h1 {
            font-size: 22px;
        }
        
        .hero-section p {
            font-size: 15px;
        }
        
        .features-section {
            padding: 15px 5px;
        }
        
        .feature-card {
            padding: 15px;
        }
    }
</style>

<div class="main-content">
    <div class="hero-section">
        <h1>Welcome to Debre Berhan University Online Student Clearance System</h1>
        <p>
            The Online Student Clearance System streamlines the entire clearance process for students and administrative staff, saving time and paperwork.  
            Submit clearance requests, track application status, and complete all steps online  
            for a faster and more efficient experience.
        </p>
    </div>
    
    <div class="features-section">
        <div class="feature-card">
            <h3>📌 Easy Requests</h3>
            <p>Students can easily request clearance from different departments online.</p>
        </div>
        <div class="feature-card">
            <h3>📊 Track Status</h3>
            <p>Monitor your clearance progress online and receive instant updates.</p>
        </div>
        <div class="feature-card">
            <h3>✅ Fast Processing</h3>
            <p>Reduce paperwork and delays by processing everything digitally.</p>
        </div>
    </div>
</div>