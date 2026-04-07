<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Get updated statistics
    $stats_sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(DISTINCT user_id) as unique_users
        FROM problem_reports";

    $stats_result = $conn->query($stats_sql);
    
    if (!$stats_result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $stats = $stats_result->fetch_assoc();
    
    // Ensure all values are integers (prevent nulls)
    $response = [
        'success' => true,
        'total' => (int)($stats['total'] ?? 0),
        'pending' => (int)($stats['pending'] ?? 0),
        'reviewed' => (int)($stats['reviewed'] ?? 0),
        'resolved' => (int)($stats['resolved'] ?? 0),
        'rejected' => (int)($stats['rejected'] ?? 0),
        'unique_users' => (int)($stats['unique_users'] ?? 0),
        'timestamp' => time() // Add timestamp for cache busting
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Server error occurred',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>