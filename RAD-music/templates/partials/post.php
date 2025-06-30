<div class="post" data-post-id="<?= $post['id'] ?>">
    <div class="post-header">
        <img src="<?= htmlspecialchars($post['avatar_url'] ?? '/assets/images/default-avatar.jpg') ?>" alt="Profile">
        <div class="post-user">
            <h4><?= htmlspecialchars($post['username']) ?></h4>
            <small><?= timeAgo($post['created_at']) ?></small>
        </div>
        
        <?php if ($post['user_id'] == ($_SESSION['user_id'] ?? null)): ?>
            <div class="post-actions">
                <button class="btn btn-icon post-menu-btn">
                    <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="post-menu">
                    <button class="post-menu-item delete-post-btn">Delete</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="post-content">
        <?php if (!empty($post['content'])): ?>
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
        <?php endif; ?>
        
        <?php if (!empty($post['tracks'])): ?>
            <div class="post-track">
                <?php foreach ($post['tracks'] as $track): ?>
                    <div class="track-card">
                        <img src="<?= htmlspecialchars($track['cover_url']) ?>" alt="Album cover">
                        <div class="track-info">
                            <h4><?= htmlspecialchars($track['title']) ?></h4>
                            <p><?= htmlspecialchars($track['artist']) ?></p>
                        </div>
                        <button class="btn btn-icon play-btn" data-preview-url="<?= htmlspecialchars($track['preview_url'] ?? '') ?>">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($post['media'])): ?>
            <div class="post-media">
                <?php foreach ($post['media'] as $media): ?>
                    <?php if ($media['media_type'] === 'image'): ?>
                        <img src="/uploads/<?= htmlspecialchars($media['media_url']) ?>" alt="Post media">
                    <?php elseif ($media['media_type'] === 'video'): ?>
                        <video controls>
                            <source src="/uploads/<?= htmlspecialchars($media['media_url']) ?>" type="video/mp4">
                        </video>
                    <?php else: ?>
                        <audio controls>
                            <source src="/uploads/<?= htmlspecialchars($media['media_url']) ?>" type="audio/mpeg">
                        </audio>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="post-footer">
        <button class="btn btn-icon like-btn <?= $post['is_liked'] ? 'liked' : '' ?>">
            <i class="far fa-heart"></i>
            <span><?= $post['like_count'] ?></span>
        </button>
        
        <button class="btn btn-icon comment-btn">
            <i class="far fa-comment"></i>
            <span><?= $post['comment_count'] ?></span>
        </button>
        
        <button class="btn btn-icon share-btn">
            <i class="fas fa-share"></i>
        </button>
    </div>
    
    <div class="post-comments">
        <?php if ($post['comment_count'] > 0): ?>
            <?php $comments = (new Post())->getComments($post['id'], 3); ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment">
                    <img src="<?= htmlspecialchars($comment['avatar_url'] ?? '/assets/images/default-avatar.jpg') ?>" alt="Profile">
                    <div class="comment-content">
                        <h5><?= htmlspecialchars($comment['username']) ?></h5>
                        <p><?= htmlspecialchars($comment['content']) ?></p>
                        <small><?= timeAgo($comment['created_at']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($post['comment_count'] > 3): ?>
                <button class="btn btn-link view-more-comments">View all <?= $post['comment_count'] ?> comments</button>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isLoggedIn()): ?>
            <form class="add-comment-form">
                <input type="text" placeholder="Add a comment..." class="comment-input">
                <button type="submit" class="btn btn-link">Post</button>
            </form>
        <?php endif; ?>
    </div>
</div>