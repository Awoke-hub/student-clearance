<?php include 'includes/menu.php'; ?>
<?php include 'includes/db.php'; ?>

<style>
    .main-content {
        margin-left: 300px; /* Moved closer to sidebar */
        margin-top: 80px;
        padding: 20px;
        min-height: calc(100vh - 80px);
        background: var(--content-bg);
        color: var(--content-text);
    }

    .contact-container {
        max-width: 1000px;
        margin: 0; /* Removed auto margin to align left */
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .contact-container h2 {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 10px;
        font-size: 24px;
        padding-bottom: 10px;
        border-bottom: 2px solid #bdc3c7;
    }

    .contact-container > p {
        text-align: center;
        font-size: 16px;
        color: var(--content-text);
        margin-bottom: 20px;
    }

    .contact-box {
        margin-top: 30px;
    }

    .contact-box form {
        display: flex;
        flex-direction: column;
        gap: 15px;
        max-width: 600px;
        margin: 0 auto;
    }

    .contact-box input,
    .contact-box textarea {
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 15px;
        resize: vertical;
        width: 100%;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    .contact-box input:focus,
    .contact-box textarea:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.2);
    }

    .contact-box textarea {
        min-height: 120px;
    }

    .contact-box button {
        padding: 14px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
    }

    .contact-box button:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
    }

    .contact-info {
        margin-top: 40px;
        background-color: #f8f9fa;
        padding: 25px;
        border-radius: 6px;
        border-left: 4px solid var(--primary-color);
    }

    .contact-info h3 {
        color: var(--primary-color);
        margin-bottom: 15px;
        text-align: center;
    }

    .contact-info p {
        font-size: 15px;
        color: var(--content-text);
        line-height: 1.6;
        text-align: center;
        margin-bottom: 10px;
    }

    .message {
        text-align: center;
        margin: 20px 0;
        padding: 15px;
        border-radius: 6px;
        font-weight: bold;
        border-left: 4px solid;
    }

    .message-success {
        background-color: #d4edda;
        color: #155724;
        border-left-color: #28a745;
    }

    .message-error {
        background-color: #f8d7da;
        color: #721c24;
        border-left-color: #dc3545;
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
        
        .contact-container {
            margin: 0 auto; /* Re-center on mobile */
            padding: 25px;
        }
        
        .contact-box form {
            width: 100%;
        }
        
        .contact-container h2 {
            font-size: 22px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .contact-container {
            padding: 20px;
            margin: 10px auto; /* Re-center on mobile */
        }
        
        .contact-container h2 {
            font-size: 20px;
        }
        
        .contact-info {
            padding: 20px;
        }
        
        .contact-box input,
        .contact-box textarea {
            padding: 10px;
        }
    }
</style>

<div class="main-content">
    <div class="contact-container">
        <h2>Contact Us</h2>
        <p>If you have any questions regarding the clearance process, feel free to reach out to us.</p>

        <div class="contact-box">
            <form method="POST" action="">
                <input type="text" name="name" placeholder="Your Name" required>
                <input type="email" name="email" placeholder="Your Email" required>
                <textarea name="message" placeholder="Your Message" required></textarea>
                <button type="submit" name="send">Send Message</button>
            </form>
        </div>

        <?php
        if (isset($_POST['send'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $message = trim($_POST['message']);

            $errors = [];

            if (empty($name)) {
                $errors[] = "Name is required.";
            } elseif (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
                $errors[] = "Name can contain only letters and spaces.";
            }

            if (empty($email)) {
                $errors[] = "Email is required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format.";
            }

            if (empty($message)) {
                $errors[] = "Message cannot be empty.";
            }

            if (!empty($errors)) {
                echo "<div class='message message-error'>";
                foreach ($errors as $error) {
                    echo "❌ $error<br>";
                }
                echo "</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message, is_read) VALUES (?, ?, ?, 0)");
                $stmt->bind_param("sss", $name, $email, $message);

                if ($stmt->execute()) {
                    echo "<div class='message message-success'>✅ Thank you, your message has been sent!</div>";
                } else {
                    echo "<div class='message message-error'>❌ Failed to save your message. Please try again.</div>";
                }

                $stmt->close();
            }
        }
        ?>

        <div class="contact-info">
            <h3>Our Contact Information</h3>
            <p><strong>Address:</strong> Debre Berhan University IT Department, Main Campus</p>
            <p><strong>Email:</strong> tomasderese49@gmail.com</p>
            <p><strong>Phone:</strong> +251-939013630</p>
        </div>
    </div>
</div>