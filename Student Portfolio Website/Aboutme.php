<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    $_SESSION['login_error'] = "Please log in to access About Me.";
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
    $introduction = $_POST['introduction'] ?? '';
    $background = $_POST['background'] ?? '';
    $interests = $_POST['interests'] ?? '';
    $academic_goals = $_POST['academic_goals'] ?? '';
    $technical_skills = $_POST['technical_skills'] ?? '';
    $soft_skills = $_POST['soft_skills'] ?? '';

    try {
        $stmt = $pdo->prepare("
            UPDATE skills 
            SET introduction = ?, background = ?, interests = ?, academic_goals = ?, technical_skills = ?, soft_skills = ?
            WHERE student_id = (SELECT student_id FROM users WHERE id = ?)
        ");
        $stmt->execute([$introduction, $background, $interests, $academic_goals, $technical_skills, $soft_skills, $_SESSION['user_id']]);
        $_SESSION['success_message'] = "Information updated successfully!";
        header("Location: Aboutme.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to update information: " . $e->getMessage();
    }
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT u.username, s.profile_photo, s.introduction, s.background, s.interests, 
           s.academic_goals, s.technical_skills, s.soft_skills
    FROM users u
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
    <title>About Me</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* About Me Section */
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

        .edit-form label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        .edit-form textarea, .edit-form input {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .edit-form textarea {
            min-height: 100px;
            resize: vertical;
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
    <!-- Navigation Bar -->
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

    <!-- About Me Section -->
    <section id="about">
        <h2>About Me</h2>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message"><?php echo sanitize($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo sanitize($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        <div class="student-photo">
            <?php if ($user['profile_photo'] && file_exists($user['profile_photo'])): ?>
                <img src="<?php echo sanitize($user['profile_photo']); ?>" alt="Profile Photo">
            <?php else: ?>
                <p>No profile photo uploaded.</p>
            <?php endif; ?>
            <p><?php echo sanitize($user['username']); ?></p>
        </div>
        <table class="status-table">
            <tr>
                <th>Introduction</th>
                <td><?php echo sanitize($user['introduction'] ?: 'Not provided'); ?></td>
            </tr>
            <tr>
                <th>Background</th>
                <td><?php echo sanitize($user['background'] ?: 'Not provided'); ?></td>
            </tr>
            <tr>
                <th>Interests</th>
                <td><?php echo sanitize($user['interests'] ?: 'Not provided'); ?></td>
            </tr>
            <tr>
                <th>Academic Goals</th>
                <td><?php echo sanitize($user['academic_goals'] ?: 'Not provided'); ?></td>
            </tr>
            <tr>
                <th>Technical Skills</th>
                <td><?php echo sanitize($user['technical_skills'] ?: 'Not provided'); ?></td>
            </tr>
            <tr>
                <th>Soft Skills</th>
                <td><?php echo sanitize($user['soft_skills'] ?: 'Not provided'); ?></td>
            </tr>
        </table>

        <button class="edit-button" onclick="toggleEditForm()">Edit Information</button>

        <form class="edit-form" id="editForm" method="post" action="Aboutme.php">
            <input type="hidden" name="update_info" value="1">
            <label for="introduction">Introduction:</label>
            <textarea name="introduction" id="introduction" placeholder="Write a brief introduction about yourself"><?php echo sanitize($user['introduction'] ?: ''); ?></textarea>

            <label for="background">Background:</label>
            <textarea name="background" id="background" placeholder="Describe your background"><?php echo sanitize($user['background'] ?: ''); ?></textarea>

            <label for="interests">Interests:</label>
            <textarea name="interests" id="interests" placeholder="List your interests"><?php echo sanitize($user['interests'] ?: ''); ?></textarea>

            <label for="academic_goals">Academic Goals:</label>
            <textarea name="academic_goals" id="academic_goals" placeholder="Describe your academic goals"><?php echo sanitize($user['academic_goals'] ?: ''); ?></textarea>

            <label for="technical_skills">Technical Skills:</label>
            <textarea name="technical_skills" id="technical_skills" placeholder="List your technical skills"><?php echo sanitize($user['technical_skills'] ?: ''); ?></textarea>

            <label for="soft_skills">Soft Skills:</label>
            <textarea name="soft_skills" id="soft_skills" placeholder="List your soft skills"><?php echo sanitize($user['soft_skills'] ?: ''); ?></textarea>

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