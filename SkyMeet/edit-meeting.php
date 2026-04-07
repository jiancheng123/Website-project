<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$meeting_id = intval($_GET['id'] ?? 0);

// Get meeting details
$meeting_sql = "SELECT * FROM meetings WHERE id = ? AND host_id = ?";
$stmt = $conn->prepare($meeting_sql);
$stmt->bind_param("ii", $meeting_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();

if (!$meeting) {
    header("Location: schedule.php?error=Meeting+not+found");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_meeting'])) {
    $title = $_POST['title'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $description = $_POST['description'] ?? '';
    
    $update_sql = "UPDATE meetings SET 
                    title = ?, 
                    meeting_date = ?, 
                    start_time = ?, 
                    end_time = ?, 
                    description = ?
                   WHERE id = ? AND host_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssssii", $title, $date, $start_time, $end_time, $description, $meeting_id, $user_id);
    
    if ($update_stmt->execute()) {
        header("Location: schedule.php?success=Meeting+updated+successfully");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Meeting - JustMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .edit-meeting-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 600px;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .header h1 i {
            color: #667eea;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 25px;
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
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-update, .btn-delete, .btn-cancel {
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-update {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 100%;
        }

        .btn-update:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-delete {
            background: #ef4444;
            color: white;
            width: 48%;
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-3px);
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #666;
            width: 48%;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="edit-meeting-container">
        <div class="header">
            <h1><i class="fas fa-edit"></i> Edit Meeting</h1>
            <p>Update your meeting details</p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="title">Meeting Title</label>
                <input type="text" id="title" name="title" class="form-control" 
                       value="<?php echo htmlspecialchars($meeting['title']); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" 
                          rows="4"><?php echo htmlspecialchars($meeting['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" class="form-control" 
                       value="<?php echo $meeting['meeting_date']; ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" id="start_time" name="start_time" 
                           class="form-control" value="<?php echo $meeting['start_time']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time</label>
                    <input type="time" id="end_time" name="end_time" 
                           class="form-control" value="<?php echo $meeting['end_time']; ?>" required>
                </div>
            </div>

            <button type="submit" name="update_meeting" class="btn-update">
                <i class="fas fa-save"></i> Update Meeting
            </button>
        </form>

        <div class="button-group">
            <a href="schedule.php?delete=<?php echo $meeting_id; ?>" 
               class="btn-delete"
               onclick="return confirm('Are you sure you want to delete this meeting? This action cannot be undone.');">
                <i class="fas fa-trash"></i> Delete
            </a>
            <a href="schedule.php" class="btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </div>

    <script>
        function validateForm() {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            if (startTime >= endTime) {
                alert('End time must be after start time');
                return false;
            }
            return true;
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>