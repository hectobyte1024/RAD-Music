<?php

require_once __DIR__ . '/../api/auth.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$user = isLoggedIn() ? (new User())->getProfile($_SESSION['user_id']) : null;
$recommendations = isLoggedIn() ? (new RecommendationEngine())->getForUser($_SESSION['user_id'], 5) : [];
?>

<div class="home-container">
    <?php if (isLoggedIn()): ?>
        <div class="left-sidebar">
            <div class="user-card">
                <img src="<?= htmlspecialchars($user['avatar_url'] ?? '/assets/images/default-avatar.jpg') ?>" 
                     alt="<?= htmlspecialchars($user['username']) ?>">
                <h3><?= htmlspecialchars($user['username']) ?></h3>
                <div class="stats">
                    <span><?= $user['followers'] ?> Followers</span>
                    <span><?= $user['following'] ?> Following</span>
                </div>
            </div>
            
            <div class="recommendations">
                <h3>Recommended For You</h3>
                <?php foreach ($recommendations as $track): ?>
                    <div class="track">
                        <img src="<?= htmlspecialchars($track['cover_url']) ?>" alt="Album cover">
                        <div>
                            <h4><?= htmlspecialchars($track['title']) ?></h4>
                            <p><?= htmlspecialchars($track['artist']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <main class="main-content">
        <?php if (isLoggedIn()): ?>
            <div class="post-creator">
                <form action="/api/posts/create" method="POST" enctype="multipart/form-data">
                    <textarea name="content" placeholder="What's bumping today?"></textarea>
                    <div class="post-actions">
                        <input type="file" name="media[]" multiple accept="image/*,video/*,audio/*">
                        <button type="button" class="attach-music">Attach Music</button>
                        <button type="submit" class="post-button">Post</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <div class="feed">
            <?php 
            if (isLoggedIn()) {
                $posts = (new Post())->getFeedPosts($_SESSION['user_id']);
                foreach ($posts as $post) {
                    include __DIR__ . '/partials/post.php';
                }
            } else {
                include __DIR__ . '/partials/welcome.php';
            }
            ?>
        </div>
    </main>
    
    <div class="right-sidebar">
        <div class="news-feed">
            <h3>Music News</h3>
            <?php 
            $news = (new News())->getTrending(5);
            foreach ($news as $item) {
                include __DIR__ . '/partials/news_item.php';
            }
            ?>
        </div>
        
        <div class="top-charts">
            <h3>Billboard Top 5</h3>
            <?php 
            $charts = (new Charts())->getCurrentTop('hot-100', 5);
            foreach ($charts as $chartEntry) {
                include __DIR__ . '/partials/chart_item.php';
            }
            ?>
        </div>
    </div>
</div>