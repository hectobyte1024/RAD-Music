<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

session_start();

// Handle routing
$request = $_SERVER['REQUEST_URI'];
$basePath = str_replace('/public', '', BASE_URL);
$route = str_replace($basePath, '', $request);
$route = explode('?', $route)[0];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Route handling
switch ($route) {
    case '/':
    case '/home':
        require __DIR__ . '/../templates/home.php';
        break;
    case '/feed':
        require __DIR__ . '/../templates/feed.php';
        break;
    case '/news':
        require __DIR__ . '/../templates/news.php';
        break;
    case '/profile':
        require __DIR__ . '/../templates/profile.php';
        break;
    case '/login':
        require __DIR__ . '/../templates/login.php';
        break;
    case '/register':
        require __DIR__ . '/../templates/register.php';
        break;
    default:
        http_response_code(404);
        require __DIR__ . '/../templates/404.php';
        break;
}

// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>