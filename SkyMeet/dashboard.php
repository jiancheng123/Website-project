<?php
session_start();
require_once 'connect.php';

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

// Check if meetings table exists
$meetings_table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'meetings'");
if ($check_table && $check_table->num_rows > 0) {
    $meetings_table_exists = true;
}

// GET UNREAD MESSAGE COUNTS 
// Get unread private messages count
$unread_private_sql = "SELECT COUNT(*) as count FROM messages 
                       WHERE receiver_id = ? AND is_read = 0 AND is_deleted = 0";
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
                     AND gm.sender_id != ?
                     AND gm.is_deleted = 0";
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

// Total unread messages (including friend requests for Messages badge)
$total_unread_messages = $unread_private_count + $unread_group_count + $pending_requests_count;
// END UNREAD MESSAGE COUNTS 

// Get user's meeting statistics with proper status calculation
$stats = [
    'total_meetings' => 0,
    'today_meetings' => 0,
    'upcoming_meetings' => 0,
    'ongoing_meetings' => 0,
    'completed_meetings' => 0,
    'scheduled_meetings' => 0,
    'team_members' => 0,
    'meeting_hours' => 0
];

if ($meetings_table_exists) {
    // Get total meetings
    $total_sql = "SELECT COUNT(*) as total FROM meetings WHERE host_id = ?";
    $total_stmt = $conn->prepare($total_sql);
    if ($total_stmt) {
        $total_stmt->bind_param("i", $user_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_data = $total_result->fetch_assoc();
        $stats['total_meetings'] = $total_data['total'] ?: 0;
        $total_stmt->close();
    }
    
    // Get today's meetings (any meeting happening today)
    $today_sql = "SELECT COUNT(*) as today FROM meetings 
                  WHERE host_id = ? AND DATE(meeting_date) = CURDATE()";
    $today_stmt = $conn->prepare($today_sql);
    if ($today_stmt) {
        $today_stmt->bind_param("i", $user_id);
        $today_stmt->execute();
        $today_result = $today_stmt->get_result();
        $today_data = $today_result->fetch_assoc();
        $stats['today_meetings'] = $today_data['today'] ?: 0;
        $today_stmt->close();
    }
    
    // Get upcoming meetings (future meetings that haven't started)
    $upcoming_sql = "SELECT COUNT(*) as upcoming FROM meetings 
                     WHERE host_id = ? 
                     AND (
                         meeting_date > CURDATE() 
                         OR (meeting_date = CURDATE() AND start_time > CURTIME())
                     )";
    $upcoming_stmt = $conn->prepare($upcoming_sql);
    if ($upcoming_stmt) {
        $upcoming_stmt->bind_param("i", $user_id);
        $upcoming_stmt->execute();
        $upcoming_result = $upcoming_stmt->get_result();
        $upcoming_data = $upcoming_result->fetch_assoc();
        $stats['upcoming_meetings'] = $upcoming_data['upcoming'] ?: 0;
        $upcoming_stmt->close();
    }
    
    // Get ongoing meetings (meetings currently happening)
    $ongoing_sql = "SELECT COUNT(*) as ongoing FROM meetings 
                    WHERE host_id = ? 
                    AND meeting_date = CURDATE() 
                    AND start_time <= CURTIME() 
                    AND end_time > CURTIME()";
    $ongoing_stmt = $conn->prepare($ongoing_sql);
    if ($ongoing_stmt) {
        $ongoing_stmt->bind_param("i", $user_id);
        $ongoing_stmt->execute();
        $ongoing_result = $ongoing_stmt->get_result();
        $ongoing_data = $ongoing_result->fetch_assoc();
        $stats['ongoing_meetings'] = $ongoing_data['ongoing'] ?: 0;
        $ongoing_stmt->close();
    }
    
    // Get scheduled meetings count 
    $stats['scheduled_meetings'] = $stats['upcoming_meetings'];
}

// Get today's meetings count for Meetings badge
$today_meetings_count = $stats['today_meetings'];

// Get scheduled meetings count for Schedule badge
$scheduled_meetings_count = $stats['scheduled_meetings'];

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

// Get ongoing meetings for display
$ongoing_meetings = [];
if ($meetings_table_exists) {
    $ongoing_sql = "SELECT * FROM meetings 
                    WHERE host_id = ? 
                    AND meeting_date = CURDATE() 
                    AND start_time <= CURTIME() 
                    AND end_time > CURTIME()
                    ORDER BY start_time";
    $ongoing_stmt = $conn->prepare($ongoing_sql);
    if ($ongoing_stmt) {
        $ongoing_stmt->bind_param("i", $user_id);
        $ongoing_stmt->execute();
        $ongoing_result = $ongoing_stmt->get_result();
        if ($ongoing_result) {
            $ongoing_meetings = $ongoing_result->fetch_all(MYSQLI_ASSOC);
        }
        $ongoing_stmt->close();
    }
}

// Handle meeting join by room ID
$join_message = '';
$join_message_type = ''; // 'error' or 'success'
$room_id_to_join = '';
if (isset($_POST['search_room_id']) && !empty(trim($_POST['search_room_id']))) {
    $room_id = trim($_POST['search_room_id']);
    
    // Check if meeting exists
    $check_meeting_sql = "SELECT * FROM meetings WHERE room_id = ?";
    $check_stmt = $conn->prepare($check_meeting_sql);
    if ($check_stmt) {
        $check_stmt->bind_param("s", $room_id);
        $check_stmt->execute();
        $meeting_result = $check_stmt->get_result();
        
        if ($meeting_result->num_rows > 0) {
            $room_id_to_join = $room_id;
            $join_message = '✓ Meeting found! Redirecting to pre-join...';
            $join_message_type = 'success';
        } else {
            $join_message = '❌ Meeting not found or invalid room ID';
            $join_message_type = 'error';
        }
        $check_stmt->close();
    }
}

// FRIENDS ONLY SECTION 
// Get ONLY friends (users who are friends with the current user)

// Check if friends table exists
$friends_table_exists = false;
$check_friends_table = $conn->query("SHOW TABLES LIKE 'friends'");
if ($check_friends_table && $check_friends_table->num_rows > 0) {
    $friends_table_exists = true;
}

// If friends table doesn't exist, create it
if (!$friends_table_exists) {
    $create_friends_table_sql = "CREATE TABLE IF NOT EXISTS friends (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        friend_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_friendship (user_id, friend_id)
    )";
    $conn->query($create_friends_table_sql);
    $friends_table_exists = true;
}

// Get ONLY friends (accepted friendships) - NOT all users
$team_members = [];
$all_friends = []; // Store all friends for the modal
$total_friends_count = 0;
if ($friends_table_exists) {
    // Get friends where current user is either the requester or the receiver AND status is 'accepted'
    // First get total count
    $count_sql = "SELECT COUNT(DISTINCT u.id) as total
                  FROM users u
                  WHERE u.id != ? 
                  AND u.id IN (
                      SELECT f.friend_id FROM friends f WHERE f.user_id = ? AND f.status = 'accepted'
                      UNION
                      SELECT f.user_id FROM friends f WHERE f.friend_id = ? AND f.status = 'accepted'
                  )";
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt) {
        $count_stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();
        $total_friends_count = $count_data['total'] ?? 0;
        $count_stmt->close();
    }
    
    // Now get friends for display (limit to 4)
    $team_sql = "SELECT DISTINCT u.id, u.username, u.email, u.role, u.profile_photo, u.last_activity 
                 FROM users u
                 WHERE u.id != ? 
                 AND u.id IN (
                     SELECT f.friend_id FROM friends f WHERE f.user_id = ? AND f.status = 'accepted'
                     UNION
                     SELECT f.user_id FROM friends f WHERE f.friend_id = ? AND f.status = 'accepted'
                 )
                 ORDER BY u.last_activity DESC, u.id DESC 
                 LIMIT 4";
    $team_stmt = $conn->prepare($team_sql);
    if ($team_stmt) {
        $team_stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        if ($team_result) {
            $team_members = $team_result->fetch_all(MYSQLI_ASSOC);
            // Team members count = number of friends only (not including current user)
            $stats['team_members'] = $total_friends_count;
        }
        $team_stmt->close();
    }
    
    // Get ALL friends for the modal
    $all_friends_sql = "SELECT DISTINCT u.id, u.username, u.email, u.role, u.profile_photo, u.last_activity 
                        FROM users u
                        WHERE u.id != ? 
                        AND u.id IN (
                            SELECT f.friend_id FROM friends f WHERE f.user_id = ? AND f.status = 'accepted'
                            UNION
                            SELECT f.user_id FROM friends f WHERE f.friend_id = ? AND f.status = 'accepted'
                        )
                        ORDER BY u.last_activity DESC, u.username ASC";
    $all_friends_stmt = $conn->prepare($all_friends_sql);
    if ($all_friends_stmt) {
        $all_friends_stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $all_friends_stmt->execute();
        $all_friends_result = $all_friends_stmt->get_result();
        if ($all_friends_result) {
            $all_friends = $all_friends_result->fetch_all(MYSQLI_ASSOC);
        }
        $all_friends_stmt->close();
    }
} else {
    // Fallback if friends table doesn't exist - show NO friends
    $team_members = [];
    $all_friends = [];
    $stats['team_members'] = 0; // No friends
}

// SEARCH HISTORY FUNCTIONALITY 
// Check if search_history table exists, if not create it
$search_history_table_exists = false;
$check_search_table = $conn->query("SHOW TABLES LIKE 'search_history'");
if ($check_search_table && $check_search_table->num_rows > 0) {
    $search_history_table_exists = true;
}

// Create search_history table if it doesn't exist
if (!$search_history_table_exists) {
    $create_search_table_sql = "CREATE TABLE IF NOT EXISTS search_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        search_query VARCHAR(255) NOT NULL,
        search_type ENUM('room_id', 'meeting', 'user') DEFAULT 'room_id',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_search (user_id, created_at),
        UNIQUE KEY unique_user_query (user_id, search_query)
    )";
    $conn->query($create_search_table_sql);
    $search_history_table_exists = true;
}

// Define sensitive words filter
$sensitive_words = [
    'admin', 'root', 'system', 'hack', 'exploit', 'vulnerability', 
    'malware', 'virus', 'trojan', 'phishing', 'scam', 'fraud',
    'illegal', 'unlawful', 'banned', 'prohibited', 'offensive',
    'racist', 'discrimination', 'harassment', 'bullying',
    'explicit', 'porn', 'xxx', 'adult', 'nsfw',
    'gambling', 'casino', 'bet', 'lottery',
    'terrorism', 'extremist', 'violent',
    'drugs', 'cocaine', 'heroin', 'marijuana', 'cannabis',
    'weapon', 'gun', 'bomb', 'explosive',
    'spam', 'bot', 'automated', 'script',
    'fake', 'counterfeit', 'stolen', 'hijack'
];

// Function to validate room ID
function validateRoomId($room_id, $sensitive_words) {
    // Check length (exactly 12 characters for meeting room ID)
    if (strlen($room_id) != 12) {
        return ['valid' => false, 'message' => 'Room ID must be exactly 12 characters'];
    }
    
    // Check if contains only allowed characters (alphanumeric)
    if (!preg_match('/^[a-zA-Z0-9]+$/', $room_id)) {
        return ['valid' => false, 'message' => 'Room ID can only contain letters and numbers'];
    }
    
    // Check for sensitive words (case insensitive)
    $room_id_lower = strtolower($room_id);
    foreach ($sensitive_words as $word) {
        if (strpos($room_id_lower, $word) !== false) {
            return ['valid' => false, 'message' => 'Room ID contains prohibited content'];
        }
    }
    
    return ['valid' => true, 'message' => ''];
}

// Handle saving search query from AJAX (with duplicate prevention)
if (isset($_POST['save_search']) && isset($_POST['search_query']) && !empty(trim($_POST['search_query']))) {
    header('Content-Type: application/json');
    
    $search_query = trim($_POST['search_query']);
    $search_type = isset($_POST['search_type']) ? $_POST['search_type'] : 'room_id';
    
    // Validate room ID
    $validation = validateRoomId($search_query, $sensitive_words);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'message' => $validation['message']]);
        exit();
    }
    
    // First check if this query already exists for this user
    $check_sql = "SELECT id FROM search_history WHERE user_id = ? AND search_query = ?";
    $check_stmt = $conn->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param("is", $user_id, $search_query);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Query exists - update the timestamp instead of inserting duplicate
            $update_sql = "UPDATE search_history SET created_at = CURRENT_TIMESTAMP WHERE user_id = ? AND search_query = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("is", $user_id, $search_query);
                $update_stmt->execute();
                $update_stmt->close();
                echo json_encode(['success' => true, 'message' => 'Search timestamp updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            // Query doesn't exist - insert new record
            $insert_search_sql = "INSERT INTO search_history (user_id, search_query, search_type) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_search_sql);
            if ($insert_stmt) {
                $insert_stmt->bind_param("iss", $user_id, $search_query, $search_type);
                if ($insert_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Search saved']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save search']);
                }
                $insert_stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        }
        $check_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// Handle getting search history for current user
if (isset($_GET['get_search_history'])) {
    header('Content-Type: application/json');
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    
    $get_history_sql = "SELECT id, search_query, search_type, created_at 
                        FROM search_history 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT ?";
    $get_stmt = $conn->prepare($get_history_sql);
    if ($get_stmt) {
        $get_stmt->bind_param("ii", $user_id, $limit);
        $get_stmt->execute();
        $history_result = $get_stmt->get_result();
        $history = [];
        while ($row = $history_result->fetch_assoc()) {
            $history[] = [
                'id' => $row['id'],
                'search_query' => $row['search_query'],
                'search_type' => $row['search_type'],
                'created_at' => $row['created_at'],
                'formatted_time' => date('M d, H:i', strtotime($row['created_at']))
            ];
        }
        echo json_encode(['success' => true, 'history' => $history]);
        $get_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to get search history']);
    }
    exit();
}

// Handle clearing all search history
if (isset($_POST['clear_search_history'])) {
    header('Content-Type: application/json');
    
    $clear_sql = "DELETE FROM search_history WHERE user_id = ?";
    $clear_stmt = $conn->prepare($clear_sql);
    if ($clear_stmt) {
        $clear_stmt->bind_param("i", $user_id);
        if ($clear_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Search history cleared']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clear history']);
        }
        $clear_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// Handle deleting a single search history item
if (isset($_POST['delete_search_item']) && isset($_POST['search_id'])) {
    header('Content-Type: application/json');
    
    $search_id = intval($_POST['search_id']);
    
    $delete_sql = "DELETE FROM search_history WHERE id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $search_id, $user_id);
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Search item deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
        }
        $delete_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
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

// Check if we should auto-open friends modal (from session or URL parameter)
$auto_open_friends = isset($_GET['open_friends']) && $_GET['open_friends'] == 1;
if ($auto_open_friends) {
    // Store in session to persist through navigation
    $_SESSION['open_friends_modal'] = true;
} else {
    // Check if we have session flag
    $auto_open_friends = isset($_SESSION['open_friends_modal']) && $_SESSION['open_friends_modal'] === true;
    // Clear the flag after using it
    if ($auto_open_friends) {
        unset($_SESSION['open_friends_modal']);
    }
}

// Clear any session flags that might cause auto-focus on search
unset($_SESSION['focus_search']);

// Function to check if search input should be valid (no red color on page load)
$search_has_value = isset($_POST['search_room_id']) && !empty(trim($_POST['search_room_id']));
$search_value = $search_has_value ? trim($_POST['search_room_id']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SkyMeet</title>
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
            position: relative;
        }

        /* Toast Notification Container */
        .toast-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            pointer-events: none;
            width: 100%;
            max-width: 400px;
        }

        .toast-notification {
            background: white;
            color: #333;
            padding: 16px 25px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 14px;
            transform: translateY(-150px);
            opacity: 0;
            transition: all 0.3s ease;
            width: 100%;
            border-left: 5px solid;
            pointer-events: auto;
            animation: slideInDown 0.3s ease forwards;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-150px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .toast-notification.success {
            background: #e8f5e9;
            border-left-color: #4caf50;
            color: #2e7d32;
        }

        .toast-notification.error {
            background: #fee;
            border-left-color: #f44336;
            color: #c62828;
        }

        .toast-notification.info {
            background: #e3f2fd;
            border-left-color: #2196f3;
            color: #1565c0;
        }

        .toast-notification i:first-child {
            font-size: 20px;
        }

        .toast-notification span {
            flex: 1;
        }

        .toast-close {
            margin-left: auto;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
            font-size: 16px;
        }

        .toast-close:hover {
            opacity: 1;
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

        /* Stats Grid - Make them clickable */
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
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }

        .stat-card:active {
            transform: translateY(-2px);
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

        .stat-card.teams {
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

        .stat-card.today .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-card.upcoming .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-card.teams .stat-icon {
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

        .stat-card.today .stat-info h3 {
            color: #10b981;
        }

        .stat-card.upcoming .stat-info h3 {
            color: #f59e0b;
        }

        .stat-card.teams .stat-info h3 {
            color: #8b5cf6;
        }

        .stat-info p {
            color: #666;
            font-size: 14px;
        }

        /* Search and Join Section */
        .search-join-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            margin-top: 30px;
            position: relative;
        }

        .search-join-container {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-wrapper {
            flex: 1;
            position: relative;
        }

        .search-box {
            position: relative;
            width: 100%;
        }

        .search-box input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-size: 16px;
            transition: all 0.3s;
            text-transform: lowercase;
            background: white;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Only show invalid style when actually invalid AND not on page load */
        .search-box input.invalid:not(.initial-load) {
            border-color: #f44336;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            z-index: 1;
        }

        .char-counter {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            color: #999;
            background: #f5f5f5;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
            z-index: 2;
        }

        .char-counter.warning {
            color: #f59e0b;
            background: #fff3e0;
        }

        .char-counter.error {
            color: #f44336;
            background: #ffebee;
        }

        .char-counter.valid {
            color: #10b981;
            background: #e8f5e9;
        }

        .search-history-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin-top: 5px;
            z-index: 1000;
            display: none;
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
        }

        .search-history-dropdown.show {
            display: block;
        }

        .search-history-dropdown.scrollable {
            overflow-y: auto;
        }

        .search-history-dropdown::-webkit-scrollbar {
            width: 6px;
        }

        .search-history-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .search-history-dropdown::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .search-history-dropdown::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .search-history-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9ff;
            border-radius: 12px 12px 0 0;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .search-history-header h4 {
            font-size: 14px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-history-header h4 i {
            color: #667eea;
        }

        .clear-history-btn {
            background: none;
            border: none;
            color: #f44336;
            font-size: 12px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .clear-history-btn:hover {
            background: #ffebee;
        }

        .search-history-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .search-history-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
            position: relative;
        }

        .search-history-item:hover {
            background: #f8f9ff;
        }

        .search-history-item .search-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            cursor: pointer;
        }

        .search-history-item .search-icon {
            width: 32px;
            height: 32px;
            background: #f0f5ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }

        .search-history-item .search-details {
            flex: 1;
        }

        .search-history-item .search-query {
            font-weight: 500;
            color: #333;
            margin-bottom: 4px;
            font-size: 14px;
            font-family: monospace;
        }

        .search-history-item .search-meta {
            font-size: 11px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-history-item .search-time {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .search-history-item .search-time i {
            font-size: 10px;
        }

        .search-history-item .remove-search-btn {
            background: none;
            border: none;
            color: #ccc;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s;
            opacity: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-history-item:hover .remove-search-btn {
            opacity: 1;
        }

        .search-history-item .remove-search-btn:hover {
            color: #f44336;
            background: #ffebee;
        }

        .search-history-empty {
            padding: 40px 20px;
            text-align: center;
            color: #999;
        }

        .search-history-empty i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        .search-history-empty p {
            font-size: 14px;
        }

        .search-history-loading {
            padding: 30px;
            text-align: center;
            color: #667eea;
        }

        .search-history-loading .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .join-btn {
            padding: 14px 30px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }

        .join-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .join-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
        }

        .join-btn:hover:not(:disabled)::before {
            width: 300px;
            height: 300px;
        }

        .join-btn:active:not(:disabled) {
            transform: translateY(-1px);
        }

        .join-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #9ca3af;
            box-shadow: none;
        }

        /* Enhanced Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            border: 2px solid transparent;
            letter-spacing: 0.3px;
        }

        .btn-primary::before {
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
            z-index: 0;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-primary:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary:active {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary i {
            position: relative;
            z-index: 1;
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .btn-primary span {
            position: relative;
            z-index: 1;
        }

        .btn-primary:hover i {
            transform: translateX(3px) scale(1.1);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            border: 2px solid transparent;
            letter-spacing: 0.3px;
        }

        .btn-secondary::before {
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
            z-index: 0;
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-secondary:active {
            transform: translateY(-1px);
        }

        .btn-secondary i {
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .btn-secondary:hover i {
            transform: rotate(90deg) scale(1.1);
        }

        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-outline::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
            z-index: -1;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-outline:hover::before {
            left: 100%;
        }

        .btn-outline:active {
            transform: translateY(0);
        }

        .btn-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-icon::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.4s, height 0.4s;
        }

        .btn-icon:hover {
            transform: translateY(-3px) rotate(360deg);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }

        .btn-icon:hover::before {
            width: 100px;
            height: 100px;
        }

        .btn-icon:active {
            transform: translateY(-1px);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

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
            margin-top: 15px;
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

        .add-friends-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            border: 2px solid transparent;
        }

        .add-friends-btn::before {
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

        .add-friends-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.5);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .add-friends-btn:hover::before {
            width: 350px;
            height: 350px;
        }

        .add-friends-btn:active {
            transform: translateY(-1px);
        }

        .add-friends-btn i {
            transition: transform 0.3s ease;
        }

        .add-friends-btn:hover i {
            transform: scale(1.2);
        }

        .action-buttons-container {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: flex-start;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Sections */
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .section-card:hover {
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        .section-header {
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-header h3 {
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h3 i {
            color: #667eea;
            font-size: 20px;
        }

        .section-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ongoing-meetings-container {
            max-height: 300px;
            overflow-y: hidden;
            padding-right: 10px;
            transition: max-height 0.3s ease;
        }
        
        .ongoing-meetings-container.scrollable {
            overflow-y: auto;
        }
        
        .scroll-indicator {
            text-align: center;
            margin-top: 10px;
            color: #667eea;
            font-size: 12px;
            display: none;
        }
        
        .scroll-indicator.show {
            display: block;
        }
        
        .scroll-indicator i {
            margin-right: 5px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        .ongoing-meetings-container::-webkit-scrollbar {
            width: 6px;
        }

        .ongoing-meetings-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .ongoing-meetings-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .ongoing-meetings-container::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .meetings-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .meeting-item {
            padding: 18px;
            border: 1px solid #eee;
            border-radius: 12px;
            transition: all 0.3s;
            min-height: 100px;
            cursor: pointer;
            background: #fafbff;
        }

        .meeting-item:hover {
            border-color: #667eea;
            background: #f0f4ff;
            transform: translateX(8px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .meeting-item:active {
            transform: translateX(4px);
        }

        .meeting-info h4 {
            font-size: 15px;
            margin-bottom: 8px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meeting-info h4 i {
            color: #667eea;
            font-size: 14px;
        }

        .meeting-meta {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 13px;
            flex-wrap: wrap;
        }

        .meeting-meta i {
            margin-right: 5px;
            color: #667eea;
        }

        .meeting-time {
            background: #e8edff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            color: #667eea;
            font-weight: 500;
        }

        .message-alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .message-alert.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .message-alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Team/Friends Section - Horizontal Scrollable */
        .team-grid {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 5px 0 15px 0;
            scrollbar-width: thin;
            scrollbar-color: #667eea #e0e0e0;
        }

        .team-grid::-webkit-scrollbar {
            height: 6px;
        }

        .team-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .team-grid::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .team-grid::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }

        .team-member {
            text-decoration: none;
            color: inherit;
            display: block;
            text-align: center;
            padding: 15px;
            border-radius: 12px;
            background: #f8f9ff;
            transition: all 0.3s;
            position: relative;
            border: 1px solid transparent;
            min-width: 100px;
            flex: 0 0 auto;
            cursor: pointer;
        }

        .team-member:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
            background: white;
        }

        .team-member:active {
            transform: translateY(-2px);
        }

        .member-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .member-avatar:hover {
            transform: scale(1.1);
        }

        .member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100px;
            cursor: pointer;
        }

        .member-name:hover {
            color: #667eea;
            text-decoration: underline;
        }

        .member-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin: 0 auto 5px;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            bottom: 0;
            right: 0;
            display: inline-block;
        }

        .status-online {
            background: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
            animation: pulse-green 1.5s ease-in-out infinite;
        }

        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.1);
                transform: scale(1.1);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
                transform: scale(1);
            }
        }

        .status-offline {
            background: #9ca3af;
            box-shadow: none;
            animation: none;
        }

        .last-active {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
            text-align: center;
            cursor: pointer;
        }

        .real-time-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #666;
            margin-left: 10px;
        }

        .real-time-indicator i {
            color: #10b981;
            animation: pulse-green 1.5s ease-in-out infinite;
            font-size: 8px;
        }

        #online-badge {
            transition: background-color 0.3s ease;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #444;
        }

        .empty-state p {
            font-size: 14px;
            margin-bottom: 15px;
            max-width: 250px;
            margin-left: auto;
            margin-right: auto;
        }

        .quick-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .info-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .info-card:hover .info-icon {
            transform: scale(1.1) rotate(360deg);
        }

        .info-content h4 {
            font-size: 15px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .info-content p {
            color: #666;
            font-size: 13px;
            line-height: 1.4;
        }

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

        .device-option {
            background: #334155;
            color: #f1f5f9;
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

        .device-toggle i {
            font-size: 16px;
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

        /* Scroll to Today section highlight */
        .today-highlight {
            animation: highlight-pulse 2s ease;
        }

        @keyframes highlight-pulse {
            0% {
                background-color: rgba(16, 185, 129, 0.2);
                transform: scale(1);
            }
            50% {
                background-color: rgba(16, 185, 129, 0.4);
                transform: scale(1.02);
            }
            100% {
                background-color: transparent;
                transform: scale(1);
            }
        }

        /* Profile link cursor */
        .user-profile-content {
            cursor: pointer;
        }

        /* View All Friends Button */
        .view-all-btn {
            display: none;
        }

        .view-all-friends {
            text-decoration: none;
            color: inherit;
            display: block;
            text-align: center;
            padding: 15px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: all 0.3s;
            position: relative;
            border: 1px solid transparent;
            min-width: 100px;
            flex: 0 0 auto;
            cursor: pointer;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .view-all-friends:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.5);
        }

        .view-all-friends:active {
            transform: translateY(-2px) scale(1.02);
        }

        .view-all-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid white;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
        }

        .view-all-name {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100px;
        }

        .view-all-count {
            font-size: 11px;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 5px;
        }

        /* Friends Modal */
        .friends-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 4000;
            align-items: center;
            justify-content: center;
        }

        .friends-modal.show {
            display: flex;
        }

        .friends-modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .friends-modal-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .friends-modal-header h2 {
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .friends-modal-header h2 i {
            font-size: 28px;
        }

        .friends-modal-close {
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
            font-size: 20px;
        }

        .friends-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .friends-modal-body {
            padding: 30px;
            max-height: calc(80vh - 100px);
            overflow-y: auto;
        }

        .friends-modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .friends-modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .friends-modal-body::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .friends-modal-body::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }

        .friends-modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }

        .friends-modal-item {
            text-decoration: none;
            color: inherit;
            display: block;
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            background: #f8f9ff;
            transition: all 0.3s;
            border: 1px solid #eee;
            cursor: pointer;
        }

        .friends-modal-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
            border-color: #667eea;
        }

        .friends-modal-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .friends-modal-avatar:hover {
            transform: scale(1.1);
        }

        .friends-modal-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .friends-modal-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
            cursor: pointer;
        }

        .friends-modal-name:hover {
            color: #667eea;
            text-decoration: underline;
        }

        .friends-modal-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .friends-modal-last-active {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            cursor: pointer;
        }

        .friends-modal-empty {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .friends-modal-empty i {
            font-size: 80px;
            margin-bottom: 20px;
            color: #ddd;
        }

        .friends-modal-empty h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #666;
        }

        .friends-modal-empty p {
            font-size: 16px;
        }

        .friends-modal-search {
            margin-bottom: 20px;
            position: relative;
        }

        .friends-modal-search input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .friends-modal-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .friends-modal-search i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: 250px 1fr;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-section {
                padding: 30px 20px;
            }
            
            .hero-content h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <!-- Toast Container for Messages -->
    <div class="toast-container" id="toastContainer">
        <?php if (!empty($join_message)): ?>
            <div class="toast-notification <?php echo $join_message_type; ?>" id="initialToast">
                <i class="fas <?php echo $join_message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($join_message); ?></span>
                <i class="fas fa-times toast-close" onclick="this.parentElement.remove()"></i>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-container">
        <!-- Left Sidebar -->
        <div class="sidebar">
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
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="meetings.php" class="nav-item">
                    <i class="fas fa-video"></i> Meetings
                    <?php if ($stats['today_meetings'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['today_meetings']; ?></span>
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
            <!-- Hero Section -->
            <div class="hero-section">
                <div class="hero-content">
                    <h1><?php echo getGreeting(); ?>, <?php echo htmlspecialchars($username); ?>!</h1>
                    <div class="slogan">Seamless Meetings, Exceptional Collaboration</div>
                    <p>SkyMeet helps teams connect, collaborate, and achieve more together.</p>
                </div>
            </div>

            <!-- Stats Grid - Clickable Cards -->
            <div class="stats-grid">
                <a href="meetings.php" class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_meetings']; ?></h3>
                        <p>Total Meetings</p>
                    </div>
                </a>
                
                <a href="javascript:void(0)" onclick="scrollToTodayMeetings()" class="stat-card today">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['today_meetings']; ?></h3>
                        <p>Today's Meetings</p>
                    </div>
                </a>
                
                <a href="schedule.php" class="stat-card upcoming">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['scheduled_meetings']; ?></h3>
                        <p>Scheduled Meetings</p>
                    </div>
                </a>
                
                <a href="javascript:void(0)" onclick="openFriendsModal()" class="stat-card teams">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-friends"><?php echo $stats['team_members']; ?></h3>
                        <p>Friends</p>
                    </div>
                </a>
            </div>

            <!-- Search and Join Section -->
            <div class="search-join-section">
                <form method="POST" action="" class="search-join-container" id="joinForm">
                    <div class="search-wrapper">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search_room_id" id="searchInput"
                                   placeholder="Enter Room ID to join meeting (12 characters)" 
                                   value="<?php echo htmlspecialchars($search_value); ?>"
                                   autocomplete="off"
                                   maxlength="12"
                                   pattern="[A-Za-z0-9]+"
                                   title="Room ID must be 12 characters long and contain only letters and numbers"
                                   required>
                            <span class="char-counter" id="charCounter"><?php echo strlen($search_value); ?>/12</span>
                        </div>
                        
                        <!-- Search History Dropdown -->
                        <div class="search-history-dropdown" id="searchHistoryDropdown">
                            <div class="search-history-header">
                                <h4><i class="fas fa-history"></i> Recent Searches</h4>
                                <button type="button" class="clear-history-btn" id="clearHistoryBtn">
                                    <i class="fas fa-trash-alt"></i> Clear All
                                </button>
                            </div>
                            <div class="search-history-list" id="searchHistoryList">
                                <div class="search-history-loading" id="searchHistoryLoading">
                                    <div class="spinner"></div>
                                    <p>Loading history...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="join-btn" id="joinSubmitBtn" <?php echo (strlen($search_value) == 12) ? '' : 'disabled'; ?>>
                        <i class="fas fa-sign-in-alt"></i> Join Meeting
                    </button>
                </form>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Left Column - Ongoing Meetings -->
                <div class="left-column">
                    <div class="section-card" id="ongoing-meetings-section">
                        <div class="section-header">
                            <h3><i class="fas fa-play-circle"></i> Ongoing Meetings</h3>
                            <span class="nav-badge"><?php echo count($ongoing_meetings); ?></span>
                        </div>
                        <div class="ongoing-meetings-container" id="ongoingMeetingsContainer">
                            <div class="meetings-list">
                                <?php if (!empty($ongoing_meetings)): ?>
                                    <?php foreach ($ongoing_meetings as $meeting): ?>
                                        <div class="meeting-item" onclick="openPreJoinModal('<?php echo $meeting['room_id']; ?>', '<?php echo htmlspecialchars(addslashes($meeting['title'])); ?>', 'join')">
                                            <div class="meeting-info">
                                                <h4>
                                                    <i class="fas fa-video"></i>
                                                    <?php echo htmlspecialchars($meeting['title'] ?? 'Untitled Meeting'); ?>
                                                </h4>
                                                <div class="meeting-meta">
                                                    <span><i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($meeting['meeting_date'])); ?></span>
                                                    <span class="meeting-time">
                                                        <i class="far fa-clock"></i> 
                                                        <?php 
                                                        echo date('h:i A', strtotime($meeting['start_time'])) . ' - ' . date('h:i A', strtotime($meeting['end_time']));
                                                        ?>
                                                    </span>
                                                    <?php if (!empty($meeting['room_id'])): ?>
                                                        <span><i class="fas fa-hashtag"></i> Room: <?php echo htmlspecialchars($meeting['room_id']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="margin-top: 8px;">
                                                    <span class="status-badge status-ongoing" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2)); color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600;">
                                                        <i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i> Ongoing Now
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-play-circle"></i>
                                        <h3>No Ongoing Meetings</h3>
                                        <p>No meetings are currently in progress</p>
                                        <a href="create-meeting.php" class="create-meeting-btn" style="margin-top: 15px; display: inline-flex;">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>Create Meeting</span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="scroll-indicator" id="scrollIndicator">
                            <i class="fas fa-chevron-down"></i> Scroll to view more meetings
                        </div>
                    </div>
                </div>

                <!-- Right Column - Friends with Horizontal Scrollable Row (Clickable) -->
                <div class="right-column">
                    <div class="section-card">
                        <div class="section-header">
                            <h3>
                                <i class="fas fa-users"></i> Friends 
                            </h3>
                            <div class="section-header-actions">
                                <span class="nav-badge"><?php echo count($team_members); ?>/<?php echo $total_friends_count; ?></span>
                            </div>
                        </div>
                        <div class="team-grid" id="team-grid">
                            <!-- FRIENDS ONLY - Clickable friend cards -->
                            <?php if (!empty($team_members)): ?>
                                <?php foreach ($team_members as $member): 
                                    $last_activity = $member['last_activity'] ?? null;
                                    $status_class = 'status-offline';
                                    $status_text = 'Offline';
                                    $last_active_text = 'Never';
                                    
                                    if ($last_activity) {
                                        $last_time = strtotime($last_activity);
                                        $now = time();
                                        $diff = $now - $last_time;
                                        
                                        // Online if active in last 60 seconds (1 minute)
                                        if ($diff < 60) {
                                            $status_class = 'status-online';
                                            $status_text = 'Online';
                                        }
                                        
                                        // Format last active time
                                        if ($diff < 60) {
                                            $last_active_text = 'Online';
                                        } elseif ($diff < 3600) {
                                            $minutes = floor($diff / 60);
                                            $last_active_text = $minutes . 'm ago';
                                        } elseif ($diff < 86400) {
                                            $hours = floor($diff / 3600);
                                            $last_active_text = $hours . 'h ago';
                                        } else {
                                            $days = floor($diff / 86400);
                                            $last_active_text = $days . 'd ago';
                                        }
                                    }
                                ?>
                                    <div class="team-member">
                                        <div class="member-avatar" onclick="event.stopPropagation(); goToProfileFromFriends(<?php echo $member['id']; ?>)">
                                            <?php if (!empty($member['profile_photo'])): ?>
                                                <img src="uploads/profile_photos/<?php echo htmlspecialchars($member['profile_photo']); ?>" alt="<?php echo htmlspecialchars($member['username']); ?>">
                                            <?php else: ?>
                                                <?php echo getInitials($member['username']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="member-name" onclick="goToProfileFromFriends(<?php echo $member['id']; ?>)"><?php echo htmlspecialchars($member['username']); ?></div>
                                        <div class="member-status <?php echo $status_class; ?>" title="<?php echo $status_text; ?> - Last active: <?php echo $last_active_text; ?>"></div>
                                        <div class="last-active" onclick="event.stopPropagation(); window.location.href='chat.php?user=<?php echo $member['id']; ?>'"><?php echo $last_active_text; ?></div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($total_friends_count > 4): ?>
                                    <!-- View All Friends Button Card -->
                                    <div class="view-all-friends" onclick="openFriendsModal()">
                                        <div class="view-all-avatar">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="view-all-name">View All</div>
                                        <div class="view-all-count">+<?php echo $total_friends_count - 4; ?> more</div>
                                    </div>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <!-- No friends message - shown as a card in the scrollable row -->
                                <div class="team-member" style="min-width: 150px; cursor: default;" onclick="event.preventDefault();">
                                    <div class="member-avatar" style="background: #ddd;">
                                        <i class="fas fa-user-friends" style="font-size: 24px; color: #999;"></i>
                                    </div>
                                    <div class="member-name">No Friends Yet</div>
                                    <div class="last-active" style="margin-top: 5px;">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Find More Friends Button at bottom of section - ALWAYS VISIBLE -->
                        <div style="margin-top: 15px; text-align: center;">
                            <a href="chat.php" class="add-friends-btn" style="width: 100%;">
                                <i class="fas fa-user-plus"></i>
                                <span>Find More Friends</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Info -->
            <div class="quick-info">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="info-content">
                        <h4>Instant Meetings</h4>
                        <p>Start or join meetings instantly with one click. No downloads required.</p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="info-content">
                        <h4>Secure & Private</h4>
                        <p>End-to-end encryption ensures your meetings stay private and secure.</p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="info-content">
                        <h4>Smart Analytics</h4>
                        <p>Track meeting effectiveness with detailed insights and reports.</p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="info-content">
                        <h4>Cross-Platform</h4>
                        <p>Access meetings from any device - desktop, mobile, or tablet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Friends Modal -->
    <div class="friends-modal" id="friendsModal">
        <div class="friends-modal-content">
            <div class="friends-modal-header">
                <h2>
                    <i class="fas fa-users"></i> All Friends (<?php echo $total_friends_count; ?>)
                </h2>
                <button class="friends-modal-close" onclick="closeFriendsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="friends-modal-body">
                <div class="friends-modal-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="friendSearch" placeholder="Search friends..." onkeyup="searchFriends()">
                </div>
                <div class="friends-modal-grid" id="friendsModalGrid">
                    <?php if (!empty($all_friends)): ?>
                        <?php foreach ($all_friends as $friend): 
                            $last_activity = $friend['last_activity'] ?? null;
                            $status_class = 'status-offline';
                            $status_text = 'Offline';
                            $last_active_text = 'Never';
                            
                            if ($last_activity) {
                                $last_time = strtotime($last_activity);
                                $now = time();
                                $diff = $now - $last_time;
                                
                                if ($diff < 60) {
                                    $status_class = 'status-online';
                                    $status_text = 'Online';
                                    $last_active_text = 'Online';
                                } elseif ($diff < 3600) {
                                    $minutes = floor($diff / 60);
                                    $last_active_text = $minutes . 'm ago';
                                } elseif ($diff < 86400) {
                                    $hours = floor($diff / 3600);
                                    $last_active_text = $hours . 'h ago';
                                } else {
                                    $days = floor($diff / 86400);
                                    $last_active_text = $days . 'd ago';
                                }
                            }
                        ?>
                            <div class="friends-modal-item" onclick="goToProfileFromFriendsModal(<?php echo $friend['id']; ?>)">
                                <div class="friends-modal-avatar" onclick="event.stopPropagation(); goToProfileFromFriendsModal(<?php echo $friend['id']; ?>)">
                                    <?php if (!empty($friend['profile_photo'])): ?>
                                        <img src="uploads/profile_photos/<?php echo htmlspecialchars($friend['profile_photo']); ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>">
                                    <?php else: ?>
                                        <?php echo getInitials($friend['username']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="friends-modal-name" onclick="event.stopPropagation(); goToProfileFromFriendsModal(<?php echo $friend['id']; ?>)"><?php echo htmlspecialchars($friend['username']); ?></div>
                                <div class="friends-modal-last-active" onclick="event.stopPropagation(); window.location.href='chat.php?user=<?php echo $friend['id']; ?>'">
                                    <span class="friends-modal-status <?php echo $status_class; ?>"></span>
                                    <?php echo $last_active_text; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="friends-modal-empty">
                            <i class="fas fa-user-friends"></i>
                            <h3>No Friends Yet</h3>
                            <p>Start connecting with others to build your network</p>
                            <a href="chat.php" class="btn-primary" style="margin-top: 20px; display: inline-flex;">
                                <i class="fas fa-user-plus"></i> Find Friends
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PRE-JOIN MODAL -->
    <div class="pre-join-modal" id="preJoinModal">
        <div class="pre-join-container">
            <div class="pre-join-header">
                <h1 id="modal-title">Join Meeting</h1>
                <p id="meeting-title-display">Meeting Title</p>
                <div class="meeting-title" id="room-id-display"></div>
            </div>
            
            <div class="pre-join-content">
                <!-- Preview Section -->
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
                
                <!-- Device Selection Section -->
                <div class="device-section" id="device-section">
                    <!-- Microphone Selection -->
                    <div class="device-group">
                        <h3><i class="fas fa-microphone"></i> Microphone</h3>
                        <select class="device-selector" id="mic-select">
                            <option value="">Loading microphones...</option>
                        </select>
                        <div class="device-status">
                            <button type="button" class="device-toggle on" id="mic-toggle">
                                <i class="fas fa-microphone"></i>
                                <span>On</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Camera Selection -->
                    <div class="device-group">
                        <h3><i class="fas fa-video"></i> Camera</h3>
                        <select class="device-selector" id="camera-select">
                            <option value="">Loading cameras...</option>
                        </select>
                        <div class="device-status">
                            <button type="button" class="device-toggle on" id="camera-toggle">
                                <i class="fas fa-video"></i>
                                <span>On</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Speaker Selection -->
                    <div class="device-group">
                        <h3><i class="fas fa-volume-up"></i> Speaker</h3>
                        <select class="device-selector" id="speaker-select">
                            <option value="">Loading speakers...</option>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="pre-join-actions">
                        <button type="button" class="pre-join-btn cancel" id="cancel-join">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="pre-join-btn join" id="join-meeting-btn">
                            <i class="fas fa-video"></i> Join Meeting
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // TOAST NOTIFICATION FUNCTIONS 
        const toastContainer = document.getElementById('toastContainer');

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = 'toast-notification ' + type;
            
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            
            toast.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
                <i class="fas fa-times toast-close" onclick="this.parentElement.remove()"></i>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 4000);
        }

        // Auto-remove initial toast after 4 seconds
        <?php if (!empty($join_message)): ?>
            setTimeout(() => {
                const initialToast = document.getElementById('initialToast');
                if (initialToast && initialToast.parentNode) {
                    initialToast.remove();
                }
            }, 4000);
        <?php endif; ?>

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

        // FRIENDS MODAL FUNCTIONS 
        const friendsModal = document.getElementById('friendsModal');

        // Auto-open friends modal if flag is set
        <?php if ($auto_open_friends): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                openFriendsModal();
            }, 500);
        });
        <?php endif; ?>

        function openFriendsModal() {
            friendsModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeFriendsModal() {
            friendsModal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside content
        if (friendsModal) {
            friendsModal.addEventListener('click', function(e) {
                if (e.target === friendsModal) {
                    closeFriendsModal();
                }
            });
        }

        // Search friends function
        function searchFriends() {
            const searchInput = document.getElementById('friendSearch').value.toLowerCase();
            const friendItems = document.querySelectorAll('.friends-modal-item');
            
            friendItems.forEach(item => {
                const name = item.querySelector('.friends-modal-name').textContent.toLowerCase();
                if (name.includes(searchInput)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Navigate to profile from friends section (dashboard)
        function goToProfileFromFriends(userId) {
            // Store that we came from friends modal to return back
            sessionStorage.setItem('returnToFriends', 'true');
            window.location.href = 'profile.php?id=' + userId + '&from=friends';
        }

        // Navigate to profile from friends modal
        function goToProfileFromFriendsModal(userId) {
            // Store that we came from friends modal to return back
            sessionStorage.setItem('returnToFriends', 'true');
            sessionStorage.setItem('returnToFriendsModal', 'true');
            window.location.href = 'profile.php?id=' + userId + '&from=friends';
        }

        // Check if we should return to friends modal
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const from = urlParams.get('from');
            
            if (from === 'friends' && sessionStorage.getItem('returnToFriends') === 'true') {
                // Clear the flag
                sessionStorage.removeItem('returnToFriends');
                
                // Check if we should open the modal
                if (sessionStorage.getItem('returnToFriendsModal') === 'true') {
                    sessionStorage.removeItem('returnToFriendsModal');
                    // Add a slight delay to ensure page is loaded
                    setTimeout(function() {
                        openFriendsModal();
                    }, 500);
                }
            }
        });

        // SCROLL TO TODAY'S MEETINGS 
        function scrollToTodayMeetings() {
            // Scroll to the ongoing meetings section
            const ongoingSection = document.getElementById('ongoing-meetings-section');
            if (ongoingSection) {
                ongoingSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Add highlight effect
                ongoingSection.classList.add('today-highlight');
                setTimeout(() => {
                    ongoingSection.classList.remove('today-highlight');
                }, 2000);
            }
        }

        // SEARCH INPUT VALIDATION 
        const searchInput = document.getElementById('searchInput');
        const charCounter = document.getElementById('charCounter');
        const joinSubmitBtn = document.getElementById('joinSubmitBtn');
        
        // Sensitive words filter (from PHP)
        const sensitiveWords = <?php echo json_encode($sensitive_words); ?>;
        
        // Remove initial-load class after first interaction
        let hasUserInteracted = false;
        
        function validateRoomIdInput() {
            const roomId = searchInput.value.trim();
            const length = roomId.length;
            
            // Update character counter
            charCounter.textContent = length + '/12';
            
            // Update counter color based on length
            charCounter.classList.remove('warning', 'error', 'valid');
            if (length === 0) {
                charCounter.classList.add('warning');
            } else if (length < 12) {
                charCounter.classList.add('warning');
            } else if (length > 12) {
                charCounter.classList.add('error');
            } else {
                charCounter.classList.add('valid');
            }
            
            // Check if length is exactly 12
            if (length !== 12) {
                if (hasUserInteracted) {
                    searchInput.classList.add('invalid');
                }
                joinSubmitBtn.disabled = true;
                return false;
            }
            
            // Check if contains only alphanumeric characters
            const alphanumericRegex = /^[a-zA-Z0-9]+$/;
            if (!alphanumericRegex.test(roomId)) {
                if (hasUserInteracted) {
                    searchInput.classList.add('invalid');
                    showToast('Room ID can only contain letters and numbers', 'error');
                }
                joinSubmitBtn.disabled = true;
                return false;
            }
            
            // Check for sensitive words (case insensitive)
            const roomIdLower = roomId.toLowerCase();
            for (let word of sensitiveWords) {
                if (roomIdLower.includes(word)) {
                    if (hasUserInteracted) {
                        searchInput.classList.add('invalid');
                        showToast('Room ID contains prohibited content', 'error');
                    }
                    joinSubmitBtn.disabled = true;
                    return false;
                }
            }
            
            // All validations passed
            searchInput.classList.remove('invalid');
            joinSubmitBtn.disabled = false;
            return true;
        }
        
        if (searchInput) {
            // Mark that user has interacted on focus or input
            searchInput.addEventListener('focus', function() {
                hasUserInteracted = true;
            });
            
            searchInput.addEventListener('input', function() {
                hasUserInteracted = true;
                validateRoomIdInput();
            });
            
            searchInput.addEventListener('keyup', function() {
                hasUserInteracted = true;
                validateRoomIdInput();
            });
            
            searchInput.addEventListener('change', function() {
                hasUserInteracted = true;
                validateRoomIdInput();
            });
            
            // Initial validation but don't show red border
            validateRoomIdInput();
        }

        // PRE-JOIN MEETING FUNCTIONS 
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

        // DOM Elements
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
        const joinForm = document.getElementById('joinForm');

        // Store room ID from PHP for JavaScript to use
        const roomIdFromPHP = '<?php echo $room_id_to_join; ?>';
        const currentUserId = '<?php echo $user_id; ?>';

        // Function to check if meeting exists
        async function checkMeetingExists(roomId) {
            try {
                const formData = new FormData();
                formData.append('check_meeting', '1');
                formData.append('room_id', roomId);
                
                const response = await fetch('check_meeting.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                return data.exists;
            } catch (error) {
                console.error('Error checking meeting:', error);
                return false;
            }
        }

        // Open pre-join modal
        function openPreJoinModal(roomId, title, mode = 'join') {
            selectedRoomId = roomId;
            meetingTitle = title || 'Meeting Room';
            meetingTitleDisplay.textContent = title;
            roomIdDisplay.innerHTML = '<i class="fas fa-hashtag"></i> ' + roomId;
            currentMode = mode;
            
            preJoinModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            if (mode === 'view') {
                modalTitle.textContent = 'View Meeting';
                joinMeetingBtn.innerHTML = '<i class="fas fa-eye"></i> View Meeting';
                joinMeetingBtn.className = 'pre-join-btn view';
                // Keep device selection for view mode too
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
                // Request permission first
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    audio: true, 
                    video: true 
                });
                
                stream.getTracks().forEach(track => track.stop());
                
                const devices = await navigator.mediaDevices.enumerateDevices();
                
                // Audio inputs (microphones)
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
                
                // Video inputs (cameras)
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
                
                // Audio outputs (speakers)
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
                
                // Remove any existing video element
                const existingVideo = videoPreview.querySelector('video');
                if (existingVideo) {
                    existingVideo.remove();
                }
                
                // Create new video element
                const videoElement = document.createElement('video');
                videoElement.autoplay = true;
                videoElement.muted = true;
                videoElement.playsInline = true;
                videoPreview.appendChild(videoElement);
                videoElement.srcObject = preJoinStream;
                
                // Hide loading, show video
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
            // Update mic controls
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
            
            // Update camera controls
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
            
            // Update stream tracks
            if (preJoinStream) {
                preJoinStream.getAudioTracks().forEach(track => {
                    track.enabled = isMicOn;
                });
                
                preJoinStream.getVideoTracks().forEach(track => {
                    track.enabled = isCameraOn;
                });
            }
        }

        // Event Listeners
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
                // Audio output will be set in the meeting room
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
            document.body.style.overflow = '';
            
            // Reset states
            isMicOn = true;
            isCameraOn = true;
            currentMode = 'join';
            
            // Remove video element
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
                // For view mode, just go to meeting room without device settings
                window.location.href = `meeting_room.php?room=${selectedRoomId}&mode=view`;
                return;
            }
            
            // Store settings in session storage for the meeting room
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

        document.addEventListener('keydown', (event) => {
            if (preJoinModal && preJoinModal.style.display === 'flex') {
                if (event.key === 'Escape') {
                    closePreJoinModal();
                    event.preventDefault();
                }
                
                if (currentMode === 'join' && event.key === ' ' && !event.target.matches('input, select, textarea')) {
                    isMicOn = !isMicOn;
                    updatePreviewControls();
                    event.preventDefault();
                }
                
                if (event.key === 'Enter' && document.activeElement === joinMeetingBtn) {
                    joinMeeting();
                    event.preventDefault();
                }
            }
            
            // Close friends modal with Escape key
            if (event.key === 'Escape' && friendsModal.classList.contains('show')) {
                closeFriendsModal();
            }
        });

        // MEETING FORM FUNCTIONS 
        if (joinForm) {
            joinForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate before submitting
                if (!validateRoomIdInput()) {
                    showToast('Please enter a valid 12-character Room ID', 'error');
                    return;
                }
                
                const roomId = searchInput.value.trim();
                
                if (!roomId) {
                    showToast('Please enter a room ID', 'error');
                    return;
                }
                
                // Save search query to history (no duplicates)
                saveSearchQuery(roomId);
                
                // Check if meeting exists by making an AJAX call
                checkMeetingExists(roomId).then(exists => {
                    if (exists) {
                        showToast('✓ Meeting found! Redirecting to pre-join...', 'success');
                        setTimeout(() => {
                            openPreJoinModal(roomId, 'Meeting Room', 'join');
                        }, 500);
                    } else {
                        // Display nice toast error message
                        showToast('❌ Meeting not found or invalid room ID', 'error');
                    }
                });
            });
        }

        // SEARCH HISTORY FUNCTIONS 
        const searchHistoryDropdown = document.getElementById('searchHistoryDropdown');
        const searchHistoryList = document.getElementById('searchHistoryList');
        const searchHistoryLoading = document.getElementById('searchHistoryLoading');
        const clearHistoryBtn = document.getElementById('clearHistoryBtn');
        
        // Load search history
        async function loadSearchHistory() {
            try {
                searchHistoryLoading.style.display = 'block';
                searchHistoryList.innerHTML = '<div class="search-history-loading"><div class="spinner"></div><p>Loading history...</p></div>';
                
                const response = await fetch('dashboard.php?get_search_history=1&limit=20');
                const data = await response.json();
                
                searchHistoryLoading.style.display = 'none';
                
                if (data.success && data.history.length > 0) {
                    displaySearchHistory(data.history);
                } else {
                    showEmptySearchHistory();
                }
            } catch (error) {
                console.error('Error loading search history:', error);
                searchHistoryLoading.style.display = 'none';
                showEmptySearchHistory();
            }
        }

        // Display search history in dropdown
        function displaySearchHistory(history) {
            if (history.length === 0) {
                showEmptySearchHistory();
                return;
            }
            
            let html = '';
            history.forEach(item => {
                html += `
                    <li class="search-history-item" data-id="${item.id}" data-query="${item.search_query.replace(/'/g, "\\'")}">
                        <div class="search-info" onclick="useSearchHistory('${item.search_query.replace(/'/g, "\\'")}')">
                            <div class="search-icon">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <div class="search-details">
                                <div class="search-query">${escapeHtml(item.search_query)}</div>
                                <div class="search-meta">
                                    <span class="search-time">
                                        <i class="far fa-clock"></i> ${item.formatted_time}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="remove-search-btn" onclick="deleteSearchItem(${item.id}, event)">
                            <i class="fas fa-times"></i>
                        </button>
                    </li>
                `;
            });
            
            searchHistoryList.innerHTML = html;
            
            // Add scrollable class if more than 6 items
            if (history.length > 6) {
                searchHistoryDropdown.classList.add('scrollable');
            } else {
                searchHistoryDropdown.classList.remove('scrollable');
            }
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Show empty search history
        function showEmptySearchHistory() {
            searchHistoryList.innerHTML = `
                <div class="search-history-empty">
                    <i class="fas fa-search"></i>
                    <p>No search history yet</p>
                </div>
            `;
            searchHistoryDropdown.classList.remove('scrollable');
        }

        // Save search query to history (no duplicates)
        async function saveSearchQuery(query) {
            if (!query || query.trim() === '') return;
            
            try {
                const formData = new FormData();
                formData.append('save_search', '1');
                formData.append('search_query', query);
                formData.append('search_type', 'room_id');
                
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Reload search history
                    loadSearchHistory();
                } else if (!data.success && data.message) {
                    // Show error message if validation fails
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Error saving search:', error);
            }
        }

        // Use a search history item
        window.useSearchHistory = function(query) {
            const searchInput = document.getElementById('searchInput');
            searchInput.value = query;
            searchHistoryDropdown.classList.remove('show');
            
            // Trigger validation
            validateRoomIdInput();
            
            // Automatically submit the form after a short delay
            setTimeout(() => {
                document.getElementById('joinSubmitBtn').click();
            }, 100);
        };

        // Delete individual search history item
        window.deleteSearchItem = async function(id, event) {
            event.stopPropagation();
            
            try {
                const formData = new FormData();
                formData.append('delete_search_item', '1');
                formData.append('search_id', id);
                
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('✓ Search item deleted', 'success');
                    // Reload search history
                    loadSearchHistory();
                } else {
                    showToast('Failed to delete item', 'error');
                }
            } catch (error) {
                console.error('Error deleting search item:', error);
                showToast('Error deleting item', 'error');
            }
        };

        // Clear all search history
        if (clearHistoryBtn) {
            clearHistoryBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (!confirm('Clear all search history?')) return;
                
                try {
                    const formData = new FormData();
                    formData.append('clear_search_history', '1');
                    
                    const response = await fetch('dashboard.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast('✓ Search history cleared', 'success');
                        showEmptySearchHistory();
                    } else {
                        showToast('Failed to clear history', 'error');
                    }
                } catch (error) {
                    console.error('Error clearing history:', error);
                    showToast('Error clearing history', 'error');
                }
            });
        }

        // Show/hide search history dropdown
        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                loadSearchHistory();
                searchHistoryDropdown.classList.add('show');
            });
        }

        document.addEventListener('click', function(e) {
            if (searchInput && !searchInput.contains(e.target) && !searchHistoryDropdown.contains(e.target)) {
                searchHistoryDropdown.classList.remove('show');
            }
        });

        // Prevent form submission when clicking on search history
        if (searchHistoryDropdown) {
            searchHistoryDropdown.addEventListener('click', function(e) {
                e.preventDefault();
            });
        }

        // DOCUMENT READY FUNCTIONS 

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($roomIdFromPHP)): ?>
                setTimeout(() => {
                    showToast('✓ Meeting found! Redirecting to pre-join...', 'success');
                    setTimeout(() => {
                        openPreJoinModal('<?php echo $roomIdFromPHP; ?>', 'Meeting Room', 'join');
                    }, 500);
                }, 300);
            <?php endif; ?>
            
            // Do NOT auto-focus on search input after login
            // Let user naturally navigate to the search if they want
            
            setupOngoingMeetingsScroll();
            
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

            const urlParams = new URLSearchParams(window.location.search);
            const roomId = urlParams.get('room');
            if (roomId && searchInput) {
                searchInput.value = roomId;
                searchInput.focus();
                searchInput.select();
                validateRoomIdInput();
            }
            
            // Load search history on page load
            setTimeout(() => {
                loadSearchHistory();
            }, 500);
        });

        function setupOngoingMeetingsScroll() {
            const container = document.getElementById('ongoingMeetingsContainer');
            const scrollIndicator = document.getElementById('scrollIndicator');
            const meetingItems = container ? container.querySelectorAll('.meeting-item') : [];
            
            if (meetingItems.length > 3) {
                container.classList.add('scrollable');
                scrollIndicator.classList.add('show');
                
                container.addEventListener('scroll', function() {
                    const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 5;
                    
                    if (isAtBottom) {
                        scrollIndicator.style.opacity = '0';
                        scrollIndicator.style.transition = 'opacity 0.3s ease';
                    } else {
                        scrollIndicator.style.opacity = '1';
                    }
                });
                
                setTimeout(() => {
                    if (scrollIndicator.style.opacity !== '0') {
                        scrollIndicator.style.opacity = '0.6';
                    }
                }, 5000);
            }
        }

        document.querySelectorAll('.stat-card, .meeting-item, .team-member, .info-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        function copyRoomId(roomId) {
            navigator.clipboard.writeText(roomId).then(() => {
                showToast('✓ Room ID copied to clipboard', 'success');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                showToast('Failed to copy', 'error');
            });
        }

        document.querySelectorAll('.meeting-meta span:has(i.fa-hashtag)').forEach(el => {
            const text = el.textContent;
            const roomIdMatch = text.match(/Room:\s*(.+)/);
            if (roomIdMatch) {
                const roomId = roomIdMatch[1];
                const copyBtn = document.createElement('button');
                copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
                copyBtn.style.marginLeft = '5px';
                copyBtn.style.padding = '2px 8px';
                copyBtn.style.background = '#f0f5ff';
                copyBtn.style.color = '#667eea';
                copyBtn.style.border = 'none';
                copyBtn.style.borderRadius = '4px';
                copyBtn.style.cursor = 'pointer';
                copyBtn.style.fontSize = '11px';
                copyBtn.title = 'Copy Room ID';
                copyBtn.onclick = (e) => {
                    e.stopPropagation();
                    copyRoomId(roomId);
                };
                el.appendChild(copyBtn);
            }
        });

        if (searchInput) {
            searchInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    if (this.value.trim().length > 0) {
                        this.select();
                        validateRoomIdInput();
                    }
                }, 100);
            });
        }

        window.addEventListener('focus', function() {
            updateLastActivity();
            setTimeout(() => {
                updateOnlineStatuses();
            }, 500);
        });

        // REAL-TIME ONLINE STATUS SYSTEM 
        // Function to update user statuses via AJAX - runs every 1 second
        function updateOnlineStatuses() {
            fetch('get_online_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update status for each team member
                        data.users.forEach(user => {
                            const userElement = document.querySelector(`.team-member[data-user-id="${user.id}"]`);
                            if (userElement) {
                                const statusDot = userElement.querySelector('.member-status');
                                const lastActiveDiv = userElement.querySelector('.last-active');
                                
                                if (statusDot) {
                                    // Remove existing status classes
                                    statusDot.classList.remove('status-online', 'status-offline');
                                    
                                    // Add new status class
                                    statusDot.classList.add(user.status_class);
                                    statusDot.title = `${user.status_text} - Last active: ${user.last_active_text}`;
                                }
                                
                                if (lastActiveDiv) {
                                    lastActiveDiv.textContent = user.last_active_text + '';
                                }
                            }
                            
                            // Also update in modal if open
                            const modalUserElement = document.querySelector(`.friends-modal-item[onclick*="profile.php?id=${user.id}"] .friends-modal-status`);
                            if (modalUserElement) {
                                modalUserElement.classList.remove('status-online', 'status-offline');
                                modalUserElement.classList.add(user.status_class);
                            }
                            
                            const modalLastActive = document.querySelector(`.friends-modal-item[onclick*="profile.php?id=${user.id}"] .friends-modal-last-active`);
                            if (modalLastActive) {
                                const statusSpan = modalLastActive.querySelector('.friends-modal-status');
                                modalLastActive.innerHTML = '';
                                if (statusSpan) {
                                    modalLastActive.appendChild(statusSpan);
                                }
                                modalLastActive.innerHTML += ` ${user.last_active_text}`;
                            }
                        });
                        
                        // Update online count badge
                        updateOnlineCount(data.users);
                    }
                })
                .catch(error => console.error('Error fetching online status:', error));
        }

        // Function to update online count badge
        function updateOnlineCount(users) {
            const onlineCount = users.filter(user => user.status === 'online').length;
            const onlineBadge = document.getElementById('online-badge');
            if (onlineBadge) {
                onlineBadge.textContent = `${onlineCount} online`;
                
                // Change badge color based on online count
                if (onlineCount > 0) {
                    onlineBadge.style.background = '#10b981';
                } else {
                    onlineBadge.style.background = '#9ca3af';
                }
            }
        }

        // Function to update current user's last activity
        function updateLastActivity() {
            fetch('update_activity.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update current user's status to online
                        const currentUserElement = document.querySelector(`.team-member[data-user-id="${currentUserId}"]`);
                        if (currentUserElement) {
                            const statusDot = currentUserElement.querySelector('.member-status');
                            const lastActiveDiv = currentUserElement.querySelector('.last-active');
                            
                            if (statusDot) {
                                statusDot.classList.remove('status-offline');
                                statusDot.classList.add('status-online');
                                statusDot.title = 'Online now';
                            }
                            
                            if (lastActiveDiv) {
                                lastActiveDiv.textContent = 'Online';
                            }
                        }
                    }
                })
                .catch(error => console.error('Error updating activity:', error));
        }

        // Update user statuses EVERY 1 SECOND (1000ms) - NEAR REAL-TIME
        setInterval(updateOnlineStatuses, 1000);

        // Update current user's activity EVERY 30 SECONDS
        setInterval(updateLastActivity, 30000);

        // Update statuses immediately on page load
        setTimeout(() => {
            updateOnlineStatuses();
        }, 100);
        
        // Also update immediately
        updateOnlineStatuses();
        updateLastActivity();

        // Update activity when user interacts with the page
        ['click', 'mousemove', 'keypress', 'scroll', 'mousedown', 'touchstart'].forEach(eventType => {
            document.addEventListener(eventType, function() {
                // Clear any existing timeout
                if (window.activityUpdateTimeout) {
                    clearTimeout(window.activityUpdateTimeout);
                }
                
                // Update after 2 seconds of inactivity
                window.activityUpdateTimeout = setTimeout(() => {
                    updateLastActivity();
                }, 2000);
            });
        });

        // Force update when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                updateLastActivity();
                setTimeout(() => {
                    updateOnlineStatuses();
                }, 500);
            }
        });
    </script>
</body>
</html>