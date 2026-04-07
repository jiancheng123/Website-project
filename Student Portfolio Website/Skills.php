<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "assignment";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $error = "Database connection failed: " . $e->getMessage();
}

// Initialize messages
$success = "";
$error = "";

// Fetch projects
$projects = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, description, image_path FROM projects WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to fetch projects: " . $e->getMessage();
}

// Handle project submission (Add or Update)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['projectButton'])) {
    $user_id = $_SESSION['user_id'];
    $title = htmlspecialchars(trim($_POST['projectTitle']));
    $description = htmlspecialchars(trim($_POST['projectDescription']));
    $edit_id = $_POST['editId'] ?? -1;

    if (empty($title) || empty($description)) {
        $error = "Please fill in all required project fields.";
    } else {
        $image_path = null;
        if (isset($_FILES['projectImage']) && $_FILES['projectImage']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "Uploads/projects/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $ext = strtolower(pathinfo($_FILES['projectImage']['name'], PATHINFO_EXTENSION));
            $file_type = $_FILES['projectImage']['type'];

            if (!in_array($file_type, $allowed_types)) {
                $error = "Invalid project image type. Allowed: jpg, png, gif.";
            } else {
                $new_name = uniqid("proj_", true) . "." . $ext;
                $target = $upload_dir . $new_name;

                if (move_uploaded_file($_FILES['projectImage']['tmp_name'], $target)) {
                    $image_path = $target;
                } else {
                    $error = "Failed to upload project image.";
                }
            }
        }

        if (!$error) {
            try {
                if ($edit_id >= 0) {
                    // Update existing project
                    if ($image_path) {
                        // Delete old image if exists
                        $stmt = $pdo->prepare("SELECT image_path FROM projects WHERE id = ? AND user_id = ?");
                        $stmt->execute([$edit_id, $user_id]);
                        $old_project = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($old_project && $old_project['image_path'] && file_exists($old_project['image_path'])) {
                            unlink($old_project['image_path']);
                        }
                        // Update with new image
                        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, image_path = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$title, $description, $image_path, $edit_id, $user_id]);
                    } else {
                        // Update without changing image
                        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$title, $description, $edit_id, $user_id]);
                    }
                    $success = "Project updated successfully.";
                } else {
                    // Insert new project
                    $stmt = $pdo->prepare("INSERT INTO projects (user_id, title, description, image_path) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $title, $description, $image_path]);
                    $success = "Project added successfully.";
                }
                // Refresh projects list
                $stmt = $pdo->prepare("SELECT id, title, description, image_path FROM projects WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = "Project database error: " . $e->getMessage();
            }
        }
    }
}

// Handle project deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['deleteProjectId'])) {
    $delete_id = $_POST['deleteProjectId'];
    try {
        // Fetch image path to delete the file
        $stmt = $pdo->prepare("SELECT image_path FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $_SESSION['user_id']]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($project && $project['image_path'] && file_exists($project['image_path'])) {
            unlink($project['image_path']);
        }

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $_SESSION['user_id']]);
        $success = "Project deleted successfully.";
        // Refresh projects list
        $stmt = $pdo->prepare("SELECT id, title, description, image_path FROM projects WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Failed to delete project: " . $e->getMessage();
    }
}

// Handle certificate submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['certButton'])) {
    $user_id = $_SESSION['user_id'];
    $title = htmlspecialchars(trim($_POST['certTitle']));
    $issuer = htmlspecialchars(trim($_POST['certIssuer']));
    $cert_date = $_POST['certDate'];
    $edit_id = $_POST['editId'] ?? -1;

    if (empty($title) || empty($issuer) || empty($cert_date)) {
        $error = "Please fill in all required certificate fields.";
    } else {
        $file_path = null;
        if (isset($_FILES['certFile']) && $_FILES['certFile']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "Uploads/certificates/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $ext = strtolower(pathinfo($_FILES['certFile']['name'], PATHINFO_EXTENSION));
            $file_type = $_FILES['certFile']['type'];

            if (!in_array($file_type, $allowed_types)) {
                $error = "Invalid certificate file type. Allowed: jpg, png, gif, pdf, doc, docx.";
            } else {
                $new_name = uniqid("cert_", true) . "." . $ext;
                $target = $upload_dir . $new_name;

                if (move_uploaded_file($_FILES['certFile']['tmp_name'], $target)) {
                    $file_path = $target;
                } else {
                    $error = "Failed to upload certificate file.";
                }
            }
        }

        if (!$error) {
            try {
                if ($edit_id >= 0) {
                    if ($file_path) {
                        $stmt = $pdo->prepare("SELECT file_path FROM certificates WHERE id = ? AND user_id = ?");
                        $stmt->execute([$edit_id, $user_id]);
                        $old_cert = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($old_cert && $old_cert['file_path'] && file_exists($old_cert['file_path'])) {
                            unlink($old_cert['file_path']);
                        }
                        $stmt = $pdo->prepare("UPDATE certificates SET title = ?, issuer = ?, cert_date = ?, file_path = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$title, $issuer, $cert_date, $file_path, $edit_id, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE certificates SET title = ?, issuer = ?, cert_date = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$title, $issuer, $cert_date, $edit_id, $user_id]);
                    }
                    $success = "Certificate updated successfully.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO certificates (user_id, title, issuer, cert_date, file_path) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $title, $issuer, $cert_date, $file_path]);
                    $success = "Certificate added successfully.";
                }
                // Refresh certificates list
                $stmt = $pdo->prepare("SELECT id, title, issuer, cert_date, file_path FROM certificates WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = "Certificate database error: " . $e->getMessage();
            }
        }
    }
}

// Handle certificate deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['deleteId'])) {
    $delete_id = $_POST['deleteId'];
    try {
        $stmt = $pdo->prepare("SELECT file_path FROM certificates WHERE id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $_SESSION['user_id']]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cert && $cert['file_path'] && file_exists($cert['file_path'])) {
            unlink($cert['file_path']);
        }

        $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $_SESSION['user_id']]);
        $success = "Certificate deleted successfully.";
        // Refresh certificates list
        $stmt = $pdo->prepare("SELECT id, title, issuer, cert_date, file_path FROM certificates WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Failed to delete certificate: " . $e->getMessage();
    }
}

// Fetch certificates
$certificates = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, issuer, cert_date, file_path FROM certificates WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to fetch certificates: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Projects & Certificates</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f9f9f9;
    }

    .projects-section, .cert-section {
      max-width: 900px;
      margin: 40px auto;
      background-color: #ffffff;
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    h2 {
      text-align: center;
      font-size: 26px;
      color: #2c3e50;
      margin-bottom: 10px;
    }

    .projects-gallery {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
    }

    .project-card {
      background-color: #fdfdfd;
      border: 1px solid #ddd;
      border-radius: 10px;
      padding: 15px;
      text-align: center;
      transition: transform 0.3s ease;
    }

    .project-card:hover {
      transform: translateY(-5px);
    }

    .project-card img {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
      margin-bottom: 10px;
    }

    .project-card h3 {
      font-size: 18px;
      color: #2c3e50;
      margin: 10px 0;
    }

    .project-card p {
      font-size: 14px;
      color: #666;
      margin-bottom: 10px;
    }

    .project-form-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
    }

    .project-form-grid input[type="text"],
    .project-form-grid textarea,
    .project-form-grid input[type="file"] {
      flex: 1 1 calc(50% - 10px);
      padding: 10px 12px;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 16px;
    }

    .project-form-grid textarea {
      min-height: 100px;
      resize: vertical;
    }

    .cert-form-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: flex-end; /* Align form to the right */
    }

    .cert-form-grid input[type="text"],
    .cert-form-grid input[type="date"],
    .cert-form-grid input[type="file"] {
      flex: 1 1 calc(50% - 10px);
      padding: 10px 12px;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 16px;
    }

    .btn {
      background-color: #2575fc;
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-size: 16px;
      transition: background-color 0.3s ease;
    }

    .btn:hover {
      background-color: #1e63d7;
    }

    #certTable {
      width: 100%;
      margin-top: 20px;
      border-collapse: collapse;
      font-size: 15px;
      background-color: #fdfdfd;
      border-radius: 10px;
      overflow: hidden;
    }

    #certTable th, #certTable td {
      padding: 12px 15px;
      border: 1px solid #ddd;
      text-align: center;
    }

    #certTable th {
      background-color: #f0f0f0;
      color: #333;
      font-weight: 600;
    }

    .action-btns {
      display: flex;
      justify-content: center;
      gap: 5px;
      margin-top: 10px; /* Space above buttons in project cards */
    }

    .action-btns button {
      margin: 0;
      padding: 6px 10px;
      font-size: 14px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }

    .action-btns button.edit-btn {
      background-color: #f0ad4e;
      color: white;
    }

    .action-btns button.delete-btn {
      background-color: #d9534f;
      color: white;
    }

    .message {
      padding: 10px;
      margin-bottom: 20px;
      border-radius: 5px;
    }

    .success {
      background-color: #dff0d8;
      color: #3c763d;
    }

    .error {
      background-color: #f2dede;
      color: #a94442;
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav>
    <div class="Navigation-bar">
      <ul>
        <li><a href="home.php">Home</a></li>
        <li><a href="Aboutme.php">About Me</a></li>
        <li><a href="Skills.php">Projects & Certificates</a></li>
        <li><a href="Contact.php">Contact</a></li>
        <li>
          <form action="logout.php" method="post" class="logout-form">
            <button type="submit" class="logout-button">Logout</button>
          </form>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Projects Section -->
  <section class="projects-section">
    <h2>Projects</h2>
    <?php if ($success): ?>
      <div class="message success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <!-- Project Form -->
    <form action="Skills.php" method="post" enctype="multipart/form-data" class="project-form-grid">
      <input type="text" id="projectTitle" name="projectTitle" placeholder="Project Title" required />
      <textarea id="projectDescription" name="projectDescription" placeholder="Project Description" required></textarea>
      <input type="file" id="projectImage" name="projectImage" accept="image/jpeg,image/png,image/gif" />
      <input type="hidden" id="projectEditId" name="editId" value="-1" />
      <button type="submit" class="btn" name="projectButton" id="projectButton">Add Project</button>
    </form>

    <!-- Projects Gallery -->
    <?php if (empty($projects)): ?>
      <p>No projects available</p>
    <?php else: ?>
      <div class="projects-gallery">
        <?php foreach ($projects as $project): ?>
          <div class="project-card">
            <?php if ($project['image_path']): ?>
              <img src="<?php echo htmlspecialchars($project['image_path']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>" />
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($project['title']); ?></h3>
            <p><?php echo htmlspecialchars($project['description']); ?></p>
            <div class="action-btns">
              <button class="edit-btn" onclick="editProject(<?php echo $project['id']; ?>, '<?php echo addslashes($project['title']); ?>', '<?php echo addslashes($project['description']); ?>')">Edit</button>
              <form action="Skills.php" method="post" style="display:inline;">
                <input type="hidden" name="deleteProjectId" value="<?php echo $project['id']; ?>">
                <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this project?')">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Certificate Section -->
  <section class="cert-section">
    <h2>Certificates</h2>
    <form action="Skills.php" method="post" enctype="multipart/form-data" class="cert-form-grid">
      <input type="text" id="certTitle" name="certTitle" placeholder="Certificate Title" value="<?php echo isset($_POST['certTitle']) ? htmlspecialchars($_POST['certTitle']) : ''; ?>" />
      <input type="text" id="certIssuer" name="certIssuer" placeholder="Issuer" value="<?php echo isset($_POST['certIssuer']) ? htmlspecialchars($_POST['certIssuer']) : ''; ?>" />
      <input type="date" id="certDate" name="certDate" value="<?php echo isset($_POST['certDate']) ? htmlspecialchars($_POST['certDate']) : ''; ?>" />
      <input type="file" id="certFile" name="certFile" accept="image/*,.pdf,.doc,.docx" />
      <input type="hidden" id="editId" name="editId" value="-1" />
      <button type="submit" class="btn" name="certButton" id="certButton">Add Certificate</button>
    </form>

    <table id="certTable">
      <thead>
        <tr>
          <th>Title</th>
          <th>Issuer</th>
          <th>Date</th>
          <th>Attachment</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($certificates as $cert): ?>
          <tr>
            <td><?php echo htmlspecialchars($cert['title']); ?></td>
            <td><?php echo htmlspecialchars($cert['issuer']); ?></td>
            <td><?php echo htmlspecialchars($cert['cert_date']); ?></td>
            <td>
              <?php if ($cert['file_path']): ?>
                <a href="<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank">
                  <?php
                  $ext = strtolower(pathinfo($cert['file_path'], PATHINFO_EXTENSION));
                  if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    echo '<img src="' . htmlspecialchars($cert['file_path']) . '" width="80" />';
                  } else {
                    echo 'Download';
                  }
                  ?>
                </a>
              <?php else: ?>
                No File
              <?php endif; ?>
            </td>
            <td class="action-btns">
              <button class="edit-btn" onclick="editCertificate(<?php echo $cert['id']; ?>, '<?php echo addslashes($cert['title']); ?>', '<?php echo addslashes($cert['issuer']); ?>', '<?php echo $cert['cert_date']; ?>')">Edit</button>
              <form action="Skills.php" method="post" style="display:inline;">
                <input type="hidden" name="deleteId" value="<?php echo $cert['id']; ?>">
                <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this certificate?')">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <script>
    function editCertificate(id, title, issuer, date) {
      document.getElementById('certTitle').value = title;
      document.getElementById('certIssuer').value = issuer;
      document.getElementById('certDate').value = date;
      document.getElementById('editId').value = id;
      document.getElementById('certButton').textContent = 'Update Certificate';
    }

    function editProject(id, title, description) {
      document.getElementById('projectTitle').value = title;
      document.getElementById('projectDescription').value = description;
      document.getElementById('projectEditId').value = id;
      document.getElementById('projectButton').textContent = 'Update Project';
    }
  </script>
</body>
</html>