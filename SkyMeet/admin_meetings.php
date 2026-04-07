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

// Get search query
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
// Get sort order - NEW: Added sort functionality from meetings.php
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // Default to newest

// Function to get meeting status
function getMeetingStatus($meeting_date, $start_time, $end_time) {
    $now = time();
    
    // Combine date and time strings
    $meeting_start_str = $meeting_date . ' ' . $start_time;
    $meeting_end_str = $meeting_date . ' ' . $end_time;
    
    // Parse the datetime strings
    $meeting_start = strtotime($meeting_start_str);
    $meeting_end = strtotime($meeting_end_str);
    
    if ($meeting_start === false) {
        $meeting_start = strtotime($meeting_date . ' ' . substr($start_time, 0, 8));
    }
    
    if ($meeting_end === false) {
        $meeting_end = strtotime($meeting_date . ' ' . substr($end_time, 0, 8));
    }
    
    if ($now < $meeting_start) {
        return 'upcoming';
    } elseif ($now >= $meeting_start && $now <= $meeting_end) {
        return 'ongoing';
    } else {
        return 'completed';
    }
}

function formatTime($time) {
    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
        return date('g:i A', strtotime($time));
    }
    return date('g:i A', strtotime($time));
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Check if meetings table exists
$meetings_table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'meetings'");
if ($check_table && $check_table->num_rows > 0) {
    $meetings_table_exists = true;
}

// Fetch months with meetings for filter dropdown
$available_months = [];
if ($meetings_table_exists) {
    $month_sql = "SELECT DISTINCT DATE_FORMAT(meeting_date, '%Y-%m') as month_value,
                         DATE_FORMAT(meeting_date, '%M %Y') as month_name
                  FROM meetings 
                  ORDER BY meeting_date DESC";
    
    $month_result = $conn->query($month_sql);
    if ($month_result) {
        $available_months = $month_result->fetch_all(MYSQLI_ASSOC);
    }
}

// REPORT GENERATION 
// Check if report should be generated
$generate_report = isset($_GET['generate_report']) && $_GET['generate_report'] == '1';
$report_period = isset($_GET['report_period']) ? $_GET['report_period'] : 'monthly';
$report_data = [];
$report_labels = [];
$total_report_meetings = 0;
$average_meetings = 0;

if ($generate_report && $meetings_table_exists) {
    if ($report_period == 'monthly') {
        // Current month daily data
        $current_year = date('Y');
        $current_month = date('m');
        
        $report_sql = "SELECT 
                        DAY(meeting_date) as day,
                        COUNT(*) as count
                      FROM meetings 
                      WHERE YEAR(meeting_date) = ? 
                        AND MONTH(meeting_date) = ?
                      GROUP BY DAY(meeting_date)
                      ORDER BY day";
        
        $report_stmt = $conn->prepare($report_sql);
        $report_stmt->bind_param("ii", $current_year, $current_month);
        $report_stmt->execute();
        $report_result = $report_stmt->get_result();
        
        // Initialize array with zeros for all days
        $days_in_month = date('t');
        $daily_counts = array_fill(1, $days_in_month, 0);
        
        while ($row = $report_result->fetch_assoc()) {
            $daily_counts[$row['day']] = $row['count'];
        }
        
        // Prepare data for chart
        foreach ($daily_counts as $day => $count) {
            $report_labels[] = "Day $day";
            $report_data[] = $count;
        }
        
        $report_title = "Daily Meetings - " . date('F Y');
        $report_stmt->close();
        
    } elseif ($report_period == '3months') {
        // Last 3 months monthly data
        $report_sql = "SELECT 
                        DATE_FORMAT(meeting_date, '%Y-%m') as month,
                        DATE_FORMAT(meeting_date, '%M %Y') as month_name,
                        COUNT(*) as count
                      FROM meetings 
                      WHERE meeting_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                      GROUP BY DATE_FORMAT(meeting_date, '%Y-%m')
                      ORDER BY month";
        
        $report_result = $conn->query($report_sql);
        
        while ($row = $report_result->fetch_assoc()) {
            $report_labels[] = $row['month_name'];
            $report_data[] = $row['count'];
        }
        
        $report_title = "Monthly Meetings - Last 3 Months";
        
    } elseif ($report_period == '6months') {
        // Last 6 months monthly data
        $report_sql = "SELECT 
                        DATE_FORMAT(meeting_date, '%Y-%m') as month,
                        DATE_FORMAT(meeting_date, '%M %Y') as month_name,
                        COUNT(*) as count
                      FROM meetings 
                      WHERE meeting_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                      GROUP BY DATE_FORMAT(meeting_date, '%Y-%m')
                      ORDER BY month";
        
        $report_result = $conn->query($report_sql);
        
        while ($row = $report_result->fetch_assoc()) {
            $report_labels[] = $row['month_name'];
            $report_data[] = $row['count'];
        }
        
        $report_title = "Monthly Meetings - Last 6 Months";
        
    } elseif ($report_period == 'yearly') {
        // Yearly data - CHANGED FROM 5 YEARS TO 1 YEAR
        $report_sql = "SELECT 
                        YEAR(meeting_date) as year,
                        COUNT(*) as count
                      FROM meetings 
                      WHERE meeting_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                      GROUP BY YEAR(meeting_date)
                      ORDER BY year";
        
        $report_result = $conn->query($report_sql);
        
        while ($row = $report_result->fetch_assoc()) {
            $report_labels[] = $row['year'];
            $report_data[] = $row['count'];
        }
        
        $report_title = "Yearly Meetings - Last 1 Year";
    }

    // Calculate totals
    $total_report_meetings = array_sum($report_data);
    $average_meetings = $total_report_meetings > 0 ? round($total_report_meetings / count($report_data), 1) : 0;
}

// FETCH MEETINGS 
$all_meetings = [];
$total_meetings = 0;
$upcoming_count = 0;
$ongoing_count = 0;
$completed_count = 0;

if ($meetings_table_exists) {
    $sql = "SELECT m.*, 
                   u.username as host_username,
                   u.email as host_email,
                   m.is_password_protected
            FROM meetings m
            LEFT JOIN users u ON m.host_id = u.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Add search filter
    if (!empty($search_query)) {
        $sql .= " AND (m.title LIKE ? OR m.description LIKE ? OR u.username LIKE ?)";
        $search_term = "%$search_query%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }
    
    // NEW: Add sort order based on selection
    if ($sort_order === 'oldest') {
        $sql .= " ORDER BY m.meeting_date ASC, m.start_time ASC";
    } else { // newest (default)
        $sql .= " ORDER BY m.meeting_date DESC, m.start_time DESC";
    }
    
    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $all_meetings = $result->fetch_all(MYSQLI_ASSOC);
            $total_meetings = count($all_meetings);
            
            // Count statuses
            foreach ($all_meetings as $meeting) {
                $status = getMeetingStatus($meeting['meeting_date'], $meeting['start_time'], $meeting['end_time']);
                if ($status == 'upcoming') $upcoming_count++;
                if ($status == 'ongoing') $ongoing_count++;
                if ($status == 'completed') $completed_count++;
            }
        }
        $stmt->close();
    }
}

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
        // Build redirect URL with preserved search and sort
        $redirect_url = "admin_meetings.php?success=Meeting+deleted+successfully";
        if (!empty($search_query)) {
            $redirect_url .= "&search=" . urlencode($search_query);
        }
        if (!empty($sort_order)) {
            $redirect_url .= "&sort=" . urlencode($sort_order);
        }
        header("Location: " . $redirect_url);
        exit();
    }
    $delete_stmt->close();
}

// Get total meetings count for badge
$total_meetings_badge = 0;
$badge_sql = "SELECT COUNT(*) as total FROM meetings";
if ($badge_result = $conn->query($badge_sql)) {
    $badge_row = $badge_result->fetch_assoc();
    $total_meetings_badge = $badge_row['total'] ?? 0;
}

// Get upcoming meetings count for badge (for admin's own meetings)
$upcoming_count_sql = "SELECT COUNT(*) as upcoming_count 
                      FROM meetings 
                      WHERE host_id = ? 
                      AND ((meeting_date > CURDATE()) 
                          OR (meeting_date = CURDATE() AND end_time > CURTIME()))";
$upcoming_count_stmt = $conn->prepare($upcoming_count_sql);
if ($upcoming_count_stmt) {
    $upcoming_count_stmt->bind_param("i", $user_id);
    $upcoming_count_stmt->execute();
    $upcoming_count_result = $upcoming_count_stmt->get_result();
    $upcoming_count_data = $upcoming_count_result->fetch_assoc();
    $upcoming_meetings_count = $upcoming_count_data['upcoming_count'] ?? 0;
    $upcoming_count_stmt->close();
} else {
    $upcoming_meetings_count = 0;
}

// Get host names for display
$host_names = [];
$host_sql = "SELECT DISTINCT m.host_id, u.username 
             FROM meetings m 
             LEFT JOIN users u ON m.host_id = u.id 
             WHERE m.host_id IS NOT NULL";
$host_result = $conn->query($host_sql);
if ($host_result) {
    while ($row = $host_result->fetch_assoc()) {
        $host_names[$row['host_id']] = $row['username'];
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
    <title>Admin Meetings - All Meeting Logs - SkyMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Reset */
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

        /* Notification badge */
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

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #667eea;
            font-weight: 600;
            font-size: 16px;
        }

        .filter-label i {
            font-size: 18px;
        }

        .search-container {
            flex: 1;
            min-width: 300px;
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e0e0ff;
            border-radius: 10px;
            font-size: 15px;
            color: #333;
            background: white;
            transition: all 0.3s;
            outline: none;
        }

        .search-input:hover {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .search-btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .clear-search-btn {
            padding: 10px 20px;
            background: #f0f0f0;
            color: #666;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .clear-search-btn:hover {
            background: #e0e0e0;
            color: #333;
            transform: translateY(-2px);
        }

        /* NEW: Sort Controls - From meetings.php */
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }
        
        .sort-label {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 14px;
        }
        
        .sort-label i {
            color: #667eea;
        }
        
        .sort-buttons {
            display: flex;
            gap: 5px;
        }
        
        .sort-btn {
            padding: 8px 16px;
            border: 2px solid #e0e0ff;
            border-radius: 20px;
            background: white;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .sort-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }
        
        .sort-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            color: white;
        }
        
        .sort-btn i {
            font-size: 12px;
        }

        .filter-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(102, 126, 234, 0.2));
            color: #667eea;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
            border-top: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.total {
            border-color: #667eea;
        }

        .stat-card.upcoming {
            border-color: #10b981;
        }

        .stat-card.ongoing {
            border-color: #f59e0b;
        }

        .stat-card.completed {
            border-color: #6b7280;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .stat-card.upcoming .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-card.ongoing .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-card.completed .stat-icon {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card.total .stat-info h3 {
            color: #667eea;
        }

        .stat-card.upcoming .stat-info h3 {
            color: #10b981;
        }

        .stat-card.ongoing .stat-info h3 {
            color: #f59e0b;
        }

        .stat-card.completed .stat-info h3 {
            color: #6b7280;
        }

        .stat-info p {
            color: #666;
            font-size: 14px;
        }

        /* Meetings Container */
        .meetings-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
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

        .row-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(102, 126, 234, 0.2));
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.3);
        }

        /* Scrollable Table Container */
        .table-container {
            position: relative;
            border-radius: 12px;
            max-height: <?php echo $total_meetings > 6 ? '500px' : 'auto'; ?>;
            overflow-y: <?php echo $total_meetings > 6 ? 'auto' : 'visible'; ?>;
            border: <?php echo $total_meetings > 6 ? '1px solid #e0e0ff' : 'none'; ?>;
            transition: all 0.3s ease;
        }

        /* Custom Scrollbar Styling */
        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
            transition: background 0.3s;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .table-container::-webkit-scrollbar-corner {
            background: #f1f1f1;
        }

        .meetings-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .meetings-table th {
            background: #f8f9ff;
            color: #667eea;
            font-weight: 600;
            padding: 18px 15px;
            text-align: left;
            border-bottom: 2px solid #e0e0ff;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: <?php echo $total_meetings > 6 ? 'sticky' : 'static'; ?>;
            top: 0;
            z-index: 10;
            background: #f8f9ff;
            box-shadow: <?php echo $total_meetings > 6 ? '0 2px 5px rgba(0,0,0,0.1)' : 'none'; ?>;
        }

        .meetings-table td {
            padding: 20px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            transition: background 0.3s;
        }

        .meetings-table tbody tr:hover {
            background: #f8f9ff;
        }

        .meeting-title-cell {
            max-width: 300px;
            min-width: 200px;
        }

        .meeting-title {
            font-weight: 600;
            color: #333;
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meeting-title i {
            color: #667eea;
            font-size: 14px;
        }

        .password-lock {
            color: #f59e0b;
            font-size: 14px;
            margin-left: 5px;
        }

        .meeting-description {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }

        .host-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(102, 126, 234, 0.2));
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.3);
            white-space: nowrap;
        }
        
        .host-badge i {
            font-size: 12px;
        }

        .meeting-date {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            margin-bottom: 6px;
            white-space: nowrap;
        }

        .meeting-time {
            color: #666;
            font-size: 13px;
            background: #f0f0ff;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .status-upcoming {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: #10b981;
        }

        .status-ongoing {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: #d97706;
        }

        .status-completed {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.1), rgba(107, 114, 128, 0.2));
            color: #6b7280;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            min-width: 100px;
        }

        .action-btn {
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            text-decoration: none;
            white-space: nowrap;
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

        /* Scroll Indicator */
        .scroll-indicator {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(102, 126, 234, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            display: <?php echo $total_meetings > 6 ? 'block' : 'none'; ?>;
            z-index: 5;
            pointer-events: none;
            animation: fadeInOut 2s ease;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            20% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; }
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

        /* Report Section - Redesigned */
        .report-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .report-header h2 {
            font-size: 22px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-header h2 i {
            color: #667eea;
        }

        .report-controls {
            display: flex;
            gap: 12px;
        }

        /* Report Select Styling */
        .report-select {
            padding: 12px 25px;
            border: 2px solid #e0e0ff;
            border-radius: 10px;
            font-size: 15px;
            color: #333;
            background: white;
            cursor: pointer;
            outline: none;
            min-width: 180px;
            transition: all 0.3s;
        }

        .report-select:hover {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .report-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .btn-generate {
            padding: 12px 25px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            white-space: nowrap;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .btn-generate i {
            font-size: 16px;
        }

        /* Clear Report Button - Matching filter section style */
        .btn-clear-report {
            padding: 12px 25px;
            background: #f0f0f0;
            color: #666;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
            white-space: nowrap;
            border: 2px solid transparent;
        }

        .btn-clear-report:hover {
            background: #e0e0e0;
            color: #333;
            transform: translateY(-2px);
            border-color: #d0d0d0;
        }

        .btn-clear-report i {
            font-size: 14px;
        }

        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            opacity: <?php echo $generate_report ? '1' : '0.5'; ?>;
            transition: opacity 0.3s ease;
        }

        .report-stat-card {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid #e0e0ff;
            transition: all 0.3s;
        }

        .report-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.15);
        }

        .report-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .report-stat-info h4 {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .report-stat-info span {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .report-stat-info .stat-period {
            font-size: 13px;
            color: #888;
            margin-top: 2px;
        }

        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 12px;
            border: 1px solid #e0e0ff;
            display: <?php echo $generate_report ? 'block' : 'none'; ?>;
        }

        .no-data-message {
            text-align: center;
            padding: 60px;
            color: #666;
            background: #f8f9ff;
            border-radius: 12px;
            border: 2px dashed #e0e0ff;
            margin-top: 20px;
            display: <?php echo $generate_report && empty($report_data) ? 'block' : 'none'; ?>;
        }

        .no-data-message i {
            font-size: 50px;
            color: #667eea;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .no-data-message h3 {
            font-size: 20px;
            color: #333;
            margin-bottom: 10px;
        }

        .no-data-message p {
            color: #666;
        }

        /* Success Message */
        .success-message {
            position: fixed;
            top: 30px;
            right: 30px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.4);
            z-index: 2000;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 400px;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
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
            z-index: 2001;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Left Sidebar -->
        <div class="sidebar">
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
                <a href="admin_meetings.php" class="nav-item active">
                    <i class="fas fa-video"></i> User's Meetings
                    <?php if ($total_meetings_badge > 0): ?>
                        <span class="nav-badge"><?php echo $total_meetings_badge; ?></span>
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
                        <i class="fas fa-video"></i> 
                        Meeting Logs From Users
                    </h1>
                    <p>View all meetings created by users and generate a report of meetings. Total: <?php echo $total_meetings_badge; ?> meetings</p>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_meetings; ?></h3>
                        <p>User's Total Meetings</p>
                    </div>
                </div>
                
                <div class="stat-card upcoming">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $upcoming_count; ?></h3>
                        <p>User's Upcoming Meetings</p>
                    </div>
                </div>
                
                <div class="stat-card ongoing">
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $ongoing_count; ?></h3>
                        <p>User's Ongoing Meetings</p>
                    </div>
                </div>
                
                <div class="stat-card completed">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $completed_count; ?></h3>
                        <p>User's Completed Meetings</p>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-label">
                    <i class="fas fa-search"></i>
                    <span>Search:</span>
                </div>
                
                <form method="GET" action="admin_meetings.php" id="searchForm" class="search-container">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search by host, meeting title, or description..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    
                    <!-- NEW: Preserve sort order in search -->
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_order); ?>">
                    
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if (!empty($search_query)): ?>
                        <a href="admin_meetings.php?sort=<?php echo urlencode($sort_order); ?>" class="clear-search-btn">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
                
                <!-- NEW: Sort Controls - From meetings.php -->
                <div class="sort-controls">
                    <div class="sort-label">
                        <i class="fas fa-sort-amount-down"></i>
                        <span>Sort by:</span>
                    </div>
                    <div class="sort-buttons">
                        <a href="?<?php 
                            $params = [];
                            if (!empty($search_query)) $params[] = 'search=' . urlencode($search_query);
                            $params[] = 'sort=newest';
                            echo implode('&', $params);
                        ?>" class="sort-btn <?php echo $sort_order == 'newest' ? 'active' : ''; ?>">
                            <i class="fas fa-arrow-down"></i> Newest
                        </a>
                        <a href="?<?php 
                            $params = [];
                            if (!empty($search_query)) $params[] = 'search=' . urlencode($search_query);
                            $params[] = 'sort=oldest';
                            echo implode('&', $params);
                        ?>" class="sort-btn <?php echo $sort_order == 'oldest' ? 'active' : ''; ?>">
                            <i class="fas fa-arrow-up"></i> Oldest
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($search_query)): ?>
                    <div class="filter-info">
                        <i class="fas fa-filter"></i>
                        Searching: "<?php echo htmlspecialchars($search_query); ?>"
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Meetings Table -->
            <div class="meetings-container">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-list"></i> 
                        <?php if (!empty($search_query)): ?>
                            Search Results
                        <?php else: ?>
                            All Meeting Logs
                        <?php endif; ?>
                        <?php if ($total_meetings > 0): ?>
                            <span class="row-count-badge"><?php echo $total_meetings; ?> meeting<?php echo $total_meetings > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </h2>
                    
                    <!-- NEW: Sort indicator -->
                    <div class="sort-info" style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: #666; font-size: 14px;">
                            <i class="fas fa-sort"></i> 
                            Sorted by: <strong><?php echo $sort_order == 'newest' ? 'Newest first' : 'Oldest first'; ?></strong>
                        </span>
                    </div>
                </div>

                <?php if (empty($all_meetings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Meetings Found</h3>
                        <p>
                            <?php if (!empty($search_query)): ?>
                                No meetings match your information "<?php echo htmlspecialchars($search_query); ?>".
                                <br>
                                <a href="admin_meetings.php" style="color: #667eea; text-decoration: none;">Clear search</a> to see all meetings.
                            <?php else: ?>
                                No meetings have been created yet. Users can create meetings to get started!
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Scrollable Table Container with conditional scrolling -->
                    <div class="table-container" id="tableContainer">
                        <?php if ($total_meetings > 6): ?>
                            <div class="scroll-indicator" id="scrollIndicator">
                                <i class="fas fa-arrow-down"></i> Scroll to see more meetings
                            </div>
                        <?php endif; ?>
                        
                        <table class="meetings-table">
                            <thead>
                                <tr>
                                    <th>Meeting</th>
                                    <th>Host</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_meetings as $meeting): 
                                    $status = getMeetingStatus($meeting['meeting_date'], $meeting['start_time'], $meeting['end_time']);
                                    $host_name = $meeting['host_username'] ?? 'Unknown User';
                                    $host_email = $meeting['host_email'] ?? '';
                                    $is_protected = isset($meeting['is_password_protected']) && $meeting['is_password_protected'] == 1;
                                ?>
                                <tr>
                                    <td class="meeting-title-cell">
                                        <div class="meeting-title">
                                            <i class="fas fa-video"></i>
                                            <?php echo htmlspecialchars($meeting['title']); ?>
                                            <?php if ($is_protected): ?>
                                                <i class="fas fa-lock password-lock" title="Password Protected Meeting"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="meeting-description">
                                            <?php echo htmlspecialchars($meeting['description'] ?: 'No description'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="host-badge">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($host_name); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #888; margin-top: 5px;">
                                            <?php echo htmlspecialchars($host_email); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="meeting-date">
                                            <?php echo formatDate($meeting['meeting_date']); ?>
                                        </div>
                                        <div class="meeting-time">
                                            <?php echo formatTime($meeting['start_time']); ?> - <?php echo formatTime($meeting['end_time']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <i class="fas fa-circle" style="font-size: 8px;"></i>
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="confirmDelete(<?php echo $meeting['id']; ?>, '<?php echo htmlspecialchars(addslashes($meeting['title'])); ?>', '<?php echo htmlspecialchars(addslashes($host_name)); ?>')" 
                                                    class="action-btn btn-delete" title="Delete Meeting">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_meetings > 6): ?>
                        <div style="text-align: center; margin-top: 15px; color: #667eea; font-size: 14px;">
                            <i class="fas fa-arrows-alt-v"></i> Scroll vertically to see all <?php echo $total_meetings; ?> meetings
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Report Section - Redesigned -->
            <div class="report-section">
                <div class="report-header">
                    <h2>
                        <i class="fas fa-chart-bar"></i>
                        Meeting Reports
                    </h2>
                    <div class="report-controls">
                        <form method="GET" action="admin_meetings.php" id="reportForm" style="display: flex; gap: 12px;">
                            <?php if (!empty($search_query)): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                            <?php endif; ?>
                            <!-- NEW: Preserve sort order in report form -->
                            <?php if (!empty($sort_order)): ?>
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_order); ?>">
                            <?php endif; ?>
                            <input type="hidden" name="generate_report" value="1" id="generateReport">
                            
                            <select name="report_period" class="report-select">
                                <option value="monthly" <?php echo $report_period == 'monthly' ? 'selected' : ''; ?>>This Month</option>
                                <option value="3months" <?php echo $report_period == '3months' ? 'selected' : ''; ?>>Last 3 Months</option>
                                <option value="6months" <?php echo $report_period == '6months' ? 'selected' : ''; ?>>Last 6 Months</option>
                                <option value="yearly" <?php echo $report_period == 'yearly' ? 'selected' : ''; ?>>Last 1 Year</option>
                            </select>
                            
                            <button type="submit" class="btn-generate">
                                <i class="fas fa-chart-line"></i> Generate
                            </button>
                            
                            <?php if ($generate_report): ?>
                                <a href="admin_meetings.php<?php 
                                    $params = [];
                                    if (!empty($search_query)) $params[] = 'search=' . urlencode($search_query);
                                    if (!empty($sort_order)) $params[] = 'sort=' . urlencode($sort_order);
                                    echo !empty($params) ? '?' . implode('&', $params) : '';
                                ?>" class="btn-clear-report">
                                    <i class="fas fa-times"></i> Clear Report
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <?php if ($generate_report): ?>
                    <?php if (!empty($report_data)): ?>
                        <div class="report-stats">
                            <div class="report-stat-card">
                                <div class="report-stat-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="report-stat-info">
                                    <h4>Total Meetings</h4>
                                    <span><?php echo $total_report_meetings; ?></span>
                                    <div class="stat-period">
                                        <?php 
                                            if ($report_period == 'monthly') echo 'This month';
                                            elseif ($report_period == '3months') echo 'Last 3 months';
                                            elseif ($report_period == '6months') echo 'Last 6 months';
                                            else echo 'Last 1 year';
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="report-stat-card">
                                <div class="report-stat-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="report-stat-info">
                                    <h4>Average</h4>
                                    <span><?php echo $average_meetings; ?></span>
                                    <div class="stat-period">
                                        per <?php echo $report_period == 'monthly' ? 'day' : 'month'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="report-stat-card">
                                <div class="report-stat-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="report-stat-info">
                                    <h4>Periods</h4>
                                    <span><?php echo count($report_data); ?></span>
                                    <div class="stat-period">
                                        <?php 
                                            if ($report_period == 'monthly') echo 'days';
                                            elseif ($report_period == 'yearly') echo 'years';
                                            else echo 'months';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="chart-container">
                            <canvas id="meetingChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-chart-pie"></i>
                            <h3>No Data Available</h3>
                            <p>No meetings found for the selected period.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-data-message" style="display: block;">
                        <i class="fas fa-chart-line"></i>
                        <h3>Generate a Report</h3>
                        <p>Select a period and click "Generate" to view meeting statistics.</p>
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
                <h3><i class="fas fa-exclamation-triangle"></i> Delete Meeting</h3>
            </div>
            <div class="modal-body">
                <p id="deleteModalMessage">Are you sure you want to delete this meeting? This action cannot be undone.</p>
                <p style="margin-top: 10px; font-size: 14px; color: #999;">All participant data, chat history, and meeting recordings will be permanently removed.</p>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn-confirm" id="confirmDeleteBtn">Delete Meeting</button>
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
        // CHART INITIALIZATION 
        <?php if ($generate_report && !empty($report_data)): ?>
        const ctx = document.getElementById('meetingChart').getContext('2d');
        const meetingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($report_labels); ?>,
                datasets: [{
                    label: 'Number of Meetings',
                    data: <?php echo json_encode($report_data); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    hoverBackgroundColor: 'rgba(102, 126, 234, 0.9)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#f1f5f9',
                        bodyColor: '#f1f5f9',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return `Meetings: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                if (Number.isInteger(value)) {
                                    return value;
                                }
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
        <?php endif; ?>

        // Delete state
        let meetingToDelete = null;

        // Delete confirmation
        function confirmDelete(meetingId, meetingTitle, hostName) {
            meetingToDelete = meetingId;
            const message = `Are you sure you want to delete "${meetingTitle}" created by ${hostName}? This action cannot be undone.`;
            document.getElementById('deleteModalMessage').textContent = message;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            meetingToDelete = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (meetingToDelete) {
                const currentUrl = new URL(window.location.href);
                const searchParam = currentUrl.searchParams.get('search');
                const sortParam = currentUrl.searchParams.get('sort');
                let deleteUrl = 'admin_meetings.php?delete=' + meetingToDelete;
                if (searchParam) deleteUrl += '&search=' + encodeURIComponent(searchParam);
                if (sortParam) deleteUrl += '&sort=' + encodeURIComponent(sortParam);
                window.location.href = deleteUrl;
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeDeleteModal();
            }
        });

        // Auto-hide success message
        const successMessage = document.getElementById('successMessage');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.remove();
                }, 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
            }
        });

        // Auto-hide scroll indicator
        const scrollIndicator = document.getElementById('scrollIndicator');
        if (scrollIndicator) {
            setTimeout(() => {
                scrollIndicator.style.opacity = '0';
                setTimeout(() => {
                    if (scrollIndicator) scrollIndicator.remove();
                }, 2000);
            }, 3000);
        }
    </script>
</body>
</html>