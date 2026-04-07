<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$error = '';
$success = '';

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long';
    } else {
        // Get current user's password from database
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in database
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success = 'Password changed successfully!';
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to update password. Please try again.';
            }
            $update_stmt->close();
        } else {
            $error = 'Current password is incorrect';
        }
        $stmt->close();
    }
}

// Function to get initials
function getInitials($username) {
    if (strlen($username) >= 2) {
        return strtoupper(substr($username, 0, 2));
    }
    return strtoupper($username . $username);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - JustMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f8f9ff;
            min-height: 100vh;
            color: #333;
        }

        .password-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .logo {
            padding: 30px 25px;
            border-bottom: 1px solid #eee;
        }

        .logo h1 {
            color: #667eea;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo h1 i {
            font-size: 32px;
        }

        .user-profile {
            padding: 30px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
        }

        .user-info p {
            color: #666;
            font-size: 14px;
        }

        .nav-menu {
            flex: 1;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            background: #f8f9ff;
            color: #667eea;
            border-left: 4px solid #667eea;
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1), transparent);
            color: #667eea;
            border-left: 4px solid #667eea;
        }

        .nav-item i {
            font-size: 20px;
            width: 24px;
        }

        .nav-badge {
            margin-left: auto;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 25px;
            border-top: 1px solid #eee;
            margin-top: auto;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #ffebee;
            border: none;
            border-radius: 12px;
            color: #f44336;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            font-size: 16px;
        }

        .logout-btn:hover {
            background: #f44336;
            color: white;
        }

        /* Main Content */
        .main-content {
            padding: 40px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .password-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 50px;
            width: 100%;
            max-width: 500px;
        }

        .password-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .password-header h2 {
            font-size: 32px;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .password-header p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
        }

        .password-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            margin: 0 auto 25px;
        }

        /* Form */
        .password-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9ff;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 45px;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 18px;
        }

        .password-strength {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .strength-meter {
            flex: 1;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .strength-text {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .strength-weak { background: #ef4444; }
        .strength-fair { background: #f59e0b; }
        .strength-good { background: #10b981; }
        .strength-strong { background: #059669; }

        /* Messages */
        .alert {
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fee;
            border: 2px solid #f44336;
            color: #c62828;
        }

        .alert-success {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            color: #2e7d32;
        }

        .alert i {
            font-size: 20px;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Requirements List */
        .requirements {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            border-left: 4px solid #667eea;
        }

        .requirements h4 {
            font-size: 15px;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .requirements ul {
            list-style: none;
            padding-left: 5px;
        }

        .requirements li {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirements li i {
            font-size: 12px;
            width: 16px;
        }

        .requirement-valid {
            color: #10b981;
        }

        .requirement-invalid {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> JustMeet</h1>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo getInitials($username); ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($username); ?></h3>
                    <p><?php echo htmlspecialchars($_SESSION['email'] ?? 'User'); ?></p>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="meetings.php" class="nav-item">
                    <i class="fas fa-video"></i> Meetings
                </a>
                <a href="schedule.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i> Schedule
                </a>
                <a href="teams.php" class="nav-item">
                    <i class="fas fa-users"></i> Teams
                </a>
                <a href="chat.php" class="nav-item">
                    <i class="fas fa-comments"></i> Messages
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="change-password.php" class="nav-item active">
                    <i class="fas fa-key"></i> Change Password
                </a>
            </nav>

            <div class="sidebar-footer">
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="password-card">
                <div class="password-icon">
                    <i class="fas fa-key"></i>
                </div>
                
                <div class="password-header">
                    <h2>Change Password</h2>
                    <p>Update your password to keep your account secure</p>
                </div>

                <!-- Error/Success Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="password-form" id="passwordForm">
                    <!-- Current Password -->
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" 
                               class="form-control" placeholder="Enter current password" required
                               value="<?php echo htmlspecialchars($_POST['current_password'] ?? ''); ?>">
                        <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <!-- New Password -->
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" placeholder="Enter new password" required
                               value="<?php echo htmlspecialchars($_POST['new_password'] ?? ''); ?>"
                               onkeyup="checkPasswordStrength()">
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                        
                        <!-- Password Strength Meter -->
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Very Weak</div>
                        </div>
                    </div>

                    <!-- Confirm New Password -->
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" placeholder="Confirm new password" required
                               value="<?php echo htmlspecialchars($_POST['confirm_password'] ?? ''); ?>">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div id="passwordMatch" style="display: none; margin-top: 10px; font-size: 14px;"></div>
                    </div>

                    <!-- Password Requirements -->
                    <div class="requirements">
                        <h4><i class="fas fa-info-circle"></i> Password Requirements</h4>
                        <ul>
                            <li id="reqLength"><i class="fas fa-circle"></i> At least 6 characters</li>
                            <li id="reqUppercase"><i class="fas fa-circle"></i> At least one uppercase letter</li>
                            <li id="reqLowercase"><i class="fas fa-circle"></i> At least one lowercase letter</li>
                            <li id="reqNumber"><i class="fas fa-circle"></i> At least one number</li>
                            <li id="reqSpecial"><i class="fas fa-circle"></i> At least one special character</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            // Requirements check
            const hasLength = password.length >= 6;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            
            // Update requirement indicators
            updateRequirement('reqLength', hasLength);
            updateRequirement('reqUppercase', hasUppercase);
            updateRequirement('reqLowercase', hasLowercase);
            updateRequirement('reqNumber', hasNumber);
            updateRequirement('reqSpecial', hasSpecial);
            
            // Calculate strength score
            let score = 0;
            if (hasLength) score++;
            if (hasUppercase) score++;
            if (hasLowercase) score++;
            if (hasNumber) score++;
            if (hasSpecial) score++;
            
            // Update strength meter
            const percentage = (score / 5) * 100;
            strengthFill.style.width = percentage + '%';
            
            // Update strength text and color
            let strength = '';
            let colorClass = '';
            
            if (score === 0) {
                strength = 'Very Weak';
                colorClass = 'strength-weak';
            } else if (score <= 2) {
                strength = 'Weak';
                colorClass = 'strength-weak';
            } else if (score === 3) {
                strength = 'Fair';
                colorClass = 'strength-fair';
            } else if (score === 4) {
                strength = 'Good';
                colorClass = 'strength-good';
            } else {
                strength = 'Strong';
                colorClass = 'strength-strong';
            }
            
            strengthText.textContent = strength;
            strengthFill.className = 'strength-fill ' + colorClass;
            
            // Check password match
            checkPasswordMatch();
        }

        // Update requirement indicator
        function updateRequirement(elementId, isValid) {
            const element = document.getElementById(elementId);
            const icon = element.querySelector('i');
            
            if (isValid) {
                icon.className = 'fas fa-check-circle requirement-valid';
                element.classList.add('requirement-valid');
                element.classList.remove('requirement-invalid');
            } else {
                icon.className = 'fas fa-times-circle requirement-invalid';
                element.classList.add('requirement-invalid');
                element.classList.remove('requirement-valid');
            }
        }

        // Check if passwords match
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchElement = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmPassword === '') {
                matchElement.style.display = 'none';
                return;
            }
            
            if (password === confirmPassword) {
                matchElement.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Passwords match';
                matchElement.style.color = '#10b981';
                matchElement.style.display = 'block';
                submitBtn.disabled = false;
            } else {
                matchElement.innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i> Passwords do not match';
                matchElement.style.color = '#ef4444';
                matchElement.style.display = 'block';
                submitBtn.disabled = true;
            }
        }

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check if new password is same as current
            if (currentPassword === newPassword) {
                e.preventDefault();
                alert('New password cannot be the same as current password');
                return;
            }
            
            // Check password strength
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return;
            }
            
            // Check if passwords match
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }
        });

        // Check password match on confirm password input
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        // Auto-hide success message after 5 seconds
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.opacity = '0';
                successAlert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    successAlert.remove();
                }, 300);
            }, 5000);
        }

        // Focus on current password field on page load
        window.onload = function() {
            document.getElementById('current_password').focus();
        };
    </script>
</body>
</html>