<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'exists' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if meeting ID is provided
if (!isset($_POST['room_id']) || empty(trim($_POST['room_id']))) {
    echo json_encode(['success' => false, 'exists' => false, 'message' => 'Room ID is required']);
    exit();
}

$room_id = trim($_POST['room_id']);

try {
    // Check if meetings table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'meetings'");
    if ($check_table->num_rows == 0) {
        echo json_encode(['success' => true, 'exists' => false]);
        exit();
    }
    
    // Check if meeting exists with this room_id
    $sql = "SELECT id, title, host_id, is_password_protected FROM meetings WHERE room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $meeting = $result->fetch_assoc();
        echo json_encode([
            'success' => true, 
            'exists' => true,
            'meeting_id' => $meeting['id'],
            'title' => $meeting['title'],
            'host_id' => $meeting['host_id'],
            'is_password_protected' => (bool)$meeting['is_password_protected']
        ]);
    } else {
        echo json_encode(['success' => true, 'exists' => false]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'exists' => false, 'message' => $e->getMessage()]);
}
?>