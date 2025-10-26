<footer class="footer">
    <style>
        .footer {
            background-color: #004d4d; 
            color: white;
            padding: 30px 20px;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }

        .footer-title {
            text-align: center;
            font-size: clamp(20px, 4vw, 26px);
            font-weight: bold;
            margin-bottom: 25px;
            color: #00ffff;
            line-height: 1.3;
        }

        .footer-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            max-width: 1200px;
            margin: 0 auto;
            gap: 20px;
        }

        .footer-left, .footer-center, .footer-right {
            flex: 1 1 300px;
            min-width: 250px;
            padding: 0 10px;
        }

        .footer-left p, .footer-center p {
            font-size: clamp(14px, 2vw, 15px);
            margin: 8px 0;
            line-height: 1.5;
        }

        .footer-center {
            text-align: center;
        }

        .footer-center a {
            color: #00ffff;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .footer-center a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        .footer-right {
            text-align: center;
        }

        .footer-right h4 {
            margin-bottom: 15px;
            font-size: clamp(15px, 2vw, 17px);
        }

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .social-icons a {
            display: inline-block;
            transition: transform 0.3s;
        }

        .social-icons a:hover {
            transform: scale(1.1);
        }
        .social-icons img {
        width: 40px;
        height: 40px;
        object-fit: contain;
        background-color: transparent !important;
        opacity: 1 !important;
        filter: none !important;
        z-index: 999;
        position: relative;
        }

        @media (max-width: 768px) {
            .footer {
                padding: 25px 15px;
            }
            
            .footer-title {
                margin-bottom: 20px;
            }
            
            .footer-container {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .footer-left, .footer-center, .footer-right {
                text-align: center;
                padding: 0;
                flex: 1 1 auto;
            }
            
            .social-icons {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .footer {
                padding: 20px 10px;
            }
            
            .footer-title {
                margin-bottom: 15px;
            }
            
            .social-icons img {
                width: 35px;
                height: 35px;
            }
        }
        
    </style>

    <div class="footer-title">
        Debre Berhan University Online Clearance System
    </div>

    <div class="footer-container">
        <div class="footer-left">
            <p>© <?php echo date("Y"); ?> All Rights Reserved</p>
        </div>

        <div class="footer-center">
            <p><strong>Developed by:</strong> IT Department</p>
            <p><strong>Phone:</strong> +251 939 013 630<br>+251 948 813 478</p>
            <p><strong>Email:</strong> <a href="mailto:tomasderese49@gmail.com">tomasderese49@gmail.com</a></p>
        </div>

        <div class="footer-right">
            <h4>Follow Us</h4>
            <div class="social-icons">
                <a href="#" aria-label="Facebook"><img src="/clearance-management/images/facebook.png" alt="Facebook" loading="lazy"></a>
           <a href="https://t.me/dbu10178641" aria-label="Telegram" target="_blank"><img src="/clearance-management/images/telegram.jpg" alt="Telegram Icon" loading="lazy"></a>
                <a href="#" aria-label="Twitter"><img src="/clearance-management/images/twitter.png" alt="Twitter" loading="lazy"></a>

            </div>
        </div>
    </div>
</footer>