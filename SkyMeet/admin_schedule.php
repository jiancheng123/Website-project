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

// Check if last_activity column exists
$check_column_sql = "SHOW COLUMNS FROM users LIKE 'last_activity'";
$check_column = $conn->query($check_column_sql);
if ($check_column && $check_column->num_rows == 0) {
    $add_column_sql = "ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL";
    $conn->query($add_column_sql);
}

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

// Get total users count for sidebar badge
$users_count_sql = "SELECT COUNT(*) as total_users FROM users WHERE role != 'Admin'";
$users_count_result = $conn->query($users_count_sql);
$user_stats = $users_count_result->fetch_assoc();
$total_users = $user_stats['total_users'] ?? 0;

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
// END UNREAD MESSAGE COUNTS 

// Check if problem_reports table exists and get report statistics
$reports_table_exists = false;
$check_reports_table = $conn->query("SHOW TABLES LIKE 'problem_reports'");
if ($check_reports_table && $check_reports_table->num_rows > 0) {
    $reports_table_exists = true;
    
    // Get pending reports count for badge
    $pending_reports_sql = "SELECT COUNT(*) as count FROM problem_reports WHERE status = 'pending'";
    $pending_reports_result = $conn->query($pending_reports_sql);
    $pending_reports_data = $pending_reports_result->fetch_assoc();
    $pending_reports_count = $pending_reports_data['count'] ?? 0;
} else {
    $pending_reports_count = 0;
}

// Function to get meeting status
function getMeetingStatus($meeting_date, $start_time, $end_time) {
    $now = time();
    $meeting_start = strtotime($meeting_date . ' ' . $start_time);
    $meeting_end = strtotime($meeting_date . ' ' . $end_time);
    
    if ($meeting_start === false || $meeting_end === false) {
        return 'unknown';
    }
    
    if ($now < $meeting_start) return 'upcoming';
    if ($now >= $meeting_start && $now <= $meeting_end) return 'ongoing';
    return 'completed';
}

// Function to format time
function formatTime($time) {
    if (empty($time)) return '';
    return date('g:i A', strtotime($time));
}

// Function to format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($current_month < 1) {
    $current_month = 12;
    $current_year--;
} elseif ($current_month > 12) {
    $current_month = 1;
    $current_year++;
}

// Calculate previous and next months
$prev_month = $current_month == 1 ? 12 : $current_month - 1;
$prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
$next_month = $current_month == 12 ? 1 : $current_month + 1;
$next_year = $current_month == 12 ? $current_year + 1 : $current_year;

// Get number of days in month
$days_in_month = date('t', strtotime("$current_year-$current_month-01"));
$first_day_of_month = date('N', strtotime("$current_year-$current_month-01"));

// Check if meetings table exists
$meetings_table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'meetings'");
if ($check_table && $check_table->num_rows > 0) {
    $meetings_table_exists = true;
}

// Get ALL meetings for the current month (from ALL users)
$meetings = [];
$meetings_by_user = [];

if ($meetings_table_exists) {
    // Modified query to get meetings from ALL users, not just the admin
    $meetings_sql = "SELECT m.*, 
                            u.username as host_username,
                            u.email as host_email,
                            GROUP_CONCAT(DISTINCT p.email SEPARATOR ', ') as participants_emails,
                            COUNT(DISTINCT p.id) as participant_count
                     FROM meetings m
                     LEFT JOIN users u ON m.host_id = u.id
                     LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
                     LEFT JOIN users p ON mp.participant_id = p.id
                     WHERE MONTH(m.meeting_date) = ? 
                     AND YEAR(m.meeting_date) = ?
                     GROUP BY m.id
                     ORDER BY m.meeting_date, m.start_time";
    $meetings_stmt = $conn->prepare($meetings_sql);
    $meetings_stmt->bind_param("ii", $current_month, $current_year);
    $meetings_stmt->execute();
    $meetings_result = $meetings_stmt->get_result();

    // Organize meetings by date and also collect user info
    while ($meeting = $meetings_result->fetch_assoc()) {
        $date = date('j', strtotime($meeting['meeting_date']));
        if (!isset($meetings[$date])) {
            $meetings[$date] = [];
        }
        $meetings[$date][] = $meeting;
        
        // Also organize by user for user-specific views
        $host_id = $meeting['host_id'];
        if (!isset($meetings_by_user[$host_id])) {
            $meetings_by_user[$host_id] = [
                'username' => $meeting['host_username'] ?? 'Unknown User',
                'email' => $meeting['host_email'] ?? '',
                'meetings' => []
            ];
        }
        $meetings_by_user[$host_id]['meetings'][] = $meeting;
    }
    $meetings_stmt->close();
}

// Get today's meetings count
$today_meetings_count = 0;
if ($meetings_table_exists) {
    $today_sql = "SELECT COUNT(*) as today_count FROM meetings WHERE DATE(meeting_date) = CURDATE()";
    $today_result = $conn->query($today_sql);
    if ($today_result) {
        $today_data = $today_result->fetch_assoc();
        $today_meetings_count = $today_data['today_count'] ?? 0;
    }
}

// Get upcoming meetings (next 7 days) from ALL users
$upcoming_meetings = [];
if ($meetings_table_exists) {
    $upcoming_sql = "SELECT m.*, u.username as host_username, u.email as host_email,
                            GROUP_CONCAT(DISTINCT p.email SEPARATOR ', ') as participants_emails,
                            COUNT(DISTINCT p.id) as participant_count
                     FROM meetings m
                     LEFT JOIN users u ON m.host_id = u.id
                     LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
                     LEFT JOIN users p ON mp.participant_id = p.id
                     WHERE (m.meeting_date > CURDATE() 
                          OR (m.meeting_date = CURDATE() AND m.end_time > CURTIME()))
                     AND m.meeting_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                     GROUP BY m.id
                     ORDER BY m.meeting_date, m.start_time 
                     LIMIT 10";
    $upcoming_stmt = $conn->prepare($upcoming_sql);
    $upcoming_stmt->execute();
    $upcoming_result = $upcoming_stmt->get_result();
    $upcoming_meetings = $upcoming_result->fetch_all(MYSQLI_ASSOC);
    $upcoming_stmt->close();
}

// Get total meetings count for sidebar badge
$stats = [
    'total_meetings' => 0
];

if ($meetings_table_exists) {
    $total_meetings_sql = "SELECT COUNT(*) as total FROM meetings";
    $total_result = $conn->query($total_meetings_sql);
    if ($total_result) {
        $total_data = $total_result->fetch_assoc();
        $stats['total_meetings'] = $total_data['total'] ?? 0;
    }
}

// Get team members count (for stats only)
$team_sql = "SELECT COUNT(*) as team_count FROM users WHERE role IN ('user', 'admin') AND id != ?";
$team_stmt = $conn->prepare($team_sql);
$team_stmt->bind_param("i", $user_id);
$team_stmt->execute();
$team_result = $team_stmt->get_result();
if ($team_result) {
    $team_data = $team_result->fetch_assoc();
    $stats['team_members'] = $team_data['team_count'] ?? 1;
}
$team_stmt->close();

// Handle meeting deletion (admin can delete any meeting)
if (isset($_GET['delete'])) {
    $meeting_id = intval($_GET['delete']);
    
    // First delete related records in meeting_participants
    $delete_participants_sql = "DELETE FROM meeting_participants WHERE meeting_id = ?";
    $delete_participants_stmt = $conn->prepare($delete_participants_sql);
    if ($delete_participants_stmt) {
        $delete_participants_stmt->bind_param("i", $meeting_id);
        $delete_participants_stmt->execute();
        $delete_participants_stmt->close();
    }
    
    // Delete meeting_participant_settings
    $delete_settings_sql = "DELETE FROM meeting_participant_settings WHERE room_id IN (SELECT room_id FROM meetings WHERE id = ?)";
    $delete_settings_stmt = $conn->prepare($delete_settings_sql);
    if ($delete_settings_stmt) {
        $delete_settings_stmt->bind_param("i", $meeting_id);
        $delete_settings_stmt->execute();
        $delete_settings_stmt->close();
    }
    
    // Delete meeting_tasks
    $delete_tasks_sql = "DELETE FROM meeting_tasks WHERE room_id IN (SELECT room_id FROM meetings WHERE id = ?)";
    $delete_tasks_stmt = $conn->prepare($delete_tasks_sql);
    if ($delete_tasks_stmt) {
        $delete_tasks_stmt->bind_param("i", $meeting_id);
        $delete_tasks_stmt->execute();
        $delete_tasks_stmt->close();
    }
    
    // Delete host_actions
    $delete_actions_sql = "DELETE FROM host_actions WHERE room_id IN (SELECT room_id FROM meetings WHERE id = ?)";
    $delete_actions_stmt = $conn->prepare($delete_actions_sql);
    if ($delete_actions_stmt) {
        $delete_actions_stmt->bind_param("i", $meeting_id);
        $delete_actions_stmt->execute();
        $delete_actions_stmt->close();
    }
    
    // Delete muted_users
    $delete_muted_sql = "DELETE FROM muted_users WHERE room_id IN (SELECT room_id FROM meetings WHERE id = ?)";
    $delete_muted_stmt = $conn->prepare($delete_muted_sql);
    if ($delete_muted_stmt) {
        $delete_muted_stmt->bind_param("i", $meeting_id);
        $delete_muted_stmt->execute();
        $delete_muted_stmt->close();
    }
    
    // Then delete the meeting
    $delete_sql = "DELETE FROM meetings WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $meeting_id);
    if ($delete_stmt->execute()) {
        header("Location: admin_schedule.php?success=Meeting+deleted+successfully");
        exit();
    }
    $delete_stmt->close();
}

// Get upcoming meetings count for sidebar badge (admin's own meetings)
$upcoming_meetings_count = 0;
if ($meetings_table_exists) {
    $upcoming_count_sql = "SELECT COUNT(*) as upcoming_count 
                          FROM meetings 
                          WHERE host_id = ? 
                          AND ((meeting_date > CURDATE()) 
                              OR (meeting_date = CURDATE() AND end_time > CURTIME()))";
    $upcoming_count_stmt = $conn->prepare($upcoming_count_sql);
    $upcoming_count_stmt->bind_param("i", $user_id);
    $upcoming_count_stmt->execute();
    $upcoming_count_result = $upcoming_count_stmt->get_result();
    $upcoming_count_data = $upcoming_count_result->fetch_assoc();
    $upcoming_meetings_count = $upcoming_count_data['upcoming_count'] ?? 0;
    $upcoming_count_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Schedule - All User Meetings - SkyMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset - Original light theme from admin_dashboard.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9ff;
            color: #333;
        }

        /* Dashboard Container */
        .dashboard-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        /* Sidebar - Light theme */
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0ff;
            flex-wrap: wrap;
            gap: 15px;
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

        .btn-today-meetings {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-today-meetings:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        /* Error and Success Messages */
        .error {
            background: #ffebee;
            color: #d32f2f;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
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

        /* Schedule Layout */
        .schedule-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            margin-top: 30px;
        }

        /* Left Column - Calendar */
        .left-column {
            display: flex;
            flex-direction: column;
        }

        .calendar-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .calendar-header {
            padding: 25px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .calendar-nav-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .calendar-nav-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .calendar-title {
            font-size: 24px;
            font-weight: 600;
            text-align: center;
        }

        .calendar-actions {
            display: flex;
            gap: 10px;
        }

        .btn-today {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-today:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .calendar-grid {
            padding: 25px;
        }

        .weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 15px;
            background: #f8f9ff;
            padding: 15px;
            border-radius: 12px;
        }

        .weekday {
            text-align: center;
            font-weight: 600;
            color: #667eea;
            padding: 10px 0;
            font-size: 16px;
        }

        .days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .day {
            min-height: 140px;
            height: 140px;
            padding: 12px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            transition: all 0.3s;
            position: relative;
            background: white;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .day:hover {
            border-color: #667eea;
            background: #f8f9ff;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .day.empty {
            background: #f8f9ff;
            border-color: #f0f0f0;
            cursor: default;
        }

        .day.empty:hover {
            transform: none;
            border-color: #f0f0f0;
            box-shadow: none;
        }

        .day-number {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            display: inline-block;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .day.today .day-number {
            background: #667eea;
            color: white;
            border-radius: 50%;
        }

        .day.has-meetings .day-number {
            color: #667eea;
            font-weight: 700;
        }

        .meeting-list {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            margin-top: 2px;
            max-height: calc(100% - 40px);
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .meeting-list::-webkit-scrollbar {
            width: 3px;
        }

        .meeting-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .meeting-list::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }

        .meeting-item-small {
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all 0.2s;
            border-left: 3px solid;
            background: #f0f5ff;
            display: flex;
            align-items: center;
            gap: 4px;
            width: 100%;
            height: 22px;
            line-height: 22px;
            margin-bottom: 2px;
            flex-shrink: 0;
        }

        .meeting-item-small.status-upcoming {
            background: #e8f5e9;
            color: #2e7d32;
            border-left-color: #2e7d32;
        }

        .meeting-item-small.status-upcoming:hover {
            background: #2e7d32;
            color: white;
        }

        .meeting-item-small.status-ongoing {
            background: #fff3e0;
            color: #f57c00;
            border-left-color: #f57c00;
        }

        .meeting-item-small.status-ongoing:hover {
            background: #f57c00;
            color: white;
        }

        .meeting-item-small.status-completed {
            background: #f5f5f5;
            color: #9e9e9e;
            border-left-color: #9e9e9e;
        }

        .meeting-item-small.status-completed:hover {
            background: #9e9e9e;
            color: white;
        }

        .meeting-item-small .host-badge {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            font-size: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .meeting-item-small.status-upcoming .host-badge {
            background: #2e7d32;
        }

        .meeting-item-small.status-ongoing .host-badge {
            background: #f57c00;
        }

        .meeting-item-small.status-completed .host-badge {
            background: #9e9e9e;
        }

        .meeting-item-small:hover .host-badge {
            background: white;
            color: inherit;
        }

        .meeting-count {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #667eea;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }

        /* Right Sidebar */
        .right-sidebar {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 30px;
            display: flex;
            flex-direction: column;
        }

        .right-sidebar-header {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .right-sidebar-header h3 {
            font-size: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .right-sidebar-header h3 i {
            color: #10b981;
        }

        .right-nav-badge {
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .upcoming-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .upcoming-list::-webkit-scrollbar {
            width: 6px;
        }

        .upcoming-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .upcoming-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .upcoming-list::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .view-all-container {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
        }

        .view-all-link {
            display: block;
            text-align: center;
            padding: 15px;
            background: #f8f9ff;
            color: #667eea;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid #e0e0ff;
        }

        .view-all-link:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }

        .view-all-link i {
            margin-right: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 40px;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state h4 {
            font-size: 16px;
            margin-bottom: 8px;
            color: #444;
        }

        .empty-state p {
            font-size: 14px;
            margin-bottom: 15px;
        }

        .upcoming-item {
            background: #f8f9ff;
            border: 2px solid #e0e0ff;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .upcoming-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .upcoming-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .upcoming-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .upcoming-title i {
            color: #667eea;
            font-size: 14px;
        }

        .host-name {
            font-size: 12px;
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .upcoming-date {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #667eea;
            font-size: 13px;
            font-weight: 500;
        }

        .upcoming-time {
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .upcoming-details {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }

        .upcoming-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .upcoming-actions button {
            padding: 8px 15px;
            border-radius: 8px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            flex: 1;
            justify-content: center;
        }

        .btn-join {
            display: none;
        }

        .btn-view {
            background: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        /* Status indicators */
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .status-upcoming {
            background-color: #10b981;
        }

        .status-ongoing {
            background-color: #f59e0b;
            animation: pulse 2s infinite;
        }

        .status-completed {
            background-color: #6b7280;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.05);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Meeting Detail Modal */
        .meeting-detail-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(5px);
        }

        .meeting-detail-modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .meeting-detail-content {
            background: white;
            border-radius: 24px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .meeting-detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            color: white;
            border-radius: 24px 24px 0 0;
            position: relative;
        }

        .meeting-detail-header h2 {
            font-size: 24px;
            margin-bottom: 10px;
            padding-right: 30px;
        }

        .meeting-detail-header .close-detail {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .meeting-detail-header .close-detail:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .host-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            margin-top: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
        }

        .status-badge.upcoming {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.ongoing {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-badge.completed {
            background: #f5f5f5;
            color: #6b7280;
        }

        .meeting-detail-body {
            padding: 30px;
        }

        .detail-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-section h3 {
            font-size: 16px;
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-section h3 i {
            font-size: 18px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
        }

        .detail-row i {
            width: 24px;
            color: #667eea;
            font-size: 16px;
        }

        .detail-row .detail-label {
            font-weight: 600;
            color: #555;
            min-width: 80px;
            font-size: 14px;
        }

        .detail-row .detail-value {
            color: #333;
            font-size: 14px;
            flex: 1;
        }

        .detail-description {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 12px;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 10px;
        }

        .detail-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .detail-actions button {
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
        }

        .btn-join-meeting {
            display: none;
        }

        .btn-view-meeting {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .btn-view-meeting:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-delete-meeting {
            background: linear-gradient(135deg, #f87171, #ef4444);
            color: white;
        }

        .btn-delete-meeting:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        /* Delete Modal */
        .delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2002;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .delete-modal.active {
            display: flex;
        }

        .delete-modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            padding: 40px;
            position: relative;
            animation: modalSlide 0.3s ease;
        }

        .delete-modal-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: #f44336;
        }

        .delete-modal-header h3 {
            font-size: 22px;
        }

        .delete-modal-body {
            padding: 20px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .delete-modal-body p {
            color: #666;
            line-height: 1.5;
        }

        .delete-modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn-delete-cancel {
            background: #f0f0f0;
            color: #666;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-delete-cancel:hover {
            background: #e0e0e0;
        }

        .btn-delete-confirm {
            background: #f44336;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-delete-confirm:hover {
            background: #d32f2f;
        }

        /* Pre-join Modal Styles - Hidden for admin */
        .prejoin-modal {
            display: none;
        }

        /* Success Message */
        .success-message {
            position: fixed;
            top: 30px;
            right: 30px;
            background: #10b981;
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.4);
            z-index: 2003;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Notification container */
        #notification-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
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
            
            .schedule-layout {
                grid-template-columns: 1fr;
            }
            
            .right-sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .page-header h1 {
                font-size: 28px;
            }
            
            .calendar-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .calendar-actions {
                width: 100%;
                justify-content: center;
            }
            
            .days-grid .day {
                min-height: 100px;
                height: 100px;
                padding: 8px;
            }
            
            .weekday {
                font-size: 14px;
                padding: 8px 0;
            }
            
            .upcoming-actions {
                flex-direction: column;
            }
            
            .upcoming-actions button {
                width: 100%;
                justify-content: center;
            }
            
            .detail-actions {
                flex-direction: column;
            }
            
            .upcoming-list {
                max-height: 400px;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 24px;
            }
            
            .right-sidebar {
                padding: 20px;
            }
            
            .upcoming-item {
                padding: 12px;
            }
            
            .meeting-detail-header h2 {
                font-size: 20px;
            }
            
            .detail-row {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Pre-join Modal - Hidden for admin -->
    <div class="prejoin-modal" id="prejoin-modal"></div>

    <div class="dashboard-container">
        <!-- Left Sidebar  -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> SkyMeet <span>Admin</span></h1>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php if ($profile_photo = getProfilePhoto($user)): ?>
                        <img src="<?php echo $profile_photo; ?>" alt="<?php echo htmlspecialchars($username); ?>">
                    <?php else: ?>
                        <?php echo getInitials($username); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($username); ?></h3>
                    <p><?php echo htmlspecialchars($user['email'] ?? 'Admin'); ?></p>
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
                <a href="admin_schedule.php" class="nav-item active">
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
                <a href="admin_users.php" class="nav-item">
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
                        <i class="fas fa-calendar-alt"></i> 
                        Meetings Scheduled From User
                    </h1>
                    <p>View all meetings created by users in calendar. Today: <strong><?php echo $today_meetings_count; ?></strong> meeting<?php echo $today_meetings_count != 1 ? 's' : ''; ?></p>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <div><?= htmlspecialchars($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (isset($_GET['success'])): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($_GET['success']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Schedule Layout -->
            <div class="schedule-layout">
                <!-- Left Column - Calendar -->
                <div class="left-column">
                    <!-- Calendar -->
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <div class="calendar-nav">
                                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" 
                                   class="calendar-nav-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <span class="calendar-title">
                                    <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?>
                                </span>
                                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" 
                                   class="calendar-nav-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                            <div class="calendar-actions">
                                <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" 
                                   class="btn-today">
                                    Today
                                </a>
                            </div>
                        </div>

                        <div class="calendar-grid">
                            <!-- Weekday Headers -->
                            <div class="weekdays">
                                <div class="weekday">Monday</div>
                                <div class="weekday">Tuesday</div>
                                <div class="weekday">Wednesday</div>
                                <div class="weekday">Thursday</div>
                                <div class="weekday">Friday</div>
                                <div class="weekday">Saturday</div>
                                <div class="weekday">Sunday</div>
                            </div>

                            <!-- Days Grid -->
                            <div class="days-grid">
                                <?php
                                // Empty cells for days before the first day of month
                                for ($i = 1; $i < $first_day_of_month; $i++) {
                                    echo '<div class="day empty"></div>';
                                }

                                // Days of the month
                                $today = date('j');
                                for ($day = 1; $day <= $days_in_month; $day++) {
                                    $is_today = ($day == $today && $current_month == date('n') && $current_year == date('Y'));
                                    $has_meetings = isset($meetings[$day]) && count($meetings[$day]) > 0;
                                    $meeting_count = $has_meetings ? count($meetings[$day]) : 0;
                                    
                                    $date_str = date('Y-m-d', strtotime("$current_year-$current_month-$day"));
                                    
                                    echo '<div class="day' . ($is_today ? ' today' : '') . ($has_meetings ? ' has-meetings' : '') . '" 
                                          onclick="showDayMeetings(' . $day . ', \'' . $date_str . '\')">';
                                    echo '<div class="day-number">' . $day . '</div>';
                                    
                                    if ($has_meetings) {
                                        echo '<div class="meeting-count">' . $meeting_count . '</div>';
                                        echo '<div class="meeting-list">';
                                        // Sort meetings by time
                                        $day_meetings = $meetings[$day];
                                        usort($day_meetings, function($a, $b) {
                                            return strtotime($a['start_time']) - strtotime($b['start_time']);
                                        });
                                        
                                        foreach ($day_meetings as $meeting) {
                                            $status = getMeetingStatus($meeting['meeting_date'], $meeting['start_time'], $meeting['end_time']);
                                            $host_initial = strtoupper(substr($meeting['host_username'] ?? 'U', 0, 1));
                                            $status_class = "status-" . $status;
                                            echo '<div class="meeting-item-small ' . $status_class . '" 
                                                  onclick="event.stopPropagation(); showMeetingDetails(' . $meeting['id'] . ', \'' . addslashes($meeting['title']) . '\', \'' . addslashes($meeting['description']) . '\', \'' . $meeting['meeting_date'] . '\', \'' . $meeting['start_time'] . '\', \'' . $meeting['end_time'] . '\', \'' . $status . '\', \'' . $meeting['room_id'] . '\', \'' . addslashes($meeting['host_username'] ?? 'Unknown') . '\')">' 
                                                  . '<span class="host-badge" title="' . htmlspecialchars($meeting['host_username'] ?? 'Unknown') . '">' . $host_initial . '</span>'
                                                  . htmlspecialchars(substr($meeting['title'], 0, 10)) . 
                                                  ' ' . date('g:i A', strtotime($meeting['start_time'])) .
                                                  '</div>';
                                        }
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar - Upcoming Meetings from ALL Users -->
                <div class="right-sidebar">
                    <div class="right-sidebar-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Meetings (All Users)</h3>
                        <span class="right-nav-badge"><?php echo count($upcoming_meetings); ?></span>
                    </div>
                    <div class="upcoming-list">
                        <?php if (empty($upcoming_meetings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h4>No Upcoming Meetings</h4>
                                <p>No meetings scheduled in the next 7 days</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_meetings as $meeting): 
                                $status = getMeetingStatus($meeting['meeting_date'], $meeting['start_time'], $meeting['end_time']);
                                $today_date = date('Y-m-d');
                                $meeting_date = $meeting['meeting_date'];
                                
                                // Display time logic
                                if ($meeting_date == $today_date) {
                                    $time_display = "Today, " . formatTime($meeting['start_time']);
                                } elseif ($meeting_date == date('Y-m-d', strtotime('+1 day'))) {
                                    $time_display = "Tomorrow, " . formatTime($meeting['start_time']);
                                } else {
                                    $time_display = date('M j', strtotime($meeting_date)) . ', ' . formatTime($meeting['start_time']);
                                }
                            ?>
                            <div class="upcoming-item" onclick="showMeetingDetails(<?php echo $meeting['id']; ?>, '<?php echo addslashes($meeting['title']); ?>', '<?php echo addslashes($meeting['description']); ?>', '<?php echo $meeting['meeting_date']; ?>', '<?php echo $meeting['start_time']; ?>', '<?php echo $meeting['end_time']; ?>', '<?php echo $status; ?>', '<?php echo $meeting['room_id']; ?>', '<?php echo addslashes($meeting['host_username'] ?? 'Unknown'); ?>')">
                                <div class="upcoming-item-header">
                                    <div>
                                        <div class="upcoming-title">
                                            <span class="status-indicator status-<?php echo $status; ?>"></span>
                                            <i class="fas fa-video"></i>
                                            <?php echo htmlspecialchars(substr($meeting['title'], 0, 20)); ?>
                                            <span class="host-name">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($meeting['host_username'] ?? 'Unknown'); ?>
                                            </span>
                                        </div>
                                        <div class="upcoming-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo $time_display; ?>
                                        </div>
                                    </div>
                                    <div class="upcoming-time">
                                        <?php 
                                        if ($status == 'upcoming') {
                                            echo 'Upcoming';
                                        } elseif ($status == 'ongoing') {
                                            echo 'Ongoing';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="upcoming-details">
                                    <?php echo htmlspecialchars(substr($meeting['description'] ?: 'No description', 0, 60)); ?>...
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="view-all-container">
                            <a href="admin_meetings.php" class="view-all-link">
                                <i class="fas fa-eye"></i> View All Meetings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Meeting Detail Modal -->
    <div class="meeting-detail-modal" id="meetingDetailModal">
        <div class="meeting-detail-content">
            <div class="meeting-detail-header">
                <button class="close-detail" onclick="closeMeetingDetail()">
                    <i class="fas fa-times"></i>
                </button>
                <h2 id="detailTitle">Meeting Title</h2>
                <div id="detailHost" class="host-badge-large">
                    <i class="fas fa-user"></i>
                    <span id="detailHostName">Host Name</span>
                </div>
                <div id="detailStatus" class="status-badge"></div>
            </div>
            <div class="meeting-detail-body">
                <div class="detail-section">
                    <h3><i class="fas fa-info-circle"></i> Meeting Information</h3>
                    <div class="detail-row">
                        <i class="fas fa-calendar"></i>
                        <span class="detail-label">Date:</span>
                        <span class="detail-value" id="detailDate"></span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-clock"></i>
                        <span class="detail-label">Time:</span>
                        <span class="detail-value" id="detailTime"></span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-door-open"></i>
                        <span class="detail-label">Room ID:</span>
                        <span class="detail-value" id="detailRoomId"></span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3><i class="fas fa-align-left"></i> Description</h3>
                    <div class="detail-description" id="detailDescription">
                        No description provided.
                    </div>
                </div>

                <div class="detail-actions">
                    <button class="btn-view-meeting" id="detailViewBtn" onclick="window.location.href='admin_meetings.php'">
                        <i class="fas fa-eye"></i> View All Meetings
                    </button>
                    <button class="btn-delete-meeting" id="detailDeleteBtn" onclick="deleteFromDetail()">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="delete-modal" id="deleteModal">
        <div class="delete-modal-content">
            <div class="delete-modal-header">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
                <h3>Delete Meeting</h3>
            </div>
            <div class="delete-modal-body">
                <p>Are you sure you want to delete this meeting? This action cannot be undone.</p>
                <p style="margin-top: 10px; font-size: 14px; color: #999;">All participant data, chat history, and meeting recordings will be permanently removed.</p>
            </div>
            <div class="delete-modal-actions">
                <button class="btn-delete-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn-delete-confirm" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Notification container -->
    <div id="notification-container"></div>

    <script>
        // Meeting room data
        let currentMeetingRoom = {
            id: '<?php echo isset($_GET['room']) ? htmlspecialchars($_GET['room']) : ''; ?>',
            password: ''
        };

        let currentDetailMeeting = {
            id: null,
            roomId: null,
            status: null,
            title: '',
            description: '',
            date: '',
            startTime: '',
            endTime: '',
            hostName: '',
            password: ''
        };

        // Meeting Detail Modal Functions
        const meetingDetailModal = document.getElementById('meetingDetailModal');

        function showMeetingDetails(id, title, description, date, startTime, endTime, status, roomId, hostName) {
            currentDetailMeeting.id = id;
            currentDetailMeeting.roomId = roomId;
            currentDetailMeeting.status = status;
            currentDetailMeeting.title = title;
            currentDetailMeeting.description = description;
            currentDetailMeeting.date = date;
            currentDetailMeeting.startTime = startTime;
            currentDetailMeeting.endTime = endTime;
            currentDetailMeeting.hostName = hostName;
            
            document.getElementById('detailTitle').textContent = title;
            document.getElementById('detailHostName').textContent = hostName;
            document.getElementById('detailDescription').textContent = description || 'No description provided.';
            
            // Format date
            const formattedDate = new Date(date).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('detailDate').textContent = formattedDate;
            
            // Format time
            const start = formatTime(startTime);
            const end = formatTime(endTime);
            document.getElementById('detailTime').textContent = `${start} - ${end}`;
            
            // Set room ID
            document.getElementById('detailRoomId').textContent = roomId || 'Not available';
            
            // Set status badge
            const statusBadge = document.getElementById('detailStatus');
            statusBadge.className = 'status-badge ' + status;
            statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            
            meetingDetailModal.classList.add('show');
        }

        function closeMeetingDetail() {
            meetingDetailModal.classList.remove('show');
        }

        function deleteFromDetail() {
            closeMeetingDetail();
            confirmDelete(currentDetailMeeting.id);
        }

        // Format time function
        function formatTime(time) {
            const [hours, minutes] = time.split(':');
            const date = new Date();
            date.setHours(hours, minutes);
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        // Delete meeting functions
        let meetingToDelete = null;

        function confirmDelete(meetingId) {
            meetingToDelete = meetingId;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            meetingToDelete = null;
        }

        // Handle delete confirmation
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function() {
                if (meetingToDelete) {
                    window.location.href = 'admin_schedule.php?delete=' + meetingToDelete;
                }
            });
        }

        // Show meetings for a specific day
        function showDayMeetings(day, dateStr) {
            console.log('Day clicked:', day, dateStr);
            // Could be expanded to show all meetings for that day
        }

        // Notification function
        function showNotification(message, type = 'info') {
            // Check if notification container exists, if not create it
            let container = document.getElementById('notification-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'notification-container';
                container.style.position = 'fixed';
                container.style.top = '80px';
                container.style.right = '20px';
                container.style.zIndex = '9999';
                container.style.display = 'flex';
                container.style.flexDirection = 'column';
                container.style.gap = '10px';
                container.style.maxWidth = '350px';
                document.body.appendChild(container);
            }
            
            const notificationId = 'notification-' + Date.now();
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const colors = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#667eea'
            };
            
            const notification = document.createElement('div');
            notification.id = notificationId;
            notification.style.padding = '15px 25px';
            notification.style.background = 'white';
            notification.style.borderLeft = '4px solid ' + colors[type];
            notification.style.borderRadius = '8px';
            notification.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
            notification.style.animation = 'slideInRight 0.3s ease';
            notification.style.display = 'flex';
            notification.style.alignItems = 'center';
            notification.style.gap = '12px';
            notification.style.position = 'relative';
            
            notification.innerHTML = `
                <i class="fas ${icons[type]}" style="color: ${colors[type]}; font-size: 20px;"></i>
                <span style="flex: 1; color: #333; font-size: 14px;">${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #666; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(notification);
            
            // Add animation keyframes if not present
            if (!document.getElementById('notification-keyframes')) {
                const style = document.createElement('style');
                style.id = 'notification-keyframes';
                style.textContent = `
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            setTimeout(() => {
                const notif = document.getElementById(notificationId);
                if (notif) {
                    notif.style.animation = 'slideOutRight 0.3s ease forwards';
                    setTimeout(() => notif.remove(), 300);
                }
            }, 5000);
        }

        // Initialize on page load
        window.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh calendar every 5 minutes
            setInterval(() => {
                if (!document.hidden) {
                    location.reload();
                }
            }, 300000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Escape to close modals
            if (event.key === 'Escape') {
                closeDeleteModal();
                closeMeetingDetail();
            }
        });

        // Auto-hide success message
        const successMessage = document.querySelector('.success');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.remove();
                }, 300);
            }, 3000);
        }

        // Close modals when clicking outside
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('click', function(event) {
                if (event.target === this) {
                    closeDeleteModal();
                }
            });
        }

        // Close meeting detail modal when clicking outside
        meetingDetailModal.addEventListener('click', function(event) {
            if (event.target === this) {
                closeMeetingDetail();
            }
        });

        // Make functions globally available
        window.showMeetingDetails = showMeetingDetails;
        window.closeMeetingDetail = closeMeetingDetail;
        window.deleteFromDetail = deleteFromDetail;
        window.confirmDelete = confirmDelete;
        window.closeDeleteModal = closeDeleteModal;
        window.showDayMeetings = showDayMeetings;
    </script>
</body>
</html>