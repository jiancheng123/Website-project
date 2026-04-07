<?php
session_start();
require_once 'connect.php';

$user_id = $_SESSION['user_id'] ?? 0;
$type = $_GET['type'] ?? 'user';
$id = intval($_GET['id'] ?? 0);
$last_id = intval($_GET['last_id'] ?? 0);

$response = ['success' => false, 'messages' => [], 'unread_counts' => []];

if ($user_id > 0 && $id > 0) {
    if ($type === 'user') {
        // Get new private messages
        $sql = "SELECT m.*, u.username as sender_name
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR 
                      (m.sender_id = ? AND m.receiver_id = ?))
                AND m.id > ?
                ORDER BY m.created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $user_id, $id, $id, $user_id, $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($message = $result->fetch_assoc()) {
            $response['messages'][] = $message;
        }
        $stmt->close();
        
        // Mark messages as read
        $mark_sql = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND id > ?";
        $mark_stmt = $conn->prepare($mark_sql);
        $mark_stmt->bind_param("iii", $user_id, $id, $last_id);
        $mark_stmt->execute();
        $mark_stmt->close();
        
    } else {
        // Get new group messages
        $sql = "SELECT gm.*, u.username as sender_name
                FROM group_messages gm
                JOIN users u ON gm.sender_id = u.id
                WHERE gm.group_id = ? AND gm.id > ?
                ORDER BY gm.created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($message = $result->fetch_assoc()) {
            $response['messages'][] = $message;
        }
        $stmt->close();
    }
    
    // Get unread counts for all users
    $unread_sql = "SELECT sender_id, COUNT(*) as count 
                   FROM messages 
                   WHERE receiver_id = ? AND is_read = 0 
                   GROUP BY sender_id";
    $unread_stmt = $conn->prepare($unread_sql);
    $unread_stmt->bind_param("i", $user_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    
    while ($row = $unread_result->fetch_assoc()) {
        $response['unread_counts'][$row['sender_id']] = $row['count'];
    }
    $unread_stmt->close();
    
    $response['success'] = true;
}

header('Content-Type: application/json');
echo json_encode($response);
?>