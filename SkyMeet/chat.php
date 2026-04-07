<?php
session_start();
require_once 'connect.php';
require_once 'profile_utils.php';

// DATABASE COLUMN CHECKS 
// Check if last_read_message_id column exists in group_members table
$check_column = $conn->query("SHOW COLUMNS FROM group_members LIKE 'last_read_message_id'");
if ($check_column && $check_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE group_members ADD COLUMN last_read_message_id INT DEFAULT NULL";
    $conn->query($alter_sql);
}

// Check if is_read column exists in messages table
$check_is_read = $conn->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
if ($check_is_read && $check_is_read->num_rows == 0) {
    $alter_is_read = "ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0";
    $conn->query($alter_is_read);
}

// Check if deleted column exists in messages table
$check_deleted_messages = $conn->query("SHOW COLUMNS FROM messages LIKE 'is_deleted'");
if ($check_deleted_messages && $check_deleted_messages->num_rows == 0) {
    $alter_deleted = "ALTER TABLE messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0";
    $conn->query($alter_deleted);
}

// Check if deleted column exists in group_messages table
$check_deleted_group = $conn->query("SHOW COLUMNS FROM group_messages LIKE 'is_deleted'");
if ($check_deleted_group && $check_deleted_group->num_rows == 0) {
    $alter_deleted = "ALTER TABLE group_messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0";
    $conn->query($alter_deleted);
}

// Check if deleted column exists in groups table for group deletion
$check_deleted_groups = $conn->query("SHOW COLUMNS FROM groups LIKE 'is_deleted'");
if ($check_deleted_groups && $check_deleted_groups->num_rows == 0) {
    $alter_deleted = "ALTER TABLE groups ADD COLUMN is_deleted TINYINT(1) DEFAULT 0";
    $conn->query($alter_deleted);
}

// Check if deleted_at column exists in groups table
$check_deleted_at = $conn->query("SHOW COLUMNS FROM groups LIKE 'deleted_at'");
if ($check_deleted_at && $check_deleted_at->num_rows == 0) {
    $alter_deleted_at = "ALTER TABLE groups ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL";
    $conn->query($alter_deleted_at);
}

// Check if deleted_by column exists in groups table
$check_deleted_by = $conn->query("SHOW COLUMNS FROM groups LIKE 'deleted_by'");
if ($check_deleted_by && $check_deleted_by->num_rows == 0) {
    $alter_deleted_by = "ALTER TABLE groups ADD COLUMN deleted_by INT DEFAULT NULL";
    $conn->query($alter_deleted_by);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Get current user with profile photo
$current_user = getCurrentUser($conn, $user_id);

// FIRST, CHECK IF LAST_ACTIVITY COLUMN EXISTS, IF NOT ADD IT
$check_column_sql = "SHOW COLUMNS FROM users LIKE 'last_activity'";
$check_column = $conn->query($check_column_sql);
if ($check_column && $check_column->num_rows == 0) {
    $add_column_sql = "ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL";
    $conn->query($add_column_sql);
}

// Update user's last activity time (for online status)
$update_activity_sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_activity_sql);
if ($update_stmt) {
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Get total meetings count for sidebar badge
$total_meetings_sql = "SELECT COUNT(*) as total FROM meetings WHERE host_id = ?";
$total_meetings_stmt = $conn->prepare($total_meetings_sql);
$total_meetings_stmt->bind_param("i", $user_id);
$total_meetings_stmt->execute();
$total_meetings_result = $total_meetings_stmt->get_result();
$total_meetings_data = $total_meetings_result->fetch_assoc();
$total_meetings_count = $total_meetings_data['total'] ?? 0;
$total_meetings_stmt->close();

// Get upcoming meetings count for sidebar badge
$upcoming_count_sql = "SELECT COUNT(*) as upcoming_count 
                      FROM meetings 
                      WHERE host_id = ? 
                      AND ((meeting_date > CURDATE()) 
                          OR (meeting_date = CURDATE() AND end_time > CURTIME()))";
$upcoming_count_stmt = $conn->prepare($upcoming_count_sql);
$upcoming_count_stmt->bind_param("i", $user_id);
$upcoming_count_stmt->execute();
$upcoming_count_result = $upcoming_count_stmt->get_result();
$upcoming_count_data = $upcoming_count_result->fetch_assoc();
$upcoming_meetings_count = $upcoming_count_data['upcoming_count'] ?? 0;
$upcoming_count_stmt->close();

// Create uploads directory if not exists
$upload_dir = 'uploads/chat_files/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Define max file size (40MB)
define('MAX_FILE_SIZE', 40 * 1024 * 1024); // 40MB in bytes

// Allowed file types
$allowed_file_types = [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
    'video/mp4', 'video/mpeg', 'video/quicktime',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain',
    'application/zip',
    'application/x-rar-compressed'
];

$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mpeg', 'mov', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];

// Sensitive words list for filtering
$sensitive_words = [
    'fuck', 'shit', 'asshole', 'bitch', 'cunt', 'dick', 'pussy', 'bastard',
    'nigger', 'faggot', 'retard', 'stupid', 'idiot', 'dumb', 'moron',
    'racist', 'sexist', 'hate', 'kill', 'murder', 'rape', 'terrorist',
    'bomb', 'explosive', 'weapon', 'drug', 'heroin', 'cocaine', 'meth',
    'porn', 'xxx', 'sex', 'nude', 'naked', 'whore', 'slut', 'prostitute',
    'gambling', 'casino', 'bet', 'lottery', 'scam', 'fraud', 'cheat',
    'hack', 'crack', 'pirate', 'illegal', 'unlawful', 'forbidden',
    'violence', 'abuse', 'harassment', 'bully', 'threat', 'danger'
];

// Function to filter sensitive words
function filterSensitiveWords($text, $sensitive_words) {
    $filtered_text = $text;
    foreach ($sensitive_words as $word) {
        // Case-insensitive replacement with asterisks
        $replacement = str_repeat('*', strlen($word));
        $filtered_text = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', $replacement, $filtered_text);
    }
    return $filtered_text;
}

// Function to check if text contains sensitive words
function containsSensitiveWords($text, $sensitive_words) {
    $text_lower = strtolower($text);
    foreach ($sensitive_words as $word) {
        if (strpos($text_lower, strtolower($word)) !== false) {
            return true;
        }
    }
    return false;
}

// Handle create group with character limit and sensitive word filter
if (isset($_POST['create_group'])) {
    $group_name = trim($_POST['group_name']);
    $group_description = trim($_POST['group_description']);
    $join_type = isset($_POST['join_type']) ? $_POST['join_type'] : 'invite_only';
    
    // Check character length
    $char_length = strlen($group_name);
    
    if (empty($group_name)) {
        $_SESSION['error'] = "Group name is required.";
    } elseif ($char_length > 15) {
        $_SESSION['error'] = "Group name cannot exceed 15 characters. Current length: " . $char_length;
    } elseif (containsSensitiveWords($group_name, $sensitive_words)) {
        $_SESSION['error'] = "Group name contains inappropriate words. Please choose a different name.";
    } else {
        // Filter sensitive words from description (but allow it, just filter it)
        $filtered_description = filterSensitiveWords($group_description, $sensitive_words);
        
        // Insert group with join_type
        $insert_group_sql = "INSERT INTO groups (name, description, created_by, join_type) VALUES (?, ?, ?, ?)";
        $insert_group_stmt = $conn->prepare($insert_group_sql);
        $insert_group_stmt->bind_param("ssis", $group_name, $filtered_description, $user_id, $join_type);
        
        if ($insert_group_stmt->execute()) {
            $group_id = $conn->insert_id;
            
            // Add creator as admin
            $add_creator_sql = "INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')";
            $add_creator_stmt = $conn->prepare($add_creator_sql);
            $add_creator_stmt->bind_param("ii", $group_id, $user_id);
            $add_creator_stmt->execute();
            $add_creator_stmt->close();
            
            $_SESSION['success'] = "Group created successfully!";
            header("Location: chat.php?group_id=" . $group_id);
            exit();
        }
        $insert_group_stmt->close();
    }
    
    // If there was an error, redirect back
    if (isset($_SESSION['error'])) {
        header("Location: chat.php");
        exit();
    }
}

// Handle group deletion
if (isset($_GET['delete_group'])) {
    $group_id = intval($_GET['delete_group']);
    
    // Check if user is admin of the group
    $check_admin_sql = "SELECT role FROM group_members WHERE group_id = ? AND user_id = ? AND role = 'admin'";
    $check_admin_stmt = $conn->prepare($check_admin_sql);
    $check_admin_stmt->bind_param("ii", $group_id, $user_id);
    $check_admin_stmt->execute();
    $check_admin_result = $check_admin_stmt->get_result();
    
    if ($check_admin_result->num_rows > 0) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Soft delete the group
            $delete_group_sql = "UPDATE groups SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?";
            $delete_group_stmt = $conn->prepare($delete_group_sql);
            $delete_group_stmt->bind_param("ii", $user_id, $group_id);
            
            if ($delete_group_stmt->execute()) {
                // Soft delete all group messages
                $delete_messages_sql = "UPDATE group_messages SET is_deleted = 1 WHERE group_id = ?";
                $delete_messages_stmt = $conn->prepare($delete_messages_sql);
                $delete_messages_stmt->bind_param("i", $group_id);
                $delete_messages_stmt->execute();
                $delete_messages_stmt->close();
                
                $conn->commit();
                $_SESSION['success'] = "Group deleted successfully!";
            } else {
                throw new Exception("Failed to delete group");
            }
            $delete_group_stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Failed to delete group: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "You don't have permission to delete this group.";
    }
    $check_admin_stmt->close();
    
    header("Location: chat.php");
    exit();
}

// Handle leave group
if (isset($_GET['leave_group'])) {
    $group_id = intval($_GET['leave_group']);
    
    // Check if user is the last admin
    $check_admin_sql = "SELECT COUNT(*) as admin_count FROM group_members WHERE group_id = ? AND role = 'admin'";
    $check_admin_stmt = $conn->prepare($check_admin_sql);
    $check_admin_stmt->bind_param("i", $group_id);
    $check_admin_stmt->execute();
    $check_admin_result = $check_admin_stmt->get_result();
    $admin_count = $check_admin_result->fetch_assoc()['admin_count'];
    $check_admin_stmt->close();
    
    // Check if user is admin
    $user_role_sql = "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?";
    $user_role_stmt = $conn->prepare($user_role_sql);
    $user_role_stmt->bind_param("ii", $group_id, $user_id);
    $user_role_stmt->execute();
    $user_role_result = $user_role_stmt->get_result();
    $user_role = $user_role_result->fetch_assoc()['role'] ?? '';
    $user_role_stmt->close();
    
    if ($user_role == 'admin' && $admin_count <= 1) {
        $_SESSION['error'] = "You cannot leave the group as you are the only admin. Please promote another member to admin first or delete the group.";
    } else {
        $leave_sql = "DELETE FROM group_members WHERE group_id = ? AND user_id = ?";
        $leave_stmt = $conn->prepare($leave_sql);
        $leave_stmt->bind_param("ii", $group_id, $user_id);
        
        if ($leave_stmt->execute()) {
            $_SESSION['success'] = "You have left the group!";
        }
        $leave_stmt->close();
    }
    header("Location: chat.php");
    exit();
}

// Handle invite to group
if (isset($_POST['invite_to_group'])) {
    $group_id = intval($_POST['group_id']);
    $user_to_invite_id = intval($_POST['user_id']);
    
    // Check if user is admin or group creator
    $check_admin_sql = "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?";
    $check_admin_stmt = $conn->prepare($check_admin_sql);
    $check_admin_stmt->bind_param("ii", $group_id, $user_id);
    $check_admin_stmt->execute();
    $check_admin_result = $check_admin_stmt->get_result();
    $is_admin = ($check_admin_result->num_rows > 0 && $check_admin_result->fetch_assoc()['role'] == 'admin');
    $check_admin_stmt->close();
    
    if ($is_admin) {
        // Check if user is already in group
        $check_member_sql = "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?";
        $check_member_stmt = $conn->prepare($check_member_sql);
        $check_member_stmt->bind_param("ii", $group_id, $user_to_invite_id);
        $check_member_stmt->execute();
        $check_member_result = $check_member_stmt->get_result();
        
        if ($check_member_result->num_rows == 0) {
            $invite_sql = "INSERT INTO group_members (group_id, user_id) VALUES (?, ?)";
            $invite_stmt = $conn->prepare($invite_sql);
            $invite_stmt->bind_param("ii", $group_id, $user_to_invite_id);
            
            if ($invite_stmt->execute()) {
                // Add system message about the invitation
                $get_username_sql = "SELECT username FROM users WHERE id = ?";
                $get_username_stmt = $conn->prepare($get_username_sql);
                $get_username_stmt->bind_param("i", $user_to_invite_id);
                $get_username_stmt->execute();
                $get_username_result = $get_username_stmt->get_result();
                $invited_username = $get_username_result->fetch_assoc()['username'];
                $get_username_stmt->close();
                
                $system_message = $invited_username . " has been added to the group by an admin.";
                $insert_system_sql = "INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)";
                $insert_system_stmt = $conn->prepare($insert_system_sql);
                $insert_system_stmt->bind_param("iis", $group_id, $user_id, $system_message);
                $insert_system_stmt->execute();
                $insert_system_stmt->close();
                
                $_SESSION['success'] = "User has been added to the group!";
            } else {
                $_SESSION['error'] = "Failed to add user to group.";
            }
            $invite_stmt->close();
        } else {
            $_SESSION['error'] = "User is already in the group.";
        }
        $check_member_stmt->close();
    } else {
        $_SESSION['error'] = "You don't have permission to invite users.";
    }
    
    header("Location: chat.php?group_id=" . $group_id);
    exit();
}

// Handle remove friend
if (isset($_POST['remove_friend'])) {
    $friend_id = intval($_POST['friend_id']);
    
    $remove_sql = "DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
    $remove_stmt = $conn->prepare($remove_sql);
    $remove_stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    
    if ($remove_stmt->execute()) {
        $_SESSION['success'] = "Friend removed successfully!";
    } else {
        $_SESSION['error'] = "Failed to remove friend.";
    }
    $remove_stmt->close();
    
    header("Location: chat.php");
    exit();
}

// Handle reject friend request
if (isset($_POST['reject_friend'])) {
    $friend_id = intval($_POST['friend_id']);
    
    $reject_sql = "DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
    $reject_stmt = $conn->prepare($reject_sql);
    $reject_stmt->bind_param("ii", $friend_id, $user_id);
    
    if ($reject_stmt->execute()) {
        $_SESSION['success'] = "Friend request rejected.";
    } else {
        $_SESSION['error'] = "Failed to reject friend request.";
    }
    $reject_stmt->close();
    
    header("Location: chat.php");
    exit();
}

// Handle search for users
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search_users') {
    header('Content-Type: application/json');
    
    $search_term = isset($_GET['term']) ? '%' . $_GET['term'] . '%' : '%';
    
    $search_sql = "SELECT id, username, email, profile_photo FROM users 
                   WHERE id != ? AND username != 'admin' AND email NOT LIKE '%admin%'
                   AND (username LIKE ? OR email LIKE ?)
                   ORDER BY username LIMIT 20";
    $search_stmt = $conn->prepare($search_sql);
    $search_stmt->bind_param("iss", $user_id, $search_term, $search_term);
    $search_stmt->execute();
    $search_result = $search_stmt->get_result();
    
    $users = [];
    while ($user = $search_result->fetch_assoc()) {
        // Check friendship status
        $friend_status_sql = "SELECT status FROM friends 
                              WHERE (user_id = ? AND friend_id = ?) 
                              OR (user_id = ? AND friend_id = ?)";
        $friend_status_stmt = $conn->prepare($friend_status_sql);
        $friend_status_stmt->bind_param("iiii", $user_id, $user['id'], $user['id'], $user_id);
        $friend_status_stmt->execute();
        $friend_status_result = $friend_status_stmt->get_result();
        
        $user['friend_status'] = 'not_friend';
        $user['is_pending_from_me'] = false;
        $user['is_pending_to_me'] = false;
        
        if ($friend_status_result->num_rows > 0) {
            $friend_status = $friend_status_result->fetch_assoc();
            $user['friend_status'] = $friend_status['status'];
            
            // Check if pending request was sent by current user
            if ($friend_status['status'] == 'pending') {
                $check_sender_sql = "SELECT user_id FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
                $check_sender_stmt = $conn->prepare($check_sender_sql);
                $check_sender_stmt->bind_param("ii", $user_id, $user['id']);
                $check_sender_stmt->execute();
                $sender_result = $check_sender_stmt->get_result();
                $user['is_pending_from_me'] = ($sender_result->num_rows > 0);
                $check_sender_stmt->close();
                
                $check_receiver_sql = "SELECT user_id FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
                $check_receiver_stmt = $conn->prepare($check_receiver_sql);
                $check_receiver_stmt->bind_param("ii", $user['id'], $user_id);
                $check_receiver_stmt->execute();
                $receiver_result = $check_receiver_stmt->get_result();
                $user['is_pending_to_me'] = ($receiver_result->num_rows > 0);
                $check_receiver_stmt->close();
            }
        }
        $friend_status_stmt->close();
        
        $users[] = $user;
    }
    $search_stmt->close();
    
    echo json_encode(['success' => true, 'users' => $users]);
    exit();
}

// Handle AJAX request for pending friend requests
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_pending_requests') {
    header('Content-Type: application/json');
    
    // Get pending friend requests sent to current user
    $pending_requests_sql = "SELECT u.id, u.username, u.email, u.profile_photo FROM friends f
                             JOIN users u ON f.user_id = u.id
                             WHERE f.friend_id = ? AND f.status = 'pending' AND u.username != 'admin' AND u.email NOT LIKE '%admin%'";
    $pending_stmt = $conn->prepare($pending_requests_sql);
    $pending_stmt->bind_param("i", $user_id);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    
    $pending_requests = [];
    while ($request = $pending_result->fetch_assoc()) {
        $pending_requests[] = $request;
    }
    $pending_stmt->close();
    
    echo json_encode(['success' => true, 'requests' => $pending_requests, 'count' => count($pending_requests)]);
    exit();
}

// Handle AJAX accept friend request
if (isset($_POST['ajax']) && $_POST['ajax'] == 'accept_friend') {
    header('Content-Type: application/json');
    
    $friend_id = intval($_POST['friend_id']);
    
    $accept_sql = "UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?";
    $accept_stmt = $conn->prepare($accept_sql);
    $accept_stmt->bind_param("ii", $friend_id, $user_id);
    
    if ($accept_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend request accepted!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to accept friend request.']);
    }
    $accept_stmt->close();
    exit();
}

// Handle AJAX reject friend request
if (isset($_POST['ajax']) && $_POST['ajax'] == 'reject_friend') {
    header('Content-Type: application/json');
    
    $friend_id = intval($_POST['friend_id']);
    
    $reject_sql = "DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
    $reject_stmt = $conn->prepare($reject_sql);
    $reject_stmt->bind_param("ii", $friend_id, $user_id);
    
    if ($reject_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend request rejected.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject friend request.']);
    }
    $reject_stmt->close();
    exit();
}

// Handle AJAX add friend request
if (isset($_POST['ajax']) && $_POST['ajax'] == 'add_friend') {
    header('Content-Type: application/json');
    
    $friend_id = intval($_POST['friend_id']);
    
    // Check if friendship already exists
    $check_sql = "SELECT id, status FROM friends 
                  WHERE (user_id = ? AND friend_id = ?) 
                  OR (user_id = ? AND friend_id = ?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        // Add friend request
        $add_sql = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
        $add_stmt = $conn->prepare($add_sql);
        $add_stmt->bind_param("ii", $user_id, $friend_id);
        
        if ($add_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Friend request sent!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send friend request.']);
        }
        $add_stmt->close();
    } else {
        $existing_request = $check_result->fetch_assoc();
        if ($existing_request['status'] == 'pending') {
            echo json_encode(['success' => false, 'message' => 'Friend request already pending.']);
        } elseif ($existing_request['status'] == 'accepted') {
            echo json_encode(['success' => false, 'message' => 'You are already friends with this user.']);
        }
    }
    $check_stmt->close();
    exit();
}

// Handle AJAX unfriend request
if (isset($_POST['ajax']) && $_POST['ajax'] == 'unfriend') {
    header('Content-Type: application/json');
    
    $friend_id = intval($_POST['friend_id']);
    
    $remove_sql = "DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
    $remove_stmt = $conn->prepare($remove_sql);
    $remove_stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    
    if ($remove_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend removed successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove friend.']);
    }
    $remove_stmt->close();
    exit();
}

// Handle AJAX delete message with file cleanup - FIXED VERSION
if (isset($_POST['ajax']) && $_POST['ajax'] == 'delete_message') {
    header('Content-Type: application/json');
    
    $message_id = intval($_POST['message_id']);
    $message_type = $_POST['message_type']; // 'private' or 'group'
    
    error_log("Delete message attempt - ID: $message_id, Type: $message_type, User: $user_id");
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        if ($message_type == 'private') {
            // First, verify the message exists and belongs to the user
            $check_sql = "SELECT id, sender_id, is_deleted FROM messages WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            $check_stmt->bind_param("i", $message_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message_data = $check_result->fetch_assoc();
                
                // Check if already deleted
                if ($message_data['is_deleted'] == 1) {
                    throw new Exception('Message already deleted');
                }
                
                // Verify the user is the sender
                if ($message_data['sender_id'] != $user_id) {
                    throw new Exception('You can only delete your own messages');
                }
                
                // Get file information if exists
                $file_sql = "SELECT id, file_path FROM message_files WHERE message_id = ?";
                $file_stmt = $conn->prepare($file_sql);
                if ($file_stmt) {
                    $file_stmt->bind_param("i", $message_id);
                    $file_stmt->execute();
                    $file_result = $file_stmt->get_result();
                    $file_paths = [];
                    
                    while ($file = $file_result->fetch_assoc()) {
                        $file_paths[] = $file['file_path'];
                    }
                    $file_stmt->close();
                    
                    // Delete file records
                    if (!empty($file_paths)) {
                        $delete_files_sql = "DELETE FROM message_files WHERE message_id = ?";
                        $delete_files_stmt = $conn->prepare($delete_files_sql);
                        if ($delete_files_stmt) {
                            $delete_files_stmt->bind_param("i", $message_id);
                            if (!$delete_files_stmt->execute()) {
                                throw new Exception("Failed to delete file records: " . $delete_files_stmt->error);
                            }
                            $delete_files_stmt->close();
                            
                            // Delete physical files
                            foreach ($file_paths as $file_path) {
                                if (file_exists($file_path)) {
                                    if (unlink($file_path)) {
                                        error_log("Deleted file: $file_path");
                                    } else {
                                        error_log("Failed to delete file: $file_path");
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Soft delete the message
                $delete_sql = "UPDATE messages SET is_deleted = 1 WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                if (!$delete_stmt) {
                    throw new Exception("Failed to prepare delete: " . $conn->error);
                }
                $delete_stmt->bind_param("i", $message_id);
                
                if ($delete_stmt->execute()) {
                    if ($delete_stmt->affected_rows > 0) {
                        error_log("Message deleted successfully - ID: $message_id");
                        $conn->commit();
                        echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
                    } else {
                        throw new Exception('No rows affected. Message may already be deleted.');
                    }
                } else {
                    throw new Exception('Failed to execute delete: ' . $delete_stmt->error);
                }
                $delete_stmt->close();
            } else {
                // Check if message is already deleted
                $check_deleted_sql = "SELECT id FROM messages WHERE id = ? AND is_deleted = 1";
                $check_deleted_stmt = $conn->prepare($check_deleted_sql);
                if ($check_deleted_stmt) {
                    $check_deleted_stmt->bind_param("i", $message_id);
                    $check_deleted_stmt->execute();
                    $check_deleted_result = $check_deleted_stmt->get_result();
                    
                    if ($check_deleted_result->num_rows > 0) {
                        throw new Exception('Message already deleted');
                    }
                    $check_deleted_stmt->close();
                }
                throw new Exception('Message not found (ID: ' . $message_id . ')');
            }
            $check_stmt->close();
            
        } else {
            // Handle group messages
            // First, verify the message exists and belongs to the user
            $check_sql = "SELECT id, sender_id, is_deleted FROM group_messages WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            $check_stmt->bind_param("i", $message_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message_data = $check_result->fetch_assoc();
                
                // Check if already deleted
                if ($message_data['is_deleted'] == 1) {
                    throw new Exception('Message already deleted');
                }
                
                // Verify the user is the sender
                if ($message_data['sender_id'] != $user_id) {
                    throw new Exception('You can only delete your own messages');
                }
                
                // Get file information if exists
                $file_sql = "SELECT id, file_path FROM group_message_files WHERE message_id = ?";
                $file_stmt = $conn->prepare($file_sql);
                if ($file_stmt) {
                    $file_stmt->bind_param("i", $message_id);
                    $file_stmt->execute();
                    $file_result = $file_stmt->get_result();
                    $file_paths = [];
                    
                    while ($file = $file_result->fetch_assoc()) {
                        $file_paths[] = $file['file_path'];
                    }
                    $file_stmt->close();
                    
                    // Delete file records
                    if (!empty($file_paths)) {
                        $delete_files_sql = "DELETE FROM group_message_files WHERE message_id = ?";
                        $delete_files_stmt = $conn->prepare($delete_files_sql);
                        if ($delete_files_stmt) {
                            $delete_files_stmt->bind_param("i", $message_id);
                            if (!$delete_files_stmt->execute()) {
                                throw new Exception("Failed to delete file records: " . $delete_files_stmt->error);
                            }
                            $delete_files_stmt->close();
                            
                            // Delete physical files
                            foreach ($file_paths as $file_path) {
                                if (file_exists($file_path)) {
                                    if (unlink($file_path)) {
                                        error_log("Deleted file: $file_path");
                                    } else {
                                        error_log("Failed to delete file: $file_path");
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Soft delete the message
                $delete_sql = "UPDATE group_messages SET is_deleted = 1 WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                if (!$delete_stmt) {
                    throw new Exception("Failed to prepare delete: " . $conn->error);
                }
                $delete_stmt->bind_param("i", $message_id);
                
                if ($delete_stmt->execute()) {
                    if ($delete_stmt->affected_rows > 0) {
                        error_log("Group message deleted successfully - ID: $message_id");
                        $conn->commit();
                        echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
                    } else {
                        throw new Exception('No rows affected. Message may already be deleted.');
                    }
                } else {
                    throw new Exception('Failed to execute delete: ' . $delete_stmt->error);
                }
                $delete_stmt->close();
            } else {
                // Check if message is already deleted
                $check_deleted_sql = "SELECT id FROM group_messages WHERE id = ? AND is_deleted = 1";
                $check_deleted_stmt = $conn->prepare($check_deleted_sql);
                if ($check_deleted_stmt) {
                    $check_deleted_stmt->bind_param("i", $message_id);
                    $check_deleted_stmt->execute();
                    $check_deleted_result = $check_deleted_stmt->get_result();
                    
                    if ($check_deleted_result->num_rows > 0) {
                        throw new Exception('Message already deleted');
                    }
                    $check_deleted_stmt->close();
                }
                throw new Exception('Message not found (ID: ' . $message_id . ')');
            }
            $check_stmt->close();
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Delete error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// END DELETE MESSAGE HANDLER 

// Handle search users for invite
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search_users_for_invite') {
    header('Content-Type: application/json');
    
    $group_id = intval($_GET['group_id']);
    $search_term = isset($_GET['term']) ? '%' . $_GET['term'] . '%' : '%';
    
    // Get all users except current user, admin, and users already in the group
    $search_sql = "SELECT id, username, email, profile_photo 
                   FROM users 
                   WHERE id != ? 
                   AND username != 'admin' 
                   AND email NOT LIKE '%admin%'
                   AND id NOT IN (
                       SELECT user_id FROM group_members WHERE group_id = ?
                   )
                   AND (username LIKE ? OR email LIKE ?)
                   ORDER BY username LIMIT 20";
    $search_stmt = $conn->prepare($search_sql);
    $search_stmt->bind_param("iiss", $user_id, $group_id, $search_term, $search_term);
    $search_stmt->execute();
    $search_result = $search_stmt->get_result();
    
    $users = [];
    while ($user = $search_result->fetch_assoc()) {
        $users[] = $user;
    }
    $search_stmt->close();
    
    echo json_encode(['success' => true, 'users' => $users]);
    exit();
}

// Get chat type and ID
$chat_type = isset($_GET['type']) ? $_GET['type'] : (isset($_GET['group_id']) ? 'group' : 'user');
$selected_id = 0;

if ($chat_type == 'group' && isset($_GET['group_id'])) {
    $selected_id = intval($_GET['group_id']);
} elseif ($chat_type == 'user' && isset($_GET['user_id'])) {
    $selected_id = intval($_GET['user_id']);
}

// Handle AJAX request for new messages
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_messages' && $selected_id > 0) {
    header('Content-Type: application/json');
    
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    $last_time = isset($_GET['last_time']) ? $_GET['last_time'] : date('Y-m-d H:i:s', strtotime('-1 minute'));
    
    $response = ['messages' => [], 'last_id' => $last_id, 'last_time' => $last_time];
    
    if ($chat_type == 'user') {
        // Check if users are friends
        $check_friendship_sql = "SELECT id FROM friends 
                                WHERE ((user_id = ? AND friend_id = ?) 
                                OR (user_id = ? AND friend_id = ?))
                                AND status = 'accepted'";
        $check_friendship_stmt = $conn->prepare($check_friendship_sql);
        $check_friendship_stmt->bind_param("iiii", $user_id, $selected_id, $selected_id, $user_id);
        $check_friendship_stmt->execute();
        $check_friendship_result = $check_friendship_stmt->get_result();
        $is_friend = $check_friendship_result->num_rows > 0;
        $check_friendship_stmt->close();
        
        if ($is_friend) {
            // Get new private messages with file attachments
            $messages_sql = "SELECT m.*, 
                            u.username as sender_username,
                            u.profile_photo as sender_photo,
                            mf.file_name, mf.file_path, mf.file_size, mf.file_type
                    FROM messages m
                    LEFT JOIN message_files mf ON m.id = mf.message_id
                    JOIN users u ON m.sender_id = u.id
                    WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
                        OR (m.sender_id = ? AND m.receiver_id = ?))
                        AND m.id > ?
                        AND m.is_deleted = 0
         ORDER BY m.created_at ASC";
            $messages_stmt = $conn->prepare($messages_sql);
            $messages_stmt->bind_param("iiiiii", $user_id, $selected_id, $selected_id, $user_id, $last_id);
            $messages_stmt->execute();
            $messages_result = $messages_stmt->get_result();
            
            while ($message = $messages_result->fetch_assoc()) {
                $message['created_at_formatted'] = date('H:i', strtotime($message['created_at']));
                $message['created_at_full'] = $message['created_at'];
                $response['messages'][] = $message;
                $response['last_id'] = $message['id'];
                $response['last_time'] = $message['created_at'];
            }
            $messages_stmt->close();
        }
    } else {
        // Check if user is member of group
        $check_member_sql = "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?";
        $check_member_stmt = $conn->prepare($check_member_sql);
        $check_member_stmt->bind_param("ii", $selected_id, $user_id);
        $check_member_stmt->execute();
        $check_member_result = $check_member_stmt->get_result();
        $is_member = $check_member_result->num_rows > 0;
        $check_member_stmt->close();
        
        if ($is_member) {
            // Get new group messages with file attachments
            $messages_sql = "SELECT gm.*, u.username as sender_name, u.profile_photo as sender_photo,
                            gmf.file_name, gmf.file_path, gmf.file_size, gmf.file_type
                            FROM group_messages gm
                            JOIN users u ON gm.sender_id = u.id
                            LEFT JOIN group_message_files gmf ON gm.id = gmf.message_id
                            WHERE gm.group_id = ? AND gm.id > ? AND gm.is_deleted = 0
                            ORDER BY gm.created_at ASC";
            $messages_stmt = $conn->prepare($messages_sql);
            $messages_stmt->bind_param("ii", $selected_id, $last_id);
            $messages_stmt->execute();
            $messages_result = $messages_stmt->get_result();
            
            while ($message = $messages_result->fetch_assoc()) {
                $message['created_at_formatted'] = date('H:i', strtotime($message['created_at']));
                $message['created_at_full'] = $message['created_at'];
                $response['messages'][] = $message;
                $response['last_id'] = $message['id'];
                $response['last_time'] = $message['created_at'];
            }
            $messages_stmt->close();
        }
    }
    
    echo json_encode($response);
    exit();
}

// Handle add friend request (non-AJAX fallback)
if (isset($_POST['add_friend']) && !isset($_POST['ajax'])) {
    $friend_id = intval($_POST['friend_id']);
    
    // Check if friendship already exists
    $check_sql = "SELECT id, status FROM friends 
                  WHERE (user_id = ? AND friend_id = ?) 
                  OR (user_id = ? AND friend_id = ?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        // Add friend request
        $add_sql = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
        $add_stmt = $conn->prepare($add_sql);
        $add_stmt->bind_param("ii", $user_id, $friend_id);
        
        if ($add_stmt->execute()) {
            $_SESSION['success'] = "Friend request sent!";
        } else {
            $_SESSION['error'] = "Failed to send friend request.";
        }
        $add_stmt->close();
    } else {
        $existing_request = $check_result->fetch_assoc();
        if ($existing_request['status'] == 'pending') {
            $_SESSION['error'] = "Friend request already pending.";
        } elseif ($existing_request['status'] == 'accepted') {
            $_SESSION['error'] = "You are already friends with this user.";
        }
    }
    $check_stmt->close();
    
    header("Location: chat.php");
    exit();
}

// Handle accept friend request (non-AJAX fallback)
if (isset($_POST['accept_friend']) && !isset($_POST['ajax'])) {
    $friend_id = intval($_POST['friend_id']);
    
    $accept_sql = "UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?";
    $accept_stmt = $conn->prepare($accept_sql);
    $accept_stmt->bind_param("ii", $friend_id, $user_id);
    
    if ($accept_stmt->execute()) {
        $_SESSION['success'] = "Friend request accepted!";
    }
    $accept_stmt->close();
    
    header("Location: chat.php");
    exit();
}

// Handle reject friend request (non-AJAX fallback)
if (isset($_POST['reject_friend']) && !isset($_POST['ajax'])) {
    $friend_id = intval($_POST['friend_id']);
    
    $reject_sql = "DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
    $reject_stmt = $conn->prepare($reject_sql);
    $reject_stmt->bind_param("ii", $friend_id, $user_id);
    
    if ($reject_stmt->execute()) {
        $_SESSION['success'] = "Friend request rejected.";
    } else {
        $_SESSION['error'] = "Failed to reject friend request.";
    }
    $reject_stmt->close();
    
    header("Location: chat.php");
    exit();
}

// Handle sending message with file upload - AJAX endpoint
if (isset($_POST['send_message']) && $selected_id > 0) {
    $message = trim($_POST['message']);
    $has_file = isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] == UPLOAD_ERR_OK;
    $file_error = null;
    
    if ($has_file) {
        $file = $_FILES['chat_file'];
        $file_name = basename($file['name']);
        $file_size = $file['size'];
        $file_type = $file['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check file size
        if ($file_size > MAX_FILE_SIZE) {
            $file_error = "File size exceeds the maximum limit of 40MB.";
        }
        // Check file type
        elseif (!in_array($file_type, $allowed_file_types) && !in_array($file_ext, $allowed_extensions)) {
            $file_error = "File type not supported. Allowed types: Images (JPG, PNG, GIF), Videos (MP4), Documents (PDF, DOC, DOCX, TXT), Archives (ZIP, RAR)";
        }
    }
    
    if ($file_error) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $file_error]);
            exit();
        } else {
            $_SESSION['error'] = $file_error;
            header("Location: chat.php?" . ($chat_type == 'group' ? "group_id=" . $selected_id : "user_id=" . $selected_id));
            exit();
        }
    }
    
    $message_sent = false;
    $message_id = 0;
    
    if (!empty($message) || $has_file) {
        if ($chat_type == 'user') {
            // Check if users are friends
            $check_friendship_sql = "SELECT id FROM friends 
                                    WHERE ((user_id = ? AND friend_id = ?) 
                                    OR (user_id = ? AND friend_id = ?))
                                    AND status = 'accepted'";
            $check_friendship_stmt = $conn->prepare($check_friendship_sql);
            $check_friendship_stmt->bind_param("iiii", $user_id, $selected_id, $selected_id, $user_id);
            $check_friendship_stmt->execute();
            $check_friendship_result = $check_friendship_stmt->get_result();
            
            if ($check_friendship_result->num_rows > 0) {
                // Send private message
                $insert_sql = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iis", $user_id, $selected_id, $message);
                
                if ($insert_stmt->execute()) {
                    $message_id = $conn->insert_id;
                    $message_sent = true;
                    
                    // Handle file upload for private message
                    if ($has_file) {
                        $file = $_FILES['chat_file'];
                        $file_name = basename($file['name']);
                        $file_tmp = $file['tmp_name'];
                        $file_size = $file['size'];
                        $file_type = $file['type'];
                        
                        $unique_name = uniqid() . '_' . $file_name;
                        $file_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $file_sql = "INSERT INTO message_files (message_id, file_name, file_path, file_size, file_type) 
                                         VALUES (?, ?, ?, ?, ?)";
                            $file_stmt = $conn->prepare($file_sql);
                            $file_stmt->bind_param("issis", $message_id, $file_name, $file_path, $file_size, $file_type);
                            $file_stmt->execute();
                            $file_stmt->close();
                        }
                    }
                }
                $insert_stmt->close();
            }
            $check_friendship_stmt->close();
        } else {
            // Check if user is member of group
            $check_member_sql = "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?";
            $check_member_stmt = $conn->prepare($check_member_sql);
            $check_member_stmt->bind_param("ii", $selected_id, $user_id);
            $check_member_stmt->execute();
            $check_member_result = $check_member_stmt->get_result();
            
            if ($check_member_result->num_rows > 0) {
                // Send group message
                $insert_sql = "INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iis", $selected_id, $user_id, $message);
                
                if ($insert_stmt->execute()) {
                    $message_id = $conn->insert_id;
                    $message_sent = true;
                    
                    // Handle file upload for group message
                    if ($has_file) {
                        $file = $_FILES['chat_file'];
                        $file_name = basename($file['name']);
                        $file_tmp = $file['tmp_name'];
                        $file_size = $file['size'];
                        $file_type = $file['type'];
                        
                        $unique_name = uniqid() . '_' . $file_name;
                        $file_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $file_sql = "INSERT INTO group_message_files (message_id, file_name, file_path, file_size, file_type) 
                                         VALUES (?, ?, ?, ?, ?)";
                            $file_stmt = $conn->prepare($file_sql);
                            $file_stmt->bind_param("issis", $message_id, $file_name, $file_path, $file_size, $file_type);
                            $file_stmt->execute();
                            $file_stmt->close();
                        }
                    }
                }
                $insert_stmt->close();
            }
            $check_member_stmt->close();
        }
    }
    
    // Return JSON for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($message_sent) {
            // Get the newly created message with all details
            if ($chat_type == 'user') {
                $message_sql = "SELECT m.*, 
                                u.username as sender_username,
                                u.profile_photo as sender_photo,
                                mf.file_name, mf.file_path, mf.file_size, mf.file_type
                         FROM messages m
                         LEFT JOIN message_files mf ON m.id = mf.message_id
                         JOIN users u ON m.sender_id = u.id
                         WHERE m.id = ?";
                $message_stmt = $conn->prepare($message_sql);
                $message_stmt->bind_param("i", $message_id);
                $message_stmt->execute();
                $message_result = $message_stmt->get_result();
                $new_message = $message_result->fetch_assoc();
                $message_stmt->close();
            } else {
                $message_sql = "SELECT gm.*, u.username as sender_name, u.profile_photo as sender_photo,
                                gmf.file_name, gmf.file_path, gmf.file_size, gmf.file_type
                                FROM group_messages gm
                                JOIN users u ON gm.sender_id = u.id
                                LEFT JOIN group_message_files gmf ON gm.id = gmf.message_id
                                WHERE gm.id = ?";
                $message_stmt = $conn->prepare($message_sql);
                $message_stmt->bind_param("i", $message_id);
                $message_stmt->execute();
                $message_result = $message_stmt->get_result();
                $new_message = $message_result->fetch_assoc();
                $message_stmt->close();
            }
            
            if ($new_message) {
                $new_message['created_at_formatted'] = date('H:i', strtotime($new_message['created_at']));
                echo json_encode(['success' => true, 'message' => $new_message]);
                exit();
            } else {
                echo json_encode(['success' => true, 'message_id' => $message_id]);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to send message']);
            exit();
        }
    } else {
        header("Location: chat.php?" . ($chat_type == 'group' ? "group_id=" . $selected_id : "user_id=" . $selected_id));
        exit();
    }
}

// Get all users except current user and admin - For Add Friend modal (display all users)
$all_users_sql = "SELECT id, username, email, profile_photo FROM users 
                  WHERE id != ? AND username != 'admin' AND email NOT LIKE '%admin%' 
                  ORDER BY username";
$all_users_stmt = $conn->prepare($all_users_sql);
$all_users_stmt->bind_param("i", $user_id);
$all_users_stmt->execute();
$all_users_result = $all_users_stmt->get_result();

// Get all pending friend request IDs to exclude from main list
$pending_ids_sql = "SELECT user_id FROM friends WHERE friend_id = ? AND status = 'pending'";
$pending_ids_stmt = $conn->prepare($pending_ids_sql);
$pending_ids_stmt->bind_param("i", $user_id);
$pending_ids_stmt->execute();
$pending_ids_result = $pending_ids_stmt->get_result();
$pending_ids = [];
while ($row = $pending_ids_result->fetch_assoc()) {
    $pending_ids[] = $row['user_id'];
}
$pending_ids_stmt->close();

$all_users_for_friend = [];
while ($user = $all_users_result->fetch_assoc()) {
    // Skip users who have pending requests to current user
    if (in_array($user['id'], $pending_ids)) {
        continue;
    }
    
    $friend_status_sql = "SELECT status FROM friends 
                          WHERE (user_id = ? AND friend_id = ?) 
                          OR (user_id = ? AND friend_id = ?)";
    $friend_status_stmt = $conn->prepare($friend_status_sql);
    $friend_status_stmt->bind_param("iiii", $user_id, $user['id'], $user['id'], $user_id);
    $friend_status_stmt->execute();
    $friend_status_result = $friend_status_stmt->get_result();
    
    $user['friend_status'] = 'not_friend';
    $user['is_pending_from_me'] = false;
    $user['is_pending_to_me'] = false;
    
    if ($friend_status_result->num_rows > 0) {
        $friend_status = $friend_status_result->fetch_assoc();
        $user['friend_status'] = $friend_status['status'];
        
        if ($friend_status['status'] == 'pending') {
            // Check if pending request was sent by current user
            $check_sender_sql = "SELECT user_id FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
            $check_sender_stmt = $conn->prepare($check_sender_sql);
            $check_sender_stmt->bind_param("ii", $user_id, $user['id']);
            $check_sender_stmt->execute();
            $sender_result = $check_sender_stmt->get_result();
            $user['is_pending_from_me'] = ($sender_result->num_rows > 0);
            $check_sender_stmt->close();
            
            // Check if pending request was sent to current user
            $check_receiver_sql = "SELECT user_id FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
            $check_receiver_stmt = $conn->prepare($check_receiver_sql);
            $check_receiver_stmt->bind_param("ii", $user['id'], $user_id);
            $check_receiver_stmt->execute();
            $receiver_result = $check_receiver_stmt->get_result();
            $user['is_pending_to_me'] = ($receiver_result->num_rows > 0);
            $check_receiver_stmt->close();
        }
    }
    
    $friend_status_stmt->close();
    $all_users_for_friend[] = $user;
}
$all_users_stmt->close();

// Get pending friend requests count (exclude admin)
$pending_requests_count_sql = "SELECT COUNT(*) as count FROM friends f
                               JOIN users u ON f.user_id = u.id
                               WHERE f.friend_id = ? AND f.status = 'pending' 
                               AND u.username != 'admin' AND u.email NOT LIKE '%admin%'";
$pending_count_stmt = $conn->prepare($pending_requests_count_sql);
if ($pending_count_stmt) {
    $pending_count_stmt->bind_param("i", $user_id);
    $pending_count_stmt->execute();
    $pending_count_result = $pending_count_stmt->get_result();
    $pending_count_data = $pending_count_result->fetch_assoc();
    $pending_requests_count = $pending_count_data['count'] ?? 0;
    $pending_count_stmt->close();
} else {
    $pending_requests_count = 0;
}

// Get pending friend requests (exclude admin) - for display
$pending_requests_sql = "SELECT u.id, u.username, u.email, u.profile_photo FROM friends f
                         JOIN users u ON f.user_id = u.id
                         WHERE f.friend_id = ? AND f.status = 'pending' 
                         AND u.username != 'admin' AND u.email NOT LIKE '%admin%'";
$pending_stmt = $conn->prepare($pending_requests_sql);
if ($pending_stmt) {
    $pending_stmt->bind_param("i", $user_id);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    $pending_requests = $pending_result->fetch_all(MYSQLI_ASSOC);
    $pending_stmt->close();
} else {
    $pending_requests = [];
}

// Get accepted friends (exclude admin)
$accepted_friends_sql = "SELECT u.id, u.username, u.email, u.profile_photo FROM friends f
                         JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id) AND u.id != ?
                         WHERE ((f.user_id = ? AND f.friend_id = u.id) OR (f.friend_id = ? AND f.user_id = u.id))
                         AND f.status = 'accepted' AND u.username != 'admin' AND u.email NOT LIKE '%admin%'";
$friends_stmt = $conn->prepare($accepted_friends_sql);
if ($friends_stmt) {
    $friends_stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $friends_stmt->execute();
    $friends_result = $friends_stmt->get_result();
    $accepted_friends = $friends_result->fetch_all(MYSQLI_ASSOC);
    $friends_stmt->close();
} else {
    $accepted_friends = [];
}

// Get unread message counts for friends
$unread_counts = [];
foreach ($accepted_friends as $friend) {
    $unread_sql = "SELECT COUNT(*) as count FROM messages 
                   WHERE sender_id = ? AND receiver_id = ? AND is_read = 0 AND is_deleted = 0";
    $unread_stmt = $conn->prepare($unread_sql);
    if ($unread_stmt) {
        $unread_stmt->bind_param("ii", $friend['id'], $user_id);
        $unread_stmt->execute();
        $unread_result = $unread_stmt->get_result();
        $unread_data = $unread_result->fetch_assoc();
        $unread_counts[$friend['id']] = $unread_data['count'] ?? 0;
        $unread_stmt->close();
    }
}

// Get user's groups (only non-deleted groups)
$user_groups_sql = "SELECT g.*, gm.role FROM groups g
                    JOIN group_members gm ON g.id = gm.group_id
                    WHERE gm.user_id = ? AND (g.is_deleted = 0 OR g.is_deleted IS NULL)
                    ORDER BY g.created_at DESC";
$user_groups_stmt = $conn->prepare($user_groups_sql);
$user_groups_stmt->bind_param("i", $user_id);
$user_groups_stmt->execute();
$user_groups_result = $user_groups_stmt->get_result();
$user_groups = $user_groups_result->fetch_all(MYSQLI_ASSOC);
$user_groups_stmt->close();

// Get unread group message counts
$group_unread_counts = [];
foreach ($user_groups as $group) {
    $last_read_sql = "SELECT last_read_message_id FROM group_members 
                      WHERE group_id = ? AND user_id = ?";
    $last_read_stmt = $conn->prepare($last_read_sql);
    $last_read_stmt->bind_param("ii", $group['id'], $user_id);
    $last_read_stmt->execute();
    $last_read_result = $last_read_stmt->get_result();
    $last_read_data = $last_read_result->fetch_assoc();
    $last_read_id = $last_read_data['last_read_message_id'] ?? 0;
    $last_read_stmt->close();
    
    $unread_sql = "SELECT COUNT(*) as count FROM group_messages 
                   WHERE group_id = ? AND id > ? AND sender_id != ? AND is_deleted = 0";
    $unread_stmt = $conn->prepare($unread_sql);
    $unread_stmt->bind_param("iii", $group['id'], $last_read_id, $user_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    $group_unread_counts[$group['id']] = $unread_data['count'] ?? 0;
    $unread_stmt->close();
}

// Get selected chat data
$selected_chat = null;
$messages = [];
$is_friend = false;
$last_message_id = 0;

if ($selected_id > 0) {
    if ($chat_type == 'group') {
        // Get group info (only if not deleted)
        $group_sql = "SELECT g.*, u.username as creator_name 
                      FROM groups g
                      JOIN users u ON g.created_by = u.id
                      WHERE g.id = ? AND (g.is_deleted = 0 OR g.is_deleted IS NULL)";
        $group_stmt = $conn->prepare($group_sql);
        $group_stmt->bind_param("i", $selected_id);
        $group_stmt->execute();
        $group_result = $group_stmt->get_result();
        $selected_chat = $group_result->fetch_assoc();
        $group_stmt->close();
        
        if ($selected_chat) {
            $check_member_sql = "SELECT role, last_read_message_id FROM group_members WHERE group_id = ? AND user_id = ?";
            $check_member_stmt = $conn->prepare($check_member_sql);
            $check_member_stmt->bind_param("ii", $selected_id, $user_id);
            $check_member_stmt->execute();
            $check_member_result = $check_member_stmt->get_result();
            $selected_chat['is_member'] = $check_member_result->num_rows > 0;
            $member_data = $check_member_result->fetch_assoc();
            $selected_chat['user_role'] = $member_data['role'] ?? null;
            $last_read_id = $member_data['last_read_message_id'] ?? 0;
            $check_member_stmt->close();
            
            $count_sql = "SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ?";
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param("i", $selected_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $selected_chat['member_count'] = $count_result->fetch_assoc()['member_count'] ?? 0;
            $count_stmt->close();
            
            $members_sql = "SELECT u.id, u.username, u.email, u.profile_photo, gm.role, gm.joined_at, gm.last_read_message_id
                           FROM group_members gm
                           JOIN users u ON gm.user_id = u.id
                           WHERE gm.group_id = ?
                           ORDER BY gm.role = 'admin' DESC, u.username ASC";
            $members_stmt = $conn->prepare($members_sql);
            $members_stmt->bind_param("i", $selected_id);
            $members_stmt->execute();
            $members_result = $members_stmt->get_result();
            $selected_chat['members'] = $members_result->fetch_all(MYSQLI_ASSOC);
            $members_stmt->close();
            
            if ($selected_chat['is_member']) {
                // Get group messages with file attachments (only non-deleted)
                $messages_sql = "SELECT gm.*, u.username as sender_name, u.profile_photo as sender_photo,
                                gmf.file_name, gmf.file_path, gmf.file_size, gmf.file_type
                                FROM group_messages gm
                                JOIN users u ON gm.sender_id = u.id
                                LEFT JOIN group_message_files gmf ON gm.id = gmf.message_id
                                WHERE gm.group_id = ? AND gm.is_deleted = 0
                                ORDER BY gm.created_at ASC";
                $messages_stmt = $conn->prepare($messages_sql);
                $messages_stmt->bind_param("i", $selected_id);
                $messages_stmt->execute();
                $messages_result = $messages_stmt->get_result();
                $messages = $messages_result->fetch_all(MYSQLI_ASSOC);
                
                if (!empty($messages)) {
                    $last_message_id = end($messages)['id'];
                    
                    $update_read_sql = "UPDATE group_members SET last_read_message_id = ? 
                                       WHERE group_id = ? AND user_id = ?";
                    $update_read_stmt = $conn->prepare($update_read_sql);
                    $update_read_stmt->bind_param("iii", $last_message_id, $selected_id, $user_id);
                    $update_read_stmt->execute();
                    $update_read_stmt->close();
                }
                $messages_stmt->close();
            }
        }
    } else {
        $user_sql = "SELECT id, username, email, profile_photo FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $selected_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $selected_chat = $user_result->fetch_assoc();
        $user_stmt->close();
        
        if ($selected_chat) {
            $check_friendship_sql = "SELECT id FROM friends 
                                    WHERE ((user_id = ? AND friend_id = ?) 
                                    OR (user_id = ? AND friend_id = ?))
                                    AND status = 'accepted'";
            $check_friendship_stmt = $conn->prepare($check_friendship_sql);
            $check_friendship_stmt->bind_param("iiii", $user_id, $selected_id, $selected_id, $user_id);
            $check_friendship_stmt->execute();
            $check_friendship_result = $check_friendship_stmt->get_result();
            $is_friend = $check_friendship_result->num_rows > 0;
            $check_friendship_stmt->close();
            
            if ($is_friend) {
                // Get private messages with file attachments
                $messages_sql = "SELECT m.*, 
                                u.username as sender_username,
                                u.profile_photo as sender_photo,
                                u2.username as receiver_username,
                                u2.profile_photo as receiver_photo,
                                mf.file_name, mf.file_path, mf.file_size, mf.file_type
                        FROM messages m
                        LEFT JOIN message_files mf ON m.id = mf.message_id
                        JOIN users u ON m.sender_id = u.id
                        JOIN users u2 ON m.receiver_id = u2.id
                        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                            OR (m.sender_id = ? AND m.receiver_id = ?)
                            AND m.is_deleted = 0
                        ORDER BY m.created_at ASC";
                $messages_stmt = $conn->prepare($messages_sql);
                $messages_stmt->bind_param("iiii", $user_id, $selected_id, $selected_id, $user_id);
                $messages_stmt->execute();
                $messages_result = $messages_stmt->get_result();
                $messages = $messages_result->fetch_all(MYSQLI_ASSOC);
                
                if (!empty($messages)) {
                    $last_message_id = end($messages)['id'];
                    
                    $update_read_sql = "UPDATE messages SET is_read = 1 
                                       WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
                    $update_read_stmt = $conn->prepare($update_read_sql);
                    $update_read_stmt->bind_param("ii", $selected_id, $user_id);
                    $update_read_stmt->execute();
                    $update_read_stmt->close();
                }
                $messages_stmt->close();
            }
        }
    }
}

// Get all users for group invitation
$all_users_for_invite = [];
if ($chat_type == 'group' && $selected_id > 0 && isset($selected_chat['user_role']) && $selected_chat['user_role'] == 'admin') {
    $all_users_for_invite_sql = "SELECT id, username, email, profile_photo 
                                FROM users 
                                WHERE id != ? AND username != 'admin' AND email NOT LIKE '%admin%'
                                AND id NOT IN (
                                    SELECT user_id FROM group_members WHERE group_id = ?
                                )
                                ORDER BY username";
    $all_users_for_invite_stmt = $conn->prepare($all_users_for_invite_sql);
    $all_users_for_invite_stmt->bind_param("ii", $user_id, $selected_id);
    $all_users_for_invite_stmt->execute();
    $all_users_for_invite_result = $all_users_for_invite_stmt->get_result();
    $all_users_for_invite = $all_users_for_invite_result->fetch_all(MYSQLI_ASSOC);
    $all_users_for_invite_stmt->close();
}

// Get total unread messages count for sidebar badge (including friend requests)
$total_unread = array_sum($unread_counts) + array_sum($group_unread_counts) + $pending_requests_count;

// Function to get file icon
function getFileIcon($file_type) {
    $icons = [
        'image' => 'fa-image',
        'video' => 'fa-video',
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'txt' => 'fa-file-alt',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive',
        'default' => 'fa-file'
    ];
    
    if (strpos($file_type, 'image/') === 0) {
        return $icons['image'];
    } elseif (strpos($file_type, 'video/') === 0) {
        return $icons['video'];
    } elseif (strpos($file_type, 'application/pdf') === 0) {
        return $icons['pdf'];
    } elseif (strpos($file_type, 'application/msword') === 0 || 
              strpos($file_type, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') === 0) {
        return $icons['doc'];
    } elseif (strpos($file_type, 'text/') === 0) {
        return $icons['txt'];
    } elseif (strpos($file_type, 'application/zip') === 0 || 
              strpos($file_type, 'application/x-rar-compressed') === 0) {
        return $icons['zip'];
    }
    
    return $icons['default'];
}

// Function to check if file is image
function isImageFile($file_type) {
    return strpos($file_type, 'image/') === 0;
}

// Function to check if file is video
function isVideoFile($file_type) {
    return strpos($file_type, 'video/') === 0;
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Function to get time-based greeting
function getGreeting() {
    $hour = date('H');
    if ($hour < 12) {
        return 'Good Morning';
    } elseif ($hour < 18) {
        return 'Good Afternoon';
    } else {
        return 'Good Evening';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - SkyMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ALL ORIGINAL CSS STYLES - PRESERVED EXACTLY */
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

        .chat-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            min-height: 100vh;
        }

        /* Sidebar  */
        .sidebar {
            background: white;
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
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
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            position: relative;
        }

        .user-profile-content {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            padding: 10px;
            border-radius: 12px;
            transition: background 0.3s;
        }

        .user-profile-content:hover {
            background: #f8f9ff;
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
            overflow: hidden;
            flex-shrink: 0;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-info p {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-icon {
            color: #667eea;
            font-size: 14px;
            transition: transform 0.3s;
            flex-shrink: 0;
        }

        .user-profile-content:hover .dropdown-icon {
            transform: translateY(3px);
        }

        .profile-dropdown-menu {
            position: absolute;
            top: calc(100% - 5px);
            left: 25px;
            right: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 8px 0;
            z-index: 100;
            display: none;
            border: 1px solid #eee;
            animation: slideDown 0.2s ease;
        }

        .profile-dropdown-menu.show {
            display: block;
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

        .profile-dropdown-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #333;
            text-decoration: none;
            transition: background 0.3s;
            font-size: 14px;
            width: 100%;
            text-align: left;
            border: none;
            background: none;
            cursor: pointer;
        }

        .profile-dropdown-item:hover {
            background: #f8f9ff;
            color: #667eea;
        }

        .profile-dropdown-item i {
            width: 20px;
            color: #667eea;
            font-size: 16px;
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
            background: #8b5cf6;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            min-width: 24px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(139, 92, 246, 0.3);
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

        /* Main Chat Area */
        .main-content {
            padding: 30px;
            min-height: 100vh;
        }

        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .chat-header-left h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }

        .chat-header-left p {
            color: #666;
            font-size: 15px;
        }

        .chat-header-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-add-friend-header {
            padding: 12px 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            position: relative;
        }

        .btn-add-friend-header:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }

        .btn-add-friend-header i {
            font-size: 16px;
        }

        .friend-request-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            font-size: 12px;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            padding: 0 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        .btn-new-group {
            padding: 12px 24px;
            background: #8b5cf6;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-new-group:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }

        .btn-new-group i {
            font-size: 16px;
        }

        .btn-unfriend-modal {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-unfriend-modal:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        /* Chat Layout - FIXED HEIGHTS FOR PROPER SCROLLING */
        .chat-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            height: calc(100vh - 180px); /* Fixed height instead of min-height */
            min-height: 500px;
        }

        /* Users List Container - FIXED SCROLLING FOR 9+ ITEMS */
        .users-list-container {
            background: #f8f9ff;
            border-right: 2px solid #eee;
            display: flex;
            flex-direction: column;
            height: 100%; /* Take full height of parent */
            overflow: hidden;
            position: relative;
        }

        .users-list-header {
            padding: 20px;
            border-bottom: 2px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0; /* Prevent header from shrinking */
            background: #f8f9ff;
            z-index: 5;
        }

        .search-with-button {
            margin: 15px 20px;
            flex-shrink: 0; /* Prevent search from shrinking */
            background: #f8f9ff;
        }

        .users-search {
            position: relative;
        }

        .users-search input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .users-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .users-search i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
        }

        /* THIS IS THE KEY PART - The scrollable list */
        .users-list {
            flex: 1; /* Take remaining space */
            overflow-y: auto; /* Enable vertical scrolling */
            min-height: 0; /* Critical for flexbox scrolling */
            display: flex;
            flex-direction: column;
        }

        /* User items */
        .user-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            flex-shrink: 0; /* Prevent items from shrinking */
        }

        .user-item:hover {
            background: white;
        }

        .user-item.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1), transparent);
            border-left: 4px solid #667eea;
        }

        .user-avatar-small {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            font-weight: bold;
            flex-shrink: 0;
            overflow: hidden;
        }

        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info-small {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-weight: 600;
            font-size: 15px;
            color: #333;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-last-message {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
            flex-shrink: 0;
        }

        .unread-badge {
            background: #ef4444;
            color: white;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            padding: 0 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }

        /* Chat Area - Ensure proper height */
        .chat-area-container {
            display: flex;
            flex-direction: column;
            background: white;
            height: 100%; /* Take full height */
            overflow: hidden;
        }

        .no-chat-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            padding: 40px;
            text-align: center;
            color: #666;
        }

        .no-chat-selected i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .no-chat-selected h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #444;
        }

        .no-chat-selected p {
            font-size: 16px;
            margin-bottom: 30px;
            max-width: 400px;
        }

        /* Chat Area Header */
        .chat-area-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
        }

        .chat-user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: bold;
            flex-shrink: 0;
            overflow: hidden;
        }

        .chat-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-user-details h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 3px;
        }

        .chat-user-details p {
            font-size: 14px;
            color: #666;
        }

        .chat-actions {
            display: flex;
            gap: 10px;
        }

        .chat-action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .chat-action-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .chat-action-btn.info-btn {
            background: #8b5cf6;
            color: white;
            border-color: #8b5cf6;
        }

        .chat-action-btn.info-btn:hover {
            background: #7c3aed;
            border-color: #7c3aed;
        }

        .chat-action-btn.delete-group-btn {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        .chat-action-btn.delete-group-btn:hover {
            background: #dc2626;
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        /* REDESIGNED MESSAGES CONTAINER - Better visibility */
        .messages-container {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
            background: #f0f2f5;
            min-height: 0; /* Critical for scrolling */
        }

        .message-date {
            text-align: center;
            margin: 10px 0;
        }

        .date-label {
            display: inline-block;
            background: rgba(0, 0, 0, 0.05);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            color: #666;
            font-weight: 500;
            backdrop-filter: blur(5px);
        }

        .message {
            display: flex;
            max-width: 70%;
            animation: messageSlide 0.3s ease;
            position: relative;
        }

        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.sent {
            align-self: flex-end;
        }

        .message.received {
            align-self: flex-start;
        }

        .message-content {
            padding: 12px 18px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            line-height: 1.5;
            max-width: 100%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            cursor: context-menu;
        }

        .message.sent .message-content {
            background: #667eea;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.received .message-content {
            background: white;
            color: #333;
            border-bottom-left-radius: 4px;
        }

        /* Message Layout */
        .message-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }

        .message-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .message-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .message-username {
            font-weight: 600;
            font-size: 13px;
            color: #667eea;
        }

        .message.sent .message-username {
            color: rgba(255, 255, 255, 0.9);
        }

        .message-time-small {
            font-size: 10px;
            opacity: 0.6;
            margin-left: auto;
        }

        .message.sent .message-time-small {
            color: rgba(255, 255, 255, 0.7);
        }

        .message.received .message-time-small {
            color: #999;
        }

        .message-text {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            text-align: right;
            margin-top: 5px;
        }

        .message.received .message-time {
            color: #999;
        }

        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }

        /* Right-click context menu indicator - subtle */
        .message.can-delete .message-content {
            cursor: context-menu;
            position: relative;
        }

        .message.can-delete .message-content::after {
            content: '⋮';
            position: absolute;
            top: 5px;
            right: 8px;
            font-size: 16px;
            color: rgba(255, 255, 255, 0.5);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .message.received.can-delete .message-content::after {
            color: #999;
        }

        .message.can-delete:hover .message-content::after {
            opacity: 1;
        }

        /* Context Menu Styles */
        .context-menu {
            position: fixed;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 2000;
            min-width: 200px;
            overflow: hidden;
            animation: fadeIn 0.2s ease;
        }

        .context-menu-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            color: #333;
        }

        .context-menu-item:hover {
            background: #f8f9ff;
        }

        .context-menu-item.delete {
            color: #ef4444;
            border-top: 1px solid #f0f0f0;
        }

        .context-menu-item.delete:hover {
            background: #ffebee;
        }

        .context-menu-item i {
            width: 18px;
            text-align: center;
            font-size: 16px;
        }

        /* File Attachment Styles - Enhanced */
        .file-attachment {
            margin-top: 8px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
        }

        .message.received .file-attachment {
            background: #f8f9ff;
            border: 1px solid #e0e0e0;
        }

        .file-attachment:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .file-icon {
            font-size: 28px;
            color: #667eea;
            flex-shrink: 0;
        }

        .message.sent .file-icon {
            color: white;
        }

        .file-info {
            flex: 1;
            min-width: 0;
        }

        .file-name {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-size {
            font-size: 11px;
            opacity: 0.8;
        }

        .file-actions {
            display: flex;
            gap: 5px;
            margin-left: 5px;
        }

        .file-action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: white;
            border: 1px solid #e0e0e0;
            color: #667eea;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .message.sent .file-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .file-action-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .message.sent .file-action-btn:hover {
            background: white;
            color: #667eea;
        }

        /* Image Preview in Chat - Enhanced */
        .message-content .image-preview-container {
            max-width: 300px;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 8px;
            cursor: pointer;
            position: relative;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .message.sent .image-preview-container {
            border-color: rgba(255, 255, 255, 0.3);
        }

        .message.received .image-preview-container {
            border-color: #e0e0e0;
        }

        .message-content .image-preview-container:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .message-content img.file-preview-image {
            width: 100%;
            height: auto;
            max-height: 300px;
            object-fit: cover;
            display: block;
        }

        .image-preview-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            color: white;
            padding: 30px 10px 10px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .image-preview-container:hover .image-preview-overlay {
            opacity: 1;
        }

        /* Video Preview - Enhanced */
        .message-content .video-preview-container {
            max-width: 300px;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 8px;
            cursor: pointer;
            position: relative;
            border: 2px solid transparent;
        }

        .message.sent .video-preview-container {
            border-color: rgba(255, 255, 255, 0.3);
        }

        .message.received .video-preview-container {
            border-color: #e0e0e0;
        }

        .message-content .video-preview-container video {
            width: 100%;
            height: auto;
            max-height: 300px;
            display: block;
        }

        .video-preview-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            background: rgba(102, 126, 234, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .video-preview-container:hover .video-preview-overlay {
            opacity: 1;
        }

        .video-duration {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Message Input */
        .message-input-container {
            background: white;
            padding: 20px 30px;
            border-top: 2px solid #f0f0f0;
            flex-shrink: 0;
        }

        .message-input-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .message-input-wrapper {
            flex: 1;
            position: relative;
        }

        .message-input {
            width: 100%;
            min-height: 50px;
            max-height: 150px;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 15px;
            resize: none;
            line-height: 1.5;
            transition: all 0.3s;
        }

        .message-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .file-upload-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .file-upload-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #f0f0f0;
            border: none;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .file-upload-btn:hover {
            background: #667eea;
            color: white;
        }

        .file-upload-input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9ff;
            border-radius: 10px;
            border: 2px dashed #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-preview.hidden {
            display: none;
        }

        .file-preview-info {
            flex: 1;
        }

        .file-preview-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .file-preview-size {
            font-size: 12px;
            color: #666;
        }

        .remove-file {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
        }

        .btn-send {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .btn-send:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* File Preview Modal - Enhanced */
        .file-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }

        .file-preview-modal.active {
            display: flex;
        }

        .file-preview-content {
            max-width: 90%;
            max-height: 90vh;
            position: relative;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .file-preview-header {
            background: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #f0f0f0;
        }

        .file-preview-header h3 {
            font-size: 16px;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-preview-header h3 i {
            color: #667eea;
        }

        .file-preview-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .file-preview-close:hover {
            background: #f0f0f0;
            color: #ef4444;
        }

        .file-preview-body {
            padding: 20px;
            text-align: center;
            background: #f8f9ff;
            max-height: calc(90vh - 70px);
            overflow: auto;
        }

        .file-preview-body img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            cursor: zoom-in;
            transition: transform 0.3s;
        }

        .file-preview-body img.zoomed {
            max-width: none;
            max-height: 90vh;
            cursor: zoom-out;
        }

        .file-preview-body video {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 12px;
            background: #000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .file-preview-body .file-info {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .file-preview-body .file-details {
            text-align: left;
        }

        .file-preview-body .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .file-preview-body .file-meta {
            font-size: 13px;
            color: #666;
        }

        .file-preview-body .download-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 14px;
        }

        .file-preview-body .download-btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 40px;
            position: relative;
            animation: modalSlide 0.3s ease;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            margin-bottom: 30px;
            padding-right: 30px;
        }

        .modal-header h3 {
            font-size: 24px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header h3 i {
            color: #667eea;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .close-modal:hover {
            background: #f0f0f0;
            color: #667eea;
        }

        .modal-search {
            position: relative;
            margin-bottom: 20px;
        }

        .modal-search input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .modal-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-search i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
        }

        .modal-users-list {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 30px;
        }

        .modal-user-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .modal-user-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateX(5px);
        }

        .btn-add-friend, .btn-pending, .btn-accepted, .btn-accept-request, .btn-reject-request, .btn-remove-friend, .btn-invite, .btn-chat, .btn-unfriend, .btn-chat-with-unfriend, .btn-unfriend-modal {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            min-width: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-add-friend {
            background: #10b981;
            color: white;
        }

        .btn-add-friend:hover {
            background: #059669;
        }

        .btn-pending {
            background: #fbbf24;
            color: white;
            cursor: not-allowed;
        }

        .btn-accepted {
            background: #10b981;
            color: white;
        }

        .btn-accept-request {
            background: #3b82f6;
            color: white;
        }

        .btn-accept-request:hover {
            background: #2563eb;
        }

        .btn-reject-request {
            background: #ef4444;
            color: white;
        }

        .btn-reject-request:hover {
            background: #dc2626;
        }

        .btn-chat-with-unfriend {
            background: #667eea;
            color: white;
            text-decoration: none;
        }

        .btn-chat-with-unfriend:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-invite {
            background: #8b5cf6;
            color: white;
        }

        .btn-invite:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .btn-chat {
            background: #667eea;
            color: white;
            text-decoration: none;
        }

        .btn-chat:hover {
            background: #5a67d8;
        }

        .btn-unfriend-modal {
            background: #ef4444;
            color: white;
        }

        .btn-unfriend-modal:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-cancel, .btn-start {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #666;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-start {
            background: #667eea;
            color: white;
        }

        .btn-start:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        /* Group Details - Enhanced with three-dot menu */
        .group-avatar {
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
        }

        .group-badge {
            background: #8b5cf6;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-left: 8px;
        }

        .admin-badge {
            background: #10b981;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }

        .group-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9ff, #f0f2f5);
            border-radius: 16px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
        }

        .member-list {
            max-height: 400px;
            overflow-y: auto;
            border-radius: 12px;
            border: 2px solid #f0f0f0;
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            transition: all 0.3s;
        }
        
        .member-item:last-child {
            border-bottom: none;
        }
        
        .member-item:hover {
            background: #f8f9ff;
        }
        
        .member-actions {
            margin-left: auto;
            position: relative;
        }
        
        .member-menu-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            opacity: 0.7;
        }
        
        .member-item:hover .member-menu-btn {
            opacity: 1;
            background: #f0f0f0;
        }
        
        .member-menu-btn:hover {
            background: #667eea !important;
            color: white !important;
            opacity: 1;
        }

        .member-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            font-weight: bold;
            overflow: hidden;
            flex-shrink: 0;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .member-avatar:hover {
            transform: scale(1.05);
        }

        .member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-info {
            flex: 1;
            cursor: pointer;
        }

        .member-name {
            font-weight: 600;
            font-size: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 3px;
        }

        .member-role {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            color: #666;
        }

        .member-role.admin {
            background: #8b5cf6;
            color: white;
        }

        .member-email {
            font-size: 12px;
            color: #666;
            margin-bottom: 2px;
        }

        .member-joined {
            font-size: 11px;
            color: #999;
        }

        /* Dropdown menu */
        .dropdown-menu {
            position: fixed;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 1001;
            min-width: 220px;
            overflow: hidden;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-header {
            padding: 15px 16px;
            background: #f8f9ff;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .dropdown-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 14px;
        }
        
        .dropdown-item:hover {
            background: #f0f0f0;
        }
        
        .dropdown-item i {
            width: 18px;
            text-align: center;
            font-size: 16px;
        }

        .invite-btn {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-right: 10px;
        }

        .invite-btn:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 14px;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }

        .toast {
            padding: 16px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease forwards;
            transform: translateX(100%);
            opacity: 0;
        }

        .toast.show {
            animation: slideIn 0.3s ease forwards;
        }

        @keyframes slideIn {
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.success {
            background: linear-gradient(135deg, #10b981, #059669);
            border-left: 4px solid #047857;
        }

        .toast.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-left: 4px solid #b91c1c;
        }

        .toast i {
            font-size: 20px;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .toast-close:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .search-results-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .search-result-item, .pending-request-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .search-result-item:hover, .pending-request-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateX(5px);
        }

        .pending-request-item {
            border-color: #f59e0b;
            background: #fff3e0;
        }

        .search-actions {
            margin-left: auto;
            display: flex;
            gap: 10px;
        }

        .request-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #fff3e0;
            border-radius: 12px;
            border: 2px solid #f59e0b;
        }

        .request-section h4 {
            color: #f59e0b;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .request-section h4 i {
            font-size: 18px;
        }

        .loading-spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(102, 126, 234, 0.2);
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .toast {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        
        .chat-action-btn[style*="background: #ef4444"]:hover {
            background: #dc2626 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .file-error-message {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 12px 15px;
            margin: 10px 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #b91c1c;
            animation: slideDown 0.3s ease;
        }

        .file-error-message i {
            font-size: 18px;
        }

        .file-error-message span {
            flex: 1;
            font-size: 14px;
        }

        .file-error-message .close-error {
            background: none;
            border: none;
            color: #b91c1c;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .file-error-message .close-error:hover {
            opacity: 1;
        }

        /* Create Group Form - Simplified */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            align-items: center;
            padding: 10px 0;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            margin-bottom: 0;
            cursor: pointer;
        }

        .radio-group input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }
        
        /* Character count indicator */
        .char-count {
            font-size: 12px;
            margin-top: 5px;
            text-align: right;
            color: #666;
        }
        
        .char-count.warning {
            color: #f59e0b;
        }
        
        .char-count.error {
            color: #ef4444;
        }
        
        /* Additional File Preview Styles */
        .file-attachment {
            margin-top: 8px;
            padding: 10px 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .message.received .file-attachment {
            background: #f0f2f5;
            border: 1px solid #e0e0e0;
        }
        
        .file-attachment:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background: #e8eaf6;
        }
        
        .file-icon {
            font-size: 28px;
            color: #667eea;
        }
        
        .message.sent .file-icon {
            color: white;
        }
        
        .file-info {
            flex: 1;
            min-width: 0;
        }
        
        .file-name {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .file-size {
            font-size: 11px;
            opacity: 0.7;
        }
        
        .file-download {
            color: #667eea;
            font-size: 16px;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .file-attachment:hover .file-download {
            opacity: 1;
        }
        
        .message.sent .file-download {
            color: white;
        }
        
        /* Image Preview in Messages */
        .image-preview-container {
            max-width: 300px;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 8px;
            cursor: pointer;
            position: relative;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .image-preview-container:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }
        
        .image-preview-container img {
            width: 100%;
            height: auto;
            max-height: 300px;
            object-fit: cover;
            display: block;
            border-radius: 10px;
        }
        
        .image-preview-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            padding: 20px 10px 10px;
            font-size: 13px;
            opacity: 0;
            transition: opacity 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .image-preview-container:hover .image-preview-overlay {
            opacity: 1;
        }
        
        /* Video Preview in Messages */
        .video-preview-container {
            max-width: 300px;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 8px;
            cursor: pointer;
            position: relative;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .video-preview-container:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }
        
        .video-preview-container video {
            width: 100%;
            height: auto;
            max-height: 300px;
            display: block;
            border-radius: 10px;
            background: #000;
        }
        
        .video-preview-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            background: rgba(102, 126, 234, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .video-preview-container:hover .video-preview-overlay {
            opacity: 1;
        }
        
        .video-duration {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Loading overlay for AJAX requests */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .loading-spinner-large {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(102, 126, 234, 0.2);
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
        }
        
        /* Deleted group message style */
        .deleted-group-info {
            padding: 20px;
            background: #f8f9ff;
            border-radius: 12px;
            text-align: center;
            margin: 20px;
            border: 2px dashed #ef4444;
        }
        
        .deleted-group-info i {
            font-size: 48px;
            color: #ef4444;
            margin-bottom: 15px;
        }
        
        .deleted-group-info h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .deleted-group-info p {
            color: #666;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner-large"></div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="toast show success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="toast show error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="chat-container">
        <!-- Left Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> SkyMeet</h1>
            </div>

            <!-- User Profile with Photo and Dropdown -->
            <div class="user-profile">
                <div class="user-profile-content" id="userProfileTrigger">
                    <div class="user-avatar">
                        <?php 
                        $profile_photo = getProfilePhoto($current_user);
                        if ($profile_photo): ?>
                            <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="<?php echo htmlspecialchars($username); ?>">
                        <?php else: ?>
                            <?php echo getInitials($username); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($username); ?></h3>
                        <p><?php echo htmlspecialchars($current_user['email'] ?? 'User'); ?></p>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon" id="dropdownIcon"></i>
                </div>
                
                <!-- Dropdown Menu - Only View Profile -->
                <div class="profile-dropdown-menu" id="profileDropdownMenu">
                    <a href="profile.php?id=<?php echo $user_id; ?>" class="profile-dropdown-item">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                </div>
            </div>

            <!-- Main Navigation -->
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="meetings.php" class="nav-item">
                    <i class="fas fa-video"></i> Meetings
                    <?php if ($upcoming_meetings_count > 0): ?>
                        <span class="nav-badge"><?php echo $upcoming_meetings_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="schedule.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i> Schedule
                    <?php if ($upcoming_meetings_count > 0): ?>
                        <span class="nav-badge"><?php echo $upcoming_meetings_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="chat.php" class="nav-item active">
                    <i class="fas fa-comments"></i> Messages
                    <?php if ($total_unread > 0): ?>
                        <span class="nav-badge"><?php echo $total_unread; ?></span>
                    <?php else: ?>
                        <span class="nav-badge">0</span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="help.php" class="nav-item">
                    <i class="fas fa-question-circle"></i> Help & Support
                </a>
            </nav>

            <!-- Sidebar Footer with Logout Button -->
            <div class="sidebar-footer">
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="main-content">
            <!-- Header -->
            <div class="chat-header">
                <div class="chat-header-left">
                    <h2>Messages</h2>
                    <p>Chat with your friends and groups</p>
                </div>
                <div class="chat-header-right">
                    <button class="btn-add-friend-header" onclick="showAddFriendModal()">
                        <i class="fas fa-user-plus"></i> Add Friend
                        <?php if ($pending_requests_count > 0): ?>
                            <span class="friend-request-badge"><?php echo $pending_requests_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="btn-new-group" onclick="showCreateGroupModal()">
                        <i class="fas fa-users"></i> New Group
                    </button>
                </div>
            </div>

            <!-- Chat Layout -->
            <div class="chat-layout">
                <!-- Users List Sidebar - FIXED SCROLLING FOR 9+ ITEMS -->
                <div class="users-list-container">
                    <div class="users-list-header">
                        <h3><i class="fas fa-inbox"></i> Conversations</h3>
                    </div>
                    
                    <div class="search-with-button">
                        <div class="users-search">
                            <input type="text" id="usersSearch" placeholder="Search conversations...">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    
                    <div class="users-list" id="usersList">
                        <?php if (empty($accepted_friends) && empty($user_groups)): ?>
                            <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <p>No conversations yet</p>
                                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                                    Add friends to start chatting
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($accepted_friends as $friend): ?>
                                <?php $is_active = ($chat_type == 'user' && $selected_id == $friend['id']); ?>
                                <?php $unread_count = $unread_counts[$friend['id']] ?? 0; ?>
                                <a href="chat.php?user_id=<?php echo $friend['id']; ?>" 
                                   class="user-item <?php echo $is_active ? 'active' : ''; ?>">
                                    <div class="user-avatar-small">
                                        <?php if (!empty($friend['profile_photo'])): 
                                            $photo_path = 'uploads/profile_photos/' . $friend['profile_photo'];
                                            if (file_exists($photo_path)): ?>
                                                <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>">
                                            <?php else: ?>
                                                <?php echo getInitials($friend['username']); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php echo getInitials($friend['username']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-info-small">
                                        <div class="user-name">
                                            <?php echo htmlspecialchars($friend['username']); ?>
                                        </div>
                                        <div class="user-last-message">
                                            <?php if ($unread_count > 0): ?>
                                                <?php echo $unread_count; ?> unread message<?php echo $unread_count > 1 ? 's' : ''; ?>
                                            <?php else: ?>
                                                Click to chat
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="user-meta">
                                        <?php if ($unread_count > 0): ?>
                                            <span class="unread-badge"><?php echo $unread_count; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            
                            <?php foreach ($user_groups as $group): ?>
                                <?php $is_active = ($chat_type == 'group' && $selected_id == $group['id']); ?>
                                <?php $unread_count = $group_unread_counts[$group['id']] ?? 0; ?>
                                <a href="chat.php?group_id=<?php echo $group['id']; ?>" 
                                   class="user-item <?php echo $is_active ? 'active' : ''; ?>">
                                    <div class="user-avatar-small group-avatar">
                                        <?php echo getInitials($group['name']); ?>
                                    </div>
                                    <div class="user-info-small">
                                        <div class="user-name">
                                            <?php echo htmlspecialchars($group['name']); ?>
                                            <span class="group-badge">Group</span>
                                        </div>
                                        <div class="user-last-message">
                                            <?php if ($unread_count > 0): ?>
                                                <?php echo $unread_count; ?> unread message<?php echo $unread_count > 1 ? 's' : ''; ?>
                                            <?php else: ?>
                                                <?php echo $group['description'] ? substr(htmlspecialchars($group['description']), 0, 30) . '...' : 'Group chat'; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="user-meta">
                                        <?php if ($unread_count > 0): ?>
                                            <span class="unread-badge"><?php echo $unread_count; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-area-container">
                    <?php if (!$selected_id): ?>
                        <!-- No Chat Selected -->
                        <div class="no-chat-selected">
                            <i class="fas fa-comments"></i>
                            <h3>Welcome to SkyMeet Chat</h3>
                            <p>Select a conversation from the list or start a new chat with friends</p>
                        </div>
                    <?php elseif ($chat_type == 'group' && !$selected_chat): ?>
                        <!-- Group not found or deleted -->
                        <div class="deleted-group-info">
                            <i class="fas fa-trash-alt"></i>
                            <h4>Group Not Found</h4>
                            <p>This group may have been deleted or no longer exists.</p>
                            <a href="chat.php" class="btn-start" style="text-decoration: none; display: inline-block;">
                                <i class="fas fa-arrow-left"></i> Back to Chats
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Chat Header -->
                        <div class="chat-area-header">
                            <div class="chat-user-info" onclick="viewUserProfile(<?php echo $chat_type == 'user' ? $selected_id : 0; ?>)">
                                <div class="chat-user-avatar <?php echo $chat_type == 'group' ? 'group-avatar' : ''; ?>">
                                    <?php if ($chat_type == 'user' && !empty($selected_chat['profile_photo'])): 
                                        $photo_path = 'uploads/profile_photos/' . $selected_chat['profile_photo'];
                                        if (file_exists($photo_path)): ?>
                                            <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($selected_chat['username']); ?>">
                                        <?php else: ?>
                                            <?php echo getInitials($selected_chat['username'] ?? 'User'); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php echo getInitials($selected_chat[$chat_type == 'group' ? 'name' : 'username'] ?? 'User'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-user-details">
                                    <h3>
                                        <?php echo htmlspecialchars($selected_chat[$chat_type == 'group' ? 'name' : 'username'] ?? 'User'); ?>
                                        <?php if ($chat_type == 'group'): ?>
                                            <span class="group-badge"><?php echo $selected_chat['member_count'] ?? 0; ?> members</span>
                                            <?php if (($selected_chat['user_role'] ?? '') == 'admin'): ?>
                                                <span class="admin-badge">Admin</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </h3>
                                    <p>
                                        <?php if ($chat_type == 'group'): ?>
                                            Created by <?php echo htmlspecialchars($selected_chat['creator_name'] ?? 'User'); ?>
                                            <?php if (!($selected_chat['is_member'] ?? false)): ?>
                                                • <span style="color: #f59e0b;">Invite only - Contact an admin to join</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($selected_chat['email'] ?? 'Online'); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Chat Actions -->
                            <div class="chat-actions">
                                <?php if ($chat_type == 'group' && ($selected_chat['is_member'] ?? false)): ?>
                                    <?php if (($selected_chat['user_role'] ?? '') == 'admin'): ?>
                                        <button class="invite-btn" onclick="showInviteModal()">
                                            <i class="fas fa-user-plus"></i> Invite Users
                                        </button>
                                        <button class="chat-action-btn delete-group-btn" title="Delete Group" onclick="showDeleteGroupConfirmation(<?php echo $selected_id; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="chat-action-btn info-btn" title="Group Details" onclick="showGroupDetails(<?php echo $selected_id; ?>)">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                    <button class="chat-action-btn" title="Leave Group" onclick="leaveGroup(<?php echo $selected_id; ?>)">
                                        <i class="fas fa-sign-out-alt"></i>
                                    </button>
                                <?php else: ?>
                                    <!-- Friend Chat Actions -->
                                    <?php if ($is_friend): ?>
                                        <button class="chat-action-btn" style="background: #ef4444; color: white; border-color: #ef4444;" 
                                                title="Unfriend" onclick="showUnfriendConfirmation(<?php echo $selected_id; ?>, '<?php echo htmlspecialchars(addslashes($selected_chat['username'] ?? '')); ?>')">
                                            <i class="fas fa-user-minus"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="chat-action-btn" title="Add Friend" onclick="addFriendFromChat(<?php echo $selected_id; ?>)">
                                            <i class="fas fa-user-plus" style="color: #10b981;"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <button class="chat-action-btn" title="Refresh" onclick="window.location.reload()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Messages Container -->
                        <div class="messages-container" id="messagesContainer" oncontextmenu="return false;">
                            <?php if (empty($messages)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-comment-slash"></i>
                                    <p>No messages yet</p>
                                    <p style="font-size: 12px; color: #999; margin-top: 10px;">
                                        Start the conversation by sending a message
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php
                                $current_date = null;
                                foreach ($messages as $message):
                                    $message_date = date('Y-m-d', strtotime($message['created_at']));
                                    if ($current_date != $message_date):
                                        $current_date = $message_date;
                                        $display_date = date('Y-m-d') == $message_date ? 'Today' : 
                                                       (date('Y-m-d', strtotime('-1 day')) == $message_date ? 'Yesterday' : 
                                                        date('F j, Y', strtotime($message_date)));
                                ?>
                                    <div class="message-date">
                                        <span class="date-label"><?php echo $display_date; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php
                                $is_sent = ($message['sender_id'] == $user_id);
                                $sender_name = $chat_type == 'group' ? ($message['sender_name'] ?? 'User') : ($is_sent ? 'You' : ($selected_chat['username'] ?? 'User'));
                                $sender_photo = $message['sender_photo'] ?? ($selected_chat['profile_photo'] ?? null);
                                $message_time = date('H:i', strtotime($message['created_at']));
                                ?>
                                
                                <div class="message <?php echo $is_sent ? 'sent' : 'received'; ?> <?php echo $is_sent ? 'can-delete' : ''; ?>" 
                                     data-message-id="<?php echo $message['id']; ?>" 
                                     data-message-type="<?php echo $chat_type; ?>"
                                     data-can-delete="<?php echo $is_sent ? 'true' : 'false'; ?>"
                                     oncontextmenu="return showContextMenu(event, <?php echo $message['id']; ?>, '<?php echo $chat_type; ?>', <?php echo $is_sent ? 'true' : 'false'; ?>);">
                                    
                                    <div class="message-content">
                                        <!-- Message Header with Avatar, Username and Time -->
                                        <div class="message-header">
                                            <div class="message-avatar">
                                                <?php if (!empty($sender_photo)): 
                                                    $photo_path = 'uploads/profile_photos/' . $sender_photo;
                                                    if (file_exists($photo_path)): ?>
                                                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($sender_name); ?>">
                                                    <?php else: ?>
                                                        <?php echo getInitials($sender_name); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php echo getInitials($sender_name); ?>
                                                <?php endif; ?>
                                            </div>
                                            <span class="message-username"><?php echo htmlspecialchars($sender_name); ?></span>
                                            <span class="message-time-small"><?php echo $message_time; ?></span>
                                        </div>
                                        
                                        <!-- Message Content (Text and Files) -->
                                        <?php if (!empty($message['message'])): ?>
                                            <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($message['file_name'])): ?>
                                            <?php if (isImageFile($message['file_type'])): ?>
                                                <div class="image-preview-container">
                                                    <img src="<?php echo htmlspecialchars($message['file_path']); ?>" 
                                                         alt="<?php echo htmlspecialchars($message['file_name']); ?>"
                                                         class="file-preview-image"
                                                         onclick="showFilePreview('<?php echo htmlspecialchars($message['file_path']); ?>', '<?php echo htmlspecialchars($message['file_name']); ?>', '<?php echo htmlspecialchars($message['file_type']); ?>', <?php echo $message['file_size']; ?>)">
                                                    <div class="image-preview-overlay" onclick="showFilePreview('<?php echo htmlspecialchars($message['file_path']); ?>', '<?php echo htmlspecialchars($message['file_name']); ?>', '<?php echo htmlspecialchars($message['file_type']); ?>', <?php echo $message['file_size']; ?>)">
                                                        <i class="fas fa-search-plus"></i> Click to view
                                                    </div>
                                                </div>
                                            <?php elseif (isVideoFile($message['file_type'])): ?>
                                                <div class="video-preview-container">
                                                    <video preload="metadata" onclick="showFilePreview('<?php echo htmlspecialchars($message['file_path']); ?>', '<?php echo htmlspecialchars($message['file_name']); ?>', '<?php echo htmlspecialchars($message['file_type']); ?>', <?php echo $message['file_size']; ?>)">
                                                        <source src="<?php echo htmlspecialchars($message['file_path']); ?>#t=0.1" type="<?php echo htmlspecialchars($message['file_type']); ?>">
                                                    </video>
                                                    <div class="video-preview-overlay" onclick="showFilePreview('<?php echo htmlspecialchars($message['file_path']); ?>', '<?php echo htmlspecialchars($message['file_name']); ?>', '<?php echo htmlspecialchars($message['file_type']); ?>', <?php echo $message['file_size']; ?>)">
                                                        <i class="fas fa-play"></i>
                                                    </div>
                                                    <span class="video-duration" onclick="showFilePreview('<?php echo htmlspecialchars($message['file_path']); ?>', '<?php echo htmlspecialchars($message['file_name']); ?>', '<?php echo htmlspecialchars($message['file_type']); ?>', <?php echo $message['file_size']; ?>)">
                                                        <i class="fas fa-video"></i> Video
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="file-attachment">
                                                    <i class="fas <?php echo getFileIcon($message['file_type']); ?> file-icon"></i>
                                                    <div class="file-info" onclick="showFilePreview('<?php echo htmlspecialchars($message['file_path']); ?>', '<?php echo htmlspecialchars($message['file_name']); ?>', '<?php echo htmlspecialchars($message['file_type']); ?>', <?php echo $message['file_size']; ?>)">
                                                        <div class="file-name"><?php echo htmlspecialchars($message['file_name']); ?></div>
                                                        <div class="file-size"><?php echo formatFileSize($message['file_size']); ?></div>
                                                    </div>
                                                    <div class="file-actions">
                                                        <a href="<?php echo htmlspecialchars($message['file_path']); ?>" download class="file-action-btn" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <a href="<?php echo htmlspecialchars($message['file_path']); ?>" target="_blank" class="file-action-btn" title="Open in new tab">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Message Input -->
                        <?php if ($chat_type == 'user'): ?>
                            <?php if ($is_friend): ?>
                                <div class="message-input-container">
                                    <form id="messageForm" method="POST" class="message-input-form" enctype="multipart/form-data">
                                        <input type="hidden" name="send_message" value="1">
                                        
                                        <div class="file-upload-wrapper">
                                            <button type="button" class="file-upload-btn" onclick="document.getElementById('chatFile').click()">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <input type="file" id="chatFile" name="chat_file" class="file-upload-input" 
                                                   accept=".jpg,.jpeg,.png,.gif,.mp4,.mov,.mpeg,.pdf,.doc,.docx,.txt,.zip,.rar"
                                                   onchange="previewFile(this)">
                                        </div>
                                        
                                        <div class="message-input-wrapper">
                                            <textarea name="message" 
                                                      class="message-input" 
                                                      id="messageInput" 
                                                      placeholder="Type your message here..."
                                                      rows="1"></textarea>
                                            
                                            <div class="file-preview hidden" id="filePreview">
                                                <i class="fas fa-file" id="filePreviewIcon"></i>
                                                <div class="file-preview-info">
                                                    <div class="file-preview-name" id="fileName"></div>
                                                    <div class="file-preview-size" id="fileSize"></div>
                                                </div>
                                                <button type="button" class="remove-file" onclick="removeFile()">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn-send" id="sendButton">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 30px; background: white; border-top: 2px solid #f0f0f0;">
                                    <p style="color: #666; margin-bottom: 15px;">You need to be friends with this user to send messages</p>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="friend_id" value="<?php echo $selected_id; ?>">
                                        <button type="submit" name="add_friend" class="btn-add-friend">
                                            <i class="fas fa-user-plus"></i> Add Friend
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($chat_type == 'group'): ?>
                            <?php if ($selected_chat['is_member'] ?? false): ?>
                                <div class="message-input-container">
                                    <form id="messageForm" method="POST" class="message-input-form" enctype="multipart/form-data">
                                        <input type="hidden" name="send_message" value="1">
                                        
                                        <div class="file-upload-wrapper">
                                            <button type="button" class="file-upload-btn" onclick="document.getElementById('chatFile').click()">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <input type="file" id="chatFile" name="chat_file" class="file-upload-input" 
                                                   accept=".jpg,.jpeg,.png,.gif,.mp4,.mov,.mpeg,.pdf,.doc,.docx,.txt,.zip,.rar"
                                                   onchange="previewFile(this)">
                                        </div>
                                        
                                        <div class="message-input-wrapper">
                                            <textarea name="message" 
                                                      class="message-input" 
                                                      id="messageInput" 
                                                      placeholder="Type your message here..."
                                                      rows="1"></textarea>
                                            
                                            <div class="file-preview hidden" id="filePreview">
                                                <i class="fas fa-file" id="filePreviewIcon"></i>
                                                <div class="file-preview-info">
                                                    <div class="file-preview-name" id="fileName"></div>
                                                    <div class="file-preview-size" id="fileSize"></div>
                                                </div>
                                                <button type="button" class="remove-file" onclick="removeFile()">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn-send" id="sendButton">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 30px; background: white; border-top: 2px solid #f0f0f0;">
                                    <p style="color: #666; margin-bottom: 15px;">
                                        <i class="fas fa-lock" style="margin-right: 5px;"></i>
                                        This is an invite-only group. You cannot join directly.
                                    </p>
                                    <p style="color: #999; font-size: 14px;">
                                        Contact an admin to get an invitation.
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Context Menu for Messages -->
    <div class="context-menu" id="contextMenu" style="display: none;">
        <div class="context-menu-item delete" id="deleteMessageOption" onclick="confirmDeleteMessage()">
            <i class="fas fa-trash"></i>
            <span>Delete Message</span>
        </div>
    </div>

    <!-- File Preview Modal - Enhanced -->
    <div class="file-preview-modal" id="filePreviewModal" onclick="if(event.target === this) hideFilePreview()">
        <div class="file-preview-content">
            <div class="file-preview-header">
                <h3>
                    <i class="fas fa-file"></i>
                    <span id="previewFileName">File Preview</span>
                </h3>
                <button class="file-preview-close" onclick="hideFilePreview()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="file-preview-body" id="previewBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Group Details Modal -->
    <div class="modal" id="groupDetailsModal">
        <div class="modal-content">
            <button class="close-modal" onclick="hideGroupDetailsModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Group Details</h3>
            </div>
            <?php if ($chat_type == 'group' && $selected_chat && isset($selected_chat['members'])): ?>
            <div class="group-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $selected_chat['member_count']; ?></div>
                    <div class="stat-label">Total Members</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count(array_filter($selected_chat['members'], function($m) { return $m['role'] == 'admin'; })); ?></div>
                    <div class="stat-label">Admins</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('M d, Y', strtotime($selected_chat['created_at'])); ?></div>
                    <div class="stat-label">Created</div>
                </div>
            </div>
            
            <h4 style="margin-bottom: 15px; color: #333;">Group Members</h4>
            <div class="member-list" id="memberList">
                <?php foreach ($selected_chat['members'] as $member): ?>
                <div class="member-item" data-user-id="<?php echo $member['id']; ?>" data-user-role="<?php echo $member['role']; ?>">
                    <div class="member-avatar" onclick="viewUserProfile(<?php echo $member['id']; ?>)">
                        <?php if (!empty($member['profile_photo'])): 
                            $photo_path = 'uploads/profile_photos/' . $member['profile_photo'];
                            if (file_exists($photo_path)): ?>
                                <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($member['username']); ?>">
                            <?php else: ?>
                                <?php echo getInitials($member['username']); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo getInitials($member['username']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="member-info" onclick="viewUserProfile(<?php echo $member['id']; ?>)">
                        <div class="member-name">
                            <?php echo htmlspecialchars($member['username']); ?>
                            <?php if ($member['role'] == 'admin'): ?>
                                <span class="member-role admin">Admin</span>
                            <?php else: ?>
                                <span class="member-role">Member</span>
                            <?php endif; ?>
                            <?php if ($member['id'] == $user_id): ?>
                                <span style="color: #667eea; font-size: 11px;">(You)</span>
                            <?php endif; ?>
                        </div>
                        <div class="member-email"><?php echo htmlspecialchars($member['email']); ?></div>
                        <div class="member-joined">Joined <?php echo date('M d, Y', strtotime($member['joined_at'])); ?></div>
                    </div>
                    <div class="member-actions">
                        <?php if ($member['id'] != $user_id): ?>
                        <button class="member-menu-btn" onclick="toggleMemberMenu(this, <?php echo $member['id']; ?>, '<?php echo htmlspecialchars(addslashes($member['username'])); ?>', <?php echo $selected_id; ?>)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="hideGroupDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Invite to Group Modal - Shows ALL non‑admin users (friendship not required) -->
    <div class="modal" id="inviteModal">
        <div class="modal-content">
            <button class="close-modal" onclick="hideInviteModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Invite Users to Group</h3>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">You can invite any registered user (except admin) to this group</p>
            </div>
            <div class="modal-search">
                <input type="text" id="inviteSearch" placeholder="Search users by name or email..." 
                       onkeyup="searchUsersForInvite()">
                <i class="fas fa-search"></i>
            </div>
            <div class="modal-users-list" id="inviteUsersList">
                <?php if (empty($all_users_for_invite)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No users available to invite</p>
                        <p style="font-size: 12px; color: #999; margin-top: 10px;">
                            All registered users are already in this group
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_users_for_invite as $user): ?>
                        <div class="modal-user-item" data-user-id="<?php echo $user['id']; ?>" 
                             data-user-name="<?php echo htmlspecialchars(strtolower($user['username'])); ?>" 
                             data-user-email="<?php echo htmlspecialchars(strtolower($user['email'])); ?>">
                            <div class="user-avatar-small" onclick="viewUserProfile(<?php echo $user['id']; ?>)">
                                <?php if (!empty($user['profile_photo'])): 
                                    $photo_path = 'uploads/profile_photos/' . $user['profile_photo'];
                                    if (file_exists($photo_path)): ?>
                                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>">
                                    <?php else: ?>
                                        <?php echo getInitials($user['username']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo getInitials($user['username']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="user-info-small" onclick="viewUserProfile(<?php echo $user['id']; ?>)">
                                <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="user-last-message"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="group_id" value="<?php echo $selected_id; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="invite_to_group" class="btn-invite">
                                    <i class="fas fa-user-plus"></i> Invite
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="hideInviteModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Add Friend Modal - Fully Functional with Profile Display -->
    <div class="modal" id="addFriendModal">
        <div class="modal-content">
            <button class="close-modal" onclick="hideAddFriendModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add Friend</h3>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">Browse all registered users and send friend requests</p>
            </div>
            
            <div class="modal-search">
                <input type="text" id="addFriendSearch" placeholder="Search users by name or email..." onkeyup="filterAddFriendUsers()">
                <i class="fas fa-search"></i>
            </div>
            
            <!-- Incoming Friend Requests Section -->
            <div id="addFriendPendingRequestsContainer" class="request-section" style="display: <?php echo !empty($pending_requests) ? 'block' : 'none'; ?>;">
                <h4><i class="fas fa-user-clock"></i> Incoming Friend Requests (<?php echo count($pending_requests); ?>)</h4>
                <div id="addFriendPendingRequestsList">
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="pending-request-item" data-user-id="<?php echo $request['id']; ?>">
                            <div class="user-avatar-small" onclick="viewUserProfile(<?php echo $request['id']; ?>)">
                                <?php if (!empty($request['profile_photo'])): 
                                    $photo_path = 'uploads/profile_photos/' . $request['profile_photo'];
                                    if (file_exists($photo_path)): ?>
                                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($request['username']); ?>">
                                    <?php else: ?>
                                        <?php echo getInitials($request['username']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo getInitials($request['username']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="user-info-small" onclick="viewUserProfile(<?php echo $request['id']; ?>)">
                                <div class="user-name"><?php echo htmlspecialchars($request['username']); ?></div>
                                <div class="user-last-message"><?php echo htmlspecialchars($request['email']); ?></div>
                            </div>
                            <div class="search-actions">
                                <button class="btn-accept-request" onclick="acceptFriendRequest(<?php echo $request['id']; ?>, this)">
                                    <i class="fas fa-check"></i> Accept
                                </button>
                                <button class="btn-reject-request" onclick="rejectFriendRequest(<?php echo $request['id']; ?>, this)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="modal-users-list" id="addFriendUsersList">
                <?php if (empty($all_users_for_friend)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No users found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_users_for_friend as $user): ?>
                        <div class="modal-user-item" data-user-id="<?php echo $user['id']; ?>" 
                             data-user-name="<?php echo htmlspecialchars(strtolower($user['username'])); ?>" 
                             data-user-email="<?php echo htmlspecialchars(strtolower($user['email'])); ?>">
                            
                            <!-- Profile Avatar - Click to view profile -->
                            <div class="user-avatar-small" onclick="viewUserProfile(<?php echo $user['id']; ?>)">
                                <?php if (!empty($user['profile_photo'])): 
                                    $photo_path = 'uploads/profile_photos/' . $user['profile_photo'];
                                    if (file_exists($photo_path)): ?>
                                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo getInitials($user['username']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo getInitials($user['username']); ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- User Info - Click to view profile -->
                            <div class="user-info-small" onclick="viewUserProfile(<?php echo $user['id']; ?>)">
                                <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="user-last-message"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            
                            <!-- Action Buttons based on friendship status -->
                            <?php if ($user['friend_status'] == 'accepted'): ?>
                                <div style="display: flex; gap: 5px;">
                                    <button class="btn-accepted" disabled>
                                        <i class="fas fa-check"></i> Friends
                                    </button>
                                    <button class="btn-unfriend-modal" onclick="showUnfriendConfirmation(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')">
                                        <i class="fas fa-user-minus"></i> Unfriend
                                    </button>
                                </div>
                            <?php elseif ($user['friend_status'] == 'pending' && $user['is_pending_from_me']): ?>
                                <button class="btn-pending" disabled>
                                    <i class="fas fa-clock"></i> Pending
                                </button>
                            <?php elseif ($user['friend_status'] == 'pending' && $user['is_pending_to_me']): ?>
                                <!-- This should not appear here as we filtered out pending requests -->
                            <?php else: ?>
                                <button class="btn-add-friend" onclick="addFriend(<?php echo $user['id']; ?>, this)">
                                    <i class="fas fa-user-plus"></i> Add Friend
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="hideAddFriendModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Create Group Modal with Character Limit  -->
    <div class="modal" id="createGroupModal">
        <div class="modal-content">
            <button class="close-modal" onclick="hideCreateGroupModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-users"></i> Create New Group</h3>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">Groups are invite-only by default for privacy</p>
            </div>
            
            <form method="POST" id="createGroupForm" onsubmit="return validateGroupName()">
                <div class="form-group">
                    <label for="group_name">Group Name * (Max 15 characters)</label>
                    <input type="text" id="group_name" name="group_name" placeholder="Enter group name" maxlength="15" required onkeyup="updateCharCount()">
                    <div class="char-count" id="charCountDisplay">0/15 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="group_description">Description</label>
                    <textarea id="group_description" name="group_description" placeholder="Group description (optional)"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Group Type</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="join_type" value="invite_only" checked>
                            <span>Invite Only (Only admins can add members)</span>
                        </label>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="hideCreateGroupModal()">Cancel</button>
                    <button type="submit" name="create_group" class="btn-start" id="createGroupSubmitBtn">
                        <i class="fas fa-plus"></i> Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dropdown Menu for Member Actions -->
    <div class="dropdown-menu" id="memberDropdown" style="display: none;">
        <div class="dropdown-header">
            <span id="dropdownMemberName"></span>
        </div>
        <div class="dropdown-item" onclick="viewUserProfile(currentSelectedMemberId)">
            <i class="fas fa-user" style="color: #667eea;"></i>
            <span>View Profile</span>
        </div>
        <div class="dropdown-item" onclick="sendMessageToUser()">
            <i class="fas fa-comment" style="color: #10b981;"></i>
            <span>Send Message</span>
        </div>
        <?php if ($selected_chat && $selected_chat['user_role'] == 'admin'): ?>
        <div class="dropdown-item" id="kickUserOption" onclick="showKickConfirmation()" style="border-top: 1px solid #eee; color: #ef4444;">
            <i class="fas fa-user-slash" style="color: #ef4444;"></i>
            <span>Kick from Group</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Kick Confirmation Modal -->
    <div class="modal" id="kickConfirmModal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="close-modal" onclick="hideKickModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Kick</h3>
            </div>
            <div style="padding: 20px 0; text-align: center;">
                <i class="fas fa-user-slash" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                <p style="margin-bottom: 10px; font-size: 16px;">Are you sure you want to kick <strong id="kickUserName"></strong> from the group?</p>
                <p style="color: #666; font-size: 14px;">This action cannot be undone. The user will be notified.</p>
            </div>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn-cancel" onclick="hideKickModal()">Cancel</button>
                <button class="btn-start" id="confirmKickBtn" style="background: #ef4444;" onclick="executeKick()">
                    <i class="fas fa-user-slash"></i> Yes, Kick Member
                </button>
            </div>
        </div>
    </div>

    <!-- Unfriend Confirmation Modal -->
    <div class="modal" id="unfriendConfirmModal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="close-modal" onclick="hideUnfriendModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Unfriend</h3>
            </div>
            <div style="padding: 20px 0; text-align: center;">
                <i class="fas fa-user-minus" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                <p style="margin-bottom: 10px; font-size: 16px;">Are you sure you want to remove <strong id="unfriendUserName"></strong> from your friends?</p>
                <p style="color: #666; font-size: 14px;">This action cannot be undone. You will no longer be able to chat with this user.</p>
            </div>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn-cancel" onclick="hideUnfriendModal()">Cancel</button>
                <button class="btn-start" id="confirmUnfriendBtn" style="background: #ef4444;" onclick="executeUnfriend()">
                    <i class="fas fa-user-minus"></i> Yes, Remove Friend
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Group Confirmation Modal -->
    <div class="modal" id="deleteGroupConfirmModal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="close-modal" onclick="hideDeleteGroupModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Delete Group</h3>
            </div>
            <div style="padding: 20px 0; text-align: center;">
                <i class="fas fa-trash" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                <p style="margin-bottom: 10px; font-size: 16px;">Are you sure you want to delete this group?</p>
                <p style="color: #666; font-size: 14px;">This action cannot be undone. All messages will be deleted and members will be notified.</p>
                <p style="color: #ef4444; font-size: 14px; font-weight: 600; margin-top: 10px;">This is permanent and cannot be recovered!</p>
            </div>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn-cancel" onclick="hideDeleteGroupModal()">Cancel</button>
                <button class="btn-start" id="confirmDeleteGroupBtn" style="background: #ef4444;" onclick="executeDeleteGroup()">
                    <i class="fas fa-trash"></i> Yes, Delete Group
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Message Confirmation Modal -->
    <div class="modal" id="deleteMessageConfirmModal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="close-modal" onclick="hideDeleteMessageModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Delete</h3>
            </div>
            <div style="padding: 20px 0; text-align: center;">
                <i class="fas fa-trash" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                <p style="margin-bottom: 10px; font-size: 16px;">Are you sure you want to delete this message?</p>
                <p style="color: #666; font-size: 14px;">This action cannot be undone. The message will be removed for everyone.</p>
            </div>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn-cancel" onclick="hideDeleteMessageModal()">Cancel</button>
                <button class="btn-start" id="confirmDeleteMessageBtn" style="background: #ef4444;" onclick="executeDeleteMessage()">
                    <i class="fas fa-trash"></i> Yes, Delete Message
                </button>
            </div>
        </div>
    </div>

    <!-- Error Popup Modal -->
    <div class="modal" id="errorModal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="close-modal" onclick="hideErrorModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> Error</h3>
            </div>
            <div style="padding: 20px 0; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                <p id="errorMessage" style="font-size: 16px; color: #333;"></p>
            </div>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn-start" onclick="hideErrorModal()" style="background: #667eea;">OK</button>
            </div>
        </div>
    </div>

    <!-- Success Popup Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="close-modal" onclick="hideSuccessModal()">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-check-circle" style="color: #10b981;"></i> Success</h3>
            </div>
            <div style="padding: 20px 0; text-align: center;">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 15px;"></i>
                <p id="successMessage" style="font-size: 16px; color: #333;"></p>
            </div>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn-start" onclick="hideSuccessModal()" style="background: #10b981;">OK</button>
            </div>
        </div>
    </div>

    <script>
        // GLOBAL VARIABLES 
        let currentSelectedMemberId = null;
        let currentSelectedMemberName = '';
        let currentSelectedFriendId = null;
        let currentSelectedFriendName = '';
        let currentGroupId = <?php echo $selected_id ?? 0; ?>;
        let currentUserRole = '<?php echo $selected_chat['user_role'] ?? 'member'; ?>';
        let lastMessageId = <?php echo $last_message_id ?? 0; ?>;
        let chatType = '<?php echo $chat_type; ?>';
        let selectedId = <?php echo $selected_id ?? 0; ?>;
        let currentUserId = <?php echo $user_id; ?>;
        let messageCheckInterval = null;
        let searchTimeout = null;
        let pendingRequestsInterval = null;
        let isSubmitting = false;
        
        // Sensitive words list (for client-side validation)
        const sensitiveWords = <?php echo json_encode($sensitive_words); ?>;
        
        // Delete message variables
        let currentDeleteMessageId = null;
        let currentDeleteMessageType = null;
        let currentDeleteMessageElement = null;
        
        // Delete group variables
        let currentDeleteGroupId = null;

        // Context menu variables
        let contextMenuMessageId = null;
        let contextMenuMessageType = null;
        let contextMenuMessageElement = null;

        // Define max file size (40MB)
        const MAX_FILE_SIZE = 40 * 1024 * 1024;
        
        // Allowed file extensions
        const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'mpeg', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];

        // PROFILE DROPDOWN MENU 
        const userProfileTrigger = document.getElementById('userProfileTrigger');
        const profileDropdownMenu = document.getElementById('profileDropdownMenu');
        const dropdownIcon = document.getElementById('dropdownIcon');

        if (userProfileTrigger) {
            userProfileTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdownMenu.classList.toggle('show');
                if (dropdownIcon) {
                    dropdownIcon.style.transform = profileDropdownMenu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0)';
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (profileDropdownMenu && !profileDropdownMenu.contains(e.target) && !userProfileTrigger.contains(e.target)) {
                profileDropdownMenu.classList.remove('show');
                if (dropdownIcon) {
                    dropdownIcon.style.transform = 'rotate(0)';
                }
            }
        });

        // Prevent dropdown from closing when clicking inside dropdown
        if (profileDropdownMenu) {
            profileDropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure dropdown is closed on page load
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        // Handle pageshow event (for back button navigation)
        window.addEventListener('pageshow', function() {
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        // UTILITY FUNCTIONS 
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast show ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 5000);
        }

        function showErrorPopup(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').classList.add('active');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                hideErrorModal();
            }, 5000);
        }

        function hideErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }

        function showSuccessPopup(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').classList.add('active');
            
            setTimeout(() => {
                hideSuccessModal();
            }, 3000);
        }

        function hideSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getInitials(name) {
            if (!name) return '?';
            return name.charAt(0).toUpperCase();
        }

        function isImageFile(fileType) {
            return fileType && fileType.startsWith('image/');
        }

        function isVideoFile(fileType) {
            return fileType && fileType.startsWith('video/');
        }

        function getFileIcon(fileType) {
            if (!fileType) return 'fa-file';
            if (fileType.startsWith('image/')) return 'fa-image';
            if (fileType.startsWith('video/')) return 'fa-video';
            if (fileType.includes('pdf')) return 'fa-file-pdf';
            if (fileType.includes('word') || fileType.includes('document')) return 'fa-file-word';
            if (fileType.startsWith('text/')) return 'fa-file-alt';
            if (fileType.includes('zip') || fileType.includes('rar')) return 'fa-file-archive';
            return 'fa-file';
        }

        function formatFileSize(bytes) {
            if (!bytes) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // CHARACTER COUNT AND SENSITIVE WORD FILTER FOR GROUP CREATION 

        function containsSensitiveWords(text) {
            const textLower = text.toLowerCase();
            for (let word of sensitiveWords) {
                if (textLower.includes(word.toLowerCase())) {
                    return true;
                }
            }
            return false;
        }

        function updateCharCount() {
            const input = document.getElementById('group_name');
            const display = document.getElementById('charCountDisplay');
            const charCount = input.value.length;
            
            display.textContent = charCount + '/15 characters';
            
            if (charCount > 15) {
                display.className = 'char-count error';
            } else if (charCount > 12) {
                display.className = 'char-count warning';
            } else {
                display.className = 'char-count';
            }
            
            if (containsSensitiveWords(input.value)) {
                display.className = 'char-count error';
                display.innerHTML += ' ⚠️ Contains inappropriate words';
            }
        }

        function validateGroupName() {
            const input = document.getElementById('group_name');
            const charCount = input.value.length;
            
            if (charCount > 15) {
                showErrorPopup('Group name cannot exceed 15 characters. Current length: ' + charCount);
                return false;
            }
            
            if (containsSensitiveWords(input.value)) {
                showErrorPopup('Group name contains inappropriate words. Please choose a different name.');
                return false;
            }
            
            return true;
        }

        // CONTEXT MENU FUNCTIONS 

        function showContextMenu(event, messageId, messageType, canDelete) {
            event.preventDefault();
            event.stopPropagation();
            
            // Only show context menu if user can delete this message
            if (canDelete !== 'true' && canDelete !== true) {
                return false;
            }
            
            hideContextMenu();
            
            contextMenuMessageId = messageId;
            contextMenuMessageType = messageType;
            contextMenuMessageElement = event.currentTarget;
            
            const contextMenu = document.getElementById('contextMenu');
            
            const x = event.clientX;
            const y = event.clientY;
            
            contextMenu.style.display = 'block';
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            
            // Ensure menu stays within viewport
            const menuWidth = contextMenu.offsetWidth;
            const menuHeight = contextMenu.offsetHeight;
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            if (x + menuWidth > windowWidth) {
                contextMenu.style.left = (windowWidth - menuWidth - 10) + 'px';
            }
            
            if (y + menuHeight > windowHeight) {
                contextMenu.style.top = (windowHeight - menuHeight - 10) + 'px';
            }
            
            return false;
        }

        function hideContextMenu() {
            document.getElementById('contextMenu').style.display = 'none';
        }

        function confirmDeleteMessage() {
            if (contextMenuMessageId && contextMenuMessageType) {
                showDeleteMessageConfirmation(contextMenuMessageId, contextMenuMessageType, contextMenuMessageElement);
            }
            hideContextMenu();
        }

        // GROUP DELETE FUNCTIONS 

        function showDeleteGroupConfirmation(groupId) {
            currentDeleteGroupId = groupId;
            document.getElementById('deleteGroupConfirmModal').classList.add('active');
        }

        function hideDeleteGroupModal() {
            document.getElementById('deleteGroupConfirmModal').classList.remove('active');
            currentDeleteGroupId = null;
        }

        function executeDeleteGroup() {
            if (!currentDeleteGroupId) {
                hideDeleteGroupModal();
                return;
            }
            
            const groupId = currentDeleteGroupId;
            
            hideDeleteGroupModal();
            
            showLoading();
            
            // Redirect to delete group URL
            window.location.href = `chat.php?delete_group=${groupId}`;
        }

        // ADD FRIEND MODAL FUNCTIONS 

        function showAddFriendModal() {
            document.getElementById('addFriendModal').classList.add('active');
            loadAddFriendPendingRequests();
            
            if (pendingRequestsInterval) {
                clearInterval(pendingRequestsInterval);
            }
            pendingRequestsInterval = setInterval(loadAddFriendPendingRequests, 10000);
        }

        function hideAddFriendModal() {
            document.getElementById('addFriendModal').classList.remove('active');
            if (pendingRequestsInterval) {
                clearInterval(pendingRequestsInterval);
            }
        }

        function loadAddFriendPendingRequests() {
            fetch('chat.php?ajax=get_pending_requests')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('addFriendPendingRequestsContainer');
                    const list = document.getElementById('addFriendPendingRequestsList');
                    
                    if (data.success && data.requests.length > 0) {
                        let html = '';
                        data.requests.forEach(request => {
                            const profilePhotoHtml = request.profile_photo 
                                ? `<img src="uploads/profile_photos/${request.profile_photo}" alt="${request.username}" style="width: 100%; height: 100%; object-fit: cover;">`
                                : getInitials(request.username);
                            
                            html += `
                                <div class="pending-request-item" data-user-id="${request.id}">
                                    <div class="user-avatar-small" onclick="viewUserProfile(${request.id})">${profilePhotoHtml}</div>
                                    <div class="user-info-small" onclick="viewUserProfile(${request.id})">
                                        <div class="user-name">${escapeHtml(request.username)}</div>
                                        <div class="user-last-message">${escapeHtml(request.email)}</div>
                                    </div>
                                    <div class="search-actions">
                                        <button class="btn-accept-request" onclick="acceptFriendRequest(${request.id}, this)">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button class="btn-reject-request" onclick="rejectFriendRequest(${request.id}, this)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        
                        container.style.display = 'block';
                        list.innerHTML = html;
                        
                        // Update badge on header
                        const badge = document.querySelector('.btn-add-friend-header .friend-request-badge');
                        if (badge) {
                            badge.textContent = data.count;
                        } else if (data.count > 0) {
                            const btn = document.querySelector('.btn-add-friend-header');
                            const newBadge = document.createElement('span');
                            newBadge.className = 'friend-request-badge';
                            newBadge.textContent = data.count;
                            btn.appendChild(newBadge);
                        }
                    } else {
                        container.style.display = 'none';
                        list.innerHTML = '';
                        
                        // Remove badge if exists
                        const badge = document.querySelector('.btn-add-friend-header .friend-request-badge');
                        if (badge) {
                            badge.remove();
                        }
                    }
                })
                .catch(error => console.error('Error loading pending requests:', error));
        }

        function filterAddFriendUsers() {
            const search = document.getElementById('addFriendSearch').value.toLowerCase();
            const items = document.querySelectorAll('#addFriendUsersList .modal-user-item');
            
            items.forEach(item => {
                const name = item.getAttribute('data-user-name');
                const email = item.getAttribute('data-user-email');
                
                if (name.includes(search) || email.includes(search)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // FRIEND REQUEST FUNCTIONS 

        function acceptFriendRequest(friendId, button) {
            const requestItem = button.closest('.pending-request-item');
            
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            showLoading();
            
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=accept_friend&friend_id=${friendId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    if (requestItem) {
                        requestItem.remove();
                    }
                    
                    const requestSection = document.querySelector('.request-section');
                    if (requestSection && requestSection.querySelectorAll('.pending-request-item').length === 0) {
                        requestSection.style.display = 'none';
                    }
                    
                    // Update badge
                    loadAddFriendPendingRequests();
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message, 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            })
            .finally(() => {
                hideLoading();
            });
        }

        function rejectFriendRequest(friendId, button) {
            if (!confirm('Reject this friend request?')) return;
            
            const requestItem = button.closest('.pending-request-item');
            
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            showLoading();
            
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=reject_friend&friend_id=${friendId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    if (requestItem) {
                        requestItem.remove();
                    }
                    
                    const requestSection = document.querySelector('.request-section');
                    if (requestSection && requestSection.querySelectorAll('.pending-request-item').length === 0) {
                        requestSection.style.display = 'none';
                    }
                    
                    // Update badge
                    loadAddFriendPendingRequests();
                } else {
                    showToast(data.message, 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            })
            .finally(() => {
                hideLoading();
            });
        }

        function addFriend(friendId, button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            showLoading();
            
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=add_friend&friend_id=${friendId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    const newButton = document.createElement('button');
                    newButton.className = 'btn-pending';
                    newButton.disabled = true;
                    newButton.innerHTML = '<i class="fas fa-clock"></i> Pending';
                    button.parentNode.replaceChild(newButton, button);
                } else {
                    showToast(data.message, 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            })
            .finally(() => {
                hideLoading();
            });
        }

        function addFriendFromChat(friendId) {
            const button = event.currentTarget;
            
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            showLoading();
            
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=add_friend&friend_id=${friendId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    button.innerHTML = '<i class="fas fa-clock"></i>';
                    button.title = 'Request Pending';
                    button.style.background = '#fbbf24';
                    button.style.borderColor = '#fbbf24';
                    button.disabled = true;
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                button.innerHTML = originalHtml;
                button.disabled = false;
            })
            .finally(() => {
                hideLoading();
            });
        }

        // UNFRIEND FUNCTIONS 

        function showUnfriendConfirmation(friendId, friendName) {
            currentSelectedFriendId = friendId;
            currentSelectedFriendName = friendName;
            document.getElementById('unfriendUserName').textContent = friendName;
            document.getElementById('unfriendConfirmModal').classList.add('active');
        }

        function hideUnfriendModal() {
            document.getElementById('unfriendConfirmModal').classList.remove('active');
        }

        function executeUnfriend() {
            hideUnfriendModal();
            
            const confirmBtn = document.getElementById('confirmUnfriendBtn');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmBtn.disabled = true;
            
            showLoading();
            
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=unfriend&friend_id=${currentSelectedFriendId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'chat.php';
                    }, 1500);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
                hideLoading();
            });
        }

        // VIEW PROFILE FUNCTION 
        function viewUserProfile(userId) {
            if (userId && userId > 0) {
                window.location.href = `profile.php?id=${userId}`;
            }
            closeDropdown();
        }

        // FILE UPLOAD FUNCTIONS 

        function validateFileUpload() {
            const fileInput = document.getElementById('chatFile');
            if (fileInput && fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const fileExt = file.name.split('.').pop().toLowerCase();
                const fileSize = file.size;
                
                if (fileSize > MAX_FILE_SIZE) {
                    showToast('File size exceeds the maximum limit of 40MB.', 'error');
                    return false;
                }
                
                if (!ALLOWED_EXTENSIONS.includes(fileExt)) {
                    showToast('File type not supported. Allowed types: Images (JPG, PNG, GIF), Videos (MP4), Documents (PDF, DOC, DOCX, TXT), Archives (ZIP, RAR)', 'error');
                    return false;
                }
            }
            return true;
        }

        function previewFile(input) {
            const filePreview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const filePreviewIcon = document.getElementById('filePreviewIcon');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileExt = file.name.split('.').pop().toLowerCase();
                const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                
                if (file.size > MAX_FILE_SIZE) {
                    showToast('File size exceeds the maximum limit of 40MB.', 'error');
                    input.value = '';
                    filePreview.classList.add('hidden');
                    return;
                }
                
                if (!ALLOWED_EXTENSIONS.includes(fileExt)) {
                    showToast('File type not supported. Allowed types: Images (JPG, PNG, GIF), Videos (MP4), Documents (PDF, DOC, DOCX, TXT), Archives (ZIP, RAR)', 'error');
                    input.value = '';
                    filePreview.classList.add('hidden');
                    return;
                }
                
                fileName.textContent = file.name;
                fileSize.textContent = fileSizeMB + ' MB';
                
                if (file.type.startsWith('image/')) {
                    filePreviewIcon.className = 'fas fa-image';
                } else if (file.type.startsWith('video/')) {
                    filePreviewIcon.className = 'fas fa-video';
                } else if (file.type.includes('pdf')) {
                    filePreviewIcon.className = 'fas fa-file-pdf';
                } else if (file.type.includes('word') || file.type.includes('document')) {
                    filePreviewIcon.className = 'fas fa-file-word';
                } else if (file.type.startsWith('text/')) {
                    filePreviewIcon.className = 'fas fa-file-alt';
                } else if (file.type.includes('zip') || file.type.includes('rar')) {
                    filePreviewIcon.className = 'fas fa-file-archive';
                } else {
                    if (fileExt === 'jpg' || fileExt === 'jpeg' || fileExt === 'png' || fileExt === 'gif') {
                        filePreviewIcon.className = 'fas fa-image';
                    } else if (fileExt === 'mp4' || fileExt === 'mov' || fileExt === 'mpeg') {
                        filePreviewIcon.className = 'fas fa-video';
                    } else if (fileExt === 'pdf') {
                        filePreviewIcon.className = 'fas fa-file-pdf';
                    } else if (fileExt === 'doc' || fileExt === 'docx') {
                        filePreviewIcon.className = 'fas fa-file-word';
                    } else if (fileExt === 'txt') {
                        filePreviewIcon.className = 'fas fa-file-alt';
                    } else if (fileExt === 'zip' || fileExt === 'rar') {
                        filePreviewIcon.className = 'fas fa-file-archive';
                    } else {
                        filePreviewIcon.className = 'fas fa-file';
                    }
                }
                
                filePreview.classList.remove('hidden');
            }
        }

        function removeFile() {
            const fileInput = document.getElementById('chatFile');
            const filePreview = document.getElementById('filePreview');
            if (fileInput) {
                fileInput.value = '';
            }
            filePreview.classList.add('hidden');
        }

        // FILE PREVIEW MODAL FUNCTIONS 

        function showFilePreview(filePath, fileName, fileType, fileSize) {
            const modal = document.getElementById('filePreviewModal');
            const body = document.getElementById('previewBody');
            const fileNameSpan = document.getElementById('previewFileName');
            
            fileNameSpan.textContent = fileName;
            
            let content = '';
            
            if (isImageFile(fileType)) {
                content = `
                    <div style="position: relative;">
                        <img src="${filePath}" alt="${fileName}" style="max-width: 100%; max-height: 70vh; cursor: zoom-in;" 
                             onclick="toggleImageZoom(this)">
                        <div style="margin-top: 20px;">
                            <div class="file-info" style="background: white; padding: 20px; border-radius: 12px; text-align: left;">
                                <div style="display: flex; align-items: center; gap: 20px;">
                                    <i class="fas fa-image" style="font-size: 48px; color: #667eea;"></i>
                                    <div style="flex: 1;">
                                        <div class="file-name" style="font-weight: 600; font-size: 16px; margin-bottom: 5px;">
                                            ${escapeHtml(fileName)}
                                        </div>
                                        <div class="file-meta" style="color: #666; font-size: 14px; margin-bottom: 5px;">
                                            Type: Image | Size: ${formatFileSize(fileSize)}
                                        </div>
                                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                                            <a href="${filePath}" download class="btn-start" style="text-decoration: none; padding: 10px 20px;">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <a href="${filePath}" target="_blank" class="btn-cancel" style="text-decoration: none; padding: 10px 20px;">
                                                <i class="fas fa-external-link-alt"></i> Open in New Tab
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (isVideoFile(fileType)) {
                content = `
                    <div>
                        <video controls autoplay style="max-width: 100%; max-height: 70vh; border-radius: 8px;">
                            <source src="${filePath}" type="${fileType}">
                            Your browser does not support the video tag.
                        </video>
                        <div style="margin-top: 20px;">
                            <div class="file-info" style="background: white; padding: 20px; border-radius: 12px; text-align: left;">
                                <div style="display: flex; align-items: center; gap: 20px;">
                                    <i class="fas fa-video" style="font-size: 48px; color: #667eea;"></i>
                                    <div style="flex: 1;">
                                        <div class="file-name" style="font-weight: 600; font-size: 16px; margin-bottom: 5px;">
                                            ${escapeHtml(fileName)}
                                        </div>
                                        <div class="file-meta" style="color: #666; font-size: 14px; margin-bottom: 5px;">
                                            Type: Video | Size: ${formatFileSize(fileSize)}
                                        </div>
                                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                                            <a href="${filePath}" download class="btn-start" style="text-decoration: none; padding: 10px 20px;">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <a href="${filePath}" target="_blank" class="btn-cancel" style="text-decoration: none; padding: 10px 20px;">
                                                <i class="fas fa-external-link-alt"></i> Open in New Tab
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                let documentPreview = '';
                
                if (fileType.includes('pdf')) {
                    documentPreview = `
                        <div style="margin-bottom: 20px;">
                            <embed src="${filePath}#toolbar=0" type="application/pdf" width="100%" height="500px" style="border-radius: 8px;">
                        </div>
                    `;
                }
                
                content = `
                    ${documentPreview}
                    <div class="file-info" style="background: white; padding: 20px; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <i class="fas ${getFileIcon(fileType)}" style="font-size: 48px; color: #667eea;"></i>
                            <div style="flex: 1; text-align: left;">
                                <div class="file-name" style="font-weight: 600; font-size: 16px; margin-bottom: 5px;">
                                    ${escapeHtml(fileName)}
                                </div>
                                <div class="file-meta" style="color: #666; font-size: 14px; margin-bottom: 5px;">
                                    Type: ${fileType.split('/').pop().toUpperCase()} | 
                                    Size: ${formatFileSize(fileSize)}
                                </div>
                                <div style="display: flex; gap: 10px; margin-top: 10px;">
                                    <a href="${filePath}" download class="btn-start" style="text-decoration: none; padding: 10px 20px;">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <a href="${filePath}" target="_blank" class="btn-cancel" style="text-decoration: none; padding: 10px 20px;">
                                        <i class="fas fa-external-link-alt"></i> Open in New Tab
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            body.innerHTML = content;
            modal.classList.add('active');
        }

        function toggleImageZoom(img) {
            if (img.style.cursor === 'zoom-out') {
                img.style.maxWidth = '100%';
                img.style.maxHeight = '70vh';
                img.style.cursor = 'zoom-in';
            } else {
                img.style.maxWidth = 'none';
                img.style.maxHeight = '90vh';
                img.style.cursor = 'zoom-out';
            }
        }

        function hideFilePreview() {
            document.getElementById('filePreviewModal').classList.remove('active');
        }

        // REAL-TIME MESSAGING FUNCTIONS 

        function initRealTimeMessaging() {
            if (selectedId > 0) {
                if (messageCheckInterval) {
                    clearInterval(messageCheckInterval);
                }
                messageCheckInterval = setInterval(checkNewMessages, 2000);
            }
        }

        function checkNewMessages() {
            if (!selectedId) return;
            
            let url = `chat.php?ajax=get_messages&${chatType === 'group' ? 'group_id' : 'user_id'}=${selectedId}&last_id=${lastMessageId}&t=${Date.now()}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.messages && data.messages.length > 0) {
                        appendNewMessages(data.messages);
                        lastMessageId = data.last_id;
                    }
                })
                .catch(error => console.error('Error checking messages:', error));
        }

        function appendNewMessages(messages) {
            const container = document.getElementById('messagesContainer');
            const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
            
            const emptyState = container.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            messages.forEach(message => {
                const messageHtml = createMessageHtml(message);
                container.insertAdjacentHTML('beforeend', messageHtml);
            });
            
            if (wasAtBottom) {
                container.scrollTop = container.scrollHeight;
            }
        }

        function createMessageHtml(message) {
            const isSent = message.sender_id == currentUserId;
            const time = message.created_at_formatted || new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            let senderName = '';
            if (chatType === 'group') {
                senderName = message.sender_name || 'User';
            } else {
                senderName = isSent ? 'You' : (selectedChatUsername || 'User');
            }
            
            const senderPhoto = message.sender_photo || null;
            
            let messageText = '';
            if (message.message) {
                messageText = `<div class="message-text">${escapeHtml(message.message).replace(/\n/g, '<br>')}</div>`;
            }
            
            let fileHtml = '';
            if (message.file_name) {
                if (isImageFile(message.file_type)) {
                    fileHtml = `
                        <div class="image-preview-container">
                            <img src="${escapeHtml(message.file_path)}" 
                                 alt="${escapeHtml(message.file_name)}"
                                 class="file-preview-image"
                                 onclick="showFilePreview('${escapeHtml(message.file_path)}', '${escapeHtml(message.file_name)}', '${escapeHtml(message.file_type)}', ${message.file_size})">
                            <div class="image-preview-overlay" onclick="showFilePreview('${escapeHtml(message.file_path)}', '${escapeHtml(message.file_name)}', '${escapeHtml(message.file_type)}', ${message.file_size})">
                                <i class="fas fa-search-plus"></i> Click to view
                            </div>
                        </div>
                    `;
                } else if (isVideoFile(message.file_type)) {
                    fileHtml = `
                        <div class="video-preview-container">
                            <video preload="metadata" onclick="showFilePreview('${escapeHtml(message.file_path)}', '${escapeHtml(message.file_name)}', '${escapeHtml(message.file_type)}', ${message.file_size})">
                                <source src="${escapeHtml(message.file_path)}#t=0.1" type="${escapeHtml(message.file_type)}">
                            </video>
                            <div class="video-preview-overlay" onclick="showFilePreview('${escapeHtml(message.file_path)}', '${escapeHtml(message.file_name)}', '${escapeHtml(message.file_type)}', ${message.file_size})">
                                <i class="fas fa-play"></i>
                            </div>
                            <span class="video-duration" onclick="showFilePreview('${escapeHtml(message.file_path)}', '${escapeHtml(message.file_name)}', '${escapeHtml(message.file_type)}', ${message.file_size})">
                                <i class="fas fa-video"></i> Video
                            </span>
                        </div>
                    `;
                } else {
                    fileHtml = `
                        <div class="file-attachment">
                            <i class="fas ${getFileIcon(message.file_type)} file-icon"></i>
                            <div class="file-info" onclick="showFilePreview('${escapeHtml(message.file_path)}', '${escapeHtml(message.file_name)}', '${escapeHtml(message.file_type)}', ${message.file_size})">
                                <div class="file-name">${escapeHtml(message.file_name)}</div>
                                <div class="file-size">${formatFileSize(message.file_size)}</div>
                            </div>
                            <div class="file-actions">
                                <a href="${escapeHtml(message.file_path)}" download class="file-action-btn" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="${escapeHtml(message.file_path)}" target="_blank" class="file-action-btn" title="Open in new tab">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                    `;
                }
            }
            
            // Avatar HTML
            const avatarHtml = senderPhoto 
                ? `<img src="uploads/profile_photos/${senderPhoto}" alt="${escapeHtml(senderName)}">`
                : getInitials(senderName);
            
            return `
                <div class="message ${isSent ? 'sent' : 'received'} ${isSent ? 'can-delete' : ''}" 
                     data-message-id="${message.id}" 
                     data-message-type="${chatType}"
                     data-can-delete="${isSent}"
                     oncontextmenu="return showContextMenu(event, ${message.id}, '${chatType}', ${isSent});">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="message-avatar">${avatarHtml}</div>
                            <span class="message-username">${escapeHtml(senderName)}</span>
                            <span class="message-time-small">${time}</span>
                        </div>
                        ${messageText}
                        ${fileHtml}
                    </div>
                </div>
            `;
        }
        
        // FIXED DELETE MESSAGE FUNCTIONS 

        function showDeleteMessageConfirmation(messageId, messageType, element) {
            currentDeleteMessageId = messageId;
            currentDeleteMessageType = messageType;
            currentDeleteMessageElement = element;
            document.getElementById('deleteMessageConfirmModal').classList.add('active');
        }

        function hideDeleteMessageModal() {
            document.getElementById('deleteMessageConfirmModal').classList.remove('active');
            currentDeleteMessageId = null;
            currentDeleteMessageType = null;
            currentDeleteMessageElement = null;
        }

        function executeDeleteMessage() {
            if (!currentDeleteMessageId || !currentDeleteMessageType) {
                hideDeleteMessageModal();
                return;
            }
            
            const messageId = currentDeleteMessageId;
            const messageType = currentDeleteMessageType;
            const messageElement = currentDeleteMessageElement;
            
            hideDeleteMessageModal();
            
            // Store original button state
            const confirmBtn = document.getElementById('confirmDeleteMessageBtn');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            confirmBtn.disabled = true;
            
            // Show loading
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) loadingOverlay.classList.add('active');
            
            console.log("Attempting to delete message:", {
                id: messageId,
                type: messageType,
                element: messageElement
            });
            
            // Create form data
            const formData = new URLSearchParams();
            formData.append('ajax', 'delete_message');
            formData.append('message_id', messageId);
            formData.append('message_type', messageType);
            
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
            .then(response => {
                console.log("Response status:", response.status);
                return response.json();
            })
            .then(data => {
                console.log("Delete response data:", data);
                
                if (data.success) {
                    // Successfully deleted
                    if (messageElement) {
                        // Add fade-out animation
                        messageElement.style.transition = 'opacity 0.3s, transform 0.3s';
                        messageElement.style.opacity = '0';
                        messageElement.style.transform = 'scale(0.9)';
                        
                        setTimeout(() => {
                            if (messageElement && messageElement.parentNode) {
                                messageElement.remove();
                                
                                // Check if messages container is empty
                                const container = document.getElementById('messagesContainer');
                                if (container && container.children.length === 0) {
                                    container.innerHTML = `
                                        <div class="empty-state">
                                            <i class="fas fa-comment-slash"></i>
                                            <p>No messages yet</p>
                                            <p style="font-size: 12px; color: #999; margin-top: 10px;">
                                                Start the conversation by sending a message
                                            </p>
                                        </div>
                                    `;
                                }
                            }
                        }, 300);
                    }
                    
                    showToast('Message deleted successfully', 'success');
                } else {
                    console.error("Delete failed:", data);
                    showToast(data.message || 'Failed to delete message', 'error');
                    
                    // Show more detailed error
                    if (data.message === 'Message not found') {
                        console.error("Message ID", messageId, "not found in database");
                        showErrorPopup(`Message not found. It may have been already deleted.`);
                    } else if (data.message.includes('already deleted')) {
                        showErrorPopup(`This message has already been deleted.`);
                    } else {
                        showErrorPopup(data.message || 'Failed to delete message');
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showToast('Network error: ' + error.message, 'error');
                showErrorPopup('Network error: ' + error.message);
            })
            .finally(() => {
                // Restore button
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
                
                // Hide loading
                if (loadingOverlay) loadingOverlay.classList.remove('active');
                
                // Clear current variables
                currentDeleteMessageId = null;
                currentDeleteMessageType = null;
                currentDeleteMessageElement = null;
            });
        }

        //  MESSAGE FORM HANDLING 

        document.addEventListener('DOMContentLoaded', function() {
            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (isSubmitting) return false;
                    
                    if (!validateFileUpload()) {
                        return false;
                    }
                    
                    const formData = new FormData(this);
                    formData.append('send_message', '1');
                    
                    const sendButton = document.getElementById('sendButton');
                    const originalHtml = sendButton.innerHTML;
                    sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    sendButton.disabled = true;
                    
                    const messageInput = document.getElementById('messageInput');
                    const messageText = messageInput.value;
                    messageInput.value = '';
                    
                    showLoading();
                    isSubmitting = true;
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            removeFile();
                            
                            if (data.message) {
                                const container = document.getElementById('messagesContainer');
                                
                                const emptyState = container.querySelector('.empty-state');
                                if (emptyState) {
                                    emptyState.remove();
                                }
                                
                                const messageHtml = createMessageHtml(data.message);
                                container.insertAdjacentHTML('beforeend', messageHtml);
                                
                                if (data.message.id > lastMessageId) {
                                    lastMessageId = data.message.id;
                                }
                                
                                container.scrollTop = container.scrollHeight;
                            } else {
                                setTimeout(checkNewMessages, 500);
                            }
                            
                            if (messageInput) {
                                messageInput.style.height = 'auto';
                            }
                        } else {
                            messageInput.value = messageText;
                            showToast(data.error || 'Failed to send message', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        messageInput.value = messageText;
                        showToast('Failed to send message. Please try again.', 'error');
                    })
                    .finally(() => {
                        sendButton.innerHTML = originalHtml;
                        sendButton.disabled = false;
                        hideLoading();
                        isSubmitting = false;
                    });
                });
            }
        });

        // GROUP FUNCTIONS 

        function leaveGroup(groupId) {
            if (confirm('Are you sure you want to leave this group?')) {
                window.location.href = `chat.php?leave_group=${groupId}`;
            }
        }

        function toggleMemberMenu(button, memberId, memberName, groupId) {
            event.stopPropagation();
            
            closeDropdown();
            
            currentSelectedMemberId = memberId;
            currentSelectedMemberName = memberName;
            currentGroupId = groupId;
            
            const rect = button.getBoundingClientRect();
            const dropdown = document.getElementById('memberDropdown');
            
            document.getElementById('dropdownMemberName').textContent = memberName;
            
            dropdown.style.display = 'block';
            dropdown.style.top = (rect.bottom + window.scrollY + 5) + 'px';
            dropdown.style.left = (rect.left + window.scrollX - 150) + 'px';
            
            const dropdownRect = dropdown.getBoundingClientRect();
            if (dropdownRect.right > window.innerWidth) {
                dropdown.style.left = (window.innerWidth - dropdownRect.width - 10) + 'px';
            }
            if (dropdownRect.bottom > window.innerHeight) {
                dropdown.style.top = (rect.top + window.scrollY - dropdownRect.height - 5) + 'px';
            }
            
            const kickOption = document.getElementById('kickUserOption');
            if (kickOption) {
                if (currentUserRole !== 'admin') {
                    kickOption.style.display = 'none';
                } else {
                    const memberItem = button.closest('.member-item');
                    const userRole = memberItem.getAttribute('data-user-role');
                    if (userRole === 'admin') {
                        kickOption.style.display = 'none';
                    } else {
                        kickOption.style.display = 'flex';
                    }
                }
            }
        }

        function closeDropdown() {
            document.getElementById('memberDropdown').style.display = 'none';
        }

        function sendMessageToUser() {
            if (currentSelectedMemberId) {
                window.location.href = `chat.php?user_id=${currentSelectedMemberId}`;
            }
            closeDropdown();
        }

        function showKickConfirmation() {
            closeDropdown();
            document.getElementById('kickUserName').textContent = currentSelectedMemberName;
            document.getElementById('kickConfirmModal').classList.add('active');
        }

        function hideKickModal() {
            document.getElementById('kickConfirmModal').classList.remove('active');
        }

        function executeKick() {
            hideKickModal();
            
            const confirmBtn = document.getElementById('confirmKickBtn');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmBtn.disabled = true;
            
            showLoading();
            
            fetch('kick_group_member.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    group_id: currentGroupId,
                    user_id: currentSelectedMemberId,
                    username: currentSelectedMemberName
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSuccessPopup(`${currentSelectedMemberName} has been removed from the group`);
                    
                    const memberItem = document.querySelector(`.member-item[data-user-id="${currentSelectedMemberId}"]`);
                    if (memberItem) {
                        memberItem.remove();
                    }
                    
                    updateMemberCount();
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showErrorPopup(data.message || 'Failed to kick member. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorPopup('An error occurred: ' + error.message);
            })
            .finally(() => {
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
                hideLoading();
            });
        }

        function updateMemberCount() {
            const memberCountElements = document.querySelectorAll('.stat-item:first-child .stat-value, .group-badge');
            memberCountElements.forEach(element => {
                if (element.classList.contains('stat-value')) {
                    const currentCount = parseInt(element.textContent);
                    if (!isNaN(currentCount)) {
                        element.textContent = currentCount - 1;
                    }
                } else if (element.classList.contains('group-badge')) {
                    const text = element.textContent;
                    const match = text.match(/(\d+)/);
                    if (match) {
                        const currentCount = parseInt(match[0]);
                        element.textContent = text.replace(match[0], currentCount - 1);
                    }
                }
            });
        }

        // INVITE SEARCH FUNCTIONS 

        function searchUsersForInvite() {
            const searchInput = document.getElementById('inviteSearch');
            const searchTerm = searchInput.value;
            const groupId = <?php echo $selected_id ?? 0; ?>;
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            if (searchTerm.length < 2) {
                filterInviteUsers();
                return;
            }
            
            const usersList = document.getElementById('inviteUsersList');
            usersList.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="loading-spinner"></div><p style="margin-top: 10px;">Searching...</p></div>';
            
            searchTimeout = setTimeout(() => {
                fetch(`chat.php?ajax=search_users_for_invite&group_id=${groupId}&term=${encodeURIComponent(searchTerm)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.users.length > 0) {
                            let html = '';
                            data.users.forEach(user => {
                                const profilePhotoHtml = user.profile_photo 
                                    ? `<img src="uploads/profile_photos/${user.profile_photo}" alt="${escapeHtml(user.username)}" style="width: 100%; height: 100%; object-fit: cover;">`
                                    : getInitials(user.username);
                                
                                html += `
                                    <div class="modal-user-item" data-user-id="${user.id}" data-user-name="${user.username.toLowerCase()}" data-user-email="${user.email.toLowerCase()}">
                                        <div class="user-avatar-small" onclick="viewUserProfile(${user.id})">${profilePhotoHtml}</div>
                                        <div class="user-info-small" onclick="viewUserProfile(${user.id})">
                                            <div class="user-name">${escapeHtml(user.username)}</div>
                                            <div class="user-last-message">${escapeHtml(user.email)}</div>
                                        </div>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="group_id" value="${groupId}">
                                            <input type="hidden" name="user_id" value="${user.id}">
                                            <button type="submit" name="invite_to_group" class="btn-invite">
                                                <i class="fas fa-user-plus"></i> Invite
                                            </button>
                                        </form>
                                    </div>
                                `;
                            });
                            usersList.innerHTML = html;
                        } else {
                            usersList.innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <p>No users found</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error searching users:', error);
                        usersList.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                                <p>Error searching users</p>
                            </div>
                        `;
                    });
            }, 300);
        }

        function filterInviteUsers() {
            const search = document.getElementById('inviteSearch').value.toLowerCase();
            const items = document.querySelectorAll('#inviteUsersList .modal-user-item');
            
            items.forEach(item => {
                const name = item.getAttribute('data-user-name');
                const email = item.getAttribute('data-user-email');
                
                if (name.includes(search) || email.includes(search)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // MODAL FUNCTIONS 

        function showCreateGroupModal() {
            document.getElementById('createGroupModal').classList.add('active');
            document.getElementById('group_name').value = '';
            document.getElementById('group_description').value = '';
            updateCharCount();
        }
        
        function hideCreateGroupModal() {
            document.getElementById('createGroupModal').classList.remove('active');
        }
        
        function showGroupDetails(groupId) {
            document.getElementById('groupDetailsModal').classList.add('active');
        }
        
        function hideGroupDetailsModal() {
            document.getElementById('groupDetailsModal').classList.remove('active');
        }
        
        function showInviteModal() {
            document.getElementById('inviteModal').classList.add('active');
        }
        
        function hideInviteModal() {
            document.getElementById('inviteModal').classList.remove('active');
        }

        // EVENT LISTENERS 

        document.addEventListener('click', function(event) {
            const contextMenu = document.getElementById('contextMenu');
            if (contextMenu.style.display === 'block' && !contextMenu.contains(event.target)) {
                hideContextMenu();
            }
        });

        window.addEventListener('scroll', function() {
            hideContextMenu();
        });

        window.addEventListener('resize', function() {
            hideContextMenu();
        });

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('memberDropdown');
            const isClickInside = dropdown.contains(event.target);
            const isClickOnMenuBtn = event.target.closest('.member-menu-btn');
            
            if (!isClickInside && !isClickOnMenuBtn && dropdown.style.display === 'block') {
                closeDropdown();
            }
        });

        window.addEventListener('click', function(event) {
            const modals = ['kickConfirmModal', 'errorModal', 'successModal', 'createGroupModal', 'groupDetailsModal', 'inviteModal', 'addFriendModal', 'unfriendConfirmModal', 'filePreviewModal', 'deleteMessageConfirmModal', 'deleteGroupConfirmModal'];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && modal.classList.contains('active')) {
                    if (event.target == modal) {
                        modal.classList.remove('active');
                    }
                }
            });
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideKickModal();
                hideErrorModal();
                hideSuccessModal();
                hideCreateGroupModal();
                hideGroupDetailsModal();
                hideInviteModal();
                hideAddFriendModal();
                hideUnfriendModal();
                hideFilePreview();
                hideDeleteMessageModal();
                hideDeleteGroupModal();
                hideContextMenu();
                closeDropdown();
            }
        });

        window.addEventListener('load', function() {
            const container = document.getElementById('messagesContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
            
            initRealTimeMessaging();

            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                
                messageInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        const messageForm = document.getElementById('messageForm');
                        if (messageForm && validateFileUpload() && !isSubmitting) {
                            messageForm.dispatchEvent(new Event('submit', { cancelable: true }));
                        }
                    }
                });
            }

            document.getElementById('usersSearch')?.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const items = document.querySelectorAll('.users-list .user-item');
                
                items.forEach(item => {
                    const name = item.querySelector('.user-name').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });

        window.addEventListener('beforeunload', function() {
            if (messageCheckInterval) {
                clearInterval(messageCheckInterval);
            }
            if (pendingRequestsInterval) {
                clearInterval(pendingRequestsInterval);
            }
        });

        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }, 5000);
            });
        }, 100);
    </script>
</body>
</html>