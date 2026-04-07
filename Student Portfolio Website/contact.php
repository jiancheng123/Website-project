<?php
session_start();

// Database connection configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "assignment";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['contact_error'] = "Database connection failed: " . $e->getMessage();
    header("Location: contact.php");
    exit;
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone_number'] ?? '');
    $message = trim($_POST['message']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Input validation
if (empty($name)) {
    $_SESSION['contact_error'] = 'Name is required!';
    header("Location: contact.php");
    exit;
}

if (empty($email)) {
    $_SESSION['contact_error'] = 'Email is required!';
    header("Location: contact.php");
    exit;
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['contact_error'] = 'Invalid email format! Use something like name@hotmail.com';
    header("Location: contact.php");
    exit;
}

if (empty($phone)) {
    $_SESSION['contact_error'] = 'Phone number is required!';
    header("Location: contact.php");
    exit;
} elseif (!preg_match('/^\+60\d{8,9}$/', $phone)) {
    $_SESSION['contact_error'] = 'Invalid phone number format! Use +60123456789';
    header("Location: contact.php");
    exit;
}

if (empty($message)) {
    $_SESSION['contact_error'] = 'Message is required!';
    header("Location: contact.php");
    exit;
}

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['contact_error'] = 'Invalid email format!';
        header("Location: contact.php");
        exit;
    }

    if (!preg_match('/^\+60[0-9]{8,9}$/', $phone)) {
        $_SESSION['contact_error'] = 'Phone number must follow format +60123456789!';
        header("Location: contact.php");
        exit;
    }

    if (strlen($message) > 500) {
        $_SESSION['contact_error'] = 'Message must be 500 characters or less!';
        header("Location: contact.php");
        exit;
    }

    if (!preg_match('/^[a-zA-Z\s]{1,50}$/', $name)) {
        $_SESSION['contact_error'] = 'Name must be 1-50 characters and contain only letters and spaces!';
        header("Location: contact.php");
        exit;
    }

    // Store message in database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO contact_messages (user_id, name, email, phone_number, message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $name, $email, $phone, $message]);
        $_SESSION['contact_success'] = 'Message sent successfully!';
    } catch (PDOException $e) {
        $_SESSION['contact_error'] = 'Failed to send message. Please try again later.';
    }

    header("Location: contact.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <div class="Navigation-bar">
            <ul>
                <li><a href="home.php">Home</a></li>
                <li><a href="Aboutme.php">About Me</a></li>
                <li><a href="Skills.php">Skills & Projects</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li>
                        <form action="logout.php" method="post" class="logout-form">
                            <button type="submit" class="logout-button">Logout</button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Contact Form -->
    <div class="container">
        <form action="contact.php" method="post" class="form-box active" id="contactBox">
            <h2>Contact Us</h2>
            <?php if (isset($_SESSION['contact_error'])): ?>
                <p class="error-message"><?php echo htmlspecialchars($_SESSION['contact_error']); unset($_SESSION['contact_error']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['contact_success'])): ?>
                <p style="color: green;"><?php echo htmlspecialchars($_SESSION['contact_success']); unset($_SESSION['contact_success']); ?></p>
            <?php endif; ?>
            <input type="text" id="name" name="name" placeholder="Enter your name" required />
            <input type="email" id="email" name="email" placeholder="Enter your email" required />
            <input type="text" id="phone_number" name="phone_number" placeholder="Enter your phone number" required />
            <textarea id="message" name="message" placeholder="Enter your message (max 500 characters)" maxlength="500" rows="5" style="width: 100%;" required></textarea>
            <button type="submit" name="send_message">Send Message</button>
        </form>
    </div>
    <script src="script.js"></script>
</body>
</html>