<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$room_id = isset($_GET['room']) ? trim($_GET['room']) : '';

// Check if password is provided in URL
$url_password = isset($_GET['password']) ? trim($_GET['password']) : '';

if (empty($room_id)) {
    header("Location: dashboard.php");
    exit();
}

// Check if meeting exists and get password protection status
$meeting = null;
$host_id = null;
$host_username = null;
$is_password_protected = false;
$requires_password = false;
$meeting_password = ''; // Store hashed password
$original_password = ''; // Store original password for link generation

try {
    $sql = "SELECT m.*, u.username as host_name, u.id as host_user_id 
            FROM meetings m 
            JOIN users u ON m.host_id = u.id 
            WHERE m.room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $meeting = $result->fetch_assoc();
    
    if ($meeting) {
        $host_id = $meeting['host_user_id'];
        $host_username = $meeting['host_name'];
        $is_password_protected = $meeting['is_password_protected'] ?? false;
        $meeting_password = $meeting['password'] ?? '';
        $is_host = ($host_id == $user_id);
        
        // Check if password is verified in session
        $password_verified = isset($_SESSION['meeting_passwords'][$room_id]) && 
                            $_SESSION['meeting_passwords'][$room_id] === true;
        
        // Check if we have the original password stored in session
        if (isset($_SESSION['meeting_original_passwords'][$room_id])) {
            $original_password = $_SESSION['meeting_original_passwords'][$room_id];
        }
        elseif ($is_host && isset($_SESSION['temp_room_password'][$room_id])) {
            $original_password = $_SESSION['temp_room_password'][$room_id];
            $_SESSION['meeting_original_passwords'][$room_id] = $original_password;
        }
        
        // If password is provided in URL, verify it automatically
        if ($is_password_protected && !$is_host && !$password_verified && !empty($url_password)) {
            if (password_verify($url_password, $meeting['password'])) {
                $_SESSION['meeting_passwords'][$room_id] = true;
                $_SESSION['meeting_original_passwords'][$room_id] = $url_password;
                $password_verified = true;
                $original_password = $url_password;
                
                // Redirect to clean URL without password
                header("Location: meeting_room.php?room=" . urlencode($room_id));
                exit();
            }
        }
        
        if ($is_password_protected && !$is_host && !$password_verified) {
            $requires_password = true;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Get meeting error: " . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

if (!$meeting) {
    header("Location: dashboard.php");
    exit();
}

// Handle password verification
if ($requires_password && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_password'])) {
    $entered_password = $_POST['password'] ?? '';
    
    if (password_verify($entered_password, $meeting['password'])) {
        $_SESSION['meeting_passwords'][$room_id] = true;
        $_SESSION['meeting_original_passwords'][$room_id] = $entered_password;
        $original_password = $entered_password;
        $requires_password = false;
        header("Location: meeting_room.php?room=" . urlencode($room_id));
        exit();
    } else {
        $password_error = "Incorrect password. Please try again.";
    }
}

// If password is required, show password form
if ($requires_password) {
    // Password form HTML
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Protected Meeting - SkyMeet</title>
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
                align-items: center;
                justify-content: center;
            }
            .password-container {
                background: white;
                border-radius: 20px;
                padding: 40px;
                width: 90%;
                max-width: 400px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
            }
            .lock-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #667eea, #764ba2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                color: white;
                font-size: 32px;
            }
            h2 {
                color: #333;
                margin-bottom: 10px;
            }
            p {
                color: #666;
                margin-bottom: 20px;
            }
            .room-id {
                background: #f0f0f0;
                padding: 10px;
                border-radius: 8px;
                font-family: monospace;
                margin-bottom: 20px;
                color: #667eea;
                font-weight: bold;
            }
            .password-input {
                width: 100%;
                padding: 15px;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                font-size: 16px;
                margin-bottom: 15px;
                transition: border-color 0.3s;
            }
            .password-input:focus {
                border-color: #667eea;
                outline: none;
            }
            .error-message {
                color: #ef4444;
                margin-bottom: 15px;
                font-size: 14px;
            }
            .btn-submit {
                width: 100%;
                padding: 15px;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.3s, box-shadow 0.3s;
            }
            .btn-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
            }
            .btn-back {
                margin-top: 15px;
                display: inline-block;
                color: #666;
                text-decoration: none;
                font-size: 14px;
                transition: color 0.3s;
            }
            .btn-back:hover {
                color: #667eea;
            }
        </style>
    </head>
    <body>
        <div class="password-container">
            <div class="lock-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h2>Password Protected Meeting</h2>
            <p>This meeting requires a password to join</p>
            <div class="room-id">
                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($room_id); ?>
            </div>
            <?php if (isset($password_error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $password_error; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" class="password-input" placeholder="Enter meeting password" required>
                <button type="submit" name="verify_password" class="btn-submit">
                    <i class="fas fa-unlock-alt"></i> Join Meeting
                </button>
            </form>
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Create tables if not exists
try {
    // Check if chat_messages table exists and has message_type column
    $check_column = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'message_type'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE chat_messages ADD COLUMN message_type VARCHAR(50) DEFAULT 'chat' AFTER is_system_message");
    }
    
    // Create peer_connections table
    $conn->query("CREATE TABLE IF NOT EXISTS peer_connections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        peer_id VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_room (room_id, user_id),
        INDEX idx_room_id (room_id)
    )");
    
    // Create meeting participants table
    $conn->query("CREATE TABLE IF NOT EXISTS meeting_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id VARCHAR(50) NOT NULL,
        participant_id INT NOT NULL,
        username VARCHAR(100),
        status VARCHAR(20) DEFAULT 'online',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        left_at TIMESTAMP NULL DEFAULT NULL,
        welcome_message_sent BOOLEAN DEFAULT FALSE,
        INDEX idx_meeting (meeting_id),
        INDEX idx_participant (participant_id),
        INDEX idx_status (status),
        INDEX idx_last_active (last_active)
    )");
    
    // Create meeting participant settings table
    $conn->query("CREATE TABLE IF NOT EXISTS meeting_participant_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        device_type VARCHAR(20) NOT NULL,
        status BOOLEAN DEFAULT TRUE,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_device (room_id, user_id, device_type)
    )");
    
    // Create chat messages table
    $conn->query("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        username VARCHAR(100),
        message TEXT NOT NULL,
        file_path VARCHAR(255),
        file_name VARCHAR(255),
        file_size INT,
        is_system_message BOOLEAN DEFAULT FALSE,
        message_type VARCHAR(50) DEFAULT 'chat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_room_id (room_id),
        INDEX idx_message_type (message_type)
    )");
    
    // Create meeting tasks table
    $conn->query("CREATE TABLE IF NOT EXISTS meeting_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(50) NOT NULL,
        task_text TEXT NOT NULL,
        created_by INT NOT NULL,
        is_completed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_room_id (room_id)
    )");
    
    // Create host actions table
    $conn->query("CREATE TABLE IF NOT EXISTS host_actions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(50) NOT NULL,
        action VARCHAR(50) NOT NULL,
        target_user_id INT NOT NULL,
        host_user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_room_id (room_id),
        INDEX idx_target (target_user_id)
    )");
    
    // Create muted users table
    $conn->query("CREATE TABLE IF NOT EXISTS muted_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        muted_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_room (room_id, user_id),
        INDEX idx_room_id (room_id)
    )");
    
    // Create signaling table
    $conn->query("CREATE TABLE IF NOT EXISTS signaling (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(50) NOT NULL,
        from_user_id INT NOT NULL,
        to_user_id INT NOT NULL,
        type VARCHAR(20) NOT NULL,
        data TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_room_to (room_id, to_user_id),
        INDEX idx_created (created_at)
    )");
} catch (Exception $e) {
    error_log("Create tables error: " . $e->getMessage());
}

// FIX: Clean up stale participants 
try {
    // Mark users as offline if they haven't been active for 2 minutes
    $cleanup_sql = "UPDATE meeting_participants 
                   SET status = 'offline', left_at = NOW() 
                   WHERE meeting_id = ? 
                   AND status = 'online' 
                   AND last_active < DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    $cleanup_stmt->bind_param("s", $room_id);
    $cleanup_stmt->execute();
    $cleanup_stmt->close();
    
    // Delete very old offline records (older than 1 hour) to keep table clean
    $delete_old_sql = "DELETE FROM meeting_participants 
                      WHERE meeting_id = ? 
                      AND status = 'offline' 
                      AND left_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $delete_old_stmt = $conn->prepare($delete_old_sql);
    $delete_old_stmt->bind_param("s", $room_id);
    $delete_old_stmt->execute();
    $delete_old_stmt->close();
} catch (Exception $e) {
    error_log("Cleanup error: " . $e->getMessage());
}

// Get mic and camera settings from URL parameters (set by meetings.php)
$mic_status = isset($_GET['mic']) && $_GET['mic'] === 'on' ? true : false;
$camera_status = isset($_GET['camera']) && $_GET['camera'] === 'on' ? true : false;

// If no parameters in URL, check saved settings
if (!isset($_GET['mic']) && !isset($_GET['camera'])) {
    try {
        $sql = "SELECT device_type, status FROM meeting_participant_settings 
                WHERE room_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $room_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $saved_settings = [];
        while ($row = $result->fetch_assoc()) {
            $saved_settings[$row['device_type']] = (bool)$row['status'];
        }
        $stmt->close();
        
        $mic_status = $saved_settings['mic'] ?? true;
        $camera_status = $saved_settings['camera'] ?? true;
        
    } catch (Exception $e) {
        error_log("Get saved settings error: " . $e->getMessage());
        $mic_status = true;
        $camera_status = true;
    }
} else {
    // Save the settings from URL parameters
    try {
        if (isset($_GET['mic'])) {
            $save_mic_sql = "INSERT INTO meeting_participant_settings (room_id, user_id, device_type, status) 
                            VALUES (?, ?, 'mic', ?)
                            ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP";
            $save_mic_stmt = $conn->prepare($save_mic_sql);
            $save_mic_stmt->bind_param("sii", $room_id, $user_id, $mic_status);
            $save_mic_stmt->execute();
            $save_mic_stmt->close();
        }
        
        if (isset($_GET['camera'])) {
            $save_camera_sql = "INSERT INTO meeting_participant_settings (room_id, user_id, device_type, status) 
                               VALUES (?, ?, 'camera', ?)
                               ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP";
            $save_camera_stmt = $conn->prepare($save_camera_sql);
            $save_camera_stmt->bind_param("sii", $room_id, $user_id, $camera_status);
            $save_camera_stmt->execute();
            $save_camera_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Save settings error: " . $e->getMessage());
    }
}

// Create uploads directory
$upload_dir = 'uploads/chat_files/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if this is the first time user is joining (not a refresh)
$is_new_join = false;
$should_send_welcome = false;

try {
    $check_sql = "SELECT id, status, left_at, welcome_message_sent, last_active FROM meeting_participants 
                  WHERE meeting_id = ? AND participant_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $room_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $participant_data = $check_result->fetch_assoc();
        $old_status = $participant_data['status'];
        $welcome_already_sent = $participant_data['welcome_message_sent'] == 1;
        
        // If user was offline or left, this is a new join
        if ($old_status != 'online') {
            $is_new_join = true;
            $should_send_welcome = !$welcome_already_sent;
        }
        
        $update_sql = "UPDATE meeting_participants 
                      SET status = 'online', left_at = NULL, username = ?, last_active = NOW()
                      WHERE meeting_id = ? AND participant_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $username, $room_id, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // First time joining this meeting
        $is_new_join = true;
        $should_send_welcome = true;
        
        $insert_sql = "INSERT INTO meeting_participants (meeting_id, participant_id, status, username, welcome_message_sent, last_active) 
                      VALUES (?, ?, 'online', ?, 1, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("siss", $room_id, $user_id, $username);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    $check_stmt->close();
    
    // Add join message to chat only if this is a new join
    if ($is_new_join) {
        // Check if join message already exists in the last minute
        $check_join_sql = "SELECT id FROM chat_messages 
                          WHERE room_id = ? AND user_id = ? AND message_type = 'join' 
                          AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        $check_join_stmt = $conn->prepare($check_join_sql);
        $check_join_stmt->bind_param("si", $room_id, $user_id);
        $check_join_stmt->execute();
        $check_join_result = $check_join_stmt->get_result();
        
        if ($check_join_result->num_rows == 0) {
            $join_message = "👋 " . $username . " joined the meeting";
            $join_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, is_system_message, message_type) 
                        VALUES (?, ?, ?, ?, 1, 'join')";
            $join_stmt = $conn->prepare($join_sql);
            $join_stmt->bind_param("siss", $room_id, $user_id, $username, $join_message);
            $join_stmt->execute();
            $join_stmt->close();
        }
        $check_join_stmt->close();
    }
    
    // Add welcome message only if it should be sent and doesn't exist
    if ($should_send_welcome) {
        // Check if welcome message already exists for this user
        $check_welcome_sql = "SELECT id FROM chat_messages 
                             WHERE room_id = ? AND user_id = ? AND message_type = 'welcome'";
        $check_welcome_stmt = $conn->prepare($check_welcome_sql);
        $check_welcome_stmt->bind_param("si", $room_id, $user_id);
        $check_welcome_stmt->execute();
        $check_welcome_result = $check_welcome_stmt->get_result();
        
        if ($check_welcome_result->num_rows == 0) {
            $welcome_message = "🎉 Welcome to the meeting, " . $username . "!";
            $welcome_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, is_system_message, message_type) 
                           VALUES (?, ?, 'System', ?, 1, 'welcome')";
            $welcome_stmt = $conn->prepare($welcome_sql);
            $welcome_stmt->bind_param("sis", $room_id, $user_id, $welcome_message);
            $welcome_stmt->execute();
            $welcome_stmt->close();
            
            // Update welcome_message_sent flag
            $update_welcome_sql = "UPDATE meeting_participants SET welcome_message_sent = 1 
                                  WHERE meeting_id = ? AND participant_id = ?";
            $update_welcome_stmt = $conn->prepare($update_welcome_sql);
            $update_welcome_stmt->bind_param("si", $room_id, $user_id);
            $update_welcome_stmt->execute();
            $update_welcome_stmt->close();
        }
        $check_welcome_stmt->close();
    }
} catch (Exception $e) {
    error_log("Participant update error: " . $e->getMessage());
}

// Ensure host is in participants table
try {
    $check_host_sql = "SELECT id FROM meeting_participants WHERE meeting_id = ? AND participant_id = ?";
    $check_host_stmt = $conn->prepare($check_host_sql);
    $check_host_stmt->bind_param("si", $room_id, $host_id);
    $check_host_stmt->execute();
    $check_host_result = $check_host_stmt->get_result();
    
    if ($check_host_result->num_rows == 0) {
        $insert_host_sql = "INSERT INTO meeting_participants (meeting_id, participant_id, status, username, welcome_message_sent, last_active) 
                          VALUES (?, ?, 'online', ?, 1, NOW())";
        $insert_host_stmt = $conn->prepare($insert_host_sql);
        $insert_host_stmt->bind_param("sis", $room_id, $host_id, $host_username);
        $insert_host_stmt->execute();
        $insert_host_stmt->close();
        
        // Add host join message only if not exists
        $check_host_join_sql = "SELECT id FROM chat_messages 
                               WHERE room_id = ? AND user_id = ? AND message_type = 'join' 
                               AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        $check_host_join_stmt = $conn->prepare($check_host_join_sql);
        $check_host_join_stmt->bind_param("si", $room_id, $host_id);
        $check_host_join_stmt->execute();
        $check_host_join_result = $check_host_join_stmt->get_result();
        
        if ($check_host_join_result->num_rows == 0) {
            $host_join_message = "👋 Host " . $host_username . " is in the meeting";
            $host_join_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, is_system_message, message_type) 
                             VALUES (?, ?, ?, ?, 1, 'join')";
            $host_join_stmt = $conn->prepare($host_join_sql);
            $host_join_stmt->bind_param("siss", $room_id, $host_id, $host_username, $host_join_message);
            $host_join_stmt->execute();
            $host_join_stmt->close();
        }
        $check_host_join_stmt->close();
    } else {
        $update_host_sql = "UPDATE meeting_participants 
                           SET status = 'online', left_at = NULL, username = ?, last_active = NOW()
                           WHERE meeting_id = ? AND participant_id = ?";
        $update_host_stmt = $conn->prepare($update_host_sql);
        $update_host_stmt->bind_param("ssi", $host_username, $room_id, $host_id);
        $update_host_stmt->execute();
        $update_host_stmt->close();
    }
    $check_host_stmt->close();
} catch (Exception $e) {
    error_log("Host participant update error: " . $e->getMessage());
}

// Check if current user is muted
$is_muted = false;
try {
    $check_muted_sql = "SELECT id FROM muted_users WHERE room_id = ? AND user_id = ?";
    $check_muted_stmt = $conn->prepare($check_muted_sql);
    $check_muted_stmt->bind_param("si", $room_id, $user_id);
    $check_muted_stmt->execute();
    $check_muted_result = $check_muted_stmt->get_result();
    $is_muted = $check_muted_result->num_rows > 0;
    $check_muted_stmt->close();
    
    // If user is muted by host, override mic status
    if ($is_muted) {
        $mic_status = false;
    }
} catch (Exception $e) {
    error_log("Check muted error: " . $e->getMessage());
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    $ajax_action = $_GET['ajax'];
    header('Content-Type: application/json');
    
    if ($ajax_action === 'get_chat_messages') {
        $chat_messages = [];
        try {
            $chat_sql = "SELECT cm.*, u.username as sender_name 
                        FROM chat_messages cm 
                        LEFT JOIN users u ON cm.user_id = u.id 
                        WHERE cm.room_id = ? 
                        ORDER BY cm.created_at DESC 
                        LIMIT 50";
            $chat_stmt = $conn->prepare($chat_sql);
            $chat_stmt->bind_param("s", $room_id);
            $chat_stmt->execute();
            $chat_result = $chat_stmt->get_result();
            $chat_messages = array_reverse($chat_result->fetch_all(MYSQLI_ASSOC));
            $chat_stmt->close();
            
            echo json_encode(['success' => true, 'messages' => $chat_messages]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    
    if ($ajax_action === 'get_tasks') {
        $meeting_tasks = [];
        try {
            $tasks_sql = "SELECT mt.*, u.username as created_by_name 
                         FROM meeting_tasks mt 
                         LEFT JOIN users u ON mt.created_by = u.id 
                         WHERE mt.room_id = ? 
                         ORDER BY mt.created_at ASC 
                         LIMIT 10";
            $tasks_stmt = $conn->prepare($tasks_sql);
            $tasks_stmt->bind_param("s", $room_id);
            $tasks_stmt->execute();
            $tasks_result = $tasks_stmt->get_result();
            $meeting_tasks = $tasks_result->fetch_all(MYSQLI_ASSOC);
            $tasks_stmt->close();
            
            echo json_encode(['success' => true, 'tasks' => $meeting_tasks]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    
    if ($ajax_action === 'leave_meeting') {
        try {
            // Check if user is already offline
            $check_leave_sql = "SELECT status FROM meeting_participants 
                               WHERE meeting_id = ? AND participant_id = ?";
            $check_leave_stmt = $conn->prepare($check_leave_sql);
            $check_leave_stmt->bind_param("si", $room_id, $user_id);
            $check_leave_stmt->execute();
            $check_leave_result = $check_leave_stmt->get_result();
            $current_status = $check_leave_result->fetch_assoc()['status'] ?? 'offline';
            $check_leave_stmt->close();
            
            if ($current_status != 'offline') {
                $update_sql = "UPDATE meeting_participants 
                              SET status = 'offline', left_at = CURRENT_TIMESTAMP 
                              WHERE meeting_id = ? AND participant_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $room_id, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Add leave message only if not already added in the last minute
                $check_leave_msg_sql = "SELECT id FROM chat_messages 
                                       WHERE room_id = ? AND user_id = ? AND message_type = 'leave' 
                                       AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
                $check_leave_msg_stmt = $conn->prepare($check_leave_msg_sql);
                $check_leave_msg_stmt->bind_param("si", $room_id, $user_id);
                $check_leave_msg_stmt->execute();
                $check_leave_msg_result = $check_leave_msg_stmt->get_result();
                
                if ($check_leave_msg_result->num_rows == 0) {
                    $leave_message = "👋 " . $username . " left the meeting";
                    $leave_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, is_system_message, message_type) 
                                 VALUES (?, ?, ?, ?, 1, 'leave')";
                    $leave_stmt = $conn->prepare($leave_sql);
                    $leave_stmt->bind_param("siss", $room_id, $user_id, $username, $leave_message);
                    $leave_stmt->execute();
                    $leave_stmt->close();
                }
                $check_leave_msg_stmt->close();
            }
            
            // Also remove peer connection
            $delete_sql = "DELETE FROM peer_connections WHERE room_id = ? AND user_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("si", $room_id, $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Remove from muted users
            $delete_muted_sql = "DELETE FROM muted_users WHERE room_id = ? AND user_id = ?";
            $delete_muted_stmt = $conn->prepare($delete_muted_sql);
            $delete_muted_stmt->bind_param("si", $room_id, $user_id);
            $delete_muted_stmt->execute();
            $delete_muted_stmt->close();
            
            echo json_encode(['success' => true]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    
    if ($ajax_action === 'heartbeat') {
        try {
            // Update last_active timestamp
            $update_sql = "UPDATE meeting_participants 
                          SET last_active = NOW() 
                          WHERE meeting_id = ? AND participant_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $room_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Clean up stale participants
            $cleanup_sql = "UPDATE meeting_participants 
                           SET status = 'offline', left_at = NOW() 
                           WHERE meeting_id = ? 
                           AND status = 'online' 
                           AND last_active < DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
            $cleanup_stmt = $conn->prepare($cleanup_sql);
            $cleanup_stmt->bind_param("s", $room_id);
            $cleanup_stmt->execute();
            $cleanup_stmt->close();
            
            echo json_encode(['success' => true]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    
    if ($ajax_action === 'get_participants') {
        try {
            // First clean up stale participants
            $cleanup_sql = "UPDATE meeting_participants 
                           SET status = 'offline', left_at = NOW() 
                           WHERE meeting_id = ? 
                           AND status = 'online' 
                           AND last_active < DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
            $cleanup_stmt = $conn->prepare($cleanup_sql);
            $cleanup_stmt->bind_param("s", $room_id);
            $cleanup_stmt->execute();
            $cleanup_stmt->close();
            
            // Now get only online participants
            $participants_sql = "SELECT DISTINCT participant_id as user_id, username, status
                               FROM meeting_participants 
                               WHERE meeting_id = ? AND status = 'online'";
            $participants_stmt = $conn->prepare($participants_sql);
            $participants_stmt->bind_param("s", $room_id);
            $participants_stmt->execute();
            $participants_result = $participants_stmt->get_result();
            
            $participants = [];
            $online_user_ids = [];
            while ($row = $participants_result->fetch_assoc()) {
                $participants[] = [
                    'user_id' => (int)$row['user_id'],
                    'username' => $row['username'],
                    'status' => $row['status']
                ];
                $online_user_ids[] = $row['user_id'];
            }
            $participants_stmt->close();
            
            // Ensure host is always in the list if they're online
            $host_exists = false;
            foreach ($participants as $p) {
                if ($p['user_id'] == $host_id) {
                    $host_exists = true;
                    break;
                }
            }
            
            if (!$host_exists) {
                // Check if host is actually online via last_active
                $check_host_sql = "SELECT id FROM meeting_participants 
                                  WHERE meeting_id = ? AND participant_id = ? 
                                  AND last_active > DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
                $check_host_stmt = $conn->prepare($check_host_sql);
                $check_host_stmt->bind_param("si", $room_id, $host_id);
                $check_host_stmt->execute();
                $check_host_result = $check_host_stmt->get_result();
                
                if ($check_host_result->num_rows > 0) {
                    $participants[] = [
                        'user_id' => $host_id,
                        'username' => $host_username,
                        'status' => 'online'
                    ];
                    $online_user_ids[] = $host_id;
                }
                $check_host_stmt->close();
            }
            
            // Get muted users
            $muted_users = [];
            $muted_sql = "SELECT user_id FROM muted_users WHERE room_id = ?";
            $muted_stmt = $conn->prepare($muted_sql);
            $muted_stmt->bind_param("s", $room_id);
            $muted_stmt->execute();
            $muted_result = $muted_stmt->get_result();
            while ($row = $muted_result->fetch_assoc()) {
                $muted_users[] = (int)$row['user_id'];
            }
            $muted_stmt->close();
            
            // Get mic and camera status for all participants
            $device_status = [];
            $device_sql = "SELECT user_id, device_type, status FROM meeting_participant_settings WHERE room_id = ?";
            $device_stmt = $conn->prepare($device_sql);
            $device_stmt->bind_param("s", $room_id);
            $device_stmt->execute();
            $device_result = $device_stmt->get_result();
            while ($row = $device_result->fetch_assoc()) {
                if (!isset($device_status[$row['user_id']])) {
                    $device_status[$row['user_id']] = [];
                }
                $device_status[$row['user_id']][$row['device_type']] = (bool)$row['status'];
            }
            $device_stmt->close();
            
            echo json_encode([
                'success' => true, 
                'participants' => $participants,
                'online_user_ids' => $online_user_ids,
                'muted_users' => $muted_users,
                'device_status' => $device_status,
                'host_id' => $host_id
            ]);
            exit();
        } catch (Exception $e) {
            error_log("Get participants error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    
    if ($ajax_action === 'is_muted') {
        try {
            $check_muted_sql = "SELECT id FROM muted_users WHERE room_id = ? AND user_id = ?";
            $check_muted_stmt = $conn->prepare($check_muted_sql);
            $check_muted_stmt->bind_param("si", $room_id, $user_id);
            $check_muted_stmt->execute();
            $check_muted_result = $check_muted_stmt->get_result();
            $is_muted = $check_muted_result->num_rows > 0;
            $check_muted_stmt->close();
            
            echo json_encode(['success' => true, 'is_muted' => $is_muted]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    
    // Check if current user has been kicked
    if ($ajax_action === 'check_kicked') {
        try {
            $check_kicked_sql = "SELECT status FROM meeting_participants 
                               WHERE meeting_id = ? AND participant_id = ?";
            $check_kicked_stmt = $conn->prepare($check_kicked_sql);
            $check_kicked_stmt->bind_param("si", $room_id, $user_id);
            $check_kicked_stmt->execute();
            $check_kicked_result = $check_kicked_stmt->get_result();
            $status = 'online';
            if ($row = $check_kicked_result->fetch_assoc()) {
                $status = $row['status'];
            }
            $check_kicked_stmt->close();
            
            echo json_encode([
                'success' => true, 
                'is_kicked' => ($status === 'kicked')
            ]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
}

// Handle POST requests via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    $response = ['success' => false];
    
    // Handle PeerJS registration
    if (isset($_POST['action']) && $_POST['action'] === 'register_peer') {
        $peer_id = $_POST['peer_id'] ?? '';
        
        if (!empty($room_id) && !empty($peer_id)) {
            try {
                $sql = "INSERT INTO peer_connections (room_id, user_id, peer_id) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE peer_id = VALUES(peer_id), updated_at = CURRENT_TIMESTAMP";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sis", $room_id, $user_id, $peer_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                }
                $stmt->close();
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
            }
        }
    }
    
    // Handle leave room
    if (isset($_POST['action']) && $_POST['action'] === 'leave_room') {
        try {
            $sql = "DELETE FROM peer_connections WHERE room_id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $room_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $response['success'] = true;
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
    }
    
    // Handle text message
    if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
        $message = trim($_POST['message']);
        try {
            $insert_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, message_type) 
                           VALUES (?, ?, ?, ?, 'chat')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("siss", $room_id, $user_id, $username, $message);
            $insert_stmt->execute();
            $insert_stmt->close();
            $response['success'] = true;
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
    }
    
    // Handle file upload
    if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['chat_file'];
        $file_name = basename($file['name']);
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'mp4', 'mp3', 'pptx', 'xlsx', 'csv'];
        
        if (in_array($file_ext, $allowed_ext) && $file_size <= 25 * 1024 * 1024) {
            $unique_name = uniqid() . '_' . $file_name;
            $file_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $message = "Shared a file: $file_name";
                try {
                    $insert_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, file_path, file_name, file_size, message_type) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'file')";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("sissssi", $room_id, $user_id, $username, $message, $file_path, $file_name, $file_size);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    $response['success'] = true;
                } catch (Exception $e) {
                    $response['error'] = $e->getMessage();
                }
            }
        }
    }
    
    // Handle create task - only allow host
    if (isset($_POST['create_task']) && !empty(trim($_POST['task_text']))) {
        $is_host = ($host_id == $user_id);
        if ($is_host) {
            $task_text = trim($_POST['task_text']);
            try {
                $insert_sql = "INSERT INTO meeting_tasks (room_id, task_text, created_by, is_completed) 
                               VALUES (?, ?, ?, 0)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssi", $room_id, $task_text, $user_id);
                $insert_stmt->execute();
                $insert_stmt->close();
                $response['success'] = true;
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
            }
        } else {
            $response['error'] = 'Only the host can create tasks';
        }
    }
    
    // Handle edit task - only allow host
    if (isset($_POST['edit_task']) && !empty(trim($_POST['task_text'])) && !empty($_POST['task_id'])) {
        $is_host = ($host_id == $user_id);
        if ($is_host) {
            $task_id = intval($_POST['task_id']);
            $task_text = trim($_POST['task_text']);
            try {
                $update_sql = "UPDATE meeting_tasks 
                              SET task_text = ? 
                              WHERE id = ? AND room_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sis", $task_text, $task_id, $room_id);
                $update_stmt->execute();
                $update_stmt->close();
                $response['success'] = true;
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
            }
        } else {
            $response['error'] = 'Only the host can edit tasks';
        }
    }
    
    // Handle task toggle
    if (isset($_POST['toggle_task']) && !empty($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        try {
            $toggle_sql = "UPDATE meeting_tasks 
                          SET is_completed = NOT is_completed 
                          WHERE id = ? AND room_id = ?";
            $toggle_stmt = $conn->prepare($toggle_sql);
            $toggle_stmt->bind_param("is", $task_id, $room_id);
            $toggle_stmt->execute();
            $toggle_stmt->close();
            $response['success'] = true;
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
    }
    
    // Handle delete task - only allow host
    if (isset($_POST['delete_task']) && !empty($_POST['task_id'])) {
        $is_host = ($host_id == $user_id);
        if ($is_host) {
            $task_id = intval($_POST['task_id']);
            try {
                $delete_sql = "DELETE FROM meeting_tasks WHERE id = ? AND room_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("is", $task_id, $room_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                $response['success'] = true;
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
            }
        } else {
            $response['error'] = 'Only the host can delete tasks';
        }
    }
    
    // Handle kick participant - only host
    if (isset($_POST['kick_user']) && !empty($_POST['target_user_id'])) {
        $is_host = ($host_id == $user_id);
        if ($is_host) {
            $target_user_id = intval($_POST['target_user_id']);
            
            // Can't kick self or host
            if ($target_user_id != $user_id && $target_user_id != $host_id) {
                try {
                    // Get username of target user
                    $get_username_sql = "SELECT username FROM users WHERE id = ?";
                    $get_username_stmt = $conn->prepare($get_username_sql);
                    $get_username_stmt->bind_param("i", $target_user_id);
                    $get_username_stmt->execute();
                    $get_username_result = $get_username_stmt->get_result();
                    $target_username = 'User';
                    if ($row = $get_username_result->fetch_assoc()) {
                        $target_username = $row['username'];
                    }
                    $get_username_stmt->close();
                    
                    // Update participant status to kicked
                    $update_sql = "UPDATE meeting_participants 
                                  SET status = 'kicked', left_at = CURRENT_TIMESTAMP 
                                  WHERE meeting_id = ? AND participant_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("si", $room_id, $target_user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Insert kick signals into signaling table for the target user
                    for ($i = 0; $i < 5; $i++) {
                        $signal_sql = "INSERT INTO signaling (room_id, from_user_id, to_user_id, type, data) 
                                       VALUES (?, ?, ?, 'kick', 'kicked')";
                        $signal_stmt = $conn->prepare($signal_sql);
                        $signal_stmt->bind_param("sii", $room_id, $user_id, $target_user_id);
                        $signal_stmt->execute();
                        $signal_stmt->close();
                    }
                    
                    // Add kicked message only if not already added in the last minute
                    $check_kicked_msg_sql = "SELECT id FROM chat_messages 
                                           WHERE room_id = ? AND user_id = ? AND message_type = 'kick' 
                                           AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
                    $check_kicked_msg_stmt = $conn->prepare($check_kicked_msg_sql);
                    $check_kicked_msg_stmt->bind_param("si", $room_id, $target_user_id);
                    $check_kicked_msg_stmt->execute();
                    $check_kicked_msg_result = $check_kicked_msg_stmt->get_result();
                    
                    if ($check_kicked_msg_result->num_rows == 0) {
                        $kicked_message = "🚫 " . $target_username . " was removed from the meeting by the host";
                        $kicked_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, is_system_message, message_type) 
                                      VALUES (?, ?, ?, ?, 1, 'kick')";
                        $kicked_stmt = $conn->prepare($kicked_sql);
                        $kicked_stmt->bind_param("siss", $room_id, $host_id, $host_username, $kicked_message);
                        $kicked_stmt->execute();
                        $kicked_stmt->close();
                    }
                    $check_kicked_msg_stmt->close();
                    
                    // Remove peer connection
                    $delete_peer_sql = "DELETE FROM peer_connections WHERE room_id = ? AND user_id = ?";
                    $delete_peer_stmt = $conn->prepare($delete_peer_sql);
                    $delete_peer_stmt->bind_param("si", $room_id, $target_user_id);
                    $delete_peer_stmt->execute();
                    $delete_peer_stmt->close();
                    
                    // Remove from muted users
                    $delete_muted_sql = "DELETE FROM muted_users WHERE room_id = ? AND user_id = ?";
                    $delete_muted_stmt = $conn->prepare($delete_muted_sql);
                    $delete_muted_stmt->bind_param("si", $room_id, $target_user_id);
                    $delete_muted_stmt->execute();
                    $delete_muted_stmt->close();
                    
                    // Log host action
                    $insert_action_sql = "INSERT INTO host_actions (room_id, action, target_user_id, host_user_id) 
                                         VALUES (?, 'kick', ?, ?)";
                    $insert_action_stmt = $conn->prepare($insert_action_sql);
                    $insert_action_stmt->bind_param("sii", $room_id, $target_user_id, $user_id);
                    $insert_action_stmt->execute();
                    $insert_action_stmt->close();
                    
                    $response['success'] = true;
                } catch (Exception $e) {
                    $response['error'] = $e->getMessage();
                }
            } else {
                $response['error'] = 'Cannot kick yourself or the host';
            }
        } else {
            $response['error'] = 'Only the host can kick participants';
        }
    }
    
    // Handle mute participant - only host
    if (isset($_POST['mute_user']) && !empty($_POST['target_user_id'])) {
        $is_host = ($host_id == $user_id);
        if ($is_host) {
            $target_user_id = intval($_POST['target_user_id']);
            
            // Can't mute self or host
            if ($target_user_id != $user_id && $target_user_id != $host_id) {
                try {
                    // Get username of target user
                    $get_username_sql = "SELECT username FROM users WHERE id = ?";
                    $get_username_stmt = $conn->prepare($get_username_sql);
                    $get_username_stmt->bind_param("i", $target_user_id);
                    $get_username_stmt->execute();
                    $get_username_result = $get_username_stmt->get_result();
                    $target_username = 'User';
                    if ($row = $get_username_result->fetch_assoc()) {
                        $target_username = $row['username'];
                    }
                    $get_username_stmt->close();
                    
                    // Check if already muted
                    $check_muted_sql = "SELECT id FROM muted_users WHERE room_id = ? AND user_id = ?";
                    $check_muted_stmt = $conn->prepare($check_muted_sql);
                    $check_muted_stmt->bind_param("si", $room_id, $target_user_id);
                    $check_muted_stmt->execute();
                    $check_muted_result = $check_muted_stmt->get_result();
                    
                    if ($check_muted_result->num_rows == 0) {
                        // Add to muted users
                        $insert_muted_sql = "INSERT INTO muted_users (room_id, user_id, muted_by) 
                                           VALUES (?, ?, ?)";
                        $insert_muted_stmt = $conn->prepare($insert_muted_sql);
                        $insert_muted_stmt->bind_param("sii", $room_id, $target_user_id, $user_id);
                        $insert_muted_stmt->execute();
                        $insert_muted_stmt->close();
                        
                        // Log host action
                        $insert_action_sql = "INSERT INTO host_actions (room_id, action, target_user_id, host_user_id) 
                                             VALUES (?, 'mute', ?, ?)";
                        $insert_action_stmt = $conn->prepare($insert_action_sql);
                        $insert_action_stmt->bind_param("sii", $room_id, $target_user_id, $user_id);
                        $insert_action_stmt->execute();
                        $insert_action_stmt->close();
                        
                        // Also update participant's mic setting to off
                        $update_mic_sql = "INSERT INTO meeting_participant_settings (room_id, user_id, device_type, status) 
                                         VALUES (?, ?, 'mic', 0)
                                         ON DUPLICATE KEY UPDATE status = 0";
                        $update_mic_stmt = $conn->prepare($update_mic_sql);
                        $update_mic_stmt->bind_param("si", $room_id, $target_user_id);
                        $update_mic_stmt->execute();
                        $update_mic_stmt->close();
                        
                        // Add mute message to chat only if not already added in the last minute
                        $check_mute_msg_sql = "SELECT id FROM chat_messages 
                                             WHERE room_id = ? AND user_id = ? AND message_type = 'mute' 
                                             AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
                        $check_mute_msg_stmt = $conn->prepare($check_mute_msg_sql);
                        $check_mute_msg_stmt->bind_param("si", $room_id, $target_user_id);
                        $check_mute_msg_stmt->execute();
                        $check_mute_msg_result = $check_mute_msg_stmt->get_result();
                        
                        if ($check_mute_msg_result->num_rows == 0) {
                            $mute_message = "🔇 " . $target_username . " was muted by the host";
                            $mute_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, is_system_message, message_type) 
                                        VALUES (?, ?, ?, ?, 1, 'mute')";
                            $mute_stmt = $conn->prepare($mute_sql);
                            $mute_stmt->bind_param("siss", $room_id, $host_id, $host_username, $mute_message);
                            $mute_stmt->execute();
                            $mute_stmt->close();
                        }
                        $check_mute_msg_stmt->close();
                        
                        $response['success'] = true;
                    } else {
                        $response['error'] = 'User is already muted';
                    }
                    $check_muted_stmt->close();
                } catch (Exception $e) {
                    $response['error'] = $e->getMessage();
                }
            } else {
                $response['error'] = 'Cannot mute yourself or the host';
            }
        } else {
            $response['error'] = 'Only the host can mute participants';
        }
    }
    
    // Handle unmute participant - only host
    if (isset($_POST['unmute_user']) && !empty($_POST['target_user_id'])) {
        $is_host = ($host_id == $user_id);
        if ($is_host) {
            $target_user_id = intval($_POST['target_user_id']);
            
            try {
                // Get username of target user
                $get_username_sql = "SELECT username FROM users WHERE id = ?";
                $get_username_stmt = $conn->prepare($get_username_sql);
                $get_username_stmt->bind_param("i", $target_user_id);
                $get_username_stmt->execute();
                $get_username_result = $get_username_stmt->get_result();
                $target_username = 'User';
                if ($row = $get_username_result->fetch_assoc()) {
                    $target_username = $row['username'];
                }
                $get_username_stmt->close();
                
                // Remove from muted users
                $delete_muted_sql = "DELETE FROM muted_users WHERE room_id = ? AND user_id = ?";
                $delete_muted_stmt = $conn->prepare($delete_muted_sql);
                $delete_muted_stmt->bind_param("si", $room_id, $target_user_id);
                $delete_muted_stmt->execute();
                $delete_muted_stmt->close();
                
                // Log host action
                $insert_action_sql = "INSERT INTO host_actions (room_id, action, target_user_id, host_user_id) 
                                     VALUES (?, 'unmute', ?, ?)";
                $insert_action_stmt = $conn->prepare($insert_action_sql);
                $insert_action_stmt->bind_param("sii", $room_id, $target_user_id, $user_id);
                $insert_action_stmt->execute();
                $insert_action_stmt->close();
                
                // Add unmute message to chat only if not already added in the last minute
                $check_unmute_msg_sql = "SELECT id FROM chat_messages 
                                       WHERE room_id = ? AND user_id = ? AND message_type = 'unmute' 
                                       AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
                $check_unmute_msg_stmt = $conn->prepare($check_unmute_msg_sql);
                $check_unmute_msg_stmt->bind_param("si", $room_id, $target_user_id);
                $check_unmute_msg_stmt->execute();
                $check_unmute_msg_result = $check_unmute_msg_stmt->get_result();
                
                if ($check_unmute_msg_result->num_rows == 0) {
                    $unmute_message = "🔊 " . $target_username . " was unmuted by the host";
                    $unmute_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, is_system_message, message_type) 
                                  VALUES (?, ?, ?, ?, 1, 'unmute')";
                    $unmute_stmt = $conn->prepare($unmute_sql);
                    $unmute_stmt->bind_param("siss", $room_id, $host_id, $host_username, $unmute_message);
                    $unmute_stmt->execute();
                    $unmute_stmt->close();
                }
                $check_unmute_msg_stmt->close();
                
                $response['success'] = true;
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
            }
        } else {
            $response['error'] = 'Only the host can unmute participants';
        }
    }
    
    // Handle toggle mute (local)
    if (isset($_POST['toggle_mic'])) {
        $new_mic_status = $_POST['toggle_mic'] === 'on' ? 1 : 0;
        
        // Check if user is muted by host
        if (!$new_mic_status) {
            // User is muting themselves, remove from muted users
            try {
                $delete_muted_sql = "DELETE FROM muted_users WHERE room_id = ? AND user_id = ?";
                $delete_muted_stmt = $conn->prepare($delete_muted_sql);
                $delete_muted_stmt->bind_param("si", $room_id, $user_id);
                $delete_muted_stmt->execute();
                $delete_muted_stmt->close();
            } catch (Exception $e) {
                error_log("Error removing from muted: " . $e->getMessage());
            }
        } else {
            // User is trying to unmute themselves, check if they're muted by host
            try {
                $check_muted_sql = "SELECT id FROM muted_users WHERE room_id = ? AND user_id = ?";
                $check_muted_stmt = $conn->prepare($check_muted_sql);
                $check_muted_stmt->bind_param("si", $room_id, $user_id);
                $check_muted_stmt->execute();
                $check_muted_result = $check_muted_stmt->get_result();
                
                if ($check_muted_result->num_rows > 0) {
                    $response['error'] = 'You are muted by the host';
                    echo json_encode($response);
                    exit();
                }
                $check_muted_stmt->close();
            } catch (Exception $e) {
                error_log("Error checking muted: " . $e->getMessage());
            }
        }
        
        try {
            $save_sql = "INSERT INTO meeting_participant_settings (room_id, user_id, device_type, status) 
                        VALUES (?, ?, 'mic', ?)
                        ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP";
            $save_stmt = $conn->prepare($save_sql);
            $save_stmt->bind_param("sii", $room_id, $user_id, $new_mic_status);
            $save_stmt->execute();
            $save_stmt->close();
            $response['success'] = true;
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
    }
    
    // Handle toggle camera
    if (isset($_POST['toggle_camera'])) {
        $new_camera_status = $_POST['toggle_camera'] === 'on' ? 1 : 0;
        try {
            $save_sql = "INSERT INTO meeting_participant_settings (room_id, user_id, device_type, status) 
                        VALUES (?, ?, 'camera', ?)
                        ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP";
            $save_stmt = $conn->prepare($save_sql);
            $save_stmt->bind_param("sii", $room_id, $user_id, $new_camera_status);
            $save_stmt->execute();
            $save_stmt->close();
            $response['success'] = true;
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
    }
    
    echo json_encode($response);
    exit();
}

// Helper function for file size formatting
function formatFileSize($bytes) {
    if ($bytes === 0 || $bytes === null) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function getFileIcon($file_name) {
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $icons = [
        'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image', 
        'gif' => 'fa-file-image', 'pdf' => 'fa-file-pdf', 'doc' => 'fa-file-word', 
        'docx' => 'fa-file-word', 'txt' => 'fa-file-alt', 'zip' => 'fa-file-archive',
        'mp4' => 'fa-file-video', 'mp3' => 'fa-file-audio', 'pptx' => 'fa-file-powerpoint',
        'xlsx' => 'fa-file-excel', 'csv' => 'fa-file-excel'
    ];
    return $icons[$ext] ?? 'fa-file';
}

function getFilePreviewType($file_name) {
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) return 'image';
    if (in_array($ext, ['pdf'])) return 'pdf';
    if (in_array($ext, ['doc', 'docx', 'txt', 'pptx', 'xlsx', 'csv'])) return 'document';
    if (in_array($ext, ['mp4'])) return 'video';
    if (in_array($ext, ['mp3'])) return 'audio';
    return 'other';
}

// Get all users from database for participant mapping
$all_users = [];
try {
    $users_sql = "SELECT id, username FROM users";
    $users_result = $conn->query($users_sql);
    while ($row = $users_result->fetch_assoc()) {
        $all_users[$row['id']] = $row['username'];
    }
} catch (Exception $e) {
    error_log("Get users error: " . $e->getMessage());
}

// Get active participants 
$participants = [];
$participants_count = 0;
try {
    $participants_sql = "SELECT DISTINCT mp.participant_id as user_id, mp.username, mp.status
                        FROM meeting_participants mp
                        WHERE mp.meeting_id = ? AND mp.status = 'online'";
    $participants_stmt = $conn->prepare($participants_sql);
    $participants_stmt->bind_param("s", $room_id);
    $participants_stmt->execute();
    $participants_result = $participants_stmt->get_result();
    
    $participant_users = [];
    while ($row = $participants_result->fetch_assoc()) {
        // Use username from participants table, fallback to all_users if empty
        $display_username = !empty($row['username']) ? $row['username'] : ($all_users[$row['user_id']] ?? "User {$row['user_id']}");
        $participants[] = [
            'user_id' => $row['user_id'],
            'username' => $display_username,
            'is_speaking' => false
        ];
        $participant_users[$row['user_id']] = $display_username;
    }
    $participants_stmt->close();
    
    // Ensure host is always in the list if they're online
    $host_exists = false;
    foreach ($participants as $p) {
        if ($p['user_id'] == $host_id) {
            $host_exists = true;
            break;
        }
    }
    
    if (!$host_exists) {
        // Check if host is actually online
        $check_host_online_sql = "SELECT id FROM meeting_participants 
                                  WHERE meeting_id = ? AND participant_id = ? AND status = 'online'";
        $check_host_online_stmt = $conn->prepare($check_host_online_sql);
        $check_host_online_stmt->bind_param("si", $room_id, $host_id);
        $check_host_online_stmt->execute();
        $check_host_online_result = $check_host_online_stmt->get_result();
        
        if ($check_host_online_result->num_rows > 0) {
            $participants[] = [
                'user_id' => $host_id,
                'username' => $host_username,
                'is_speaking' => false
            ];
            $participant_users[$host_id] = $host_username;
        }
        $check_host_online_stmt->close();
    }
    
    $user_in_list = false;
    foreach ($participants as $p) {
        if ($p['user_id'] == $user_id) $user_in_list = true;
    }
    if (!$user_in_list) {
        $participants[] = [
            'user_id' => $user_id,
            'username' => $username,
            'is_speaking' => false
        ];
        $participant_users[$user_id] = $username;
    }
    
    $participants_count = count($participants);
} catch (Exception $e) {
    error_log("Get participants error: " . $e->getMessage());
}

// Get muted users
$muted_users = [];
try {
    $muted_sql = "SELECT user_id FROM muted_users WHERE room_id = ?";
    $muted_stmt = $conn->prepare($muted_sql);
    $muted_stmt->bind_param("s", $room_id);
    $muted_stmt->execute();
    $muted_result = $muted_stmt->get_result();
    while ($row = $muted_result->fetch_assoc()) {
        $muted_users[] = $row['user_id'];
    }
    $muted_stmt->close();
} catch (Exception $e) {
    error_log("Get muted users error: " . $e->getMessage());
}

// Get chat messages
$chat_messages = [];
try {
    $chat_sql = "SELECT cm.*, u.username as sender_name 
                 FROM chat_messages cm 
                 LEFT JOIN users u ON cm.user_id = u.id 
                 WHERE cm.room_id = ? 
                 ORDER BY cm.created_at DESC 
                 LIMIT 50";
    $chat_stmt = $conn->prepare($chat_sql);
    $chat_stmt->bind_param("s", $room_id);
    $chat_stmt->execute();
    $chat_result = $chat_stmt->get_result();
    $chat_messages = array_reverse($chat_result->fetch_all(MYSQLI_ASSOC));
    $chat_stmt->close();
} catch (Exception $e) {
    error_log("Get chat messages error: " . $e->getMessage());
}

// Get meeting tasks
$meeting_tasks = [];
try {
    $tasks_sql = "SELECT mt.*, u.username as created_by_name 
                  FROM meeting_tasks mt 
                  LEFT JOIN users u ON mt.created_by = u.id 
                  WHERE mt.room_id = ? 
                  ORDER BY mt.created_at ASC 
                  LIMIT 10";
    $tasks_stmt = $conn->prepare($tasks_sql);
    $tasks_stmt->bind_param("s", $room_id);
    $tasks_stmt->execute();
    $tasks_result = $tasks_stmt->get_result();
    $meeting_tasks = $tasks_result->fetch_all(MYSQLI_ASSOC);
    $tasks_stmt->close();
} catch (Exception $e) {
    error_log("Get meeting tasks error: " . $e->getMessage());
}

$is_host = ($host_id == $user_id);
$is_current_user_muted = in_array($user_id, $muted_users);

// Generate base URL for meeting
$baseUrl = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/meeting_room.php';
$urlWithRoom = $baseUrl . '?room=' . urlencode($room_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($meeting['title'] ?? 'Meeting Room'); ?> - SkyMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/peerjs/1.5.2/peerjs.min.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --dark-bg: #0f172a;
            --dark-surface: #1e293b;
            --dark-card: #334155;
            --dark-border: #475569;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --speaking-color: #3b82f6;
            --muted-color: #ef4444;
            --edit-color: #3b82f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            overflow: hidden;
        }

        /* Top Navigation */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--dark-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 25px;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
        }

        .logo i {
            color: var(--primary);
        }

        .room-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .room-code {
            background: var(--dark-card);
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid var(--dark-border);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .room-code:hover {
            background: var(--dark-border);
            transform: translateY(-2px);
        }

        .room-code .copied-tooltip {
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--success);
            color: white;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 100;
        }

        .room-code.copied .copied-tooltip {
            opacity: 1;
        }

        .room-code small {
            font-size: 10px;
            color: var(--warning);
            margin-left: 5px;
            opacity: 0.8;
        }

        .timer-display {
            background: var(--dark-card);
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid var(--dark-border);
            font-family: monospace;
            font-size: 16px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 16px;
        }

        .user-details {
            line-height: 1.4;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .muted-badge {
            background: var(--danger);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }

        .user-status {
            font-size: 12px;
            color: var(--success);
        }

        /* Control Buttons in Top Navigation */
        .nav-controls {
            display: flex;
            gap: 10px;
            margin-left: 15px;
        }

        .nav-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 1px solid var(--dark-border);
            background: var(--dark-card);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
            position: relative;
        }

        .nav-btn:hover {
            background: var(--dark-border);
            color: var(--text-primary);
            transform: translateY(-2px);
        }

        .nav-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .nav-btn.danger {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }

        .nav-btn .tooltip {
            position: absolute;
            bottom: -35px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 1000;
        }

        .nav-btn:hover .tooltip {
            opacity: 1;
        }

        /* Audio Device Selector */
        .audio-device-selector {
            position: relative;
        }

        .device-selector-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 8px;
            padding: 10px;
            min-width: 200px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1100;
            display: none;
        }

        .device-selector-dropdown.show {
            display: block;
        }

        .device-option-item {
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .device-option-item:hover {
            background: var(--dark-border);
        }

        .device-option-item.active {
            background: var(--primary);
            color: white;
        }

        .device-option-item .fa-check {
            opacity: 0;
            transition: opacity 0.3s;
        }

        .device-option-item.active .fa-check {
            opacity: 1;
        }

        /* PIP Window */
        .pip-window {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 200px;
            height: 150px;
            background: #000;
            border-radius: 10px;
            border: 3px solid var(--primary);
            overflow: hidden;
            z-index: 100;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            cursor: grab;
            display: none;
            resize: both;
            min-width: 150px;
            min-height: 120px;
            user-select: none;
            transition: none;
            touch-action: none; /* Prevents touch scrolling while dragging */
        }

        .pip-window.dragging {
            cursor: grabbing;
            opacity: 0.9;
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
            transition: none;
        }

        .pip-header {
            background: rgba(30, 41, 59, 0.95);
            padding: 8px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: grab;
            user-select: none;
            backdrop-filter: blur(5px);
            pointer-events: auto; /* Ensure buttons still work */
        }

        .pip-header:active {
            cursor: grabbing;
        }

        .pip-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            pointer-events: none;
        }

        .pip-switch-btn {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            background: var(--dark-border);
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.3s;
            pointer-events: auto; /* Ensure button works */
            position: relative;
            z-index: 102;
        }

        .pip-switch-btn:hover {
            background: var(--dark-card);
            color: var(--text-primary);
            transform: rotate(90deg);
        }

        .pip-content {
            width: 100%;
            height: calc(100% - 40px);
            position: relative;
            pointer-events: none; /* Prevent video from interfering with drag */
        }

        .pip-content video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            pointer-events: none;
        }

        /* Main Layout */
        .meeting-container {
            display: flex;
            height: 100vh;
            padding-top: 70px;
            background: var(--dark-bg);
            overflow: hidden;
        }

        /* Video Grid Area */
        .video-grid-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            background: var(--dark-surface);
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            height: calc(100vh - 110px);
        }

        .meeting-header {
            padding: 0 0 20px 0;
            border-bottom: 1px solid var(--dark-border);
            margin-bottom: 20px;
            flex-shrink: 0;
        }

        .meeting-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .meeting-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* Video Grid - Improved responsive layouts */
        .video-grid {
            display: grid;
            gap: 16px;
            flex: 1;
            min-height: 0;
            height: calc(100% - 100px);
            overflow-y: auto;
            padding: 8px;
            align-content: start;
        }

        /* Dynamic grid layouts based on participant count */
        .video-grid[data-participants="1"] {
            grid-template-columns: minmax(500px, 1700px);
            justify-content: center;
        }

        .video-grid[data-participants="2"],
        .video-grid[data-participants="3"],
        .video-grid[data-participants="4"] {
            grid-template-columns: repeat(2, 1fr);
        }

        .video-grid[data-participants="5"],
        .video-grid[data-participants="6"] {
            grid-template-columns: repeat(3, 1fr);
        }

        .video-grid[data-participants="7"],
        .video-grid[data-participants="8"],
        .video-grid[data-participants="9"],
        .video-grid[data-participants="10"],
        .video-grid[data-participants="11"],
        .video-grid[data-participants="12"] {
            grid-template-columns: repeat(4, 1fr);
        }

        .video-item {
            background: #2a2f3a;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            aspect-ratio: 16/9;
            border: 2px solid transparent;
            transition: all 0.3s;
            cursor: pointer;
            width: 100%;
            height: 100%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .video-item:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .video-item.speaking {
            border-color: var(--speaking-color);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
            animation: speaking-pulse 2s infinite;
        }

        @keyframes speaking-pulse {
            0% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
            50% { box-shadow: 0 0 30px rgba(59, 130, 246, 0.8); }
            100% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
        }

        .video-item.muted {
            border-color: var(--muted-color);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
        }

        .video-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Video placeholder styling */
        .video-item .video-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #334155, #1e293b);
            color: #94a3b8;
            font-size: 48px;
        }

        .video-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.9));
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 2;
        }

        .user-avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 12px;
            flex-shrink: 0;
        }

        .user-name {
            flex: 1;
            font-weight: 500;
            color: white;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .host-badge {
            background: var(--warning);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }

        .muted-badge-small {
            background: var(--danger);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }

        .mic-status {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.6);
            color: var(--success);
            font-size: 12px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .mic-status.muted {
            color: var(--danger);
        }

        .mic-status.speaking {
            color: var(--speaking-color);
            animation: mic-pulse 1s infinite;
        }

        @keyframes mic-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Fullscreen Mode */
        .video-item.fullscreen {
            position: fixed;
            top: 70px;
            left: 0;
            width: 100%;
            height: calc(100vh - 70px);
            z-index: 999;
            aspect-ratio: auto;
            border-radius: 0;
            border: none;
            cursor: default;
        }

        .video-item.fullscreen:hover {
            transform: none;
            border-color: transparent;
        }

        .video-item.fullscreen .video-info {
            font-size: 16px;
            padding: 20px;
        }

        .video-item.fullscreen .user-avatar-small {
            width: 48px;
            height: 48px;
            font-size: 18px;
        }

        .video-item.fullscreen .mic-status {
            width: 40px;
            height: 40px;
            font-size: 16px;
        }

        .fullscreen-exit-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            font-size: 20px;
            transition: all 0.3s;
            opacity: 0;
        }

        .video-item.fullscreen:hover .fullscreen-exit-btn {
            opacity: 1;
        }

        .fullscreen-exit-btn:hover {
            background: var(--danger);
            transform: scale(1.1);
        }

        /* Context Menu for Kick/Mute */
        .video-context-menu {
            position: fixed;
            background: var(--dark-surface);
            border: 1px solid var(--dark-border);
            border-radius: 8px;
            padding: 5px 0;
            min-width: 150px;
            z-index: 2000;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
            display: none;
        }

        .video-context-menu.show {
            display: block;
        }

        .context-menu-item {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            cursor: pointer;
            transition: background 0.3s;
            font-size: 13px;
        }

        .context-menu-item:hover {
            background: var(--dark-border);
        }

        .context-menu-item.kick {
            color: var(--danger);
        }

        .context-menu-item.mute {
            color: var(--warning);
        }

        .context-menu-item.unmute {
            color: var(--success);
        }

        .context-menu-item i {
            width: 16px;
            font-size: 14px;
        }

        .context-menu-divider {
            height: 1px;
            background: var(--dark-border);
            margin: 5px 0;
        }

        /* Scrollbar for video grid */
        .video-grid::-webkit-scrollbar {
            width: 6px;
        }

        .video-grid::-webkit-scrollbar-track {
            background: var(--dark-border);
            border-radius: 3px;
        }

        .video-grid::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

        .video-grid::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Right Panel */
        .right-panel {
            width: 400px;
            display: flex;
            flex-direction: column;
            background: var(--dark-surface);
            border-radius: 15px;
            margin: 20px 20px 20px 0;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            height: calc(100vh - 110px);
            overflow: hidden;
        }

        /* Chat Section */
        .chat-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 50%;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid var(--dark-border);
            flex-shrink: 0;
        }

        .chat-header h3 {
            font-size: 16px;
            color: var(--text-primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            min-height: 0;
        }

        .message {
            display: flex;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.own {
            flex-direction: row-reverse;
        }

        .message.system {
            justify-content: center;
            opacity: 0.8;
        }

        .message.system .message-bubble {
            background: rgba(100, 116, 139, 0.2);
            backdrop-filter: blur(10px);
            border: 1px dashed var(--dark-border);
            font-style: italic;
            text-align: center;
            color: var(--text-secondary);
        }

        .message-content {
            max-width: 70%;
        }

        .message-bubble {
            background: var(--dark-card);
            padding: 12px 15px;
            border-radius: 15px;
            word-wrap: break-word;
            position: relative;
        }

        .message.own .message-bubble {
            background: var(--primary);
            color: white;
        }

        .message.system .message-bubble {
            background: rgba(100, 116, 139, 0.2);
            color: var(--text-secondary);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .message-sender {
            font-weight: 600;
        }

        .message-time {
            font-size: 10px;
            opacity: 0.8;
        }

        /* File Preview in Chat */
        .file-preview-chat {
            margin-top: 10px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--dark-border);
            background: rgba(0, 0, 0, 0.2);
            max-width: 100%;
        }

        .file-preview-chat img {
            width: 100%;
            max-height: 200px;
            object-fit: contain;
            cursor: pointer;
        }

        .file-preview-chat.pdf iframe {
            width: 100%;
            height: 300px;
            border: none;
        }

        .file-preview-chat.document {
            padding: 15px;
            text-align: center;
        }

        .file-preview-chat.document .document-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .file-preview-chat.other {
            padding: 15px;
            text-align: center;
        }

        .file-download-btn {
            margin-top: 10px;
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 12px;
        }

        .file-download-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* File Preview Before Send */
        .file-preview-send {
            margin: 10px 0;
            padding: 15px;
            background: var(--dark-card);
            border-radius: 8px;
            border: 2px dashed var(--dark-border);
            display: flex;
            align-items: center;
            gap: 15px;
            display: none;
        }

        .file-preview-send.show {
            display: flex;
        }

        .file-preview-icon {
            font-size: 32px;
            color: var(--primary);
        }

        .file-preview-info {
            flex: 1;
        }

        .file-preview-name {
            font-weight: 500;
            margin-bottom: 5px;
            word-break: break-all;
            font-size: 14px;
        }

        .file-preview-size {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .file-remove-btn {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-remove-btn:hover {
            background: #c82333;
            transform: rotate(90deg);
        }

        /* Chat Input */
        .chat-input-container {
            padding: 20px;
            border-top: 1px solid var(--dark-border);
            flex-shrink: 0;
            background: var(--dark-surface);
        }

        .chat-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .chat-input-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-input {
            padding: 12px 20px;
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 25px;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            width: 100%;
        }

        .chat-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-send {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .btn-send:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .btn-attachment {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            background: var(--dark-card);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .btn-attachment:hover {
            background: var(--dark-border);
            color: var(--text-primary);
        }

        .file-upload-input {
            display: none;
        }

        /* Tasks Section */
        .tasks-section {
            padding: 20px;
            border-top: 1px solid var(--dark-border);
            flex-shrink: 0;
            height: 50%;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-shrink: 0;
        }

        .tasks-header h3 {
            font-size: 16px;
            color: var(--text-primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tasks-actions {
            display: flex;
            gap: 10px;
        }

        .add-task-btn, .delete-task-btn, .edit-task-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s;
        }

        .edit-task-btn {
            background: var(--edit-color);
            color: white;
        }

        .edit-task-btn:hover {
            background: #2563eb;
            transform: scale(1.1);
        }

        .edit-task-btn:disabled {
            background: var(--dark-border);
            cursor: not-allowed;
            transform: none;
            opacity: 0.5;
        }

        .add-task-btn {
            background: <?php echo $is_host ? 'var(--primary)' : 'var(--dark-border)'; ?>;
            color: white;
            cursor: <?php echo $is_host ? 'pointer' : 'not-allowed'; ?>;
            opacity: <?php echo $is_host ? '1' : '0.5'; ?>;
        }

        .add-task-btn:hover {
            background: <?php echo $is_host ? 'var(--primary-dark)' : 'var(--dark-border)'; ?>;
            transform: <?php echo $is_host ? 'scale(1.1)' : 'none'; ?>;
        }

        .delete-task-btn {
            background: var(--danger);
            color: white;
        }

        .delete-task-btn:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .delete-task-btn:disabled {
            background: var(--dark-border);
            cursor: not-allowed;
            transform: none;
            opacity: 0.5;
        }

        .tasks-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow-y: auto;
            padding-right: 5px;
            flex: 1;
            min-height: 0;
        }

        .tasks-list::-webkit-scrollbar {
            width: 6px;
        }

        .tasks-list::-webkit-scrollbar-track {
            background: var(--dark-border);
            border-radius: 3px;
        }

        .tasks-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

        .tasks-list::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        .task-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: var(--dark-card);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .task-item:hover {
            background: var(--dark-border);
            transform: translateX(5px);
        }

        .task-item.selected {
            background: rgba(102, 126, 234, 0.2);
            border: 2px solid var(--primary);
        }

        .task-checkbox {
            width: 18px;
            height: 18px;
            border: 2px solid var(--dark-border);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .task-checkbox.checked {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .task-text {
            flex: 1;
            color: var(--text-primary);
            font-size: 14px;
            word-break: break-word;
        }

        .task-checkbox.checked + .task-text {
            text-decoration: line-through;
            color: var(--text-secondary);
        }

        .task-created-by {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 4px;
            font-style: italic;
        }

        /* Participants Modal */
        .participants-modal {
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
            backdrop-filter: blur(5px);
        }

        .participants-modal.show {
            display: flex;
        }

        .participants-modal-content {
            background: var(--dark-surface);
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .participants-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .participants-modal-header h3 {
            font-size: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: var(--text-primary);
        }

        .participants-modal-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .participant-modal-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            border-radius: 8px;
            background: var(--dark-card);
            position: relative;
        }

        .participant-modal-item.you {
            background: rgba(102, 126, 234, 0.2);
            border-left: 4px solid var(--primary);
        }

        .participant-modal-item.host {
            background: rgba(245, 158, 11, 0.2);
            border-left: 4px solid var(--warning);
        }

        .participant-modal-item.muted {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--danger);
        }

        .participant-modal-item .host-badge {
            background: var(--warning);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 8px;
        }

        .participant-modal-item .muted-badge {
            background: var(--danger);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 8px;
        }

        .participant-modal-name {
            flex: 1;
            font-weight: 500;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        .participant-modal-actions {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--dark-border);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s;
        }

        .action-btn:hover {
            background: var(--dark-card);
            color: var(--text-primary);
            transform: scale(1.1);
        }

        .action-btn.mute {
            color: var(--danger);
        }

        .action-btn.mute:hover {
            background: var(--danger);
            color: white;
        }

        .action-btn.unmute {
            color: var(--success);
        }

        .action-btn.unmute:hover {
            background: var(--success);
            color: white;
        }

        .action-btn.kick {
            color: var(--danger);
        }

        .action-btn.kick:hover {
            background: var(--danger);
            color: white;
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .action-btn:disabled:hover {
            background: var(--dark-border);
            color: var(--text-secondary);
            transform: none;
        }

        .participant-modal-status-text {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 4px;
            background: var(--dark-border);
            margin-right: 10px;
        }

        /* File Preview Modal */
        .file-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .file-preview-modal.show {
            display: flex;
        }

        .file-preview-modal-content {
            background: var(--dark-surface);
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--dark-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-preview-modal-body {
            height: 70vh;
            overflow: auto;
            padding: 20px;
            text-align: center;
        }

        .file-preview-modal-body img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .file-preview-modal-body iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Add Task Modal */
        .add-task-modal {
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
            backdrop-filter: blur(5px);
        }

        .add-task-modal.show {
            display: flex;
        }

        .add-task-modal-content {
            background: var(--dark-surface);
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        /* Edit Task Modal */
        .edit-task-modal {
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
            backdrop-filter: blur(5px);
        }

        .edit-task-modal.show {
            display: flex;
        }

        .edit-task-modal-content {
            background: var(--dark-surface);
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        .task-input {
            width: 100%;
            padding: 12px 15px;
            background: var(--dark-card);
            border: 2px solid var(--dark-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
            resize: vertical;
            min-height: 100px;
        }

        .task-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Meeting Details Modal */
        .meeting-details-modal {
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
            backdrop-filter: blur(5px);
        }

        .meeting-details-modal.show {
            display: flex;
        }

        .meeting-details-modal-content {
            background: var(--dark-surface);
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        .modal-body {
            padding: 30px;
        }

        .detail-section {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .detail-section.password-section {
            background: rgba(245, 158, 11, 0.1);
            border: 2px solid var(--warning);
        }

        .detail-label {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-label i {
            color: var(--primary);
        }

        .password-section .detail-label i {
            color: var(--warning);
        }

        .password-hint {
            font-size: 11px;
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 12px;
            color: var(--warning);
        }

        .detail-value {
            background: var(--dark-surface);
            border-radius: 8px;
            padding: 15px;
            font-size: 14px;
            word-break: break-all;
            border: 1px solid var(--dark-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .detail-text {
            flex: 1;
            font-family: monospace;
            font-size: 14px;
            color: var(--text-primary);
        }

        .detail-actions {
            display: flex;
            gap: 8px;
        }

        .password-value {
            background: var(--dark-surface);
            border-color: var(--warning);
            position: relative;
        }

        .password-text {
            font-family: monospace;
            font-size: 16px;
            letter-spacing: 2px;
            flex: 1;
            color: var(--warning);
            font-weight: 600;
        }

        .password-actions {
            display: flex;
            gap: 8px;
        }

        .toggle-password-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--dark-border);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s;
        }

        .toggle-password-btn:hover {
            background: var(--dark-card);
            color: var(--text-primary);
            transform: scale(1.1);
        }

        .copy-detail-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--dark-border);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s;
        }

        .copy-detail-btn:hover {
            background: var(--dark-card);
            color: var(--text-primary);
            transform: scale(1.1);
        }

        .password-warning {
            margin-top: 15px;
            padding: 12px;
            background: rgba(245, 158, 11, 0.1);
            border-radius: 6px;
            color: var(--warning);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Notification Container */
        .notification-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }

        .notification {
            padding: 15px 25px;
            background: var(--dark-card);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.3s ease;
            position: relative;
        }

        .notification.success {
            border-left-color: var(--success);
        }

        .notification.error {
            border-left-color: var(--danger);
        }

        .notification.warning {
            border-left-color: var(--warning);
        }

        .notification.info {
            border-left-color: var(--primary);
        }

        .notification .icon {
            font-size: 20px;
        }

        .notification.success .icon {
            color: var(--success);
        }

        .notification.error .icon {
            color: var(--danger);
        }

        .notification.warning .icon {
            color: var(--warning);
        }

        .notification.info .icon {
            color: var(--primary);
        }

        .notification .message {
            flex: 1;
            font-size: 14px;
        }

        .notification .close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 14px;
            padding: 5px;
            transition: color 0.3s;
        }

        .notification .close-btn:hover {
            color: var(--text-primary);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--dark-border);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .video-grid[data-participants="1"] {
                grid-template-columns: minmax(400px, 600px);
            }
            
            .video-grid[data-participants="2"],
            .video-grid[data-participants="3"],
            .video-grid[data-participants="4"] {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .video-grid[data-participants="5"],
            .video-grid[data-participants="6"] {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .video-grid[data-participants="7"],
            .video-grid[data-participants="8"],
            .video-grid[data-participants="9"],
            .video-grid[data-participants="10"],
            .video-grid[data-participants="11"],
            .video-grid[data-participants="12"] {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .video-grid[data-participants="1"] {
                grid-template-columns: minmax(300px, 400px);
            }
            
            .video-grid[data-participants="2"],
            .video-grid[data-participants="3"],
            .video-grid[data-participants="4"],
            .video-grid[data-participants="5"],
            .video-grid[data-participants="6"],
            .video-grid[data-participants="7"],
            .video-grid[data-participants="8"],
            .video-grid[data-participants="9"],
            .video-grid[data-participants="10"],
            .video-grid[data-participants="11"],
            .video-grid[data-participants="12"] {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav" id="main-nav">
        <div class="nav-left">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-video"></i>
                <span>SkyMeet</span>
            </a>
            <div class="room-info">
                <div class="room-code" id="show-meeting-details">
                    <i class="fas fa-hashtag"></i>
                    <span><?php echo htmlspecialchars($room_id); ?></span>
                    <?php if ($is_password_protected): ?>
                    <i class="fas fa-lock" style="color: var(--warning);"></i>
                    <?php endif; ?>
                    <i class="fas fa-info-circle"></i>
                    <?php if ($is_password_protected): ?>
                    <small>with password</small>
                    <?php endif; ?>
                </div>
                <div class="timer-display" id="timer-display">00:00:00</div>
            </div>
        </div>
        
        <div class="nav-right">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($username, 0, 2)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name">
                        <?php echo htmlspecialchars($username); ?>
                        <?php if ($is_host): ?>
                        <span class="host-badge">Host</span>
                        <?php endif; ?>
                        <?php if ($is_current_user_muted): ?>
                        <span class="muted-badge">Muted</span>
                        <?php endif; ?>
                    </div>
                    <div class="user-status">Online</div>
                </div>
            </div>
            
            <!-- Control Buttons in top navigation -->
            <div class="nav-controls">
                <button class="nav-btn <?php echo $mic_status && !$is_current_user_muted ? 'active' : ''; ?>" id="toggle-mic" title="Toggle Microphone" <?php echo $is_current_user_muted ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                    <i class="fas <?php echo ($mic_status && !$is_current_user_muted) ? 'fa-microphone' : 'fa-microphone-slash'; ?>"></i>
                    <span class="tooltip"><?php echo ($mic_status && !$is_current_user_muted) ? 'Mute' : 'Unmute'; ?></span>
                </button>
                
                <button class="nav-btn <?php echo $camera_status ? 'active' : ''; ?>" id="toggle-camera" title="Toggle Camera">
                    <i class="fas <?php echo $camera_status ? 'fa-video' : 'fa-video-slash'; ?>"></i>
                    <span class="tooltip"><?php echo $camera_status ? 'Turn Off Camera' : 'Turn On Camera'; ?></span>
                </button>
                
                <button class="nav-btn" id="share-screen" title="Share Screen">
                    <i class="fas fa-desktop"></i>
                    <span class="tooltip">Share Screen</span>
                </button>
                
                <div class="audio-device-selector">
                    <button class="nav-btn" id="audio-output-control" title="Audio Output">
                        <i class="fas fa-volume-up"></i>
                        <span class="tooltip">Audio Output</span>
                    </button>
                    <div class="device-selector-dropdown" id="audio-device-selector">
                        <!-- Audio devices will be dynamically populated -->
                    </div>
                </div>
                
                <button class="nav-btn" id="show-participants" title="Participants">
                    <i class="fas fa-users"></i>
                    <span class="tooltip">Participants</span>
                </button>
                
                <button class="nav-btn danger" id="leave-meeting" title="Leave Meeting">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="tooltip">Leave Meeting</span>
                </button>
            </div>
        </div>
    </div>

    <!-- PIP Window - Fully Draggable -->
    <div class="pip-window" id="pip-window">
        <div class="pip-header" id="pip-header">
            <div class="pip-title" id="pip-title">
                <i class="fas fa-video"></i>
                <span>Camera</span>
            </div>
            <button class="pip-switch-btn" id="pip-switch-btn" title="Switch with Main Window">
                <i class="fas fa-exchange-alt"></i>
            </button>
        </div>
        <div class="pip-content" id="pip-content">
            <video id="pip-video" autoplay playsinline muted></video>
        </div>
    </div>

    <!-- Notification Container -->
    <div class="notification-container" id="notification-container"></div>

    <!-- Main Container -->
    <div class="meeting-container" id="meeting-container">
        <!-- Video Grid Area -->
        <div class="video-grid-area">
            <div class="meeting-header">
                <div class="meeting-title">
                    <i class="fas fa-video"></i>
                    <?php echo htmlspecialchars($meeting['title'] ?? 'Meeting Room'); ?>
                    <?php if ($is_password_protected): ?>
                    <span class="host-badge" style="font-size: 12px;"><i class="fas fa-lock"></i> Password Protected</span>
                    <?php endif; ?>
                </div>
                <div class="meeting-subtitle">
                    <span><i class="fas fa-user"></i> Host: <span id="host-username"><?php echo htmlspecialchars($meeting['host_name'] ?? $username); ?></span></span>
                    <span><i class="fas fa-hashtag"></i> Room: <?php echo htmlspecialchars($room_id); ?></span>
                    <span><i class="fas fa-users"></i> <span id="participant-count"><?php echo $participants_count; ?></span> participants</span>
                </div>
            </div>

            <!-- Video Grid -->
            <div class="video-grid" id="video-grid" data-participants="<?php echo $participants_count; ?>">
                <!-- Videos will be dynamically added here -->
            </div>
        </div>

        <!-- Right Panel -->
        <div class="right-panel">
            <!-- Chat Section -->
            <div class="chat-section">
                <div class="chat-header">
                    <h3><i class="fas fa-comments"></i> Chat</h3>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <?php if (empty($chat_messages)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>No messages yet. Start the conversation!</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($chat_messages as $msg): ?>
                    <div class="message <?php 
                        echo $msg['user_id'] == $user_id ? 'own' : ''; 
                        echo isset($msg['is_system_message']) && $msg['is_system_message'] ? ' system' : '';
                    ?>">
                        <?php if (!isset($msg['is_system_message']) || !$msg['is_system_message']): ?>
                        <div class="user-avatar-small">
                            <?php 
                            $sender_name = $msg['sender_name'] ?? $msg['username'] ?? 'User';
                            echo strtoupper(substr($sender_name, 0, 2)); 
                            ?>
                        </div>
                        <?php endif; ?>
                        <div class="message-content">
                            <?php if (!isset($msg['is_system_message']) || !$msg['is_system_message']): ?>
                            <div class="message-header">
                                <span class="message-sender">
                                    <?php echo ($msg['user_id'] == $user_id) ? 'You' : htmlspecialchars($sender_name); ?>
                                </span>
                                <span class="message-time">
                                    <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="message-bubble">
                                <?php echo htmlspecialchars($msg['message']); ?>
                                
                                <?php if (!empty($msg['file_name']) && !empty($msg['file_path'])): ?>
                                <?php 
                                $preview_type = getFilePreviewType($msg['file_name']);
                                $file_icon = getFileIcon($msg['file_name']);
                                ?>
                                <div class="file-preview-chat <?php echo $preview_type; ?>">
                                    <?php if ($preview_type === 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($msg['file_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($msg['file_name']); ?>"
                                         onclick="openFilePreview('<?php echo htmlspecialchars($msg['file_path']); ?>', '<?php echo htmlspecialchars($msg['file_name']); ?>')">
                                    <?php elseif ($preview_type === 'pdf'): ?>
                                    <iframe src="<?php echo htmlspecialchars($msg['file_path']); ?>#view=fitH"></iframe>
                                    <?php else: ?>
                                    <div class="document-icon">
                                        <i class="fas <?php echo $file_icon; ?>"></i>
                                    </div>
                                    <div style="margin-bottom: 10px;"><?php echo htmlspecialchars($msg['file_name']); ?></div>
                                    <?php endif; ?>
                                    
                                    <button class="file-download-btn" 
                                            onclick="downloadFile('<?php echo htmlspecialchars($msg['file_path']); ?>', '<?php echo htmlspecialchars($msg['file_name']); ?>')">
                                        <i class="fas fa-download"></i> Download (<?php echo formatFileSize($msg['file_size'] ?? 0); ?>)
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="chat-input-container">
                    <form method="POST" class="chat-input-wrapper" id="chat-form" enctype="multipart/form-data">
                        <input type="file" name="chat_file" id="file-upload" class="file-upload-input" 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip,.mp4,.mp3,.pptx,.xlsx,.csv">
                        <label for="file-upload" class="btn-attachment" title="Attach File">
                            <i class="fas fa-paperclip"></i>
                        </label>
                        <div class="chat-input-area">
                            <div class="file-preview-send" id="file-preview-send">
                                <div class="file-preview-icon">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="file-preview-info">
                                    <div class="file-preview-name" id="file-preview-name"></div>
                                    <div class="file-preview-size" id="file-preview-size"></div>
                                </div>
                                <button type="button" class="file-remove-btn" id="file-remove-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <input type="text" class="chat-input" id="chat-input" name="message" placeholder="Type message here..." autocomplete="off">
                        </div>
                        <button type="submit" class="btn-send" title="Send Message">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tasks Section -->
            <div class="tasks-section">
                <div class="tasks-header">
                    <h3><i class="fas fa-tasks"></i> Tasks List</h3>
                    <div class="tasks-actions">
                        <!-- Edit Task Button -->
                        <button class="edit-task-btn" id="edit-task-btn" disabled title="Edit Selected Task">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="delete-task-btn" id="delete-task-btn" disabled title="Delete Selected Task">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="add-task-btn" id="add-task-btn" title="<?php echo $is_host ? 'Add New Task' : 'Only host can add tasks'; ?>" <?php echo $is_host ? '' : 'disabled'; ?>>
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="tasks-list" id="tasks-list">
                    <?php if (empty($meeting_tasks)): ?>
                    <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                        <i class="fas fa-clipboard-list" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No tasks yet. Add your first task!</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($meeting_tasks as $task): ?>
                    <div class="task-item" data-task-id="<?php echo $task['id']; ?>" onclick="selectTask(this, <?php echo $task['id']; ?>)">
                        <div class="task-checkbox <?php echo $task['is_completed'] ? 'checked' : ''; ?>" onclick="event.stopPropagation(); toggleTask(this, <?php echo $task['id']; ?>)">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="task-text">
                            <?php echo htmlspecialchars($task['task_text']); ?>
                            <div class="task-created-by">
                                Added by: <?php echo htmlspecialchars($task['created_by_name'] ?? 'Unknown'); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Participants Modal -->
    <div class="participants-modal" id="participants-modal">
        <div class="participants-modal-content">
            <div class="participants-modal-header">
                <h3><i class="fas fa-users"></i> Participants (<span id="modal-participant-count"><?php echo $participants_count; ?></span>)</h3>
                <button class="close-modal" id="close-participants-modal">&times;</button>
            </div>
            <div class="participants-modal-list" id="participants-modal-list">
                <!-- Participants will be dynamically loaded here -->
            </div>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div class="file-preview-modal" id="file-preview-modal">
        <div class="file-preview-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file"></i> <span id="file-preview-title"></span></h3>
                <button class="close-modal" id="close-file-preview-modal">&times;</button>
            </div>
            <div class="file-preview-modal-body" id="file-preview-modal-body">
                <!-- File preview will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div class="add-task-modal" id="add-task-modal">
        <div class="add-task-modal-content">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h3><i class="fas fa-plus-circle"></i> Add New Task</h3>
                <button class="close-modal" id="close-add-task-modal">&times;</button>
            </div>
            <form id="add-task-form" method="POST">
                <input type="hidden" name="create_task" value="1">
                <div class="modal-body">
                    <textarea class="task-input" name="task_text" placeholder="Enter task description..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('add-task-modal').classList.remove('show')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Add Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="edit-task-modal" id="edit-task-modal">
        <div class="edit-task-modal-content">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h3><i class="fas fa-edit"></i> Edit Task</h3>
                <button class="close-modal" id="close-edit-task-modal">&times;</button>
            </div>
            <form id="edit-task-form" method="POST">
                <input type="hidden" name="edit_task" value="1">
                <input type="hidden" name="task_id" id="edit-task-id" value="">
                <div class="modal-body">
                    <textarea class="task-input" name="task_text" id="edit-task-text" placeholder="Enter task description..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('edit-task-modal').classList.remove('show')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Meeting Details Modal -->
    <div class="meeting-details-modal" id="meeting-details-modal">
        <div class="meeting-details-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Meeting Details</h3>
                <button class="close-modal" id="close-meeting-details-modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Meeting Room ID -->
                <div class="detail-section">
                    <div class="detail-label">
                        <i class="fas fa-hashtag"></i> Meeting Room ID:
                    </div>
                    <div class="detail-value" id="detail-room-id">
                        <span class="detail-text"><?php echo htmlspecialchars($room_id); ?></span>
                        <div class="detail-actions">
                            <button class="copy-detail-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($room_id); ?>', 'Room ID copied!')" title="Copy Room ID">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Meeting URL -->
                <div class="detail-section">
                    <div class="detail-label">
                        <i class="fas fa-link"></i> Meeting URL:
                    </div>
                    <div class="detail-value" id="detail-meeting-url">
                        <span class="detail-text"><?php echo htmlspecialchars($urlWithRoom); ?></span>
                        <div class="detail-actions">
                            <button class="copy-detail-btn" onclick="copyToClipboard('<?php echo $urlWithRoom; ?>', 'Meeting URL copied!')" title="Copy Meeting URL">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Meeting Password - Only show if password protected -->
                <?php if ($is_password_protected && !empty($original_password)): ?>
                <div class="detail-section password-section">
                    <div class="detail-label">
                        <i class="fas fa-lock"></i> Meeting Password:
                        <span class="password-hint">(Share with participants)</span>
                    </div>
                    <div class="detail-value password-value" id="detail-password">
                        <span class="password-text" id="password-display">••••••••</span>
                        <div class="password-actions">
                            <button class="toggle-password-btn" id="toggle-password-visibility" onclick="togglePasswordVisibility()" title="<?php echo $is_host ? 'Show/Hide Password' : 'Toggle visibility'; ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="copy-detail-btn" onclick="copyToClipboard('<?php echo addslashes($original_password); ?>', 'Password copied!')" title="Copy Password">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="password-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <?php if ($is_host): ?>
                        Keep this password secure. Share it only with meeting participants.
                        <?php else: ?>
                        Password is visible only to you. Do not share with others.
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Meeting Created Date -->
                <div class="detail-section">
                    <div class="detail-label">
                        <i class="fas fa-calendar-alt"></i> Meeting Created:
                    </div>
                    <div class="detail-value">
                        <span class="detail-text"><?php echo date('F j, Y, g:i a', strtotime($meeting['created_at'] ?? date('Y-m-d H:i:s'))); ?></span>
                    </div>
                </div>
                
                <!-- Host Information -->
                <div class="detail-section">
                    <div class="detail-label">
                        <i class="fas fa-user"></i> Host:
                    </div>
                    <div class="detail-value">
                        <span class="detail-text">
                            <?php echo htmlspecialchars($host_username); ?>
                            <?php if ($is_host): ?>
                            <span style="color: var(--warning); margin-left: 10px;">(You)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // COMPLETE WORKING WEBRTC WITH PEERJS AND SIGNALING 
        // Global Variables
        let localStream = null;
        let screenStream = null;
        let isMicOn = <?php echo $mic_status ? 'true' : 'false'; ?>;
        let isCameraOn = <?php echo $camera_status ? 'true' : 'false'; ?>;
        let isScreenSharing = false;
        let isMutedByHost = <?php echo $is_current_user_muted ? 'true' : 'false'; ?>;
        let currentUserId = <?php echo $user_id; ?>;
        let currentUsername = '<?php echo addslashes($username); ?>';
        let hostUserId = <?php echo $host_id; ?>;
        let hostUsername = '<?php echo addslashes($host_username); ?>';
        let roomId = '<?php echo $room_id; ?>';
        let isHost = <?php echo $is_host ? 'true' : 'false'; ?>;
        let isPasswordProtected = <?php echo $is_password_protected ? 'true' : 'false'; ?>;
        let originalPassword = '<?php echo addslashes($original_password); ?>';
        
        // All users map for username lookup
        let allUsers = <?php echo json_encode($all_users); ?>;
        
        // PeerJS variables
        let peer = null;
        let myPeerId = null;
        let calls = {};
        let peerCheckInterval = null;
        
        // Signaling variables
        let signalingPollInterval = null;
        let lastSignalCheck = 0;
        
        // Track host online status
        let isHostOnline = <?php echo $is_host ? 'true' : 'false'; ?>;
        
        // Participant map for username lookup
        let participantMap = {};
        let mutedUsers = <?php echo json_encode($muted_users); ?>;
        
        // Device status for all participants
        let deviceStatus = {};
        
        // Context menu variables
        let activeContextMenu = null;
        
        // Kicked check interval
        let kickedCheckInterval = null;

        // Fullscreen state
        let fullscreenElement = null;

        // Heartbeat interval
        let heartbeatInterval = null;

        // MEETING DETAILS MODAL 
        function showMeetingDetails() {
            document.getElementById('meeting-details-modal').classList.add('show');
        }

        function closeMeetingDetails() {
            document.getElementById('meeting-details-modal').classList.remove('show');
        }

        function togglePasswordVisibility() {
            const passwordDisplay = document.getElementById('password-display');
            const toggleBtn = document.getElementById('toggle-password-visibility');
            
            if (passwordDisplay.textContent === '••••••••') {
                passwordDisplay.textContent = '<?php echo addslashes($original_password); ?>';
                toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordDisplay.textContent = '••••••••';
                toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }

        function copyToClipboard(text, message) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification(message, 'success');
            }).catch(() => {
                showNotification('Failed to copy to clipboard', 'error');
            });
        }

        // KICKED USER HANDLER 
        function handleUserKicked() {
            // Show notification
            showNotification('You have been removed from the meeting by the host', 'error');
            
            // Clean up media streams
            if (localStream) {
                localStream.getTracks().forEach(track => {
                    track.stop();
                });
                localStream = null;
            }
            
            if (screenStream) {
                screenStream.getTracks().forEach(track => {
                    track.stop();
                });
                screenStream = null;
            }
            
            // Close all peer connections
            Object.values(calls).forEach(call => {
                if (call && call.close) {
                    call.close();
                }
            });
            calls = {};
            
            // Destroy peer connection
            if (peer) {
                peer.destroy();
                peer = null;
            }
            
            // Clear all intervals
            if (peerCheckInterval) clearInterval(peerCheckInterval);
            if (timerInterval) clearInterval(timerInterval);
            if (chatInterval) clearInterval(chatInterval);
            if (participantsInterval) clearInterval(participantsInterval);
            if (tasksInterval) clearInterval(tasksInterval);
            if (signalingPollInterval) clearInterval(signalingPollInterval);
            if (kickedCheckInterval) clearInterval(kickedCheckInterval);
            if (heartbeatInterval) clearInterval(heartbeatInterval);
            
            // Notify server that user is leaving (kicked)
            fetch(`?room=${roomId}&ajax=leave_meeting&t=${Date.now()}`)
                .finally(() => {
                    // Redirect to dashboard after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                });
        }

        // Check if user has been kicked
        function startKickedCheck() {
            kickedCheckInterval = setInterval(async () => {
                try {
                    const response = await fetch(`?room=${roomId}&ajax=check_kicked&t=${Date.now()}`);
                    const data = await response.json();
                    
                    if (data.success && data.is_kicked) {
                        // User has been kicked - stop checking and handle kick
                        clearInterval(kickedCheckInterval);
                        handleUserKicked();
                    }
                } catch (error) {
                    console.error('Error checking kicked status:', error);
                }
            }, 3000);
        }

        // HEARTBEAT FUNCTION 
        function startHeartbeat() {
            heartbeatInterval = setInterval(async () => {
                try {
                    await fetch(`?room=${roomId}&ajax=heartbeat&t=${Date.now()}`);
                } catch (error) {
                    console.error('Error sending heartbeat:', error);
                }
            }, 30000); // Send heartbeat every 30 seconds
        }

        // FULLSCREEN FUNCTIONS 
        function toggleFullscreen(videoElement) {
            if (fullscreenElement === videoElement) {
                // Exit fullscreen
                videoElement.classList.remove('fullscreen');
                const exitBtn = videoElement.querySelector('.fullscreen-exit-btn');
                if (exitBtn) exitBtn.remove();
                fullscreenElement = null;
                
                // Restore video grid layout
                updateVideoGridLayout();
            } else {
                // Exit previous fullscreen if any
                if (fullscreenElement) {
                    fullscreenElement.classList.remove('fullscreen');
                    const oldExitBtn = fullscreenElement.querySelector('.fullscreen-exit-btn');
                    if (oldExitBtn) oldExitBtn.remove();
                }
                
                // Enter fullscreen
                videoElement.classList.add('fullscreen');
                
                // Add exit button
                const exitBtn = document.createElement('button');
                exitBtn.className = 'fullscreen-exit-btn';
                exitBtn.innerHTML = '<i class="fas fa-times"></i>';
                exitBtn.onclick = (e) => {
                    e.stopPropagation();
                    toggleFullscreen(videoElement);
                };
                videoElement.appendChild(exitBtn);
                
                fullscreenElement = videoElement;
            }
        }

        // INITIALIZATION 
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('DOM loaded. Starting with URL parameters...');
            console.log('Mic status from URL:', isMicOn);
            console.log('Camera status from URL:', isCameraOn);
            
            // Initialize participant map with all users
            <?php foreach ($all_users as $uid => $uname): ?>
            participantMap[<?php echo $uid; ?>] = '<?php echo addslashes($uname); ?>';
            <?php endforeach; ?>
            
            // Add host to participant map if not already there
            if (!participantMap[hostUserId]) {
                participantMap[hostUserId] = hostUsername;
            }
            
            // Initialize media with URL parameters
            await initMedia();
            
            // Show main interface (no pre-join modal)
            document.getElementById('main-nav').style.display = 'flex';
            document.getElementById('meeting-container').style.display = 'flex';
            
            // Initialize PeerJS
            await initPeerJS();
            
            // Add local video to grid
            addLocalVideoElement();
            
            // Start looking for other peers
            startPeerDiscovery();
            
            // Initialize other features
            startTimer();
            initMainEventListeners();
            initPipWindow();
            
            // Start real-time updates
            startRealTimeUpdates();
            
            // Start heartbeat
            startHeartbeat();
            
            // Start checking if user has been kicked
            startKickedCheck();
            
            // Clean old signals periodically
            setInterval(cleanOldSignals, 60000);
            
            // Check if user is muted periodically
            setInterval(checkMutedStatus, 5000);
            
            // Initialize audio devices
            initAudioDevices();
            
            // Close context menu when clicking elsewhere
            document.addEventListener('click', function(e) {
                if (activeContextMenu && !activeContextMenu.contains(e.target)) {
                    activeContextMenu.remove();
                    activeContextMenu = null;
                }
            });
            
            // Show welcome notification
            showNotification('Welcome to the meeting!', 'info');
        });
        
        async function initMedia() {
            try {
                const constraints = {
                    video: isCameraOn ? {
                        width: { ideal: 1280, max: 1920 },
                        height: { ideal: 720, max: 1080 },
                        facingMode: 'user'
                    } : false,
                    audio: isMicOn ? {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    } : false
                };
                
                // Only request media if either is enabled
                if (isCameraOn || isMicOn) {
                    localStream = await navigator.mediaDevices.getUserMedia(constraints);
                    console.log('Got media stream with settings from URL');
                }
            } catch (error) {
                console.error('Error accessing media:', error);
                isMicOn = false;
                isCameraOn = false;
                showNotification('Unable to access camera/microphone', 'warning');
            }
        }
        
        async function initPeerJS() {
            return new Promise((resolve) => {
                // Generate unique peer ID
                myPeerId = `user-${currentUserId}-${Date.now()}`;
                
                // Create PeerJS instance
                peer = new Peer(myPeerId, {
                    host: '0.peerjs.com',
                    port: 443,
                    secure: true,
                    config: {
                        'iceServers': [
                            { urls: 'stun:stun.l.google.com:19302' },
                            { urls: 'stun:stun1.l.google.com:19302' },
                            { urls: 'stun:stun2.l.google.com:19302' },
                            { urls: 'stun:stun3.l.google.com:19302' },
                            { urls: 'stun:stun4.l.google.com:19302' },
                            { urls: 'stun:stun.ekiga.net:3478' },
                            { urls: 'stun:stun.stunprotocol.org:3478' }
                        ]
                    },
                    debug: 3
                });
                
                peer.on('open', async (id) => {
                    console.log('PeerJS connected with ID:', id);
                    myPeerId = id;
                    
                    // Register with server
                    await registerPeer();
                    
                    // Start signaling poll
                    startSignalingPoll();
                    
                    resolve();
                });
                
                peer.on('error', (err) => {
                    console.error('PeerJS error:', err);
                    showNotification('Peer connection error: ' + err.type, 'error');
                    
                    // Retry connection after 3 seconds
                    setTimeout(() => {
                        if (peer.destroyed) {
                            initPeerJS();
                        }
                    }, 3000);
                    
                    resolve();
                });
                
                // Handle incoming calls
                peer.on('call', (call) => {
                    console.log('Incoming call from:', call.peer);
                    
                    if (localStream) {
                        console.log('Answering call with local stream');
                        call.answer(localStream);
                        
                        call.on('stream', (remoteStream) => {
                            console.log('Received remote stream from:', call.peer);
                            addRemoteVideoStream(call.peer, remoteStream);
                        });
                        
                        call.on('close', () => {
                            console.log('Call closed:', call.peer);
                            removeRemoteVideo(call.peer);
                        });
                        
                        calls[call.peer] = call;
                    }
                });
            });
        }
        
        async function registerPeer() {
            try {
                const formData = new FormData();
                formData.append('action', 'register_peer');
                formData.append('peer_id', myPeerId);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                console.log('Registered peer:', data);
            } catch (error) {
                console.error('Error registering peer:', error);
            }
        }
        
        function startSignalingPoll() {
            if (signalingPollInterval) clearInterval(signalingPollInterval);
            signalingPollInterval = setInterval(pollSignals, 2000);
        }
        
        async function pollSignals() {
            try {
                const response = await fetch(`signaling.php?room=${roomId}&action=get_signals&last_check=${lastSignalCheck}&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success && data.signals) {
                    lastSignalCheck = data.last_check;
                    
                    for (const signal of data.signals) {
                        await processSignal(signal);
                        
                        // Delete processed signal
                        await deleteSignal(signal.id);
                    }
                }
            } catch (error) {
                console.error('Error polling signals:', error);
            }
        }
        
        async function processSignal(signal) {
            try {
                const fromPeerId = await getPeerIdByUserId(signal.from_user_id);
                
                if (!fromPeerId) {
                    console.log('No peer ID found for user:', signal.from_user_id);
                    return;
                }
                
                switch (signal.type) {
                    case 'offer':
                        console.log('Received offer from:', fromPeerId);
                        await handleOfferViaSignaling(fromPeerId, signal.data);
                        break;
                    case 'answer':
                        console.log('Received answer from:', fromPeerId);
                        if (calls[fromPeerId]) {
                            const answer = JSON.parse(signal.data);
                            await calls[fromPeerId].handleAnswer(answer);
                        }
                        break;
                    case 'ice_candidate':
                        console.log('Received ICE candidate from:', fromPeerId);
                        if (calls[fromPeerId] && calls[fromPeerId].peerConnection) {
                            const candidate = JSON.parse(signal.data);
                            await calls[fromPeerId].peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                        }
                        break;
                    case 'kick':
                        console.log('🚨 RECEIVED KICK SIGNAL from:', fromPeerId);
                        // Handle kick immediately
                        handleUserKicked();
                        break;
                }
            } catch (error) {
                console.error('Error processing signal:', error);
            }
        }
        
        async function handleOfferViaSignaling(peerId, offerData) {
            try {
                const pc = new RTCPeerConnection({
                    iceServers: [
                        { urls: 'stun:stun.l.google.com:19302' }
                    ]
                });
                
                if (localStream) {
                    localStream.getTracks().forEach(track => {
                        pc.addTrack(track, localStream);
                    });
                }
                
                pc.onicecandidate = async (event) => {
                    if (event.candidate) {
                        const targetUserId = peerId.split('-')[1];
                        
                        const formData = new FormData();
                        formData.append('action', 'ice_candidate');
                        formData.append('candidate', JSON.stringify(event.candidate));
                        formData.append('target', targetUserId);
                        formData.append('room', roomId);
                        
                        await fetch('signaling.php', {
                            method: 'POST',
                            body: formData
                        });
                    }
                };
                
                pc.ontrack = (event) => {
                    console.log('Got remote track via signaling from:', peerId);
                    const fakeCall = {
                        peer: peerId,
                        peerConnection: pc,
                        close: () => pc.close()
                    };
                    calls[peerId] = fakeCall;
                    addRemoteVideoStream(peerId, event.streams[0]);
                };
                
                const offer = JSON.parse(offerData);
                await pc.setRemoteDescription(new RTCSessionDescription(offer));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                
                // Send answer via signaling
                const targetUserId = peerId.split('-')[1];
                const formData = new FormData();
                formData.append('action', 'answer');
                formData.append('answer', JSON.stringify(answer));
                formData.append('target', targetUserId);
                formData.append('room', roomId);
                
                await fetch('signaling.php', {
                    method: 'POST',
                    body: formData
                });
                
            } catch (error) {
                console.error('Error handling offer via signaling:', error);
            }
        }
        
        async function deleteSignal(signalId) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_signal');
                formData.append('signal_id', signalId);
                
                await fetch('signaling.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                console.error('Error deleting signal:', error);
            }
        }
        
        async function getPeerIdByUserId(userId) {
            try {
                const response = await fetch(`signaling.php?room=${roomId}&action=get_peers&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success && data.peers) {
                    const peer = data.peers.find(p => p.user_id == userId);
                    return peer ? peer.peer_id : null;
                }
            } catch (error) {
                console.error('Error getting peer ID:', error);
            }
            return null;
        }
        
        async function cleanOldSignals() {
            try {
                await fetch(`signaling.php?room=${roomId}&action=clean_old_signals&t=${Date.now()}`);
            } catch (error) {
                console.error('Error cleaning old signals:', error);
            }
        }
        
        function startPeerDiscovery() {
            peerCheckInterval = setInterval(async () => {
                await discoverPeers();
            }, 3000);
        }
        
        async function discoverPeers() {
            try {
                const response = await fetch(`signaling.php?room=${roomId}&action=get_peers&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success && data.peers) {
                    data.peers.forEach(peerInfo => {
                        const peerId = peerInfo.peer_id;
                        
                        if (peerId !== myPeerId && !calls[peerId] && localStream) {
                            console.log('Found new peer:', peerId);
                            callPeer(peerId);
                        }
                    });
                }
            } catch (error) {
                console.error('Error discovering peers:', error);
            }
        }
        
        function callPeer(peerId) {
            if (!localStream) {
                console.log('No local stream, cannot call');
                return;
            }
            
            console.log('Calling peer:', peerId);
            
            try {
                const call = peer.call(peerId, localStream);
                
                call.on('stream', (remoteStream) => {
                    console.log('Got remote stream from:', peerId);
                    addRemoteVideoStream(peerId, remoteStream);
                });
                
                call.on('close', () => {
                    console.log('Call closed:', peerId);
                    removeRemoteVideo(peerId);
                });
                
                call.on('error', (err) => {
                    console.error('Call error:', err);
                    // Try signaling as fallback
                    createOfferViaSignaling(peerId);
                });
                
                calls[peerId] = call;
            } catch (error) {
                console.error('Error calling peer:', error);
                // Try signaling as fallback
                createOfferViaSignaling(peerId);
            }
        }
        
        async function createOfferViaSignaling(targetPeerId) {
            try {
                const targetUserId = targetPeerId.split('-')[1];
                
                const pc = new RTCPeerConnection({
                    iceServers: [
                        { urls: 'stun:stun.l.google.com:19302' }
                    ]
                });
                
                localStream.getTracks().forEach(track => {
                    pc.addTrack(track, localStream);
                });
                
                pc.onicecandidate = async (event) => {
                    if (event.candidate) {
                        const formData = new FormData();
                        formData.append('action', 'ice_candidate');
                        formData.append('candidate', JSON.stringify(event.candidate));
                        formData.append('target', targetUserId);
                        formData.append('room', roomId);
                        
                        await fetch('signaling.php', {
                            method: 'POST',
                            body: formData
                        });
                    }
                };
                
                pc.ontrack = (event) => {
                    console.log('Got remote track via signaling');
                    const fakeCall = {
                        peer: targetPeerId,
                        peerConnection: pc,
                        close: () => pc.close()
                    };
                    calls[targetPeerId] = fakeCall;
                    addRemoteVideoStream(targetPeerId, event.streams[0]);
                };
                
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                
                const formData = new FormData();
                formData.append('action', 'offer');
                formData.append('offer', JSON.stringify(offer));
                formData.append('target', targetUserId);
                formData.append('room', roomId);
                
                await fetch('signaling.php', {
                    method: 'POST',
                    body: formData
                });
                
            } catch (error) {
                console.error('Error creating offer via signaling:', error);
            }
        }
        
        function addLocalVideoElement() {
            const videoId = `video-${currentUserId}`;
            let existing = document.getElementById(videoId);
            if (existing) existing.remove();
            
            const container = document.createElement('div');
            container.id = videoId;
            container.className = 'video-item' + (isMutedByHost ? ' muted' : '');
            container.dataset.userId = currentUserId;
            container.dataset.isLocal = true;
            
            // Add click for fullscreen
            container.addEventListener('dblclick', () => toggleFullscreen(container));
            
            // Add context menu for host to kick/mute other users (but not themselves)
            if (isHost) {
                container.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    showContextMenu(e, container);
                });
            }
            
            if (localStream) {
                const video = document.createElement('video');
                video.autoplay = true;
                video.playsInline = true;
                video.muted = true;
                video.id = 'main-video';
                video.srcObject = localStream;
                container.appendChild(video);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'video-placeholder';
                placeholder.innerHTML = '<i class="fas fa-user-circle"></i>';
                container.appendChild(placeholder);
            }
            
            const info = document.createElement('div');
            info.className = 'video-info';
            
            const hostIndicator = isHost ? '<span class="host-badge">Host</span>' : '';
            const mutedIndicator = isMutedByHost ? '<span class="muted-badge-small">Muted</span>' : '';
            
            info.innerHTML = `
                <div class="user-avatar-small">${escapeHtml(currentUsername.substring(0, 2).toUpperCase())}</div>
                <div class="user-name">${escapeHtml(currentUsername)} ${hostIndicator} ${mutedIndicator}</div>
                <div class="mic-status ${isMicOn && !isMutedByHost ? '' : 'muted'}">
                    <i class="fas fa-microphone${isMicOn && !isMutedByHost ? '' : '-slash'}"></i>
                </div>
            `;
            
            container.appendChild(info);
            document.getElementById('video-grid').appendChild(container);
            
            updateVideoGridLayout();
            updateParticipantCount();
        }
        
        function addHostPlaceholderTile() {
            if (hostUserId == currentUserId) return;
            
            const videoId = `video-${hostUserId}`;
            if (document.getElementById(videoId)) return;
            
            const container = document.createElement('div');
            container.id = videoId;
            container.className = 'video-item';
            container.dataset.userId = hostUserId;
            
            // Add click for fullscreen
            container.addEventListener('dblclick', () => toggleFullscreen(container));
            
            // Add context menu for host to kick/mute other users (but not themselves)
            if (isHost && hostUserId != currentUserId) {
                container.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    showContextMenu(e, container);
                });
            }
            
            const placeholder = document.createElement('div');
            placeholder.className = 'video-placeholder';
            placeholder.innerHTML = '<i class="fas fa-user-circle"></i>';
            
            const info = document.createElement('div');
            info.className = 'video-info';
            info.innerHTML = `
                <div class="user-avatar-small">${escapeHtml(hostUsername.substring(0, 2).toUpperCase())}</div>
                <div class="user-name">${escapeHtml(hostUsername)} <span class="host-badge">Host</span></div>
                <div class="mic-status"><i class="fas fa-microphone"></i></div>
            `;
            
            container.appendChild(placeholder);
            container.appendChild(info);
            document.getElementById('video-grid').appendChild(container);
            
            updateVideoGridLayout();
            updateParticipantCount();
        }
        
        function addRemoteVideoStream(peerId, stream) {
            console.log('✅ REMOTE STREAM RECEIVED. Peer ID:', peerId, 'Stream active:', stream.active);
            const userId = peerId.split('-')[1];
            const videoId = `video-${userId}`;
            
            // Get username from participant map or allUsers
            let username = participantMap[userId] || allUsers[userId] || `User ${userId}`;
            if (userId == hostUserId) {
                username = hostUsername;
            }
            
            // Check if this user is muted
            const isUserMuted = mutedUsers.includes(parseInt(userId));
            
            // Get device status
            const userMicStatus = deviceStatus[userId]?.mic ?? true;
            
            let container = document.getElementById(videoId);
            if (container) container.remove();
            
            container = document.createElement('div');
            container.id = videoId;
            container.className = 'video-item' + (isUserMuted ? ' muted' : '');
            container.dataset.userId = userId;
            container.dataset.isLocal = false;
            
            // Add click for fullscreen
            container.addEventListener('dblclick', () => toggleFullscreen(container));
            
            // Add context menu for host to kick/mute other users
            if (isHost && userId != currentUserId) {
                container.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    showContextMenu(e, container);
                });
            }
            
            const video = document.createElement('video');
            video.autoplay = true;
            video.playsInline = true;
            video.srcObject = stream;
            
            const hostIndicator = (userId == hostUserId) ? '<span class="host-badge">Host</span>' : '';
            const mutedIndicator = isUserMuted ? '<span class="muted-badge-small">Muted</span>' : '';
            
            const info = document.createElement('div');
            info.className = 'video-info';
            info.innerHTML = `
                <div class="user-avatar-small">${escapeHtml(username.substring(0, 2).toUpperCase())}</div>
                <div class="user-name">${escapeHtml(username)} ${hostIndicator} ${mutedIndicator}</div>
                <div class="mic-status ${isUserMuted || !userMicStatus ? 'muted' : ''}">
                    <i class="fas fa-microphone${isUserMuted || !userMicStatus ? '-slash' : ''}"></i>
                </div>
            `;
            
            container.appendChild(video);
            container.appendChild(info);
            document.getElementById('video-grid').appendChild(container);
            
            video.play().catch(e => console.error('Error playing video:', e));
            updateVideoGridLayout();
            updateParticipantCount();
            showNotification(`${username} joined the meeting`, 'info');
        }
        
        function removeRemoteVideo(peerId) {
            const userId = peerId.split('-')[1];
            const element = document.getElementById(`video-${userId}`);
            if (element) {
                element.remove();
                updateVideoGridLayout();
                updateParticipantCount();
                
                // If the removed user was the host, remove their tile
                if (userId == hostUserId) {
                    isHostOnline = false;
                }
            }
            
            delete calls[peerId];
        }
        
        // Context menu for kicking/muting 
        function showContextMenu(event, videoElement) {
            // Close any existing context menu
            if (activeContextMenu) {
                activeContextMenu.remove();
                activeContextMenu = null;
            }
            
            const userId = videoElement.dataset.userId;
            
            // Don't show menu for self or host
            if (userId == currentUserId || userId == hostUserId) return;
            
            // Get username
            const username = participantMap[userId] || allUsers[userId] || `User ${userId}`;
            const isMuted = mutedUsers.includes(parseInt(userId));
            
            // Create context menu
            const menu = document.createElement('div');
            menu.className = 'video-context-menu show';
            menu.style.position = 'fixed';
            menu.style.top = event.clientY + 'px';
            menu.style.left = event.clientX + 'px';
            menu.style.zIndex = '2000';
            
            let menuItems = '';
            
            if (isMuted) {
                menuItems += `
                    <div class="context-menu-item unmute" onclick="unmuteUser(${userId}, '${escapeHtml(username)}')">
                        <i class="fas fa-microphone"></i> Unmute
                    </div>
                `;
            } else {
                menuItems += `
                    <div class="context-menu-item mute" onclick="muteUser(${userId}, '${escapeHtml(username)}')">
                        <i class="fas fa-microphone-slash"></i> Mute
                    </div>
                `;
            }
            
            menuItems += `
                <div class="context-menu-divider"></div>
                <div class="context-menu-item kick" onclick="kickUser(${userId}, '${escapeHtml(username)}')">
                    <i class="fas fa-user-slash"></i> Kick
                </div>
            `;
            
            menu.innerHTML = menuItems;
            
            // Add to body
            document.body.appendChild(menu);
            activeContextMenu = menu;
            
            // Adjust position if menu goes off screen
            const menuRect = menu.getBoundingClientRect();
            if (menuRect.right > window.innerWidth) {
                menu.style.left = (window.innerWidth - menuRect.width - 10) + 'px';
            }
            if (menuRect.bottom > window.innerHeight) {
                menu.style.top = (window.innerHeight - menuRect.height - 10) + 'px';
            }
            
            // Prevent default context menu
            event.preventDefault();
        }
        
        function updateVideoGridLayout() {
            const grid = document.getElementById('video-grid');
            const participants = document.querySelectorAll('.video-item:not(.fullscreen)').length;
            grid.setAttribute('data-participants', participants);
        }
        
        function updateParticipantCount() {
            const count = document.querySelectorAll('.video-item').length;
            document.getElementById('participant-count').textContent = count;
            document.getElementById('modal-participant-count').textContent = count;
            updateVideoGridLayout();
        }
        
        function checkHostStatus() {
            // Check if host is online by looking for host's video tile
            const hostTile = document.getElementById(`video-${hostUserId}`);
            isHostOnline = hostTile !== null;
            
            // If host is not online and we're not the host, add placeholder
            if (!isHostOnline && !isHost) {
                addHostPlaceholderTile();
            }
        }
        
        // HOST ACTIONS 
        async function kickUser(targetUserId, username) {
            if (!isHost) {
                showNotification('Only the host can kick participants', 'error');
                return;
            }
            
            if (targetUserId == currentUserId) {
                showNotification('You cannot kick yourself', 'warning');
                return;
            }
            
            if (targetUserId == hostUserId) {
                showNotification('You cannot kick the host', 'warning');
                return;
            }
            
            if (!confirm(`Are you sure you want to kick ${escapeHtml(username)} from the meeting?`)) return;
            
            try {
                const formData = new FormData();
                formData.append('kick_user', '1');
                formData.append('target_user_id', targetUserId);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(`${escapeHtml(username)} has been kicked from the meeting`, 'success');
                    
                    // Remove their video tile immediately
                    const videoElement = document.getElementById(`video-${targetUserId}`);
                    if (videoElement) videoElement.remove();
                    
                    // Close peer connection if exists
                    const peerId = `user-${targetUserId}`;
                    const call = calls[peerId];
                    if (call) {
                        call.close();
                        delete calls[peerId];
                    }
                    
                    updateVideoGridLayout();
                    updateParticipantCount();
                    await updateParticipants();
                    
                    // Close context menu
                    if (activeContextMenu) {
                        activeContextMenu.remove();
                        activeContextMenu = null;
                    }
                } else {
                    showNotification(result.error || 'Failed to kick user', 'error');
                }
            } catch (error) {
                console.error('Error kicking user:', error);
                showNotification('Failed to kick user', 'error');
            }
        }
        
        async function muteUser(targetUserId, username) {
            if (!isHost) {
                showNotification('Only the host can mute participants', 'error');
                return;
            }
            
            if (targetUserId == currentUserId) {
                showNotification('You cannot mute yourself', 'warning');
                return;
            }
            
            if (targetUserId == hostUserId) {
                showNotification('You cannot mute the host', 'warning');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('mute_user', '1');
                formData.append('target_user_id', targetUserId);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(`${escapeHtml(username)} has been muted`, 'success');
                    
                    // Add to muted users list
                    if (!mutedUsers.includes(parseInt(targetUserId))) {
                        mutedUsers.push(parseInt(targetUserId));
                    }
                    
                    // Update video tile for this user
                    const videoElement = document.getElementById(`video-${targetUserId}`);
                    if (videoElement) {
                        videoElement.classList.add('muted');
                        const micStatus = videoElement.querySelector('.mic-status');
                        if (micStatus) {
                            micStatus.classList.add('muted');
                            micStatus.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                        }
                        
                        // Add muted badge to username
                        const userNameElement = videoElement.querySelector('.user-name');
                        if (userNameElement && !userNameElement.innerHTML.includes('Muted')) {
                            userNameElement.innerHTML += ' <span class="muted-badge-small">Muted</span>';
                        }
                    }
                    
                    await updateParticipants();
                    
                    // Close context menu
                    if (activeContextMenu) {
                        activeContextMenu.remove();
                        activeContextMenu = null;
                    }
                } else {
                    showNotification(result.error || 'Failed to mute user', 'error');
                }
            } catch (error) {
                console.error('Error muting user:', error);
                showNotification('Failed to mute user', 'error');
            }
        }
        
        async function unmuteUser(targetUserId, username) {
            if (!isHost) {
                showNotification('Only the host can unmute participants', 'error');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('unmute_user', '1');
                formData.append('target_user_id', targetUserId);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(`${escapeHtml(username)} has been unmuted`, 'success');
                    
                    // Remove from muted users list
                    mutedUsers = mutedUsers.filter(id => id != targetUserId);
                    
                    // Update video tile for this user
                    const videoElement = document.getElementById(`video-${targetUserId}`);
                    if (videoElement) {
                        videoElement.classList.remove('muted');
                        const micStatus = videoElement.querySelector('.mic-status');
                        if (micStatus) {
                            micStatus.classList.remove('muted');
                            micStatus.innerHTML = '<i class="fas fa-microphone"></i>';
                        }
                        
                        // Remove muted badge from username
                        const userNameElement = videoElement.querySelector('.user-name');
                        if (userNameElement) {
                            userNameElement.innerHTML = userNameElement.innerHTML.replace(' <span class="muted-badge-small">Muted</span>', '');
                        }
                    }
                    
                    await updateParticipants();
                    
                    // Close context menu
                    if (activeContextMenu) {
                        activeContextMenu.remove();
                        activeContextMenu = null;
                    }
                } else {
                    showNotification(result.error || 'Failed to unmute user', 'error');
                }
            } catch (error) {
                console.error('Error unmuting user:', error);
                showNotification('Failed to unmute user', 'error');
            }
        }
        
        async function checkMutedStatus() {
            try {
                const response = await fetch(`?room=${roomId}&ajax=is_muted&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success) {
                    const wasMuted = isMutedByHost;
                    isMutedByHost = data.is_muted;
                    
                    if (wasMuted !== isMutedByHost) {
                        if (isMutedByHost) {
                            // User was just muted by host
                            isMicOn = false;
                            if (localStream) {
                                localStream.getAudioTracks().forEach(track => track.enabled = false);
                            }
                            
                            // Disable mic controls
                            document.getElementById('toggle-mic').disabled = true;
                            document.getElementById('toggle-mic').style.opacity = '0.5';
                            
                            // Update UI
                            updateMicUI();
                            updateLocalVideoMutedStatus();
                            showNotification('You have been muted by the host', 'warning');
                        } else {
                            // User was unmuted by host
                            // Re-enable mic controls
                            document.getElementById('toggle-mic').disabled = false;
                            document.getElementById('toggle-mic').style.opacity = '1';
                            
                            updateLocalVideoMutedStatus();
                            
                            showNotification('You have been unmuted by the host', 'success');
                        }
                    }
                }
            } catch (error) {
                console.error('Error checking muted status:', error);
            }
        }
        
        function updateLocalVideoMutedStatus() {
            const localVideo = document.getElementById(`video-${currentUserId}`);
            if (!localVideo) return;
            
            if (isMutedByHost) {
                localVideo.classList.add('muted');
            } else {
                localVideo.classList.remove('muted');
            }
            
            const micStatus = localVideo.querySelector('.mic-status');
            if (micStatus) {
                if (isMutedByHost || !isMicOn) {
                    micStatus.classList.add('muted');
                    micStatus.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                } else {
                    micStatus.classList.remove('muted');
                    micStatus.innerHTML = '<i class="fas fa-microphone"></i>';
                }
            }
            
            const userNameElement = localVideo.querySelector('.user-name');
            if (userNameElement) {
                // Remove existing muted badge
                userNameElement.innerHTML = userNameElement.innerHTML.replace(' <span class="muted-badge-small">Muted</span>', '');
                
                // Add muted badge if needed
                if (isMutedByHost) {
                    userNameElement.innerHTML += ' <span class="muted-badge-small">Muted</span>';
                }
            }
        }
        
        // DEVICE CONTROLS
        async function toggleMic() {
            if (isMutedByHost) {
                showNotification('You are muted by the host and cannot unmute yourself', 'warning');
                return;
            }
            
            if (!localStream) {
                try {
                    isCameraOn = false;
                    isMicOn = true;
                    await initMedia();
                    return;
                } catch (error) {
                    showNotification('Unable to access microphone', 'error');
                    return;
                }
            }
            
            isMicOn = !isMicOn;
            
            if (localStream) {
                localStream.getAudioTracks().forEach(track => track.enabled = isMicOn);
            }
            
            updateMicUI();
            await saveDeviceStatus('mic', isMicOn);
            showNotification(isMicOn ? 'Microphone unmuted' : 'Microphone muted', isMicOn ? 'success' : 'warning');
        }
        
        function updateMicUI() {
            const micBtn = document.getElementById('toggle-mic');
            const micIcon = (isMicOn && !isMutedByHost) ? 'fa-microphone' : 'fa-microphone-slash';
            
            micBtn.innerHTML = `<i class="fas ${micIcon}"></i>`;
            micBtn.classList.toggle('active', isMicOn && !isMutedByHost);
            
            const localVideoInfo = document.querySelector(`#video-${currentUserId} .mic-status`);
            if (localVideoInfo) {
                if (isMutedByHost || !isMicOn) {
                    localVideoInfo.classList.add('muted');
                    localVideoInfo.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                } else {
                    localVideoInfo.classList.remove('muted');
                    localVideoInfo.innerHTML = '<i class="fas fa-microphone"></i>';
                }
            }
        }
        
        async function toggleCamera() {
            if (!localStream) {
                try {
                    isCameraOn = true;
                    isMicOn = false;
                    await initMedia();
                    return;
                } catch (error) {
                    showNotification('Unable to access camera', 'error');
                    return;
                }
            }
            
            isCameraOn = !isCameraOn;
            
            if (localStream) {
                const videoTrack = localStream.getVideoTracks()[0];
                if (videoTrack) {
                    videoTrack.enabled = isCameraOn;
                } else if (isCameraOn) {
                    const newStream = await navigator.mediaDevices.getUserMedia({ video: true });
                    const newVideoTrack = newStream.getVideoTracks()[0];
                    localStream.addTrack(newVideoTrack);
                    newStream.getAudioTracks().forEach(t => t.stop());
                }
            }
            
            updateCameraUI();
            updateLocalVideoStream();
            await saveDeviceStatus('camera', isCameraOn);
            showNotification(isCameraOn ? 'Camera turned on' : 'Camera turned off', isCameraOn ? 'success' : 'warning');
        }
        
        function updateCameraUI() {
            const cameraBtn = document.getElementById('toggle-camera');
            const cameraIcon = isCameraOn ? 'fa-video' : 'fa-video-slash';
            
            cameraBtn.innerHTML = `<i class="fas ${cameraIcon}"></i>`;
            cameraBtn.classList.toggle('active', isCameraOn);
        }
        
        function updateLocalVideoStream() {
            const container = document.getElementById(`video-${currentUserId}`);
            if (!container) return;
            
            if (isScreenSharing && screenStream) {
                const video = container.querySelector('video');
                if (video) {
                    video.srcObject = screenStream;
                    video.muted = true;
                } else {
                    const placeholder = container.querySelector('.video-placeholder');
                    if (placeholder) {
                        const newVideo = document.createElement('video');
                        newVideo.autoplay = true;
                        newVideo.playsInline = true;
                        newVideo.muted = true;
                        newVideo.srcObject = screenStream;
                        placeholder.replaceWith(newVideo);
                    }
                }
                updatePipWindow();
            } else if (isCameraOn && localStream) {
                const video = container.querySelector('video');
                if (video) {
                    video.srcObject = localStream;
                    video.muted = true;
                } else {
                    const placeholder = container.querySelector('.video-placeholder');
                    if (placeholder) {
                        const newVideo = document.createElement('video');
                        newVideo.autoplay = true;
                        newVideo.playsInline = true;
                        newVideo.muted = true;
                        newVideo.srcObject = localStream;
                        placeholder.replaceWith(newVideo);
                    }
                }
                updatePipWindow();
            } else {
                const video = container.querySelector('video');
                if (video) {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'video-placeholder';
                    placeholder.innerHTML = '<i class="fas fa-user-circle"></i>';
                    video.replaceWith(placeholder);
                }
                updatePipWindow();
            }
            
            updateTracksInPeerConnections();
        }
        
        // SCREEN SHARE FUNCTIONS 
        async function shareScreen() {
            if (!isScreenSharing) {
                try {
                    screenStream = await navigator.mediaDevices.getDisplayMedia({
                        video: { 
                            cursor: "always",
                            displaySurface: "monitor"
                        },
                        audio: {
                            echoCancellation: true,
                            noiseSuppression: true,
                            autoGainControl: true
                        }
                    });
                    
                    isScreenSharing = true;
                    
                    document.getElementById('share-screen').classList.add('active');
                    
                    updateLocalVideoStream();
                    showNotification('Screen sharing started', 'success');
                    
                    screenStream.getVideoTracks()[0].addEventListener('ended', () => {
                        stopScreenSharing();
                    });
                    
                } catch (error) {
                    console.error('Error sharing screen:', error);
                    if (error.name !== 'NotAllowedError') {
                        showNotification('Failed to share screen', 'error');
                    }
                    isScreenSharing = false;
                }
            } else {
                stopScreenSharing();
            }
        }
        
        function stopScreenSharing() {
            if (screenStream) {
                screenStream.getTracks().forEach(track => {
                    track.stop();
                });
                screenStream = null;
            }
            
            isScreenSharing = false;
            
            document.getElementById('share-screen').classList.remove('active');
            
            updateLocalVideoStream();
            showNotification('Screen sharing stopped', 'info');
        }
        
        // PIP WINDOW FUNCTIONS - Fully Draggable (entire window) 
        function initPipWindow() {
            makePipDraggable();
            document.getElementById('pip-switch-btn').addEventListener('click', switchWindows);
        }
        
        function updatePipWindow() {
            const pipWindow = document.getElementById('pip-window');
            const pipVideo = document.getElementById('pip-video');
            const pipTitle = document.getElementById('pip-title');
            
            if (isScreenSharing && screenStream && isCameraOn && localStream) {
                pipVideo.srcObject = localStream;
                pipTitle.innerHTML = '<i class="fas fa-video"></i><span>Camera</span>';
                pipWindow.style.display = 'block';
            } else if (isScreenSharing && screenStream) {
                pipWindow.style.display = 'none';
                pipVideo.srcObject = null;
            } else {
                pipWindow.style.display = 'none';
                pipVideo.srcObject = null;
            }
        }
        
        function switchWindows() {
            const mainVideo = document.getElementById('main-video');
            const pipVideo = document.getElementById('pip-video');
            
            if (!mainVideo || !pipVideo || !mainVideo.srcObject || !pipVideo.srcObject) {
                showNotification('No video to switch', 'warning');
                return;
            }
            
            const tempStream = mainVideo.srcObject;
            mainVideo.srcObject = pipVideo.srcObject;
            pipVideo.srcObject = tempStream;
            
            const pipTitle = document.getElementById('pip-title');
            
            // Check if the new pip video is screen share
            const tracks = pipVideo.srcObject.getTracks();
            const hasScreenTrack = tracks.some(track => track.kind === 'video' && track.label && track.label.includes('screen'));
            
            if (hasScreenTrack) {
                pipTitle.innerHTML = '<i class="fas fa-desktop"></i><span>Screen Share</span>';
            } else {
                pipTitle.innerHTML = '<i class="fas fa-video"></i><span>Camera</span>';
            }
            
            showNotification('Switched windows', 'info');
        }
        
        // Fully draggable PIP window - entire window can be dragged
        function makePipDraggable() {
            const pipWindow = document.getElementById('pip-window');
            
            let isDragging = false;
            let currentX, currentY, initialX, initialY, xOffset = 0, yOffset = 0;
            
            // Make the entire window draggable (except the switch button)
            pipWindow.addEventListener('mousedown', dragStart);
            pipWindow.addEventListener('mousemove', drag);
            pipWindow.addEventListener('mouseup', dragEnd);
            pipWindow.addEventListener('mouseleave', dragEnd);
            
            // Prevent drag when clicking on the switch button
            const switchBtn = document.getElementById('pip-switch-btn');
            switchBtn.addEventListener('mousedown', (e) => {
                e.stopPropagation();
            });
            
            function dragStart(e) {
                // Don't start drag if clicking on the switch button
                if (e.target === switchBtn || switchBtn.contains(e.target)) {
                    return;
                }
                
                e.preventDefault();
                initialX = e.clientX - xOffset;
                initialY = e.clientY - yOffset;
                
                isDragging = true;
                pipWindow.classList.add('dragging');
            }
            
            function drag(e) {
                if (isDragging) {
                    e.preventDefault();
                    
                    currentX = e.clientX - initialX;
                    currentY = e.clientY - initialY;
                    
                    // Boundary checks
                    const maxX = window.innerWidth - pipWindow.offsetWidth;
                    const maxY = window.innerHeight - pipWindow.offsetHeight;
                    
                    currentX = Math.max(0, Math.min(currentX, maxX));
                    currentY = Math.max(0, Math.min(currentY, maxY));
                    
                    xOffset = currentX;
                    yOffset = currentY;
                    
                    pipWindow.style.left = currentX + 'px';
                    pipWindow.style.right = 'auto';
                    pipWindow.style.bottom = 'auto';
                    pipWindow.style.top = currentY + 'px';
                }
            }
            
            function dragEnd() {
                isDragging = false;
                pipWindow.classList.remove('dragging');
            }
        }
        
        // AUDIO DEVICE FUNCTIONS 
        async function initAudioDevices() {
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const audioOutputs = devices.filter(device => device.kind === 'audiooutput');
                
                const selector = document.getElementById('audio-device-selector');
                selector.innerHTML = '';
                
                if (audioOutputs.length === 0) {
                    selector.innerHTML = '<div class="device-option-item">No audio output devices found</div>';
                    return;
                }
                
                audioOutputs.forEach((device, index) => {
                    const deviceItem = document.createElement('div');
                    deviceItem.className = 'device-option-item' + (index === 0 ? ' active' : '');
                    deviceItem.dataset.deviceId = device.deviceId;
                    deviceItem.innerHTML = `
                        <i class="fas fa-check" style="opacity: ${index === 0 ? 1 : 0};"></i>
                        <span>${device.label || `Speaker ${index + 1}`}</span>
                    `;
                    
                    deviceItem.addEventListener('click', () => {
                        selectAudioOutputDevice(device.deviceId);
                    });
                    
                    selector.appendChild(deviceItem);
                });
                
            } catch (error) {
                console.error('Error enumerating audio devices:', error);
            }
        }
        
        async function selectAudioOutputDevice(deviceId) {
            document.querySelectorAll('#audio-device-selector .device-option-item').forEach(option => {
                option.classList.remove('active');
                const checkIcon = option.querySelector('.fa-check');
                if (checkIcon) checkIcon.style.opacity = '0';
            });
            
            const selectedOption = document.querySelector(`#audio-device-selector .device-option-item[data-device-id="${deviceId}"]`);
            if (selectedOption) {
                selectedOption.classList.add('active');
                const checkIcon = selectedOption.querySelector('.fa-check');
                if (checkIcon) checkIcon.style.opacity = '1';
            }
            
            const videoElements = document.querySelectorAll('video');
            for (const video of videoElements) {
                if (deviceId !== 'default' && video.setSinkId) {
                    try {
                        await video.setSinkId(deviceId);
                    } catch (err) {
                        console.error('Error setting audio output:', err);
                    }
                }
            }
            
            document.getElementById('audio-device-selector').classList.remove('show');
            showNotification('Audio output switched', 'info');
        }
        
        // UPDATE TRACKS IN PEER CONNECTIONS 
        function updateTracksInPeerConnections() {
            console.log('Updating tracks in peer connections');
            Object.entries(calls).forEach(([peerId, call]) => {
                if (call && call.peerConnection) {
                    const pc = call.peerConnection;
                    const senders = pc.getSenders();
                    
                    if (localStream) {
                        const audioTrack = localStream.getAudioTracks()[0];
                        const audioSender = senders.find(s => s.track?.kind === 'audio');
                        if (audioSender && audioTrack) {
                            audioSender.replaceTrack(audioTrack);
                        } else if (audioTrack) {
                            pc.addTrack(audioTrack, localStream);
                        }
                    }
                    
                    if (isScreenSharing && screenStream) {
                        const videoTrack = screenStream.getVideoTracks()[0];
                        const videoSender = senders.find(s => s.track?.kind === 'video');
                        if (videoSender && videoTrack) {
                            videoSender.replaceTrack(videoTrack);
                        } else if (videoTrack) {
                            pc.addTrack(videoTrack, screenStream);
                        }
                    } else if (isCameraOn && localStream) {
                        const videoTrack = localStream.getVideoTracks()[0];
                        const videoSender = senders.find(s => s.track?.kind === 'video');
                        if (videoSender && videoTrack) {
                            videoSender.replaceTrack(videoTrack);
                        } else if (videoTrack) {
                            pc.addTrack(videoTrack, localStream);
                        }
                    }
                }
            });
        }
        
        async function saveDeviceStatus(device, status) {
            try {
                const formData = new FormData();
                formData.append(`toggle_${device}`, status ? 'on' : 'off');
                
                await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
            } catch (error) {
                console.error('Error saving device status:', error);
            }
        }
        
        // CHAT FUNCTIONS 
        let lastMessageTimestamp = 0; // Track last message time to prevent duplicate notifications
        let lastChatMessageId = null; // Track last message ID to prevent duplicate notifications

        async function handleChatSubmit(e) {
            e.preventDefault();
            
            const chatInput = document.getElementById('chat-input');
            const message = chatInput.value.trim();
            const fileInput = document.getElementById('file-upload');
            
            if (!message && !fileInput.files.length) {
                showNotification('Please enter a message or select a file', 'warning');
                return;
            }
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    chatInput.value = '';
                    document.getElementById('file-preview-send').classList.remove('show');
                    fileInput.value = '';
                    
                    // Store current message count before update
                    const oldMessageCount = document.querySelectorAll('#chat-messages .message').length;
                    
                    await updateChat();
                    
                    // Check if a new message was actually added (not just refresh)
                    const newMessageCount = document.querySelectorAll('#chat-messages .message').length;
                    const now = Date.now();
                    
                    // Only show notification if:
                    // 1. It's been more than 2 seconds since last notification
                    // 2. Message count increased (new message was added)
                    if (now - lastMessageTimestamp > 2000 && newMessageCount > oldMessageCount) {
                        showNotification('Message sent', 'success');
                        lastMessageTimestamp = now;
                    }
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showNotification('Failed to send message', 'error');
            }
        }

        function handleFileSelect(e) {
            const filePreview = document.getElementById('file-preview-send');
            const filePreviewName = document.getElementById('file-preview-name');
            const filePreviewSize = document.getElementById('file-preview-size');
            
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                if (file.size > 25 * 1024 * 1024) {
                    showNotification('File size too large. Maximum 25MB allowed.', 'error');
                    this.value = '';
                    return;
                }
                
                filePreviewName.textContent = file.name;
                filePreviewSize.textContent = formatFileSize(file.size);
                filePreview.classList.add('show');
            }
        }

        function removeFile() {
            document.getElementById('file-upload').value = '';
            document.getElementById('file-preview-send').classList.remove('show');
        }

        async function updateChat() {
            try {
                const now = Date.now();
                if (now - lastChatUpdate < 1000) return;
                
                const response = await fetch(`?room=${roomId}&ajax=get_chat_messages&t=${now}`);
                const data = await response.json();
                
                if (data.success) {
                    lastChatUpdate = now;
                    
                    const chatMessages = document.getElementById('chat-messages');
                    if (!chatMessages) return;
                    
                    if (!data.messages || data.messages.length === 0) {
                        chatMessages.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        `;
                        return;
                    }
                    
                    const currentMessageCount = chatMessages.querySelectorAll('.message').length;
                    if (currentMessageCount === data.messages.length) return;
                    
                    chatMessages.innerHTML = '';
                    
                    data.messages.forEach(msg => {
                        const isOwn = msg.user_id == currentUserId;
                        const isSystem = msg.is_system_message == 1;
                        const senderName = msg.sender_name || msg.username || allUsers[msg.user_id] || 'User';
                        const messageTime = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${isOwn ? 'own' : ''} ${isSystem ? 'system' : ''}`;
                        
                        let fileHtml = '';
                        if (msg.file_name && msg.file_path) {
                            const previewType = getFilePreviewTypeClient(msg.file_name);
                            const fileIcon = getFileIconClient(msg.file_name);
                            
                            if (previewType === 'image') {
                                fileHtml = `
                                    <div class="file-preview-chat image">
                                        <img src="${escapeHtml(msg.file_path)}" 
                                            alt="${escapeHtml(msg.file_name)}"
                                            onclick="openFilePreview('${escapeHtml(msg.file_path)}', '${escapeHtml(msg.file_name)}')">
                                        <button class="file-download-btn" 
                                                onclick="downloadFile('${escapeHtml(msg.file_path)}', '${escapeHtml(msg.file_name)}')">
                                            <i class="fas fa-download"></i> Download (${formatFileSize(msg.file_size || 0)})
                                        </button>
                                    </div>
                                `;
                            } else if (previewType === 'pdf') {
                                fileHtml = `
                                    <div class="file-preview-chat pdf">
                                        <iframe src="${escapeHtml(msg.file_path)}#view=fitH"></iframe>
                                        <button class="file-download-btn" 
                                                onclick="downloadFile('${escapeHtml(msg.file_path)}', '${escapeHtml(msg.file_name)}')">
                                            <i class="fas fa-download"></i> Download (${formatFileSize(msg.file_size || 0)})
                                        </button>
                                    </div>
                                `;
                            } else {
                                fileHtml = `
                                    <div class="file-preview-chat other">
                                        <div class="document-icon">
                                            <i class="fas ${fileIcon}"></i>
                                        </div>
                                        <div style="margin-bottom: 10px;">${escapeHtml(msg.file_name)}</div>
                                        <button class="file-download-btn" 
                                                onclick="downloadFile('${escapeHtml(msg.file_path)}', '${escapeHtml(msg.file_name)}')">
                                            <i class="fas fa-download"></i> Download (${formatFileSize(msg.file_size || 0)})
                                        </button>
                                    </div>
                                `;
                            }
                        }
                        
                        let messageContent = '';
                        if (isSystem) {
                            messageContent = `
                                <div class="message-content">
                                    <div class="message-bubble">
                                        ${escapeHtml(msg.message)}
                                    </div>
                                </div>
                            `;
                        } else {
                            messageContent = `
                                <div class="user-avatar-small">
                                    ${escapeHtml(senderName.substring(0, 2).toUpperCase())}
                                </div>
                                <div class="message-content">
                                    <div class="message-header">
                                        <span class="message-sender">
                                            ${isOwn ? 'You' : escapeHtml(senderName)}
                                        </span>
                                        <span class="message-time">${messageTime}</span>
                                    </div>
                                    <div class="message-bubble">
                                        ${escapeHtml(msg.message)}
                                        ${fileHtml}
                                    </div>
                                </div>
                            `;
                        }
                        
                        messageDiv.innerHTML = messageContent;
                        chatMessages.appendChild(messageDiv);
                    });
                    
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            } catch (error) {
                console.error('Error updating chat:', error);
            }
        }
        
        // TASK FUNCTIONS 
        let selectedTaskId = null;
        let selectedTaskText = '';
        
        function selectTask(element, taskId) {
            document.querySelectorAll('.task-item').forEach(task => {
                task.classList.remove('selected');
            });
            
            element.classList.add('selected');
            selectedTaskId = taskId;
            selectedTaskText = element.querySelector('.task-text').childNodes[0].nodeValue.trim();
            document.getElementById('delete-task-btn').disabled = false;
            document.getElementById('edit-task-btn').disabled = false;
        }
        
        function openEditTaskModal() {
            if (!selectedTaskId) {
                showNotification('Please select a task to edit', 'warning');
                return;
            }
            
            // Check if user is host
            if (!isHost) {
                showNotification('Only the host can edit tasks', 'error');
                return;
            }
            
            document.getElementById('edit-task-id').value = selectedTaskId;
            document.getElementById('edit-task-text').value = selectedTaskText;
            document.getElementById('edit-task-modal').classList.add('show');
            document.getElementById('edit-task-text').focus();
        }
        
        async function handleEditTask(e) {
            e.preventDefault();
            
            // Check if user is host
            if (!isHost) {
                showNotification('Only the host can edit tasks', 'error');
                document.getElementById('edit-task-modal').classList.remove('show');
                return;
            }
            
            const taskInput = this.querySelector('textarea[name="task_text"]');
            const taskText = taskInput.value.trim();
            
            if (!taskText) {
                showNotification('Please enter task description', 'warning');
                return;
            }
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    taskInput.value = '';
                    document.getElementById('edit-task-modal').classList.remove('show');
                    selectedTaskId = null;
                    document.getElementById('edit-task-btn').disabled = true;
                    await updateTasks();
                    showNotification('Task updated', 'success');
                } else {
                    showNotification(result.error || 'Failed to update task', 'error');
                }
            } catch (error) {
                console.error('Error updating task:', error);
                showNotification('Failed to update task', 'error');
            }
        }
        
        async function toggleTask(checkbox, taskId) {
            checkbox.classList.toggle('checked');
            
            try {
                const formData = new FormData();
                formData.append('toggle_task', '1');
                formData.append('task_id', taskId);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                if (response.ok) {
                    await updateTasks();
                }
            } catch (error) {
                console.error('Error toggling task:', error);
            }
        }
        
        async function handleAddTask(e) {
            e.preventDefault();
            
            // Check if user is host
            if (!isHost) {
                showNotification('Only the host can create tasks', 'error');
                document.getElementById('add-task-modal').classList.remove('show');
                return;
            }
            
            const taskInput = this.querySelector('textarea[name="task_text"]');
            const taskText = taskInput.value.trim();
            
            if (!taskText) {
                showNotification('Please enter task description', 'warning');
                return;
            }
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    taskInput.value = '';
                    document.getElementById('add-task-modal').classList.remove('show');
                    await updateTasks();
                    showNotification('Task added', 'success');
                } else {
                    showNotification(result.error || 'Failed to add task', 'error');
                }
            } catch (error) {
                console.error('Error adding task:', error);
                showNotification('Failed to add task', 'error');
            }
        }
        
        async function handleDeleteTask() {
            if (!selectedTaskId) {
                showNotification('Please select a task to delete', 'warning');
                return;
            }
            
            // Check if user is host
            if (!isHost) {
                showNotification('Only the host can delete tasks', 'error');
                return;
            }
            
            if (!confirm('Are you sure you want to delete this task?')) return;
            
            try {
                const formData = new FormData();
                formData.append('delete_task', '1');
                formData.append('task_id', selectedTaskId);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    selectedTaskId = null;
                    document.getElementById('delete-task-btn').disabled = true;
                    document.getElementById('edit-task-btn').disabled = true;
                    await updateTasks();
                    showNotification('Task deleted', 'success');
                } else {
                    showNotification(result.error || 'Failed to delete task', 'error');
                }
            } catch (error) {
                console.error('Error deleting task:', error);
                showNotification('Failed to delete task', 'error');
            }
        }
        
        // REAL TIME UPDATES 
        let chatInterval = null;
        let participantsInterval = null;
        let tasksInterval = null;
        let lastChatUpdate = 0;
        let lastParticipantsUpdate = 0;
        
        function startRealTimeUpdates() {
            participantsInterval = setInterval(updateParticipants, 3000);
            chatInterval = setInterval(updateChat, 2000);
            tasksInterval = setInterval(updateTasks, 5000);
            
            updateParticipants();
            updateChat();
            updateTasks();
        }
        
        async function updateParticipants() {
            try {
                const now = Date.now();
                if (now - lastParticipantsUpdate < 2000) return;
                
                const response = await fetch(`?room=${roomId}&ajax=get_participants&t=${now}`);
                const data = await response.json();
                
                if (data.success) {
                    lastParticipantsUpdate = now;
                    
                    // Update participant map with usernames
                    data.participants.forEach(p => {
                        participantMap[p.user_id] = p.username;
                    });
                    
                    // Update muted users list
                    if (data.muted_users) {
                        mutedUsers = data.muted_users;
                    }
                    
                    // Update device status
                    if (data.device_status) {
                        deviceStatus = data.device_status;
                    }
                    
                    // Ensure host is in map
                    if (!participantMap[hostUserId]) {
                        participantMap[hostUserId] = hostUsername;
                    }
                    
                    const count = data.participants.length;
                    document.getElementById('participant-count').textContent = count;
                    document.getElementById('modal-participant-count').textContent = count;
                    
                    // Update host online status
                    const hostOnline = data.participants.some(p => p.user_id == hostUserId);
                    isHostOnline = hostOnline || isHost;
                    
                    // If host is not online and we're not the host, add placeholder
                    if (!isHostOnline && !isHost) {
                        addHostPlaceholderTile();
                    }
                    
                    // Update participants modal
                    const participantsList = document.getElementById('participants-modal-list');
                    if (participantsList) {
                        participantsList.innerHTML = '';
                        
                        data.participants.forEach(participant => {
                            const isCurrentUser = participant.user_id == currentUserId;
                            const isParticipantHost = participant.user_id == hostUserId;
                            const isMuted = data.muted_users && data.muted_users.includes(participant.user_id);
                            
                            const item = document.createElement('div');
                            item.className = `participant-modal-item 
                                ${isCurrentUser ? 'you' : ''} 
                                ${isParticipantHost ? 'host' : ''}
                                ${isMuted ? 'muted' : ''}`;
                            item.dataset.userId = participant.user_id;
                            
                            const actionButtons = [];
                            
                            if (isHost && !isCurrentUser && !isParticipantHost) {
                                if (isMuted) {
                                    actionButtons.push(`
                                        <button class="action-btn unmute" onclick="event.stopPropagation(); unmuteUser(${participant.user_id}, '${escapeHtml(participant.username)}')" title="Unmute">
                                            <i class="fas fa-microphone"></i>
                                        </button>
                                    `);
                                } else {
                                    actionButtons.push(`
                                        <button class="action-btn mute" onclick="event.stopPropagation(); muteUser(${participant.user_id}, '${escapeHtml(participant.username)}')" title="Mute">
                                            <i class="fas fa-microphone-slash"></i>
                                        </button>
                                    `);
                                }
                                
                                actionButtons.push(`
                                    <button class="action-btn kick" onclick="event.stopPropagation(); kickUser(${participant.user_id}, '${escapeHtml(participant.username)}')" title="Kick">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                `);
                            }
                            
                            item.innerHTML = `
                                <div class="user-avatar-small">
                                    ${escapeHtml(participant.username.substring(0, 2).toUpperCase())}
                                </div>
                                <div class="participant-modal-name">
                                    ${escapeHtml(participant.username)}
                                    ${isCurrentUser ? '<span style="color: var(--primary); font-size: 12px;"> (You)</span>' : ''}
                                    ${isParticipantHost ? '<span class="host-badge">Host</span>' : ''}
                                    ${isMuted ? '<span class="muted-badge">Muted</span>' : ''}
                                </div>
                                <div class="participant-modal-actions">
                                    ${actionButtons.join('')}
                                </div>
                            `;
                            
                            participantsList.appendChild(item);
                        });
                    }
                    
                    // Update video tile usernames and device status for existing streams
                    document.querySelectorAll('.video-item').forEach(tile => {
                        const tileUserId = tile.dataset.userId;
                        if (tileUserId && tileUserId != currentUserId) {
                            let username = participantMap[tileUserId] || allUsers[tileUserId];
                            if (!username && tileUserId == hostUserId) {
                                username = hostUsername;
                            }
                            
                            if (username) {
                                const nameElement = tile.querySelector('.user-name');
                                if (nameElement && !nameElement.textContent.includes('(You)')) {
                                    const hostIndicator = (tileUserId == hostUserId) ? ' <span class="host-badge">Host</span>' : '';
                                    const mutedIndicator = (mutedUsers.includes(parseInt(tileUserId))) ? ' <span class="muted-badge-small">Muted</span>' : '';
                                    
                                    const newHtml = escapeHtml(username) + hostIndicator + mutedIndicator;
                                    nameElement.innerHTML = newHtml;
                                }
                                
                                const avatarElement = tile.querySelector('.user-avatar-small');
                                if (avatarElement) {
                                    avatarElement.textContent = username.substring(0, 2).toUpperCase();
                                }
                                
                                // Update mic status based on device status and mute list
                                const userMicStatus = deviceStatus[tileUserId]?.mic ?? true;
                                const isUserMuted = mutedUsers.includes(parseInt(tileUserId));
                                
                                if (isUserMuted || !userMicStatus) {
                                    tile.classList.add('muted');
                                    const micStatus = tile.querySelector('.mic-status');
                                    if (micStatus) {
                                        micStatus.classList.add('muted');
                                        micStatus.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                                    }
                                } else {
                                    tile.classList.remove('muted');
                                    const micStatus = tile.querySelector('.mic-status');
                                    if (micStatus) {
                                        micStatus.classList.remove('muted');
                                        micStatus.innerHTML = '<i class="fas fa-microphone"></i>';
                                    }
                                }
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Error updating participants:', error);
            }
        }
        
        async function updateChat() {
            try {
                const now = Date.now();
                if (now - lastChatUpdate < 1000) return;
                
                const response = await fetch(`?room=${roomId}&ajax=get_chat_messages&t=${now}`);
                const data = await response.json();
                
                if (data.success) {
                    lastChatUpdate = now;
                    
                    const chatMessages = document.getElementById('chat-messages');
                    if (!chatMessages) return;
                    
                    if (!data.messages || data.messages.length === 0) {
                        chatMessages.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        `;
                        return;
                    }
                    
                    const currentMessageCount = chatMessages.querySelectorAll('.message').length;
                    if (currentMessageCount === data.messages.length) return;
                    
                    chatMessages.innerHTML = '';
                    
                    data.messages.forEach(msg => {
                        const isOwn = msg.user_id == currentUserId;
                        const isSystem = msg.is_system_message == 1;
                        const senderName = msg.sender_name || msg.username || allUsers[msg.user_id] || 'User';
                        const messageTime = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${isOwn ? 'own' : ''} ${isSystem ? 'system' : ''}`;
                        
                        let fileHtml = '';
                        if (msg.file_name && msg.file_path) {
                            const previewType = getFilePreviewTypeClient(msg.file_name);
                            const fileIcon = getFileIconClient(msg.file_name);
                            
                            if (previewType === 'image') {
                                fileHtml = `
                                    <div class="file-preview-chat image">
                                        <img src="${escapeHtml(msg.file_path)}" 
                                             alt="${escapeHtml(msg.file_name)}"
                                             onclick="openFilePreview('${escapeHtml(msg.file_path)}', '${escapeHtml(msg.file_name)}')">
                                        <button class="file-download-btn" 
                                                onclick="downloadFile('${escapeHtml(msg.file_path)}', '${escapeHtml(msg.file_name)}')">
                                            <i class="fas fa-download"></i> Download (${formatFileSize(msg.file_size || 0)})
                                        </button>
                                    </div>
                                `;
                            } else if (previewType === 'pdf') {
                                fileHtml = `
                                    <div class="file-preview-chat pdf">
                                        <iframe src="${escapeHtml(msg.file_path)}#view=fitH"></iframe>
                                        <button class="file-download-btn" 
                                                onclick="downloadFile('${escapeHtml(msg.file_path)}', '${escapeHtml(msg.file_name)}')">
                                            <i class="fas fa-download"></i> Download (${formatFileSize(msg.file_size || 0)})
                                        </button>
                                    </div>
                                `;
                            } else {
                                fileHtml = `
                                    <div class="file-preview-chat other">
                                        <div class="document-icon">
                                            <i class="fas ${fileIcon}"></i>
                                        </div>
                                        <div style="margin-bottom: 10px;">${escapeHtml(msg.file_name)}</div>
                                        <button class="file-download-btn" 
                                                onclick="downloadFile('${escapeHtml(msg.file_path)}', '${escapeHtml(msg.file_name)}')">
                                            <i class="fas fa-download"></i> Download (${formatFileSize(msg.file_size || 0)})
                                        </button>
                                    </div>
                                `;
                            }
                        }
                        
                        let messageContent = '';
                        if (isSystem) {
                            messageContent = `
                                <div class="message-content">
                                    <div class="message-bubble">
                                        ${escapeHtml(msg.message)}
                                    </div>
                                </div>
                            `;
                        } else {
                            messageContent = `
                                <div class="user-avatar-small">
                                    ${escapeHtml(senderName.substring(0, 2).toUpperCase())}
                                </div>
                                <div class="message-content">
                                    <div class="message-header">
                                        <span class="message-sender">
                                            ${isOwn ? 'You' : escapeHtml(senderName)}
                                        </span>
                                        <span class="message-time">${messageTime}</span>
                                    </div>
                                    <div class="message-bubble">
                                        ${escapeHtml(msg.message)}
                                        ${fileHtml}
                                    </div>
                                </div>
                            `;
                        }
                        
                        messageDiv.innerHTML = messageContent;
                        chatMessages.appendChild(messageDiv);
                    });
                    
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            } catch (error) {
                console.error('Error updating chat:', error);
            }
        }
        
        function getFilePreviewTypeClient(fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) return 'image';
            if (['pdf'].includes(ext)) return 'pdf';
            if (['mp4', 'webm', 'ogg'].includes(ext)) return 'video';
            if (['mp3', 'wav', 'ogg'].includes(ext)) return 'audio';
            if (['doc', 'docx', 'txt', 'pptx', 'xlsx', 'csv'].includes(ext)) return 'document';
            return 'other';
        }
        
        function getFileIconClient(fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            const icons = {
                'jpg': 'fa-file-image', 'jpeg': 'fa-file-image', 'png': 'fa-file-image', 
                'gif': 'fa-file-image', 'pdf': 'fa-file-pdf', 'doc': 'fa-file-word', 
                'docx': 'fa-file-word', 'txt': 'fa-file-alt', 'zip': 'fa-file-archive',
                'mp4': 'fa-file-video', 'mp3': 'fa-file-audio', 'pptx': 'fa-file-powerpoint',
                'xlsx': 'fa-file-excel', 'csv': 'fa-file-excel'
            };
            return icons[ext] || 'fa-file';
        }
        
        async function updateTasks() {
            try {
                const response = await fetch(`?room=${roomId}&ajax=get_tasks&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success) {
                    const tasksList = document.getElementById('tasks-list');
                    if (!tasksList) return;
                    
                    if (!data.tasks || data.tasks.length === 0) {
                        tasksList.innerHTML = `
                            <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                                <i class="fas fa-clipboard-list" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                                <p>No tasks yet. Add your first task!</p>
                            </div>
                        `;
                        return;
                    }
                    
                    tasksList.innerHTML = '';
                    data.tasks.forEach(task => {
                        const taskDiv = document.createElement('div');
                        taskDiv.className = `task-item ${selectedTaskId == task.id ? 'selected' : ''}`;
                        taskDiv.dataset.taskId = task.id;
                        taskDiv.onclick = () => selectTask(taskDiv, task.id);
                        taskDiv.innerHTML = `
                            <div class="task-checkbox ${task.is_completed ? 'checked' : ''}" onclick="event.stopPropagation(); toggleTask(this, ${task.id})">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="task-text">
                                ${escapeHtml(task.task_text)}
                                <div class="task-created-by">
                                    Added by: ${escapeHtml(task.created_by_name || 'Unknown')}
                                </div>
                            </div>
                        `;
                        tasksList.appendChild(taskDiv);
                    });
                }
            } catch (error) {
                console.error('Error updating tasks:', error);
            }
        }
        
        // TIMER 
        let meetingDuration = 0;
        let timerInterval = null;
        
        function startTimer() {
            updateTimer();
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                meetingDuration++;
                updateTimer();
            }, 1000);
        }
        
        function updateTimer() {
            const hours = Math.floor(meetingDuration / 3600);
            const minutes = Math.floor((meetingDuration % 3600) / 60);
            const seconds = meetingDuration % 60;
            document.getElementById('timer-display').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        // FILE FUNCTIONS 
        function downloadFile(filePath, fileName) {
            const link = document.createElement('a');
            link.href = filePath;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function openFilePreview(filePath, fileName) {
            const modal = document.getElementById('file-preview-modal');
            const modalBody = document.getElementById('file-preview-modal-body');
            const title = document.getElementById('file-preview-title');
            
            title.textContent = fileName;
            
            const ext = fileName.split('.').pop().toLowerCase();
            const imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
            const pdfTypes = ['pdf'];
            const videoTypes = ['mp4', 'webm', 'ogg'];
            const audioTypes = ['mp3', 'wav', 'ogg'];
            
            let content = '';
            
            if (imageTypes.includes(ext)) {
                content = `<img src="${filePath}" alt="${fileName}" style="max-width: 100%; max-height: 100%;">`;
            } else if (pdfTypes.includes(ext)) {
                content = `<iframe src="${filePath}" style="width: 100%; height: 100%; border: none;"></iframe>`;
            } else if (videoTypes.includes(ext)) {
                content = `
                    <video controls style="width: 100%; max-height: 100%;">
                        <source src="${filePath}" type="video/${ext === 'mp4' ? 'mp4' : ext === 'webm' ? 'webm' : 'ogg'}">
                    </video>
                `;
            } else if (audioTypes.includes(ext)) {
                content = `
                    <audio controls style="width: 100%; margin-top: 50px;">
                        <source src="${filePath}" type="audio/${ext === 'mp3' ? 'mpeg' : ext === 'wav' ? 'wav' : 'ogg'}">
                    </audio>
                    <div style="text-align: center; margin-top: 20px;">${escapeHtml(fileName)}</div>
                `;
            } else {
                content = `
                    <div style="text-align: center; padding: 50px;">
                        <i class="fas fa-file" style="font-size: 48px; color: var(--primary); margin-bottom: 20px;"></i>
                        <div style="margin-bottom: 20px;">${escapeHtml(fileName)}</div>
                        <button class="btn btn-primary" onclick="downloadFile('${filePath}', '${escapeHtml(fileName)}')">
                            <i class="fas fa-download"></i> Download File
                        </button>
                    </div>
                `;
            }
            
            modalBody.innerHTML = content;
            modal.classList.add('show');
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0 || bytes === null) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // MEETING FUNCTIONS 
        async function leaveMeeting(isKicked = false) {
            // Don't show confirmation if kicked
            if (!isKicked && !confirm('Are you sure you want to leave the meeting?')) {
                return;
            }
            
            try {
                // Clean up media streams
                if (localStream) {
                    localStream.getTracks().forEach(track => track.stop());
                    localStream = null;
                }
                
                if (screenStream) {
                    screenStream.getTracks().forEach(track => track.stop());
                    screenStream = null;
                }
                
                // Close all peer connections
                Object.values(calls).forEach(call => {
                    if (call && call.close) call.close();
                });
                calls = {};
                
                // Destroy peer connection
                if (peer) {
                    peer.destroy();
                    peer = null;
                }
                
                // Clear all intervals
                if (peerCheckInterval) clearInterval(peerCheckInterval);
                if (timerInterval) clearInterval(timerInterval);
                if (chatInterval) clearInterval(chatInterval);
                if (participantsInterval) clearInterval(participantsInterval);
                if (tasksInterval) clearInterval(tasksInterval);
                if (signalingPollInterval) clearInterval(signalingPollInterval);
                if (kickedCheckInterval) clearInterval(kickedCheckInterval);
                if (heartbeatInterval) clearInterval(heartbeatInterval);
                
                // Notify server that user is leaving
                const formData = new FormData();
                formData.append('action', 'leave_room');
                
                await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                await fetch(`?room=${roomId}&ajax=leave_meeting&t=${Date.now()}`);
                
                // Redirect to dashboard
                window.location.href = 'dashboard.php';
            } catch (error) {
                console.error('Error leaving meeting:', error);
                window.location.href = 'dashboard.php';
            }
        }
        
        // NOTIFICATIONS 
        let notificationCounter = 0;
        
        function showNotification(message, type = 'info') {
            const container = document.getElementById('notification-container');
            const notificationId = `notification-${Date.now()}-${notificationCounter++}`;
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const notification = document.createElement('div');
            notification.id = notificationId;
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas ${icons[type]} icon"></i>
                <span class="message">${escapeHtml(message)}</span>
                <button class="close-btn" onclick="document.getElementById('${notificationId}').remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(notification);
            
            setTimeout(() => {
                const notif = document.getElementById(notificationId);
                if (notif) {
                    notif.style.animation = 'slideOutRight 0.3s ease forwards';
                    setTimeout(() => notif.remove(), 300);
                }
            }, 5000);
        }
        
        // UTILITY FUNCTIONS 
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        //  MAIN EVENT LISTENERS 
        function initMainEventListeners() {
            document.getElementById('toggle-mic').addEventListener('click', toggleMic);
            document.getElementById('toggle-camera').addEventListener('click', toggleCamera);
            document.getElementById('share-screen').addEventListener('click', shareScreen);
            document.getElementById('leave-meeting').addEventListener('click', () => leaveMeeting(false));
            
            document.getElementById('audio-output-control').addEventListener('click', function() {
                document.getElementById('audio-device-selector').classList.toggle('show');
            });
            
            document.addEventListener('click', function(event) {
                const audioSelector = document.getElementById('audio-device-selector');
                const audioBtn = document.getElementById('audio-output-control');
                if (!audioBtn.contains(event.target) && !audioSelector.contains(event.target)) {
                    audioSelector.classList.remove('show');
                }
            });
            
            document.getElementById('show-participants').addEventListener('click', function() {
                document.getElementById('participants-modal').classList.add('show');
                updateParticipants();
            });
            
            document.getElementById('close-participants-modal').addEventListener('click', function() {
                document.getElementById('participants-modal').classList.remove('show');
            });
            
            document.getElementById('close-file-preview-modal').addEventListener('click', function() {
                document.getElementById('file-preview-modal').classList.remove('show');
            });
            
            document.getElementById('show-meeting-details').addEventListener('click', function() {
                showMeetingDetails();
            });
            
            document.getElementById('close-meeting-details-modal').addEventListener('click', function() {
                closeMeetingDetails();
            });
            
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('participants-modal')) {
                    event.target.classList.remove('show');
                }
                if (event.target.classList.contains('file-preview-modal')) {
                    event.target.classList.remove('show');
                }
                if (event.target.classList.contains('add-task-modal')) {
                    event.target.classList.remove('show');
                }
                if (event.target.classList.contains('edit-task-modal')) {
                    event.target.classList.remove('show');
                }
                if (event.target.classList.contains('meeting-details-modal')) {
                    event.target.classList.remove('show');
                }
            });
            
            document.getElementById('chat-form').addEventListener('submit', handleChatSubmit);
            document.getElementById('file-upload').addEventListener('change', handleFileSelect);
            document.getElementById('file-remove-btn').addEventListener('click', removeFile);
            
            document.getElementById('add-task-btn').addEventListener('click', function() {
                if (!isHost) {
                    showNotification('Only the host can create tasks', 'error');
                    return;
                }
                document.getElementById('add-task-modal').classList.add('show');
                document.querySelector('#add-task-form textarea').focus();
            });
            
            document.getElementById('edit-task-btn').addEventListener('click', function() {
                openEditTaskModal();
            });
            
            document.getElementById('close-add-task-modal').addEventListener('click', function() {
                document.getElementById('add-task-modal').classList.remove('show');
            });
            
            document.getElementById('close-edit-task-modal').addEventListener('click', function() {
                document.getElementById('edit-task-modal').classList.remove('show');
            });
            
            document.getElementById('add-task-form').addEventListener('submit', handleAddTask);
            document.getElementById('edit-task-form').addEventListener('submit', handleEditTask);
            document.getElementById('delete-task-btn').addEventListener('click', handleDeleteTask);
            
            window.addEventListener('beforeunload', function(e) {
                if (navigator.sendBeacon) {
                    const formData = new FormData();
                    formData.append('action', 'leave_room');
                    navigator.sendBeacon('', formData);
                    
                    const beaconData = new FormData();
                    beaconData.append('action', 'leave_room');
                    beaconData.append('room', roomId);
                    navigator.sendBeacon('signaling.php', beaconData);
                }
                if (localStream) localStream.getTracks().forEach(track => track.stop());
                if (screenStream) screenStream.getTracks().forEach(track => track.stop());
                if (peer) peer.destroy();
            });
        }
    </script>
</body>
</html>