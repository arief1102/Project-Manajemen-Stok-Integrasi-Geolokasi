<?php
require_once 'auth_middleware.php';
require_once 'config/db_connect.php';
require 'controller/profile_controller.php';

// Check if user is logged in
$user = authenticate();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$user_id = $user['user_id'];
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    list($success, $message, $requires_reauth, $password_updated) = updateUserProfile($user_id, $role, $_POST);
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'requires_reauth' => $requires_reauth,
        'password_updated' => $password_updated
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}