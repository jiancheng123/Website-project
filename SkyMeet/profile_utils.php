<?php
// Helper function to get initials from username
if (!function_exists('getInitials')) {
    function getInitials($username) {
        if (empty($username)) return 'U';
        if (strlen($username) >= 2) {
            return strtoupper(substr($username, 0, 2));
        }
        return strtoupper($username . $username);
    }
}

function getProfilePhoto($user) {
    if (!empty($user['profile_photo'])) {
        $photo_path = 'uploads/profile_photos/' . $user['profile_photo'];
        if (file_exists($photo_path)) {
            return $photo_path;
        }
    }
    return false;
}

function getCurrentUser($conn, $user_id) {
    $sql = "SELECT id, username, email, profile_photo FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}
?>