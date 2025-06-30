<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$title = "RAD Music Network";
$jsFiles = ['home.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="home-hero">
    <div class="hero-content">
        <h1>Discover and Share Your Music</h1>
        <p>Connect with music lovers around the world. Find new tracks, share your favorites, and stay updated with the latest music news.</p>
        
        <?php if (!isLoggedIn()): ?>
            <div class="hero-actions">
                <a href="/RAD-music/templates/register.php" class="btn btn-primary btn-lg">Join Now</a>
                <a href="/RAD-music/templates/login.php" class="btn btn-outline btn-lg">Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!isLoggedIn()): ?>
    <div class="features-section">
        <div class="feature">
            <i class="fas fa-music"></i>
            <h3>Discover Music</h3>
            <p>Get personalized recommendations based on your listening habits.</p>
        </div>
        
        <div class="feature">
            <i class="fas fa-users"></i>
            <h3>Connect with Others</h3>
            <p>Follow friends and artists to see what they're listening to.</p>
        </div>
        
        <div class="feature">
            <i class="fas fa-newspaper"></i>
            <h3>Stay Updated</h3>
            <p>Get the latest music news and chart updates.</p>
        </div>
    </div>
<?php else: ?>
    <div class="home-content">
        <div class="home-sidebar">
            <div class="trending-tracks">
                <h3>Trending Now</h3>
                <?php foreach (getTrendingTracks(5) as $track): ?>
                    <div class="track-card">
                        <img src="<?= htmlspecialchars($track['cover_url']) ?>" alt="Album cover">
                        <div class="track-info">
                            <h4><?= htmlspecialchars($track['title']) ?></h4>
                            <p><?= htmlspecialchars($track['artist']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="popular-artists">
                <h3>Popular Artists</h3>
                <?php foreach (getPopularArtists(5) as $artist): ?>
                    <div class="artist-card">
                        <div class="artist-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="artist-info">
                            <h4><?= htmlspecialchars($artist['artist']) ?></h4>
                            <p><?= $artist['track_count'] ?> tracks</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="home-main">
            <div class="activity-feed">
                <h2>Recent Activity</h2>
                <?php foreach (getRecentActivity($_SESSION['user_id'], 10) as $activity): ?>
                    <div class="activity-item">
                        <img src="<?= htmlspecialchars($activity['actor_avatar'] ?? '/assets/images/default-avatar.jpg') ?>" alt="User">
                        <div class="activity-content">
                            <p>
                                <strong><?= htmlspecialchars($activity['actor_username']) ?></strong>
                                <?= getActivityDescription($activity) ?>
                            </p>
                            <small><?= timeAgo($activity['created_at']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>