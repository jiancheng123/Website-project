<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    $_SESSION['login_error'] = "Please log in to access the Home.";
    $_SESSION['active_form'] = 'login';
    header("Location: login.php");
    exit;
}

// Database connection configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "assignment";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['login_error'] = "Database connection failed: " . $e->getMessage();
    $_SESSION['active_form'] = 'login';
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $status = $_POST['status'] ?? '';
    $admission_year = $_POST['admission_year'] ?? '';
    $level_of_study = $_POST['level_of_study'] ?? '';
    $department = $_POST['department'] ?? '';
    $college = $_POST['college'] ?? '';
    $intake = $_POST['intake'] ?? '';

    try {
        $stmt = $pdo->prepare("
            UPDATE student_info 
            SET status = ?, admission_year = ?, level_of_study = ?, department = ?, college = ?, intake = ?
            WHERE student_id = (SELECT student_id FROM users WHERE id = ?)
        ");
        $stmt->execute([$status, $admission_year, $level_of_study, $department, $college, $intake, $_SESSION['user_id']]);
        $_SESSION['success_message'] = "Information updated successfully!";
        header("Location: home.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to update information: " . $e->getMessage();
    }
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT u.username, s.profile_photo, si.status, si.admission_year, si.student_id, 
           si.level_of_study, si.department, si.college, si.intake
    FROM users u
    LEFT JOIN student_info si ON u.student_id = si.student_id
    LEFT JOIN skills s ON u.student_id = s.student_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['login_error'] = "User not found.";
    $_SESSION['active_form'] = 'login';
    header("Location: login.php");
    exit;
}

// Sanitize output
function sanitize($data) {
    return htmlspecialchars(trim($data));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Landing Page</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Home Section */
        #home, #about {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        #home h2, #about h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        /* Student Photo Section */
        .student-photo {
            text-align: center;
            margin-bottom: 20px;
        }

        .student-photo img {
            max-width: 150px;
            height: auto;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .student-photo p {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }

        /* Status Table */
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .status-table th, .status-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        .status-table th {
            background-color: #f8f8f8;
            width: 30%;
        }

        .status-table td {
            background-color: #fff;
        }

        /* Edit Form Styles */
        .edit-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .edit-button:hover {
            background-color: #45a049;
        }

        .edit-form {
            display: none;
            margin-top: 20px;
        }

        .edit-form.active {
            display: block;
        }

        .edit-form input, .edit-form select {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .edit-form .form-buttons {
            text-align: center;
            margin-top: 20px;
        }

        .edit-form .form-buttons button {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .edit-form .save-button {
            background-color: #4CAF50;
            color: white;
        }

        .edit-form .save-button:hover {
            background-color: #45a049;
        }

        .edit-form .cancel-button {
            background-color: #f44336;
            color: white;
        }

        .edit-form .cancel-button:hover {
            background-color: #da190b;
        }

        .message {
            text-align: center;
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }

        .success-message {
            background-color: #dff0d8;
            color: #3c763d;
        }

        .error-message {
            background-color: #f2dede;
            color: #a94442;
        }
    </style>
</head>
<body>
    <nav>
        <div class="Navigation-bar">
            <ul>
                <li><a href="home.php">Home</a></li>
                <li><a href="Aboutme.php">About Me</a></li>
                <li><a href="Skills.php">Skills & Projects</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li>
                    <form action="logout.php" method="post" class="logout-form">
                        <button type="submit" class="logout-button">Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </nav>

    <section id="home">
        <h2>Student Status</h2>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message"><?php echo sanitize($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo sanitize($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        <div class="student-photo">
            <?php if ($user['profile_photo'] && file_exists($user['profile_photo'])): ?>
                <img src="<?php echo sanitize($user['profile_photo']); ?>" alt="Student Photo">
            <?php else: ?>
                <p>No profile photo uploaded.</p>
            <?php endif; ?>
            <p><?php echo sanitize($user['username']); ?></p>
        </div>

        <table class="status-table">
            <tr><th>Status</th><td><?php echo sanitize($user['status'] ?: 'Not provided'); ?></td></tr>
            <tr><th>Admission Year</th><td><?php echo sanitize($user['admission_year'] ?: 'Not provided'); ?></td></tr>
            <tr><th>Student ID</th><td><?php echo sanitize($user['student_id'] ?: 'Not provided'); ?></td></tr>
            <tr><th>Degree</th><td><?php echo sanitize($user['level_of_study'] ?: 'Not provided'); ?></td></tr>
            <tr><th>Department</th><td><?php echo sanitize($user['department'] ?: 'Not provided'); ?></td></tr>
            <tr><th>College</th><td><?php echo sanitize($user['college'] ?: 'Not provided'); ?></td></tr>
            <tr><th>Intake</th><td><?php echo sanitize($user['intake'] ?: 'Not provided'); ?></td></tr>
        </table>

        <button class="edit-button" onclick="toggleEditForm()">Edit Information</button>

        <form class="edit-form" id="editForm" method="post" action="home.php">
            <input type="hidden" name="update_info" value="1">
            <label for="status">Status:</label>
            <select name="status" id="status">
                <option value="Active" <?php echo $user['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo $user['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="Graduated" <?php echo $user['status'] === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
            </select>

            <label for="admission_year">Admission Year:</label>
            <input type="number" name="admission_year" id="admission_year" value="<?php echo sanitize($user['admission_year'] ?: ''); ?>" placeholder="e.g., 2020">

            <label for="level_of_study">Degree:</label>
            <input type="text" name="level_of_study" id="level_of_study" value="<?php echo sanitize($user['level_of_study'] ?: ''); ?>" placeholder="e.g., Bachelor's">

            <label for="department">Department:</label>
            <input type="text" name="department" id="department" value="<?php echo sanitize($user['department'] ?: ''); ?>" placeholder="e.g., Computer Science">

            <label for="college">College:</label>
            <input type="text" name="college" id="college" value="<?php echo sanitize($user['college'] ?: ''); ?>" placeholder="e.g., College of Engineering">

            <label for="intake">Intake:</label>
            <input type="text" name="intake" id="intake" value="<?php echo sanitize($user['intake'] ?: ''); ?>" placeholder="e.g., Fall 2020">

            <div class="form-buttons">
                <button type="submit" class="save-button">Save Changes</button>
                <button type="button" class="cancel-button" onclick="toggleEditForm()">Cancel</button>
            </div>
        </form>
    </section>

    <script>
        function toggleEditForm() {
            const form = document.getElementById('editForm');
            form.classList.toggle('active');
        }
    </script>
</body>
</html>