<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$other_user_id = intval($_POST['user_id'] ?? 0);

if ($other_user_id <= 0 || $other_user_id == $user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

// Check if user exists
$user_sql = "SELECT id, username FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $other_user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Check if chats table exists
$check_chats = $conn->query("SHOW TABLES LIKE 'chats'");
if (!$check_chats || $check_chats->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Chats feature not setup']);
    exit();
}

// Check if conversation already exists
$check_sql = "SELECT c.id 
             FROM chats c
             INNER JOIN chat_members cm1 ON c.id = cm1.chat_id AND cm1.user_id = ?
             INNER JOIN chat_members cm2 ON c.id = cm2.chat_id AND cm2.user_id = ?
             WHERE c.type = 'private'";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $other_user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $existing_conversation = $check_result->fetch_assoc();
    echo json_encode([
        'success' => true, 
        'message' => 'Chat already exists',
        'chat_id' => $existing_conversation['id']
    ]);
    exit();
}

// Get current user details
$current_user_sql = "SELECT username FROM users WHERE id = ?";
$current_user_stmt = $conn->prepare($current_user_sql);
$current_user_stmt->bind_param("i", $user_id);
$current_user_stmt->execute();
$current_user_result = $current_user_stmt->get_result();
$current_user = $current_user_result->fetch_assoc();

// Get other user details
$other_user = $user_result->fetch_assoc();

// Start transaction
$conn->begin_transaction();

try {
    // Create private chat
    $chat_name = $current_user['username'] . " & " . $other_user['username'];
    $chat_description = "Private chat between " . $current_user['username'] . " and " . $other_user['username'];
    
    $chat_sql = "INSERT INTO chats (name, description, type, created_by, created_at) 
                VALUES (?, ?, 'private', ?, NOW())";
    $chat_stmt = $conn->prepare($chat_sql);
    $chat_stmt->bind_param("ssi", $chat_name, $chat_description, $user_id);
    
    if (!$chat_stmt->execute()) {
        throw new Exception("Failed to create chat");
    }
    
    $chat_id = $conn->insert_id;
    
    // Check if chat_members table exists
    $check_chat_members = $conn->query("SHOW TABLES LIKE 'chat_members'");
    if ($check_chat_members && $check_chat_members->num_rows > 0) {
        // Add both users to the chat
        $chat_member_sql = "INSERT INTO chat_members (chat_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())";
        $chat_member_stmt = $conn->prepare($chat_member_sql);
        
        // Add current user
        $chat_member_stmt->bind_param("ii", $chat_id, $user_id);
        $chat_member_stmt->execute();
        
        // Add other user
        $chat_member_stmt->bind_param("ii", $chat_id, $other_user_id);
        $chat_member_stmt->execute();
        $chat_member_stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Private chat created successfully',
        'chat_id' => $chat_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>