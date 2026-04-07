<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Guest';

// Check if meetings table has password fields, add if not
try {
    $check_sql = "SHOW COLUMNS FROM meetings LIKE 'password'";
    $result = $conn->query($check_sql);
    if ($result->num_rows == 0) {
        // Add password fields
        $alter_sql = "ALTER TABLE meetings 
                     ADD COLUMN password VARCHAR(255) NULL DEFAULT NULL AFTER room_id,
                     ADD COLUMN is_password_protected TINYINT(1) DEFAULT 0 AFTER password";
        $conn->query($alter_sql);
    }
} catch (Exception $e) {
    error_log("Check/alter table error: " . $e->getMessage());
}

// Generate unique 12-char room ID
function generateRoomID() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $room_id = '';
    for ($i = 0; $i < 12; $i++) {
        $room_id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $room_id;
}

// Handle meeting creation
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_meeting'])) {
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $is_protected = isset($_POST['is_protected']) ? 1 : 0;
    $host_id = $user_id;

    // Validation
    if (empty($title)) {
        $errors[] = "Meeting title is required";
    }
    
    // Password validation if protected
    if ($is_protected) {
        if (empty($password)) {
            $errors[] = "Password is required for protected meeting";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters, special symbol are supported (@,#,$,&)";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }

    if (empty($errors)) {
        $room_id = generateRoomID();
        
        // Hash password if provided
        $hashed_password = $is_protected ? password_hash($password, PASSWORD_DEFAULT) : null;

        try {
            $sql = "INSERT INTO meetings 
                    (title, description, host_id, meeting_date, start_time, end_time, status, room_id, username, password, is_password_protected) 
                    VALUES (?, ?, ?, CURDATE(), CURTIME(), DATE_ADD(CURTIME(), INTERVAL 1 HOUR), 'ongoing', ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            // Bind parameters
            $stmt->bind_param("ssisssi", 
                $title,              // s - string
                $description,        // s - string
                $host_id,            // i - integer
                $room_id,            // s - string
                $username,           // s - string
                $hashed_password,    // s - string (could be NULL)
                $is_protected        // i - integer
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            
            // Store the plain password in session for immediate access
            if ($is_protected) {
                $_SESSION['temp_room_password'][$room_id] = $password;
            }
            
            // Redirect to meeting room with password in URL if protected
            $redirect_url = "meeting_room.php?room=" . urlencode($room_id);
            if ($is_protected && !empty($password)) {
                $redirect_url .= "&password=" . urlencode($password);
            }
            
            header("Location: " . $redirect_url);
            exit();

        } catch (Exception $e) {
            $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
            error_log("Create meeting error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Meeting - JustMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Global Resets */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Body Styles */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Container Styles */
        .container {
            width: 100%;
            max-width: 800px;
        }

        /* Back Link Styles */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            text-decoration: none;
        }

        /* Quick Start Card */
        .quick-start-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            margin-bottom: 30px;
        }

        /* Quick Start Header */
        .quick-start-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            padding: 25px;
            color: white;
            text-align: center;
        }

        .quick-start-header h2 {
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        /* Quick Start Content */
        .quick-start-content {
            padding: 30px;
            text-align: center;
        }

        .quick-start-content p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        /* Instant Button */
        .btn-instant {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .btn-instant:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(76, 175, 80, 0.3);
        }

        /* Feature List */
        .feature-list {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .feature-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .feature-item i {
            font-size: 20px;
            color: #667eea;
        }

        /* Meeting Modal */
        .meeting-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(5px);
        }

        .meeting-modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        /* Modal Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                transform: translateY(30px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Modal Content */
        .meeting-modal-content {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        /* Modal Header */
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            color: white;
            text-align: center;
            border-radius: 20px 20px 0 0;
        }

        .modal-header h2 {
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .modal-header p {
            opacity: 0.9;
            font-size: 16px;
        }

        /* Modal Body */
        .modal-body {
            padding: 30px;
        }

        /* Info Box */
        .info-box {
            background: #fff8e1;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #ffb300;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box i {
            color: #ffb300;
            font-size: 20px;
        }

        .info-box p {
            color: #5d4037;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Form Group */
        .form-group {
            margin-bottom: 25px;
        }

        /* Label Styles */
        label {
            display: block;
            margin-bottom: 10px;
            color: #444;
            font-weight: 500;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .required::after {
            content: " *";
            color: #f44336;
        }

        /* Input, Textarea, Select Styles */
        input,
        textarea,
        select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
            font-family: inherit;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Password Section */
        .password-section {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border: 2px dashed #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        /* Password Fields */
        .password-fields {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .password-fields.show {
            display: block;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle input {
            padding-right: 45px;
        }

        .password-toggle .toggle-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 16px;
        }

        /* Password Hint */
        .password-hint {
            background: #fff8e1;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 13px;
            color: #5d4037;
            border-left: 4px solid #ffb300;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-hint i {
            color: #ffb300;
            font-size: 16px;
        }

        /* Security Badge */
        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }

        /* Error and Success Messages */
        .error,
        .success {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .error {
            background: #ffebee;
            color: #d32f2f;
            border-left-color: #f44336;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left-color: #4caf50;
        }

        .error i,
        .success i {
            font-size: 18px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        /* Button Styles */
        .btn {
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #444;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.4);
        }

        /* Small Text */
        small {
            color: #666;
            display: block;
            margin-top: 5px;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-header {
                padding: 20px;
            }
            
            .modal-header h2 {
                font-size: 22px;
            }
            
            .feature-list {
                flex-direction: column;
                gap: 20px;
            }
            
            .btn-instant {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="quick-start-card">
        <div class="quick-start-header">
            <h2><i class="fas fa-bolt"></i> Start A Meeting</h2>
            <p>Create and host a meeting immediately</p>
        </div>
        <div class="quick-start-content">
            <p>Start a secure video meeting with optional password protection. Configure your meeting settings before joining the room.</p>
            <button class="btn-instant" id="startMeetingBtn">
                <i class="fas fa-play-circle"></i> Create Meeting
            </button>
            <div class="feature-list">
                <div class="feature-item"><i class="fas fa-video"></i><span>Video & Audio</span></div>
                <div class="feature-item"><i class="fas fa-desktop"></i><span>Screen Share</span></div>
                <div class="feature-item"><i class="fas fa-comments"></i><span>Live Chat</span></div>
                <div class="feature-item"><i class="fas fa-lock"></i><span>Password Protect</span></div>
            </div>
        </div>
    </div>
</div>

<!-- Meeting Setup Modal -->
<div class="meeting-modal" id="meetingModal">
    <div class="meeting-modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-cog"></i> Configure Meeting</h2>
            <p>Set up your meeting before starting</p>
        </div>
        
        <div class="modal-body">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <div><?= htmlspecialchars($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <p>
                    <strong>Note:</strong> The meeting will start immediately after configuration. 
                    If you enable password protection, it will be automatically included in the meeting link
                    for seamless access.
                </p>
            </div>

            <form method="POST" id="meetingForm">
                <input type="hidden" name="create_meeting" value="1">
                
                <div class="form-group">
                    <label for="title" class="required"><i class="fas fa-heading"></i> Meeting Title</label>
                    <input type="text" id="title" name="title" required 
                           placeholder="Team Sync, Client Meeting, Class Session..." 
                           value="<?= htmlspecialchars($_POST['title'] ?? 'Weekly Team Meeting') ?>">
                </div>

                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Description (Optional)</label>
                    <textarea id="description" name="description" 
                              placeholder="Meeting agenda, objectives, or any special notes..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="password-section">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_protected" name="is_protected" value="1" 
                               <?= isset($_POST['is_protected']) ? 'checked' : '' ?>>
                        <label for="is_protected">
                            <i class="fas fa-lock"></i> Password Protect This Meeting
                            <span class="security-badge">
                                <i class="fas fa-shield-alt"></i> Secure
                            </span>
                        </label>
                    </div>
                    
                    <div class="password-fields" id="passwordFields">
                        <div class="form-group">
                            <label for="password" class="required"><i class="fas fa-key"></i> Meeting Password</label>
                            <div class="password-toggle">
                                <input type="password" id="password" name="password" 
                                       placeholder="Enter 6+ character password"
                                       value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">
                                <button type="button" class="toggle-btn" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small>Password will be automatically included in the meeting link</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="required"><i class="fas fa-key"></i> Confirm Password</label>
                            <div class="password-toggle">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       placeholder="Re-enter the password"
                                       value="<?= htmlspecialchars($_POST['confirm_password'] ?? '') ?>">
                                <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="password-hint">
                            <i class="fas fa-link"></i>
                            <span>When you share the meeting link, it will automatically include the password. Participants can join with one click.</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="createBtn">
                        <i class="fas fa-video"></i> Create & Join Meeting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// DOM Elements
const startMeetingBtn = document.getElementById('startMeetingBtn');
const meetingModal = document.getElementById('meetingModal');
const cancelBtn = document.getElementById('cancelBtn');
const createBtn = document.getElementById('createBtn');
const isProtectedCheckbox = document.getElementById('is_protected');
const passwordFields = document.getElementById('passwordFields');
const meetingForm = document.getElementById('meetingForm');

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Toggle password fields visibility
function updatePasswordFields() {
    if (isProtectedCheckbox.checked) {
        passwordFields.classList.add('show');
    } else {
        passwordFields.classList.remove('show');
    }
}

// Show meeting setup modal
startMeetingBtn.addEventListener('click', function() {
    // Reset form
    meetingForm.reset();
    
    // Set default title with date
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    document.getElementById('title').value = `Meeting - ${dateStr}`;
    
    // Reset password fields
    isProtectedCheckbox.checked = false;
    updatePasswordFields();
    document.getElementById('password').value = '';
    document.getElementById('confirm_password').value = '';
    
    // Set focus to title field
    document.getElementById('title').focus();
    
    // Show modal
    meetingModal.classList.add('show');
});

// Hide meeting setup modal
cancelBtn.addEventListener('click', function() {
    meetingModal.classList.remove('show');
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === meetingModal) {
        meetingModal.classList.remove('show');
    }
});

// Handle password protection checkbox
isProtectedCheckbox.addEventListener('change', updatePasswordFields);

// Form submission handling
meetingForm.addEventListener('submit', function(e) {
    // Validate password fields if protected
    if (isProtectedCheckbox.checked) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long, special symbols are supported (@,#,$,&)');
            return;
        }
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match');
            return;
        }
    }
    
    // Disable button and show loading state
    createBtn.disabled = true;
    createBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Meeting...';
    
    // Form will submit normally to PHP
});

// Initialize password fields visibility
updatePasswordFields();

// Focus start button for accessibility
window.addEventListener('DOMContentLoaded', function() {
    startMeetingBtn.focus();
});
</script>
</body>
</html>