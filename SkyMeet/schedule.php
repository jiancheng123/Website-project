<?php
session_start();
require_once 'connect.php';

// Include profile utilities
require_once 'profile_utils.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Set timezone 
date_default_timezone_set('Asia/Kuala_Lumpur'); 

// Get current user with profile photo
$user = getCurrentUser($conn, $user_id);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// FIRST, CHECK IF LAST_ACTIVITY COLUMN EXISTS, IF NOT ADD IT
$check_column_sql = "SHOW COLUMNS FROM users LIKE 'last_activity'";
$check_column = $conn->query($check_column_sql);
if ($check_column && $check_column->num_rows == 0) {
    $add_column_sql = "ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL";
    $conn->query($add_column_sql);
}

// Update user's last activity time (for online status)
$update_activity_sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_activity_sql);
if ($update_stmt) {
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
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

// Get pending friend requests count (exclude admin)
$pending_requests_count_sql = "SELECT COUNT(*) as count FROM friends f
                               JOIN users u ON f.user_id = u.id
                               WHERE f.friend_id = ? AND f.status = 'pending' 
                               AND u.username != 'admin' AND u.email NOT LIKE '%admin%'";
$pending_count_stmt = $conn->prepare($pending_requests_count_sql);
$pending_requests_count = 0;
if ($pending_count_stmt) {
    $pending_count_stmt->bind_param("i", $user_id);
    $pending_count_stmt->execute();
    $pending_count_result = $pending_count_stmt->get_result();
    $pending_count_data = $pending_count_result->fetch_assoc();
    $pending_requests_count = $pending_count_data['count'] ?? 0;
    $pending_count_stmt->close();
}

// Total unread messages (including friend requests for badge)
$total_unread_messages = $unread_private_count + $unread_group_count + $pending_requests_count;
// END UNREAD MESSAGE COUNTS 

// Generate unique meeting room ID
function generateMeetingRoomId() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $room_id = '';
    for ($i = 0; $i < 12; $i++) {
        $room_id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $room_id;
}

// Function to get meeting status
function getMeetingStatus($meeting_date, $start_time, $end_time) {
    $now = time();
    $meeting_start = strtotime($meeting_date . ' ' . $start_time);
    $meeting_end = strtotime($meeting_date . ' ' . $end_time);
    
    if ($now < $meeting_start) return 'upcoming';
    if ($now >= $meeting_start && $now <= $meeting_end) return 'ongoing';
    return 'completed';
}

// Function to check if meeting is upcoming (for display)
function isUpcomingMeeting($meeting_date, $end_time) {
    $now = time();
    $meeting_end = strtotime($meeting_date . ' ' . $end_time);
    return $meeting_end > $now;
}

// Function to format time
function formatTime($time) {
    return date('g:i A', strtotime($time));
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

// MEETING CREATION HANDLING 
$errors = [];
$success_message = '';

// Handle meeting creation for scheduled meetings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_meeting'])) {
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $meeting_date = $_POST['meeting_date'] ?? date('Y-m-d');
    $start_time = $_POST['start_time'] ?? date('H:i');
    $end_time   = $_POST['end_time']   ?? date('H:i', strtotime('+1 hour'));
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $is_protected = isset($_POST['is_protected']) ? 1 : 0;

    // Validation
    if (empty($title)) {
        $errors[] = "Meeting title is required";
    }
    
    // Date validation
    $selected_date = strtotime($meeting_date);
    $today = strtotime(date('Y-m-d'));
    
    if ($selected_date < $today) {
        $errors[] = "Meeting date cannot be in the past";
    }
    
    // Time validation - basic order
    if ($start_time >= $end_time) {
        $errors[] = "End time must be after start time";
    }
    
    // Handle meetings that might span across midnight
    $start_datetime = $meeting_date . ' ' . $start_time;
    $end_datetime = $meeting_date . ' ' . $end_time;
    
    // If end time is less than start time, assume it goes to next day
    if (strtotime($end_datetime) < strtotime($start_datetime)) {
        $end_datetime = date('Y-m-d', strtotime($meeting_date . ' +1 day')) . ' ' . $end_time;
    }
    
    $start_ts = strtotime($start_datetime);
    $end_ts = strtotime($end_datetime);
    $duration_hours = ($end_ts - $start_ts) / 3600;

    // Maximum duration 4 hours validation
    if ($duration_hours > 4) {
        $errors[] = "Meeting duration cannot exceed 4 hours. Your meeting duration is " . round($duration_hours, 1) . " hours.";
    }
    
    // Minimum duration validation (optional - 15 minutes minimum)
    if ($duration_hours < 0.25) {
        $errors[] = "Meeting duration must be at least 15 minutes";
    }

    // For today's meetings, check if start time is in the past
    if ($selected_date == $today) {
        $current_time = date('H:i:s');
        $current_datetime = strtotime(date('Y-m-d H:i:s'));
        
        // Compare the full datetime to ensure accurate comparison
        if ($start_ts < $current_datetime) {
            $errors[] = "Start time cannot be in the past for today's meeting. Current time is " . date('g:i A');
        }
    }
    
    // Password validation if protected
    if ($is_protected) {
        if (empty($password)) {
            $errors[] = "Password is required for protected meeting";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }

    if (empty($errors)) {
        $room_id = generateMeetingRoomId();
        
        // Hash password if provided
        $hashed_password = $is_protected ? password_hash($password, PASSWORD_DEFAULT) : null;

        try {
            // Prepare insert statement based on database structure from fypdb.sql
            $sql = "INSERT INTO meetings 
                    (title, description, host_id, meeting_date, start_time, end_time, room_id, password, is_password_protected, username, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisssssis", 
                $title,
                $description,
                $user_id,
                $meeting_date,
                $start_time,
                $end_time,
                $room_id,
                $hashed_password,
                $is_protected,
                $username
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $meeting_id = $conn->insert_id;
            $stmt->close();
            
            $success_message = "Meeting scheduled successfully!";
            
            // Store the original password in session for later use
            if (!isset($_SESSION['temp_room_password'])) {
                $_SESSION['temp_room_password'] = [];
            }
            $_SESSION['temp_room_password'][$room_id] = $password;
            
            // Get the password for display
            $display_password = $is_protected ? $password : '';
            
            // Redirect with all parameters
            header("Location: schedule.php?success=" . urlencode($success_message) . 
                   "&room=" . urlencode($room_id) . 
                   "&id=" . $meeting_id . 
                   "&title=" . urlencode($title) . 
                   "&password=" . urlencode($display_password));
            exit();

        } catch (Exception $e) {
            $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
            error_log("Create meeting error: " . $e->getMessage());
        }
    }
}

// Get meetings for the current month 
$meetings = [];
// Simple query without GROUP_CONCAT to avoid GROUP BY issues
$meetings_sql = "SELECT m.* 
                 FROM meetings m
                 WHERE m.host_id = ? 
                 AND MONTH(m.meeting_date) = ? 
                 AND YEAR(m.meeting_date) = ?
                 ORDER BY m.meeting_date, m.start_time";
$meetings_stmt = $conn->prepare($meetings_sql);
$meetings_stmt->bind_param("iii", $user_id, $current_month, $current_year);
$meetings_stmt->execute();
$meetings_result = $meetings_stmt->get_result();

// Organize meetings by date
while ($meeting = $meetings_result->fetch_assoc()) {
    $date = date('j', strtotime($meeting['meeting_date']));
    if (!isset($meetings[$date])) {
        $meetings[$date] = [];
    }
    $meetings[$date][] = $meeting;
}
$meetings_stmt->close();

// Get upcoming meetings count 
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

// Get upcoming meetings list 
$upcoming_sql = "SELECT m.* 
                 FROM meetings m
                 WHERE m.host_id = ? 
                 AND (m.meeting_date > CURDATE() 
                      OR (m.meeting_date = CURDATE() AND m.end_time > CURTIME()))
                 ORDER BY m.meeting_date, m.start_time 
                 LIMIT 10";
$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bind_param("i", $user_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();
$upcoming_meetings = $upcoming_result->fetch_all(MYSQLI_ASSOC);
$upcoming_stmt->close();

// Get total meetings count only
$stats = [
    'total_meetings' => 0
];

$total_meetings_sql = "SELECT COUNT(*) as total FROM meetings WHERE host_id = ?";
$total_stmt = $conn->prepare($total_meetings_sql);
$total_stmt->bind_param("i", $user_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
if ($total_result) {
    $total_data = $total_result->fetch_assoc();
    $stats['total_meetings'] = $total_data['total'] ?? 0;
}
$total_stmt->close();

// Get team members count
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

// Handle meeting deletion
if (isset($_GET['delete'])) {
    $meeting_id = intval($_GET['delete']);
    
    // First delete related records in meeting_participants if table exists
    $check_participants = $conn->query("SHOW TABLES LIKE 'meeting_participants'");
    if ($check_participants && $check_participants->num_rows > 0) {
        $delete_participants_sql = "DELETE FROM meeting_participants WHERE meeting_id = ?";
        $delete_participants_stmt = $conn->prepare($delete_participants_sql);
        if ($delete_participants_stmt) {
            $delete_participants_stmt->bind_param("i", $meeting_id);
            $delete_participants_stmt->execute();
            $delete_participants_stmt->close();
        }
    }
    
    // Then delete the meeting
    $delete_sql = "DELETE FROM meetings WHERE id = ? AND host_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $meeting_id, $user_id);
    if ($delete_stmt->execute()) {
        header("Location: schedule.php?success=Meeting+deleted+successfully");
        exit();
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Schedule - SkyMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --dark-bg: #0f172a;
            --dark-surface: #1e293b;
            --dark-card: #334155;
            --dark-border: #475569;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --speaking-color: #3b82f6;
            --muted-color: #ef4444;
        }

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

        .schedule-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            min-height: 100vh;
        }

        /* Sidebar - UPDATED TO MATCH CHAT.PHP EXACTLY */
        .sidebar {
            background: white;
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            overflow-y: auto;
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

        .user-profile {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            position: relative;
        }

        .user-profile-content {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            padding: 10px;
            border-radius: 12px;
            transition: background 0.3s;
        }

        .user-profile-content:hover {
            background: #f8f9ff;
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
            flex-shrink: 0;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-info p {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-icon {
            color: #667eea;
            font-size: 14px;
            transition: transform 0.3s;
            flex-shrink: 0;
        }

        .user-profile-content:hover .dropdown-icon {
            transform: translateY(3px);
        }

        .profile-dropdown-menu {
            position: absolute;
            top: calc(100% - 5px);
            left: 25px;
            right: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 8px 0;
            z-index: 100;
            display: none;
            border: 1px solid #eee;
            animation: slideDown 0.2s ease;
        }

        .profile-dropdown-menu.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-dropdown-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #333;
            text-decoration: none;
            transition: background 0.3s;
            font-size: 14px;
            width: 100%;
            text-align: left;
            border: none;
            background: none;
            cursor: pointer;
        }

        .profile-dropdown-item:hover {
            background: #f8f9ff;
            color: #667eea;
        }

        .profile-dropdown-item i {
            width: 20px;
            color: #667eea;
            font-size: 16px;
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
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        .content-left {
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .header-left h2 {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }

        .header-left p {
            color: #666;
            font-size: 16px;
        }

        .header-right {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .btn-create {
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-create:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-quick {
            padding: 15px 30px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-quick:hover {
            background: #059669;
            transform: translateY(-3px);
        }

        /* Calendar */
        .calendar-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
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
            padding: 12px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            transition: all 0.3s;
            position: relative;
            background: white;
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        .day:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateY(-3px);
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
            margin-bottom: 8px;
            color: #333;
            display: inline-block;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            max-height: 85px;
            overflow-y: auto;
            scrollbar-width: thin;
            margin-top: 5px;
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
            padding: 4px 8px;
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all 0.3s;
            border-left: 3px solid;
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
            min-height: 400px;
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
        }

        .upcoming-title i {
            color: #667eea;
            font-size: 14px;
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
            background: #10b981;
            color: white;
        }

        .btn-join:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: #3b82f6;
            color: white;
        }

        .btn-edit:hover {
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
            background-color: #9e9e9e;
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
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-join-meeting:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-edit-meeting {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .btn-edit-meeting:hover {
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

        .btn-rejoin-meeting {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

        .btn-rejoin-meeting:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        }

        .btn-rejoin-meeting i {
            color: white;
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
            color: #9e9e9e;
        }

        /* Meeting Modal */
        .meeting-modal {
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

        .meeting-modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .meeting-modal-content {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            color: white;
            text-align: center;
            border-radius: 20px 20px 0 0;
        }

        .modal-header h2 {
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .modal-header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .modal-body {
            padding: 30px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #444;
            font-weight: 500;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .required::after {
            content: " *";
            color: #f44336;
        }

        input, textarea, select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
            font-family: inherit;
        }

        input:focus, textarea:focus, select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Password Section */
        .password-section {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border: 2px dashed #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .password-fields {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .password-fields.show {
            display: block;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle input {
            padding-right: 45px;
        }

        .password-toggle .toggle-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 16px;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #444;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
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

        .error i, .success i {
            font-size: 18px;
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

        /* Info Box */
        .info-box {
            background: #fff8e1;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #ffb300;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box i {
            color: #ffb300;
            font-size: 20px;
        }

        .info-box p {
            color: #5d4037;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Security Badge */
        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
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
            z-index: 1000;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Meeting Room Info Modal */
        .room-info-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .room-info-modal.active {
            display: flex;
        }

        .room-info-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            padding: 40px;
            position: relative;
            animation: modalSlide 0.3s ease;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
        }

        .close-modal:hover {
            color: #f44336;
        }

        .room-info-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .room-info-header i {
            font-size: 60px;
            color: #10b981;
            margin-bottom: 15px;
        }

        .room-info-header h3 {
            font-size: 24px;
            color: #333;
        }

        .room-details {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .room-detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .room-detail-item:last-child {
            border-bottom: none;
        }

        .room-detail-item i {
            width: 30px;
            text-align: center;
            color: #667eea;
            font-size: 20px;
        }

        .room-detail-item .detail-label {
            font-weight: 600;
            color: #555;
            min-width: 90px;
            font-size: 14px;
        }

        .room-detail-item .detail-value {
            color: #333;
            font-family: monospace;
            font-size: 14px;
            word-break: break-all;
            flex: 1;
        }

        .copy-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s;
            margin-left: auto;
            font-size: 14px;
        }

        .copy-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: scale(1.1);
        }

        .room-info-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-copy-link, .btn-go-to-room {
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            justify-content: center;
        }

        .btn-copy-link {
            background: #f8f9ff;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-copy-link:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .btn-go-to-room {
            background: #10b981;
            color: white;
        }

        .btn-go-to-room:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
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
            z-index: 1002;
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

        /* Password strength indicator */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
        }

        .password-strength-bar.weak {
            width: 33.33%;
            background-color: #f44336;
        }

        .password-strength-bar.medium {
            width: 66.66%;
            background-color: #ff9800;
        }

        .password-strength-bar.strong {
            width: 100%;
            background-color: #10b981;
        }

        .password-strength-text {
            font-size: 11px;
            margin-top: 5px;
            color: #666;
        }

        /* Pre-join Modal Styles */
        .prejoin-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .prejoin-modal.show {
            display: flex;
        }

        .prejoin-container {
            width: 100%;
            max-width: 1200px;
            padding: 20px;
        }

        .prejoin-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .prejoin-header h1 {
            font-size: 36px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .prejoin-header p {
            color: var(--text-secondary);
            font-size: 18px;
        }

        .prejoin-header .meeting-title {
            color: var(--primary);
            font-weight: 600;
            margin-top: 10px;
        }

        .prejoin-content {
            display: flex;
            gap: 30px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Preview Section */
        .preview-section {
            flex: 1;
        }

        .preview-container {
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            aspect-ratio: 16/9;
            position: relative;
            border: 2px solid var(--dark-border);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .preview-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: var(--text-secondary);
            font-size: 100px;
        }

        .preview-badge {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 20px;
        }

        .preview-badge-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }

        .preview-badge-item i {
            font-size: 16px;
        }

        .preview-badge-item i.mic-on {
            color: var(--success);
        }

        .preview-badge-item i.mic-off {
            color: var(--danger);
        }

        .preview-badge-item i.camera-on {
            color: var(--success);
        }

        .preview-badge-item i.camera-off {
            color: var(--danger);
        }

        /* Device Selection Section */
        .device-section {
            width: 400px;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .device-group {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .device-group h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .device-group h3 i {
            color: var(--primary);
            width: 24px;
        }

        .device-selector {
            width: 100%;
            padding: 12px 15px;
            background: var(--dark-card);
            border: 2px solid var(--dark-border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            cursor: pointer;
            outline: none;
            transition: all 0.3s;
            margin-bottom: 10px;
        }

        .device-selector:hover {
            border-color: var(--primary);
        }

        .device-selector:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .device-option {
            background: var(--dark-card);
            color: var(--text-primary);
        }

        .device-status {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
        }

        .device-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 15px;
            background: var(--dark-card);
            border-radius: 30px;
            border: 1px solid var(--dark-border);
            transition: all 0.3s;
        }

        .device-toggle:hover {
            border-color: var(--primary);
        }

        .device-toggle i {
            font-size: 16px;
        }

        .device-toggle.on {
            background: var(--primary);
            border-color: var(--primary);
        }

        .device-toggle.on i {
            color: white;
        }

        .device-toggle.off {
            background: var(--dark-card);
        }

        .device-toggle.off i {
            color: var(--text-secondary);
        }

        /* Action Buttons */
        .prejoin-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .prejoin-btn {
            flex: 1;
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            border: none;
        }

        .prejoin-btn.cancel {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--dark-border);
        }

        .prejoin-btn.cancel:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        .prejoin-btn.join {
            background: var(--primary);
            color: white;
        }

        .prejoin-btn.join:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .prejoin-btn i {
            font-size: 18px;
        }

        /* Warning Messages - Individual Column Errors */
        .error-message {
            margin-top: 8px;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideIn 0.3s ease;
        }

        .error-message i {
            font-size: 14px;
        }

        .error-message.title-error {
            background: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #f44336;
        }

        .error-message.date-error {
            background: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #f44336;
        }

        .error-message.time-error {
            background: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #f44336;
        }

        .error-message.duration-error {
            background: #fff3e0;
            color: #f57c00;
            border-left: 4px solid #f57c00;
        }

        .error-message.duration-error.error {
            background: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #f44336;
        }

        .error-message.password-error {
            background: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #f44336;
        }

        .error-message.past-time-error {
            background: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #f44336;
        }

        /* Form row with error container */
        .form-row {
            position: relative;
        }

        .error-container {
            margin-top: 5px;
            min-height: 30px;
        }

        /* Toast notification for copy */
        .copy-toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 14px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            z-index: 9999;
            animation: fadeInUp 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .copy-toast.success {
            background: #10b981;
        }
        
        .copy-toast.error {
            background: #ef4444;
        }
        
        .copy-toast i {
            font-size: 18px;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translate(-50%, 20px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, 0);
            }
        }
        
        @keyframes fadeOutUp {
            from {
                opacity: 1;
                transform: translate(-50%, 0);
            }
            to {
                opacity: 0;
                transform: translate(-50%, -20px);
            }
        }

        /* Additional styles for scheduled meetings */
        .scheduled-item {
            background: linear-gradient(135deg, #f0f4ff, #e8f0fe);
            border-left: 4px solid #667eea;
            position: relative;
            overflow: hidden;
        }

        .scheduled-item::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, transparent 50%, rgba(102, 126, 234, 0.1) 50%);
            pointer-events: none;
        }

        .status-indicator.scheduled-indicator {
            background-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
            animation: pulse-scheduled 2s infinite;
        }

        @keyframes pulse-scheduled {
            0% {
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(102, 126, 234, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
            }
        }

        .upcoming-time.scheduled-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .upcoming-date.today-badge {
            color: #10b981;
            font-weight: 600;
        }

        .upcoming-date.today-badge i {
            color: #10b981;
        }

        .upcoming-date.tomorrow-badge {
            color: #f59e0b;
        }

        .upcoming-date.tomorrow-badge i {
            color: #f59e0b;
        }

        .upcoming-date.future-badge {
            color: #667eea;
        }

        .upcoming-date.future-badge i {
            color: #667eea;
        }

        .upcoming-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .upcoming-actions button {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            flex: 1;
        }

        .btn-join-small {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-join-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-copy-link-small {
            background: #f8f9ff;
            color: #667eea;
            border: 1px solid #667eea;
        }

        .btn-copy-link-small:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .empty-state .btn-quick {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            border: 2px solid transparent;
            letter-spacing: 0.5px;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .empty-state .btn-quick::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .empty-state .btn-quick:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .empty-state .btn-quick:hover::before {
            width: 400px;
            height: 400px;
        }

        .empty-state .btn-quick:active {
            transform: translateY(0);
        }

        .empty-state .btn-quick i {
            font-size: 16px;
            transition: transform 0.3s ease;
            transform: translateY(8px); 
            display: inline-block;
        }

        .empty-state .btn-quick:hover i {
            transform: translateY(8px) rotate(90deg) scale(1.2); 
        }
    </style>
</head>
<body>
    <!-- Pre-join Modal -->
    <div class="prejoin-modal" id="prejoin-modal">
        <div class="prejoin-container">
            <div class="prejoin-header">
                <h1>Join Meeting</h1>
                <p id="prejoin-meeting-title">Meeting Room</p>
                <div class="meeting-title" id="prejoin-meeting-id">
                    <i class="fas fa-hashtag"></i> 
                </div>
            </div>
            
            <div class="prejoin-content">
                <!-- Preview Section -->
                <div class="preview-section">
                    <div class="preview-container">
                        <video id="prejoin-video" autoplay playsinline muted></video>
                        <div class="preview-placeholder" id="preview-placeholder">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="preview-badge">
                            <div class="preview-badge-item">
                                <i class="fas fa-microphone" id="preview-mic-icon"></i>
                                <span id="preview-mic-text">Mic On</span>
                            </div>
                            <div class="preview-badge-item">
                                <i class="fas fa-video" id="preview-camera-icon"></i>
                                <span id="preview-camera-text">Camera On</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Device Selection Section -->
                <div class="device-section">
                    <!-- Microphone Selection -->
                    <div class="device-group">
                        <h3><i class="fas fa-microphone"></i> Microphone</h3>
                        <select class="device-selector" id="mic-select">
                            <option value="default">Default Microphone</option>
                        </select>
                        <div class="device-status">
                            <button class="device-toggle on" id="mic-toggle">
                                <i class="fas fa-microphone"></i>
                                <span>On</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Camera Selection -->
                    <div class="device-group">
                        <h3><i class="fas fa-video"></i> Camera</h3>
                        <select class="device-selector" id="camera-select">
                            <option value="default">Default Camera</option>
                        </select>
                        <div class="device-status">
                            <button class="device-toggle on" id="camera-toggle">
                                <i class="fas fa-video"></i>
                                <span>On</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Speaker Selection -->
                    <div class="device-group">
                        <h3><i class="fas fa-volume-up"></i> Speaker</h3>
                        <select class="device-selector" id="speaker-select">
                            <option value="default">Default Speaker</option>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="prejoin-actions">
                        <button class="prejoin-btn cancel" onclick="closePrejoinModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button class="prejoin-btn join" id="join-meeting-btn">
                            <i class="fas fa-video"></i> Join Meeting
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="schedule-container">
        <!-- Left Sidebar - UPDATED TO MATCH CHAT.PHP -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> SkyMeet</h1>
            </div>

            <!-- User Profile with Photo and Dropdown -->
            <div class="user-profile">
                <div class="user-profile-content" id="userProfileTrigger">
                    <div class="user-avatar">
                        <?php 
                        $profile_photo = getProfilePhoto($user);
                        if ($profile_photo): ?>
                            <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="<?php echo htmlspecialchars($username); ?>">
                        <?php else: ?>
                            <?php echo getInitials($username); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($username); ?></h3>
                        <p><?php echo htmlspecialchars($user['email'] ?? 'User'); ?></p>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon" id="dropdownIcon"></i>
                </div>
                
                <!-- Dropdown Menu - Only View Profile -->
                <div class="profile-dropdown-menu" id="profileDropdownMenu">
                    <a href="profile.php?id=<?php echo $user_id; ?>" class="profile-dropdown-item">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                </div>
            </div>

            <!-- Main Navigation -->
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="meetings.php" class="nav-item">
                    <i class="fas fa-video"></i> Meetings
                    <?php if ($upcoming_meetings_count > 0): ?>
                        <span class="nav-badge"><?php echo $upcoming_meetings_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="schedule.php" class="nav-item active">
                    <i class="fas fa-calendar-alt"></i> Schedule
                    <?php if ($upcoming_meetings_count > 0): ?>
                        <span class="nav-badge"><?php echo $upcoming_meetings_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="chat.php" class="nav-item">
                    <i class="fas fa-comments"></i> Messages
                    <?php if ($total_unread_messages > 0): ?>
                        <span class="nav-badge"><?php echo $total_unread_messages; ?></span>
                    <?php else: ?>
                        <span class="nav-badge">0</span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="help.php" class="nav-item">
                    <i class="fas fa-question-circle"></i> Help & Support
                </a>
            </nav>

            <!-- Sidebar Footer with Logout Button -->
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
            <div class="content-left">
                <!-- Header -->
                <div class="header">
                    <div class="header-left">
                        <h2>Meeting Schedule</h2>
                        <p><?php echo getGreeting(); ?>, <?php echo htmlspecialchars($username); ?>! View and manage your meetings in calendar view</p>
                    </div>
                    <div class="header-right">
                        <button class="btn-quick" onclick="showMeetingModal()">
                            <i class="fas fa-plus"></i> Schedule Meeting
                        </button>
                    </div>
                </div>

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
                                        $status_class = "status-" . $status;
                                        echo '<div class="meeting-item-small ' . $status_class . '" 
                                              onclick="event.stopPropagation(); showMeetingDetails(' . $meeting['id'] . ', \'' . addslashes($meeting['title']) . '\', \'' . addslashes($meeting['description']) . '\', \'' . $meeting['meeting_date'] . '\', \'' . $meeting['start_time'] . '\', \'' . $meeting['end_time'] . '\', \'' . $status . '\', \'' . $meeting['room_id'] . '\')">' 
                                              . htmlspecialchars(substr($meeting['title'], 0, 12)) . 
                                              ' (' . date('g:i A', strtotime($meeting['start_time'])) . ')' .
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

            <!-- Right Sidebar - Scheduled Meetings -->
            <div class="right-sidebar">
                <div class="right-sidebar-header">
                    <h3><i class="fas fa-calendar-alt"></i> Scheduled Meetings</h3>
                </div>
                <div class="upcoming-list">
                    <?php if (empty($upcoming_meetings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <h4>No Scheduled Meetings</h4>
                            <p>Schedule a new meeting to get started</p>
                            <button class="btn-quick" onclick="showMeetingModal()" style="margin-top: 15px; padding: 10px 20px; font-size: 14px;">
                                <i class="fas fa-plus"></i> Schedule Meeting
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_meetings as $meeting): 
                            $status = getMeetingStatus($meeting['meeting_date'], $meeting['start_time'], $meeting['end_time']);
                            $today_date = date('Y-m-d');
                            $meeting_date = $meeting['meeting_date'];
                            
                            // Only show upcoming meetings (not ongoing)
                            if ($status !== 'upcoming') continue;
                            
                            // Display time logic
                            if ($meeting_date == $today_date) {
                                $time_display = "Today, " . formatTime($meeting['start_time']);
                                $date_class = "today-badge";
                            } elseif ($meeting_date == date('Y-m-d', strtotime('+1 day'))) {
                                $time_display = "Tomorrow, " . formatTime($meeting['start_time']);
                                $date_class = "tomorrow-badge";
                            } else {
                                $time_display = date('M j', strtotime($meeting_date)) . ', ' . formatTime($meeting['start_time']);
                                $date_class = "future-badge";
                            }
                            
                            // Calculate days until meeting
                            $meeting_timestamp = strtotime($meeting_date);
                            $days_until = floor(($meeting_timestamp - strtotime($today_date)) / (60 * 60 * 24));
                            
                            if ($days_until > 0 && $meeting_date != $today_date && $meeting_date != date('Y-m-d', strtotime('+1 day'))) {
                                $time_display .= ' <span style="font-size: 11px; opacity: 0.7;">(' . $days_until . ' days)</span>';
                            }
                            
                            $room_param = !empty($meeting['room_id']) ? 'room=' . urlencode($meeting['room_id']) : 'id=' . $meeting['id'];
                        ?>
                        <div class="upcoming-item scheduled-item" onclick="showMeetingDetails(<?php echo $meeting['id']; ?>, '<?php echo addslashes($meeting['title']); ?>', '<?php echo addslashes($meeting['description']); ?>', '<?php echo $meeting['meeting_date']; ?>', '<?php echo $meeting['start_time']; ?>', '<?php echo $meeting['end_time']; ?>', '<?php echo $status; ?>', '<?php echo $meeting['room_id']; ?>')">
                            <div class="upcoming-item-header">
                                <div>
                                    <div class="upcoming-title">
                                        <span class="status-indicator scheduled-indicator"></span>
                                        <i class="fas fa-calendar-check" style="color: #667eea;"></i>
                                        <?php echo htmlspecialchars(substr($meeting['title'], 0, 25)); ?>
                                        <?php if (!empty($meeting['password'])): ?>
                                            <i class="fas fa-lock" style="font-size: 12px; color: #f59e0b; margin-left: 5px;" title="Password Protected"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="upcoming-date <?php echo $date_class; ?>">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $time_display; ?>
                                    </div>
                                </div>
                                <div class="upcoming-time scheduled-badge">
                                    <i class="fas fa-hourglass-half"></i>
                                    Scheduled
                                </div>
                            </div>
                            <div class="upcoming-details">
                                <?php 
                                $desc = $meeting['description'] ?: 'No description provided';
                                echo htmlspecialchars(substr($desc, 0, 70)); 
                                if (strlen($desc) > 70) echo '...';
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="view-all-container">
                            <a href="meetings.php?filter=upcoming" class="view-all-link">
                                <i class="fas fa-eye"></i> View All Scheduled Meetings
                            </a>
                        </div>
                    <?php endif; ?>
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

                <div class="detail-actions" id="detailActions">
                    <!-- Join button will be dynamically shown/hidden -->
                </div>
            </div>
        </div>
    </div>

    <!-- Meeting Setup Modal -->
    <div class="meeting-modal" id="meetingModal">
        <div class="meeting-modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-plus"></i> Schedule Meeting</h2>
                <p>Configure your meeting details and schedule it</p>
            </div>
            
            <div class="modal-body">
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

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p>
                        <strong>Note:</strong> Schedule a meeting for a specific date and time. 
                        Meeting duration cannot exceed 4 hours. You can protect it with a password to control access.
                    </p>
                </div>

                <form method="POST" id="meetingForm" onsubmit="return validateMeetingForm(event)">
                    <input type="hidden" name="create_meeting" value="1">
                    
                    <!-- Title Field with Error Container -->
                    <div class="form-group">
                        <label for="title" class="required"><i class="fas fa-heading"></i> Meeting Title</label>
                        <input type="text" id="title" name="title" required 
                               placeholder="Team Sync, Client Meeting, Class Session..." 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        <div id="title-error-container" class="error-container"></div>
                    </div>

                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description (Optional)</label>
                        <textarea id="description" name="description" 
                                  placeholder="Meeting agenda, objectives, or any special notes..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Date Field with Error Container -->
                    <div class="form-group">
                        <label for="meeting_date" class="required"><i class="fas fa-calendar"></i> Meeting Date</label>
                        <input type="date" id="meeting_date" name="meeting_date" required 
                               value="<?= htmlspecialchars($_POST['meeting_date'] ?? date('Y-m-d')) ?>" 
                               min="<?= date('Y-m-d') ?>">
                        <div id="date-error-container" class="error-container"></div>
                    </div>

                    <!-- Time Fields with Error Containers -->
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="start_time" class="required"><i class="fas fa-clock"></i> Start Time</label>
                            <input type="time" id="start_time" name="start_time" required 
                                   value="<?= htmlspecialchars($_POST['start_time'] ?? date('H:i', strtotime('+1 hour'))) ?>">
                            <div id="start-time-error-container" class="error-container"></div>
                        </div>
                        <div class="form-group">
                            <label for="end_time" class="required"><i class="fas fa-clock"></i> End Time</label>
                            <input type="time" id="end_time" name="end_time" required 
                                   value="<?= htmlspecialchars($_POST['end_time'] ?? date('H:i', strtotime('+2 hours'))) ?>">
                            <div id="end-time-error-container" class="error-container"></div>
                        </div>
                    </div>

                    <!-- Duration Warning Container (separate) -->
                    <div id="duration-error-container" class="error-container"></div>

                    <!-- Past Time Warning Container (separate) -->
                    <div id="past-time-error-container" class="error-container"></div>

                    <!-- Password Section with Error Container -->
                    <div class="password-section">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_protected" name="is_protected" value="1" 
                                   <?= isset($_POST['is_protected']) ? 'checked' : '' ?>>
                            <label for="is_protected">
                                <i class="fas fa-lock"></i> Password Protect This Meeting
                                <span class="security-badge">
                                    <i class="fas fa-shield-alt"></i> Secure
                                </span>
                            </label>
                        </div>
                        
                        <div class="password-fields" id="passwordFields">
                            <div class="form-group">
                                <label for="password" class="required"><i class="fas fa-key"></i> Meeting Password (min. 6 characters)</label>
                                <div class="password-toggle">
                                    <input type="password" id="password" name="password" 
                                           placeholder="Enter at least 6 characters"
                                           value="<?= htmlspecialchars($_POST['password'] ?? '') ?>"
                                           onkeyup="checkPasswordStrength(this.value)">
                                    <button type="button" class="toggle-btn" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength">
                                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                </div>
                                <div class="password-strength-text" id="passwordStrengthText"></div>
                                <small style="color:#666;display:block;margin-top:5px;">
                                    Participants will need this password to join the meeting
                                </small>
                                <div id="password-error-container" class="error-container"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="required"><i class="fas fa-key"></i> Confirm Password</label>
                                <div class="password-toggle">
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           placeholder="Re-enter the password"
                                           value="<?= htmlspecialchars($_POST['confirm_password'] ?? '') ?>">
                                    <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="confirm-password-error-container" class="error-container"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success" id="createBtn">
                            <i class="fas fa-calendar-plus"></i> Schedule Meeting
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Meeting Room Info Modal -->
    <div class="room-info-modal" id="roomInfoModal">
        <div class="room-info-content">
            <button class="close-modal" onclick="closeRoomInfoModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="room-info-header">
                <i class="fas fa-check-circle"></i>
                <h3>Meeting Created Successfully!</h3>
            </div>
            <div class="room-details" id="roomDetails">
                <!-- Will be populated by JavaScript -->
            </div>
            <div class="room-info-actions">
                <button class="btn-copy-link" onclick="copyMeetingLink()">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
                <button class="btn-go-to-room" onclick="goToMeetingRoom()">
                    <i class="fas fa-video"></i> Go to Meeting
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="delete-modal" id="deleteModal">
        <div class="delete-modal-content">
            <div class="delete-modal-header">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
                <h3>Delete Meeting</h3>
            </div>
            <div class="delete-modal-body">
                <p>Are you sure you want to delete this meeting? This action cannot be undone.</p>
            </div>
            <div class="delete-modal-actions">
                <button class="btn-delete-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn-delete-confirm" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
    <div class="success-message" id="successMessage">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($_GET['success']); ?></span>
    </div>
    <?php endif; ?>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded');
            
            // Initialize all event listeners
            initializeEventListeners();
            
            // Initialize password fields visibility
            updatePasswordFields();
            
            // Initialize profile dropdown from dashboard.php
            initializeProfileDropdown();
            
            // Auto-refresh calendar every 5 minutes
            setInterval(() => {
                if (!document.hidden) {
                    location.reload();
                }
            }, 300000);
        });

        // PROFILE DROPDOWN MENU - EXACTLY FROM CHAT.PHP
        const userProfileTrigger = document.getElementById('userProfileTrigger');
        const profileDropdownMenu = document.getElementById('profileDropdownMenu');
        const dropdownIcon = document.getElementById('dropdownIcon');

        if (userProfileTrigger) {
            userProfileTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdownMenu.classList.toggle('show');
                if (dropdownIcon) {
                    dropdownIcon.style.transform = profileDropdownMenu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0)';
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (profileDropdownMenu && !profileDropdownMenu.contains(e.target) && !userProfileTrigger.contains(e.target)) {
                profileDropdownMenu.classList.remove('show');
                if (dropdownIcon) {
                    dropdownIcon.style.transform = 'rotate(0)';
                }
            }
        });

        // Prevent dropdown from closing when clicking inside dropdown
        if (profileDropdownMenu) {
            profileDropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Ensure dropdown is closed when page loads (especially after navigation)
        document.addEventListener('DOMContentLoaded', function() {
            // Force dropdown to be closed on page load
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        // Handle pageshow event (fires when page is loaded from cache/back navigation)
        window.addEventListener('pageshow', function(event) {
            // Force dropdown to be closed when page is shown (including from back button)
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        // Handle popstate event (back/forward navigation)
        window.addEventListener('popstate', function() {
            // Force dropdown to be closed on history navigation
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        // Handle visibility change (when tab becomes active again)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // Page became visible again, ensure dropdown is closed
                if (profileDropdownMenu) {
                    profileDropdownMenu.classList.remove('show');
                }
                if (dropdownIcon) {
                    dropdownIcon.style.transform = 'rotate(0)';
                }
            }
        });

        // Meeting room data
        let currentMeetingRoom = {
            id: '<?php echo isset($_GET['room']) ? htmlspecialchars($_GET['room']) : ''; ?>',
            password: '<?php echo isset($_GET['password']) ? htmlspecialchars(addslashes($_GET['password'])) : ''; ?>'
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
            password: ''
        };

        // Pre-join variables
        let localStream = null;
        let isMicOn = true;
        let isCameraOn = true;
        let audioInputs = [];
        let videoInputs = [];
        let audioOutputs = [];

        // Function to clear all error messages
        function clearAllErrors() {
            const errorContainers = [
                'title-error-container',
                'date-error-container',
                'start-time-error-container',
                'end-time-error-container',
                'duration-error-container',
                'past-time-error-container',
                'password-error-container',
                'confirm-password-error-container'
            ];
            
            errorContainers.forEach(containerId => {
                const container = document.getElementById(containerId);
                if (container) {
                    container.innerHTML = '';
                }
            });
        }

        // Function to show error message in specific container
        function showError(containerId, message, type = 'error') {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            let className = 'error-message';
            if (containerId.includes('title')) className += ' title-error';
            else if (containerId.includes('date')) className += ' date-error';
            else if (containerId.includes('time')) className += ' time-error';
            else if (containerId.includes('duration')) className += ' duration-error';
            else if (containerId.includes('past')) className += ' past-time-error';
            else if (containerId.includes('password')) className += ' password-error';
            
            if (type === 'warning') {
                className += ' duration-error';
            }
            
            const icon = type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle';
            
            container.innerHTML = `
                <div class="${className}">
                    <i class="fas ${icon}"></i>
                    <span>${message}</span>
                </div>
            `;
        }

        // Initialize all event listeners
        function initializeEventListeners() {
            // Schedule Meeting button
            const scheduleBtn = document.querySelector('.btn-quick');
            if (scheduleBtn) {
                scheduleBtn.onclick = function(e) {
                    e.preventDefault();
                    showMeetingModal();
                };
            }

            // Cancel button
            const cancelBtn = document.getElementById('cancelBtn');
            if (cancelBtn) {
                cancelBtn.onclick = function(e) {
                    e.preventDefault();
                    hideMeetingModal();
                };
            }

            // Password protection checkbox
            const isProtectedCheckbox = document.getElementById('is_protected');
            if (isProtectedCheckbox) {
                isProtectedCheckbox.onchange = updatePasswordFields;
            }

            // Time inputs for real-time validation
            const startTime = document.getElementById('start_time');
            const endTime = document.getElementById('end_time');
            const meetingDate = document.getElementById('meeting_date');
            const title = document.getElementById('title');

            if (startTime) {
                startTime.addEventListener('change', validateRealTime);
                startTime.addEventListener('input', validateRealTime);
            }
            
            if (endTime) {
                endTime.addEventListener('change', validateRealTime);
                endTime.addEventListener('input', validateRealTime);
            }
            
            if (meetingDate) {
                meetingDate.addEventListener('change', validateRealTime);
            }

            if (title) {
                title.addEventListener('input', function() {
                    const titleError = document.getElementById('title-error-container');
                    if (title.value.trim()) {
                        titleError.innerHTML = '';
                    }
                });
            }

            // Password fields validation
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password) {
                password.addEventListener('input', function() {
                    validatePasswordFields();
                });
            }
            
            if (confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    validatePasswordFields();
                });
            }

            // Form submission
            const meetingForm = document.getElementById('meetingForm');
            if (meetingForm) {
                meetingForm.onsubmit = function(e) {
                    return validateMeetingForm(e);
                };
            }

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const meetingModal = document.getElementById('meetingModal');
                const meetingDetailModal = document.getElementById('meetingDetailModal');
                
                if (event.target === meetingModal) {
                    hideMeetingModal();
                }
                if (event.target === meetingDetailModal) {
                    closeMeetingDetail();
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(event) {
                // Ctrl/Cmd + N for new meeting
                if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
                    event.preventDefault();
                    showMeetingModal();
                }
                // Escape to close modals
                if (event.key === 'Escape') {
                    hideMeetingModal();
                    closeDeleteModal();
                    closeRoomInfoModal();
                    closeMeetingDetail();
                    closePrejoinModal();
                }
            });

            // Close modals when clicking outside
            const roomInfoModal = document.getElementById('roomInfoModal');
            if (roomInfoModal) {
                roomInfoModal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        closeRoomInfoModal();
                    }
                });
            }

            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        closeDeleteModal();
                    }
                });
            }

            const prejoinModal = document.getElementById('prejoin-modal');
            if (prejoinModal) {
                prejoinModal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        closePrejoinModal();
                    }
                });
            }

            // Delete confirmation
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.onclick = function() {
                    if (meetingToDelete) {
                        window.location.href = 'schedule.php?delete=' + meetingToDelete;
                    }
                };
            }

            // Pre-join controls
            const micToggle = document.getElementById('mic-toggle');
            if (micToggle) {
                micToggle.onclick = togglePrejoinMic;
            }

            const cameraToggle = document.getElementById('camera-toggle');
            if (cameraToggle) {
                cameraToggle.onclick = togglePrejoinCamera;
            }

            const joinMeetingBtn = document.getElementById('join-meeting-btn');
            if (joinMeetingBtn) {
                joinMeetingBtn.onclick = joinMeetingFromPrejoin;
            }

            const micSelect = document.getElementById('mic-select');
            if (micSelect) {
                micSelect.onchange = changeAudioInput;
            }

            const cameraSelect = document.getElementById('camera-select');
            if (cameraSelect) {
                cameraSelect.onchange = changeVideoInput;
            }

            const speakerSelect = document.getElementById('speaker-select');
            if (speakerSelect) {
                speakerSelect.onchange = changeAudioOutput;
            }
        }

        // Initialize profile dropdown
        function initializeProfileDropdown() {
            const userProfileTrigger = document.getElementById('userProfileTrigger');
            const profileDropdownMenu = document.getElementById('profileDropdownMenu');
            const dropdownIcon = document.getElementById('dropdownIcon');

            if (userProfileTrigger) {
                userProfileTrigger.onclick = function(e) {
                    e.stopPropagation();
                    profileDropdownMenu.classList.toggle('show');
                    if (dropdownIcon) {
                        dropdownIcon.style.transform = profileDropdownMenu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0)';
                    }
                };
            }

            // Close dropdown when clicking outside
            document.onclick = function(e) {
                if (profileDropdownMenu && !profileDropdownMenu.contains(e.target) && !userProfileTrigger.contains(e.target)) {
                    profileDropdownMenu.classList.remove('show');
                    if (dropdownIcon) {
                        dropdownIcon.style.transform = 'rotate(0)';
                    }
                }
            };

            // Prevent dropdown from closing when clicking inside dropdown
            if (profileDropdownMenu) {
                profileDropdownMenu.onclick = function(e) {
                    e.stopPropagation();
                };
            }
        }

        // Validate password fields
        function validatePasswordFields() {
            const isProtected = document.getElementById('is_protected')?.checked;
            if (!isProtected) return true;
            
            const password = document.getElementById('password')?.value || '';
            const confirmPassword = document.getElementById('confirm_password')?.value || '';
            const passwordError = document.getElementById('password-error-container');
            const confirmPasswordError = document.getElementById('confirm-password-error-container');
            
            let isValid = true;
            
            if (passwordError) passwordError.innerHTML = '';
            if (confirmPasswordError) confirmPasswordError.innerHTML = '';
            
            if (!password) {
                if (passwordError) {
                    showError('password-error-container', 'Password is required for protected meeting');
                }
                isValid = false;
            } else if (password.length < 6) {
                if (passwordError) {
                    showError('password-error-container', 'Password must be at least 6 characters long');
                }
                isValid = false;
            }
            
            if (password !== confirmPassword) {
                if (confirmPasswordError) {
                    showError('confirm-password-error-container', 'Passwords do not match');
                }
                isValid = false;
            }
            
            return isValid;
        }

        // Meeting Detail Modal Functions
        const meetingDetailModal = document.getElementById('meetingDetailModal');
        const detailActions = document.getElementById('detailActions');

        function showMeetingDetails(id, title, description, date, startTime, endTime, status, roomId) {
            currentDetailMeeting.id = id;
            currentDetailMeeting.roomId = roomId;
            currentDetailMeeting.status = status;
            currentDetailMeeting.title = title;
            currentDetailMeeting.description = description;
            currentDetailMeeting.date = date;
            currentDetailMeeting.startTime = startTime;
            currentDetailMeeting.endTime = endTime;
            
            document.getElementById('detailTitle').textContent = title;
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
            
            // Create action buttons based on status
            let buttons = '';
            
            // Join button appears for ALL meetings except if roomId is not available
            if (roomId) {
                if (status === 'completed') {
                    buttons += `
                        <button class="btn-rejoin-meeting" onclick="joinFromDetail()">
                            <i class="fas fa-redo-alt"></i> Rejoin Meeting
                        </button>
                    `;
                } else if (status === 'upcoming' || status === 'ongoing') {
                    buttons += `
                        <button class="btn-join-meeting" onclick="joinFromDetail()">
                            <i class="fas fa-video"></i> Join Meeting
                        </button>
                    `;
                }
            }
            
            // Edit button for upcoming meetings (optional)
            if (status === 'upcoming') {
                buttons += `
                    <button class="btn-edit-meeting" onclick="editFromDetail()">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                `;
            }
            
            // Delete button for all meetings (host only)
            buttons += `
                <button class="btn-delete-meeting" onclick="deleteFromDetail()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            `;
            
            detailActions.innerHTML = buttons;
            
            meetingDetailModal.classList.add('show');
        }

        function closeMeetingDetail() {
            meetingDetailModal.classList.remove('show');
        }

        function joinFromDetail() {
            if (currentDetailMeeting.roomId) {
                // Show pre-join modal instead of going directly
                showPrejoinModal(currentDetailMeeting.roomId, currentDetailMeeting.title);
            } else {
                alert('This meeting does not have a room ID');
            }
        }

        function editFromDetail() {
            window.location.href = 'edit-meeting.php?id=' + currentDetailMeeting.id;
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

        // Meeting Modal Functions
        const meetingModal = document.getElementById('meetingModal');
        const createBtn = document.getElementById('createBtn');

        function showMeetingModal() {
            const meetingForm = document.getElementById('meetingForm');
            
            // Reset form
            if (meetingForm) {
                meetingForm.reset();
            }
            
            // Clear all error messages
            clearAllErrors();
            
            // Set default values
            const now = new Date();
            const dateStr = now.toISOString().split('T')[0];
            const meetingDate = document.getElementById('meeting_date');
            if (meetingDate) {
                meetingDate.value = dateStr;
                meetingDate.min = dateStr;
            }
            
            // Set default times (current time + 1 hour for start, + 2 hours for end)
            const nextHour = new Date(now.getTime() + 60 * 60 * 1000);
            const twoHoursLater = new Date(now.getTime() + 120 * 60 * 1000);
            
            const formatTimeInput = (date) => {
                return date.getHours().toString().padStart(2, '0') + ':' + 
                       date.getMinutes().toString().padStart(2, '0');
            };
            
            const startTime = document.getElementById('start_time');
            const endTime = document.getElementById('end_time');
            
            if (startTime) {
                startTime.value = formatTimeInput(nextHour);
            }
            
            if (endTime) {
                endTime.value = formatTimeInput(twoHoursLater);
            }
            
            // Reset password fields
            const isProtectedCheckbox = document.getElementById('is_protected');
            if (isProtectedCheckbox) {
                isProtectedCheckbox.checked = false;
            }
            
            updatePasswordFields();
            
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password) password.value = '';
            if (confirmPassword) confirmPassword.value = '';
            
            // Reset password strength
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');
            
            if (strengthBar) strengthBar.className = 'password-strength-bar';
            if (strengthText) strengthText.textContent = '';
            
            // Set focus to title field
            setTimeout(() => {
                const title = document.getElementById('title');
                if (title) title.focus();
            }, 100);
            
            // Show modal
            if (meetingModal) {
                meetingModal.classList.add('show');
            }
        }

        function hideMeetingModal() {
            if (meetingModal) {
                meetingModal.classList.remove('show');
            }
            clearAllErrors();
        }

        // Toggle password fields visibility
        function updatePasswordFields() {
            const isProtectedCheckbox = document.getElementById('is_protected');
            const passwordFields = document.getElementById('passwordFields');
            
            if (isProtectedCheckbox && passwordFields) {
                if (isProtectedCheckbox.checked) {
                    passwordFields.classList.add('show');
                } else {
                    passwordFields.classList.remove('show');
                }
            }
        }

        // Password toggle visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                field.type = 'password';
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');
            
            if (!strengthBar || !strengthText) return;
            
            if (!password) {
                strengthBar.className = 'password-strength-bar';
                strengthText.textContent = '';
                return;
            }
            
            let strength = 0;
            
            // Length check
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            
            // Character type checks
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            // Determine strength level
            if (strength <= 2) {
                strengthBar.className = 'password-strength-bar weak';
                strengthText.textContent = 'Weak password';
            } else if (strength <= 4) {
                strengthBar.className = 'password-strength-bar medium';
                strengthText.textContent = 'Medium password';
            } else {
                strengthBar.className = 'password-strength-bar strong';
                strengthText.textContent = 'Strong password';
            }
        }

        // Function to check if start time is in the past
        function checkPastTime() {
            const meetingDate = document.getElementById('meeting_date');
            const startTime = document.getElementById('start_time');
            
            if (!meetingDate || !startTime) return true;
            
            const meetingDateValue = meetingDate.value;
            const startTimeValue = startTime.value;
            
            const today = new Date().toISOString().split('T')[0];
            
            if (meetingDateValue === today && startTimeValue) {
                const now = new Date();
                const [startHours, startMinutes] = startTimeValue.split(':');
                const startDateTime = new Date(meetingDateValue);
                startDateTime.setHours(parseInt(startHours), parseInt(startMinutes), 0);
                
                if (startDateTime < now) {
                    const currentTimeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                    showError('past-time-error-container', `Start time cannot be in the past for today's meeting. Current time is ${currentTimeStr}`, 'error');
                    return false;
                } else {
                    document.getElementById('past-time-error-container').innerHTML = '';
                    return true;
                }
            } else {
                document.getElementById('past-time-error-container').innerHTML = '';
                return true;
            }
        }

        // Real-time duration checker
        function checkMeetingDuration() {
            const startTime = document.getElementById('start_time');
            const endTime = document.getElementById('end_time');
            const meetingDate = document.getElementById('meeting_date');
            
            if (!startTime || !endTime || !meetingDate) return true;
            
            const startTimeValue = startTime.value;
            const endTimeValue = endTime.value;
            const meetingDateValue = meetingDate.value;
            
            if (startTimeValue && endTimeValue && meetingDateValue) {
                // Handle meetings that might span across midnight
                let startDateTime = meetingDateValue + 'T' + startTimeValue + ':00';
                let endDateTime = meetingDateValue + 'T' + endTimeValue + ':00';
                
                let start = new Date(startDateTime);
                let end = new Date(endDateTime);
                
                // If end time is less than start time, assume it goes to next day
                if (end < start) {
                    end = new Date(end.getTime() + 24 * 60 * 60 * 1000);
                }
                
                const durationMs = end - start;
                const durationHours = durationMs / (1000 * 60 * 60);
                
                if (durationHours > 4) {
                    showError('duration-error-container', `Meeting duration cannot exceed 4 hours. Your meeting duration is ${durationHours.toFixed(1)} hours.`, 'error');
                    return false;
                } else if (durationHours < 0.25 && durationHours > 0) {
                    showError('duration-error-container', `Meeting duration is only ${(durationHours * 60).toFixed(0)} minutes. Minimum recommended is 15 minutes.`, 'warning');
                    return true;
                } else {
                    document.getElementById('duration-error-container').innerHTML = '';
                    return true;
                }
            }
            return true;
        }

        // Combined validation function for real-time checking
        function validateRealTime() {
            checkPastTime();
            checkMeetingDuration();
        }

        // Form submission validation
        function validateMeetingForm(e) {
            e.preventDefault();
            
            // Clear all previous errors
            clearAllErrors();
            
            const title = document.getElementById('title');
            const startTime = document.getElementById('start_time');
            const endTime = document.getElementById('end_time');
            const meetingDate = document.getElementById('meeting_date');
            const isProtectedCheckbox = document.getElementById('is_protected');
            
            if (!title || !startTime || !endTime || !meetingDate) return false;
            
            const titleValue = title.value.trim();
            const startTimeValue = startTime.value;
            const endTimeValue = endTime.value;
            const meetingDateValue = meetingDate.value;
            const isProtected = isProtectedCheckbox ? isProtectedCheckbox.checked : false;

            const selectedDate = new Date(meetingDateValue);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            let isValid = true;
            
            // Title validation
            if (!titleValue) {
                showError('title-error-container', 'Meeting title is required');
                title.focus();
                isValid = false;
            }
            
            // Date validation
            if (selectedDate < today) {
                showError('date-error-container', 'Meeting date cannot be in the past');
                meetingDate.focus();
                isValid = false;
            }
            
            // Time validation for today
            if (selectedDate.getTime() === today.getTime()) {
                const now = new Date();
                const [startHours, startMinutes] = startTimeValue.split(':');
                const startDateTime = new Date(meetingDateValue);
                startDateTime.setHours(parseInt(startHours), parseInt(startMinutes), 0);
                
                if (startDateTime < now) {
                    const currentTimeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                    showError('start-time-error-container', `Start time cannot be in the past. Current time is ${currentTimeStr}`);
                    startTime.focus();
                    isValid = false;
                }
            }
            
            // Start/end time order validation
            if (startTimeValue >= endTimeValue) {
                // Check if it might be a meeting spanning midnight
                if (startTimeValue > endTimeValue) {
                    // This is allowed for meetings spanning midnight
                    console.log('Meeting spans midnight');
                } else {
                    showError('end-time-error-container', 'End time must be after start time');
                    endTime.focus();
                    isValid = false;
                }
            }

            // Handle meetings that might span across midnight for duration calculation
            let startDateTime = meetingDateValue + 'T' + startTimeValue + ':00';
            let endDateTime = meetingDateValue + 'T' + endTimeValue + ':00';
            
            let start = new Date(startDateTime);
            let end = new Date(endDateTime);
            
            // If end time is less than start time, assume it goes to next day
            if (end < start) {
                end = new Date(end.getTime() + 24 * 60 * 60 * 1000);
            }
            
            const durationMs = end - start;
            const durationHours = durationMs / (1000 * 60 * 60);

            // Max 4 hours validation
            if (durationHours > 4) {
                showError('duration-error-container', `Meeting duration cannot exceed 4 hours. Your meeting duration is ${durationHours.toFixed(1)} hours.`, 'error');
                endTime.focus();
                isValid = false;
            }

            // Min 15 minutes validation (optional)
            if (durationHours < 0.25 && durationHours > 0) {
                if (!confirm(`Meeting duration is only ${(durationHours * 60).toFixed(0)} minutes. Are you sure you want to continue?`)) {
                    endTime.focus();
                    isValid = false;
                }
            }

            // Validate password fields if protected
            if (isProtected) {
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');
                
                if (!password || !confirmPassword) return false;
                
                const passwordValue = password.value;
                const confirmPasswordValue = confirmPassword.value;
                
                if (!passwordValue) {
                    showError('password-error-container', 'Password is required for protected meeting');
                    password.focus();
                    isValid = false;
                } else if (passwordValue.length < 6) {
                    showError('password-error-container', 'Password must be at least 6 characters long');
                    password.focus();
                    isValid = false;
                }
                
                if (passwordValue !== confirmPasswordValue) {
                    showError('confirm-password-error-container', 'Passwords do not match');
                    confirmPassword.focus();
                    isValid = false;
                }
            }
            
            if (!isValid) {
                return false;
            }
            
            // Disable button and show loading state
            const createBtn = document.getElementById('createBtn');
            if (createBtn) {
                createBtn.disabled = true;
                createBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Meeting...';
            }
            
            // Submit the form
            e.target.submit();
            
            return true;
        }

        // Room Info Modal Functions
        function showRoomInfoModal(meetingId, roomId, password, meetingTitle) {
            currentMeetingRoom.id = roomId;
            currentMeetingRoom.password = password || '';
            
            const baseUrl = window.location.origin;
            const roomLink = baseUrl + '/meeting/meeting_room.php?room=' + roomId;
            const roomDetails = document.getElementById('roomDetails');
            
            if (!roomDetails) return;
            
            // Update modal header with meeting title
            const modalHeader = document.querySelector('.room-info-header h3');
            if (modalHeader && meetingTitle) {
                modalHeader.textContent = 'Meeting Created: ' + meetingTitle;
            }
            
            roomDetails.innerHTML = `
                <div class="room-detail-item">
                    <i class="fas fa-link"></i>
                    <span class="detail-label">Meeting Link:</span>
                    <span class="detail-value" id="meetingLink">${roomLink}</span>
                    <button class="copy-btn" onclick="copyToClipboard('${roomLink}')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="room-detail-item">
                    <i class="fas fa-hashtag"></i>
                    <span class="detail-label">Room ID:</span>
                    <span class="detail-value">${roomId}</span>
                    <button class="copy-btn" onclick="copyToClipboard('${roomId}')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                ${password ? `
                <div class="room-detail-item">
                    <i class="fas fa-lock"></i>
                    <span class="detail-label">Password:</span>
                    <span class="detail-value">${password}</span>
                    <button class="copy-btn" onclick="copyToClipboard('${password}')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                ` : ''}
            `;
            
            const roomInfoModal = document.getElementById('roomInfoModal');
            if (roomInfoModal) {
                roomInfoModal.classList.add('active');
            }
        }

        function closeRoomInfoModal() {
            const roomInfoModal = document.getElementById('roomInfoModal');
            if (roomInfoModal) {
                roomInfoModal.classList.remove('active');
            }
        }

        // Copy functions
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showTemporaryMessage('Copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        function copyMeetingLink() {
            const baseUrl = window.location.origin;
            const roomLink = baseUrl + '/meeting/meeting_room.php?room=' + currentMeetingRoom.id;
            
            // If meeting is password protected, include the password in the copied text
            if (currentMeetingRoom.password) {
                // You could either copy just the link or include the password in the copied text
                copyToClipboard(roomLink + ' (Password: ' + currentMeetingRoom.password + ')');
            } else {
                copyToClipboard(roomLink);
            }
        }

        function goToMeetingRoom() {
            // Show pre-join modal instead of going directly
            showPrejoinModal(currentMeetingRoom.id, 'Meeting Room');
        }

        // Show temporary message
        function showTemporaryMessage(message) {
            const msgDiv = document.createElement('div');
            msgDiv.className = 'success-message';
            msgDiv.innerHTML = `<i class="fas fa-check-circle"></i><span>${message}</span>`;
            document.body.appendChild(msgDiv);
            
            setTimeout(() => {
                msgDiv.style.opacity = '0';
                setTimeout(() => {
                    if (msgDiv.parentNode) {
                        msgDiv.remove();
                    }
                }, 300);
            }, 2000);
        }

        // Pre-join Modal Functions
        const prejoinModal = document.getElementById('prejoin-modal');

        async function showPrejoinModal(roomId, meetingTitle) {
            currentMeetingRoom.id = roomId;
            
            const prejoinMeetingTitle = document.getElementById('prejoin-meeting-title');
            const prejoinMeetingId = document.getElementById('prejoin-meeting-id');
            
            if (prejoinMeetingTitle) {
                prejoinMeetingTitle.textContent = meetingTitle || 'Meeting Room';
            }
            
            if (prejoinMeetingId) {
                prejoinMeetingId.innerHTML = '<i class="fas fa-hashtag"></i> ' + roomId;
            }
            
            if (prejoinModal) {
                prejoinModal.classList.add('show');
            }
            
            // Initialize media devices
            await initPrejoinMedia();
            await enumerateDevices();
        }

        function closePrejoinModal() {
            // Stop all media tracks
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            
            if (prejoinModal) {
                prejoinModal.classList.remove('show');
            }
        }

        async function initPrejoinMedia() {
            try {
                const constraints = {
                    video: {
                        width: { ideal: 1280, max: 1920 },
                        height: { ideal: 720, max: 1080 },
                        facingMode: 'user'
                    },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                };
                
                localStream = await navigator.mediaDevices.getUserMedia(constraints);
                console.log('Got pre-join stream');
                
                const video = document.getElementById('prejoin-video');
                const placeholder = document.getElementById('preview-placeholder');
                
                if (video) {
                    video.srcObject = localStream;
                    video.style.display = 'block';
                }
                
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                
                isMicOn = true;
                isCameraOn = true;
                
                updatePrejoinUI();
            } catch (error) {
                console.error('Error accessing media:', error);
                isMicOn = false;
                isCameraOn = false;
                
                const video = document.getElementById('prejoin-video');
                const placeholder = document.getElementById('preview-placeholder');
                
                if (video) video.style.display = 'none';
                if (placeholder) placeholder.style.display = 'flex';
                
                showNotification('Unable to access camera/microphone', 'warning');
                updatePrejoinUI();
            }
        }

        async function enumerateDevices() {
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                
                audioInputs = devices.filter(device => device.kind === 'audioinput');
                videoInputs = devices.filter(device => device.kind === 'videoinput');
                audioOutputs = devices.filter(device => device.kind === 'audiooutput');
                
                // Populate microphone select
                const micSelect = document.getElementById('mic-select');
                if (micSelect) {
                    micSelect.innerHTML = '';
                    audioInputs.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.text = device.label || `Microphone ${micSelect.length + 1}`;
                        micSelect.appendChild(option);
                    });
                    
                    // Add default option at the beginning
                    const defaultMic = document.createElement('option');
                    defaultMic.value = 'default';
                    defaultMic.text = 'Default Microphone';
                    micSelect.prepend(defaultMic);
                }
                
                // Populate camera select
                const cameraSelect = document.getElementById('camera-select');
                if (cameraSelect) {
                    cameraSelect.innerHTML = '';
                    videoInputs.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.text = device.label || `Camera ${cameraSelect.length + 1}`;
                        cameraSelect.appendChild(option);
                    });
                    
                    // Add default option at the beginning
                    const defaultCamera = document.createElement('option');
                    defaultCamera.value = 'default';
                    defaultCamera.text = 'Default Camera';
                    cameraSelect.prepend(defaultCamera);
                }
                
                // Populate speaker select
                const speakerSelect = document.getElementById('speaker-select');
                if (speakerSelect) {
                    speakerSelect.innerHTML = '';
                    audioOutputs.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.text = device.label || `Speaker ${speakerSelect.length + 1}`;
                        speakerSelect.appendChild(option);
                    });
                    
                    // Add default option at the beginning
                    const defaultSpeaker = document.createElement('option');
                    defaultSpeaker.value = 'default';
                    defaultSpeaker.text = 'Default Speaker';
                    speakerSelect.prepend(defaultSpeaker);
                }
                
            } catch (error) {
                console.error('Error enumerating devices:', error);
            }
        }

        function updatePrejoinUI() {
            // Update mic toggle
            const micToggle = document.getElementById('mic-toggle');
            const previewMicIcon = document.getElementById('preview-mic-icon');
            const previewMicText = document.getElementById('preview-mic-text');
            
            if (micToggle) {
                const micIcon = micToggle.querySelector('i');
                const micText = micToggle.querySelector('span');
                
                if (isMicOn) {
                    micToggle.className = 'device-toggle on';
                    if (micIcon) micIcon.className = 'fas fa-microphone';
                    if (micText) micText.textContent = 'On';
                } else {
                    micToggle.className = 'device-toggle off';
                    if (micIcon) micIcon.className = 'fas fa-microphone-slash';
                    if (micText) micText.textContent = 'Off';
                }
            }
            
            if (previewMicIcon) {
                previewMicIcon.className = isMicOn ? 'fas fa-microphone mic-on' : 'fas fa-microphone-slash mic-off';
            }
            
            if (previewMicText) {
                previewMicText.textContent = isMicOn ? 'Mic On' : 'Mic Off';
            }
            
            // Update camera toggle
            const cameraToggle = document.getElementById('camera-toggle');
            const previewCameraIcon = document.getElementById('preview-camera-icon');
            const previewCameraText = document.getElementById('preview-camera-text');
            
            if (cameraToggle) {
                const cameraIcon = cameraToggle.querySelector('i');
                const cameraText = cameraToggle.querySelector('span');
                
                if (isCameraOn) {
                    cameraToggle.className = 'device-toggle on';
                    if (cameraIcon) cameraIcon.className = 'fas fa-video';
                    if (cameraText) cameraText.textContent = 'On';
                } else {
                    cameraToggle.className = 'device-toggle off';
                    if (cameraIcon) cameraIcon.className = 'fas fa-video-slash';
                    if (cameraText) cameraText.textContent = 'Off';
                }
            }
            
            if (previewCameraIcon) {
                previewCameraIcon.className = isCameraOn ? 'fas fa-video camera-on' : 'fas fa-video-slash camera-off';
            }
            
            if (previewCameraText) {
                previewCameraText.textContent = isCameraOn ? 'Camera On' : 'Camera Off';
            }
        }

        function togglePrejoinMic() {
            isMicOn = !isMicOn;
            
            if (localStream) {
                localStream.getAudioTracks().forEach(track => track.enabled = isMicOn);
            }
            
            updatePrejoinUI();
        }

        function togglePrejoinCamera() {
            isCameraOn = !isCameraOn;
            
            if (localStream) {
                localStream.getVideoTracks().forEach(track => track.enabled = isCameraOn);
            }
            
            const video = document.getElementById('prejoin-video');
            const placeholder = document.getElementById('preview-placeholder');
            
            if (!isCameraOn) {
                if (video) video.style.display = 'none';
                if (placeholder) placeholder.style.display = 'flex';
            } else {
                if (video) video.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';
            }
            
            updatePrejoinUI();
        }

        async function changeAudioInput() {
            const deviceId = document.getElementById('mic-select')?.value;
            if (!deviceId) return;
            
            if (localStream) {
                const tracks = localStream.getAudioTracks();
                tracks.forEach(track => track.stop());
            }
            
            try {
                const constraints = {
                    audio: deviceId !== 'default' ? { deviceId: { exact: deviceId } } : true,
                    video: localStream ? localStream.getVideoTracks()[0]?.getSettings() : false
                };
                
                const newStream = await navigator.mediaDevices.getUserMedia(constraints);
                
                if (localStream) {
                    const videoTrack = localStream.getVideoTracks()[0];
                    if (videoTrack) {
                        newStream.addTrack(videoTrack);
                    }
                }
                
                localStream = newStream;
                const video = document.getElementById('prejoin-video');
                if (video) {
                    video.srcObject = localStream;
                }
                
            } catch (error) {
                console.error('Error changing audio input:', error);
                showNotification('Failed to change audio device', 'error');
            }
        }

        async function changeVideoInput() {
            const deviceId = document.getElementById('camera-select')?.value;
            if (!deviceId) return;
            
            if (localStream) {
                const tracks = localStream.getVideoTracks();
                tracks.forEach(track => track.stop());
            }
            
            try {
                const constraints = {
                    video: deviceId !== 'default' ? { deviceId: { exact: deviceId } } : true,
                    audio: localStream ? localStream.getAudioTracks()[0]?.getSettings() : false
                };
                
                const newStream = await navigator.mediaDevices.getUserMedia(constraints);
                
                if (localStream) {
                    const audioTrack = localStream.getAudioTracks()[0];
                    if (audioTrack) {
                        newStream.addTrack(audioTrack);
                    }
                }
                
                localStream = newStream;
                const video = document.getElementById('prejoin-video');
                if (video) {
                    video.srcObject = localStream;
                }
                
                if (isCameraOn) {
                    if (video) video.style.display = 'block';
                    const placeholder = document.getElementById('preview-placeholder');
                    if (placeholder) placeholder.style.display = 'none';
                }
                
            } catch (error) {
                console.error('Error changing video input:', error);
                showNotification('Failed to change camera device', 'error');
            }
        }

        async function changeAudioOutput() {
            const deviceId = document.getElementById('speaker-select')?.value;
            if (!deviceId) return;
            
            await selectAudioOutputDevice(deviceId);
        }

        async function selectAudioOutputDevice(deviceId) {
            const videoElements = document.querySelectorAll('video');
            videoElements.forEach(video => {
                if (deviceId !== 'default' && video.setSinkId) {
                    video.setSinkId(deviceId).catch(err => {
                        console.error('Error setting audio output:', err);
                    });
                }
            });
            
            showNotification('Audio output switched', 'info');
        }

        function joinMeetingFromPrejoin() {
            if (!currentMeetingRoom.id) return;
            
            // Build URL with device settings
            let url = window.location.origin + '/meeting/meeting_room.php?room=' + encodeURIComponent(currentMeetingRoom.id);
            url += '&mic=' + (isMicOn ? 'on' : 'off');
            url += '&camera=' + (isCameraOn ? 'on' : 'off');
            
            // If meeting is password protected and we have the password, include it
            if (currentMeetingRoom.password) {
                url += '&password=' + encodeURIComponent(currentMeetingRoom.password);
            }
            
            // Stop local stream
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            
            // Navigate to meeting room
            window.location.href = url;
        }

        // Function to copy meeting invite
        function copyMeetingInvite(roomId, password) {
            const baseUrl = window.location.origin;
            const roomLink = baseUrl + '/meeting/meeting_room.php?room=' + roomId;
            
            let inviteText = `Join my SkyMeet meeting\n`;
            inviteText += `Room ID: ${roomId}\n`;
            inviteText += `Link: ${roomLink}\n`;
            
            if (password) {
                inviteText += `Password: ${password}\n`;
            }
            
            inviteText += `\nJoin using SkyMeet app or web browser.`;
            
            copyToClipboard(inviteText);
            showNotification('Meeting invite copied to clipboard!', 'success');
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
                    setTimeout(() => {
                        if (notif.parentNode) {
                            notif.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }

        // Delete meeting functions
        let meetingToDelete = null;

        function confirmDelete(meetingId) {
            meetingToDelete = meetingId;
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.add('active');
            }
        }

        function closeDeleteModal() {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.remove('active');
            }
            meetingToDelete = null;
        }

        // Show meetings for a specific day
        function showDayMeetings(day, dateStr) {
            console.log('Day clicked:', day, dateStr);
        }

        // Check for room ID in URL and show room info
        <?php if (isset($_GET['room']) && isset($_GET['success'])): ?>
        window.addEventListener('load', function() {
            setTimeout(() => {
                const meetingTitle = '<?php echo isset($_GET['title']) ? htmlspecialchars(addslashes($_GET['title'])) : 'Meeting Room'; ?>';
                const meetingPassword = '<?php echo isset($_GET['password']) ? htmlspecialchars(addslashes($_GET['password'])) : ''; ?>';
                
                showRoomInfoModal(
                    '<?php echo isset($_GET['id']) ? intval($_GET['id']) : 'null'; ?>',
                    '<?php echo htmlspecialchars($_GET['room']); ?>',
                    meetingPassword,
                    meetingTitle
                );
            }, 500);
        });
        <?php endif; ?>

        // Auto-hide success message
        window.addEventListener('load', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        if (successMessage.parentNode) {
                            successMessage.remove();
                        }
                    }, 300);
                }, 3000);
            }
        });

        // Make functions globally available
        window.showMeetingModal = showMeetingModal;
        window.hideMeetingModal = hideMeetingModal;
        window.showMeetingDetails = showMeetingDetails;
        window.closeMeetingDetail = closeMeetingDetail;
        window.joinFromDetail = joinFromDetail;
        window.editFromDetail = editFromDetail;
        window.deleteFromDetail = deleteFromDetail;
        window.togglePassword = togglePassword;
        window.checkPasswordStrength = checkPasswordStrength;
        window.confirmDelete = confirmDelete;
        window.closeDeleteModal = closeDeleteModal;
        window.closeRoomInfoModal = closeRoomInfoModal;
        window.copyMeetingLink = copyMeetingLink;
        window.goToMeetingRoom = goToMeetingRoom;
        window.copyToClipboard = copyToClipboard;
        window.closePrejoinModal = closePrejoinModal;
        window.showDayMeetings = showDayMeetings;
        window.copyMeetingInvite = copyMeetingInvite;
        window.showPrejoinModal = showPrejoinModal;
        window.showNotification = showNotification;
    </script>
</body>
</html>