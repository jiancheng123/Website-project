<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $current_user_id;

// Get basic user info for the page title
$sql = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_user = $result->fetch_assoc();
$stmt->close();

$page_title = $profile_user ? $profile_user['username'] . "'s Profile" : "User Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - SkyMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }

        /* Navigation */
        .nav-bar {
            max-width: 900px;
            margin: 0 auto 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-bar a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 500;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .nav-bar a:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .nav-bar a i {
            font-size: 14px;
        }

        .nav-bar .home-link {
            background: white;
            color: #667eea;
            border: none;
        }

        .nav-bar .home-link:hover {
            background: #f8f9ff;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.3);
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }

        .toast {
            padding: 16px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease forwards;
            transform: translateX(100%);
            opacity: 0;
        }

        .toast.show {
            animation: slideIn 0.3s ease forwards;
        }

        @keyframes slideIn {
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.success {
            background: linear-gradient(135deg, #10b981, #059669);
            border-left: 4px solid #047857;
        }

        .toast.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-left: 4px solid #b91c1c;
        }

        .toast i {
            font-size: 20px;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .toast-close:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* Profile Container */
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .loading {
            text-align: center;
            color: white;
            font-size: 16px;
            padding: 60px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .loading i {
            margin-right: 10px;
            animation: spin 1s infinite linear;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .error-message {
            background: #fee;
            color: #c62828;
            padding: 40px;
            border-radius: 30px;
            text-align: center;
            border: 1px solid #feb2b2;
        }

        .error-message i {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .error-message .retry-btn {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 35px;
            background: #c62828;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s;
        }

        .error-message .retry-btn:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }

        /* Profile Card - Glassmorphism Design */
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header Section */
        .profile-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 50px 40px 30px;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .profile-avatar-wrapper {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-avatar {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 4px solid white;
            overflow: hidden;
            background: white;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 600;
            color: white;
        }

        .profile-title {
            flex: 1;
        }

        .profile-name {
            font-size: 38px;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .profile-email {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .profile-email i {
            font-size: 14px;
            opacity: 0.8;
        }

        .profile-role {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 18px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 500;
            color: white;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Stats Grid - Minimal Design */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 40px 40px 20px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        /* Info Grid - Clean Layout */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            padding: 20px 40px 40px;
        }

        .info-section {
            background: #fafbff;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #f0f0f0;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            padding-bottom: 12px;
            border-bottom: 2px solid #eaeef5;
        }

        .section-title i {
            color: #667eea;
            font-size: 18px;
        }

        .info-content {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 12px;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 18px;
            color: #333;
            font-weight: 600;
        }

        .info-value.light {
            font-weight: 500;
            color: #555;
        }

        .info-value.empty {
            color: #aaa;
            font-weight: 400;
            font-style: italic;
        }

        /* Online Status Indicator */
        .online-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 15px;
            font-size: 13px;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-dot.online {
            background: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
            animation: pulse 2s infinite;
        }

        .status-dot.offline {
            background: #9ca3af;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        .last-active {
            font-size: 13px;
            color: #666;
            margin-top: 2px;
        }

        /* Action Buttons */
        .action-buttons {
            padding: 20px 40px 40px;
            text-align: center;
        }

        .friend-btn {
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 250px;
        }

        .friend-btn.add {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .friend-btn.add:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .friend-btn.pending {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fbbf24;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .friend-btn.friends {
            background: #10b981;
            color: white;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .friend-btn.friends:hover {
            background: #059669;
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(16, 185, 129, 0.4);
        }

        .friend-btn i {
            font-size: 18px;
        }

        .friend-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-avatar-wrapper {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .profile-name {
                font-size: 30px;
            }

            .profile-header {
                padding: 40px 30px 25px;
            }

            .stats-grid {
                padding: 30px 30px 15px;
                gap: 15px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                padding: 15px 30px 30px;
                gap: 15px;
            }

            .action-buttons {
                padding: 15px 30px 30px;
            }

            .friend-btn {
                width: 100%;
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
            }

            .profile-name {
                font-size: 26px;
            }

            .stat-number {
                font-size: 28px;
            }

            .info-value {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="nav-bar">
        <a href="javascript:history.back()">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <a href="dashboard.php" class="home-link">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>

    <div class="profile-container">
        <div id="profileCard" style="display: none;"></div>
        <div id="loading" class="loading">
            <i class="fas fa-spinner"></i> Loading profile...
        </div>
        <div id="error" class="error-message" style="display: none;"></div>
    </div>

    <script>
        const userId = <?php echo $profile_id; ?>;
        const currentUserId = <?php echo $current_user_id; ?>;

        // Toast notification function
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast show ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 5000);
        }

        // Fetch user profile
        fetch(`get_user_profile.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                
                if (data.success) {
                    displayProfile(data.user);
                } else {
                    showError(data.message || 'User not found');
                }
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                showError('Error loading profile. Please try again.');
                console.error('Error:', error);
            });

        function displayProfile(user) {
            const profileCard = document.getElementById('profileCard');
            
            // Get initials for default avatar
            const initials = user.username.substring(0, 2).toUpperCase();
            
            // Format member since as dd/mm/yyyy
            let memberSince = user.member_since || '';
            
            // Format last activity
            let lastActivityText = 'Never';
            let statusClass = 'offline';
            
            if (user.last_activity) {
                const lastActivity = new Date(user.last_activity);
                const now = new Date();
                const diffMs = now - lastActivity;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                // Check if online (active in last 5 minutes)
                if (diffMins < 5) {
                    statusClass = 'online';
                    lastActivityText = 'Online now';
                } 
                // Format last active time
                else if (diffMins < 60) {
                    lastActivityText = `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
                } else if (diffHours < 24) {
                    lastActivityText = `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
                } else if (diffDays < 7) {
                    lastActivityText = `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
                } else {
                    // Format as date
                    const day = lastActivity.getDate().toString().padStart(2, '0');
                    const month = (lastActivity.getMonth() + 1).toString().padStart(2, '0');
                    const year = lastActivity.getFullYear();
                    const hours = lastActivity.getHours().toString().padStart(2, '0');
                    const minutes = lastActivity.getMinutes().toString().padStart(2, '0');
                    lastActivityText = `${day}/${month}/${year} at ${hours}:${minutes}`;
                }
            }
            
            // Determine if this is the current user's profile
            const isCurrentUser = user.id === currentUserId;
            
            // Determine friend button
            let friendButtonHtml = '';
            if (!isCurrentUser) {
                if (user.friend_status === 'accepted') {
                    friendButtonHtml = `
                        <button class="friend-btn friends" onclick="openChat(${user.id})">
                            <i class="fas fa-comment"></i> Send Message
                        </button>
                    `;
                } else if (user.friend_status === 'pending') {
                    if (user.is_pending_from_me) {
                        friendButtonHtml = `
                            <button class="friend-btn pending" disabled>
                                <i class="fas fa-clock"></i> Request Sent
                            </button>
                        `;
                    } else {
                        friendButtonHtml = `
                            <button class="friend-btn add" onclick="acceptFriend(${user.id})">
                                <i class="fas fa-user-check"></i> Accept Request
                            </button>
                        `;
                    }
                } else {
                    friendButtonHtml = `
                        <button class="friend-btn add" onclick="addFriend(${user.id})">
                            <i class="fas fa-user-plus"></i> Add Friend
                        </button>
                    `;
                }
            } else {
                friendButtonHtml = `
                    <button class="friend-btn add" onclick="window.location.href='settings.php'">
                        <i class="fas fa-cog"></i> Edit Profile
                    </button>
                `;
            }

            // Build profile photo HTML
            let profilePhotoHtml = '';
            if (user.profile_photo_url) {
                profilePhotoHtml = `<img src="${user.profile_photo_url}" alt="${escapeHtml(user.username)}">`;
            } else {
                profilePhotoHtml = `<div class="avatar-placeholder">${initials}</div>`;
            }

            // Format intake display
            let intakeDisplay = 'Not specified';
            if (user.semester && user.year) {
                intakeDisplay = `${user.semester} ${user.year}`;
            } else if (user.semester) {
                intakeDisplay = `${user.semester} Intake`;
            } else if (user.year) {
                intakeDisplay = `Year: ${user.year}`;
            }

            profileCard.innerHTML = `
                <div class="profile-card">
                    <!-- Header -->
                    <div class="profile-header">
                        <div class="profile-avatar-wrapper">
                            <div class="profile-avatar">
                                ${profilePhotoHtml}
                            </div>
                            <div class="profile-title">
                                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <h1 class="profile-name">${escapeHtml(user.username)}</h1>
                                    <div class="online-status">
                                        <span class="status-dot ${statusClass}"></span>
                                        <span>${statusClass === 'online' ? 'Online' : 'Offline'}</span>
                                    </div>
                                </div>
                                <div class="profile-email">
                                    <i class="fas fa-envelope"></i>
                                    ${escapeHtml(user.email)}
                                </div>
                                <div class="profile-role">
                                    <i class="fas fa-user-tag"></i>
                                    ${escapeHtml(user.role || 'Member')}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number">${user.friends_count || 0}</div>
                            <div class="stat-label">Friends</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">${user.groups_count || 0}</div>
                            <div class="stat-label">Groups</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">${user.meetings_hosted || 0}</div>
                            <div class="stat-label">Meetings</div>
                        </div>
                    </div>

                    <!-- Information -->
                    <div class="info-grid">
                        <!-- Academic Information -->
                        <div class="info-section">
                            <div class="section-title">
                                <i class="fas fa-graduation-cap"></i>
                                <span>Academic Information</span>
                            </div>
                            <div class="info-content">
                                <div class="info-item">
                                    <div class="info-label">Education Level</div>
                                    <div class="info-value ${!user.education_level ? 'empty' : ''}">
                                        ${user.education_level ? escapeHtml(user.education_level) : 'Not specified'}
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-label">Program of Study</div>
                                    <div class="info-value ${!user.program ? 'empty' : ''}">
                                        ${user.program ? escapeHtml(user.program) : 'Not specified'}
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-label">Intake</div>
                                    <div class="info-value ${!user.semester && !user.year ? 'empty' : ''}">
                                        ${intakeDisplay}
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-label">Student ID</div>
                                    <div class="info-value ${!user.student_id ? 'empty' : ''}">
                                        ${user.student_id ? escapeHtml(user.student_id) : 'Not specified'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="info-section">
                            <div class="section-title">
                                <i class="fas fa-user-circle"></i>
                                <span>Account Information</span>
                            </div>
                            <div class="info-content">
                                <div class="info-item">
                                    <div class="info-label">Member Since</div>
                                    <div class="info-value">
                                        ${escapeHtml(memberSince)}
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-label">User ID</div>
                                    <div class="info-value">
                                        #${user.id}
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-label">Last Activity</div>
                                    <div class="info-value light" style="font-size: 15px;">
                                        <i class="fas fa-clock" style="color: #667eea; margin-right: 5px;"></i>
                                        ${escapeHtml(lastActivityText)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="action-buttons">
                        ${friendButtonHtml}
                    </div>
                </div>
            `;

            profileCard.style.display = 'block';
        }

        function showError(message) {
            document.getElementById('error').style.display = 'block';
            document.getElementById('error').innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <div>${escapeHtml(message)}</div>
                <a href="javascript:location.reload()" class="retry-btn">
                    <i class="fas fa-redo"></i> Try Again
                </a>
            `;
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            if (!text) return text;
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Friend action functions using chat.php endpoints
        function addFriend(userId) {
            const button = event.currentTarget;
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;
            
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=add_friend&friend_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Friend request sent!', 'success');
                    // Update button to pending state
                    const newButton = document.createElement('button');
                    newButton.className = 'friend-btn pending';
                    newButton.disabled = true;
                    newButton.innerHTML = '<i class="fas fa-clock"></i> Request Sent';
                    button.parentNode.replaceChild(newButton, button);
                } else {
                    showToast(data.message, 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error sending friend request', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        function acceptFriend(userId) {
            const button = event.currentTarget;
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Accepting...';
            button.disabled = true;
            
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=accept_friend&friend_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Friend request accepted!', 'success');
                    // Update button to chat button
                    const newButton = document.createElement('button');
                    newButton.className = 'friend-btn friends';
                    newButton.onclick = function() { openChat(userId); };
                    newButton.innerHTML = '<i class="fas fa-comment"></i> Send Message';
                    button.parentNode.replaceChild(newButton, button);
                } else {
                    showToast(data.message, 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error accepting friend request', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        function openChat(userId) {
            window.location.href = `chat.php?user_id=${userId}`;
        }
    </script>
</body>
</html>