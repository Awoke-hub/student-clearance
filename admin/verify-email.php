<?php
// verify-email.php - AJAX endpoint for email verification
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valid' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['valid' => false, 'message' => 'Email is required']);
    exit;
}

// Basic format validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['valid' => false, 'message' => 'Invalid email format']);
    exit;
}

// Check if email already exists in database
$stmt = $conn->prepare("SELECT COUNT(*) FROM student WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count > 0) {
    echo json_encode(['valid' => false, 'message' => 'Email is already registered']);
    exit;
}

// Check disposable email domains
$disposableDomains = [
    'tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
    'throwawaymail.com', 'fakeinbox.com', 'temp-mail.org', 'yopmail.com',
    'getairmail.com', 'maildrop.cc', 'tempail.com', 'trashmail.com'
];

$domain = explode('@', $email)[1];
if (in_array(strtolower($domain), $disposableDomains)) {
    echo json_encode(['valid' => false, 'message' => 'Disposable email addresses are not allowed']);
    exit;
}

// Check DNS records
if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
    echo json_encode(['valid' => false, 'message' => 'Email domain does not exist']);
    exit;
}

// For demo purposes, we'll return valid for common domains
// In production, you would implement SMTP verification here
$commonDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com'];

if (in_array(strtolower($domain), $commonDomains)) {
    echo json_encode(['valid' => true, 'message' => 'Email verification passed']);
} else {
    echo json_encode(['valid' => true, 'message' => 'Email appears valid']); 
    // For non-common domains, you might want to implement SMTP verification
}

exit;
?>