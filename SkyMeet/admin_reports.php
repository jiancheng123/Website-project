<?php
session_start();
require_once 'connect.php';
require_once 'profile_utils.php';

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

// Initialize variables
$error = '';
$success = '';

// Get active tab from URL
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// Map tab to status for filtering
$status_filter = 'all';
if ($active_tab === 'pending') {
    $status_filter = 'pending';
} elseif ($active_tab === 'review') {
    $status_filter = 'reviewed';
} elseif ($active_tab === 'resolved') {
    $status_filter = 'resolved';
} elseif ($active_tab === 'all') {
    $status_filter = 'all';
}

// Get filter parameters from URL
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Create reports table if it doesn't exist
$create_reports_table = "CREATE TABLE IF NOT EXISTS problem_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($create_reports_table);

// Check if admin_notes column exists
$check_column = $conn->query("SHOW COLUMNS FROM problem_reports LIKE 'admin_notes'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE problem_reports ADD COLUMN admin_notes TEXT AFTER status");
}

// Handle report status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $report_id = intval($_POST['report_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes'] ?? '');
    
    if (!empty($admin_notes)) {
        $notes_with_prefix = "\n\nAdmin Note (" . date('Y-m-d H:i') . "): " . $admin_notes;
        $update_sql = "UPDATE problem_reports SET status = ?, admin_notes = CONCAT(IFNULL(admin_notes, ''), ?) WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $new_status, $notes_with_prefix, $report_id);
    } else {
        $update_sql = "UPDATE problem_reports SET status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $report_id);
    }
    
    if ($update_stmt->execute()) {
        $success = "Report status updated successfully!";
        header("Location: admin_reports.php?tab=" . $active_tab . "&updated=1");
        exit();
    } else {
        $error = "Failed to update report status: " . $conn->error;
    }
    $update_stmt->close();
}

// Handle edit admin notes 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_notes'])) {
    $report_id = intval($_POST['report_id']);
    
    // Get the raw POST data to preserve line breaks exactly as entered
    $edited_notes = $_POST['edited_notes'] ?? '';
    
    // CRITICAL FIX: Remove all \r\n and replace with just \n
    $edited_notes = str_replace("\r\n", "\n", $edited_notes);
    $edited_notes = str_replace("\r", "\n", $edited_notes);
    
    // Trim but preserve internal line breaks
    $edited_notes = trim($edited_notes);
    
    // Escape for database
    $edited_notes = mysqli_real_escape_string($conn, $edited_notes);
    
    $update_sql = "UPDATE problem_reports SET admin_notes = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $edited_notes, $report_id);
    
    if ($update_stmt->execute()) {
        $success = "Admin notes updated successfully!";
        header("Location: admin_reports.php?tab=" . $active_tab . "&notes_updated=1");
        exit();
    } else {
        $error = "Failed to update admin notes: " . $conn->error;
    }
    $update_stmt->close();
}

// Build query with profile_photo included
$query = "SELECT r.*, u.username, u.email as user_email, u.profile_photo
          FROM problem_reports r 
          JOIN users u ON r.user_id = u.id 
          WHERE 1=1";

$params = [];
$types = "";

// Apply status filter
if ($status_filter !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Apply category filter
if ($category_filter !== 'all') {
    $query .= " AND r.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

// Apply search
if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR r.description LIKE ? OR r.contact_email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY 
    CASE r.status 
        WHEN 'pending' THEN 1 
        WHEN 'reviewed' THEN 2 
        WHEN 'resolved' THEN 3 
    END, 
    r.created_at DESC";

$reports = [];
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $reports = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    COUNT(DISTINCT user_id) as unique_users
    FROM problem_reports";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Initialize stats with default values to prevent undefined array key
$stats['total'] = $stats['total'] ?? 0;
$stats['pending'] = $stats['pending'] ?? 0;
$stats['reviewed'] = $stats['reviewed'] ?? 0;
$stats['resolved'] = $stats['resolved'] ?? 0;
$stats['unique_users'] = $stats['unique_users'] ?? 0;

// Get category distribution
$category_sql = "SELECT category, COUNT(*) as count FROM problem_reports GROUP BY category";
$category_result = $conn->query($category_sql);
$categories = [];
while ($row = $category_result->fetch_assoc()) {
    $categories[$row['category']] = $row['count'];
}

// Get total meetings count for sidebar
$meetings_count_sql = "SELECT COUNT(*) as total FROM meetings";
$meetings_count_result = $conn->query($meetings_count_sql);
$meetings_count = 0;
if ($meetings_count_result) {
    $meetings_data = $meetings_count_result->fetch_assoc();
    $meetings_count = $meetings_data['total'] ?? 0;
}

// Get all users count for sidebar (excluding current admin for accuracy)
$users_count_sql = "SELECT COUNT(*) as total FROM users WHERE role != 'Admin'";
$users_count_result = $conn->query($users_count_sql);
$total_users = 0;
if ($users_count_result) {
    $users_data = $users_count_result->fetch_assoc();
    $total_users = $users_data['total'] ?? 0;
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
        'bug' => 'Bug or Error',
        'feature' => 'Feature Request',
        'ui' => 'User Interface Issue',
        'performance' => 'Performance Problem',
        'security' => 'Security Concern',
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
    <title>Reports Management - SkyMeet Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset - Same as admin_dashboard.php */
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

        /* Dashboard Container - Same as admin_dashboard.php */
        .dashboard-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        /* Sidebar - Same as admin_dashboard.php */
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

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0ff;
        }

        .header-left h2 {
            font-size: 32px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .header-left h2 i {
            color: #667eea;
            font-size: 32px;
        }

        .header-left p {
            color: #666;
            font-size: 16px;
        }

        /* Stats Cards */
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

        .stat-card.pending {
            border-color: #f59e0b;
        }

        .stat-card.reviewed {
            border-color: #3b82f6;
        }

        .stat-card.resolved {
            border-color: #10b981;
        }

        .stat-card.users {
            border-color: #8b5cf6;
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

        .stat-card.pending .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-card.reviewed .stat-icon {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .stat-card.resolved .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-card.users .stat-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card.total .stat-info h3 {
            color: #667eea;
        }

        .stat-card.pending .stat-info h3 {
            color: #f59e0b;
        }

        .stat-card.reviewed .stat-info h3 {
            color: #3b82f6;
        }

        .stat-card.resolved .stat-info h3 {
            color: #10b981;
        }

        .stat-card.users .stat-info h3 {
            color: #8b5cf6;
        }

        .stat-info p {
            color: #666;
            font-size: 14px;
        }

        .stat-info small {
            font-size: 12px;
            color: #999;
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 20px;
        }

        .filter-bar form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            width: 100%;
        }

        .filter-group {
            flex: 2;
            min-width: 250px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            color: #667eea;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-select, .filter-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0ff;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9ff;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex: 1;
            min-width: 280px;
        }

        .btn-filter {
            flex: 1;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-reset {
            flex: 1;
            padding: 12px 20px;
            background: #f0f0f0;
            color: #666;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-reset:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        /* Reports Navigation */
        .reports-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
            overflow-x: auto;
        }

        .reports-nav::-webkit-scrollbar {
            height: 4px;
        }

        .reports-nav::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .reports-nav::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }

        .nav-tab {
            padding: 12px 25px;
            background: white;
            border: 2px solid #e0e0ff;
            border-radius: 10px;
            color: #555;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .nav-tab:hover {
            background: #f8f9ff;
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        .nav-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .nav-tab i {
            font-size: 16px;
        }

        .nav-tab .count {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 5px;
        }

        .nav-tab.active .count {
            background: rgba(255, 255, 255, 0.3);
        }

        .nav-tab:not(.active) .count {
            background: #667eea;
            color: white;
        }

        /* Reports Container */
        .reports-container {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 25px;
        }

        /* Reports List - Scrollable */
        .reports-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-height: 800px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .reports-list::-webkit-scrollbar {
            width: 8px;
        }

        .reports-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .reports-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .reports-list::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .report-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            transition: all 0.3s;
            position: relative;
            border-left: 4px solid transparent;
        }

        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        }

        .report-card.pending {
            border-left-color: #f59e0b;
        }

        .report-card.reviewed {
            border-left-color: #3b82f6;
        }

        .report-card.resolved {
            border-left-color: #10b981;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .report-user {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 250px;
        }

        .user-avatar-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
        }

        .user-info h4 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
        }

        .user-info p {
            color: #666;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .user-info .contact-email {
            color: #667eea;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .report-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
            min-width: 200px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
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

        .badge-category {
            background: #f0f5ff;
            color: #667eea;
        }

        .report-description {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #444;
            line-height: 1.6;
            border-left: 3px solid #667eea;
            white-space: pre-wrap;
        }

        /* Improved Admin Notes Styles */
        .admin-notes-container {
            background: #fff7ed;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
            border: 1px solid #ffe5b4;
        }

        .admin-notes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .admin-notes-header strong {
            color: #b45309;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-edit-notes {
            background: white;
            border: 1px solid #e0e0e0;
            color: #667eea;
            cursor: pointer;
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 20px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit-notes:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .admin-notes-content {
            color: #5d3a1a;
            line-height: 1.6;
            font-size: 13px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 300px;
            overflow-y: auto;
            background: #fffaf0;
            border-radius: 8px;
            padding: 4px;
        }

        .note-entry {
            padding: 16px;
            border-bottom: 1px solid #ffddb0;
            background: white;
            margin: 8px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .note-entry:last-child {
            border-bottom: none;
        }

        .note-timestamp {
            color: #b45309;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #ffddb0;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #fff3e0;
            padding: 6px 10px;
            border-radius: 4px;
            margin: -8px -8px 10px -8px;
        }

        .note-text {
            color: #5d3a1a;
            padding: 4px 8px;
            line-height: 1.6;
            white-space: pre-wrap;
            font-size: 13px;
        }

        .note-text:not(:last-child) {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }

        .admin-notes-edit-form {
            display: none;
            margin-top: 15px;
        }

        .admin-notes-edit-form textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 15px;
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        .admin-notes-edit-form textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .admin-notes-edit-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-save-notes {
            padding: 10px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-save-notes:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-cancel-notes {
            padding: 10px 24px;
            background: #f0f0f0;
            color: #666;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-cancel-notes:hover {
            background: #e0e0e0;
        }

        /* Status Update Form */
        .status-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .status-select {
            padding: 10px 16px;
            border: 2px solid #e0e0ff;
            border-radius: 8px;
            font-size: 13px;
            background: white;
            min-width: 140px;
            flex: 1;
        }

        .status-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-update {
            padding: 10px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .report-meta {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            font-size: 12px;
            color: #666;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-item i {
            color: #667eea;
        }

        /* Side Panel */
        .side-panel {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .panel-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
        }

        .panel-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .panel-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
        }

        .panel-title h4 {
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
        }

        .panel-title p {
            font-size: 13px;
            color: #666;
        }

        /* Category Distribution */
        .category-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
            padding: 8px 0;
            border-bottom: 1px dashed #f0f0f0;
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-name {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
        }

        .category-name i {
            color: #667eea;
            width: 20px;
            font-size: 14px;
        }

        .category-count {
            background: #f0f5ff;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .quick-stat {
            text-align: center;
            padding: 15px;
            background: #f8f9ff;
            border-radius: 12px;
        }

        .quick-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .quick-stat-label {
            font-size: 12px;
            color: #666;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
            font-size: 14px;
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

        .alert-error {
            background: #fee;
            border: 2px solid #f44336;
            color: #c62828;
        }

        .alert-success {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            color: #2e7d32;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 2px dashed #e0e0e0;
        }

        .empty-state i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 22px;
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            font-size: 15px;
            margin-bottom: 20px;
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
            
            .reports-list {
                max-height: 600px;
            }
            
            .reports-container {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-actions {
                min-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .header-left h2 {
                font-size: 24px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-bar form {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-group {
                width: 100%;
                min-width: 100%;
            }
            
            .filter-actions {
                width: 100%;
                min-width: 100%;
            }
            
            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .report-badges {
                justify-content: flex-start;
                width: 100%;
            }
            
            .status-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-select {
                width: 100%;
            }
            
            .btn-update {
                width: 100%;
                justify-content: center;
            }
            
            .reports-nav {
                flex-wrap: wrap;
            }
            
            .nav-tab {
                flex: 1;
                min-width: 120px;
                justify-content: center;
            }
            
            .report-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .reports-list {
                max-height: 500px;
            }
        }

        @media (max-width: 480px) {
            .header-left h2 {
                font-size: 22px;
            }
            
            .reports-list {
                max-height: 400px;
            }
            
            .nav-tab {
                min-width: 100px;
                padding: 10px 15px;
                font-size: 13px;
            }
            
            .report-user {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .user-avatar-small {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar - Matching admin_dashboard.php -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> SkyMeet <span>Admin</span></h1>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="uploads/profile_photos/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                             alt="<?php echo htmlspecialchars($username); ?>">
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
                    <?php if ($meetings_count > 0): ?>
                        <span class="nav-badge"><?php echo $meetings_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_schedule.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i> User's Schedule
                    <?php if ($upcoming_meetings_count > 0): ?>
                        <span class="nav-badge"><?php echo $upcoming_meetings_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_reports.php" class="nav-item active">
                    <i class="fas fa-flag"></i> User's Reports
                    <?php if ($stats['pending'] > 0): ?>
                        <span class="nav-badge warning"><?php echo $stats['pending']; ?></span>
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
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <h2><i class="fas fa-flag"></i> Reports Management</h2>
                    <p>View and manage user's problem reports.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Reports</p>
                    </div>
                </div>
                
                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                        <small>Need attention</small>
                    </div>
                </div>

                <div class="stat-card reviewed">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['reviewed']; ?></h3>
                        <p>Reviewed</p>
                        <small>Being processed</small>
                    </div>
                </div>
                
                <div class="stat-card resolved">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['resolved']; ?></h3>
                        <p>Resolved</p>
                        <small>Completed</small>
                    </div>
                </div>
            </div>

            <!-- Filter Bar - Improved button alignment -->
            <div class="filter-bar">
                <form method="GET">
                    <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                    
                    <div class="filter-group">
                        <label><i class="fas fa-tag"></i> Category</label>
                        <select name="category" class="filter-select">
                            <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <option value="bug" <?php echo $category_filter === 'bug' ? 'selected' : ''; ?>>Bug or Error</option>
                            <option value="feature" <?php echo $category_filter === 'feature' ? 'selected' : ''; ?>>Feature Request</option>
                            <option value="ui" <?php echo $category_filter === 'ui' ? 'selected' : ''; ?>>User Interface Issue</option>
                            <option value="performance" <?php echo $category_filter === 'performance' ? 'selected' : ''; ?>>Performance Problem</option>
                            <option value="security" <?php echo $category_filter === 'security' ? 'selected' : ''; ?>>Security Concern</option>
                            <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Search by user, description, email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="admin_reports.php?tab=<?php echo $active_tab; ?>" class="btn-reset">
                            <i class="fas fa-redo"></i> Reset Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Reports Navigation -->
            <div class="reports-nav">
                <a href="?tab=pending" class="nav-tab <?php echo $active_tab === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                    <span class="count"><?php echo $stats['pending']; ?></span>
                </a>
                <a href="?tab=review" class="nav-tab <?php echo $active_tab === 'review' ? 'active' : ''; ?>">
                    <i class="fas fa-eye"></i> Reviewed
                    <span class="count"><?php echo $stats['reviewed']; ?></span>
                </a>
                <a href="?tab=resolved" class="nav-tab <?php echo $active_tab === 'resolved' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Resolved
                    <span class="count"><?php echo $stats['resolved']; ?></span>
                </a>
                <a href="?tab=all" class="nav-tab <?php echo $active_tab === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Reports
                    <span class="count"><?php echo $stats['total']; ?></span>
                </a>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Report status updated successfully!</span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['notes_updated'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Admin notes updated successfully!</span>
                </div>
            <?php endif; ?>

            <!-- Reports Container -->
            <div class="reports-container">
                <!-- Reports List - Scrollable -->
                <div class="reports-list">
                    <?php if (empty($reports)): ?>
                        <div class="empty-state">
                            <i class="fas fa-flag"></i>
                            <h3>No Reports Found</h3>
                            <p>
                                <?php if ($active_tab === 'all'): ?>
                                    There are no reports in the system yet.
                                <?php elseif ($active_tab === 'review'): ?>
                                    There are no reports under review matching your criteria.
                                <?php else: ?>
                                    There are no <?php echo $active_tab; ?> reports matching your criteria.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <div class="report-card <?php echo $report['status']; ?>" id="report-<?php echo $report['id']; ?>">
                                <div class="report-header">
                                    <div class="report-user">
                                        <div class="user-avatar-small">
                                            <?php if (!empty($report['profile_photo'])): ?>
                                                <img src="uploads/profile_photos/<?php echo htmlspecialchars($report['profile_photo']); ?>" 
                                                     alt="<?php echo htmlspecialchars($report['username']); ?>">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($report['username'], 0, 2)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-info">
                                            <h4><?php echo htmlspecialchars($report['username']); ?></h4>
                                            <p>User ID: #<?php echo $report['user_id']; ?></p>
                                            <div class="contact-email">
                                                <i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($report['contact_email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="report-badges">
                                        <span class="badge <?php echo getStatusBadge($report['status']); ?>">
                                            <i class="fas <?php echo getStatusIcon($report['status']); ?>"></i>
                                            <?php 
                                            if ($report['status'] === 'reviewed') {
                                                echo 'Under Review';
                                            } else {
                                                echo ucfirst($report['status']); 
                                            }
                                            ?>
                                        </span>
                                        <span class="badge badge-category">
                                            <i class="fas <?php echo getCategoryIcon($report['category']); ?>"></i>
                                            <?php echo getCategoryLabel($report['category']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="report-description">
                                    <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                                </div>

                                <?php if (!empty($report['admin_notes'])): ?>
                                    <!-- Editable Admin Notes  -->
                                    <div class="admin-notes-container" id="notes-container-<?php echo $report['id']; ?>">
                                        <div class="admin-notes-header">
                                            <strong><i class="fas fa-sticky-note"></i> Admin Notes:</strong>
                                            <button type="button" class="btn-edit-notes" onclick="showEditForm(<?php echo $report['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit Notes
                                            </button>
                                        </div>
                                        <div class="admin-notes-content" id="notes-content-<?php echo $report['id']; ?>">
                                            <?php 
                                            // CRITICAL FIX: Remove all \r\n and replace with \n
                                            $clean_notes = str_replace("\r\n", "\n", $report['admin_notes']);
                                            $clean_notes = str_replace("\r", "\n", $clean_notes);
                                            
                                            // Split by patterns that indicate new notes
                                            $pattern = '/((?:Admin Note|Quick Update)\s*\(\d{4}-\d{2}-\d{2} \d{2}:\d{2}\)):\s*/';
                                            $parts = preg_split($pattern, $clean_notes, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                                            
                                            $current_timestamp = '';
                                            $current_content = '';
                                            
                                            foreach ($parts as $part) {
                                                $part = trim($part);
                                                if (empty($part)) continue;
                                                
                                                if (preg_match('/^(Admin Note|Quick Update)\s*\(\d{4}-\d{2}-\d{2} \d{2}:\d{2}\)$/s', $part)) {
                                                    if (!empty($current_content)) {
                                                        echo '<div class="note-entry">';
                                                        if (!empty($current_timestamp)) {
                                                            echo '<div class="note-timestamp"><i class="far fa-clock"></i> ' . htmlspecialchars($current_timestamp) . '</div>';
                                                        }
                                                        // Split by single newlines
                                                        $content_lines = explode("\n", $current_content);
                                                        foreach ($content_lines as $line) {
                                                            if (trim($line)) {
                                                                echo '<div class="note-text">' . htmlspecialchars(trim($line)) . '</div>';
                                                            }
                                                        }
                                                        echo '</div>';
                                                    }
                                                    $current_timestamp = $part;
                                                    $current_content = '';
                                                } else {
                                                    if (empty($current_timestamp)) {
                                                        echo '<div class="note-entry">';
                                                        $content_lines = explode("\n", $part);
                                                        foreach ($content_lines as $line) {
                                                            if (trim($line)) {
                                                                echo '<div class="note-text">' . htmlspecialchars(trim($line)) . '</div>';
                                                            }
                                                        }
                                                        echo '</div>';
                                                    } else {
                                                        $current_content .= ($current_content ? "\n\n" : "") . $part;
                                                    }
                                                }
                                            }
                                            
                                            if (!empty($current_content)) {
                                                echo '<div class="note-entry">';
                                                if (!empty($current_timestamp)) {
                                                    echo '<div class="note-timestamp"><i class="far fa-clock"></i> ' . htmlspecialchars($current_timestamp) . '</div>';
                                                }
                                                $content_lines = explode("\n", $current_content);
                                                foreach ($content_lines as $line) {
                                                    if (trim($line)) {
                                                        echo '<div class="note-text">' . htmlspecialchars(trim($line)) . '</div>';
                                                    }
                                                }
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                        
                                        <!-- Edit Form (Hidden by default) -->
                                        <div class="admin-notes-edit-form" id="edit-form-<?php echo $report['id']; ?>">
                                            <form method="POST" onsubmit="return validateNotes(<?php echo $report['id']; ?>)">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="edit_notes" value="1">
                                                <textarea name="edited_notes" id="notes-textarea-<?php echo $report['id']; ?>" 
                                                          placeholder="Enter your admin notes here..."><?php 
                                                    // Clean up the text for editing
                                                    $clean_for_edit = str_replace("\r\n", "\n", $report['admin_notes']);
                                                    $clean_for_edit = str_replace("\r", "\n", $clean_for_edit);
                                                    echo htmlspecialchars($clean_for_edit); 
                                                ?></textarea>
                                                <div class="admin-notes-edit-actions">
                                                    <button type="submit" class="btn-save-notes">
                                                        <i class="fas fa-save"></i> Save
                                                    </button>
                                                    <button type="button" class="btn-cancel-notes" onclick="hideEditForm(<?php echo $report['id']; ?>)">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Add Notes Form (When no notes exist) -->
                                    <div class="admin-notes-container" id="notes-container-<?php echo $report['id']; ?>">
                                        <div class="admin-notes-header">
                                            <strong><i class="fas fa-sticky-note"></i> Admin Notes:</strong>
                                            <button type="button" class="btn-edit-notes" onclick="showEditForm(<?php echo $report['id']; ?>)">
                                                <i class="fas fa-plus"></i> Add Notes
                                            </button>
                                        </div>
                                        <div class="admin-notes-content" id="notes-content-<?php echo $report['id']; ?>" style="color: #999; font-style: italic; padding: 12px;">
                                            No admin notes yet. Click "Add Notes" to add some.
                                        </div>
                                        
                                        <!-- Add Form (Hidden by default) -->
                                        <div class="admin-notes-edit-form" id="edit-form-<?php echo $report['id']; ?>">
                                            <form method="POST" onsubmit="return validateNotes(<?php echo $report['id']; ?>)">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="edit_notes" value="1">
                                                <textarea name="edited_notes" id="notes-textarea-<?php echo $report['id']; ?>" 
                                                          placeholder="Enter your admin notes here..."></textarea>
                                                <div class="admin-notes-edit-actions">
                                                    <button type="submit" class="btn-save-notes">
                                                        <i class="fas fa-save"></i> Save
                                                    </button>
                                                    <button type="button" class="btn-cancel-notes" onclick="hideEditForm(<?php echo $report['id']; ?>)">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Status Update Form -->
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                    <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                                    <select name="status" class="status-select">
                                        <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="reviewed" <?php echo $report['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    </select>
                                    <input type="text" name="admin_notes" class="status-select" 
                                           placeholder="Add note (optional)" style="flex: 2;">
                                    <button type="submit" name="update_status" class="btn-update">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                </form>

                                <div class="report-meta">
                                    <span class="meta-item">
                                        <i class="far fa-clock"></i> 
                                        <?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo timeAgo($report['updated_at']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Side Panel -->
                <div class="side-panel">
                    <!-- Category Distribution -->
                    <div class="panel-card">
                        <div class="panel-header">
                            <div class="panel-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="panel-title">
                                <h4>Categories</h4>
                                <p>Report distribution by type</p>
                            </div>
                        </div>
                        <div class="category-list">
                            <?php
                            $category_labels = [
                                'bug' => ['Bug or Error', 'fa-bug'],
                                'feature' => ['Feature Request', 'fa-lightbulb'],
                                'ui' => ['User Interface Issue', 'fa-paint-brush'],
                                'performance' => ['Performance Problem', 'fa-tachometer-alt'],
                                'security' => ['Security Concern', 'fa-shield-alt'],
                                'other' => ['Other', 'fa-question-circle']
                            ];
                            foreach ($category_labels as $key => $label):
                                $count = isset($categories[$key]) ? $categories[$key] : 0;
                            ?>
                                <div class="category-item">
                                    <span class="category-name">
                                        <i class="fas <?php echo $label[1]; ?>"></i>
                                        <?php echo $label[0]; ?>
                                    </span>
                                    <span class="category-count"><?php echo $count; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="panel-card">
                        <div class="panel-header">
                            <div class="panel-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="panel-title">
                                <h4>Quick Stats</h4>
                                <p>Current status overview</p>
                            </div>
                        </div>
                        <div class="quick-stats">
                            <div class="quick-stat">
                                <div class="quick-stat-value"><?php echo $stats['pending']; ?></div>
                                <div class="quick-stat-label">Pending</div>
                            </div>
                            <div class="quick-stat">
                                <div class="quick-stat-value"><?php echo $stats['reviewed']; ?></div>
                                <div class="quick-stat-label">Reviewed</div>
                            </div>
                            <div class="quick-stat">
                                <div class="quick-stat-value"><?php echo $stats['resolved']; ?></div>
                                <div class="quick-stat-label">Resolved</div>
                            </div>
                            <div class="quick-stat">
                                <div class="quick-stat-value"><?php echo $stats['total']; ?></div>
                                <div class="quick-stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);

        // Admin Notes Edit Functions
        function showEditForm(reportId) {
            document.getElementById('notes-content-' + reportId).style.display = 'none';
            document.getElementById('edit-form-' + reportId).style.display = 'block';
        }

        function hideEditForm(reportId) {
            document.getElementById('edit-form-' + reportId).style.display = 'none';
            document.getElementById('notes-content-' + reportId).style.display = 'block';
        }

        function validateNotes(reportId) {
            const textarea = document.getElementById('notes-textarea-' + reportId);
            if (!textarea.value.trim()) {
                return confirm('Are you sure you want to save empty notes? This will clear existing notes.');
            }
            return true;
        }

        // Close edit forms when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.admin-notes-container')) {
                document.querySelectorAll('.admin-notes-edit-form').forEach(form => {
                    if (form.style.display === 'block') {
                        form.style.display = 'none';
                        const reportId = form.id.split('-')[2];
                        const contentElement = document.getElementById('notes-content-' + reportId);
                        if (contentElement) {
                            contentElement.style.display = 'block';
                        }
                    }
                });
            }
        });

        // Keyboard shortcuts 
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                if (e.key === '1') window.location.href = '?tab=pending';
                if (e.key === '2') window.location.href = '?tab=review';
                if (e.key === '3') window.location.href = '?tab=resolved';
                if (e.key === '4') window.location.href = '?tab=all';
            }
        });
    </script>
</body>
</html>