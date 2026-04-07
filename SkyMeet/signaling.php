<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Get current user info
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Guest';

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$room_id = $_GET['room'] ?? $_POST['room'] ?? '';

if (empty($room_id)) {
    echo json_encode(['success' => false, 'error' => 'Room ID required']);
    exit();
}

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_ice_servers':
        // Return STUN/TURN servers for WebRTC
        $iceServers = [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
            ['urls' => 'stun:stun2.l.google.com:19302'],
            ['urls' => 'stun:stun3.l.google.com:19302'],
            ['urls' => 'stun:stun4.l.google.com:19302'],
            ['urls' => 'stun:stun.ekiga.net:3478'],
            ['urls' => 'stun:stun.stunprotocol.org:3478'],
            ['urls' => 'stun:stun.cloudflare.com:3478']
        ];
        
        echo json_encode(['success' => true, 'iceServers' => $iceServers]);
        break;
        
    case 'register_peer':
        // Register user's PeerJS ID
        $peer_id = $_POST['peer_id'] ?? '';
        
        if (!empty($room_id) && !empty($peer_id)) {
            try {
                // Clean up old connections
                $clean_sql = "DELETE FROM peer_connections WHERE room_id = ? AND user_id = ?";
                $clean_stmt = $conn->prepare($clean_sql);
                $clean_stmt->bind_param("si", $room_id, $user_id);
                $clean_stmt->execute();
                $clean_stmt->close();
                
                // Insert new peer connection
                $sql = "INSERT INTO peer_connections (room_id, user_id, peer_id) 
                        VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sis", $room_id, $user_id, $peer_id);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode(['success' => true, 'peer_id' => $peer_id]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing room_id or peer_id']);
        }
        break;
        
    case 'get_peers':
        // Get all peers in room except current user
        try {
            $sql = "SELECT user_id, peer_id FROM peer_connections 
                    WHERE room_id = ? AND user_id != ? 
                    AND updated_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $room_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $peers = [];
            while ($row = $result->fetch_assoc()) {
                $peers[] = [
                    'user_id' => (int)$row['user_id'],
                    'peer_id' => $row['peer_id']
                ];
            }
            $stmt->close();
            
            echo json_encode(['success' => true, 'peers' => $peers]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'kick':
        // Kick a user from the meeting
        $target_user_id = (int)($_POST['target'] ?? 0);
        
        if (!$target_user_id) {
            echo json_encode(['success' => false, 'error' => 'Missing target user ID']);
            break;
        }
        
        try {
            // Verify that the requester is the host
            $check_host_sql = "SELECT host_id FROM meetings WHERE room_id = ?";
            $check_host_stmt = $conn->prepare($check_host_sql);
            $check_host_stmt->bind_param("s", $room_id);
            $check_host_stmt->execute();
            $check_host_result = $check_host_stmt->get_result();
            $host_data = $check_host_result->fetch_assoc();
            $check_host_stmt->close();
            
            $is_host = ($host_data && $host_data['host_id'] == $user_id);
            
            if (!$is_host) {
                echo json_encode(['success' => false, 'error' => 'Only host can kick users']);
                break;
            }
            
            // Don't allow kicking self or host
            if ($target_user_id == $user_id || $target_user_id == $host_data['host_id']) {
                echo json_encode(['success' => false, 'error' => 'Cannot kick yourself or the host']);
                break;
            }
            
            // Get meeting ID from room_id
            $get_meeting_sql = "SELECT id FROM meetings WHERE room_id = ? LIMIT 1";
            $get_meeting_stmt = $conn->prepare($get_meeting_sql);
            $get_meeting_stmt->bind_param("s", $room_id);
            $get_meeting_stmt->execute();
            $get_meeting_result = $get_meeting_stmt->get_result();
            $meeting_data = $get_meeting_result->fetch_assoc();
            $get_meeting_stmt->close();
            
            if ($meeting_data) {
                $meeting_id = $meeting_data['id'];
                
                // First, delete any existing records for this user in this meeting
                $delete_existing_sql = "DELETE FROM meeting_participants 
                                       WHERE meeting_id = ? AND participant_id = ?";
                $delete_existing_stmt = $conn->prepare($delete_existing_sql);
                $delete_existing_stmt->bind_param("ii", $meeting_id, $target_user_id);
                $delete_existing_stmt->execute();
                $delete_existing_stmt->close();
                
                // Insert new record with kicked status
                $insert_kicked_sql = "INSERT INTO meeting_participants 
                                     (meeting_id, participant_id, status, left_at) 
                                     VALUES (?, ?, 'kicked', NOW())";
                $insert_kicked_stmt = $conn->prepare($insert_kicked_sql);
                $insert_kicked_stmt->bind_param("ii", $meeting_id, $target_user_id);
                $insert_kicked_stmt->execute();
                $insert_kicked_stmt->close();
                
                // Get usernames for chat message
                $get_username_sql = "SELECT username FROM users WHERE id = ?";
                $get_username_stmt = $conn->prepare($get_username_sql);
                $get_username_stmt->bind_param("i", $target_user_id);
                $get_username_stmt->execute();
                $get_username_result = $get_username_stmt->get_result();
                $target_username = 'User';
                if ($row = $get_username_result->fetch_assoc()) {
                    $target_username = $row['username'];
                }
                $get_username_stmt->close();
                
                $get_host_username_sql = "SELECT username FROM users WHERE id = ?";
                $get_host_username_stmt = $conn->prepare($get_host_username_sql);
                $get_host_username_stmt->bind_param("i", $user_id);
                $get_host_username_stmt->execute();
                $get_host_username_result = $get_host_username_stmt->get_result();
                $host_username = 'Host';
                if ($row = $get_host_username_result->fetch_assoc()) {
                    $host_username = $row['username'];
                }
                $get_host_username_stmt->close();
                
                // Delete any existing kick signals for this user
                $delete_signals_sql = "DELETE FROM signaling 
                                      WHERE room_id = ? AND to_user_id = ? AND type = 'kick'";
                $delete_signals_stmt = $conn->prepare($delete_signals_sql);
                $delete_signals_stmt->bind_param("si", $room_id, $target_user_id);
                $delete_signals_stmt->execute();
                $delete_signals_stmt->close();
                
                // Send ONE kick signal to target user
                $signal_sql = "INSERT INTO signaling (room_id, from_user_id, to_user_id, type, data) 
                               VALUES (?, ?, ?, 'kick', 'kicked')";
                $signal_stmt = $conn->prepare($signal_sql);
                $signal_stmt->bind_param("sii", $room_id, $user_id, $target_user_id);
                $signal_stmt->execute();
                $signal_stmt->close();
                
                // Add kicked message to chat - check if message already exists in last 60 seconds
                $check_msg_sql = "SELECT id FROM chat_messages 
                                 WHERE room_id = ? AND message_type = 'kick' 
                                 AND user_id = ? 
                                 AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)";
                $check_msg_stmt = $conn->prepare($check_msg_sql);
                $check_msg_stmt->bind_param("si", $room_id, $target_user_id);
                $check_msg_stmt->execute();
                $check_msg_result = $check_msg_stmt->get_result();
                
                if ($check_msg_result->num_rows == 0) {
                    $kicked_message = "🚫 " . $target_username . " was removed from the meeting by the host";
                    
                    $insert_chat_sql = "INSERT INTO chat_messages (room_id, user_id, username, message, is_system_message, message_type) 
                                       VALUES (?, ?, ?, ?, 1, 'kick')";
                    $insert_chat_stmt = $conn->prepare($insert_chat_sql);
                    $insert_chat_stmt->bind_param("siss", $room_id, $user_id, $host_username, $kicked_message);
                    $insert_chat_stmt->execute();
                    $insert_chat_stmt->close();
                }
                $check_msg_stmt->close();
                
                // Remove from peer_connections
                $delete_peer_sql = "DELETE FROM peer_connections WHERE room_id = ? AND user_id = ?";
                $delete_peer_stmt = $conn->prepare($delete_peer_sql);
                $delete_peer_stmt->bind_param("si", $room_id, $target_user_id);
                $delete_peer_stmt->execute();
                $delete_peer_stmt->close();
                
                // Remove from muted_users if table exists
                $check_muted_table = $conn->query("SHOW TABLES LIKE 'muted_users'");
                if ($check_muted_table && $check_muted_table->num_rows > 0) {
                    $delete_muted_sql = "DELETE FROM muted_users WHERE room_id = ? AND user_id = ?";
                    $delete_muted_stmt = $conn->prepare($delete_muted_sql);
                    $delete_muted_stmt->bind_param("si", $room_id, $target_user_id);
                    $delete_muted_stmt->execute();
                    $delete_muted_stmt->close();
                }
                
                // Log host action
                $insert_action_sql = "INSERT INTO host_actions (room_id, action, target_user_id, host_user_id) 
                                     VALUES (?, 'kick', ?, ?)";
                $insert_action_stmt = $conn->prepare($insert_action_sql);
                $insert_action_stmt->bind_param("sii", $room_id, $target_user_id, $user_id);
                $insert_action_stmt->execute();
                $insert_action_stmt->close();
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log("Kick error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'check_kicked':
        // Check if current user has been kicked
        try {
            $get_meeting_sql = "SELECT id FROM meetings WHERE room_id = ? LIMIT 1";
            $get_meeting_stmt = $conn->prepare($get_meeting_sql);
            $get_meeting_stmt->bind_param("s", $room_id);
            $get_meeting_stmt->execute();
            $get_meeting_result = $get_meeting_stmt->get_result();
            $meeting_data = $get_meeting_result->fetch_assoc();
            $get_meeting_stmt->close();
            
            $is_kicked = false;
            
            if ($meeting_data) {
                $meeting_id = $meeting_data['id'];
                
                $check_sql = "SELECT status FROM meeting_participants 
                             WHERE meeting_id = ? AND participant_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $meeting_id, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($row = $check_result->fetch_assoc()) {
                    $is_kicked = ($row['status'] === 'kicked');
                }
                $check_stmt->close();
            }
            
            echo json_encode(['success' => true, 'is_kicked' => $is_kicked]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'get_signals':
        // Get pending signals for current user
        $last_check = (int)($_GET['last_check'] ?? 0);
        $last_check_date = $last_check > 0 ? date('Y-m-d H:i:s', $last_check) : date('Y-m-d H:i:s', 0);
        
        try {
            // Check if user has been kicked directly from meeting_participants
            $get_meeting_sql = "SELECT id FROM meetings WHERE room_id = ? LIMIT 1";
            $get_meeting_stmt = $conn->prepare($get_meeting_sql);
            $get_meeting_stmt->bind_param("s", $room_id);
            $get_meeting_stmt->execute();
            $get_meeting_result = $get_meeting_stmt->get_result();
            $meeting_data = $get_meeting_result->fetch_assoc();
            $get_meeting_stmt->close();
            
            if ($meeting_data) {
                $meeting_id = $meeting_data['id'];
                
                $check_kicked_sql = "SELECT status FROM meeting_participants 
                                    WHERE meeting_id = ? AND participant_id = ? AND status = 'kicked'";
                $check_kicked_stmt = $conn->prepare($check_kicked_sql);
                $check_kicked_stmt->bind_param("ii", $meeting_id, $user_id);
                $check_kicked_stmt->execute();
                $check_kicked_result = $check_kicked_stmt->get_result();
                
                if ($check_kicked_result->num_rows > 0) {
                    // User has been kicked, add a kick signal if not already present in last 5 minutes
                    $check_signal_sql = "SELECT id FROM signaling 
                                        WHERE room_id = ? AND to_user_id = ? AND type = 'kick' 
                                        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                    $check_signal_stmt = $conn->prepare($check_signal_sql);
                    $check_signal_stmt->bind_param("si", $room_id, $user_id);
                    $check_signal_stmt->execute();
                    $check_signal_result = $check_signal_stmt->get_result();
                    
                    if ($check_signal_result->num_rows == 0) {
                        // Add a kick signal
                        $insert_kick_sql = "INSERT INTO signaling (room_id, from_user_id, to_user_id, type, data) 
                                          VALUES (?, 0, ?, 'kick', 'kicked')";
                        $insert_kick_stmt = $conn->prepare($insert_kick_sql);
                        $insert_kick_stmt->bind_param("si", $room_id, $user_id);
                        $insert_kick_stmt->execute();
                        $insert_kick_stmt->close();
                    }
                    $check_signal_stmt->close();
                }
                $check_kicked_stmt->close();
            }
            
            // Get regular signals - only signals from the last 5 minutes
            $sql = "SELECT * FROM signaling 
                    WHERE room_id = ? AND to_user_id = ? AND created_at > ?
                    ORDER BY created_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sis", $room_id, $user_id, $last_check_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $signals = [];
            while ($row = $result->fetch_assoc()) {
                $signals[] = [
                    'id' => (int)$row['id'],
                    'type' => $row['type'],
                    'data' => $row['data'],
                    'from_user_id' => (int)$row['from_user_id'],
                    'created_at' => strtotime($row['created_at'])
                ];
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true, 
                'signals' => $signals, 
                'last_check' => time()
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'delete_signal':
        // Delete a specific signal after processing
        $signal_id = (int)($_POST['signal_id'] ?? 0);
        
        if ($signal_id) {
            try {
                $delete_sql = "DELETE FROM signaling WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $signal_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing signal ID']);
        }
        break;
        
    case 'clean_old_signals':
        // Clean up signals older than 5 minutes
        try {
            $clean_sql = "DELETE FROM signaling WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
            $conn->query($clean_sql);
            
            // Clean up old peer connections
            $clean_peer_sql = "DELETE FROM peer_connections WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)";
            $conn->query($clean_peer_sql);
            
            echo json_encode(['success' => true, 'message' => 'Old signals and peers cleaned']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'keep_alive':
        // Update peer connection timestamp
        try {
            $sql = "UPDATE peer_connections SET updated_at = NOW() 
                    WHERE room_id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $room_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    case 'send_signal':
        // Send a signaling message to a specific user
        $target_user_id = (int)($_POST['target'] ?? 0);
        $signal_type = $_POST['type'] ?? '';
        $signal_data = $_POST['data'] ?? '';
        
        if (!$target_user_id || !$signal_type || !$signal_data) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            break;
        }
        
        try {
            $sql = "INSERT INTO signaling (room_id, from_user_id, to_user_id, type, data) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiss", $room_id, $user_id, $target_user_id, $signal_type, $signal_data);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action', 'action' => $action]);
}