<?php
session_start();
require_once 'connect.php';

// Database connection
function connectDB() {
    $conn = new mysqli('localhost', 'root', '', 'assignment');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$conn = connectDB();
$error = '';
$message = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $new_password = $_POST['new_password'] ?? '';

    $sql = "SELECT username FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $username = $user['username'];
        
        if (empty($new_password)) {
            $message = "Your username is: {$username}. Please enter a new password to reset.";
        } elseif (strlen($new_password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*\d)[A-Za-z\d]{8,}$/', $new_password)) {
            $error = 'Password must be at least 8 characters long and include at least one lowercase letter and one number.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $hashed_password, $username);
            if ($stmt->execute()) {
                $message = "Username: {$username}. Password reset successfully. Please <a href='login.php'>login</a>.";
            } else {
                $error = 'Error resetting password: ' . $conn->error;
            }
        }
    } else {
        $error = 'Username not found.';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Username or Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-box {
            display: block;
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .error-message { color: red; font-size: 14px; }
        .success-message { color: green; font-size: 14px; }
        .form-box input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
        }
        .form-box button {
            margin-top: 10px;
            padding: 8px;
            width: 100%;
        }
        .form-box p {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <form action="reset_password.php" method="post" class="form-box" id="resetBox">
            <h2>Reset Username or Password</h2>
            <?php if ($error): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php elseif ($message): ?>
                <p class="success-message"><?php echo $message; ?></p>
            <?php endif; ?>
            <input type="text" id="username" name="username" placeholder="Enter your username" required />
            <input type="password" id="new_password" name="new_password" placeholder="Enter new password (optional for username recovery)" />
            <br>
            <button type="submit" name="reset">Reset</button>
            <br><br>
            <p>Remember your account details? <a href="login.php">Login</a></p>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>