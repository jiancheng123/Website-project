<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="empty-state">Please login to view meetings.</div>';
    exit();
}

$user_id = $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');

// Function to format time
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

// Get meetings for the specific date
$sql = "SELECT m.*, 
               GROUP_CONCAT(DISTINCT p.email SEPARATOR ', ') as participants_emails,
               COUNT(DISTINCT p.id) as participant_count
        FROM meetings m
        LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
        LEFT JOIN users p ON mp.participant_id = p.id
        WHERE m.host_id = ? 
        AND m.meeting_date = ?
        GROUP BY m.id
        ORDER BY m.start_time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $date);
$stmt->execute();
$result = $stmt->get_result();
$meetings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($meetings)) {
    echo '<div class="empty-state">';
    echo '<i class="fas fa-calendar-times"></i>';
    echo '<h4>No Meetings Scheduled</h4>';
    echo '<p>No meetings scheduled for this day.</p>';
    echo '<button class="btn-quick" onclick="parent.showQuickMeetingModal()">';
    echo '<i class="fas fa-plus"></i> Schedule Meeting';
    echo '</button>';
    echo '</div>';
    exit();
}

foreach ($meetings as $meeting): 
    $status = '';
    $now = time();
    $meeting_start = strtotime($meeting['meeting_date'] . ' ' . $meeting['start_time']);
    $meeting_end = strtotime($meeting['meeting_date'] . ' ' . $meeting['end_time']);
    
    if ($now < $meeting_start) {
        $status = 'upcoming';
        $status_class = 'status-upcoming';
    } elseif ($now >= $meeting_start && $now <= $meeting_end) {
        $status = 'ongoing';
        $status_class = 'status-ongoing';
    } else {
        $status = 'completed';
        $status_class = 'status-completed';
    }
?>
<div class="day-meeting-item">
    <div class="upcoming-item-header">
        <div>
            <div class="upcoming-title">
                <i class="fas fa-video"></i>
                <?php echo htmlspecialchars($meeting['title']); ?>
            </div>
            <div class="upcoming-date">
                <i class="fas fa-clock"></i>
                <?php echo formatTime($meeting['start_time']); ?> - <?php echo formatTime($meeting['end_time']); ?>
            </div>
        </div>
        <div class="upcoming-time <?php echo $status_class; ?>">
            <?php echo ucfirst($status); ?>
        </div>
    </div>
    <div class="upcoming-details">
        <?php echo htmlspecialchars($meeting['description'] ?: 'No description provided'); ?>
    </div>
    <div class="upcoming-participants">
        <i class="fas fa-users"></i>
        <?php echo $meeting['participant_count']; ?> participants
    </div>
    <div class="upcoming-actions">
        <?php if ($status == 'ongoing'): ?>
            <button class="btn-join" onclick="parent.joinMeeting(<?php echo $meeting['id']; ?>)">
                <i class="fas fa-video"></i> Join Now
            </button>
        <?php endif; ?>
        <button class="btn-edit" onclick="parent.editMeeting(<?php echo $meeting['id']; ?>)">
            <i class="fas fa-edit"></i> Edit
        </button>
        <button class="btn-delete" onclick="parent.confirmDelete(<?php echo $meeting['id']; ?>)">
            <i class="fas fa-trash"></i> Delete
        </button>
    </div>
</div>
<?php endforeach; ?>