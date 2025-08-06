<?php
session_start();

// Simple heartbeat to keep session alive
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'alive', 'time' => time()]);
} else {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'expired']);
}
?>