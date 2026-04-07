<?php
session_start();
require_once 'connect.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Get form data
    $username = sanitize_input($_POST['username'] ?? '', $conn);
    $email = sanitize_input($_POST['email'] ?? '', $conn);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!validate_email($email)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!in_array($role, ['user', 'admin'])) {
        $errors[] = 'Invalid role selected';
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Check if username already exists
        if (username_exists($conn, $username)) {
            set_error_message('Username already exists');
            header('Location: login.php');
            exit();
        }
        
        // Check if email already exists
        if (email_exists($conn, $email)) {
            set_error_message('Email already registered');
            header('Location: login.php');
            exit();
        }
        
        // Hash the password
        $hashedPassword = md5($password);
        
        // Insert new user
        $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $username, $email, $hashedPassword, $role);
        
        if ($stmt->execute()) {
            // Get the new user ID
            $user_id = $stmt->insert_id;
            
            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            
            // Log the activity
            log_activity($conn, $user_id, 'registration', 'New user registered');
            
            set_success_message('Registration successful! Welcome to JustMeet.');
            header('Location: dashboard.php');
            exit();
        } else {
            set_error_message('Registration failed. Please try again.');
            header('Location: login.php');
            exit();
        }
        
        $stmt->close();
    } else {
        // Store errors in session
        set_error_message(implode('. ', $errors));
        header('Location: login.php');
        exit();
    }
} else {
    // If not a POST request or not registration, redirect to login
    header('Location: login.php');
    exit();
}
?>