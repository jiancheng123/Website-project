<?php
session_start();
require_once 'connect.php';
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
$current_user = getCurrentUser($conn, $user_id);

// Update user's last activity time
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

// GET PENDING FRIEND REQUESTS COUNT 
$pending_requests_count_sql = "SELECT COUNT(*) as count FROM friends f
                               JOIN users u ON f.user_id = u.id
                               WHERE f.friend_id = ? AND f.status = 'pending' 
                               AND u.username != 'admin' AND u.email NOT LIKE '%admin%'";
$pending_requests_stmt = $conn->prepare($pending_requests_count_sql);
$pending_requests_count = 0;
if ($pending_requests_stmt) {
    $pending_requests_stmt->bind_param("i", $user_id);
    $pending_requests_stmt->execute();
    $pending_requests_result = $pending_requests_stmt->get_result();
    $pending_requests_data = $pending_requests_result->fetch_assoc();
    $pending_requests_count = $pending_requests_data['count'] ?? 0;
    $pending_requests_stmt->close();
}

// Total unread messages including friend requests
$total_unread_messages = $unread_private_count + $unread_group_count + $pending_requests_count;

// Get selected filters
$selected_month = isset($_GET['month']) ? $_GET['month'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // Default to newest

// Function to get meeting status
function getMeetingStatus($meeting_date, $start_time, $end_time) {
    $now = time();
    $meeting_start = strtotime($meeting_date . ' ' . $start_time);
    $meeting_end = strtotime($meeting_date . ' ' . $end_time);
    
    if ($now < $meeting_start) return 'upcoming';
    if ($now >= $meeting_start && $now <= $meeting_end) return 'ongoing';
    return 'completed';
}

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
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

// Get all meetings for this user
$all_meetings = [];
$upcoming_count = 0;
$ongoing_count = 0;
$completed_count = 0;

// Build the SQL query
$sql = "SELECT * FROM meetings WHERE host_id = ?";
$params = [$user_id];
$types = "i";

// Add search filter
if (!empty($search_query)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Add month filter
if (!empty($selected_month)) {
    $sql .= " AND DATE_FORMAT(meeting_date, '%Y-%m') = ?";
    $params[] = $selected_month;
    $types .= "s";
}

// Order by based on sort selection
if ($sort_order === 'oldest') {
    $sql .= " ORDER BY meeting_date ASC, start_time ASC";
} else { // newest (default)
    $sql .= " ORDER BY meeting_date DESC, start_time DESC";
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Calculate status
        $status = getMeetingStatus($row['meeting_date'], $row['start_time'], $row['end_time']);
        $row['status'] = $status;
        
        // Count by status
        if ($status == 'upcoming') $upcoming_count++;
        if ($status == 'ongoing') $ongoing_count++;
        if ($status == 'completed') $completed_count++;
        
        // Get participant count if table exists
        $participant_count = 0;
        $check_participants = $conn->query("SHOW TABLES LIKE 'meeting_participants'");
        if ($check_participants && $check_participants->num_rows > 0) {
            $count_sql = "SELECT COUNT(DISTINCT participant_id) as count FROM meeting_participants WHERE meeting_id = ?";
            $count_stmt = $conn->prepare($count_sql);
            if ($count_stmt) {
                $count_stmt->bind_param("i", $row['id']);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                if ($count_row = $count_result->fetch_assoc()) {
                    $participant_count = $count_row['count'];
                }
                $count_stmt->close();
            }
        }
        $row['participant_count'] = $participant_count;
        
        $all_meetings[] = $row;
    }
    $stmt->close();
}

// Get total meetings count
$total_meetings = count($all_meetings);

// Get available months for filter
$available_months = [];
$month_sql = "SELECT DISTINCT DATE_FORMAT(meeting_date, '%Y-%m') as month_value,
                      DATE_FORMAT(meeting_date, '%M %Y') as month_name
               FROM meetings 
               WHERE host_id = ?
               ORDER BY meeting_date DESC";
$month_stmt = $conn->prepare($month_sql);
if ($month_stmt) {
    $month_stmt->bind_param("i", $user_id);
    $month_stmt->execute();
    $month_result = $month_stmt->get_result();
    $available_months = $month_result->fetch_all(MYSQLI_ASSOC);
    $month_stmt->close();
}

// Handle meeting deletion
if (isset($_GET['delete'])) {
    $meeting_id = intval($_GET['delete']);
    
    // Check if meeting_participants table exists
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
    
    // Delete the meeting
    $delete_sql = "DELETE FROM meetings WHERE id = ? AND host_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $meeting_id, $user_id);
        if ($delete_stmt->execute()) {
            $redirect_url = "meetings.php?success=Meeting+deleted+successfully";
            if (!empty($selected_month)) {
                $redirect_url .= "&month=" . urlencode($selected_month);
            }
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
}

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
    <title>My Meetings - SkyMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
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

        .meetings-container {
            display: grid;
            grid-template-columns: 300px 1fr;
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

        .profile-dropdown-item.logout-item:hover {
            color: #f44336;
        }

        .profile-dropdown-item.logout-item i {
            color: #f44336;
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
        }

        /* Header */
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

        /* Create Meeting Button */
        .create-meeting-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            border: 2px solid transparent;
            letter-spacing: 0.5px;
        }

        .create-meeting-btn::before {
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

        .create-meeting-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.6);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .create-meeting-btn:hover::before {
            width: 400px;
            height: 400px;
        }

        .create-meeting-btn:active {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }

        .create-meeting-btn i {
            font-size: 18px;
            transition: transform 0.3s ease;
            transform: translateY(10px);
            display: inline-block;
        }

        .create-meeting-btn:hover i {
            transform: translateY(10px) rotate(90deg) scale(1.2); 
        }

        .create-meeting-btn span {
            display: inline-block;
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

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e0e0ff;
            border-radius: 10px;
            font-size: 15px;
            color: #333;
            background: white;
            cursor: pointer;
            min-width: 200px;
            transition: all 0.3s;
            outline: none;
        }

        .filter-select:hover {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        /* Statistics Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
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
            font-size: 28px;
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(102, 126, 234, 0.2));
            color: #667eea;
        }

        .stat-card.upcoming .stat-icon {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: #10b981;
        }

        .stat-card.ongoing .stat-icon {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: #f59e0b;
        }

        .stat-card.completed .stat-icon {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.1), rgba(107, 114, 128, 0.2));
            color: #6b7280;
        }

        .stat-info h3 {
            font-size: 36px;
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
            font-size: 16px;
            font-weight: 500;
        }

        /* Meetings Container */
        .meetings-container-inner {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            flex-shrink: 0;
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

        /* Sort Controls */
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* Scrollable Table */
        .table-container {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e0e0ff;
            background: white;
        }

        .meetings-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .meetings-table thead {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .table-body-container {
            max-height: 400px;
            overflow-y: auto;
            overflow-x: auto;
            display: block;
            width: 100%;
        }

        .table-body-container.scrollable {
            border-top: 2px solid #e0e0ff;
        }

        .table-body-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-body-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-body-container::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .table-body-container::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }

        .meetings-table-body {
            border-collapse: collapse;
            width: 100%;
            display: table;
            table-layout: fixed;
        }

        .meetings-table-body tbody {
            display: block;
            width: 100%;
        }

        .meetings-table-body tr {
            display: table;
            width: 100%;
            table-layout: fixed;
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
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f8f9ff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .meetings-table td {
            padding: 20px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
            transition: background 0.3s;
        }

        .meetings-table tbody tr:hover {
            background: #f8f9ff;
        }

        .scroll-indicator {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(102, 126, 234, 0.1));
            color: #667eea;
            font-size: 14px;
            font-weight: 500;
            border-top: 1px solid #e0e0ff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .scroll-indicator i {
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        /* Meeting title cell */
        .meeting-title-cell {
            max-width: 300px;
        }

        .meeting-title {
            font-weight: 600;
            color: #333;
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .meeting-title i {
            color: #667eea;
            font-size: 14px;
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

        /* Date & Time cell */
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

        /* Participants cell */
        .participants-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .participant-count {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
            flex-shrink: 0;
        }

        .participant-details {
            display: flex;
            flex-direction: column;
        }

        .participant-text {
            color: #333;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .participant-subtext {
            color: #888;
            font-size: 12px;
            margin-top: 2px;
            white-space: nowrap;
        }

        /* Status cell */
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

        /* Action buttons cell */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 220px;
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

        .btn-join {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-join:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-view {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-edit {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.3);
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

        .btn-copy {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .btn-copy:hover {
            background: linear-gradient(135deg, #4b5563, #374151);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(107, 114, 128, 0.3);
        }

        /* Meeting owner badge */
        .meeting-owner {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            white-space: nowrap;
        }

        /* Password protected badge */
        .password-protected {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
            margin-left: 5px;
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

        /* Pre-Join Modal */
        .pre-join-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }

        .pre-join-modal.show {
            display: flex;
        }

        .pre-join-container {
            width: 100%;
            max-width: 1200px;
            padding: 20px;
        }

        .pre-join-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .pre-join-header h1 {
            font-size: 36px;
            color: #f1f5f9;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .pre-join-header p {
            color: #94a3b8;
            font-size: 18px;
        }

        .pre-join-header .meeting-title {
            color: #667eea;
            font-weight: 600;
            margin-top: 10px;
        }

        .pre-join-content {
            display: flex;
            gap: 30px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .preview-section {
            flex: 1;
        }

        .preview-container {
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            aspect-ratio: 16/9;
            position: relative;
            border: 2px solid #475569;
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
            color: #94a3b8;
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
            color: #f1f5f9;
        }

        .preview-badge-item i.mic-on,
        .preview-badge-item i.camera-on {
            color: #10b981;
        }

        .preview-badge-item i.mic-off,
        .preview-badge-item i.camera-off {
            color: #ef4444;
        }

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
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .device-group h3 i {
            color: #667eea;
            width: 24px;
        }

        .device-selector {
            width: 100%;
            padding: 12px 15px;
            background: #334155;
            border: 2px solid #475569;
            border-radius: 10px;
            color: #f1f5f9;
            font-size: 14px;
            cursor: pointer;
            outline: none;
            transition: all 0.3s;
            margin-bottom: 10px;
        }

        .device-selector:hover {
            border-color: #667eea;
        }

        .device-selector:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
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
            background: #334155;
            border-radius: 30px;
            border: 1px solid #475569;
            transition: all 0.3s;
            color: #f1f5f9;
        }

        .device-toggle:hover {
            border-color: #667eea;
        }

        .device-toggle.on {
            background: #667eea;
            border-color: #667eea;
        }

        .device-toggle.on i {
            color: white;
        }

        .device-toggle.off {
            background: #334155;
        }

        .device-toggle.off i {
            color: #94a3b8;
        }

        .pre-join-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .pre-join-btn {
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

        .pre-join-btn.cancel {
            background: rgba(255, 255, 255, 0.05);
            color: #94a3b8;
            border: 1px solid #475569;
        }

        .pre-join-btn.cancel:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
        }

        .pre-join-btn.join {
            background: #667eea;
            color: white;
        }

        .pre-join-btn.join:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .pre-join-btn.view {
            background: #3b82f6;
            color: white;
        }

        .pre-join-btn.view:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .loading-preview {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            gap: 15px;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .camera-off-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            gap: 15px;
        }

        .camera-off-overlay i {
            font-size: 60px;
            color: rgba(255, 255, 255, 0.7);
        }

        .camera-off-overlay span {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.9);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
    </style>
</head>
<body>
    <div class="meetings-container">
        <!-- Left Sidebar  -->
        <div class="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> SkyMeet</h1>
            </div>

            <div class="user-profile">
                <div class="user-profile-content" id="userProfileTrigger">
                    <div class="user-avatar">
                        <?php 
                        $profile_photo = getProfilePhoto($current_user);
                        if ($profile_photo): ?>
                            <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="<?php echo htmlspecialchars($username); ?>">
                        <?php else: ?>
                            <?php echo getInitials($username); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($username); ?></h3>
                        <p><?php echo htmlspecialchars($current_user['email'] ?? 'User'); ?></p>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon" id="dropdownIcon"></i>
                </div>
                
                <div class="profile-dropdown-menu" id="profileDropdownMenu">
                    <a href="profile.php?id=<?php echo $user_id; ?>" class="profile-dropdown-item">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="meetings.php" class="nav-item active">
                    <i class="fas fa-video"></i> Meetings
                    <?php if ($upcoming_meetings_count > 0): ?>
                        <span class="nav-badge"><?php echo $upcoming_meetings_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="schedule.php" class="nav-item">
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
                    <h2>My Meetings</h2>
                    <p>Manage your meetings here. Total: <?php echo $total_meetings; ?> meetings</p>
                </div>
                <div class="header-right">
                    <a href="create-meeting.php" class="create-meeting-btn">
                        <i class=""></i>
                        <span>Create New Meeting</span>
                    </a>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-label">
                    <i class="fas fa-search"></i>
                    <span>Search:</span>
                </div>
                
                <form method="GET" action="meetings.php" id="searchForm" class="search-container">
                    <input type="text" name="search" class="search-input" 
                        placeholder="Search by meeting title or description..." 
                        value="<?php echo htmlspecialchars($search_query); ?>">
                    
                    <?php if (!empty($selected_month)): ?>
                        <input type="hidden" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
                    <?php endif; ?>
                    
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_order); ?>">
                    
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if (!empty($search_query)): ?>
                        <a href="meetings.php<?php 
                            $params = [];
                            if (!empty($selected_month)) $params[] = 'month=' . urlencode($selected_month);
                            if (!empty($sort_order)) $params[] = 'sort=' . urlencode($sort_order);
                            echo !empty($params) ? '?' . implode('&', $params) : '';
                        ?>" class="clear-search-btn">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
                
                <div class="filter-label" style="margin-left: auto;">
                    <i class="fas fa-filter"></i>
                    <span>Filter by Month:</span>
                </div>
                
                <form method="GET" action="meetings.php" id="filterForm" style="display: flex; gap: 10px;">
                    <?php if (!empty($search_query)): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>
                    
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_order); ?>">
                    
                    <select name="month" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Months</option>
                        <?php foreach ($available_months as $month): ?>
                            <option value="<?php echo htmlspecialchars($month['month_value']); ?>" 
                                    <?php echo $selected_month == $month['month_value'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($month['month_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if (!empty($selected_month)): ?>
                        <a href="meetings.php<?php 
                            $params = [];
                            if (!empty($search_query)) $params[] = 'search=' . urlencode($search_query);
                            if (!empty($sort_order)) $params[] = 'sort=' . urlencode($sort_order);
                            echo !empty($params) ? '?' . implode('&', $params) : '';
                        ?>" class="clear-search-btn">
                            <i class="fas fa-times"></i> Clear Filter
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_meetings; ?></h3>
                        <p>My Meetings</p>
                    </div>
                </div>
                
                <div class="stat-card upcoming">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $upcoming_count; ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>
                
                <div class="stat-card ongoing">
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $ongoing_count; ?></h3>
                        <p>Ongoing Now</p>
                    </div>
                </div>
                
                <div class="stat-card completed">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $completed_count; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
            </div>

            <!-- Meetings Table -->
            <div class="meetings-container-inner">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-list"></i> 
                        <?php if (!empty($selected_month) && !empty($search_query)): ?>
                            Search Results in <?php echo date('F Y', strtotime($selected_month . '-01')); ?>
                        <?php elseif (!empty($selected_month)): ?>
                            Meetings - <?php echo date('F Y', strtotime($selected_month . '-01')); ?>
                        <?php elseif (!empty($search_query)): ?>
                            Search Results
                        <?php else: ?>
                            All Meetings
                        <?php endif; ?>
                        <?php if ($total_meetings > 0): ?>
                            <span class="row-count-badge"><?php echo $total_meetings; ?> meeting<?php echo $total_meetings > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </h2>
                    
                    <!-- Sort Controls -->
                    <div class="sort-controls">
                        <div class="sort-label">
                            <i class="fas fa-sort-amount-down"></i>
                            <span>Sort by:</span>
                        </div>
                        <div class="sort-buttons">
                            <a href="?<?php 
                                $params = [];
                                if (!empty($search_query)) $params[] = 'search=' . urlencode($search_query);
                                if (!empty($selected_month)) $params[] = 'month=' . urlencode($selected_month);
                                $params[] = 'sort=newest';
                                echo implode('&', $params);
                            ?>" class="sort-btn <?php echo $sort_order == 'newest' ? 'active' : ''; ?>">
                                <i class="fas fa-arrow-down"></i> Newest
                            </a>
                            <a href="?<?php 
                                $params = [];
                                if (!empty($search_query)) $params[] = 'search=' . urlencode($search_query);
                                if (!empty($selected_month)) $params[] = 'month=' . urlencode($selected_month);
                                $params[] = 'sort=oldest';
                                echo implode('&', $params);
                            ?>" class="sort-btn <?php echo $sort_order == 'oldest' ? 'active' : ''; ?>">
                                <i class="fas fa-arrow-up"></i> Oldest
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (empty($all_meetings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Meetings Found</h3>
                        <p>
                            <?php if (!empty($search_query) && !empty($selected_month)): ?>
                                No meetings match your search "<?php echo htmlspecialchars($search_query); ?>" in <?php echo date('F Y', strtotime($selected_month . '-01')); ?>.
                                <br>
                                <a href="meetings.php" style="color: #667eea; text-decoration: none;">Clear all filters</a> to see all meetings.
                            <?php elseif (!empty($search_query)): ?>
                                No meetings match your search "<?php echo htmlspecialchars($search_query); ?>".
                                <br>
                                <a href="meetings.php" style="color: #667eea; text-decoration: none;">Clear search</a> to see all meetings.
                            <?php elseif (!empty($selected_month)): ?>
                                No meetings found in <?php echo date('F Y', strtotime($selected_month . '-01')); ?>.
                                <br>
                                <a href="meetings.php" style="color: #667eea; text-decoration: none;">View all meetings</a> or create a new one.
                            <?php else: ?>
                                You haven't created any meetings. Schedule your first meeting to get started!
                            <?php endif; ?>
                        </p>
                        <?php if (empty($selected_month) && empty($search_query)): ?>
                            <a href="create-meeting.php" class="create-meeting-btn" style="margin-top: 15px; display: inline-flex;">
                                <i class="fas fa-plus-circle"></i> Create Meeting
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-container" id="tableContainer">
                        <table class="meetings-table">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Meeting</th>
                                    <th style="width: 25%;">Date & Time</th>
                                    <th style="width: 15%;">Participants</th>
                                    <th style="width: 15%;">Status</th>
                                    <th style="width: 15%;">Actions</th>
                                </tr>
                            </thead>
                        </table>
                        
                        <!-- Scrollable Table Body -->
                        <div class="table-body-container <?php echo count($all_meetings) > 5 ? 'scrollable' : ''; ?>" id="tableBodyContainer">
                            <table class="meetings-table meetings-table-body">
                                <tbody>
                                    <?php foreach ($all_meetings as $meeting): 
                                        $status = $meeting['status'];
                                        $is_active = $status == 'ongoing' || $status == 'upcoming';
                                        $room_identifier = !empty($meeting['room_id']) ? $meeting['room_id'] : $meeting['id'];
                                        $participant_count = intval($meeting['participant_count'] ?? 0);
                                        $is_protected = isset($meeting['is_password_protected']) && $meeting['is_password_protected'] == 1;
                                        
                                        // Get the original password if available
                                        $original_password = isset($meeting['password']) && $is_protected ? $meeting['password'] : '';
                                    ?>
                                    <tr>
                                        <td class="meeting-title-cell">
                                            <div class="meeting-title">
                                                <i class="fas fa-video"></i>
                                                <?php echo htmlspecialchars($meeting['title']); ?>
                                                <span class="meeting-owner">My Meeting</span>
                                                <?php if ($is_protected): ?>
                                                    <span class="password-protected" title="Password Protected">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="meeting-description">
                                                <?php echo htmlspecialchars($meeting['description'] ?: 'No description'); ?>
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
                                            <div class="participants-info">
                                                <div class="participant-count">
                                                    <?php echo $participant_count; ?>
                                                </div>
                                                <div class="participant-details">
                                                    <div class="participant-text">
                                                        <?php echo $participant_count; ?> Joined
                                                    </div>
                                                    <div class="participant-subtext">
                                                        <?php echo $participant_count == 1 ? 'participant' : 'participants'; ?>
                                                    </div>
                                                </div>
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
                                                <?php if ($is_active): ?>
                                                    <button onclick="openPreJoinModal('<?php echo $room_identifier; ?>', '<?php echo htmlspecialchars(addslashes($meeting['title'])); ?>', 'join')" 
                                                            class="action-btn btn-join" title="Join Meeting">
                                                        <i class="fas fa-video"></i> Join
                                                    </button>
                                                    
                                                    <a href="edit-meeting.php?id=<?php echo $meeting['id']; ?>" 
                                                       class="action-btn btn-edit" title="Edit Meeting">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <button onclick="copyMeetingLink('<?php echo $room_identifier; ?>', <?php echo $is_protected ? 'true' : 'false'; ?>, '<?php echo addslashes($original_password); ?>', event)" 
                                                            class="action-btn btn-copy" title="Copy Invite Link">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    
                                                    <button onclick="confirmDelete(<?php echo $meeting['id']; ?>, '<?php echo htmlspecialchars(addslashes($meeting['title'])); ?>')" 
                                                            class="action-btn btn-delete" title="Delete Meeting">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php elseif ($status == 'completed'): ?>
                                                    <button onclick="openPreJoinModal('<?php echo $room_identifier; ?>', '<?php echo htmlspecialchars(addslashes($meeting['title'])); ?>', 'view')" 
                                                            class="action-btn btn-view" title="View Meeting">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    
                                                    <button onclick="confirmDelete(<?php echo $meeting['id']; ?>, '<?php echo htmlspecialchars(addslashes($meeting['title'])); ?>')" 
                                                            class="action-btn btn-delete" title="Delete Meeting">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Scroll Indicator -->
                        <?php if (count($all_meetings) > 5): ?>
                            <div class="scroll-indicator" id="scrollIndicator">
                                <i class="fas fa-chevron-down"></i> Scroll to view more meetings (<?php echo count($all_meetings); ?> total)
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pre-Join Modal -->
    <div class="pre-join-modal" id="preJoinModal">
        <div class="pre-join-container">
            <div class="pre-join-header">
                <h1 id="modal-title">Join Meeting</h1>
                <p id="meeting-title-display">Meeting Title</p>
                <div class="meeting-title" id="room-id-display"></div>
            </div>
            
            <div class="pre-join-content">
                <div class="preview-section">
                    <div class="preview-container" id="video-preview">
                        <div class="loading-preview" id="loading-preview">
                            <div class="loading-spinner"></div>
                            <span>Loading camera preview...</span>
                        </div>
                        <div class="camera-off-overlay" id="camera-off-overlay" style="display: none;">
                            <i class="fas fa-video-slash"></i>
                            <span>Camera is off</span>
                        </div>
                        <div class="preview-badge" id="preview-badge">
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
                
                <div class="device-section" id="device-section">
                    <div class="device-group">
                        <h3><i class="fas fa-microphone"></i> Microphone</h3>
                        <select class="device-selector" id="mic-select">
                            <option value="">Loading microphones...</option>
                        </select>
                        <div class="device-status">
                            <button class="device-toggle on" id="mic-toggle">
                                <i class="fas fa-microphone"></i>
                                <span>On</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="device-group">
                        <h3><i class="fas fa-video"></i> Camera</h3>
                        <select class="device-selector" id="camera-select">
                            <option value="">Loading cameras...</option>
                        </select>
                        <div class="device-status">
                            <button class="device-toggle on" id="camera-toggle">
                                <i class="fas fa-video"></i>
                                <span>On</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="device-group">
                        <h3><i class="fas fa-volume-up"></i> Speaker</h3>
                        <select class="device-selector" id="speaker-select">
                            <option value="">Loading speakers...</option>
                        </select>
                    </div>
                    
                    <div class="pre-join-actions">
                        <button class="pre-join-btn cancel" id="cancel-join">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button class="pre-join-btn join" id="join-meeting-btn">
                            <i class="fas fa-video"></i> Join Meeting
                        </button>
                    </div>
                </div>
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
                <p style="margin-top: 10px; font-size: 14px; color: #999;">All participant data and meeting recordings will be permanently removed.</p>
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

        document.addEventListener('click', function(e) {
            if (profileDropdownMenu && !profileDropdownMenu.contains(e.target) && !userProfileTrigger.contains(e.target)) {
                profileDropdownMenu.classList.remove('show');
                if (dropdownIcon) {
                    dropdownIcon.style.transform = 'rotate(0)';
                }
            }
        });

        if (profileDropdownMenu) {
            profileDropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        window.addEventListener('pageshow', function(event) {
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        window.addEventListener('popstate', function() {
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                if (profileDropdownMenu) {
                    profileDropdownMenu.classList.remove('show');
                }
                if (dropdownIcon) {
                    dropdownIcon.style.transform = 'rotate(0)';
                }
            }
        });

        let preJoinStream = null;
        let isMicOn = true;
        let isCameraOn = true;
        let selectedRoomId = '';
        let meetingTitle = '';
        let audioDevices = [];
        let videoDevices = [];
        let audioOutputs = [];
        let selectedAudioDevice = '';
        let selectedVideoDevice = '';
        let selectedSpeakerDevice = '';
        let currentMode = 'join';
        let meetingToDelete = null;

        const preJoinModal = document.getElementById('preJoinModal');
        const meetingTitleDisplay = document.getElementById('meeting-title-display');
        const modalTitle = document.getElementById('modal-title');
        const roomIdDisplay = document.getElementById('room-id-display');
        const videoPreview = document.getElementById('video-preview');
        const loadingPreview = document.getElementById('loading-preview');
        const cameraOffOverlay = document.getElementById('camera-off-overlay');
        const previewMicIcon = document.getElementById('preview-mic-icon');
        const previewMicText = document.getElementById('preview-mic-text');
        const previewCameraIcon = document.getElementById('preview-camera-icon');
        const previewCameraText = document.getElementById('preview-camera-text');
        const micToggle = document.getElementById('mic-toggle');
        const cameraToggle = document.getElementById('camera-toggle');
        const micSelect = document.getElementById('mic-select');
        const cameraSelect = document.getElementById('camera-select');
        const speakerSelect = document.getElementById('speaker-select');
        const cancelJoinBtn = document.getElementById('cancel-join');
        const joinMeetingBtn = document.getElementById('join-meeting-btn');
        const deviceSection = document.getElementById('device-section');

        function openPreJoinModal(roomId, title, mode = 'join') {
            selectedRoomId = roomId;
            meetingTitle = title;
            meetingTitleDisplay.textContent = title;
            roomIdDisplay.innerHTML = '<i class="fas fa-hashtag"></i> ' + roomId;
            currentMode = mode;
            
            preJoinModal.style.display = 'flex';
            
            if (mode === 'view') {
                modalTitle.textContent = 'View Meeting';
                joinMeetingBtn.innerHTML = '<i class="fas fa-eye"></i> View Meeting';
                joinMeetingBtn.className = 'pre-join-btn view';
                deviceSection.style.display = 'block';
                initializePreJoin();
            } else {
                modalTitle.textContent = 'Join Meeting';
                joinMeetingBtn.innerHTML = '<i class="fas fa-video"></i> Join Meeting';
                joinMeetingBtn.className = 'pre-join-btn join';
                deviceSection.style.display = 'block';
                initializePreJoin();
            }
        }

        async function initializePreJoin() {
            try {
                await getMediaDevices();
                await startPreview();
            } catch (error) {
                console.error('Error initializing pre-join:', error);
                loadingPreview.innerHTML = `
                    <i class="fas fa-exclamation-triangle" style="font-size: 30px;"></i>
                    <span>Unable to access camera/microphone. Please check permissions.</span>
                `;
            }
        }

        async function getMediaDevices() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    audio: true, 
                    video: true 
                });
                
                stream.getTracks().forEach(track => track.stop());
                
                const devices = await navigator.mediaDevices.enumerateDevices();
                
                audioDevices = devices.filter(device => device.kind === 'audioinput');
                micSelect.innerHTML = '';
                if (audioDevices.length > 0) {
                    audioDevices.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.text = device.label || `Microphone ${micSelect.options.length + 1}`;
                        micSelect.appendChild(option);
                    });
                    selectedAudioDevice = audioDevices[0].deviceId;
                    micSelect.value = selectedAudioDevice;
                } else {
                    micSelect.innerHTML = '<option value="">No microphones found</option>';
                }
                
                videoDevices = devices.filter(device => device.kind === 'videoinput');
                cameraSelect.innerHTML = '';
                if (videoDevices.length > 0) {
                    videoDevices.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.text = device.label || `Camera ${cameraSelect.options.length + 1}`;
                        cameraSelect.appendChild(option);
                    });
                    selectedVideoDevice = videoDevices[0].deviceId;
                    cameraSelect.value = selectedVideoDevice;
                } else {
                    cameraSelect.innerHTML = '<option value="">No cameras found</option>';
                }
                
                audioOutputs = devices.filter(device => device.kind === 'audiooutput');
                speakerSelect.innerHTML = '';
                if (audioOutputs.length > 0) {
                    audioOutputs.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.text = device.label || `Speaker ${speakerSelect.options.length + 1}`;
                        speakerSelect.appendChild(option);
                    });
                    selectedSpeakerDevice = audioOutputs[0].deviceId;
                    speakerSelect.value = selectedSpeakerDevice;
                } else {
                    speakerSelect.innerHTML = '<option value="">No speakers found</option>';
                }
                
            } catch (error) {
                console.error('Error getting media devices:', error);
                micSelect.innerHTML = '<option value="">No microphones found</option>';
                cameraSelect.innerHTML = '<option value="">No cameras found</option>';
                speakerSelect.innerHTML = '<option value="">No speakers found</option>';
                throw error;
            }
        }

        async function startPreview() {
            try {
                if (preJoinStream) {
                    preJoinStream.getTracks().forEach(track => track.stop());
                }
                
                const constraints = {
                    audio: selectedAudioDevice ? { deviceId: { exact: selectedAudioDevice } } : true,
                    video: selectedVideoDevice ? { 
                        deviceId: { exact: selectedVideoDevice },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } : true
                };
                
                preJoinStream = await navigator.mediaDevices.getUserMedia(constraints);
                
                const existingVideo = videoPreview.querySelector('video');
                if (existingVideo) {
                    existingVideo.remove();
                }
                
                const videoElement = document.createElement('video');
                videoElement.autoplay = true;
                videoElement.muted = true;
                videoElement.playsInline = true;
                videoPreview.appendChild(videoElement);
                videoElement.srcObject = preJoinStream;
                
                loadingPreview.style.display = 'none';
                cameraOffOverlay.style.display = isCameraOn ? 'none' : 'flex';
                
                updatePreviewControls();
                
            } catch (error) {
                console.error('Error starting preview:', error);
                loadingPreview.innerHTML = `
                    <i class="fas fa-exclamation-triangle" style="font-size: 30px;"></i>
                    <span>Failed to start camera preview</span>
                `;
            }
        }

        function updatePreviewControls() {
            if (isMicOn) {
                micToggle.classList.remove('off');
                micToggle.classList.add('on');
                micToggle.innerHTML = '<i class="fas fa-microphone"></i><span>On</span>';
                previewMicIcon.className = 'fas fa-microphone mic-on';
                previewMicText.textContent = 'Mic On';
            } else {
                micToggle.classList.add('off');
                micToggle.classList.remove('on');
                micToggle.innerHTML = '<i class="fas fa-microphone-slash"></i><span>Off</span>';
                previewMicIcon.className = 'fas fa-microphone-slash mic-off';
                previewMicText.textContent = 'Mic Off';
            }
            
            if (isCameraOn) {
                cameraToggle.classList.remove('off');
                cameraToggle.classList.add('on');
                cameraToggle.innerHTML = '<i class="fas fa-video"></i><span>On</span>';
                previewCameraIcon.className = 'fas fa-video camera-on';
                previewCameraText.textContent = 'Camera On';
                cameraOffOverlay.style.display = 'none';
            } else {
                cameraToggle.classList.add('off');
                cameraToggle.classList.remove('on');
                cameraToggle.innerHTML = '<i class="fas fa-video-slash"></i><span>Off</span>';
                previewCameraIcon.className = 'fas fa-video-slash camera-off';
                previewCameraText.textContent = 'Camera Off';
                cameraOffOverlay.style.display = 'flex';
            }
            
            if (preJoinStream) {
                preJoinStream.getAudioTracks().forEach(track => {
                    track.enabled = isMicOn;
                });
                
                preJoinStream.getVideoTracks().forEach(track => {
                    track.enabled = isCameraOn;
                });
            }
        }

        if (micToggle) {
            micToggle.addEventListener('click', () => {
                isMicOn = !isMicOn;
                updatePreviewControls();
            });
        }

        if (cameraToggle) {
            cameraToggle.addEventListener('click', () => {
                isCameraOn = !isCameraOn;
                updatePreviewControls();
            });
        }

        if (micSelect) {
            micSelect.addEventListener('change', async () => {
                selectedAudioDevice = micSelect.value;
                await startPreview();
            });
        }

        if (cameraSelect) {
            cameraSelect.addEventListener('change', async () => {
                selectedVideoDevice = cameraSelect.value;
                await startPreview();
            });
        }

        if (speakerSelect) {
            speakerSelect.addEventListener('change', async () => {
                selectedSpeakerDevice = speakerSelect.value;
            });
        }

        if (cancelJoinBtn) {
            cancelJoinBtn.addEventListener('click', closePreJoinModal);
        }

        if (joinMeetingBtn) {
            joinMeetingBtn.addEventListener('click', () => {
                joinMeeting();
            });
        }

        function closePreJoinModal() {
            if (preJoinStream) {
                preJoinStream.getTracks().forEach(track => track.stop());
                preJoinStream = null;
            }
            
            preJoinModal.style.display = 'none';
            
            isMicOn = true;
            isCameraOn = true;
            currentMode = 'join';
            
            const videoElement = videoPreview.querySelector('video');
            if (videoElement) {
                videoElement.remove();
            }
            
            loadingPreview.style.display = 'flex';
            loadingPreview.innerHTML = `
                <div class="loading-spinner"></div>
                <span>Loading camera preview...</span>
            `;
        }

        function joinMeeting() {
            if (currentMode === 'view') {
                window.location.href = `meeting_room.php?room=${selectedRoomId}&mode=view`;
                return;
            }
            
            sessionStorage.setItem('meeting_settings', JSON.stringify({
                micOn: isMicOn,
                cameraOn: isCameraOn,
                audioDevice: selectedAudioDevice,
                videoDevice: selectedVideoDevice,
                speakerDevice: selectedSpeakerDevice,
                mode: 'join'
            }));
            
            const params = new URLSearchParams({
                room: selectedRoomId,
                mic: isMicOn ? 'on' : 'off',
                camera: isCameraOn ? 'on' : 'off',
                mode: 'join'
            });
            
            window.location.href = `meeting_room.php?${params.toString()}`;
        }

        if (preJoinModal) {
            preJoinModal.addEventListener('click', (event) => {
                if (event.target === preJoinModal) {
                    closePreJoinModal();
                }
            });
        }

        function confirmDelete(meetingId, meetingTitle) {
            meetingToDelete = meetingId;
            const message = `Are you sure you want to delete "${meetingTitle}"? This action cannot be undone.`;
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
                const monthParam = currentUrl.searchParams.get('month');
                const searchParam = currentUrl.searchParams.get('search');
                const sortParam = currentUrl.searchParams.get('sort');
                let deleteUrl = 'meetings.php?delete=' + meetingToDelete;
                if (monthParam) deleteUrl += '&month=' + encodeURIComponent(monthParam);
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

        // copyMeetingLink function
        function copyMeetingLink(roomIdentifier, isProtected, password, event) {
            // Prevent event bubbling
            if (event) {
                event.stopPropagation();
            }
            
            // Get the base URL 
            const baseUrl = window.location.origin + '/meeting/meeting_room.php';
            
            // Start with the room parameter
            let meetingLink = baseUrl + '?room=' + encodeURIComponent(roomIdentifier);
            
            // If meeting is password protected and we have the password, include it in the link
            if (isProtected && password && password !== '') {
                meetingLink += '&password=' + encodeURIComponent(password);
            }
            
            // Use modern clipboard API if available
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(meetingLink).then(() => {
                    showCopyToast('Meeting link copied to clipboard!', 'success', event);
                }).catch(() => {
                    // Fallback to execCommand
                    fallbackCopy(meetingLink, event);
                });
            } else {
                // Fallback for older browsers
                fallbackCopy(meetingLink, event);
            }
        }
        
        // Fallback copy method using execCommand
        function fallbackCopy(text, event) {
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            tempInput.setSelectionRange(0, 99999);
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopyToast('Meeting link copied to clipboard!', 'success', event);
                } else {
                    showCopyToast('Failed to copy link. Please copy manually.', 'error', event);
                }
            } catch (err) {
                showCopyToast('Failed to copy link. Please copy manually.', 'error', event);
            }
            
            document.body.removeChild(tempInput);
        }
        
        // Show toast notification for copy action
        function showCopyToast(message, type, event) {
            // Remove any existing toasts
            const existingToast = document.querySelector('.copy-toast');
            if (existingToast) {
                existingToast.remove();
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = 'copy-toast ' + type;
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(toast);
            
            // Animate the button if event is provided
            if (event && event.currentTarget) {
                const originalBtn = event.currentTarget;
                const originalHTML = originalBtn.innerHTML;
                originalBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                originalBtn.style.background = '#10b981';
                
                setTimeout(() => {
                    originalBtn.innerHTML = originalHTML;
                    originalBtn.style.background = '';
                }, 2000);
            }
            
            // Remove toast after 3 seconds
            setTimeout(() => {
                toast.style.animation = 'fadeOutUp 0.3s ease forwards';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }, 3000);
        }

        // Initialize scroll indicator for table
        document.addEventListener('DOMContentLoaded', function() {
            const tableBodyContainer = document.getElementById('tableBodyContainer');
            const scrollIndicator = document.getElementById('scrollIndicator');
            
            if (tableBodyContainer && scrollIndicator) {
                tableBodyContainer.addEventListener('scroll', function() {
                    const isAtBottom = tableBodyContainer.scrollHeight - tableBodyContainer.scrollTop <= tableBodyContainer.clientHeight + 10;
                    
                    if (isAtBottom) {
                        scrollIndicator.style.opacity = '0.3';
                        scrollIndicator.innerHTML = '<i class="fas fa-check-circle"></i> End of list';
                    } else {
                        scrollIndicator.style.opacity = '1';
                        scrollIndicator.innerHTML = '<i class="fas fa-chevron-down"></i> Scroll to view more meetings (<?php echo count($all_meetings); ?> total)';
                    }
                });
            }
        });

        // Add keyboard shortcut for creating meeting (Ctrl+N)
        document.addEventListener('keydown', function(event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
                event.preventDefault();
                window.location.href = 'create-meeting.php';
            }
        });

        const successMessage = document.getElementById('successMessage');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.remove();
                }, 300);
            }, 3000);
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
                if (preJoinModal.style.display === 'flex') {
                    closePreJoinModal();
                }
            }
            
            if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
                event.preventDefault();
                window.location.href = 'create-meeting.php';
            }
        });

        window.openPreJoinModal = openPreJoinModal;
        window.confirmDelete = confirmDelete;
        window.closeDeleteModal = closeDeleteModal;
        window.copyMeetingLink = copyMeetingLink;
    </script>
</body>
</html>