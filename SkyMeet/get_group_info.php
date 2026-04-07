<?php
session_start();
require_once 'connect.php';

$group_id = intval($_GET['group_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

$response = ['success' => false];

if ($group_id > 0 && $user_id > 0) {
    // Get group info
    $sql = "SELECT g.*, 
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
           u.username as creator_name
           FROM groups g
           JOIN users u ON g.created_by = u.id
           WHERE g.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($group = $result->fetch_assoc()) {
        // Get group members
        $members_sql = "SELECT u.id, u.username, gm.role, gm.joined_at
                       FROM group_members gm
                       JOIN users u ON gm.user_id = u.id
                       WHERE gm.group_id = ?
                       ORDER BY gm.role DESC, gm.joined_at";
        $members_stmt = $conn->prepare($members_sql);
        $members_stmt->bind_param("i", $group_id);
        $members_stmt->execute();
        $members_result = $members_stmt->get_result();
        $group['members'] = $members_result->fetch_all(MYSQLI_ASSOC);
        $members_stmt->close();
        
        $response = $group;
        $response['success'] = true;
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($response);
?>