<?php
// get_participants.php

require_once __DIR__ . '/connect.php';

// Require login
require_login();

$user_id = (int) $_SESSION['user_id'];
$meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;

if ($meeting_id === 0) {
    echo json_encode([]);
    exit();
}

// Check if meeting_participants table exists
$check_table = $conn->query("SHOW TABLES LIKE 'meeting_participants'");
if (!$check_table || $check_table->num_rows === 0) {
    echo json_encode([]);
    exit();
}

// Check if user has access to this meeting (either as host or participant)
$check_sql = "SELECT id FROM meetings WHERE id = ? AND host_id = ? 
              UNION 
              SELECT meeting_id FROM meeting_participants WHERE meeting_id = ? AND participant_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("iiii", $meeting_id, $user_id, $meeting_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode([]);
    exit();
}
$check_stmt->close();

// Fetch participants 
$sql = "SELECT mp.*, u.username, u.email, u.profile_photo
        FROM meeting_participants mp
        LEFT JOIN users u ON mp.participant_id = u.id
        WHERE mp.meeting_id = ?
        ORDER BY mp.joined_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();

$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}

$stmt->close();

// Function to record when a user leaves a meeting
function recordUserLeftMeeting($conn, $meeting_id, $user_id) {
    $update_sql = "UPDATE meeting_participants 
                    SET left_at = NOW(), status = 'left' 
                    WHERE meeting_id = ? AND participant_id = ? AND left_at IS NULL";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $meeting_id, $user_id);
    $update_stmt->execute();
    $affected_rows = $update_stmt->affected_rows;
    $update_stmt->close();
    
    return $affected_rows > 0;
}

// Check if this request includes a leave action
if (isset($_GET['action']) && $_GET['action'] === 'leave' && isset($_GET['meeting_id'])) {
    $leave_meeting_id = (int)$_GET['meeting_id'];
    $result = recordUserLeftMeeting($conn, $leave_meeting_id, $user_id);
    
    // Return success response
    echo json_encode(['success' => $result, 'message' => $result ? 'Left meeting recorded' : 'Already left or not found']);
    exit();
}

header('Content-Type: application/json');
echo json_encode($participants);
?>