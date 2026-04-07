<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if (!isset($_GET['group_id']) || !isset($_GET['term'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = intval($_GET['group_id']);
$search_term = '%' . $_GET['term'] . '%';

// Check if user is admin of the group
$check_admin_sql = "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?";
$check_admin_stmt = $conn->prepare($check_admin_sql);
$check_admin_stmt->bind_param("ii", $group_id, $user_id);
$check_admin_stmt->execute();
$check_admin_result = $check_admin_stmt->get_result();
$is_admin = ($check_admin_result->num_rows > 0 && $check_admin_result->fetch_assoc()['role'] == 'admin');
$check_admin_stmt->close();

if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Search for users not in the group
$search_sql = "SELECT id, username, email, profile_photo 
               FROM users 
               WHERE id != ? AND username != 'admin' AND email NOT LIKE '%admin%'
               AND (username LIKE ? OR email LIKE ?)
               AND id NOT IN (
                   SELECT user_id FROM group_members WHERE group_id = ?
               )
               ORDER BY username 
               LIMIT 20";
$search_stmt = $conn->prepare($search_sql);
$search_stmt->bind_param("issi", $user_id, $search_term, $search_term, $group_id);
$search_stmt->execute();
$search_result = $search_stmt->get_result();

$users = [];
while ($user = $search_result->fetch_assoc()) {
    $users[] = $user;
}
$search_stmt->close();

echo json_encode(['success' => true, 'users' => $users]);
?>