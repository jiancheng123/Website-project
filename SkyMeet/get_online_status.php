<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// First check if last_activity column exists
$check_column_sql = "SHOW COLUMNS FROM users LIKE 'last_activity'";
$check_column = $conn->query($check_column_sql);
if ($check_column->num_rows == 0) {
    // Add the column if it doesn't exist
    $add_column_sql = "ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL";
    $conn->query($add_column_sql);
}

// Get all users except current user
$sql = "SELECT id, username, last_activity FROM users WHERE id != ? ORDER BY last_activity DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $last_activity = $row['last_activity'];
    $status = 'offline';
    $status_class = 'status-offline';
    $status_text = 'Offline';
    $last_active_text = 'Never';
    
    if ($last_activity) {
        $last_time = strtotime($last_activity);
        $now = time();
        $diff = $now - $last_time;
        
        // Online if active in last 60 seconds (1 minute)
        if ($diff < 60) {
            $status = 'online';
            $status_class = 'status-online';
            $status_text = 'Online';
        }
        
        // Format last active time
        if ($diff < 60) {
            $last_active_text = 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            $last_active_text = $minutes . 'm ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            $last_active_text = $hours . 'h ago';
        } else {
            $days = floor($diff / 86400);
            $last_active_text = $days . 'd ago';
        }
    }
    
    $users[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'status' => $status,
        'status_class' => $status_class,
        'status_text' => $status_text,
        'last_active_text' => $last_active_text,
        'last_activity' => $last_activity
    ];
}

echo json_encode(['success' => true, 'users' => $users]);
$stmt->close();
$conn->close();
?>