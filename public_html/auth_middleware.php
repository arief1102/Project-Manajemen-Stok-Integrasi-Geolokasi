<?php
require_once 'config/db_connect.php';

function authenticate() {
    global $pdo;
    
    if (!isset($_COOKIE['auth_token'])) {
        return false;
    }
    
    $token = $_COOKIE['auth_token'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE auth_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        return $user;
    }
    
    return false;
}

function requireRole($role) {
    $user = authenticate();
    if (!$user || $user['role'] !== $role) {
        header("Location: login.php");
        exit();
    }
    return $user;
}
