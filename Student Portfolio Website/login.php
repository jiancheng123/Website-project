<?php
session_start();

$error = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';

session_unset();

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login Page</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    .form-box { display: none; }
    .form-box.active { display: block; }
    .error-message { color: red; font-size: 14px; }
    .register-container, .form-box {
        max-height: 500px;
        overflow-y: auto;
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    .register-container label, .form-box label {
        display: block;
        margin-top: 10px;
    }
    .register-container input,
    .register-container select,
    .register-container textarea,
    .form-box input,
    .form-box select {
        width: 100%;
        padding: 8px;
        margin-top: 5px;
    }
    .form-box button {
        margin-top: 10px;
        padding: 8px;
        width: 100%;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Login Form -->
    <form action="register.php" method="post" class="form-box <?= isActiveForm('login', $activeForm); ?>" id="loginBox">
      <h2>Login</h2>
      <?= showError($error['login']); ?>
      <input type="text" id="username" name="username" placeholder="Enter your username" required />
      <input type="password" id="password" name="password" placeholder="Enter your password" required />
      <br>
      <button type="submit" name="login">Login</button>
      <br><br>
      <p>Don't have an account? <a href="#" onclick="showForm('registerBox')">Register</a></p>
      <p>Forgot username or password? <a href="reset_password.php">Reset</a></p>
    </form>

    <!-- Register Form -->
    <form action="register.php" method="post" class="form-box <?= isActiveForm('register', $activeForm); ?>" id="registerBox" enctype="multipart/form-data">
      <h2>Register</h2>
      <div class="register-container">
        <?= showError($error['register']); ?>
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" placeholder="Enter your username" required pattern="[a-zA-Z0-9_]{3,50}" title="3-50 characters, letters, numbers, or underscores" />
        
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required minlength="8" />
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required />
        
        <label for="status">Status:</label>
        <select name="status" id="status" required>
            <option value="">Select Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Graduated">Graduated</option>
        </select>
        
        <label for="student_id">Student ID:</label>
        <input type="text" name="student_id" id="student_id" required pattern="SCSJ[0-9]{7}" title="Format: SCSJ followed by 7 digits (e.g., SCSJ2200858)" />
        
        <label for="admission_year">Admission Year:</label>
        <input type="number" name="admission_year" id="admission_year" required min="1970" max="<?php echo date('Y'); ?>" />
        
        <label for="intake">Intake:</label>
        <input type="text" name="intake" id="intake" required pattern="[0-9]{6}" title="Format: YYYYMM (e.g., 202409)" />
        
        <label for="level_of_study">Level of Study:</label>
        <select name="level_of_study" id="level_of_study" required>
            <option value="">Select Level</option>
            <option value="Certificate">Certificate</option>
            <option value="Diploma">Diploma</option>
            <option value="Degree">Degree</option>
        </select>
        
        <label for="department">Department:</label>
        <select name="department" id="department" required>
            <option value="">Select Department</option>
            <option value="Information Technology">Information Technology</option>
            <option value="Computer Science">Computer Science</option>
            <option value="Engineering">Engineering</option>
            <option value="Business">Business</option>
            <option value="Arts">Arts</option>
            <option value="Science">Science</option>
        </select>
        
        <label for="college">College:</label>
        <select name="college" id="college" required>
            <option value="">Select College</option>
            <option value="SEGi College Subang Jaya">SEGi College Subang Jaya</option>
            <option value="SEGi College Kuala Lumpur">SEGi College Kuala Lumpur</option>
            <option value="SEGi College Penang">SEGi College Penang</option>
            <option value="SEGi College Sarawak">SEGi College Sarawak</option>
            <option value="SEGi College Kota Damansara">SEGi College Kota Damansara</option>
            <option value="SEGi University Kota Damansara">SEGi University Kota Damansara</option>
        </select>
        
        <label for="profile_photo">Profile Photo:</label>
        <input type="file" id="profile_photo" name="profile_photo" accept="image/*" />
        
        <label for="introduction">Short Personal Introduction:</label>
        <textarea id="introduction" name="introduction" placeholder="Tell us about yourself (max 200 characters)" maxlength="200" rows="4" style="width: 100%;"></textarea>
        
        <label for="background">Background:</label>
        <textarea id="background" name="background" placeholder="Describe your background (max 300 characters)" maxlength="300" rows="4" style="width: 100%;"></textarea>
        
        <label for="interests">Interests:</label>
        <textarea id="interests" name="interests" placeholder="List your interests (max 300 characters)" maxlength="300" rows="4" style="width: 100%;"></textarea>
        
        <label for="academic_goals">Academic Goals:</label>
        <textarea id="academic_goals" name="academic_goals" placeholder="Describe your academic goals (max 300 characters)" maxlength="300" rows="4" style="width: 100%;"></textarea>
        
        <label for="technical_skills">Technical Skills:</label>
        <input type="text" id="technical_skills" name="technical_skills" placeholder="List your technical skills (e.g., Python, JavaScript)" />
        
        <label for="soft_skills">Soft Skills:</label>
        <input type="text" id="soft_skills" name="soft_skills" placeholder="List your soft skills (e.g., Communication, Teamwork)" />
        
        <button type="submit" name="register">Register</button>
        <br><br>
        <p>Already have an account? <a href="#" onclick="showForm('loginBox')">Login</a></p>
      </div>
    </form>
    
    <script>
    // Display the register box
      function showForm(formId) {
        document.querySelectorAll('.form-box').forEach(form => {
          form.classList.remove('active');
        });
        document.getElementById(formId).classList.add('active');
      }

      // Show alert for login error
      <?php if (!empty($error['login'])): ?>
        alert('Username or password incorrect');
      <?php endif; ?>

        // Show alert for register error
      <?php if (!empty($error['register'])): ?>
          alert("<?= strip_tags($error['register']) ?>");
      <?php endif; ?>
    </script>
  </div>
</body>
</html>