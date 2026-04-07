<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fypdb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// UTILITY FUNCTIONS 

// Function to sanitize input
function sanitize_input($data, $conn = null) {
    if ($conn) {
        $data = $conn->real_escape_string($data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to check if user is regular user
function is_user() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

// Function to require login (redirect if not logged in)
function require_login() {
    if (!is_logged_in()) {
        // Store current page for redirect after login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

// Function to require admin access
function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: dashboard.php');
        exit();
    }
}

// Function to get user data
function get_user_data($conn, $user_id) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to generate user avatar initials
function get_user_initials($user) {
    if (isset($user['name']) && !empty($user['name'])) {
        $name_parts = explode(' ', $user['name']);
        if (count($name_parts) >= 2) {
            return strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
        }
        return strtoupper(substr($user['name'], 0, 2));
    } elseif (isset($user['username']) && !empty($user['username'])) {
        return strtoupper(substr($user['username'], 0, 2));
    }
    return 'JM'; // JustMeet default
}

// Function to format time for display
function format_time($time_string) {
    if (empty($time_string) || $time_string == '00:00:00') return '';
    $date = new DateTime($time_string);
    return $date->format('g:i A');
}

// Function to set success message
function set_success_message($message) {
    $_SESSION['success_message'] = $message;
}

// Function to set error message
function set_error_message($message) {
    $_SESSION['error_message'] = $message;
}

// Function to display messages
function display_messages() {
    $html = '';
    
    if (isset($_SESSION['success_message'])) {
        $html .= '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
        unset($_SESSION['success_message']);
    }
    
    if (isset($_SESSION['error_message'])) {
        $html .= '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
        unset($_SESSION['error_message']);
    }
    
    return $html;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate password strength
function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';
    return preg_match($pattern, $password);
}

// Function to check if username exists
function username_exists($conn, $username) {
    $sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to check if email exists
function email_exists($conn, $email) {
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to log activity
function log_activity($conn, $user_id, $activity, $details = '') {
    // First check if activity_log table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'activity_log'");
    if ($check_table && $check_table->num_rows > 0) {
        $sql = "INSERT INTO activity_log (user_id, activity, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $user_id, $activity, $details, 
                         $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        $stmt->execute();
        $stmt->close();
    }
}

// Function to get meeting status
function get_meeting_status($meeting_date, $start_time, $end_time) {
    $now = time();
    $meeting_start = strtotime($meeting_date . ' ' . $start_time);
    $meeting_end = strtotime($meeting_date . ' ' . $end_time);
    
    if ($now > $meeting_end) {
        return 'completed';
    } elseif ($now >= $meeting_start && $now <= $meeting_end) {
        return 'ongoing';
    } else {
        return 'upcoming';
    }
}

// Function to get initials from username
function get_initials_from_username($username) {
    if (strlen($username) >= 2) {
        return strtoupper(substr($username, 0, 2));
    }
    return strtoupper($username . $username);
}

// MEETING ROOM TABLES SETUP 
function setup_meeting_room_tables($conn) {
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows == 0) {
        // Create users table
        $conn->query("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE
        )");
        
        // Create default admin user (password: admin123)
        $hashed_password = md5('admin123');
        $conn->query("INSERT INTO users (username, email, password, role) 
                      VALUES ('admin', 'admin@justmeet.com', '$hashed_password', 'admin')");
        
        // Create default test user (password: user123)
        $user_password = md5('user123');
        $conn->query("INSERT INTO users (username, email, password, role) 
                      VALUES ('user', 'user@justmeet.com', '$user_password', 'user')");
    }
    
    // Check if meetings table exists - COMPLETE STRUCTURE
    $result = $conn->query("SHOW TABLES LIKE 'meetings'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE meetings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(50) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            host_id INT NOT NULL,
            host_name VARCHAR(100),
            meeting_date DATE,
            start_time TIME,
            end_time TIME,
            status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
            recording_status ENUM('stopped', 'recording', 'paused') DEFAULT 'stopped',
            is_password_protected BOOLEAN DEFAULT FALSE,
            password VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_room_id (room_id),
            INDEX idx_host_id (host_id),
            INDEX idx_status (status),
            FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
    }
    
    // Check if meeting_participants table exists
    $result = $conn->query("SHOW TABLES LIKE 'meeting_participants'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE meeting_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id VARCHAR(50) NOT NULL,
            participant_id INT NOT NULL,
            username VARCHAR(100),
            status VARCHAR(20) DEFAULT 'online',
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            left_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_meeting (meeting_id),
            INDEX idx_participant (participant_id),
            INDEX idx_status (status),
            FOREIGN KEY (participant_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
    }
    
    // Check if meeting_participant_settings table exists
    $result = $conn->query("SHOW TABLES LIKE 'meeting_participant_settings'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE meeting_participant_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(50) NOT NULL,
            user_id INT NOT NULL,
            device_type VARCHAR(20) NOT NULL,
            status BOOLEAN DEFAULT TRUE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_device (room_id, user_id, device_type),
            INDEX idx_room_id (room_id),
            INDEX idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
    }
    
    // Check if chat_messages table exists
    $result = $conn->query("SHOW TABLES LIKE 'chat_messages'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(50) NOT NULL,
            user_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            file_path VARCHAR(255) DEFAULT NULL,
            file_name VARCHAR(255) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room_id (room_id),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
    }
    
    // Check if meeting_tasks table exists
    $result = $conn->query("SHOW TABLES LIKE 'meeting_tasks'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE meeting_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(50) NOT NULL,
            task_text TEXT NOT NULL,
            created_by INT NOT NULL,
            is_completed BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room_id (room_id),
            INDEX idx_created_by (created_by),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
    }
    
    // Check if peer_connections table exists
    $result = $conn->query("SHOW TABLES LIKE 'peer_connections'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE peer_connections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(50) NOT NULL,
            user_id INT NOT NULL,
            peer_id VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_room (room_id, user_id),
            INDEX idx_room_id (room_id),
            INDEX idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
    }
    
    // Check if signaling table exists
    $result = $conn->query("SHOW TABLES LIKE 'signaling'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE signaling (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(50) NOT NULL,
            from_user_id INT NOT NULL,
            to_user_id INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            data TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room_to (room_id, to_user_id),
            INDEX idx_created (created_at),
            INDEX idx_from_user (from_user_id),
            INDEX idx_to_user (to_user_id),
            FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
    }
    
    // Check if activity_log table exists
    $result = $conn->query("SHOW TABLES LIKE 'activity_log'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            activity VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->query($sql);
    }
    
    // Check if uploads directory exists
    $upload_dir = 'uploads/chat_files/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
}

// Run the table setup
setup_meeting_room_tables($conn);

// Function to generate unique room ID
function generate_room_id() {
    return uniqid() . '-' . bin2hex(random_bytes(4));
}

// Function to create a new meeting
function create_meeting($conn, $host_id, $title, $description = '', $meeting_date = null, $start_time = null, $end_time = null, $password = null) {
    $room_id = generate_room_id();
    $is_password_protected = !empty($password);
    $hashed_password = $is_password_protected ? password_hash($password, PASSWORD_DEFAULT) : null;
    
    // Get host username
    $host_data = get_user_data($conn, $host_id);
    $host_name = $host_data['username'] ?? 'Host';
    
    $sql = "INSERT INTO meetings (room_id, title, description, host_id, host_name, meeting_date, start_time, end_time, is_password_protected, password, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssissssi", $room_id, $title, $description, $host_id, $host_name, $meeting_date, $start_time, $end_time, $is_password_protected, $hashed_password);
    
    if ($stmt->execute()) {
        $meeting_id = $stmt->insert_id;
        $stmt->close();
        return ['success' => true, 'room_id' => $room_id, 'meeting_id' => $meeting_id];
    } else {
        $stmt->close();
        return ['success' => false, 'error' => $conn->error];
    }
}

// Function to get meeting by room ID
function get_meeting_by_room($conn, $room_id) {
    $sql = "SELECT m.*, u.username as host_username 
            FROM meetings m 
            JOIN users u ON m.host_id = u.id 
            WHERE m.room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $meeting = $result->fetch_assoc();
    $stmt->close();
    return $meeting;
}

// Function to verify meeting password
function verify_meeting_password($conn, $room_id, $password) {
    $sql = "SELECT password FROM meetings WHERE room_id = ? AND is_password_protected = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $meeting = $result->fetch_assoc();
    $stmt->close();
    
    if ($meeting && password_verify($password, $meeting['password'])) {
        return true;
    }
    return false;
}

// Close connection function
function close_connection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// Register shutdown function to close connection
register_shutdown_function(function() use ($conn) {
    close_connection($conn);
});
?>