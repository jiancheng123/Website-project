<?php
session_start();

// Database connection configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "assignment";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['register_error'] = "Database connection failed: " . $e->getMessage();
    $_SESSION['active_form'] = 'register';
    header("Location: login.php");
    exit;
}

// Function to sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        // Handle Login
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = "Username and password are required.";
            $_SESSION['active_form'] = 'login';
            header("Location: login.php");
            exit;
        }

        // Check if username exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Successful login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: home.php");
            exit;
        } else {
            $_SESSION['login_error'] = "Invalid username or password.";
            $_SESSION['active_form'] = 'login';
            header("Location: login.php");
            exit;
        }
    } elseif (isset($_POST['register'])) {
        // Handle Registration
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $email = sanitize($_POST['email']);
        $student_id = sanitize($_POST['student_id']);
        $status = sanitize($_POST['status']);
        $admission_year = sanitize($_POST['admission_year']);
        $intake = sanitize($_POST['intake']);
        $level_of_study = sanitize($_POST['level_of_study']);
        $department = sanitize($_POST['department']);
        $college = sanitize($_POST['college']);
        $introduction = sanitize($_POST['introduction']);
        $background = sanitize($_POST['background']);
        $interests = sanitize($_POST['interests']);
        $academic_goals = sanitize($_POST['academic_goals']);
        $technical_skills = sanitize($_POST['technical_skills']);
        $soft_skills = sanitize($_POST['soft_skills']);

        // Validation
        $errors = [];

        // Username validation
        if (!preg_match("/^[a-zA-Z0-9_]{3,50}$/", $username)) {
            $errors[] = "Username must be 3-50 characters, letters, numbers, or underscores.";
        }

        // Password validation
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }

        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        // Student ID validation
        if (!preg_match("/^SCSJ[0-9]{7}$/", $student_id)) {
            $errors[] = "Student ID must follow the format SCSJ followed by 7 digits.";
        }

        // Status validation
        if (!in_array($status, ['Active', 'Inactive', 'Graduated'])) {
            $errors[] = "Invalid status selected.";
        }

        // Admission year validation
        $current_year = date('Y');
        if ($admission_year < 1970 || $admission_year > $current_year) {
            $errors[] = "Admission year must be between 1970 and $current_year.";
        }

        // Intake validation
        if (!preg_match("/^[0-9]{6}$/", $intake)) {
            $errors[] = "Intake must be in the format YYYYMM (e.g., 202409).";
        }

        // Level of study validation
        if (!in_array($level_of_study, ['Certificate', 'Diploma', 'Degree'])) {
            $errors[] = "Invalid level of study selected.";
        }

        // Department validation
        $valid_departments = ['Information Technology', 'Computer Science', 'Engineering', 'Business', 'Arts', 'Science'];
        if (!in_array($department, $valid_departments)) {
            $errors[] = "Invalid department selected.";
        }

        // College validation
        $valid_colleges = ['SEGi College Subang Jaya', 'SEGi College Kuala Lumpur', 'SEGi College Penang', 'SEGi College Sarawak', 'SEGi College Kota Damansara', 'SEGi University Kota Damansara'];
        if (!in_array($college, $valid_colleges)) {
            $errors[] = "Invalid college selected.";
        }

        // Profile photo handling
        $profile_photo = null;
        if (!empty($_FILES['profile_photo']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_type = $_FILES['profile_photo']['type'];
            $file_size = $_FILES['profile_photo']['size'];
            $file_tmp = $_FILES['profile_photo']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $file_name = uniqid() . '.' . $file_ext;
            $upload_dir = 'Uploads/';
            $upload_path = $upload_dir . $file_name;

            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Profile photo must be a JPEG, PNG, or GIF image.";
            } elseif ($file_size > $max_size) {
                $errors[] = "Profile photo must not exceed 5MB.";
            } else {
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                if (!move_uploaded_file($file_tmp, $upload_path)) {
                    $errors[] = "Failed to upload profile photo.";
                } else {
                    $profile_photo = $upload_path;
                }
            }
        }

        // Text field length validations
        if (strlen($introduction) > 200) {
            $errors[] = "Introduction must not exceed 200 characters.";
        }
        if (strlen($background) > 300) {
            $errors[] = "Background must not exceed 300 characters.";
        }
        if (strlen($interests) > 300) {
            $errors[] = "Interests must not exceed 300 characters.";
        }
        if (strlen($academic_goals) > 300) {
            $errors[] = "Academic goals must not exceed 300 characters.";
        }

        // Check if username, email, or student ID already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ? OR student_id = ?");
        $stmt->execute([$username, $email, $student_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username, email, or student ID already exists.";
        }

        if (!empty($errors)) {
            $_SESSION['register_error'] = implode("<br>", $errors);
            $_SESSION['active_form'] = 'register';
            header("Location: login.php");
            exit;
        }

        // Start transaction
        try {
            $pdo->beginTransaction();

            // Insert into users table
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, email, student_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hashed_password, $email, $student_id]);
            $user_id = $pdo->lastInsertId();

            // Insert into student_info table
            $stmt = $pdo->prepare("
                INSERT INTO student_info (student_id, status, admission_year, intake, level_of_study, department, college)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $status, $admission_year, $intake, $level_of_study, $department, $college]);

            // Insert into skills table
            $stmt = $pdo->prepare("
                INSERT INTO skills (student_id, profile_photo, introduction, background, interests, academic_goals, technical_skills, soft_skills)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $profile_photo, $introduction, $background, $interests, $academic_goals, $technical_skills, $soft_skills]);

            // Commit transaction
            $pdo->commit();

            // Successful registration
            $_SESSION['register_error'] = "Registration successful! Please login.";
            $_SESSION['active_form'] = 'login';
            header("Location: login.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['register_error'] = "Registration failed: " . $e->getMessage();
            $_SESSION['active_form'] = 'register';
            header("Location: login.php");
            exit;
        }
    }
}
?>