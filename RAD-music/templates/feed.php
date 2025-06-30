<?php

require_once __DIR__ . '/../includes/auth_functions.php';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

redirectIfNotLoggedIn();

$title = "Your Feed";
$jsFiles = ['feed.js'];
require_once __DIR__ . '/../includes/header.php';

$post = new Post();
$posts = $post->getFeedPosts($_SESSION['user_id']);
?>

<div class="feed-container">
    <div class="feed-sidebar">
        <div class="user-card">
            <img src="<?= htmlspecialchars($currentUser['avatar_url'] ?? '/assets/images/default-avatar.jpg') ?>" alt="Profile">
            <h3><?= htmlspecialchars($currentUser['username']) ?></h3>
            <div class="user-stats">
                <div>
                    <strong><?= $currentUser['followers'] ?? 0 ?></strong>
                    <span>Followers</span>
                </div>
                <div>
                    <strong><?= $currentUser['following'] ?? 0 ?></strong>
                    <span>Following</span>
                </div>
            </div>
        </div>
        
        <div class="recommendations">
            <h3>Recommended For You</h3>
            <?php 
            $recommendations = (new RecommendationEngine())->getForUser($_SESSION['user_id'], 5);
            foreach ($recommendations as $track): ?>
                <div class="track">
                    <img src="<?= htmlspecialchars($track['cover_url']) ?>" alt="Album cover">
                    <div class="track-info">
                        <h4><?= htmlspecialchars($track['title']) ?></h4>
                        <p><?= htmlspecialchars($track['artist']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="feed-main">
        <div class="post-creator">
            <form id="create-post-form" enctype="multipart/form-data">
                <textarea name="content" placeholder="What's bumping today?"></textarea>
                <div class="post-actions">
                    <input type="file" name="media[]" id="post-media" multiple accept="image/*,video/*,audio/*" style="display: none;">
                    <button type="button" class="btn btn-icon" onclick="document.getElementById('post-media').click()">
                        <i class="fas fa-image"></i>
                    </button>
                    <button type="button" class="btn btn-icon" id="attach-music-btn">
                        <i class="fas fa-music"></i>
                    </button>
                    <button type="submit" class="btn btn-primary">Post</button>
                </div>
                <div class="media-preview" id="media-preview"></div>
                <div class="track-preview" id="track-preview"></div>
            </form>
        </div>
        
        <div class="posts-list">
            <?php if (empty($posts)): ?>
                <div class="empty-feed">
                    <i class="fas fa-music"></i>
                    <h3>Your feed is empty</h3>
                    <p>Follow some users or start posting to see content here</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php include __DIR__ . '/partials/post.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="feed-sidebar-right">
        <div class="news-feed">
            <h3>Music News</h3>
            <?php 
            $newsItems = (new News())->getTrending(3);
            foreach ($newsItems as $news): ?>
                <div class="news-card">
                    <img src="<?= htmlspecialchars($news['cover_url']) ?>" alt="News cover">
                    <h4><?= htmlspecialchars($news['title']) ?></h4>
                    <p><?= htmlspecialchars(substr($news['excerpt'] ?? $news['content'], 0, 100)) ?>...</p>
                    <a href="/news/<?= $news['id'] ?>" class="btn btn-link">Read More</a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="top-charts">
            <h3>Top Charts</h3>
            <?php 
            $topTracks = (new Charts())->getCurrentTop('hot-100', 5);
            foreach ($topTracks as $index => $track): ?>
                <div class="chart-track">
                    <span class="chart-position"><?= $index + 1 ?></span>
                    <img src="<?= htmlspecialchars($track['cover_url']) ?>" alt="Album cover">
                    <div class="track-info">
                        <h4><?= htmlspecialchars($track['title']) ?></h4>
                        <p><?= htmlspecialchars($track['artist']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <a href="/charts" class="btn btn-link">View Full Chart</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>