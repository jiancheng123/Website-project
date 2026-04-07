<?php
session_start();

/* DATABASE CONNECTION */
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "fypdb";

$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* MESSAGE */
$message = "";
$messageType = "";

/* PASSWORD RESET */
if (isset($_POST['reset_password'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validation
    if (empty($username) || empty($password) || empty($confirmPassword)) {
        $message = "All fields are required";
        $messageType = "error";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters";
        $messageType = "error";
    } else {
        // Check if username exists
        $check = $conn->query("SELECT * FROM users WHERE username='$username'");
        if ($check->num_rows !== 1) {
            $message = "Username not found";
            $messageType = "error";
        } else {
            $user = $check->fetch_assoc();
            $user_id = $user['id'];
            $email = $user['email'];
            
            // Update password (using MD5 to match login system)
            $hashedPassword = md5($password);
            $update_result = $conn->query("UPDATE users SET password='$hashedPassword' WHERE username='$username'");
            
            if ($update_result) {
                // Log the activity in activity_log table
                $activity = "Password reset completed";
                $details = "Password successfully reset for username: $username";
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']);
                $conn->query("INSERT INTO activity_log (user_id, activity, details, ip_address, user_agent) VALUES ('$user_id', '$activity', '$details', '$ip_address', '$user_agent')");
                
                // Generate a token for tracking
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Check if email already exists in password_resets table
                $check_exists = $conn->query("SELECT * FROM password_resets WHERE email='$email'");
                
                if ($check_exists->num_rows > 0) {
                    // Update existing record
                    $conn->query("UPDATE password_resets SET token='$token', expires_at='$expires_at', created_at=NOW() WHERE email='$email'");
                } else {
                    // Insert new record
                    $conn->query("INSERT INTO password_resets (email, token, expires_at) VALUES ('$email', '$token', '$expires_at')");
                }
                
                $message = "Password reset successful! Redirecting to login...";
                $messageType = "success";
                
                // Redirect to login page after 2 seconds
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                </script>";
            } else {
                $message = "Error updating password. Please try again.";
                $messageType = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyMeet | Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        .form-title {
            text-align: center;
            margin-bottom: 30px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .form-title p {
            color: #718096;
            font-size: 15px;
            font-weight: 500;
            max-width: 300px;
            margin: 0 auto;
            line-height: 1.5;
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
            margin-top: 10px;
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
            transform: translateX(-5px);
        }

        .message {
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

        .message::before {
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

        .message.error {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        .message.success {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
            border-left: 4px solid #4caf50;
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

        .instructions {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .instructions:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .instructions h3 {
            color: #2d3748;
            font-size: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .instructions ul {
            padding-left: 20px;
            color: #4a5568;
            font-size: 13px;
            line-height: 1.6;
        }

        .instructions li {
            margin-bottom: 8px;
            position: relative;
            padding-left: 5px;
        }

        .instructions li:before {
            content: '✓';
            color: #667eea;
            font-weight: bold;
            position: absolute;
            left: -18px;
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
            
            .instructions {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-comments"></i>
                    SkyMeet
                </div>
                <p>Reset Your Password</p>
            </div>

            <div class="auth-content">
                <?php if($message != ""): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php if($messageType == 'error'): ?>
                            <i class="fas fa-exclamation-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-check-circle"></i>
                        <?php endif; ?>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                    <div class="loading-text">Resetting Password...</div>
                </div>

                <div class="form-title">
                    <h2><i class="fas fa-key"></i> Reset Password</h2>
                    <p>Enter your username and new password to reset your account</p>
                </div>

                <div class="instructions">
                    <h3><i class="fas fa-info-circle"></i> Password Requirements</h3>
                    <ul>
                        <li>At least 6 characters long</li>
                        <li>Use a mix of letters and numbers</li>
                        <li>Consider using special characters for extra security</li>
                    </ul>
                </div>

                <form method="post" id="resetForm">
                    <div class="input-group">
                        <label for="username"><i class="fas fa-user dashboard-icon"></i> Username</label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="input-field" 
                               placeholder="Enter your username" 
                               required
                               autocomplete="off">
                    </div>

                    <div class="input-group">
                        <label for="password"><i class="fas fa-lock dashboard-icon"></i> New Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="input-field" 
                               placeholder="Enter new password" 
                               required
                               oninput="checkPasswordStrength(this.value)">
                        <button type="button" 
                                class="password-toggle" 
                                onclick="togglePassword('password', this)">
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
                    </div>

                    <div class="input-group">
                        <label for="confirm_password"><i class="fas fa-lock dashboard-icon"></i> Confirm Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="input-field" 
                               placeholder="Confirm new password" 
                               required>
                        <button type="button" 
                                class="password-toggle" 
                                onclick="togglePassword('confirm_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <button type="submit" 
                            name="reset_password" 
                            class="btn"
                            onclick="showLoading()">
                        <i class="fas fa-key"></i>
                        Reset Password
                    </button>
                </form>

                <div class="form-toggle">
                    <a class="toggle-link" href="login.php">
                        <i class="fas fa-arrow-left"></i>
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
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

        function checkPasswordStrength(password) {
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            if (!strengthFill || !strengthText) return;
            
            let strength = 0;
            
            // Check password length
            if (password.length >= 8) strength++;
            
            // Check for mixed case
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            
            // Check for numbers
            if (/\d/.test(password)) strength++;
            
            // Check for special characters
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
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
        }

        // Auto-focus username input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Add ripple effect to buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
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
                    `;
                    
                    this.style.position = 'relative';
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            });
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
        `;
        document.head.appendChild(style);

        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!username.trim()) {
                e.preventDefault();
                showMessage('Please enter your username!', 'error');
                document.getElementById('username').focus();
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showMessage('Passwords do not match!', 'error');
                document.getElementById('confirm_password').focus();
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showMessage('Password must be at least 6 characters long!', 'error');
                document.getElementById('password').focus();
                return false;
            }
            
            return true;
        });

        function showMessage(text, type) {
            const message = document.createElement('div');
            message.className = `message ${type}`;
            message.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                ${text}
            `;
            message.style.animation = 'slideIn 0.4s ease';
            
            const container = document.querySelector('.auth-content');
            const existingMessage = container.querySelector('.message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            container.insertBefore(message, container.firstChild);
            
            setTimeout(() => {
                message.style.animation = 'floatOut 0.4s ease forwards';
                setTimeout(() => message.remove(), 400);
            }, 3000);
        }

        // Check password match on the fly
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if (confirm && password !== confirm) {
                this.style.borderColor = '#f44336';
                this.style.boxShadow = '0 0 0 4px rgba(244, 67, 54, 0.15)';
            } else if (confirm) {
                this.style.borderColor = '#4caf50';
                this.style.boxShadow = '0 0 0 4px rgba(76, 175, 80, 0.15)';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.boxShadow = 'none';
            }
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + R to focus reset button
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                document.querySelector('button[name="reset_password"]').focus();
            }
            
            // Escape to clear form
            if (e.key === 'Escape') {
                const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
                inputs.forEach(input => {
                    if (document.activeElement !== input) {
                        input.value = '';
                    }
                });
            }
        });

        // Add hover effects
        document.querySelectorAll('.btn, .toggle-link, .instructions').forEach(el => {
            el.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            el.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Logo icon animation
        const logoIcon = document.querySelector('.logo i');
        if (logoIcon) {
            logoIcon.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1) rotate(10deg)';
            });
            
            logoIcon.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1.1) rotate(0deg)';
            });
        }

        // Auto-hide success message if redirecting
        <?php if ($messageType == 'success'): ?>
        setTimeout(() => {
            const successMsg = document.querySelector('.message.success');
            if (successMsg) {
                successMsg.style.animation = 'floatOut 0.4s ease forwards';
                setTimeout(() => {
                    successMsg.remove();
                }, 400);
            }
        }, 1500);
        <?php endif; ?>

        // Add password visibility toggle on enter key
        document.querySelectorAll('.input-field[type="password"]').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.type === 'password') {
                    const toggleBtn = this.parentElement.querySelector('.password-toggle');
                    if (toggleBtn) {
                        toggleBtn.click();
                    }
                }
            });
        });

        // Enhance password strength check with real-time feedback
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                
                // Also check password match in real-time
                const confirmInput = document.getElementById('confirm_password');
                if (confirmInput && confirmInput.value) {
                    if (this.value !== confirmInput.value) {
                        confirmInput.style.borderColor = '#f44336';
                        confirmInput.style.boxShadow = '0 0 0 4px rgba(244, 67, 54, 0.15)';
                    } else {
                        confirmInput.style.borderColor = '#4caf50';
                        confirmInput.style.boxShadow = '0 0 0 4px rgba(76, 175, 80, 0.15)';
                    }
                }
            });
        }
    </script>
</body>
</html>