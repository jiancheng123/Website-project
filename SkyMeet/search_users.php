<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

$results = [];

if (!empty($search_term)) {
    // Search users by username or email
    $search_sql = "SELECT id, username, email, profile_photo FROM users 
                   WHERE id != ? AND (username LIKE ? OR email LIKE ?)
                   ORDER BY username
                   LIMIT 50";
    $search_stmt = $conn->prepare($search_sql);
    $search_term_like = "%{$search_term}%";
    $search_stmt->bind_param("iss", $user_id, $search_term_like, $search_term_like);
    $search_stmt->execute();
    $search_result = $search_stmt->get_result();
    
    while ($user = $search_result->fetch_assoc()) {
        // Check friendship status
        $friend_status_sql = "SELECT status FROM friends 
                              WHERE (user_id = ? AND friend_id = ?) 
                              OR (user_id = ? AND friend_id = ?)";
        $friend_status_stmt = $conn->prepare($friend_status_sql);
        $friend_status_stmt->bind_param("iiii", $user_id, $user['id'], $user['id'], $user_id);
        $friend_status_stmt->execute();
        $friend_status_result = $friend_status_stmt->get_result();
        
        $friend_status = 'not_friend';
        $is_pending_from_me = false;
        
        if ($friend_status_result->num_rows > 0) {
            $status_data = $friend_status_result->fetch_assoc();
            $friend_status = $status_data['status'];
            
            // Check who sent the request
            $check_sender_sql = "SELECT user_id FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
            $check_sender_stmt = $conn->prepare($check_sender_sql);
            $check_sender_stmt->bind_param("ii", $user_id, $user['id']);
            $check_sender_stmt->execute();
            $sender_result = $check_sender_stmt->get_result();
            $is_pending_from_me = ($sender_result->num_rows > 0);
            $check_sender_stmt->close();
        }
        
        $friend_status_stmt->close();
        
        $results[] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'profile_photo' => $user['profile_photo'],
            'friend_status' => $friend_status,
            'is_pending_from_me' => $is_pending_from_me
        ];
    }
    
    $search_stmt->close();
}

echo json_encode(['users' => $results]);
?>