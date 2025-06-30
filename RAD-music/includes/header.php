<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_functions.php';

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'RAD Music Network') ?></title>
    <link rel="stylesheet" href="/RAD-music/assets/css/main.css">
    <link rel="stylesheet" href="/RAD-music/assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <a href="/" class="logo">RAD</a>
        </div>
        
        <div class="navbar-search">
            <form action="/search" method="GET">
                <input type="text" name="q" placeholder="Search for music, people, news...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        
        <div class="navbar-links">
            <?php if (isLoggedIn()): ?>
                <a href="/feed" class="<?= $activePage === 'feed' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i>
                    <span>Feed</span>
                </a>
                <a href="/discover" class="<?= $activePage === 'discover' ? 'active' : '' ?>">
                    <i class="fas fa-compass"></i>
                    <span>Discover</span>
                </a>
                <a href="/news" class="<?= $activePage === 'news' ? 'active' : '' ?>">
                    <i class="fas fa-newspaper"></i>
                    <span>News</span>
                </a>
                <a href="/profile" class="user-avatar">
                    <img src="<?= htmlspecialchars($currentUser['avatar_url'] ?? '/assets/images/default-avatar.jpg') ?>" alt="Profile">
                </a>
            <?php else: ?>
                <a href="/login" class="btn btn-outline">Login</a>
                <a href="/register" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </nav>
    
    <div class="container">