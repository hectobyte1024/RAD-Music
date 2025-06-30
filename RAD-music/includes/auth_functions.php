<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    if (!isLoggedIn()) return false;
    
    // In a real app, you'd check a database field
    return $_SESSION['user_id'] == 1; // Example: user ID 1 is admin
}

function redirectIfNotLoggedIn($redirectTo = '/RAD-music/templates/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

function redirectIfLoggedIn($redirectTo = '/') {
    if (isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT id, username, email, avatar_url, bio, location 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
        throw new Exception("Invalid CSRF token");
    }
    return true;
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>