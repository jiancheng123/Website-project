<?php
// get_user_profile.php - API endpoint to get user profile data
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$profile_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $current_user_id;

// First, check if last_activity column exists, if not add it
$check_column_sql = "SHOW COLUMNS FROM users LIKE 'last_activity'";
$check_column = $conn->query($check_column_sql);
if ($check_column && $check_column->num_rows == 0) {
    $add_column_sql = "ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL";
    $conn->query($add_column_sql);
}

// Check if education_level column exists, if not add it
$check_education_column = $conn->query("SHOW COLUMNS FROM users LIKE 'education_level'");
if ($check_education_column && $check_education_column->num_rows == 0) {
    $add_education_sql = "ALTER TABLE users ADD COLUMN education_level VARCHAR(50) NULL DEFAULT NULL AFTER email";
    $conn->query($add_education_sql);
}

// Get user data including last_activity and education_level
$sql = "SELECT id, username, email, role, profile_photo, education_level, program, semester, year, student_id, created_at, last_activity 
        FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Format member since (keep as month year for display)
$user['member_since'] = date('F Y', strtotime($user['created_at']));

// Format last_activity - keep as full datetime for JavaScript formatting
if ($user['last_activity']) {
    $user['last_activity'] = date('Y-m-d H:i:s', strtotime($user['last_activity']));
} else {
    $user['last_activity'] = null;
}

// Format semester display
$semester_display = '';
if (!empty($user['semester']) && !empty($user['year'])) {
    $semester_display = $user['semester'] . ' ' . $user['year'];
} elseif (!empty($user['semester'])) {
    $semester_display = $user['semester'] . ' Intake';
} elseif (!empty($user['year'])) {
    $semester_display = 'Year: ' . $user['year'];
}
$user['semester_display'] = $semester_display;

// Get friends count 
$friends_count = 0;
$check_friends_table = $conn->query("SHOW TABLES LIKE 'friends'");
if ($check_friends_table && $check_friends_table->num_rows > 0) {
    $friends_sql = "SELECT COUNT(*) as count FROM friends 
                    WHERE ((user_id = ? OR friend_id = ?)) 
                    AND status = 'accepted'";
    $friends_stmt = $conn->prepare($friends_sql);
    if ($friends_stmt) {
        $friends_stmt->bind_param("ii", $profile_id, $profile_id);
        $friends_stmt->execute();
        $friends_result = $friends_stmt->get_result();
        $friends_data = $friends_result->fetch_assoc();
        $friends_count = $friends_data['count'] ?? 0;
        $friends_stmt->close();
    }
}
$user['friends_count'] = $friends_count;

// Get groups count 
$groups_count = 0;
$check_groups_table = $conn->query("SHOW TABLES LIKE 'group_members'");
$check_groups_main_table = $conn->query("SHOW TABLES LIKE 'groups'");

if ($check_groups_table && $check_groups_table->num_rows > 0 && 
    $check_groups_main_table && $check_groups_main_table->num_rows > 0) {
    
    // Check if status column exists in group_members
    $check_status_column = $conn->query("SHOW COLUMNS FROM group_members LIKE 'status'");
    $has_status_column = ($check_status_column && $check_status_column->num_rows > 0);
    
    if ($has_status_column) {
        // Only count groups where user is an active member (status = 'active' or 'member')
        // and the group still exists
        $groups_sql = "SELECT COUNT(*) as count FROM group_members gm 
                       INNER JOIN `groups` g ON gm.group_id = g.id 
                       WHERE gm.user_id = ? AND gm.status IN ('active', 'member', 'approved')";
    } else {
        // If no status column, assume all records are active members
        // But still verify the group exists
        $groups_sql = "SELECT COUNT(*) as count FROM group_members gm 
                       INNER JOIN `groups` g ON gm.group_id = g.id 
                       WHERE gm.user_id = ?";
    }
    
    $groups_stmt = $conn->prepare($groups_sql);
    if ($groups_stmt) {
        $groups_stmt->bind_param("i", $profile_id);
        $groups_stmt->execute();
        $groups_result = $groups_stmt->get_result();
        $groups_data = $groups_result->fetch_assoc();
        $groups_count = $groups_data['count'] ?? 0;
        $groups_stmt->close();
    }
}
$user['groups_count'] = $groups_count;

// Get meetings hosted count
$meetings_count = 0;
$check_meetings_table = $conn->query("SHOW TABLES LIKE 'meetings'");
if ($check_meetings_table && $check_meetings_table->num_rows > 0) {
    $meetings_sql = "SELECT COUNT(*) as count FROM meetings WHERE host_id = ?";
    $meetings_stmt = $conn->prepare($meetings_sql);
    if ($meetings_stmt) {
        $meetings_stmt->bind_param("i", $profile_id);
        $meetings_stmt->execute();
        $meetings_result = $meetings_stmt->get_result();
        $meetings_data = $meetings_result->fetch_assoc();
        $meetings_count = $meetings_data['count'] ?? 0;
        $meetings_stmt->close();
    }
}
$user['meetings_hosted'] = $meetings_count;

// Check friend status if not current user
if ($profile_id != $current_user_id) {
    $friend_status = 'none';
    $is_pending_from_me = false;
    
    $check_friends_table = $conn->query("SHOW TABLES LIKE 'friends'");
    if ($check_friends_table && $check_friends_table->num_rows > 0) {
        $status_sql = "SELECT user_id, status FROM friends 
                       WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
        $status_stmt = $conn->prepare($status_sql);
        if ($status_stmt) {
            $status_stmt->bind_param("iiii", $current_user_id, $profile_id, $profile_id, $current_user_id);
            $status_stmt->execute();
            $status_result = $status_stmt->get_result();
            
            if ($status_result->num_rows > 0) {
                $status_row = $status_result->fetch_assoc();
                $friend_status = $status_row['status'];
                $is_pending_from_me = ($status_row['user_id'] == $current_user_id && $friend_status == 'pending');
            }
            $status_stmt->close();
        }
    }
    
    $user['friend_status'] = $friend_status;
    $user['is_pending_from_me'] = $is_pending_from_me;
}

// Build profile photo URL
if (!empty($user['profile_photo'])) {
    // Check if file exists
    $photo_path = 'uploads/profile_photos/' . $user['profile_photo'];
    if (file_exists($photo_path)) {
        $user['profile_photo_url'] = $photo_path;
    } else {
        $user['profile_photo_url'] = null;
    }
} else {
    $user['profile_photo_url'] = null;
}

// Remove sensitive data
unset($user['created_at']);
unset($user['profile_photo']);

echo json_encode(['success' => true, 'user' => $user]);
?>