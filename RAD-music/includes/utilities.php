<?php
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $timeDiff = time() - $time;
    
    if ($timeDiff < 60) {
        return "just now";
    } elseif ($timeDiff < 3600) {
        $mins = floor($timeDiff / 60);
        return "$mins min" . ($mins > 1 ? 's' : '') . " ago";
    } elseif ($timeDiff < 86400) {
        $hours = floor($timeDiff / 3600);
        return "$hours hour" . ($hours > 1 ? 's' : '') . " ago";
    } elseif ($timeDiff < 604800) {
        $days = floor($timeDiff / 86400);
        return "$days day" . ($days > 1 ? 's' : '') . " ago";
    } else {
        return date('M j, Y', $time);
    }
}

function getActivityDescription($activity) {
    switch ($activity['activity_type']) {
        case 'post':
            return "created a new post";
        case 'like':
            return "liked a post";
        case 'comment':
            return "commented on a post";
        case 'follow':
            return "started following someone";
        default:
            return "did something";
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 8;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}
?>