<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Include profile utilities
require_once 'profile_utils.php';

// Update user's last activity time
$update_activity_sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_activity_sql);
if ($update_stmt) {
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Get current user with profile photo
$user = getCurrentUser($conn, $user_id);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get total users count for sidebar badge (excluding current admin for accuracy)
$users_count_sql = "SELECT COUNT(*) as total FROM users WHERE role != 'Admin'";
$users_count_result = $conn->query($users_count_sql);
$total_users = 0;
if ($users_count_result) {
    $users_data = $users_count_result->fetch_assoc();
    $total_users = $users_data['total'] ?? 0;
}

// GET UNREAD MESSAGE COUNTS 
// Get unread private messages count
$unread_private_sql = "SELECT COUNT(*) as count FROM messages 
                       WHERE receiver_id = ? AND is_read = 0";
$unread_private_stmt = $conn->prepare($unread_private_sql);
$unread_private_count = 0;
if ($unread_private_stmt) {
    $unread_private_stmt->bind_param("i", $user_id);
    $unread_private_stmt->execute();
    $unread_private_result = $unread_private_stmt->get_result();
    $unread_private_data = $unread_private_result->fetch_assoc();
    $unread_private_count = $unread_private_data['count'] ?? 0;
    $unread_private_stmt->close();
}

// Get unread group messages count
$unread_group_sql = "SELECT COUNT(*) as count 
                     FROM group_messages gm
                     JOIN group_members g ON gm.group_id = g.group_id
                     WHERE g.user_id = ? 
                     AND gm.id > COALESCE(g.last_read_message_id, 0)
                     AND gm.sender_id != ?";
$unread_group_stmt = $conn->prepare($unread_group_sql);
$unread_group_count = 0;
if ($unread_group_stmt) {
    $unread_group_stmt->bind_param("ii", $user_id, $user_id);
    $unread_group_stmt->execute();
    $unread_group_result = $unread_group_stmt->get_result();
    $unread_group_data = $unread_group_result->fetch_assoc();
    $unread_group_count = $unread_group_data['count'] ?? 0;
    $unread_group_stmt->close();
}

// Total unread messages
$total_unread_messages = $unread_private_count + $unread_group_count;
// ============ END UNREAD MESSAGE COUNTS ============

// Get upcoming meetings count for sidebar badge
$upcoming_count_sql = "SELECT COUNT(*) as upcoming_count 
                      FROM meetings 
                      WHERE host_id = ? 
                      AND ((meeting_date > CURDATE()) 
                          OR (meeting_date = CURDATE() AND end_time > CURTIME()))";
$upcoming_count_stmt = $conn->prepare($upcoming_count_sql);
$upcoming_meetings_count = 0;
if ($upcoming_count_stmt) {
    $upcoming_count_stmt->bind_param("i", $user_id);
    $upcoming_count_stmt->execute();
    $upcoming_count_result = $upcoming_count_stmt->get_result();
    $upcoming_count_data = $upcoming_count_result->fetch_assoc();
    $upcoming_meetings_count = $upcoming_count_data['upcoming_count'] ?? 0;
    $upcoming_count_stmt->close();
}

// Get pending reports count for sidebar badge
$pending_reports_count = 0;
$reports_table_exists = false;
$check_reports_table = $conn->query("SHOW TABLES LIKE 'problem_reports'");
if ($check_reports_table && $check_reports_table->num_rows > 0) {
    $reports_table_exists = true;
    $reports_sql = "SELECT COUNT(*) as pending_count FROM problem_reports WHERE status = 'pending'";
    $reports_result = $conn->query($reports_sql);
    if ($reports_result) {
        $reports_data = $reports_result->fetch_assoc();
        $pending_reports_count = $reports_data['pending_count'] ?? 0;
    }
}

// Get meeting statistics for sidebar
$meeting_stats_sql = "SELECT 
    COUNT(*) as total_meetings,
    SUM(CASE WHEN meeting_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_meetings
FROM meetings";
$meeting_stats_result = $conn->query($meeting_stats_sql);
$stats = $meeting_stats_result->fetch_assoc();

// If no meetings, set to 0
if (!$stats) {
    $stats = ['total_meetings' => 0, 'upcoming_meetings' => 0];
}

// Handle user actions (delete only - removed role change)
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $target_user_id = intval($_GET['user_id']);
    $action = $_GET['action'];
    
    // Don't allow admin to delete themselves
    if ($target_user_id != $user_id) {
        if ($action == 'delete') {
            // First delete related records to maintain referential integrity
            $conn->begin_transaction();
            try {
                // Delete from meeting_participants
                $delete_participants = $conn->prepare("DELETE FROM meeting_participants WHERE participant_id = ?");
                $delete_participants->bind_param("i", $target_user_id);
                $delete_participants->execute();
                $delete_participants->close();
                
                // Delete from group_members
                $delete_group_members = $conn->prepare("DELETE FROM group_members WHERE user_id = ?");
                $delete_group_members->bind_param("i", $target_user_id);
                $delete_group_members->execute();
                $delete_group_members->close();
                
                // Delete messages sent by user
                $delete_messages_sent = $conn->prepare("DELETE FROM messages WHERE sender_id = ?");
                $delete_messages_sent->bind_param("i", $target_user_id);
                $delete_messages_sent->execute();
                $delete_messages_sent->close();
                
                // Delete messages received by user
                $delete_messages_received = $conn->prepare("DELETE FROM messages WHERE receiver_id = ?");
                $delete_messages_received->bind_param("i", $target_user_id);
                $delete_messages_received->execute();
                $delete_messages_received->close();
                
                // Delete problem reports
                $delete_reports = $conn->prepare("DELETE FROM problem_reports WHERE user_id = ?");
                $delete_reports->bind_param("i", $target_user_id);
                $delete_reports->execute();
                $delete_reports->close();
                
                // Finally delete the user
                $delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_user->bind_param("i", $target_user_id);
                $delete_user->execute();
                $delete_user->close();
                
                $conn->commit();
                $success_message = "User deleted successfully";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error deleting user: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "You cannot delete your own account";
    }
}

// Get sort parameter
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Get all users with additional stats and status
$users_sql = "SELECT 
    u.*,
    (SELECT COUNT(*) FROM meetings WHERE host_id = u.id) as meeting_count,
    (SELECT COUNT(*) FROM meeting_participants WHERE participant_id = u.id) as participation_count,
    CASE 
        WHEN u.last_activity IS NOT NULL AND u.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'Online'
        WHEN u.last_activity IS NOT NULL AND u.last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'Away'
        ELSE 'Offline'
    END as status
FROM users u
ORDER BY 
    CASE 
        WHEN u.role = 'Admin' THEN 1
        ELSE 2
    END,
    " . ($sort_order == 'newest' ? "u.created_at DESC" : "u.created_at ASC");

$users_result = $conn->query($users_sql);
$all_users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $all_users[] = $row;
    }
}

// Debug: Check if we're getting all users
error_log("Total users found: " . count($all_users));

// Get user statistics
$stats_sql = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular_users,
    SUM(CASE WHEN role = 'Admin' THEN 1 ELSE 0 END) as admin_users,
    COUNT(*) as active_users,
    0 as inactive_users,
    SUM(CASE WHEN DATE(last_activity) = CURDATE() THEN 1 ELSE 0 END) as active_today,
    SUM(CASE WHEN last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as online_now,
    SUM(CASE 
        WHEN last_activity IS NOT NULL AND last_activity <= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 
        ELSE 0 
    END) as offline_users
FROM users";

$stats_result = $conn->query($stats_sql);
$user_stats = $stats_result->fetch_assoc();

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

// Function to get user initials
function getInitials($username) {
    if (strlen($username) >= 2) {
        return strtoupper(substr($username, 0, 2));
    }
    return strtoupper($username . $username);
}

// Function to format date
function formatDate($date) {
    if (!$date) return 'Never';
    return date('M d, Y H:i', strtotime($date));
}

// Function to get status badge class
function getStatusClass($status) {
    switch ($status) {
        case 'Online':
            return 'status-online';
        case 'Away':
            return 'status-away';
        default:
            return 'status-offline';
    }
}

// Function to format intake display
function formatIntake($semester, $year) {
    if (!empty($semester) && !empty($year)) {
        return $semester . ' ' . $year;
    } elseif (!empty($semester)) {
        return $semester;
    } elseif (!empty($year)) {
        return $year;
    } else {
        return 'N/A';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - SkyMeet Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        .dashboard-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
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
        
        .logo span {
            font-size: 12px;
            color: #f59e0b;
            background: #fef3c7;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 5px;
        }

        .user-profile {
            padding: 30px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
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
            position: relative;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
        }

        .user-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .user-role {
            display: inline-block;
            background: #fef3c7;
            color: #f59e0b;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
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
            position: relative;
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

        /* Notification badge - Purple color */
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
        
        .nav-badge.warning {
            background: #f59e0b;
        }
        
        .nav-badge.success {
            background: #10b981;
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

        /* Main Content */
        .main-content {
            padding: 30px;
            min-height: 100vh;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0ff;
        }

        .page-header h1 {
            font-size: 32px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h1 i {
            color: #667eea;
            font-size: 32px;
        }

        .page-header p {
            color: #666;
            font-size: 16px;
            margin-top: 8px;
        }

        /* User Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
            border-left: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card.total {
            border-left-color: #667eea;
        }

        .stat-card.active {
            border-left-color: #10b981;
        }

        .stat-card.admins {
            border-left-color: #f59e0b;
        }

        .stat-card.online {
            border-left-color: #3b82f6;
        }

        .stat-card.offline {
            border-left-color: #6b7280;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(102, 126, 234, 0.2));
            color: #667eea;
        }

        .stat-card.active .stat-icon {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: #10b981;
        }

        .stat-card.admins .stat-icon {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: #f59e0b;
        }

        .stat-card.online .stat-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.2));
            color: #3b82f6;
        }

        .stat-card.offline .stat-icon {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.1), rgba(107, 114, 128, 0.2));
            color: #6b7280;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card.total .stat-info h3 {
            color: #667eea;
        }

        .stat-card.active .stat-info h3 {
            color: #10b981;
        }

        .stat-card.admins .stat-info h3 {
            color: #f59e0b;
        }

        .stat-card.online .stat-info h3 {
            color: #3b82f6;
        }

        .stat-card.offline .stat-info h3 {
            color: #6b7280;
        }

        .stat-info p {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            outline: none;
        }

        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 16px;
        }

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            background: white;
            cursor: pointer;
            min-width: 150px;
        }

        .filter-select:focus {
            border-color: #667eea;
        }

        .reset-filter {
            padding: 12px 20px;
            background: #f0f0f0;
            border: none;
            border-radius: 8px;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .reset-filter:hover {
            background: #e0e0e0;
            color: #333;
        }

        /* Sort Select */
        .sort-select {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            background: white;
            cursor: pointer;
            min-width: 150px;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
            appearance: none;
        }

        .sort-select:focus {
            border-color: #667eea;
        }

        /* Users Table Container */
        .users-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h2 {
            font-size: 24px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            color: #667eea;
        }

        .btn-export {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-export:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .table-wrapper {
            max-height: 500px;
            overflow-y: auto;
            border-radius: 12px;
            border: 1px solid #f0f0f0;
            scrollbar-width: thin;
            scrollbar-color: #667eea #f0f0f0;
        }

        .table-wrapper::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 10px;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #5a6fd8;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1400px;
        }

        .users-table th {
            background: #f8f9ff;
            color: #667eea;
            font-weight: 600;
            padding: 18px 15px;
            text-align: left;
            border-bottom: 2px solid #e0e0ff;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .users-table td {
            padding: 20px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .users-table tbody tr:hover {
            background: #f8f9ff;
        }

        /* User Info Cell */
        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 15px;
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
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .user-details p {
            color: #666;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .user-details .user-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 5px;
            font-size: 12px;
            color: #888;
        }

        .user-details .user-meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .user-details .user-meta i {
            font-size: 10px;
            color: #667eea;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }

        .status-badge i {
            font-size: 8px;
        }

        .status-online {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: #10b981;
        }

        .status-away {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: #f59e0b;
        }

        .status-offline {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.1), rgba(107, 114, 128, 0.2));
            color: #6b7280;
        }

        /* Role Badge */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            min-width: 80px;
        }

        .role-admin {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: #f59e0b;
        }

        .role-user {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.2));
            color: #3b82f6;
        }

        /* N/A Badge for empty values */
        .na-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #f0f0f0;
            color: #999;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 12px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        .disabled-btn {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
            background: #6c757d;
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #888;
            margin-bottom: 30px;
            font-size: 16px;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .success-message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: #10b981;
            border-left: 4px solid #10b981;
        }

        .error-message {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Delete Confirmation Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
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
            max-width: 500px;
            padding: 40px;
            position: relative;
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 24px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header h3 i {
            color: #ef4444;
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
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #667eea;
        }

        .modal-body {
            margin-bottom: 30px;
        }

        .modal-body p {
            color: #666;
            font-size: 16px;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn-cancel, .btn-confirm {
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

        .btn-confirm {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-confirm:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        /* Responsive - Only desktop adjustments */
        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
                height: auto;
                width: 100%;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-select, .sort-select {
                width: 100%;
            }

            .reset-filter {
                width: 100%;
                justify-content: center;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .btn-export {
                width: 100%;
                justify-content: center;
            }
            
            .user-details .user-meta {
                flex-direction: column;
                gap: 5px;
            }
            
            .users-table {
                min-width: 1000px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> SkyMeet <span>Admin</span></h1>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php 
                    $profile_photo = getProfilePhoto($user);
                    if ($profile_photo): ?>
                        <img src="<?php echo $profile_photo; ?>" alt="<?php echo htmlspecialchars($username); ?>">
                    <?php else: ?>
                        <?php echo getInitials($username); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($username); ?></h3>
                    <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    <span class="user-role">Administrator</span>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="admin_meetings.php" class="nav-item">
                    <i class="fas fa-video"></i> User's Meetings
                    <?php if ($stats['total_meetings'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['total_meetings']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_schedule.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i> User's Schedule
                    <?php if ($upcoming_meetings_count > 0): ?>
                        <span class="nav-badge"><?php echo $upcoming_meetings_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_reports.php" class="nav-item">
                    <i class="fas fa-flag"></i> User's Reports
                    <?php if ($pending_reports_count > 0): ?>
                        <span class="nav-badge warning"><?php echo $pending_reports_count; ?></span>
                    <?php else: ?>
                        <span class="nav-badge">0</span>
                    <?php endif; ?>
                </a>
                <a href="admin_users.php" class="nav-item active">
                    <i class="fas fa-users-cog"></i> Manage Users
                    <?php if ($total_users > 0): ?>
                        <span class="nav-badge"><?php echo $total_users; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>
                        <i class="fas fa-users-cog"></i> 
                        Manage Users
                    </h1>
                    <p>View and manage all users in the system. Total: <?php echo $user_stats['total_users']; ?> users</p>
                </div>
            </div>

            <!-- User Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $user_stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-card admins">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $user_stats['admin_users']; ?></h3>
                        <p>Administrators</p>
                    </div>
                </div>
                
                <div class="stat-card online">
                    <div class="stat-icon">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $user_stats['online_now']; ?></h3>
                        <p>Online Now</p>
                    </div>
                </div>

                <div class="stat-card offline">
                    <div class="stat-icon">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $user_stats['total_users'] - $user_stats['online_now']; ?></h3>
                        <p>Offline Users</p>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search users by name, email, ID, program, or intake...">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>
                
                <select class="filter-select" id="roleFilter">
                    <option value="all">All Roles</option>
                    <option value="Admin">Administrators</option>
                    <option value="user">Regular Users</option>
                </select>

                <select class="sort-select" id="sortOrder" onchange="changeSort(this.value)">
                    <option value="newest" <?php echo $sort_order == 'newest' ? 'selected' : ''; ?>>Newest Users</option>
                    <option value="oldest" <?php echo $sort_order == 'oldest' ? 'selected' : ''; ?>>Oldest Users</option>
                </select>
                
                <button class="reset-filter" onclick="resetFilters()">
                    <i class="fas fa-redo-alt"></i> Reset
                </button>
            </div>

            <!-- Users Table -->
            <div class="users-container">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-list"></i> User List
                        <span class="nav-badge" style="margin-left: 10px;"><?php echo count($all_users); ?> users</span>
                    </h2>
                    <button class="btn-export" onclick="exportUsers()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>

                <?php if (empty($all_users)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Users Found</h3>
                        <p>There are no users in the system yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <div class="table-container">
                            <table class="users-table" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Student ID</th>
                                        <th>Education Level</th>
                                        <th>Program</th>
                                        <th>Intake</th>
                                        <th>Joined</th>
                                        <th>Last Activity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_users as $index => $u): 
                                        $is_current_user = ($u['id'] == $user_id);
                                        $user_role = strtolower(trim($u['role']));
                                        $display_role = ($user_role == 'admin') ? 'Admin' : 'User';
                                        
                                        // Get student fields with N/A fallback
                                        $education_level = !empty($u['education_level']) ? htmlspecialchars($u['education_level']) : '<span class="na-badge">N/A</span>';
                                        $student_id = !empty($u['student_id']) ? htmlspecialchars($u['student_id']) : '<span class="na-badge">N/A</span>';
                                        $program = !empty($u['program']) ? htmlspecialchars($u['program']) : '<span class="na-badge">N/A</span>';
                                        $semester = $u['semester'] ?? '';
                                        $year = $u['year'] ?? '';
                                        $intake = formatIntake($semester, $year);
                                        if ($intake === 'N/A') {
                                            $intake = '<span class="na-badge">N/A</span>';
                                        } else {
                                            $intake = htmlspecialchars($intake);
                                        }
                                    ?>
                                    <tr data-user-id="<?php echo $u['id']; ?>" data-role="<?php echo $user_role; ?>">
                                        <td>
                                            <strong>#<?php echo $u['id']; ?></strong>
                                        </td>
                                        <td>
                                            <div class="user-info-cell">
                                                <div class="user-avatar-small">
                                                    <?php 
                                                    $profile_photo = getProfilePhoto($u);
                                                    if ($profile_photo): ?>
                                                        <img src="<?php echo $profile_photo; ?>" alt="<?php echo htmlspecialchars($u['username']); ?>">
                                                    <?php else: ?>
                                                        <?php echo getInitials($u['username']); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="user-details">
                                                    <h4><?php echo htmlspecialchars($u['username']); ?></h4>
                                                    <p><?php echo htmlspecialchars($u['email']); ?></p>
                                                    <span class="status-badge <?php echo getStatusClass($u['status']); ?>">
                                                        <i class="fas fa-circle"></i>
                                                        <?php echo $u['status']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge <?php echo $user_role == 'admin' ? 'role-admin' : 'role-user'; ?>">
                                                <i class="fas <?php echo $user_role == 'admin' ? 'fa-crown' : 'fa-user'; ?>"></i>
                                                <?php echo $display_role; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $student_id; ?></td>
                                        <td><?php echo $education_level; ?></td>
                                        <td><?php echo $program; ?></td>
                                        <td><?php echo $intake; ?></td>
                                        <td><?php echo formatDate($u['created_at']); ?></td>
                                        <td><?php echo formatDate($u['last_activity']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if (!$is_current_user): ?>
                                                    <button onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['username'])); ?>')" 
                                                            class="action-btn btn-delete" 
                                                            title="Delete User">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php else: ?>
                                                    <span class="action-btn disabled-btn" title="You cannot delete your own account">
                                                        <i class="fas fa-lock"></i> Current User
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Delete User</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
                <p style="margin-top: 10px; font-size: 14px; color: #999;">This action cannot be undone. All user data, meetings, messages, and activity will be permanently removed.</p>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn-confirm" id="confirmDeleteBtn">Delete User</button>
            </div>
        </div>
    </div>

    <script>
        let userToDelete = null;

        // Delete confirmation
        function confirmDelete(userId, username) {
            userToDelete = userId;
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            userToDelete = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (userToDelete) {
                window.location.href = 'admin_users.php?action=delete&user_id=' + userToDelete;
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeDeleteModal();
            }
        });

        // Sort change function
        function changeSort(sortOrder) {
            window.location.href = 'admin_users.php?sort=' + sortOrder;
        }

        // Filtering functionality
        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            let visibleCount = 0;
            
            for (let row of rows) {
                const userId = row.cells[0].textContent.toLowerCase().replace('#', '');
                const username = row.cells[1].querySelector('.user-details h4').textContent.toLowerCase();
                const email = row.cells[1].querySelector('.user-details p').textContent.toLowerCase();
                const educationLevel = row.cells[4].textContent.toLowerCase();
                const studentId = row.cells[3].textContent.toLowerCase();
                const program = row.cells[5].textContent.toLowerCase();
                const intake = row.cells[6].textContent.toLowerCase();
                const roleCell = row.cells[2].textContent.trim();
                
                let show = true;
                
                // Search filter - check multiple fields
                if (searchInput) {
                    const searchFields = [
                        userId,
                        username, 
                        email, 
                        educationLevel,
                        studentId, 
                        program, 
                        intake
                    ];
                    
                    let found = false;
                    for (let field of searchFields) {
                        if (field.includes(searchInput)) {
                            found = true;
                            break;
                        }
                    }
                    show = found;
                }
                
                // Role filter
                if (roleFilter !== 'all' && show) {
                    if (roleFilter === 'Admin' && !roleCell.includes('Admin')) show = false;
                    if (roleFilter === 'user' && !roleCell.includes('User')) show = false;
                }
                
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            }
            
            // Update the user count in the header
            const userCountSpan = document.querySelector('.section-header .nav-badge');
            if (userCountSpan) {
                userCountSpan.textContent = visibleCount + ' users';
            }
        }

        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('roleFilter').addEventListener('change', filterTable);

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('roleFilter').value = 'all';
            document.getElementById('sortOrder').value = 'newest';
            
            // Reset sort to newest
            window.location.href = 'admin_users.php?sort=newest';
        }

        // Export users as CSV 
        function exportUsers() {
            const rows = [];
            const table = document.getElementById('usersTable');
            const headers = ['User ID', 'Username', 'Email', 'Role', 'Student ID', 'Education Level', 'Program', 'Intake', 'Joined', 'Last Activity'];
            rows.push(headers.join(','));
            
            const dataRows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            let exportCount = 0;
            
            for (let row of dataRows) {
                if (row.style.display !== 'none') {
                    const userId = row.cells[0].textContent.trim().replace('#', '');
                    const username = row.cells[1].querySelector('.user-details h4').textContent;
                    const email = row.cells[1].querySelector('.user-details p').textContent;
                    
                    const role = row.cells[2].textContent.trim();
                    
                    // Get text content, handling N/A badges
                    const studentId = row.cells[3].querySelector('.na-badge') ? 'N/A' : row.cells[3].textContent.trim();
                    const educationLevel = row.cells[4].querySelector('.na-badge') ? 'N/A' : row.cells[4].textContent.trim();
                    const program = row.cells[5].querySelector('.na-badge') ? 'N/A' : row.cells[5].textContent.trim();
                    const intake = row.cells[6].querySelector('.na-badge') ? 'N/A' : row.cells[6].textContent.trim();
                    
                    const joined = row.cells[7].textContent.trim();
                    const lastActivity = row.cells[8].textContent.trim();
                    
                    rows.push(`"${userId}","${username}","${email}","${role}","${studentId}","${educationLevel}","${program}","${intake}","${joined}","${lastActivity}"`);
                    exportCount++;
                }
            }
            
            if (exportCount === 0) {
                alert('No users to export based on current filters.');
                return;
            }
            
            const csvContent = rows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'users_export_' + new Date().toISOString().slice(0,10) + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
            }
            
            if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
                event.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        // Auto-dismiss messages after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.message').forEach(message => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>