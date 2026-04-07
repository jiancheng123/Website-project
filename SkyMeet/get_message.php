<?php
// get_messages.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$last_message_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if ($selected_user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No user selected']);
    exit();
}

// Get new messages
$messages_sql = "SELECT m.*, 
                        u.username as sender_username,
                        u2.username as receiver_username
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 JOIN users u2 ON m.receiver_id = u2.id
                 WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
                    OR (m.sender_id = ? AND m.receiver_id = ?))
                 AND m.id > ?
                 ORDER BY m.created_at ASC";
$messages_stmt = $conn->prepare($messages_sql);
$messages_stmt->bind_param("iiiii", $user_id, $selected_user_id, $selected_user_id, $user_id, $last_message_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages = $messages_result->fetch_all(MYSQLI_ASSOC);
$messages_stmt->close();

// Get files for each message
foreach ($messages as &$message) {
    $files_sql = "SELECT * FROM message_files WHERE message_id = ?";
    $files_stmt = $conn->prepare($files_sql);
    $files_stmt->bind_param("i", $message['id']);
    $files_stmt->execute();
    $files_result = $files_stmt->get_result();
    $message['files'] = $files_result->fetch_all(MYSQLI_ASSOC);
    $files_stmt->close();
}

// Mark messages as read
if (!empty($messages)) {
    $mark_read_sql = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
    $mark_read_stmt = $conn->prepare($mark_read_sql);
    $mark_read_stmt->bind_param("ii", $user_id, $selected_user_id);
    $mark_read_stmt->execute();
    $mark_read_stmt->close();
}

// Get unread counts for all conversations
$unread_counts = [];
$unread_sql = "SELECT sender_id, COUNT(*) as unread_count 
               FROM messages 
               WHERE receiver_id = ? AND is_read = 0 
               GROUP BY sender_id";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
while ($row = $unread_result->fetch_assoc()) {
    $unread_counts[$row['sender_id']] = $row['unread_count'];
}
$unread_stmt->close();

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'unread_counts' => $unread_counts
]);
?>