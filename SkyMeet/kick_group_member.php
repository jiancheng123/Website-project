<?php
session_start();
require_once 'connect.php';

// Clear any previous output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set header for JSON response
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL); // Keep logging errors, but don't display

// Function to send JSON response and exit
function sendResponse($success, $message, $extra = []) {
    $response = array_merge(['success' => $success, 'message' => $message], $extra);
    echo json_encode($response);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Not authenticated');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(false, 'Invalid JSON input');
}

if (!isset($input['group_id']) || !isset($input['user_id'])) {
    sendResponse(false, 'Missing parameters');
}

$group_id = intval($input['group_id']);
$user_id_to_kick = intval($input['user_id']);
$current_user_id = $_SESSION['user_id'];

// Validate inputs
if ($group_id <= 0 || $user_id_to_kick <= 0) {
    sendResponse(false, 'Invalid group or user ID');
}

// Don't allow kicking yourself
if ($user_id_to_kick == $current_user_id) {
    sendResponse(false, 'You cannot kick yourself');
}

try {
    // Check if current user is admin of the group
    $check_admin_sql = "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?";
    $check_admin_stmt = $conn->prepare($check_admin_sql);
    if (!$check_admin_stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
    }
    
    $check_admin_stmt->bind_param("ii", $group_id, $current_user_id);
    $check_admin_stmt->execute();
    $check_admin_result = $check_admin_stmt->get_result();

    if ($check_admin_result->num_rows === 0) {
        $check_admin_stmt->close();
        sendResponse(false, 'You are not a member of this group');
    }

    $admin_role = $check_admin_result->fetch_assoc()['role'];
    $check_admin_stmt->close();

    if ($admin_role !== 'admin') {
        sendResponse(false, 'You do not have permission to kick members');
    }

    // Check if user to kick exists and get their username
    $check_user_sql = "SELECT gm.role, u.username 
                       FROM group_members gm 
                       JOIN users u ON gm.user_id = u.id 
                       WHERE gm.group_id = ? AND gm.user_id = ?";
    $check_user_stmt = $conn->prepare($check_user_sql);
    if (!$check_user_stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
    }
    
    $check_user_stmt->bind_param("ii", $group_id, $user_id_to_kick);
    $check_user_stmt->execute();
    $check_user_result = $check_user_stmt->get_result();

    if ($check_user_result->num_rows === 0) {
        $check_user_stmt->close();
        sendResponse(false, 'User is not a member of this group');
    }

    $user_data = $check_user_result->fetch_assoc();
    $username = $user_data['username'];

    if ($user_data['role'] === 'admin') {
        $check_user_stmt->close();
        sendResponse(false, 'Cannot kick another admin');
    }
    $check_user_stmt->close();

    // Remove user from group
    $kick_sql = "DELETE FROM group_members WHERE group_id = ? AND user_id = ?";
    $kick_stmt = $conn->prepare($kick_sql);
    if (!$kick_stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
    }
    
    $kick_stmt->bind_param("ii", $group_id, $user_id_to_kick);
    $kick_stmt->execute();

    if ($kick_stmt->affected_rows > 0) {
        // Optionally add a system message to the group
        $system_message = $username . " has been removed from the group.";
        $insert_system_sql = "INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)";
        $insert_system_stmt = $conn->prepare($insert_system_sql);
        if ($insert_system_stmt) {
            $insert_system_stmt->bind_param("iis", $group_id, $current_user_id, $system_message);
            $insert_system_stmt->execute();
            $insert_system_stmt->close();
        }

        sendResponse(true, 'Member kicked successfully', ['username' => $username]);
    } else {
        sendResponse(false, 'Failed to remove member');
    }

    $kick_stmt->close();

} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage());
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>