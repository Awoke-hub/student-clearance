<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<style>
    /* Footer styles */
    .footer {
        background-color: #0b105aff;
        color: white;
        padding: 20px 40px;
        text-align: center;
        width: 100%;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: fixed;
        bottom: 0;
        left: 0;
        z-index: 100;
        height: 60px; /* Fixed height */
        box-sizing: border-box;
    }
    
    .footer-content {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .footer p {
        margin: 0;
        font-size: 14px;
        color: rgba(255,255,255,0.8);
    }
    .current-year {
        font-weight: bold;
        color: white;
    }
</style>

<div class="footer">
    <div class="footer-content">
        <p>&copy; <span class="current-year"><?= date('Y') ?></span> Debre Berhan University Online student clearance System</p>
    </div>
</div>