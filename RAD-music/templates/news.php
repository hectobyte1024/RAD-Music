<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$title = "Music News";
$jsFiles = ['news.js'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../app/classes/News.php';

$news = new News();
$category = $_GET['category'] ?? 'trending';
$newsItems = $news->getByCategory($category);
?>

<div class="news-container">
    <div class="news-header">
        <h1>Music News</h1>
        
        <div class="news-categories">
            <a href="/news?category=trending" class="<?= $category === 'trending' ? 'active' : '' ?>">Trending</a>
            <a href="/news?category=charts" class="<?= $category === 'charts' ? 'active' : '' ?>">Charts</a>
            <a href="/news?category=releases" class="<?= $category === 'releases' ? 'active' : '' ?>">New Releases</a>
            <a href="/news?category=artists" class="<?= $category === 'artists' ? 'active' : '' ?>">Artists</a>
            <a href="/news?category=industry" class="<?= $category === 'industry' ? 'active' : '' ?>">Industry</a>
        </div>
    </div>
    
    <div class="news-list">
        <?php foreach ($newsItems as $item): ?>
            <div class="news-card">
                <div class="news-image">
                    <img src="<?= htmlspecialchars($item['cover_url']) ?>" alt="News cover">
                </div>
                
                <div class="news-content">
                    <div class="news-meta">
                        <span class="news-category"><?= ucfirst($item['category']) ?></span>
                        <span class="news-date"><?= date('M j, Y', strtotime($item['published_at'])) ?></span>
                    </div>
                    
                    <h2><?= htmlspecialchars($item['title']) ?></h2>
                    <p><?= htmlspecialchars($item['excerpt'] ?? substr($item['content'], 0, 200)) ?>...</p>
                    
                    <div class="news-actions">
                        <a href="/news/<?= $item['id'] ?>" class="btn btn-link">Read More</a>
                        <button class="btn btn-icon like-btn" data-news-id="<?= $item['id'] ?>">
                            <i class="far fa-heart"></i>
                            <span><?= $item['likes'] ?></span>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>