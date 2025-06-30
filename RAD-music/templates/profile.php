<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth_functions.php';

redirectIfNotLoggedIn();

$profileId = $_GET['id'] ?? $_SESSION['user_id'];
$user = new User();
$profile = $user->getProfile($profileId);
$isCurrentUser = ($profileId == $_SESSION['user_id']);

$title = $profile['username'] . "'s Profile";
$jsFiles = ['profile.js'];


$post = new Post();
$posts = $post->getFeedPosts($profileId);
?>

<div class="profile-header" style="background-image: url('<?= htmlspecialchars($profile['cover_url'] ?? '/assets/images/default-cover.jpg') ?>')">
    <div class="profile-avatar">
        <img src="<?= htmlspecialchars($profile['avatar_url'] ?? '/assets/images/default-avatar.jpg') ?>" alt="Profile picture">
    </div>
    
    <div class="profile-info">
        <h1><?= htmlspecialchars($profile['username']) ?></h1>
        
        <?php if (!empty($profile['bio'])): ?>
            <p class="profile-bio"><?= htmlspecialchars($profile['bio']) ?></p>
        <?php endif; ?>
        
        <?php if (!empty($profile['location'])): ?>
            <p class="profile-location">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($profile['location']) ?>
            </p>
        <?php endif; ?>
        
        <div class="profile-stats">
            <div>
                <strong><?= $profile['post_count'] ?></strong>
                <span>Posts</span>
            </div>
            <div>
                <strong><?= $profile['followers'] ?></strong>
                <span>Followers</span>
            </div>
            <div>
                <strong><?= $profile['following'] ?></strong>
                <span>Following</span>
            </div>
        </div>
        
        <div class="profile-actions">
            <?php if ($isCurrentUser): ?>
                <button class="btn btn-outline" id="edit-profile-btn">Edit Profile</button>
                <button class="btn btn-outline" id="change-cover-btn">Change Cover</button>
            <?php else: ?>
                <button class="btn btn-primary" id="follow-btn" data-user-id="<?= $profileId ?>">
                    <?= $user->isFollowing($_SESSION['user_id'], $profileId) ? 'Following' : 'Follow' ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="profile-content">
    <div class="profile-tabs">
        <button class="tab-btn active" data-tab="posts">Posts</button>
        <button class="tab-btn" data-tab="likes">Likes</button>
        <button class="tab-btn" data-tab="tracks">Tracks</button>
        <button class="tab-btn" data-tab="following">Following</button>
        <button class="tab-btn" data-tab="followers">Followers</button>
    </div>
    
    <div class="tab-content active" id="posts-tab">
        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <i class="fas fa-music"></i>
                <h3>No posts yet</h3>
                <?php if ($isCurrentUser): ?>
                    <p>Start sharing your music to appear here</p>
                <?php else: ?>
                    <p>This user hasn't posted anything yet</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="posts-grid">
                <?php foreach ($posts as $post): ?>
                    <?php include __DIR__ . '/partials/post.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="tab-content" id="likes-tab">
        <!-- Liked posts would go here -->
    </div>
    
    <div class="tab-content" id="tracks-tab">
        <!-- Top tracks would go here -->
    </div>
    
    <div class="tab-content" id="following-tab">
        <?php $following = $user->getFollowing($profileId); ?>
        <?php if (empty($following)): ?>
            <div class="empty-state">
                <i class="fas fa-user-friends"></i>
                <h3>Not following anyone</h3>
            </div>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($following as $user): ?>
                    <div class="user-card">
                        <img src="<?= htmlspecialchars($user['avatar_url'] ?? '/assets/images/default-avatar.jpg') ?>" alt="Profile">
                        <h4><?= htmlspecialchars($user['username']) ?></h4>
                        <a href="/profile?id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">View Profile</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="tab-content" id="followers-tab">
        <?php $followers = $user->getFollowers($profileId); ?>
        <?php if (empty($followers)): ?>
            <div class="empty-state">
                <i class="fas fa-user-friends"></i>
                <h3>No followers yet</h3>
            </div>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($followers as $user): ?>
                    <div class="user-card">
                        <img src="<?= htmlspecialchars($user['avatar_url'] ?? '/assets/images/default-avatar.jpg') ?>" alt="Profile">
                        <h4><?= htmlspecialchars($user['username']) ?></h4>
                        <a href="/profile?id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">View Profile</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal" id="edit-profile-modal">
    <div class="modal-content">
        <h2>Edit Profile</h2>
        <form id="edit-profile-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($profile['username']) ?>">
            </div>
            
            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" value="<?= htmlspecialchars($profile['location'] ?? '') ?>">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" id="cancel-edit-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>