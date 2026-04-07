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
$user_role = $_SESSION['role'] ?? 'user';

// Get current user with profile photo
$user = getCurrentUser($conn, $user_id);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
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
// END UNREAD MESSAGE COUNTS

// Get total meetings count for badge
$meetings_query = "SELECT COUNT(*) as total FROM meetings WHERE host_id = ?";
$stmt = $conn->prepare($meetings_query);
$meetings_count = 0;
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $meetings_result = $stmt->get_result();
    $meetings_count = $meetings_result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Get upcoming meetings count for badge
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

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - SkyMeet</title>
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

        .help-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            min-height: 100vh;
        }

        /* Sidebar  */
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

        /* Help Container */
        .help-content {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.08);
        }

        .help-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .help-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .help-header h1 i {
            color: #667eea;
        }

        .help-header p {
            color: #888;
            font-size: 16px;
        }

        .faq-section {
            margin-bottom: 30px;
        }

        .faq-section h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .faq-section h2 i {
            color: #667eea;
        }

        .faq-item {
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .faq-item:hover {
            border-color: #667eea;
        }

        .faq-question {
            padding: 15px 20px;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s;
        }

        .faq-question:hover {
            background: #f8f9ff;
        }

        .faq-question h4 {
            font-size: 15px;
            color: #333;
            font-weight: 500;
        }

        .faq-question i {
            color: #999;
            transition: transform 0.3s;
            font-size: 14px;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s;
            color: #666;
            line-height: 1.6;
            font-size: 14px;
            background: white;
        }

        .faq-item.active .faq-answer {
            padding: 15px 20px;
            max-height: 200px;
        }

        .contact-support {
            text-align: center;
            padding: 25px;
            background: #f8f9ff;
            border-radius: 8px;
            margin-top: 30px;
        }

        .contact-support h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .contact-support p {
            color: #888;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .contact-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .contact-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .contact-btn.secondary {
            background: #6c757d;
        }

        .contact-btn.secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="help-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> SkyMeet</h1>
            </div>

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
                <a href="help.php" class="nav-item active">
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
                    <h2>Help & Support</h2>
                    <p>Find answers to common questions and get assistance</p>
                </div>
            </div>

            <!-- Help Content -->
            <div class="help-content">
                <div class="help-header">
                    <h1>
                        <i class="fas fa-question-circle"></i>
                        How can we help you?
                    </h1>
                    <p>Browse our frequently asked questions below</p>
                </div>

                <!-- FAQ Section -->
                <div class="faq-section">
                    <h2>
                        <i class="fas fa-question-circle"></i>
                        Frequently Asked Questions
                    </h2>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>How do I create a meeting?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Go to Meetings > Create Meeting. Fill in the meeting details including title, date, time, and optional password. Click "Create Meeting" to schedule it. You'll get a unique room ID to share with participants.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>How do I join a meeting?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Click the meeting link shared by the host, or go to Dashboard and enter the Room ID in the search bar. If the meeting is password-protected, you'll need to enter the password to join.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>How do I schedule a meeting?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Go to Schedule, pick a date and time from the calendar, add meeting details, and click "Schedule Meeting". The meeting will appear in your calendar.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>How do I change my password?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Go to Settings > Password tab. Enter your current password, then your new password twice, and click "Update Password". Make sure your new password is strong and secure.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>How do I share files in messages?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Go to Messages, open a chat with a friend or group, and click the attachment icon (paperclip). Select a file from your computer to upload and share.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>How do I update my profile?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Go to Settings > Profile tab. You can update your name, email, profile photo, and education information. Then click "Save Changes" when done.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>How do I add friends?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Go to Messages and click the "Add Friend" button. Search users by name or email, then click "Add Friend". They'll receive a friend request that they can accept or reject.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>How do I create a group?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Go to Messages and click "New Group". Enter a group name (required), description (optional), and click "Create Group". Only group admins can invite users to the group.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>What do the notification badges mean?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            The numbers in the sidebar badges show your unread notifications. Meetings shows your upcoming meetings, Messages shows unread private messages, group messages, and pending friend requests combined.
                        </div>
                    </div>
                </div>

                <!-- Contact Support -->
                <div class="contact-support">
                    <h3>Still facing issues?</h3>
                    <p>Report to us for more assistance!</p>
                    <div class="contact-buttons">
                        <a href="settings.php?tab=report" class="contact-btn">
                            <i class="fas fa-flag"></i> Report problem
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
                        
    <script>
        // PROFILE DROPDOWN MENU 
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

        // FAQ toggle function
        function toggleFAQ(element) {
            const faqItem = element.closest('.faq-item');
            faqItem.classList.toggle('active');
        }

        // Close all FAQs with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.faq-item.active').forEach(item => {
                    item.classList.remove('active');
                });
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to help content
            const helpContent = document.querySelector('.help-content');
            if (helpContent) {
                helpContent.style.opacity = '0';
                helpContent.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    helpContent.style.transition = 'all 0.5s ease';
                    helpContent.style.opacity = '1';
                    helpContent.style.transform = 'translateY(0)';
                }, 300);
            }

            // Ensure dropdown is closed on page load
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });

        // Handle pageshow event (for back button navigation)
        window.addEventListener('pageshow', function() {
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
        });
    </script>
</body>
</html>