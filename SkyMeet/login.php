<?php
session_start();

/* DATABASE CONNECTION  */
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "fypdb";

$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/*  MESSAGE  */
$message = "";
$messageType = "";

/*  FIELD ERROR MESSAGES */
$emailError = "";
$usernameError = "";
$passwordError = "";
$confirmPasswordError = "";
$roleError = "";
$generalError = "";
$generalSuccess = "";

/* FORGOT PASSWORD VARIABLES */
$forgotError = "";
$forgotSuccess = "";
$showForgotForm = false;

/* FORM STATE VARIABLES */
$showRegisterForm = false;
$showLoginForm = true;
$formUsername = "";
$formEmail = "";
$formRole = "";

/* CHECK REMEMBER ME COOKIE */
$rememberedUsername = "";
$rememberedToken = "";

if (isset($_COOKIE['skymeet_remember'])) {
    $cookieValue = $_COOKIE['skymeet_remember'];
    $parts = explode(':', $cookieValue);
    
    if (count($parts) === 3) {
        list($username, $token, $mac) = $parts;
        
        // Verify the MAC
        $secret_key = 'your_secret_key_here_' . date('Y-m-d');
        $expectedMAC = hash_hmac('sha256', $username . ':' . $token, $secret_key);
        
        if (hash_equals($expectedMAC, $mac)) {
            $rememberedUsername = $username;
            $rememberedToken = $token;
        }
    }
}

/* LOGIN */
if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Use prepared statement to prevent SQL injection
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Password verification - handle multiple hash types
        $password_valid = false;
        
        // Try bcrypt verification first
        if (password_verify($password, $user['password'])) {
            $password_valid = true;
        }
        // Check for MD5 (legacy)
        elseif (strlen($user['password']) === 32 && ctype_xdigit($user['password']) && md5($password) === $user['password']) {
            $password_valid = true;
            // Upgrade to bcrypt on successful login
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_hash, $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        // Check for SHA1 (legacy)
        elseif (strlen($user['password']) === 40 && ctype_xdigit($user['password']) && sha1($password) === $user['password']) {
            $password_valid = true;
            // Upgrade to bcrypt on successful login
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_hash, $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        // Check for plain text (legacy - very insecure)
        elseif ($password === $user['password']) {
            $password_valid = true;
            // Upgrade to bcrypt on successful login
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_hash, $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        if ($password_valid) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();

            if ($remember) {
                // Create remember token
                $token = bin2hex(random_bytes(32));
                $secret_key = 'your_secret_key_here_' . date('Y-m-d');
                $mac = hash_hmac('sha256', $username . ':' . $token, $secret_key);
                $cookieValue = $username . ':' . $token . ':' . $mac;
                
                // Set cookie for 30 days
                setcookie("skymeet_remember", $cookieValue, time() + (86400 * 30), "/", "", false, true);
            } else {
                // Clear remember cookie if exists
                if (isset($_COOKIE['skymeet_remember'])) {
                    setcookie("skymeet_remember", "", time() - 3600, "/");
                }
            }

            // Redirect based on role
            if ($user['role'] === 'Admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $generalError = "Invalid password";
        }
    } else {
        $generalError = "User not found";
    }
}

/* FORGOT PASSWORD */
if (isset($_POST['show_forgot'])) {
    $showForgotForm = true;
    $showLoginForm = false;
    $showRegisterForm = false;
}

if (isset($_POST['reset_password'])) {
    $showForgotForm = true;
    $showLoginForm = false;
    $showRegisterForm = false;
    
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    
    $hasError = false;
    
    // Validate inputs - FIX: Trim passwords to ignore spaces
    if (empty($username)) {
        $forgotError = "Username is required";
        $hasError = true;
    } elseif (empty($new_password)) {
        $forgotError = "Password is required";
        $hasError = true;
    } elseif (strlen(trim($new_password)) < 6) { // FIX: Use trim() to ignore spaces
        $forgotError = "Password must be at least 6 characters (spaces not counted)";
        $hasError = true;
    } elseif (trim($new_password) !== trim($confirm_new_password)) { // FIX: Use trim() to ignore spaces
        $forgotError = "Passwords do not match";
        $hasError = true;
    }
    
    if (!$hasError) {
        // Check if username exists
        $check_sql = "SELECT * FROM users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 1) {
            // Username exists, update password
            // FIX: Store the trimmed password
            $trimmed_password = trim($new_password);
            $hashedPassword = password_hash($trimmed_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE username = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $hashedPassword, $username);
            
            if ($update_stmt->execute()) {
                $forgotSuccess = "Password reset successful! Redirecting to login...";
                
                // Clear remember me cookie
                if (isset($_COOKIE['skymeet_remember'])) {
                    setcookie("skymeet_remember", "", time() - 3600, "/");
                }
                
                // Auto redirect back to login after 2 seconds
                echo "<script>
                    setTimeout(function() {
                        document.getElementById('forgotForm').style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(function() {
                            document.getElementById('forgotForm').style.display = 'none';
                            document.getElementById('loginForm').style.display = 'block';
                            document.getElementById('loginForm').style.animation = 'floatIn 0.4s ease forwards';
                            document.querySelector('.form-title h2').innerHTML = '<i class=\\'fas fa-sign-in-alt dashboard-icon\\'></i> Welcome Back';
                            document.querySelector('.form-title p').textContent = 'Please login to your account';
                            document.getElementById('toggleText').textContent = 'Create an account';
                        }, 400);
                    }, 2000);
                </script>";
                
                $showForgotForm = false;
                $showLoginForm = true;
            } else {
                $forgotError = "Failed to reset password. Please try again.";
            }
            $update_stmt->close();
        } else {
            $forgotError = "Username not found in system";
        }
        $check_stmt->close();
    }
}

/* REGISTER */
if (isset($_POST['register'])) {
    // Set form state to show register form
    $showRegisterForm = true;
    $showLoginForm = false;
    $showForgotForm = false;
    
    // Store form values
    $formUsername = mysqli_real_escape_string($conn, $_POST['username']);
    $formEmail = mysqli_real_escape_string($conn, $_POST['email']);
    $formRole = $_POST['role'];
    
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    $hasError = false;

    // Validation - FIX: Trim passwords to ignore spaces
    if (empty($formUsername)) {
        $usernameError = "Username is required";
        $hasError = true;
    }
    
    if (empty($formEmail)) {
        $emailError = "Email is required";
        $hasError = true;
    } else {
        // Check email domain
        $allowed_domains = ['@hotmail.com', '@gmail.com', '@segi4u.my'];
        $email_domain_valid = false;
        
        foreach ($allowed_domains as $domain) {
            if (strpos($formEmail, $domain) !== false) {
                $email_domain_valid = true;
                break;
            }
        }
        
        if (!$email_domain_valid) {
            $emailError = "Email must be from @hotmail.com, @gmail.com, or @segi4u.my";
            $hasError = true;
        }
    }
    
    if (empty($password)) {
        $passwordError = "Password is required";
        $hasError = true;
    } elseif (strlen(trim($password)) < 6) { 
        $passwordError = "Password must be at least 6 characters (spaces not counted)";
        $hasError = true;
    }
    
    if (trim($password) !== trim($confirmPassword)) { 
        $confirmPasswordError = "Passwords do not match";
        $hasError = true;
    }
    
    if (empty($formRole)) {
        $roleError = "Please select an account type";
        $hasError = true;
    }

    if (!$hasError) {
        // Check existing username/email
        $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $formUsername, $formEmail);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Check which field already exists
            while ($row = $check_result->fetch_assoc()) {
                if ($row['username'] === $formUsername) {
                    $usernameError = "Username already exists";
                }
                if ($row['email'] === $formEmail) {
                    $emailError = "Email already exists";
                }
            }
        } else {
            // Use bcrypt for new registrations - FIX: Store the trimmed password
            $trimmed_password = trim($password);
            $hashedPassword = password_hash($trimmed_password, PASSWORD_DEFAULT);
            
            $insert_sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssss", $formUsername, $formEmail, $hashedPassword, $formRole);
            
            if ($insert_stmt->execute()) {
                $generalSuccess = "Registration successful! Please login.";
                
                // Reset form state to show login
                $showRegisterForm = false;
                $showLoginForm = true;
                
                // Clear form values
                $formUsername = "";
                $formEmail = "";
                $formRole = "";
            } else {
                $generalError = "Registration failed. Please try again.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Set default form states if not set by any form submission
if (!isset($_POST['login']) && !isset($_POST['register']) && !isset($_POST['reset_password']) && !isset($_POST['show_forgot'])) {
    $showLoginForm = true;
    $showRegisterForm = false;
    $showForgotForm = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyMeet | Login & Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ALL YOUR EXISTING CSS STYLES REMAIN EXACTLY THE SAME */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, 
                #667eea 0%, 
                #764ba2 25%, 
                #6B46C1 50%, 
                #553C9A 75%, 
                #2D3748 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background particles */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255,255,255,0.05) 0%, transparent 50%);
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(-10px, 10px) rotate(1deg); }
            50% { transform: translate(10px, -10px) rotate(-1deg); }
            75% { transform: translate(-5px, -5px) rotate(0.5deg); }
        }

        .container {
            width: 100%;
            max-width: 450px;
            z-index: 2;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .auth-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        /* Card glow effect */
        .auth-card::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, 
                #667eea, 
                #764ba2, 
                #6B46C1, 
                #553C9A);
            border-radius: 26px;
            z-index: -1;
            opacity: 0.7;
            filter: blur(20px);
            animation: glow 3s ease-in-out infinite alternate;
        }

        @keyframes glow {
            0% { opacity: 0.5; }
            100% { opacity: 0.8; }
        }

        .auth-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
        }

        .auth-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to bottom right,
                transparent 30%,
                rgba(255, 255, 255, 0.1) 50%,
                transparent 70%
            );
            transform: rotate(45deg);
            animation: shine 4s infinite linear;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .logo {
            font-size: 32px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .logo i {
            font-size: 36px;
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .logo:hover i {
            transform: scale(1.1) rotate(10deg);
            background: rgba(255, 255, 255, 0.3);
        }

        .auth-header p {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 500;
            margin-top: 5px;
        }

        .auth-content {
            padding: 40px;
            background: #ffffff;
        }

        .form-container {
            position: relative;
        }

        .form-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-title h2 {
            color: #1a202c;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-title p {
            color: #718096;
            font-size: 15px;
            font-weight: 500;
        }

        .input-group {
            margin-bottom: 25px;
            position: relative;
        }

        .input-group label {
            display: block;
            margin-bottom: 10px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 46px;
            color: #667eea;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .input-field {
            width: 100%;
            padding: 16px 20px 16px 55px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            outline: none;
            color: #2d3748;
            font-weight: 500;
            position: relative;
        }

        .input-field.error {
            border-color: #f44336;
            box-shadow: 0 0 0 4px rgba(244, 67, 54, 0.15);
        }

        .input-field.success {
            border-color: #4caf50;
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.15);
        }

        .input-field::placeholder {
            color: #a0aec0;
            font-weight: 400;
        }

        .input-field:focus {
            border-color: #667eea;
            background: #ffffff;
            box-shadow: 
                0 0 0 4px rgba(102, 126, 234, 0.15),
                0 4px 20px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .input-field:focus + .input-icon {
            color: #764ba2;
            transform: scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 46px;
            background: none;
            border: none;
            color: #cbd5e0;
            cursor: pointer;
            padding: 0;
            transition: all 0.3s ease;
            z-index: 2;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: #667eea;
            transform: scale(1.1);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .checkbox-container input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }

        .checkbox-container .checkmark {
            width: 22px;
            height: 22px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            margin-right: 12px;
            position: relative;
            transition: all 0.3s ease;
            background: #ffffff;
            flex-shrink: 0;
        }

        .checkbox-container:hover input ~ .checkmark {
            border-color: #667eea;
        }

        .checkbox-container input:checked ~ .checkmark {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        .checkbox-container .checkmark::after {
            content: '';
            position: absolute;
            display: none;
            left: 7px;
            top: 3px;
            width: 6px;
            height: 12px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .checkbox-container input:checked ~ .checkmark::after {
            display: block;
        }

        .checkbox-container label {
            color: #4a5568;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            user-select: none;
        }

        .btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.2), 
                transparent);
            transition: left 0.6s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 10px 30px rgba(102, 126, 234, 0.4),
                0 4px 15px rgba(118, 75, 162, 0.3);
        }

        .btn:active {
            transform: translateY(-1px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }

        .btn i {
            font-size: 18px;
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .form-toggle {
            text-align: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #edf2f7;
        }

        .toggle-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            padding: 10px 20px;
            border-radius: 10px;
            background: rgba(102, 126, 234, 0.1);
        }

        .toggle-link:hover {
            color: #764ba2;
            background: rgba(118, 75, 162, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .toggle-link i {
            transition: transform 0.3s ease;
        }

        .toggle-link:hover i {
            transform: translateX(5px);
        }

        /* Field Error Message Styles */
        .field-error {
            color: #f44336;
            font-size: 12px;
            margin-top: 8px;
            margin-left: 10px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            animation: slideInError 0.3s ease;
            font-weight: 500;
            width: 100%;
            position: relative;
            clear: both;
        }

        .field-error i {
            font-size: 12px;
            color: #f44336;
        }

        @keyframes slideInError {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* General Message Styles */
        .general-message {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            animation: slideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            background: #ffffff;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .general-message::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            animation: shimmer 2s infinite linear;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .general-message.error {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        .general-message.success {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        /* Forgot Password Form Styles */
        .forgot-form {
            display: none;
            animation: floatIn 0.4s ease forwards;
        }

        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .forgot-header h2 {
            color: #1a202c;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .forgot-header p {
            color: #718096;
            font-size: 15px;
            font-weight: 500;
        }

        .forgot-message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .forgot-message.error {
            background: #ffebee;
            color: #c62828;
        }

        .forgot-message.success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .forgot-form .terms {
            margin-top: 25px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #718096;
            text-align: center;
            line-height: 1.6;
            background: #f7fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .forgot-form .terms i {
            color: #667eea;
            margin-right: 5px;
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-login a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(102, 126, 234, 0.1);
        }

        .back-to-login a:hover {
            color: #764ba2;
            background: rgba(118, 75, 162, 0.15);
            transform: translateX(-5px);
        }

        .password-strength {
            margin-top: 12px;
        }

        .strength-text {
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            font-weight: 600;
        }

        .strength-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .strength-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.4), 
                transparent);
            animation: shimmer 2s infinite;
        }

        .strength-weak { 
            background: linear-gradient(90deg, #ff5252, #ff8a80);
        }
        .strength-fair { 
            background: linear-gradient(90deg, #ffb142, #ffda79);
        }
        .strength-good { 
            background: linear-gradient(90deg, #33d9b2, #7bed9f);
        }
        .strength-strong { 
            background: linear-gradient(90deg, #2ecc71, #55efc4);
        }

        .terms {
            margin-top: 25px;
            font-size: 13px;
            color: #718096;
            text-align: center;
            line-height: 1.6;
            background: #ffffff;
        }

        .terms a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .terms a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: #667eea;
            transition: width 0.3s ease;
        }

        .terms a:hover {
            color: #764ba2;
        }

        .terms a:hover::after {
            width: 100%;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 24px;
            z-index: 20;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(102, 126, 234, 0.1);
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            position: relative;
        }

        .spinner::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border: 3px solid transparent;
            border-top: 3px solid #764ba2;
            border-radius: 50%;
            animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite reverse;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            margin-top: 20px;
            color: #4a5568;
            font-size: 14px;
            font-weight: 600;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Floating animation for form switch */
        @keyframes floatIn {
            0% {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes floatOut {
            0% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
        }

        /* Forgot password link */
        .forgot-password {
            text-align: center;
            margin: 20px 0;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .forgot-password a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* Role selection styling */
        select.input-field {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23667eea' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            padding-right: 50px;
            cursor: pointer;
            background-color: #ffffff;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        /* Dashboard-inspired icons */
        .dashboard-icon {
            font-size: 14px;
            margin-right: 8px;
            color: #667eea;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-right: 15px;
        }

        /* Using dashboard icons for login form */
        .login-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .register-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        /* Email validation hint */
        .email-hint {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
            margin-left: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .email-hint i {
            color: #667eea;
            font-size: 12px;
        }
        
        .email-hint .allowed-domains {
            color: #667eea;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .auth-content {
                padding: 30px 25px;
            }
            
            .auth-header {
                padding: 30px 20px;
            }
            
            .container {
                padding: 10px;
            }
            
            .logo {
                font-size: 28px;
            }
            
            .logo i {
                font-size: 32px;
                padding: 12px;
            }
            
            .form-title h2 {
                font-size: 24px;
            }
            
            .input-field {
                padding: 14px 15px 14px 50px;
                font-size: 15px;
            }
            
            .input-icon {
                left: 15px;
                top: 42px;
            }
            
            .password-toggle {
                right: 15px;
                top: 42px;
            }
            
            .btn {
                padding: 16px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-card" id="authCard">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-comments"></i> 
                    SkyMeet 
                </div>
                <p>Modern Meeting Platform</p>
            </div>

            <div class="auth-content">
                <?php if(!empty($generalError)): ?>
                    <div class="general-message error" id="generalMessage">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $generalError; ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($generalSuccess)): ?>
                    <div class="general-message success" id="generalMessage">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $generalSuccess; ?>
                    </div>
                <?php endif; ?>

                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                    <div class="loading-text">Processing...</div>
                </div>

                <div class="form-container">
                    <!-- Login Form -->
                    <form id="loginForm" method="post" style="display: <?php echo ($showLoginForm) ? 'block' : 'none'; ?>;">
                        <div class="form-title">
                            <h2><i class="fas fa-sign-in-alt dashboard-icon"></i> Welcome Back</h2>
                            <p>Please login to your account</p>
                        </div>

                        <div class="input-group">
                            <label for="loginUsername"><i class="fas fa-user dashboard-icon"></i> USERNAME</label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" 
                                   id="loginUsername" 
                                   name="username" 
                                   class="input-field" 
                                   placeholder="Enter your username" 
                                   required
                                   autocomplete="username"
                                   value="<?php echo htmlspecialchars($rememberedUsername); ?>">
                        </div>

                        <div class="input-group">
                            <label for="loginPassword"><i class="fas fa-lock dashboard-icon"></i> PASSWORD</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" 
                                   id="loginPassword" 
                                   name="password" 
                                   class="input-field" 
                                   placeholder="Enter your password" 
                                   required
                                   autocomplete="current-password">
                            <button type="button" 
                                    class="password-toggle" 
                                    onclick="togglePassword('loginPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                        <div class="checkbox-container" onclick="toggleRememberMe()">
                            <input type="checkbox" 
                                   id="rememberMe" 
                                   name="remember" 
                                   <?php echo !empty($rememberedUsername) ? 'checked' : ''; ?>>
                            <div class="checkmark"></div>
                            <label for="rememberMe">
                                <i class="far fa-clock dashboard-icon"></i> Remember me for 30 days
                            </label>
                        </div>

                        <button type="submit" 
                                name="login" 
                                class="btn">
                            <i class="fas fa-sign-in-alt"></i>
                            Login to SkyMeet
                        </button>

                        <div class="forgot-password">
                            <a onclick="showForgotForm()">
                                <i class="fas fa-key"></i> Forgot Password?
                            </a>
                        </div>
                    </form>

                    <!-- Register Form -->
                    <form id="registerForm" method="post" style="display: <?php echo ($showRegisterForm) ? 'block' : 'none'; ?>;">
                        <div class="form-title">
                            <h2><i class="fas fa-user-plus dashboard-icon"></i> Create Account</h2>
                            <p>Join SkyMeet today</p>
                        </div>

                        <div class="input-group">
                            <label for="regUsername"><i class="fas fa-user dashboard-icon"></i> Username</label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" 
                                   id="regUsername" 
                                   name="username" 
                                   class="input-field <?php echo !empty($usernameError) ? 'error' : ''; ?>" 
                                   placeholder="Enter your username" 
                                   required
                                   value="<?php echo htmlspecialchars($formUsername); ?>">
                            <?php if(!empty($usernameError)): ?>
                                <div class="field-error" id="usernameError">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $usernameError; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="input-group">
                            <label for="regEmail"><i class="fas fa-envelope dashboard-icon"></i> Email Address</label>
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <input type="email" 
                                   id="regEmail" 
                                   name="email" 
                                   class="input-field <?php echo !empty($emailError) ? 'error' : ''; ?>" 
                                   placeholder="username@hotmail.com" 
                                   required
                                   value="<?php echo htmlspecialchars($formEmail); ?>"
                                   oninput="validateEmailDomain(this.value)">
                            <div class="email-hint">
                                <i class="fas fa-info-circle"></i>
                                <span>Allowed email: <span class="allowed-domains">@hotmail.com, @gmail.com, @segi4u.my</span></span>
                            </div>
                            <?php if(!empty($emailError)): ?>
                                <div class="field-error" id="emailError">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $emailError; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="input-group">
                            <label for="regPassword"><i class="fas fa-lock dashboard-icon"></i> Password</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <!-- FIX: Add onkeydown to prevent space character from being entered/displayed -->
                            <input type="password" 
                                   id="regPassword" 
                                   name="password" 
                                   class="input-field <?php echo !empty($passwordError) ? 'error' : ''; ?>" 
                                   placeholder="Create a password (min 6 characters - spaces not allowed)" 
                                   required
                                   oninput="checkPasswordStrength(this.value)"
                                   onkeydown="return preventSpace(event)"
                                   onpaste="handlePaste(event)">
                            <button type="button" 
                                    class="password-toggle" 
                                    onclick="togglePassword('regPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="password-strength">
                                <div class="strength-text">
                                    <span>Password Strength</span>
                                    <span id="strengthText">None</span>
                                </div>
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                            </div>
                            <?php if(!empty($passwordError)): ?>
                                <div class="field-error" id="passwordError">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $passwordError; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="input-group">
                            <label for="regConfirmPassword"><i class="fas fa-lock dashboard-icon"></i> Confirm Password</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <!-- FIX: Add onkeydown to prevent space character from being entered/displayed -->
                            <input type="password" 
                                   id="regConfirmPassword" 
                                   name="confirm_password" 
                                   class="input-field <?php echo !empty($confirmPasswordError) ? 'error' : ''; ?>" 
                                   placeholder="Confirm your password" 
                                   required
                                   onkeydown="return preventSpace(event)"
                                   onpaste="handlePaste(event)">
                            <button type="button" 
                                    class="password-toggle" 
                                    onclick="togglePassword('regConfirmPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if(!empty($confirmPasswordError)): ?>
                                <div class="field-error" id="confirmPasswordError">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $confirmPasswordError; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="input-group">
                            <label for="regRole"><i class="fas fa-user-tag dashboard-icon"></i> Account Type</label>
                            <div class="input-icon">
                                <i class="fas fa-user-tag"></i>
                            </div>
                            <select id="regRole" 
                                    name="role" 
                                    class="input-field <?php echo !empty($roleError) ? 'error' : ''; ?>" 
                                    required>
                                <option value="" disabled <?php echo empty($formRole) ? 'selected' : ''; ?>>Select account type</option>
                                <option value="User" <?php echo ($formRole == 'User') ? 'selected' : ''; ?>>User</option>
                            </select>
                            <?php if(!empty($roleError)): ?>
                                <div class="field-error" id="roleError">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $roleError; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="terms">
                            By creating an account, you agree to our 
                            <a href="#">Terms of Service</a> and 
                            <a href="#">Privacy Policy</a>
                        </div>
                        
                        <button type="submit" 
                                name="register" 
                                class="btn"
                                onclick="return validateRegisterForm(event)">
                            <i class="fas fa-user-plus"></i>
                            Create Account
                        </button>
                    </form>

                    <!-- Forgot Password Form -->
                    <form id="forgotForm" class="forgot-form" method="post" style="display: <?php echo $showForgotForm ? 'block' : 'none'; ?>;">
                        <div class="forgot-header">
                            <h2><i class="fas fa-key dashboard-icon"></i> Reset Password</h2>
                            <p>Enter your username and new password</p>
                        </div>
                        
                        <?php if(!empty($forgotSuccess)): ?>
                            <div class="forgot-message success" id="forgotMessage">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $forgotSuccess; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="input-group">
                            <label for="resetUsername"><i class="fas fa-user dashboard-icon"></i> USERNAME</label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" 
                                   id="resetUsername" 
                                   name="username" 
                                   class="input-field <?php echo (!empty($forgotError) && strpos($forgotError, 'Username') !== false) ? 'error' : ''; ?>" 
                                   placeholder="Enter your username" 
                                   required
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   oninput="clearResetUsernameError()">
                            <?php if(!empty($forgotError) && strpos($forgotError, 'Username') !== false): ?>
                                <div class="field-error" id="resetUsernameError">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $forgotError; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="input-group">
                            <label for="newPassword"><i class="fas fa-lock dashboard-icon"></i> NEW PASSWORD</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <!-- FIX: Add onkeydown to prevent space character from being entered/displayed -->
                            <input type="password" 
                                   id="newPassword" 
                                   name="new_password" 
                                   class="input-field <?php echo (!empty($forgotError) && (strpos($forgotError, 'Password') !== false && strpos($forgotError, 'match') === false)) ? 'error' : ''; ?>" 
                                   placeholder="Create new password (min 6 characters - spaces not allowed)" 
                                   required
                                   oninput="checkResetPasswordStrength(this.value)"
                                   onkeydown="return preventSpace(event)"
                                   onpaste="handlePaste(event)">
                            <button type="button" 
                                    class="password-toggle" 
                                    onclick="togglePassword('newPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="password-strength">
                                <div class="strength-text">
                                    <span>Password Strength</span>
                                    <span id="resetStrengthText">None</span>
                                </div>
                                <div class="strength-bar">
                                    <div class="strength-fill" id="resetStrengthFill"></div>
                                </div>
                            </div>
                            <?php if(!empty($forgotError) && strpos($forgotError, 'Password') !== false && strpos($forgotError, 'match') === false && strpos($forgotError, 'Username') === false): ?>
                                <div class="field-error" id="newPasswordError">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $forgotError; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="input-group">
                            <label for="confirmNewPassword"><i class="fas fa-lock dashboard-icon"></i> CONFIRM PASSWORD</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <!-- FIX: Add onkeydown to prevent space character from being entered/displayed -->
                            <input type="password" 
                                   id="confirmNewPassword" 
                                   name="confirm_new_password" 
                                   class="input-field <?php echo (!empty($forgotError) && strpos($forgotError, 'match') !== false) ? 'error' : ''; ?>" 
                                   placeholder="Confirm new password" 
                                   required
                                   onkeydown="return preventSpace(event)"
                                   onpaste="handlePaste(event)">
                            <button type="button" 
                                    class="password-toggle" 
                                    onclick="togglePassword('confirmNewPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if(!empty($forgotError) && strpos($forgotError, 'match') !== false): ?>
                                <div class="field-error" id="confirmPasswordError">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $forgotError; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="terms">
                            <i class="fas fa-info-circle"></i> 
                            Password must be at least 6 characters long (spaces are not allowed)
                        </div>
                        
                        <button type="submit" 
                                name="reset_password" 
                                class="btn"
                                onclick="return validateResetForm(event)">
                            <i class="fas fa-sync-alt"></i>
                            Reset Password
                        </button>

                        <div class="back-to-login">
                            <a onclick="showLoginForm()">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="form-toggle">
                    <a class="toggle-link" onclick="toggleForms()">
                        <i class="fas fa-exchange-alt"></i>
                        <span id="toggleText">
                            <?php 
                            if ($showRegisterForm) {
                                echo "Already have an account?";
                            } else {
                                echo "Create an account";
                            }
                            ?>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isLoginForm = <?php echo $showLoginForm ? 'true' : 'false'; ?>;
        let messageTimeouts = {};

        function toggleForms() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const forgotForm = document.getElementById('forgotForm');
            const toggleText = document.getElementById('toggleText');
            const title = document.querySelector('.form-title h2');
            const subtitle = document.querySelector('.form-title p');

            // Hide forgot form if visible
            if (forgotForm.style.display === 'block') {
                forgotForm.style.animation = 'floatOut 0.4s ease forwards';
                setTimeout(() => {
                    forgotForm.style.display = 'none';
                    forgotForm.style.animation = '';
                }, 400);
            }

            // Animate current form out
            const currentForm = isLoginForm ? loginForm : registerForm;
            currentForm.style.animation = 'floatOut 0.4s ease forwards';
            
            setTimeout(() => {
                currentForm.style.display = 'none';
                currentForm.style.animation = '';
                
                // Show new form
                const newForm = isLoginForm ? registerForm : loginForm;
                newForm.style.display = 'block';
                newForm.style.opacity = '0';
                newForm.style.transform = 'translateY(30px) scale(0.95)';
                
                setTimeout(() => {
                    newForm.style.animation = 'floatIn 0.4s ease forwards';
                    
                    // Update text content
                    if (isLoginForm) {
                        title.innerHTML = '<i class="fas fa-user-plus dashboard-icon"></i> Create Account';
                        subtitle.textContent = 'Join SkyMeet today';
                        toggleText.textContent = 'Already have an account?';
                    } else {
                        title.innerHTML = '<i class="fas fa-sign-in-alt dashboard-icon"></i> Welcome Back';
                        subtitle.textContent = 'Please login to your account';
                        toggleText.textContent = 'Create an account';
                    }
                    
                    isLoginForm = !isLoginForm;
                    
                    // Focus on first input of new form
                    const firstInput = newForm.querySelector('input, select');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }, 50);
            }, 400);
        }

        function showForgotForm() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const forgotForm = document.getElementById('forgotForm');
            const toggleText = document.getElementById('toggleText');
            
            // Hide register form if visible
            if (registerForm.style.display === 'block') {
                registerForm.style.animation = 'floatOut 0.4s ease forwards';
                setTimeout(() => {
                    registerForm.style.display = 'none';
                }, 400);
            }
            
            // Animate login form out
            loginForm.style.animation = 'floatOut 0.4s ease forwards';
            
            setTimeout(() => {
                loginForm.style.display = 'none';
                loginForm.style.animation = '';
                
                // Show forgot form
                forgotForm.style.display = 'block';
                forgotForm.style.opacity = '0';
                forgotForm.style.transform = 'translateY(30px) scale(0.95)';
                
                setTimeout(() => {
                    forgotForm.style.animation = 'floatIn 0.4s ease forwards';
                    
                    // Update toggle text
                    toggleText.textContent = 'Create an account';
                    
                    // Focus on username input
                    const usernameInput = document.getElementById('resetUsername');
                    if (usernameInput) {
                        usernameInput.focus();
                    }
                }, 50);
            }, 400);
        }

        function showLoginForm() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const forgotForm = document.getElementById('forgotForm');
            const toggleText = document.getElementById('toggleText');
            const title = document.querySelector('.form-title h2');
            const subtitle = document.querySelector('.form-title p');
            
            // Hide register form if visible
            if (registerForm.style.display === 'block') {
                registerForm.style.display = 'none';
            }
            
            // Animate forgot form out
            forgotForm.style.animation = 'floatOut 0.4s ease forwards';
            
            setTimeout(() => {
                forgotForm.style.display = 'none';
                forgotForm.style.animation = '';
                
                // Show login form
                loginForm.style.display = 'block';
                loginForm.style.opacity = '0';
                loginForm.style.transform = 'translateY(30px) scale(0.95)';
                
                setTimeout(() => {
                    loginForm.style.animation = 'floatIn 0.4s ease forwards';
                    
                    // Update text content
                    title.innerHTML = '<i class="fas fa-sign-in-alt dashboard-icon"></i> Welcome Back';
                    subtitle.textContent = 'Please login to your account';
                    toggleText.textContent = 'Create an account';
                    
                    isLoginForm = true;
                    
                    // Focus on username input
                    const usernameInput = document.getElementById('loginUsername');
                    if (usernameInput) {
                        usernameInput.focus();
                    }
                }, 50);
            }, 400);
        }

        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
                button.style.color = '#667eea';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
                button.style.color = '#cbd5e0';
            }
        }

        function toggleRememberMe() {
            const checkbox = document.getElementById('rememberMe');
            checkbox.checked = !checkbox.checked;
            
            // Store preference
            if (checkbox.checked) {
                localStorage.setItem('skymeet_remember_preference', 'true');
            } else {
                localStorage.removeItem('skymeet_remember_preference');
            }
        }

        // FIX: Function to prevent space character from being entered
        function preventSpace(event) {
            if (event.key === ' ' || event.keyCode === 32) {
                event.preventDefault();
                // Show a small warning that spaces are not allowed
                showTemporaryTooltip(event.target, 'Spaces are not allowed in passwords');
                return false;
            }
            return true;
        }

        // FIX: Function to handle paste events and remove spaces
        function handlePaste(event) {
            event.preventDefault();
            let pastedText = (event.clipboardData || window.clipboardData).getData('text');
            // Remove all spaces from pasted text
            let cleanedText = pastedText.replace(/\s/g, '');
            
            // Insert the cleaned text at cursor position
            const input = event.target;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const currentValue = input.value;
            const newValue = currentValue.substring(0, start) + cleanedText + currentValue.substring(end);
            input.value = newValue;
            
            // Set cursor position after pasted text
            input.selectionStart = input.selectionEnd = start + cleanedText.length;
            
            // Trigger input event to update password strength
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }

        // FIX: Show temporary tooltip for space warning
        function showTemporaryTooltip(element, message) {
            // Check if tooltip already exists
            let tooltip = document.getElementById('spaceTooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.id = 'spaceTooltip';
                tooltip.style.cssText = `
                    position: absolute;
                    background: #ff9800;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1000;
                    animation: fadeInOut 2s ease forwards;
                `;
                document.body.appendChild(tooltip);
            }
            
            // Position tooltip near the element
            const rect = element.getBoundingClientRect();
            tooltip.style.top = (rect.top - 30) + 'px';
            tooltip.style.left = (rect.left) + 'px';
            tooltip.textContent = message;
            
            // Remove tooltip after animation
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.remove();
                }
            }, 2000);
        }

        function validateEmailDomain(email) {
            const allowedDomains = ['@hotmail.com', '@gmail.com', '@segi4u.my'];
            let isValid = false;
            
            for (let domain of allowedDomains) {
                if (email.endsWith(domain)) {
                    isValid = true;
                    break;
                }
            }
            
            const emailInput = document.getElementById('regEmail');
            
            if (email && !isValid) {
                emailInput.classList.add('error');
                emailInput.classList.remove('success');
            } else if (email && isValid) {
                emailInput.classList.add('success');
                emailInput.classList.remove('error');
            } else {
                emailInput.classList.remove('error', 'success');
            }
            
            return isValid;
        }

        function checkPasswordStrength(password) {
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            // FIX: Remove spaces from password for strength calculation
            const passwordWithoutSpaces = password.replace(/\s/g, '');
            let strength = 0;
            
            // Check password length (excluding spaces)
            if (passwordWithoutSpaces.length >= 8) strength++;
            
            // Check for mixed case
            if (/[a-z]/.test(passwordWithoutSpaces) && /[A-Z]/.test(passwordWithoutSpaces)) strength++;
            
            // Check for numbers
            if (/\d/.test(passwordWithoutSpaces)) strength++;
            
            // Check for special characters
            if (/[^a-zA-Z0-9]/.test(passwordWithoutSpaces)) strength++;
            
            // Update strength bar
            let strengthPercent = (strength / 4) * 100;
            strengthFill.style.width = strengthPercent + '%';
            
            // Update color and text
            switch(strength) {
                case 0:
                    strengthFill.className = 'strength-fill';
                    strengthText.textContent = 'None';
                    strengthFill.style.width = '0%';
                    break;
                case 1:
                    strengthFill.className = 'strength-fill strength-weak';
                    strengthText.textContent = 'Weak';
                    break;
                case 2:
                    strengthFill.className = 'strength-fill strength-fair';
                    strengthText.textContent = 'Fair';
                    break;
                case 3:
                    strengthFill.className = 'strength-fill strength-good';
                    strengthText.textContent = 'Good';
                    break;
                case 4:
                    strengthFill.className = 'strength-fill strength-strong';
                    strengthText.textContent = 'Strong';
                    break;
            }
        }

        function checkResetPasswordStrength(password) {
            const strengthFill = document.getElementById('resetStrengthFill');
            const strengthText = document.getElementById('resetStrengthText');
            
            if (!strengthFill || !strengthText) return;
            
            // FIX: Remove spaces from password for strength calculation
            const passwordWithoutSpaces = password.replace(/\s/g, '');
            let strength = 0;
            
            // Check password length (excluding spaces)
            if (passwordWithoutSpaces.length >= 8) strength++;
            
            // Check for mixed case
            if (/[a-z]/.test(passwordWithoutSpaces) && /[A-Z]/.test(passwordWithoutSpaces)) strength++;
            
            // Check for numbers
            if (/\d/.test(passwordWithoutSpaces)) strength++;
            
            // Check for special characters
            if (/[^a-zA-Z0-9]/.test(passwordWithoutSpaces)) strength++;
            
            // Update strength bar
            let strengthPercent = (strength / 4) * 100;
            strengthFill.style.width = strengthPercent + '%';
            
            // Update color and text
            switch(strength) {
                case 0:
                    strengthFill.className = 'strength-fill';
                    strengthText.textContent = 'None';
                    strengthFill.style.width = '0%';
                    break;
                case 1:
                    strengthFill.className = 'strength-fill strength-weak';
                    strengthText.textContent = 'Weak';
                    break;
                case 2:
                    strengthFill.className = 'strength-fill strength-fair';
                    strengthText.textContent = 'Fair';
                    break;
                case 3:
                    strengthFill.className = 'strength-fill strength-good';
                    strengthText.textContent = 'Good';
                    break;
                case 4:
                    strengthFill.className = 'strength-fill strength-strong';
                    strengthText.textContent = 'Strong';
                    break;
            }
        }

        function showLoading() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('active');
            
            // Hide loading after form submission
            setTimeout(() => {
                loadingOverlay.classList.remove('active');
            }, 2000);
        }

        function clearResetUsernameError() {
            const usernameField = document.getElementById('resetUsername');
            const errorElement = document.getElementById('resetUsernameError');
            
            if (usernameField) {
                usernameField.classList.remove('error');
            }
            
            if (errorElement) {
                errorElement.style.animation = 'floatOut 0.4s ease forwards';
                setTimeout(() => {
                    if (errorElement && errorElement.parentNode) {
                        errorElement.remove();
                    }
                }, 400);
            }
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            field.classList.add('error');
            
            // Create error message element
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.id = fieldId + 'Error';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            
            // Find the input-group parent and append error message
            const inputGroup = field.closest('.input-group');
            
            // Remove existing error message if any
            const existingError = document.getElementById(fieldId + 'Error');
            if (existingError) {
                existingError.remove();
            }
            
            inputGroup.appendChild(errorDiv);
            
            // Auto remove after 5 seconds
            if (messageTimeouts[fieldId]) {
                clearTimeout(messageTimeouts[fieldId]);
            }
            
            messageTimeouts[fieldId] = setTimeout(() => {
                const errorElement = document.getElementById(fieldId + 'Error');
                if (errorElement) {
                    errorElement.style.animation = 'floatOut 0.4s ease forwards';
                    setTimeout(() => {
                        if (errorElement && errorElement.parentNode) {
                            errorElement.remove();
                            field.classList.remove('error');
                        }
                    }, 400);
                }
                delete messageTimeouts[fieldId];
            }, 5000);
        }

        function validateRegisterForm(event) {
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('regConfirmPassword').value;
            const role = document.getElementById('regRole').value;
            const email = document.getElementById('regEmail').value;
            const username = document.getElementById('regUsername').value;
            
            let hasError = false;
            
            // Clear all existing field error messages
            clearAllFieldErrors();
            
            // Username validation
            if (!username.trim()) {
                showFieldError('regUsername', 'Username is required');
                hasError = true;
            }
            
            // Email domain validation
            const allowedDomains = ['@hotmail.com', '@gmail.com', '@segi4u.my'];
            let emailValid = false;
            
            for (let domain of allowedDomains) {
                if (email.endsWith(domain)) {
                    emailValid = true;
                    break;
                }
            }
            
            if (!email.trim()) {
                showFieldError('regEmail', 'Email is required');
                hasError = true;
            } else if (!emailValid) {
                showFieldError('regEmail', 'Email must be from @hotmail.com, @gmail.com, or @segi4u.my');
                hasError = true;
            }
            
            // FIX: Password validation - trim to ignore spaces
            const trimmedPassword = password.replace(/\s/g, '');
            const trimmedConfirmPassword = confirmPassword.replace(/\s/g, '');
            
            if (!password) {
                showFieldError('regPassword', 'Password is required');
                hasError = true;
            } else if (trimmedPassword.length < 6) {
                showFieldError('regPassword', 'Password must be at least 6 characters (spaces not allowed)');
                hasError = true;
            }
            
            // Check if password contains spaces (additional check)
            if (password.includes(' ')) {
                showFieldError('regPassword', 'Spaces are not allowed in passwords');
                hasError = true;
            }
            
            // FIX: Confirm password validation - compare trimmed values
            if (trimmedPassword !== trimmedConfirmPassword) {
                showFieldError('regConfirmPassword', 'Passwords do not match');
                hasError = true;
            }
            
            // Check if confirm password contains spaces
            if (confirmPassword.includes(' ')) {
                showFieldError('regConfirmPassword', 'Spaces are not allowed in passwords');
                hasError = true;
            }
            
            // Role validation
            if (!role) {
                showFieldError('regRole', 'Please select an account type');
                hasError = true;
            }
            
            if (hasError) {
                event.preventDefault();
                return false;
            }
            
            showLoading();
            return true;
        }

        function validateResetForm(event) {
            const username = document.getElementById('resetUsername').value.trim();
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmNewPassword').value;
            
            // Clear previous errors
            const existingErrors = document.querySelectorAll('#forgotForm .field-error');
            existingErrors.forEach(error => error.remove());
            
            const usernameField = document.getElementById('resetUsername');
            const passwordField = document.getElementById('newPassword');
            const confirmField = document.getElementById('confirmNewPassword');
            
            usernameField.classList.remove('error');
            passwordField.classList.remove('error');
            confirmField.classList.remove('error');
            
            let hasError = false;
            
            if (!username) {
                showFieldError('resetUsername', 'Username is required');
                hasError = true;
            }
            
            // FIX: Password validation - trim to ignore spaces
            const trimmedPassword = password.replace(/\s/g, '');
            const trimmedConfirmPassword = confirmPassword.replace(/\s/g, '');
            
            if (!password) {
                showFieldError('newPassword', 'Password is required');
                hasError = true;
            } else if (trimmedPassword.length < 6) {
                showFieldError('newPassword', 'Password must be at least 6 characters (spaces not allowed)');
                hasError = true;
            }
            
            // Check if password contains spaces
            if (password.includes(' ')) {
                showFieldError('newPassword', 'Spaces are not allowed in passwords');
                hasError = true;
            }
            
            if (trimmedPassword !== trimmedConfirmPassword) {
                showFieldError('confirmNewPassword', 'Passwords do not match');
                hasError = true;
            }
            
            // Check if confirm password contains spaces
            if (confirmPassword.includes(' ')) {
                showFieldError('confirmNewPassword', 'Spaces are not allowed in passwords');
                hasError = true;
            }
            
            if (hasError) {
                event.preventDefault();
                return false;
            }
            
            showLoading();
            return true;
        }

        function clearAllFieldErrors() {
            const errorMessages = document.querySelectorAll('.field-error');
            errorMessages.forEach(error => error.remove());
            
            const errorFields = document.querySelectorAll('.input-field.error');
            errorFields.forEach(field => field.classList.remove('error'));
            
            // Clear all timeouts
            Object.keys(messageTimeouts).forEach(key => {
                clearTimeout(messageTimeouts[key]);
                delete messageTimeouts[key];
            });
        }

        // Auto-focus first input
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($showForgotForm): ?>
            document.getElementById('resetUsername').focus();
            <?php elseif($showRegisterForm): ?>
            document.getElementById('regUsername').focus();
            <?php else: ?>
            document.getElementById('loginUsername').focus();
            <?php endif; ?>
            
            // Check for remember me preference
            const rememberPref = localStorage.getItem('skymeet_remember_preference');
            if (rememberPref === 'true' || <?php echo !empty($rememberedUsername) ? 'true' : 'false'; ?>) {
                document.getElementById('rememberMe').checked = true;
            }
            
            // Auto remove general message after 5 seconds
            const generalMessage = document.getElementById('generalMessage');
            if (generalMessage) {
                setTimeout(() => {
                    if (generalMessage && generalMessage.parentNode) {
                        generalMessage.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (generalMessage && generalMessage.parentNode) {
                                generalMessage.remove();
                            }
                        }, 400);
                    }
                }, 5000);
            }
            
            // Auto remove forgot message after 5 seconds
            const forgotMessage = document.getElementById('forgotMessage');
            if (forgotMessage) {
                setTimeout(() => {
                    if (forgotMessage && forgotMessage.parentNode) {
                        forgotMessage.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (forgotMessage && forgotMessage.parentNode) {
                                forgotMessage.remove();
                            }
                        }, 400);
                    }
                }, 5000);
            }
            
            // Auto remove field error messages after 5 seconds
            const fieldErrors = document.querySelectorAll('.field-error');
            fieldErrors.forEach((error, index) => {
                const fieldId = error.id.replace('Error', '');
                setTimeout(() => {
                    if (error && error.parentNode) {
                        error.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (error && error.parentNode) {
                                error.remove();
                                const field = document.getElementById(fieldId);
                                if (field) {
                                    field.classList.remove('error');
                                }
                            }
                        }, 400);
                    }
                }, 5000);
            });
            
            // Add ripple effect to buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.disabled) return;
                    
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size/2;
                    const y = e.clientY - rect.top - size/2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.7);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        top: ${y}px;
                        left: ${x}px;
                        pointer-events: none;
                        z-index: 10;
                    `;
                    
                    this.style.position = 'relative';
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            });
            
            // ========== NEW CODE: Remove error styling when typing ==========
            // For registration form fields
            const regUsername = document.getElementById('regUsername');
            const regEmail = document.getElementById('regEmail');
            const regPassword = document.getElementById('regPassword');
            const regConfirmPassword = document.getElementById('regConfirmPassword');
            const regRole = document.getElementById('regRole');
            
            // Remove error styling when username is changed
            if (regUsername) {
                regUsername.addEventListener('input', function() {
                    this.classList.remove('error');
                    // Remove the error message if it exists
                    const errorElement = document.getElementById('usernameError');
                    if (errorElement) {
                        errorElement.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (errorElement && errorElement.parentNode) {
                                errorElement.remove();
                            }
                        }, 400);
                    }
                });
            }
            
            // Remove error styling when email is changed
            if (regEmail) {
                regEmail.addEventListener('input', function() {
                    this.classList.remove('error');
                    // Remove the error message if it exists
                    const errorElement = document.getElementById('emailError');
                    if (errorElement) {
                        errorElement.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (errorElement && errorElement.parentNode) {
                                errorElement.remove();
                            }
                        }, 400);
                    }
                });
            }
            
            // Remove error styling when password is changed
            if (regPassword) {
                regPassword.addEventListener('input', function() {
                    this.classList.remove('error');
                    // Remove the error message if it exists
                    const errorElement = document.getElementById('passwordError');
                    if (errorElement) {
                        errorElement.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (errorElement && errorElement.parentNode) {
                                errorElement.remove();
                            }
                        }, 400);
                    }
                });
            }
            
            // Remove error styling when confirm password is changed
            if (regConfirmPassword) {
                regConfirmPassword.addEventListener('input', function() {
                    this.classList.remove('error');
                    // Remove the error message if it exists
                    const errorElement = document.getElementById('confirmPasswordError');
                    if (errorElement) {
                        errorElement.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (errorElement && errorElement.parentNode) {
                                errorElement.remove();
                            }
                        }, 400);
                    }
                });
            }
            
            // Remove error styling when role is changed
            if (regRole) {
                regRole.addEventListener('change', function() {
                    this.classList.remove('error');
                    // Remove the error message if it exists
                    const errorElement = document.getElementById('roleError');
                    if (errorElement) {
                        errorElement.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (errorElement && errorElement.parentNode) {
                                errorElement.remove();
                            }
                        }, 400);
                    }
                });
            }
            
            // For forgot password form fields
            const resetUsername = document.getElementById('resetUsername');
            const newPassword = document.getElementById('newPassword');
            const confirmNewPassword = document.getElementById('confirmNewPassword');
            
            if (resetUsername) {
                resetUsername.addEventListener('input', function() {
                    this.classList.remove('error');
                    const errorElement = document.getElementById('resetUsernameError');
                    if (errorElement) {
                        errorElement.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (errorElement && errorElement.parentNode) {
                                errorElement.remove();
                            }
                        }, 400);
                    }
                });
            }
            
            if (newPassword) {
                newPassword.addEventListener('input', function() {
                    this.classList.remove('error');
                    const errorElement = document.getElementById('newPasswordError');
                    if (errorElement) {
                        errorElement.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (errorElement && errorElement.parentNode) {
                                errorElement.remove();
                            }
                        }, 400);
                    }
                });
            }
            
            if (confirmNewPassword) {
                confirmNewPassword.addEventListener('input', function() {
                    this.classList.remove('error');
                    const errorElement = document.getElementById('confirmPasswordError');
                    if (errorElement) {
                        errorElement.style.animation = 'floatOut 0.4s ease forwards';
                        setTimeout(() => {
                            if (errorElement && errorElement.parentNode) {
                                errorElement.remove();
                            }
                        }, 400);
                    }
                });
            }
            // ========== END OF NEW CODE ==========
        });

        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            @keyframes fadeInOut {
                0% { opacity: 0; transform: translateY(10px); }
                10% { opacity: 1; transform: translateY(0); }
                90% { opacity: 1; transform: translateY(0); }
                100% { opacity: 0; transform: translateY(-10px); }
            }
        `;
        document.head.appendChild(style);

        // Real-time password confirmation check for register form
        const confirmPasswordField = document.getElementById('regConfirmPassword');
        if (confirmPasswordField) {
            confirmPasswordField.addEventListener('input', function() {
                const password = document.getElementById('regPassword').value;
                const confirmPassword = this.value;
                
                // FIX: Compare trimmed values for visual feedback
                const trimmedPassword = password.replace(/\s/g, '');
                const trimmedConfirm = confirmPassword.replace(/\s/g, '');
                
                if (confirmPassword && trimmedPassword !== trimmedConfirm) {
                    this.classList.add('error');
                    this.classList.remove('success');
                } else if (confirmPassword) {
                    this.classList.add('success');
                    this.classList.remove('error');
                } else {
                    this.classList.remove('error', 'success');
                }
            });
        }

        // Real-time confirm password for reset form
        const confirmNewPasswordField = document.getElementById('confirmNewPassword');
        if (confirmNewPasswordField) {
            confirmNewPasswordField.addEventListener('input', function() {
                const password = document.getElementById('newPassword').value;
                const confirmPassword = this.value;
                
                // FIX: Compare trimmed values for visual feedback
                const trimmedPassword = password.replace(/\s/g, '');
                const trimmedConfirm = confirmPassword.replace(/\s/g, '');
                
                if (confirmPassword && trimmedPassword !== trimmedConfirm) {
                    this.classList.add('error');
                    this.classList.remove('success');
                } else if (confirmPassword) {
                    this.classList.add('success');
                    this.classList.remove('error');
                } else {
                    this.classList.remove('error', 'success');
                }
            });
        }

        // Real-time username clearing for reset form
        const resetUsernameField = document.getElementById('resetUsername');
        if (resetUsernameField) {
            resetUsernameField.addEventListener('input', function() {
                clearResetUsernameError();
            });
        }

        // Remember me checkbox functionality
        const rememberMeCheckbox = document.getElementById('rememberMe');
        if (rememberMeCheckbox) {
            rememberMeCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    localStorage.setItem('skymeet_remember_preference', 'true');
                } else {
                    localStorage.removeItem('skymeet_remember_preference');
                }
            });
        }

        // Add dashboard-like hover effects to form toggle
        const toggleLink = document.querySelector('.toggle-link');
        if (toggleLink) {
            toggleLink.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            toggleLink.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        }

        // Add animation to logo icon
        const logoIcon = document.querySelector('.logo i');
        if (logoIcon) {
            logoIcon.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1) rotate(10deg)';
            });
            
            logoIcon.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
            });
        }

        // Keyboard shortcuts for better UX
        document.addEventListener('keydown', function(e) {
            // Ctrl + / to toggle between forms
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                toggleForms();
            }
            
            // Escape to clear form
            if (e.key === 'Escape') {
                const forgotForm = document.getElementById('forgotForm');
                const activeForm = forgotForm.style.display === 'block' ? 'forgotForm' : (isLoginForm ? 'loginForm' : 'registerForm');
                const form = document.getElementById(activeForm);
                const inputs = form.querySelectorAll('input[type="text"], input[type="password"], input[type="email"]');
                inputs.forEach(input => {
                    if (document.activeElement !== input) {
                        input.value = '';
                    }
                });
            }
        });

        // Add dashboard-like greeting animation
        window.addEventListener('load', function() {
            const formTitle = document.querySelector('.form-title h2');
            if (formTitle) {
                formTitle.style.opacity = '0';
                formTitle.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    formTitle.style.transition = 'all 0.5s ease';
                    formTitle.style.opacity = '1';
                    formTitle.style.transform = 'translateY(0)';
                }, 300);
            }
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>