<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear any session cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Clear remember me cookie if exists
if (isset($_COOKIE['justmeet_user'])) {
    setcookie('justmeet_user', '', time()-3600, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - SkyMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .logout-container {
            text-align: center;
            background: white;
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
        }

        .logo-text {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }

        .checkmark {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease;
        }

        .checkmark i {
            color: white;
            font-size: 40px;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            70% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 32px;
        }

        p {
            color: #666;
            margin-bottom: 30px;
            font-size: 18px;
            line-height: 1.6;
        }

        .redirect-info {
            background: #f8f9ff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .redirect-info i {
            color: #667eea;
            font-size: 24px;
        }

        .redirect-info span {
            color: #666;
            font-size: 16px;
        }

        .timer {
            font-weight: 700;
            color: #667eea;
            font-size: 18px;
        }

        .btn-login {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 18px 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .countdown {
            font-size: 48px;
            font-weight: 700;
            color: #667eea;
            margin: 30px 0;
        }

        .success-message {
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-comments"></i>
            </div>
            <div class="logo-text">SkyMeet</div>
        </div>

        <div class="success-message">
            <div class="checkmark">
                <i class="fas fa-check"></i>
            </div>
            
            <h1>Logged Out Successfully</h1>
            <p>You have been successfully logged out of your SkyMeet account.</p>
            
            <div class="redirect-info">
                <i class="fas fa-info-circle"></i>
                <span>You will be redirected to login page in <span class="timer" id="countdown">5</span> seconds</span>
            </div>
            
            <a href="login.php" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i>
                Back to Login
            </a>
        </div>
    </div>

    <script>
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        const loginBtn = document.getElementById('loginBtn');
        
        function updateCountdown() {
            countdownElement.textContent = seconds;
            
            if (seconds === 0) {
                // Redirect to login page
                window.location.href = 'login.php';
            } else {
                seconds--;
                setTimeout(updateCountdown, 1000);
            }
        }
        
        // Start countdown
        setTimeout(updateCountdown, 1000);
        
        // Manual redirect if user clicks the button
        loginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'login.php';
        });
        
        // Also redirect on any key press
        document.addEventListener('keydown', function() {
            window.location.href = 'login.php';
        });
        
        // Also redirect on click anywhere
        document.addEventListener('click', function(e) {
            if (e.target.tagName !== 'A' && !e.target.closest('.btn-login')) {
                window.location.href = 'login.php';
            }
        });
    </script>
</body>
</html>