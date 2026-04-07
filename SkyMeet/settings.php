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
$user_role = $_SESSION['role'] ?? 'user';

// Fetch user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Initialize profile fields with defaults if not set
$program = $user['program'] ?? '';
$semester = $user['semester'] ?? '';
$year = $user['year'] ?? '';
$student_id = $user['student_id'] ?? '';
$education_level = $user['education_level'] ?? '';

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

// Initialize variables
$error = '';
$success = '';
$profile_error = '';
$profile_success = '';
$report_error = '';
$report_success = '';
$photo_upload_error = '';
$photo_upload_success = '';

// Get active tab from URL
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// REMOVE PROFILE PHOTO 
if (isset($_GET['remove_photo']) && $_GET['remove_photo'] == '1') {
    $upload_dir = 'uploads/profile_photos/';
    
    // Delete old profile photo if exists
    if (!empty($user['profile_photo']) && file_exists($upload_dir . $user['profile_photo'])) {
        unlink($upload_dir . $user['profile_photo']);
    }
    
    // Update database
    $update_sql = "UPDATE users SET profile_photo = NULL WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);
    
    if ($update_stmt->execute()) {
        $user['profile_photo'] = null;
        $photo_upload_success = 'Profile photo removed successfully!';
    } else {
        $photo_upload_error = 'Failed to remove profile photo.';
    }
    $update_stmt->close();
    
    // If it's an AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => true, 'message' => 'Photo removed successfully']);
        exit();
    }
    
    $active_tab = 'profile';
}

// PROFILE PHOTO UPLOAD (WITH SAVE CHANGES) 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_photo']) && isset($_FILES['profile_photo'])) {
    $upload_dir = 'uploads/profile_photos/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = basename($_FILES['profile_photo']['name']);
    $file_tmp = $_FILES['profile_photo']['tmp_name'];
    $file_size = $_FILES['profile_photo']['size'];
    $file_error = $_FILES['profile_photo']['error'];
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    
    if ($file_error === 0) {
        if (in_array($file_ext, $allowed_ext)) {
            if ($file_size < 5242880) { // 5MB max
                // Generate unique filename
                $new_file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                $file_destination = $upload_dir . $new_file_name;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $file_destination)) {
                    // Delete old profile photo if exists
                    if (!empty($user['profile_photo']) && file_exists($upload_dir . $user['profile_photo'])) {
                        unlink($upload_dir . $user['profile_photo']);
                    }
                    
                    // Update database
                    $update_sql = "UPDATE users SET profile_photo = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("si", $new_file_name, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $user['profile_photo'] = $new_file_name;
                        $photo_upload_success = 'Profile photo updated successfully!';
                        
                        // Return JSON response for AJAX
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            echo json_encode([
                                'success' => true, 
                                'message' => 'Photo updated successfully', 
                                'photo_url' => $file_destination
                            ]);
                            exit();
                        }
                    } else {
                        $photo_upload_error = 'Failed to update profile photo in database.';
                    }
                    $update_stmt->close();
                } else {
                    $photo_upload_error = 'Failed to upload file.';
                }
            } else {
                $photo_upload_error = 'File size is too large (max 5MB).';
            }
        } else {
            $photo_upload_error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
        }
    } else {
        $photo_upload_error = 'Error uploading file.';
    }
    
    // Return JSON response for AJAX if there was an error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'error' => $photo_upload_error]);
        exit();
    }
    
    $active_tab = 'profile';
}

// PROFILE UPDATE 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $education_level = trim($_POST['education_level']);
    $program = trim($_POST['program']);
    $semester = trim($_POST['semester']);
    $year = trim($_POST['year']);
    $student_id = trim($_POST['student_id']);
    
    // Validate username length (max 20)
    if (strlen($new_username) > 20) {
        $profile_error = 'Username must not exceed 20 characters';
    }
    // Validate email length (max 40)
    elseif (strlen($email) > 40) {
        $profile_error = 'Email must not exceed 40 characters';
    }
    elseif (empty($new_username) || empty($email)) {
        $profile_error = 'Username and email are required';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profile_error = 'Please enter a valid email address';
    } 
    else {
        // Validate email domain
        $allowed_domains = ['gmail.com', 'hotmail.com', 'segi4u.my'];
        $email_domain = substr(strrchr($email, "@"), 1);
        if (!in_array($email_domain, $allowed_domains)) {
            $profile_error = 'Email must be from @gmail.com, @hotmail.com, or @segi4u.my';
        }
        // Validate semester
        elseif (!in_array($semester, ['January', 'May', 'September', ''])) {
            $profile_error = 'Please select a valid intake semester';
        }
        // Validate year
        elseif (!empty($year) && !is_numeric($year)) {
            $profile_error = 'Please select a valid year';
        } 
        // Validate student ID format if provided
        elseif (!empty($student_id) && !preg_match('/^SCSJ\d{7}$/', $student_id)) {
            $profile_error = 'Student ID must be in format: SCSJ followed by 7 digits (e.g., SCSJ2200000)';
        }
        else {
            // Check if username exists
            $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $new_username, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $profile_error = 'Username already exists';
            } else {
                // Check if email exists
                $check_email_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
                $check_email_stmt = $conn->prepare($check_email_sql);
                $check_email_stmt->bind_param("si", $email, $user_id);
                $check_email_stmt->execute();
                $check_email_result = $check_email_stmt->get_result();
                
                if ($check_email_result->num_rows > 0) {
                    $profile_error = 'Email already exists';
                } else {
                    $update_sql = "UPDATE users SET username = ?, email = ?, education_level = ?, program = ?, semester = ?, year = ?, student_id = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("sssssssi", $new_username, $email, $education_level, $program, $semester, $year, $student_id, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $_SESSION['username'] = $new_username;
                        $_SESSION['email'] = $email;
                        $profile_success = 'Profile updated successfully!';
                        
                        // Update user array
                        $user['username'] = $new_username;
                        $user['email'] = $email;
                        $user['education_level'] = $education_level;
                        $user['program'] = $program;
                        $user['semester'] = $semester;
                        $user['year'] = $year;
                        $user['student_id'] = $student_id;
                        $username = $new_username;
                        
                        // Update local variables
                        $education_level = $education_level;
                        $program = $program;
                        $semester = $semester;
                        $year = $year;
                        $student_id = $student_id;
                    } else {
                        $profile_error = 'Failed to update profile. Please try again.';
                    }
                    $update_stmt->close();
                }
                $check_email_stmt->close();
            }
            $check_stmt->close();
        }
    }
    $active_tab = 'profile';
}

// PASSWORD VERIFICATION 
function verifyPassword($input_password, $stored_hash) {
    if (substr($stored_hash, 0, 4) === '$2y$') {
        return password_verify($input_password, $stored_hash);
    } elseif (strlen($stored_hash) === 32 && ctype_xdigit($stored_hash)) {
        return md5($input_password) === $stored_hash;
    } elseif (strlen($stored_hash) === 40 && ctype_xdigit($stored_hash)) {
        return sha1($input_password) === $stored_hash;
    } elseif (strlen($stored_hash) < 60) {
        return $input_password === $stored_hash;
    }
    return false;
}

// PASSWORD CHANGE 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password']; // Don't trim spaces for password
    $new_password = $_POST['new_password']; // Don't trim spaces for password
    $confirm_password = $_POST['confirm_password']; // Don't trim spaces for password
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters';
    } elseif (strlen($new_password) > 15) {
        $error = 'New password must not exceed 15 characters';
    } elseif (preg_match('/\s/', $new_password)) {
        $error = 'Password cannot contain spaces';
    } else {
        // First verify the current password
        if (verifyPassword($current_password, $user['password'])) {
            // Check if new password is same as current password
            if ($current_password === $new_password) {
                $error = 'New password cannot be the same as your current password';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $success = 'Password changed successfully!';
                    $_POST['current_password'] = $_POST['new_password'] = $_POST['confirm_password'] = '';
                    
                    // Refresh user data
                    $sql = "SELECT * FROM users WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    
                    // Update password security flag
                    $is_password_secure = true;
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
                $update_stmt->close();
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
    $active_tab = 'password';
}

// PROBLEM REPORT 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_problem'])) {
    $problem_category = trim($_POST['problem_category']);
    $problem_description = trim($_POST['problem_description']);
    $contact_email = trim($_POST['contact_email']);
    
    if (empty($problem_category) || empty($problem_description)) {
        $report_error = 'Please select a category and describe the problem';
    } elseif (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $report_error = 'Please enter a valid email address for follow-up';
    } else {
        $create_table_sql = "CREATE TABLE IF NOT EXISTS problem_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            category VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            contact_email VARCHAR(255) NOT NULL,
            status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
            admin_notes TEXT,
            admin_id INT,
            admin_username VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
        )";
        $conn->query($create_table_sql);
        
        $log_sql = "INSERT INTO problem_reports (user_id, category, description, contact_email) VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("isss", $user_id, $problem_category, $problem_description, $contact_email);
        
        if ($log_stmt->execute()) {
            $report_success = "Thank you for your report! We've received your issue regarding '$problem_category' and will contact you at $contact_email if we need more information.";
        } else {
            $report_error = "Failed to submit report. Please try again.";
        }
        $log_stmt->close();
        $active_tab = 'report';
    }
}

// ACCOUNT DELETION 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm_delete = $_POST['confirm_delete'];
    
    if ($confirm_delete === 'DELETE') {
        $delete_sql = "DELETE FROM users WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute()) {
            session_destroy();
            header("Location: login.php?account_deleted=1");
            exit();
        } else {
            $error = 'Failed to delete account. Please try again.';
        }
        $delete_stmt->close();
    } else {
        $error = 'Please type DELETE to confirm account deletion';
    }
    $active_tab = 'delete';
}

// HELPER FUNCTIONS
function getInitials($username) {
    if (strlen($username) >= 2) {
        return strtoupper(substr($username, 0, 2));
    }
    return strtoupper($username . $username);
}

function getProfilePhoto($user) {
    if (!empty($user['profile_photo'])) {
        return 'uploads/profile_photos/' . $user['profile_photo'];
    }
    return false;
}

// Get meeting statistics
$meeting_count = 0;
$meetings_sql = "SELECT COUNT(*) as total FROM meetings WHERE host_id = ?";
$meetings_stmt = $conn->prepare($meetings_sql);
$meetings_stmt->bind_param("i", $user_id);
$meetings_stmt->execute();
$meetings_result = $meetings_stmt->get_result();
if ($meetings_result) {
    $meeting_data = $meetings_result->fetch_assoc();
    $meeting_count = $meeting_data['total'] ?? 0;
}
$meetings_stmt->close();

// Get upcoming meetings count (for sidebar badge)
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

// Check if password is secure (bcrypt)
$is_password_secure = (substr($user['password'], 0, 4) === '$2y$');

// Store original values for unsaved changes detection
$original_username = $user['username'];
$original_email = $user['email'];
$original_education_level = $education_level;
$original_program = $program;
$original_semester = $semester;
$original_year = $year;
$original_student_id = $student_id;
$original_photo = $user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - SkyMeet</title>
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

        .settings-container {
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

        /* Settings Navigation */
        .settings-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
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
            cursor: pointer;
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

        /* Settings Content */
        .settings-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .settings-content.active {
            display: block;
        }

        /* Settings Card */
        .settings-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin-bottom: 30px;
        }

        .settings-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .settings-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }

        .settings-title h3 {
            font-size: 22px;
            color: #333;
            margin-bottom: 5px;
        }

        .settings-title p {
            color: #666;
            font-size: 14px;
        }

        /* Form Styles */
        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9ff;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
        }

        /* Character counter */
        .char-counter {
            text-align: right;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        .char-counter.warning {
            color: #ff9800;
        }

        .char-counter.error {
            color: #f44336;
        }

        /* Year select */
        .year-select {
            max-height: 200px;
            overflow-y: auto;
        }

        /* Profile Photo Upload */
        .profile-photo-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .profile-photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #667eea;
            background: #f8f9ff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-photo-preview i {
            font-size: 40px;
            color: #667eea;
        }

        .photo-upload-controls {
            flex: 1;
        }

        .photo-upload-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-right: 10px;
        }

        .photo-upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .photo-remove-btn {
            padding: 12px 24px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .photo-remove-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        .file-input {
            display: none;
        }

        .file-name {
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 40px;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 18px;
        }

        /* Password strength indicator */
        .password-strength {
            font-size: 13px;
            padding: 5px 0;
        }

        .strength-weak {
            color: #f44336;
        }

        .strength-medium {
            color: #ff9800;
        }

        .strength-strong {
            color: #4caf50;
        }

        .strength-very-strong {
            color: #2196f3;
        }

        /* Password requirements list */
        .password-requirements {
            background: #f8f9ff;
            border-radius: 8px;
            padding: 12px 15px;
            margin: 10px 0;
            font-size: 13px;
            border: 1px solid #e0e0ff;
        }

        .password-requirements ul {
            list-style: none;
            padding-left: 0;
            margin-top: 8px;
        }

        .password-requirements li {
            margin: 5px 0;
            color: #666;
        }

        .password-requirements li i {
            width: 18px;
            margin-right: 5px;
        }

        .password-requirements li.valid {
            color: #4caf50;
        }

        .password-requirements li.invalid {
            color: #f44336;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
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

        .alert-info {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            color: #1565c0;
        }

        .alert-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }

        .alert i {
            font-size: 18px;
        }

        /* Buttons */
        .btn-submit {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            margin-top: 10px;
            width: auto;
            min-width: 160px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a67d8, #6b46a0);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
        }

        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Delete button */
        .btn-delete {
            padding: 12px 24px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            margin-top: 10px;
            width: auto;
            min-width: 160px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-delete:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }

        /* Button container */
        .button-container {
            display: flex;
            justify-content: flex-start;
            margin-top: 20px;
            width: 100%;
        }

        /* Account Info */
        .account-info {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #333;
        }

        /* Danger Zone */
        .danger-zone {
            background: #fee;
            border: 2px solid #f44336;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
        }

        .danger-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .danger-header i {
            color: #f44336;
            font-size: 24px;
        }

        .danger-header h4 {
            color: #c62828;
            font-size: 18px;
        }

        .danger-text {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .danger-text ul {
            margin: 10px 0 10px 20px;
        }

        .delete-confirm {
            background: white;
            border: 2px solid #ffcdd2;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .delete-confirm label {
            display: block;
            margin-bottom: 10px;
            color: #c62828;
            font-weight: 600;
        }

        /* Password Diagnostic */
        .password-diagnostic {
            background: #fff8e1;
            border: 2px solid #ffb300;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .password-diagnostic h5 {
            color: #ff6f00;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Character count */
        .char-count {
            text-align: right;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Upload progress */
        .upload-progress {
            margin-top: 10px;
            display: none;
        }

        .progress-bar {
            width: 100%;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s ease;
        }

        /* Student ID format hint */
        .format-hint {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        .format-hint i {
            color: #667eea;
            margin-right: 3px;
        }

        /* Password hint */
        .password-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Custom notification - Centered */
        .unsaved-notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-left: 4px solid #ff9800;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 25px;
            border-radius: 12px;
            z-index: 1000;
            display: none;
            max-width: 400px;
            width: 90%;
            animation: fadeInScale 0.3s ease;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        .unsaved-notification.show {
            display: block;
        }

        .notification-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: #ff9800;
            font-weight: 600;
            font-size: 18px;
        }

        .notification-header i {
            font-size: 24px;
        }

        .notification-content {
            margin-bottom: 20px;
            color: #666;
            line-height: 1.6;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .notification-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 14px;
        }

        .notification-btn.cancel {
            background: #f0f0f0;
            color: #666;
        }

        .notification-btn.save {
            background: #667eea;
            color: white;
        }

        .notification-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Overlay for notification */
        .notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .notification-overlay.show {
            display: block;
        }

        /* Photo preview container */
        .photo-preview-container {
            position: relative;
            display: inline-block;
        }

        .photo-preview-badge {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #4caf50;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            border: 2px solid white;
        }
    </style>
</head>
<body>
    <!-- Notification Overlay -->
    <div class="notification-overlay" id="notificationOverlay" onclick="hideNotification()"></div>
    
    <!-- Unsaved Changes Notification -->
    <div class="unsaved-notification" id="unsavedNotification">
        <div class="notification-header">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Unsaved Changes</span>
        </div>
        <div class="notification-content">
            You have unsaved changes in your profile. Would you like to save them?
        </div>
        <div class="notification-actions">
            <button class="notification-btn cancel" onclick="hideNotification()">Cancel</button>
            <button class="notification-btn save" onclick="saveAllChanges()">Save Changes</button>
        </div>
    </div>

    <div class="settings-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h1><i class="fas fa-comments"></i> SkyMeet</h1>
            </div>

            <div class="user-profile">
                <div class="user-profile-content" id="userProfileTrigger">
                    <div class="user-avatar" id="sidebarAvatar">
                        <?php if ($profile_photo = getProfilePhoto($user)): ?>
                            <img src="<?php echo $profile_photo . '?t=' . time(); ?>" alt="<?php echo htmlspecialchars($username); ?>">
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
                <a href="settings.php" class="nav-item active">
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
                    <h2>Settings</h2>
                    <p>Manage your account preferences and security</p>
                </div>
            </div>

            <!-- Settings Navigation Tabs -->
            <div class="settings-nav">
                <a href="?tab=profile" class="nav-tab <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" onclick="return checkUnsavedChangesBeforeLeave()">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="?tab=password" class="nav-tab <?php echo $active_tab === 'password' ? 'active' : ''; ?>" onclick="return checkUnsavedChangesBeforeLeave()">
                    <i class="fas fa-key"></i> Password
                </a>
                <a href="?tab=report" class="nav-tab <?php echo $active_tab === 'report' ? 'active' : ''; ?>" onclick="return checkUnsavedChangesBeforeLeave()">
                    <i class="fas fa-flag"></i> Report Problem
                </a>
                <a href="?tab=delete" class="nav-tab <?php echo $active_tab === 'delete' ? 'active' : ''; ?>" onclick="return checkUnsavedChangesBeforeLeave()">
                    <i class="fas fa-trash-alt"></i> Delete Account
                </a>
            </div>

            <!-- Profile Settings Tab -->
            <div id="profile-tab" class="settings-content <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <div class="settings-card">
                    <div class="settings-header">
                        <div class="settings-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="settings-title">
                            <h3>Account Information</h3>
                            <p>Update your personal and academic information</p>
                        </div>
                    </div>

                    <?php if ($profile_error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($profile_error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($profile_success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($profile_success); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($photo_upload_error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($photo_upload_error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($photo_upload_success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($photo_upload_success); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Photo Upload (with Save Changes) -->
                    <form method="POST" action="?tab=profile" enctype="multipart/form-data" id="photoUploadForm" style="margin-bottom: 30px;">
                        <input type="hidden" name="update_photo" value="1">
                        <div class="profile-photo-container">
                            <div class="photo-preview-container">
                                <div class="profile-photo-preview" id="profilePhotoPreview">
                                    <?php if ($profile_photo = getProfilePhoto($user)): ?>
                                        <img src="<?php echo $profile_photo . '?t=' . time(); ?>" alt="Profile Photo" id="profilePhotoImg">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="photo-preview-badge" id="photoPreviewBadge" style="display: none;">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            <div class="photo-upload-controls">
                                <label for="profile_photo" class="photo-upload-btn" id="uploadPhotoLabel">
                                    <i class="fas fa-camera"></i> <span id="uploadBtnText">Choose Photo</span>
                                </label>
                                <input type="file" id="profile_photo" name="profile_photo" class="file-input" 
                                       accept="image/*" onchange="previewSelectedPhoto(this)">
                                <button type="button" class="photo-remove-btn" onclick="removeProfilePhoto()" id="removePhotoBtn" <?php echo !getProfilePhoto($user) ? 'style="display: none;"' : ''; ?>>
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                                <div class="file-name" id="fileName">
                                    <?php 
                                    if ($profile_photo = getProfilePhoto($user)) {
                                        echo basename($profile_photo);
                                    } else {
                                        echo 'No photo uploaded';
                                    }
                                    ?>
                                </div>
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    Max size: 5MB. Formats: JPG, PNG, GIF
                                </small>
                                
                                <!-- Photo Upload Messages -->
                                <div id="photoUploadMessage" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                        <div class="button-container" id="photoSaveButton" style="display: none;">
                            <button type="submit" class="btn-submit" id="savePhotoBtn" onclick="return handlePhotoSave()">
                                <i class="fas fa-save"></i> Save Photo Changes
                            </button>
                        </div>
                    </form>

                    <!-- Profile Info Update Form -->
                    <form method="POST" action="?tab=profile" class="settings-form" id="profileForm">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label for="username">Username <span style="color: #666; font-weight: normal;">(max 20 characters)</span></label>
                            <input type="text" id="username" name="username" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($user['username']); ?>" 
                                maxlength="20"
                                onkeyup="checkChanges()"
                                required>
                            <div class="char-counter" id="usernameCounter"><?php echo strlen($user['username']); ?>/20</div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span style="color: #666; font-weight: normal;">(max 40 characters, @gmail.com/@hotmail.com/@segi4u.my)</span></label>
                            <input type="email" id="email" name="email" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($user['email']); ?>" 
                                maxlength="40"
                                onkeyup="checkChanges()"
                                required>
                            <div class="char-counter" id="emailCounter"><?php echo strlen($user['email']); ?>/40</div>
                        </div>

                        <!-- Education Level Field -->
                        <div class="form-group">
                            <label for="education_level">Education Level</label>
                            <select id="education_level" name="education_level" class="form-control" onchange="checkChanges()">
                                <option value="">Select your education level</option>
                                <option value="Certificate" <?php echo ($education_level == 'Certificate') ? 'selected' : ''; ?>>Certificate</option>
                                <option value="Diploma" <?php echo ($education_level == 'Diploma') ? 'selected' : ''; ?>>Diploma</option>
                                <option value="Degree" <?php echo ($education_level == 'Degree') ? 'selected' : ''; ?>>Degree</option>
                                <option value="Master" <?php echo ($education_level == 'Master') ? 'selected' : ''; ?>>Master</option>
                                <option value="PhD" <?php echo ($education_level == 'PhD') ? 'selected' : ''; ?>>PhD</option>
                            </select>
                        </div>

                        <!-- Program Field -->
                        <div class="form-group">
                            <label for="program">Program of Study</label>
                            <select id="program" name="program" class="form-control" onchange="checkChanges()">
                                <option value="">Select your program</option>
                                <option value="Business Administration" <?php echo ($program == 'Business Administration') ? 'selected' : ''; ?>>Business Administration</option>
                                <option value="Accounting" <?php echo ($program == 'Accounting') ? 'selected' : ''; ?>>Accounting</option>
                                <option value="Information Technology" <?php echo ($program == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Early Childhood Education" <?php echo ($program == 'Early Childhood Education') ? 'selected' : ''; ?>>Early Childhood Education</option>
                                <option value="Hotel Management" <?php echo ($program == 'Hotel Management') ? 'selected' : ''; ?>>Hotel Management</option>
                                <option value="Culinary Arts" <?php echo ($program == 'Culinary Arts') ? 'selected' : ''; ?>>Culinary Arts</option>
                                <option value="Mechanical Engineering" <?php echo ($program == 'Mechanical Engineering') ? 'selected' : ''; ?>>Mechanical Engineering</option>
                                <option value="Nursing" <?php echo ($program == 'Nursing') ? 'selected' : ''; ?>>Nursing</option>
                                <option value="Mass Communication" <?php echo ($program == 'Mass Communication') ? 'selected' : ''; ?>>Mass Communication</option>
                                <option value="Graphic Design" <?php echo ($program == 'Graphic Design') ? 'selected' : ''; ?>>Graphic Design</option>
                                <option value="Computer Science" <?php echo ($program == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                            </select>
                        </div>

                        <!-- Semester Field -->
                        <div class="form-group">
                            <label for="semester">Intake Semester</label>
                            <select id="semester" name="semester" class="form-control" onchange="checkChanges()">
                                <option value="">Select your intake</option>
                                <option value="January" <?php echo ($semester == 'January') ? 'selected' : ''; ?>>January Intake</option>
                                <option value="May" <?php echo ($semester == 'May') ? 'selected' : ''; ?>>May Intake</option>
                                <option value="September" <?php echo ($semester == 'September') ? 'selected' : ''; ?>>September Intake</option>
                            </select>
                            <small style="color: #666;">When did you start your studies?</small>
                        </div>

                        <!-- Year Field -->
                        <div class="form-group">
                            <label for="year">Intake Year</label>
                            <select id="year" name="year" class="form-control" onchange="checkChanges()">
                                <option value="">Select your intake year</option>
                                <?php
                                for ($y = 2010; $y <= 2026; $y++) {
                                    $selected = ($year == $y) ? 'selected' : '';
                                    echo "<option value=\"$y\" $selected>$y</option>";
                                }
                                ?>
                            </select>
                            <small style="color: #666;">Year you started your program</small>
                        </div>

                        <!-- Student ID Field -->
                        <div class="form-group">
                            <label for="student_id">Student ID</label>
                            <input type="text" id="student_id" name="student_id" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($student_id); ?>" 
                                placeholder="Enter your student ID"
                                pattern="SCSJ\d{7}"
                                onkeyup="checkChanges()"
                                title="Must be in format: SCSJ followed by 7 digits">
                            <div class="format-hint">
                                <i class="fas fa-info-circle"></i> Format: SCSJ2200000 (SCSJ + 7 digits)
                            </div>
                        </div>

                        <!-- Academic Information Display -->
                        <div class="account-info">
                            <h4 style="margin-bottom: 15px; color: #667eea;">Academic Information</h4>
                            <div class="info-item">
                                <span class="info-label">Education Level</span>
                                <span class="info-value"><?php echo !empty($education_level) ? htmlspecialchars($education_level) : 'Not set'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Program</span>
                                <span class="info-value"><?php echo !empty($program) ? htmlspecialchars($program) : 'Not set'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Intake</span>
                                <span class="info-value">
                                    <?php 
                                    if (!empty($semester) && !empty($year)) {
                                        echo htmlspecialchars($semester) . ' ' . htmlspecialchars($year);
                                    } elseif (!empty($semester)) {
                                        echo htmlspecialchars($semester) . ' Intake';
                                    } elseif (!empty($year)) {
                                        echo 'Year: ' . htmlspecialchars($year);
                                    } else {
                                        echo 'Not set';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Student ID</span>
                                <span class="info-value"><?php echo !empty($student_id) ? htmlspecialchars($student_id) : 'Not set'; ?></span>
                            </div>
                        </div>

                        <div class="button-container">
                            <button type="submit" class="btn-submit" id="saveChangesBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Settings Tab -->
            <div id="password-tab" class="settings-content <?php echo $active_tab === 'password' ? 'active' : ''; ?>">
                <div class="settings-card">
                    <div class="settings-header">
                        <div class="settings-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="settings-title">
                            <h3>Change Password</h3>
                            <p>Update your account password (6-15 characters, no spaces)</p>
                        </div>
                    </div>

                    <?php if (!$is_password_secure): ?>
                    <div class="password-diagnostic">
                        <h5><i class="fas fa-info-circle"></i> Password Security Notice</h5>
                        <p style="color: #d32f2f; margin-top: 10px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Security Recommendation:</strong> Your password is stored in an older, less secure format. 
                            Changing your password now will upgrade it to our current security standard.
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if ($error && $active_tab === 'password'): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success && $active_tab === 'password'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="?tab=password" class="settings-form" id="passwordForm">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="form-control" placeholder="Enter current password" required
                                   onkeypress="return preventSpace(event)" maxlength="15">
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="form-control" placeholder="Enter new password (6-15 characters)" required
                                   onkeyup="checkPasswordStrength()" onkeypress="return preventSpace(event)" 
                                   maxlength="15" minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="password-strength" id="passwordStrength" style="margin-top: 8px;"></div>
                            <div class="password-hint">
                                <i class="fas fa-info-circle"></i> 
                                Password must be 6-15 characters, no spaces allowed
                            </div>
                            <div class="char-counter" id="newPasswordCounter">0/15</div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="form-control" placeholder="Confirm new password" required
                                   oninput="checkPasswordMatch()" onkeypress="return preventSpace(event)"
                                   maxlength="15">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div id="passwordMatch" style="margin-top: 8px;"></div>
                        </div>

                        <div class="button-container">
                            <button type="submit" class="btn-submit" id="submitBtn" disabled>
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Problem Tab -->
            <div id="report-tab" class="settings-content <?php echo $active_tab === 'report' ? 'active' : ''; ?>">
                <div class="settings-card">
                    <div class="settings-header">
                        <div class="settings-icon">
                            <i class="fas fa-flag"></i>
                        </div>
                        <div class="settings-title">
                            <h3>Report a Problem</h3>
                            <p>Help us improve SkyMeet by reporting issues</p>
                        </div>
                    </div>

                    <?php if ($report_error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($report_error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($report_success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($report_success); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="?tab=report" class="settings-form">
                        <input type="hidden" name="report_problem" value="1">
                        
                        <div class="form-group">
                            <label for="problem_category">Problem Category</label>
                            <select id="problem_category" name="problem_category" class="form-control" required>
                                <option value="">Select a category</option>
                                <option value="bug">Bug or Error</option>
                                <option value="feature">Feature Request</option>
                                <option value="ui">User Interface Issue</option>
                                <option value="performance">Performance Problem</option>
                                <option value="security">Security Concern</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="contact_email">Contact Email</label>
                            <input type="email" id="contact_email" name="contact_email" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   placeholder="Where we can contact you for follow-up" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="problem_description">Problem Description</label>
                            <textarea id="problem_description" name="problem_description" 
                                      class="form-control" 
                                      rows="6" 
                                      placeholder="Please describe the problem in detail. Include steps to reproduce if applicable."
                                      required></textarea>
                            <div class="char-count">
                                Character count: <span id="charCount">0</span>/2000
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span>We'll review your report and get back to you within 24-48 hours.</span>
                        </div>

                        <div class="button-container">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane"></i> Submit Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Deletion Tab -->
            <div id="delete-tab" class="settings-content <?php echo $active_tab === 'delete' ? 'active' : ''; ?>">
                <div class="settings-card">
                    <div class="danger-zone">
                        <div class="danger-header">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h4>Account Deletion</h4>
                        </div>
                        
                        <?php if ($error && $active_tab === 'delete'): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="danger-text">
                            <p><strong>Warning:</strong> This action is permanent and cannot be undone.</p>
                            <p>Deleting your account will permanently remove:</p>
                            <ul style="margin: 10px 0 20px 20px; color: #c62828;">
                                <li>Your profile information and photo</li>
                                <li>All meetings you created</li>
                                <li>All messages and chat history</li>
                                <li>All uploaded files and recordings</li>
                                <li>All team memberships and data</li>
                            </ul>
                            <p>This action cannot be reversed. All your data will be lost forever.</p>
                        </div>

                        <form method="POST" action="?tab=delete" onsubmit="return confirmDelete()">
                            <input type="hidden" name="delete_account" value="1">
                            
                            <div class="delete-confirm">
                                <label for="confirm_delete">
                                    Type <strong>DELETE</strong> to confirm account deletion:
                                </label>
                                <input type="text" id="confirm_delete" name="confirm_delete" 
                                       class="form-control" 
                                       placeholder="Type DELETE here"
                                       autocomplete="off">
                            </div>

                            <div class="button-container">
                                <button type="submit" class="btn-delete">
                                    <i class="fas fa-trash-alt"></i> Delete Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Track if photo is selected
        let photoSelected = false;
        let selectedPhotoFile = null;

        // Original values for change detection
        const originalValues = {
            username: '<?php echo addslashes($original_username); ?>',
            email: '<?php echo addslashes($original_email); ?>',
            education_level: '<?php echo addslashes($original_education_level); ?>',
            program: '<?php echo addslashes($original_program); ?>',
            semester: '<?php echo addslashes($original_semester); ?>',
            year: '<?php echo addslashes($original_year); ?>',
            student_id: '<?php echo addslashes($original_student_id); ?>',
            photo: '<?php echo addslashes($original_photo); ?>'
        };

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

        // Ensure dropdown is closed when page loads (especially after navigation)
        document.addEventListener('DOMContentLoaded', function() {
            // Force dropdown to be closed on page load
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (dropdownIcon) {
                dropdownIcon.style.transform = 'rotate(0)';
            }
            
            // Initialize character counters
            updateUsernameCounter();
            updateEmailCounter();
            
            // Initialize password strength check
            checkPasswordStrength();
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

        // Prevent space in password fields
        function preventSpace(event) {
            if (event.key === ' ') {
                event.preventDefault();
                
                // Show warning
                const input = event.target;
                const originalBorder = input.style.borderColor;
                input.style.borderColor = '#f44336';
                setTimeout(() => {
                    input.style.borderColor = originalBorder;
                }, 500);
                
                return false;
            }
            return true;
        }

        // Tab switching with unsaved changes check
        function checkUnsavedChangesBeforeLeave() {
            if (hasUnsavedChanges()) {
                showNotification();
                return false;
            }
            return true;
        }

        // Check for unsaved changes (including photo)
        function hasUnsavedChanges() {
            const currentUsername = document.getElementById('username')?.value || '';
            const currentEmail = document.getElementById('email')?.value || '';
            const currentEducation = document.getElementById('education_level')?.value || '';
            const currentProgram = document.getElementById('program')?.value || '';
            const currentSemester = document.getElementById('semester')?.value || '';
            const currentYear = document.getElementById('year')?.value || '';
            const currentStudentId = document.getElementById('student_id')?.value || '';

            // Check if form fields have changed
            const formChanged = currentUsername !== originalValues.username ||
                   currentEmail !== originalValues.email ||
                   currentEducation !== originalValues.education_level ||
                   currentProgram !== originalValues.program ||
                   currentSemester !== originalValues.semester ||
                   currentYear !== originalValues.year ||
                   currentStudentId !== originalValues.student_id;

            // Check if photo is selected but not saved
            const photoChanged = photoSelected === true;

            return formChanged || photoChanged;
        }

        // Show unsaved changes notification (centered)
        function showNotification() {
            document.getElementById('unsavedNotification').classList.add('show');
            document.getElementById('notificationOverlay').classList.add('show');
        }

        // Hide notification
        function hideNotification() {
            document.getElementById('unsavedNotification').classList.remove('show');
            document.getElementById('notificationOverlay').classList.remove('show');
        }

        // Save all changes - triggers the appropriate save buttons
        function saveAllChanges() {
            // Check if photo is selected and needs to be saved
            if (photoSelected) {
                // Submit photo form
                document.getElementById('photoUploadForm').submit();
            }
            
            // Check if form fields have changes
            if (hasUnsavedFormChanges()) {
                // Submit profile form
                document.getElementById('profileForm').submit();
            }
            
            // Hide notification after saving
            hideNotification();
        }

        // Check if form fields have changes (excluding photo)
        function hasUnsavedFormChanges() {
            const currentUsername = document.getElementById('username')?.value || '';
            const currentEmail = document.getElementById('email')?.value || '';
            const currentEducation = document.getElementById('education_level')?.value || '';
            const currentProgram = document.getElementById('program')?.value || '';
            const currentSemester = document.getElementById('semester')?.value || '';
            const currentYear = document.getElementById('year')?.value || '';
            const currentStudentId = document.getElementById('student_id')?.value || '';

            return currentUsername !== originalValues.username ||
                   currentEmail !== originalValues.email ||
                   currentEducation !== originalValues.education_level ||
                   currentProgram !== originalValues.program ||
                   currentSemester !== originalValues.semester ||
                   currentYear !== originalValues.year ||
                   currentStudentId !== originalValues.student_id;
        }

        // Handle photo save
        function handlePhotoSave() {
            photoSelected = false;
            return true;
        }

        // Check changes on input
        function checkChanges() {
            updateUsernameCounter();
            updateEmailCounter();
        }

        // Update username counter (max 20)
        function updateUsernameCounter() {
            const username = document.getElementById('username');
            const counter = document.getElementById('usernameCounter');
            if (username && counter) {
                const length = username.value.length;
                counter.textContent = length + '/20';
                counter.classList.toggle('warning', length > 15);
                counter.classList.toggle('error', length > 20);
            }
        }

        // Update email counter (max 40)
        function updateEmailCounter() {
            const email = document.getElementById('email');
            const counter = document.getElementById('emailCounter');
            if (email && counter) {
                const length = email.value.length;
                counter.textContent = length + '/40';
                counter.classList.toggle('warning', length > 35);
                counter.classList.toggle('error', length > 40);
            }
        }

        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.settings-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.nav-tab').forEach(navTab => {
                navTab.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update URL without page reload
            history.pushState(null, null, '?tab=' + tabName);
        }

        // Preview selected photo (but don't upload yet)
        function previewSelectedPhoto(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                selectedPhotoFile = file;
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    showPhotoMessage('error', 'Invalid file type. Only JPG, PNG, and GIF are allowed.');
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showPhotoMessage('error', 'File size is too large (max 5MB).');
                    return;
                }
                
                // Show preview
                const preview = document.getElementById('profilePhotoPreview');
                const fileName = document.getElementById('fileName');
                fileName.textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Profile Photo Preview" id="profilePhotoImg">`;
                }
                reader.readAsDataURL(file);
                
                // Show save button for photo
                document.getElementById('photoSaveButton').style.display = 'block';
                document.getElementById('photoPreviewBadge').style.display = 'flex';
                
                // Mark photo as selected
                photoSelected = true;
                
                // Show message
                showPhotoMessage('info', 'Click "Save Photo Changes" to upload your new photo');
            }
        }

        // Show photo upload message
        function showPhotoMessage(type, message) {
            const messageDiv = document.getElementById('photoUploadMessage');
            messageDiv.innerHTML = `<div class="alert alert-${type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success')}"><i class="fas fa-${type === 'error' ? 'exclamation-circle' : (type === 'info' ? 'info-circle' : 'check-circle')}"></i> ${message}</div>`;
            
            setTimeout(() => {
                messageDiv.innerHTML = '';
            }, 3000);
        }

        // REMOVE PROFILE PHOTO FUNCTION
        function removeProfilePhoto() {
            if (confirm('Are you sure you want to remove your profile photo?')) {
                // Show loading state
                const removeBtn = document.getElementById('removePhotoBtn');
                const originalText = removeBtn.innerHTML;
                removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';
                removeBtn.disabled = true;
                
                // Send AJAX request to remove photo
                fetch('?tab=profile&remove_photo=1', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showPhotoMessage('success', 'Photo removed successfully!');
                        
                        // Update preview to show default icon
                        document.getElementById('profilePhotoPreview').innerHTML = '<i class="fas fa-user"></i>';
                        
                        // Update sidebar avatar to show initials
                        const sidebarAvatar = document.getElementById('sidebarAvatar');
                        sidebarAvatar.innerHTML = '<?php echo getInitials($username); ?>';
                        
                        // Hide remove button
                        document.getElementById('removePhotoBtn').style.display = 'none';
                        
                        // Update file name
                        document.getElementById('fileName').textContent = 'No photo uploaded';
                        
                        // Hide photo save button
                        document.getElementById('photoSaveButton').style.display = 'none';
                        document.getElementById('photoPreviewBadge').style.display = 'none';
                        
                        // Reset photo selected flag
                        photoSelected = false;
                        
                        // Refresh the page after a short delay
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showPhotoMessage('error', data.error || 'Failed to remove photo');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showPhotoMessage('error', 'Failed to remove photo. Please try again.');
                })
                .finally(() => {
                    // Reset button
                    removeBtn.innerHTML = originalText;
                    removeBtn.disabled = false;
                });
            }
        }

        // Password toggle functionality
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthDiv = document.getElementById('passwordStrength');
            const submitBtn = document.getElementById('submitBtn');
            const counter = document.getElementById('newPasswordCounter');
            
            // Update character counter
            if (counter) {
                counter.textContent = password.length + '/15';
                if (password.length > 15) {
                    counter.style.color = '#f44336';
                } else if (password.length >= 6) {
                    counter.style.color = '#4caf50';
                } else {
                    counter.style.color = '#666';
                }
            }
            
            // Check length and spaces
            const hasSpaces = /\s/.test(password);
            const lengthValid = password.length >= 6 && password.length <= 15;
            
            if (hasSpaces) {
                strengthDiv.innerHTML = '<span class="strength-weak"><i class="fas fa-times-circle"></i> Password cannot contain spaces</span>';
                if (submitBtn) submitBtn.disabled = true;
            } else if (password.length === 0) {
                strengthDiv.innerHTML = '';
                if (submitBtn) submitBtn.disabled = true;
            } else if (password.length < 6) {
                strengthDiv.innerHTML = '<span class="strength-weak"><i class="fas fa-exclamation-triangle"></i> Too short (minimum 6 characters)</span>';
                if (submitBtn) submitBtn.disabled = true;
            } else if (password.length > 15) {
                strengthDiv.innerHTML = '<span class="strength-weak"><i class="fas fa-exclamation-triangle"></i> Too long (maximum 15 characters)</span>';
                if (submitBtn) submitBtn.disabled = true;
            } else {
                // Check password complexity
                let strength = 0;
                if (password.match(/[a-z]+/)) strength++;
                if (password.match(/[A-Z]+/)) strength++;
                if (password.match(/[0-9]+/)) strength++;
                if (password.match(/[$@#&!]+/)) strength++;
                
                let strengthText = '';
                let strengthClass = '';
                
                if (strength < 2) {
                    strengthText = 'Weak';
                    strengthClass = 'strength-weak';
                } else if (strength < 3) {
                    strengthText = 'Medium';
                    strengthClass = 'strength-medium';
                } else if (strength < 4) {
                    strengthText = 'Strong';
                    strengthClass = 'strength-strong';
                } else {
                    strengthText = 'Very Strong';
                    strengthClass = 'strength-very-strong';
                }
                
                strengthDiv.innerHTML = '<span class="' + strengthClass + '"><i class="fas fa-shield-alt"></i> Password strength: ' + strengthText + '</span>';
                
                // Enable submit button only if passwords match and meet requirements
                const confirmPassword = document.getElementById('confirm_password').value;
                if (confirmPassword !== '' && password === confirmPassword) {
                    if (submitBtn) submitBtn.disabled = false;
                } else {
                    if (submitBtn) submitBtn.disabled = true;
                }
            }
            
            if (document.getElementById('confirm_password').value !== '') {
                checkPasswordMatch();
            }
        }

        // Check password match
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmPassword === '') {
                matchDiv.innerHTML = '';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }
            
            // Check if passwords have spaces
            if (/\s/.test(newPassword) || /\s/.test(confirmPassword)) {
                matchDiv.innerHTML = '<span style="color: #f44336;"><i class="fas fa-times-circle"></i> Passwords cannot contain spaces</span>';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }
            
            // Check length
            if (newPassword.length < 6 || newPassword.length > 15) {
                matchDiv.innerHTML = '<span style="color: #f44336;"><i class="fas fa-times-circle"></i> Password must be 6-15 characters</span>';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }
            
            if (newPassword === confirmPassword) {
                matchDiv.innerHTML = '<span style="color: #4caf50;"><i class="fas fa-check-circle"></i> Passwords match!</span>';
                if (submitBtn && newPassword.length >= 6 && newPassword.length <= 15 && !/\s/.test(newPassword)) {
                    submitBtn.disabled = false;
                }
            } else {
                matchDiv.innerHTML = '<span style="color: #f44336;"><i class="fas fa-times-circle"></i> Passwords do not match!</span>';
                if (submitBtn) submitBtn.disabled = true;
            }
        }

        // Character count for problem description
        function updateCharCount() {
            const textarea = document.getElementById('problem_description');
            const charCount = document.getElementById('charCount');
            if (textarea && charCount) {
                const count = textarea.value.length;
                charCount.textContent = count;
                
                if (count > 2000) {
                    charCount.style.color = '#f44336';
                } else if (count > 1500) {
                    charCount.style.color = '#ff9800';
                } else {
                    charCount.style.color = '#666';
                }
            }
        }

        // Confirm account deletion
        function confirmDelete() {
            const confirmText = document.getElementById('confirm_delete').value;
            
            if (confirmText !== 'DELETE') {
                alert('Please type DELETE in the confirmation field');
                return false;
            }
            
            return confirm('⚠️ WARNING: This will permanently delete your account and all associated data!\n\nAre you absolutely sure you want to continue?');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if password fields exist
            if (document.getElementById('new_password')) {
                checkPasswordStrength();
                checkPasswordMatch();
            }
            
            // Setup problem description character counter
            const problemTextarea = document.getElementById('problem_description');
            if (problemTextarea) {
                problemTextarea.addEventListener('input', updateCharCount);
                updateCharCount();
            }
            
            // Handle tab clicks
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.addEventListener('click', function(e) {
                    if (!this.classList.contains('active')) {
                        if (!checkUnsavedChangesBeforeLeave()) {
                            e.preventDefault();
                            return false;
                        }
                        const href = this.getAttribute('href');
                        const tabName = href.split('=')[1];
                        switchTab(tabName);
                        e.preventDefault();
                    }
                });
            });
            
            // Check URL for tab parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam && document.getElementById(tabParam + '-tab')) {
                switchTab(tabParam);
            }
            
            // Add input listeners for unsaved changes detection
            const inputs = ['username', 'email', 'education_level', 'program', 'semester', 'year', 'student_id'];
            inputs.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('input', checkChanges);
                    element.addEventListener('change', checkChanges);
                }
            });
            
            // Close notification when clicking overlay
            document.getElementById('notificationOverlay').addEventListener('click', hideNotification);
            
            // Add beforeunload event listener for unsaved changes
            window.addEventListener('beforeunload', function(e) {
                if (hasUnsavedChanges()) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert:not(#photoUploadMessage .alert)').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.display = 'none';
                    }
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>