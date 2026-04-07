<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$search_results = [];
$search_query = '';

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';
    
    if (!empty($search_query)) {
        // Check if room_id column exists
        $check_column = $conn->query("SHOW COLUMNS FROM meetings LIKE 'room_id'");
        
        if ($check_column->num_rows > 0) {
            // Search meetings by title, description, or room ID
            $sql = "SELECT m.*, u.username as host_name 
                    FROM meetings m 
                    JOIN users u ON m.host_id = u.id 
                    WHERE (m.title LIKE ? OR m.description LIKE ? OR m.room_id LIKE ?) 
                    AND m.status IN ('scheduled', 'ongoing') 
                    ORDER BY m.meeting_date, m.start_time";
            
            $stmt = $conn->prepare($sql);
            $search_param = "%$search_query%";
            $stmt->bind_param("sss", $search_param, $search_param, $search_param);
            $stmt->execute();
            $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

// Get upcoming meetings
$upcoming_meetings = [];
try {
    $upcoming_sql = "SELECT m.*, u.username as host_name 
                     FROM meetings m 
                     JOIN users u ON m.host_id = u.id 
                     WHERE m.status IN ('scheduled', 'ongoing') 
                     AND (m.meeting_date > CURDATE() OR (m.meeting_date = CURDATE() AND m.end_time > CURTIME()))
                     ORDER BY m.meeting_date, m.start_time 
                     LIMIT 10";
    $upcoming_result = $conn->query($upcoming_sql);
    if ($upcoming_result) {
        $upcoming_meetings = $upcoming_result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // Silently fail
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Meetings - JustMeet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            color: white;
        }

        .header h1 {
            font-size: 42px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .header p {
            font-size: 18px;
            opacity: 0.9;
        }

        .search-box {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }

        .search-form {
            display: flex;
            gap: 15px;
        }

        .search-input {
            flex: 1;
            padding: 16px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            padding: 16px 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: #667eea;
        }

        .meeting-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .meeting-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .meeting-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
            border-color: #667eea;
        }

        .meeting-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .meeting-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .meeting-host {
            font-size: 14px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meeting-body {
            padding: 20px;
        }

        .meeting-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            font-size: 14px;
        }

        .info-row i {
            color: #667eea;
            width: 20px;
        }

        .meeting-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #444;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .back-link:hover {
            background: #f8f9ff;
            transform: translateY(-2px);
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .no-results i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-ongoing {
            background: #e8f5e9;
            color: #2e7d32;
        }

        @media (max-width: 768px) {
            .meeting-grid {
                grid-template-columns: 1fr;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .header h1 {
                font-size: 32px;
            }
            
            .meeting-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="header">
            <h1><i class="fas fa-search"></i> Find & Join Meetings</h1>
            <p>Search for meetings by title, description, or room ID</p>
        </div>
        
        <div class="search-box">
            <form method="POST" class="search-form">
                <input type="text" name="search_query" 
                       value="<?php echo htmlspecialchars($search_query); ?>" 
                       placeholder="Search meetings by title, description or room ID..." 
                       class="search-input">
                <button type="submit" name="search" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>
        
        <?php if (!empty($search_results)): ?>
            <h2 class="section-title">
                <i class="fas fa-search"></i> Search Results
            </h2>
            <div class="meeting-grid">
                <?php foreach ($search_results as $meeting): ?>
                    <div class="meeting-card">
                        <div class="meeting-header">
                            <h3 class="meeting-title"><?php echo htmlspecialchars($meeting['title']); ?>
                                <span class="status-badge status-<?php echo $meeting['status']; ?>">
                                    <?php echo ucfirst($meeting['status']); ?>
                                </span>
                            </h3>
                            <div class="meeting-host">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($meeting['host_name']); ?>
                            </div>
                        </div>
                        <div class="meeting-body">
                            <div class="meeting-info">
                                <div class="info-row">
                                    <i class="far fa-calendar"></i>
                                    <span><?php echo date('F j, Y', strtotime($meeting['meeting_date'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="far fa-clock"></i>
                                    <span><?php echo date('g:i A', strtotime($meeting['start_time'])); ?> - <?php echo date('g:i A', strtotime($meeting['end_time'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-hashtag"></i>
                                    <span>Room: <?php echo htmlspecialchars($meeting['room_id']); ?></span>
                                </div>
                            </div>
                            <div class="meeting-actions">
                                <a href="meeting_room.php?room=<?php echo urlencode($meeting['room_id']); ?>" class="btn btn-primary">
                                    <i class="fas fa-video"></i> Join Meeting
                                </a>
                                <button type="button" class="btn btn-secondary" onclick="copyRoomId('<?php echo $meeting['room_id']; ?>')">
                                    <i class="far fa-copy"></i> Copy ID
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($search_query)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p>No meetings found for "<?php echo htmlspecialchars($search_query); ?>"</p>
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">
            <i class="fas fa-calendar-alt"></i> Upcoming & Ongoing Meetings
        </h2>
        <div class="meeting-grid">
            <?php if (!empty($upcoming_meetings)): ?>
                <?php foreach ($upcoming_meetings as $meeting): ?>
                    <div class="meeting-card">
                        <div class="meeting-header">
                            <h3 class="meeting-title"><?php echo htmlspecialchars($meeting['title']); ?>
                                <span class="status-badge status-<?php echo $meeting['status']; ?>">
                                    <?php echo ucfirst($meeting['status']); ?>
                                </span>
                            </h3>
                            <div class="meeting-host">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($meeting['host_name']); ?>
                            </div>
                        </div>
                        <div class="meeting-body">
                            <div class="meeting-info">
                                <div class="info-row">
                                    <i class="far fa-calendar"></i>
                                    <span><?php echo date('F j, Y', strtotime($meeting['meeting_date'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="far fa-clock"></i>
                                    <span><?php echo date('g:i A', strtotime($meeting['start_time'])); ?> - <?php echo date('g:i A', strtotime($meeting['end_time'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-hashtag"></i>
                                    <span>Room: <?php echo htmlspecialchars($meeting['room_id']); ?></span>
                                </div>
                            </div>
                            <div class="meeting-actions">
                                <a href="meeting_room.php?room=<?php echo urlencode($meeting['room_id']); ?>" class="btn btn-primary">
                                    <i class="fas fa-video"></i> Join Meeting
                                </a>
                                <button type="button" class="btn btn-secondary" onclick="copyRoomId('<?php echo $meeting['room_id']; ?>')">
                                    <i class="far fa-copy"></i> Copy ID
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-calendar-times"></i>
                    <p>No upcoming or ongoing meetings found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyRoomId(roomId) {
            navigator.clipboard.writeText(roomId).then(() => {
                alert('Room ID copied to clipboard: ' + roomId);
            }).catch(err => {
                console.error('Failed to copy: ', err);
                const textArea = document.createElement('textarea');
                textArea.value = roomId;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Room ID copied to clipboard: ' + roomId);
            });
        }

        // Auto-focus search input
        document.querySelector('.search-input').focus();
    </script>
</body>
</html>