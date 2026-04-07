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

// Get current user with profile photo
$user = getCurrentUser($conn, $user_id);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get total users count for sidebar badge
$users_count_sql = "SELECT COUNT(*) as total FROM users WHERE role != 'Admin'";
$users_count_result = $conn->query($users_count_sql);
$total_users = 0;
if ($users_count_result) {
    $users_data = $users_count_result->fetch_assoc();
    $total_users = $users_data['total'] ?? 0;
}

// Check if meetings table exists
$meetings_table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'meetings'");
if ($check_table && $check_table->num_rows > 0) {
    $meetings_table_exists = true;
}

// Get meeting statistics 
$stats = [
    'total_meetings' => 0,
    'today_meetings' => 0,
    'upcoming_meetings' => 0,
    'total_reports' => 0,
    'pending_reports' => 0,
    'reviewed_reports' => 0,
    'resolved_reports' => 0,
    'unique_reporters' => 0
];

if ($meetings_table_exists) {
    // Total meetings created by ALL users (admin can see all)
    $total_sql = "SELECT COUNT(*) as total FROM meetings";
    $total_result = $conn->query($total_sql);
    if ($total_result) {
        $total_data = $total_result->fetch_assoc();
        $stats['total_meetings'] = $total_data['total'] ?: 0;
    }
    
    // Today's meetings created by ALL users
    $today_sql = "SELECT COUNT(*) as today FROM meetings WHERE DATE(meeting_date) = CURDATE()";
    $today_result = $conn->query($today_sql);
    if ($today_result) {
        $today_data = $today_result->fetch_assoc();
        $stats['today_meetings'] = $today_data['today'] ?: 0;
    }
    
    // Upcoming meetings scheduled by ALL users for future dates
    $upcoming_sql = "SELECT COUNT(*) as upcoming FROM meetings WHERE meeting_date > CURDATE()";
    $upcoming_result = $conn->query($upcoming_sql);
    if ($upcoming_result) {
        $upcoming_data = $upcoming_result->fetch_assoc();
        $stats['upcoming_meetings'] = $upcoming_data['upcoming'] ?: 0;
    }
}

// Get upcoming meetings count (for sidebar badge) - This stays for admin's own meetings
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

// Check if problem_reports table exists and get report statistics
$reports_table_exists = false;
$check_reports_table = $conn->query("SHOW TABLES LIKE 'problem_reports'");
if ($check_reports_table && $check_reports_table->num_rows > 0) {
    $reports_table_exists = true;
    
    // Get ALL report statistics (admin can see all reports)
    $reports_sql = "SELECT 
        COUNT(*) as total_reports,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
        SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_reports,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports,
        COUNT(DISTINCT user_id) as unique_reporters
        FROM problem_reports";
    $reports_result = $conn->query($reports_sql);
    if ($reports_result) {
        $report_stats = $reports_result->fetch_assoc();
        if ($report_stats) {
            $stats['total_reports'] = $report_stats['total_reports'] ?: 0;
            $stats['pending_reports'] = $report_stats['pending_reports'] ?: 0;
            $stats['reviewed_reports'] = $report_stats['reviewed_reports'] ?: 0;
            $stats['resolved_reports'] = $report_stats['resolved_reports'] ?: 0;
            $stats['unique_reporters'] = $report_stats['unique_reporters'] ?: 0;
        }
    }
}

// Get ALL pending reports for dashboard display (for scrolling)
$pending_reports = [];
if ($reports_table_exists) {
    $pending_reports_sql = "SELECT r.*, u.username, u.email as user_email, u.profile_photo 
                           FROM problem_reports r 
                           JOIN users u ON r.user_id = u.id 
                           WHERE r.status = 'pending'
                           ORDER BY r.created_at DESC";
    $pending_reports_result = $conn->query($pending_reports_sql);
    if ($pending_reports_result) {
        $pending_reports = $pending_reports_result->fetch_all(MYSQLI_ASSOC);
    }
}

// Show ALL pending reports in the scrollable container
$display_reports = $pending_reports; // Show ALL reports
$total_pending = count($pending_reports);

// Handle report status update from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update_report'])) {
    $report_id = intval($_POST['report_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes'] ?? '');
    
    if (!empty($admin_notes)) {
        // If there are admin notes, append them with timestamp
        $notes_with_prefix = "\n\nQuick Update (" . date('Y-m-d H:i') . "): " . $admin_notes;
        $update_sql = "UPDATE problem_reports SET status = ?, admin_notes = CONCAT(IFNULL(admin_notes, ''), ?) WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $new_status, $notes_with_prefix, $report_id);
    } else {
        // If no admin notes, just update status
        $update_sql = "UPDATE problem_reports SET status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $report_id);
    }
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Report status 
        ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update report']);
    }
    $update_stmt->close();
    exit();
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

// Function to get status badge class
function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return 'badge-warning';
        case 'reviewed':
            return 'badge-info';
        case 'resolved':
            return 'badge-success';
        default:
            return 'badge-secondary';
    }
}

// Function to get status icon
function getStatusIcon($status) {
    switch($status) {
        case 'pending':
            return 'fa-clock';
        case 'reviewed':
            return 'fa-eye';
        case 'resolved':
            return 'fa-check-circle';
        default:
            return 'fa-question-circle';
    }
}

// Function to get category label
function getCategoryLabel($category) {
    $labels = [
        'bug' => 'Bug/Error',
        'feature' => 'Feature Request',
        'ui' => 'UI Issue',
        'performance' => 'Performance',
        'security' => 'Security',
        'account' => 'Account Issue',
        'billing' => 'Billing/Subscription',
        'other' => 'Other'
    ];
    return $labels[$category] ?? ucfirst($category);
}

// Function to get category icon
function getCategoryIcon($category) {
    switch($category) {
        case 'bug':
            return 'fa-bug';
        case 'feature':
            return 'fa-lightbulb';
        case 'ui':
            return 'fa-paint-brush';
        case 'performance':
            return 'fa-tachometer-alt';
        case 'security':
            return 'fa-shield-alt';
        case 'account':
            return 'fa-user-cog';
        case 'billing':
            return 'fa-credit-card';
        default:
            return 'fa-question-circle';
    }
}

// Function to get time ago
function timeAgo($timestamp) {
    if (!$timestamp) return 'Never';
    
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SkyMeet</title>
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
        }
        
        .user-role {
            display: inline-block;
            background: #fef3c7;
            color: #f59e0b;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 3px;
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

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-content h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .hero-content .slogan {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 20px;
            font-weight: 400;
        }

        .hero-content p {
            font-size: 15px;
            opacity: 0.8;
            line-height: 1.6;
            margin-bottom: 25px;
            max-width: 600px;
        }

        .hero-stats {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .hero-stat {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .hero-stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            backdrop-filter: blur(5px);
        }

        .hero-stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .hero-stat-info p {
            font-size: 13px;
            opacity: 0.8;
            margin: 0;
        }

        /* Stats Grid  */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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

        .stat-card.today {
            border-color: #10b981;
        }

        .stat-card.upcoming {
            border-color: #f59e0b;
        }

        .stat-card.reports {
            border-color: #ef4444;
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

        .stat-card.today .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-card.upcoming .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-card.reports .stat-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card.total .stat-info h3 {
            color: #667eea;
        }

        .stat-card.today .stat-info h3 {
            color: #10b981;
        }

        .stat-card.upcoming .stat-info h3 {
            color: #f59e0b;
        }

        .stat-card.reports .stat-info h3 {
            color: #ef4444;
        }

        .stat-info p {
            color: #666;
            font-size: 14px;
        }
        
        .stat-info small {
            font-size: 12px;
            color: #999;
            display: block;
            margin-top: 5px;
        }

        /* Reports Section - pending reports with scroll */
        .reports-dashboard {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
            overflow: hidden;
        }

        .reports-header {
            padding: 25px 30px;
            border-bottom: 2px solid #f0f2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            background: linear-gradient(to right, #f8f9ff, white);
        }

        .reports-header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .reports-header-left h2 {
            font-size: 24px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reports-header-left h2 i {
            color: #667eea;
            font-size: 28px;
        }

        .report-badge {
            background: #667eea;
            color: white;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
        }

        .report-badge.warning {
            background: #f59e0b;
        }

        .report-stats-mini {
            display: flex;
            gap: 20px;
        }

        .report-stat-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: white;
            border-radius: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .report-stat-mini i {
            font-size: 16px;
        }

        .report-stat-mini .count {
            font-weight: 700;
            font-size: 16px;
        }

        .report-stat-mini .label {
            color: #666;
            font-size: 13px;
        }

        .report-stat-mini.pending i {
            color: #f59e0b;
        }

        .report-stat-mini.reviewed i {
            color: #3b82f6;
        }

        .report-stat-mini.resolved i {
            color: #10b981;
        }

        /* Scrollable reports container */
        .reports-scrollable {
            max-height: 450px;
            overflow-y: auto;
            padding: 20px 30px;
            scrollbar-width: thin;
            scrollbar-color: #667eea #f0f0f0;
        }

        .reports-scrollable::-webkit-scrollbar {
            width: 8px;
        }

        .reports-scrollable::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 10px;
        }

        .reports-scrollable::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
            transition: background 0.3s;
        }

        .reports-scrollable::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .report-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .report-card.pending::before {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
            border-color: transparent;
        }

        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .report-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .report-user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            overflow: hidden;
        }

        .report-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .report-user-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .report-user-info p {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .report-user-info p i {
            font-size: 10px;
            color: #999;
        }

        .report-status-badge {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .badge-warning {
            background: #fef3c7;
            color: #f59e0b;
        }

        .badge-info {
            background: #dbeafe;
            color: #3b82f6;
        }

        .badge-success {
            background: #d1fae5;
            color: #10b981;
        }

        .report-category {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8f9ff;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
            color: #667eea;
            margin-bottom: 15px;
            border: 1px solid #e0e7ff;
        }

        .report-content {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #444;
            line-height: 1.6;
            border-left: 3px solid #667eea;
            max-height: 100px;
            overflow-y: auto;
        }

        .report-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #666;
            flex-wrap: wrap;
        }

        .report-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .report-meta-item i {
            color: #667eea;
        }

        .report-admin-notes {
            background: #fff7ed;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            font-size: 13px;
            border-left: 3px solid #f59e0b;
            display: flex;
            gap: 8px;
            max-height: 80px;
            overflow-y: auto;
        }

        .report-admin-notes i {
            color: #f59e0b;
            margin-top: 2px;
        }

        .report-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .report-action-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 100px;
        }

        .report-action-btn.view {
            background: #f0f5ff;
            color: #667eea;
        }

        .report-action-btn.view:hover {
            background: #667eea;
            color: white;
        }

        .report-action-btn.resolve {
            background: #d1fae5;
            color: #10b981;
        }

        .report-action-btn.resolve:hover {
            background: #10b981;
            color: white;
        }

        .report-action-btn.review {
            background: #dbeafe;
            color: #3b82f6;
        }

        .report-action-btn.review:hover {
            background: #3b82f6;
            color: white;
        }

        /* Empty State */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px 20px;
            background: #f8f9ff;
            border-radius: 16px;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #444;
        }

        .empty-state p {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        /* Remaining Reports Indicator */
        .remaining-reports-indicator {
            text-align: center;
            padding: 15px;
            background: #f8f9ff;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px dashed #667eea;
        }

        .remaining-reports-indicator i {
            color: #667eea;
            margin: 0 8px;
            animation: bounce 2s infinite;
        }

        .remaining-reports-indicator span {
            color: #667eea;
            font-weight: 600;
        }

        /* Quick Info */
        .quick-info {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        /* Toast Notification */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 3000;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .toast-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast-notification.success {
            background: #10b981;
        }

        .toast-notification.error {
            background: #ef4444;
        }

        .toast-notification.info {
            background: #3b82f6;
        }

        /* Scroll indicator for reports */
        .scroll-indicator {
            position: sticky;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(102, 126, 234, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            z-index: 10;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            animation: bounce 2s infinite;
            margin-bottom: 10px;
            cursor: pointer;
            transition: opacity 0.3s;
        }

        .scroll-indicator:hover {
            background: #667eea;
            opacity: 0.9;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0) translateX(-50%);
            }
            40% {
                transform: translateY(-10px) translateX(-50%);
            }
            60% {
                transform: translateY(-5px) translateX(-50%);
            }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-info {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-info {
                grid-template-columns: 1fr;
            }
            
            .hero-content h1 {
                font-size: 28px;
            }
            
            .reports-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .report-stats-mini {
                width: 100%;
                justify-content: space-between;
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .reports-scrollable {
                max-height: 400px;
                padding: 15px;
            }
            
            .report-actions {
                flex-direction: column;
            }
            
            .report-action-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .hero-section {
                padding: 25px;
            }
            
            .hero-content h1 {
                font-size: 24px;
            }
            
            .report-stats-mini {
                flex-wrap: wrap;
            }
            
            .reports-scrollable {
                max-height: 350px;
                padding: 10px;
            }
            
            .report-card {
                padding: 15px;
            }
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
                <a href="admin_dashboard.php" class="nav-item active">
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
                    <?php if ($stats['pending_reports'] > 0): ?>
                        <span class="nav-badge warning"><?php echo $stats['pending_reports']; ?></span>
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
            <!-- Hero Section -->
            <div class="hero-section">
                <div class="hero-content">
                    <h1><?php echo getGreeting(); ?>, <?php echo htmlspecialchars($username); ?>!</h1>
                    <div class="slogan">Administrator Dashboard</div>
                    <p>Monitor user meeting activities and manage problem reports.</p>
                </div>
            </div>

            <!-- Stats Grid - Showing ALL user meetings and total reports -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_meetings']; ?></h3>
                        <p>Total Meetings</p>
                        <small>All meetings created by users</small>
                    </div>
                </div>
                
                <div class="stat-card today">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['today_meetings']; ?></h3>
                        <p>Today's Meetings</p>
                        <small>Meetings created by user today</small>
                    </div>
                </div>
                
                <div class="stat-card upcoming">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['upcoming_meetings']; ?></h3>
                        <p>Scheduled Meetings</p>
                        <small>Meetings scheduled by user</small>
                    </div>
                </div>
                
                <div class="stat-card reports">
                    <div class="stat-icon">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_reports']; ?></h3>
                        <p>Total Reports</p>
                        <small><?php echo $stats['pending_reports']; ?> pending</small>
                    </div>
                </div>
            </div>

            <!-- Enhanced Reports Section - ALL Pending Reports with Scroll -->
            <div class="reports-dashboard">
                <div class="reports-header">
                    <div class="reports-header-left">
                        <h2>
                            <i class="fas fa-flag"></i> 
                            Pending Reports Of Users
                        </h2>
                        <span class="report-badge <?php echo $stats['pending_reports'] > 0 ? 'warning' : ''; ?>">
                            <?php echo $stats['pending_reports']; ?> Pending
                        </span>
                    </div>
                    
                    <div class="report-stats-mini">
                        <div class="report-stat-mini pending">
                            <i class="fas fa-clock"></i>
                            <span class="count"><?php echo $stats['pending_reports']; ?></span>
                            <span class="label">Pending</span>
                        </div>
                        <div class="report-stat-mini reviewed">
                            <i class="fas fa-eye"></i>
                            <span class="count"><?php echo $stats['reviewed_reports']; ?></span>
                            <span class="label">Reviewed</span>
                        </div>
                        <div class="report-stat-mini resolved">
                            <i class="fas fa-check-circle"></i>
                            <span class="count"><?php echo $stats['resolved_reports']; ?></span>
                            <span class="label">Resolved</span>
                        </div>
                    </div>
                </div>
                
                <!-- Scrollable container for ALL pending reports -->
                <div class="reports-scrollable" id="reportsScrollable">
                    <?php if (!empty($display_reports)): ?>
                        <div class="reports-grid">
                            <?php foreach ($display_reports as $report): ?>
                                <div class="report-card pending" id="report-<?php echo $report['id']; ?>">
                                    <div class="report-header">
                                        <div class="report-user">
                                            <div class="report-user-avatar">
                                                <?php if (!empty($report['profile_photo'])): ?>
                                                    <img src="uploads/profile_photos/<?php echo htmlspecialchars($report['profile_photo']); ?>" alt="<?php echo htmlspecialchars($report['username']); ?>">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($report['username'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="report-user-info">
                                                <h4><?php echo htmlspecialchars($report['username']); ?></h4>
                                                <p>
                                                    <i class="far fa-clock"></i>
                                                    <?php echo timeAgo($report['created_at']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <span class="report-status-badge badge-warning">
                                            <i class="fas fa-clock"></i>
                                            Pending
                                        </span>
                                    </div>
                                    
                                    <div class="report-category">
                                        <i class="fas <?php echo getCategoryIcon($report['category']); ?>"></i>
                                        <?php echo getCategoryLabel($report['category']); ?>
                                    </div>
                                    
                                    <div class="report-content">
                                        <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                                    </div>
                                    
                                    <div class="report-meta">
                                        <div class="report-meta-item">
                                            <i class="fas fa-hashtag"></i>
                                            <span>ID: #<?php echo $report['id']; ?></span>
                                        </div>
                                        <div class="report-meta-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo htmlspecialchars($report['contact_email']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($report['admin_notes'])): ?>
                                        <div class="report-admin-notes">
                                            <i class="fas fa-sticky-note"></i>
                                            <div>
                                                <?php 
                                                    $notes = explode("\n\n", $report['admin_notes']);
                                                    echo nl2br(htmlspecialchars(end($notes)));
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="report-actions">
                                        <button class="report-action-btn view" onclick="viewReport(<?php echo $report['id']; ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        <button class="report-action-btn review" onclick="quickUpdateReport(<?php echo $report['id']; ?>, 'reviewed')">
                                            <i class="fas fa-check-double"></i> Mark Reviewed
                                        </button>
                                        <button class="report-action-btn resolve" onclick="quickUpdateReport(<?php echo $report['id']; ?>, 'resolved')">
                                            <i class="fas fa-check"></i> Resolve
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Show total count indicator if more than 2 reports -->
                        <?php if ($total_pending > 2): ?>
                            <div class="remaining-reports-indicator">
                                <i class="fas fa-arrow-down"></i>
                                <span>Showing all <?php echo $total_pending; ?> pending reports - scroll for more</span>
                                <i class="fas fa-arrow-down"></i>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-flag"></i>
                            <h3>No Pending Reports</h3>
                            <p>All caught up! No problem reports are currently pending review.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Scroll indicator (shown when more than 2 reports) -->
                <?php if ($total_pending > 2): ?>
                <div class="scroll-indicator" id="scrollIndicator">
                    <i class="fas fa-arrow-down"></i>
                    <span>Scroll to see all <?php echo $total_pending; ?> reports</span>
                    <i class="fas fa-arrow-down"></i>
                </div>
                <?php endif; ?>
            </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification">
        <i class="fas"></i>
        <span></span>
    </div>

    <script>
        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastIcon = toast.querySelector('i');
            const toastMessage = toast.querySelector('span');
            
            toast.className = 'toast-notification ' + type;
            toastIcon.className = 'fas';
            
            switch(type) {
                case 'success':
                    toastIcon.classList.add('fa-check-circle');
                    break;
                case 'error':
                    toastIcon.classList.add('fa-exclamation-circle');
                    break;
                default:
                    toastIcon.classList.add('fa-info-circle');
            }
            
            toastMessage.textContent = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Report functions
        window.viewReport = function(reportId) {
            window.location.href = `admin_reports.php?tab=pending&report=${reportId}`;
        };

        window.quickUpdateReport = async function(reportId, status) {
            let action = '';
            if (status === 'reviewed') action = 'mark as reviewed';
            else if (status === 'resolved') action = 'resolve';
            
            if (!confirm(`Do you want to ${action} this report?`)) return;
            
            try {
                const formData = new FormData();
                formData.append('quick_update_report', '1');
                formData.append('report_id', reportId);
                formData.append('status', status);
                formData.append('admin_notes', `Quick update from dashboard - ${status}`);
                
                const response = await fetch('admin_dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(`Report marked as ${status}`, 'success');
                    
                    // Remove the report card from the dashboard
                    const reportElement = document.getElementById(`report-${reportId}`);
                    if (reportElement) {
                        reportElement.remove();
                        
                        // Check if there are any pending reports left
                        const remainingReports = document.querySelectorAll('.report-card').length;
                        const reportsGrid = document.querySelector('.reports-grid');
                        const emptyState = document.querySelector('.empty-state');
                        
                        if (remainingReports === 0 && reportsGrid) {
                            // Replace grid with empty state
                            const scrollableDiv = document.getElementById('reportsScrollable');
                            if (scrollableDiv) {
                                scrollableDiv.innerHTML = `
                                    <div class="empty-state">
                                        <i class="fas fa-flag"></i>
                                        <h3>No Pending Reports</h3>
                                        <p>All caught up! No problem reports are currently pending review.</p>
                                    </div>
                                `;
                            }
                            
                            // Remove scroll indicator
                            const scrollIndicator = document.getElementById('scrollIndicator');
                            if (scrollIndicator) {
                                scrollIndicator.remove();
                            }
                        }
                        
                        // Update the pending count in badges
                        const pendingCountElements = document.querySelectorAll('.report-badge, .stat-info h3, .count');
                        const newPendingCount = remainingReports - 1;
                        
                        pendingCountElements.forEach(el => {
                            if (el.closest('.report-stat-mini.pending') || 
                                el.closest('.report-badge') || 
                                (el.closest('.stat-info') && el.closest('.stat-card.reports'))) {
                                if (el.tagName === 'H3' || el.classList.contains('count')) {
                                    el.textContent = newPendingCount;
                                }
                            }
                        });
                        
                        // Update sidebar badge
                        const sidebarBadge = document.querySelector('.nav-item[href="admin_reports.php"] .nav-badge');
                        if (sidebarBadge) {
                            sidebarBadge.textContent = newPendingCount;
                        }
                    }
                    
                    // Reload the page after 1.5 seconds to update all stats
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Failed to update report', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error updating report', 'error');
            }
        };

        // Hover effects for stat cards
        document.querySelectorAll('.stat-card, .info-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Scroll handling for reports
        const reportsScrollable = document.getElementById('reportsScrollable');
        const scrollIndicator = document.getElementById('scrollIndicator');
        
        if (reportsScrollable && scrollIndicator) {
            // Hide scroll indicator when user scrolls
            reportsScrollable.addEventListener('scroll', function() {
                scrollIndicator.style.opacity = '0.5';
                setTimeout(() => {
                    scrollIndicator.style.opacity = '1';
                }, 1000);
            });
            
            // Click on scroll indicator to scroll down
            scrollIndicator.addEventListener('click', function() {
                reportsScrollable.scrollBy({
                    top: 300,
                    behavior: 'smooth'
                });
            });
        }

        // Animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            const greetingElement = document.querySelector('.hero-content h1');
            if (greetingElement) {
                greetingElement.style.opacity = '0';
                greetingElement.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    greetingElement.style.transition = 'all 0.5s ease';
                    greetingElement.style.opacity = '1';
                    greetingElement.style.transform = 'translateY(0)';
                }, 300);
            }

            // Animate stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 300 + (index * 100));
            });

            // Animate report cards
            const reportCards = document.querySelectorAll('.report-card');
            reportCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 500 + (index * 100));
            });
            
            // Show scroll indicator if more than 2 reports
            if (reportCards.length > 2 && scrollIndicator) {
                setTimeout(() => {
                    scrollIndicator.style.display = 'inline-flex';
                }, 1000);
            }
            
            // Force scrollbar to appear by checking content height
            if (reportsScrollable) {
                setTimeout(() => {
                    if (reportsScrollable.scrollHeight > reportsScrollable.clientHeight) {
                        // Content is taller than container, scrollbar will appear
                        console.log('Scrollbar should appear');
                    }
                }, 500);
            }
        });
    </script>
</body>
</html>