<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Return success response
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Session updated']);
?>