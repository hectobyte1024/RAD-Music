<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

class Post {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($userId, $content, $trackId = null, $media = []) {
        // Validate input
        if (empty($content) && empty($trackId) && empty($media)) {
            throw new Exception("Post cannot be empty");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Create the post
            $stmt = $this->db->prepare("
                INSERT INTO posts (user_id, content, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())
            ");
            $stmt->execute([$userId, $content]);
            $postId = $this->db->lastInsertId();
            
            // Attach track if provided
            if ($trackId) {
                $this->attachTrack($postId, $trackId);
            }
            
            // Handle media attachments
            foreach ($media as $file) {
                $this->attachMedia($postId, $file);
            }
            
            // Log activity
            $this->logActivity($userId, 'post', $postId);
            
            $this->db->commit();
            return $postId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function attachTrack($postId, $trackId) {
        $stmt = $this->db->prepare("
            INSERT INTO post_tracks (post_id, track_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$postId, $trackId]);
    }
    
    private function attachMedia($postId, $file) {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error");
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("File too large");
        }
        
        // Determine media type
        $fileType = $file['type'];
        $allowedTypes = [
            'image' => ['image/jpeg', 'image/png', 'image/gif'],
            'video' => ['video/mp4', 'video/quicktime'],
            'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg']
        ];
        
        $mediaType = null;
        foreach ($allowedTypes as $type => $mimes) {
            if (in_array($fileType, $mimes)) {
                $mediaType = $type;
                break;
            }
        }
        
        if (!$mediaType) {
            throw new Exception("Invalid file type");
        }
        
        // Generate filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = "media_{$postId}_" . uniqid() . ".$extension";
        $targetPath = UPLOAD_DIR . "/$filename";
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Failed to save file");
        }
        
        // Save to database
        $stmt = $this->db->prepare("
            INSERT INTO post_media (post_id, media_url, media_type) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$postId, $filename, $mediaType]);
    }
    
    public function getFeedPosts($userId, $limit = 20, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT p.*, u.username, u.avatar_url,
                   (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
                   EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = ?) as is_liked
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.user_id IN (
                SELECT following_id FROM user_followers WHERE follower_id = ?
            ) OR p.user_id = ? OR EXISTS (
                SELECT 1 FROM post_tracks pt 
                JOIN user_listening_history ulh ON pt.track_id = ulh.track_id
                WHERE pt.post_id = p.id AND ulh.user_id = ?
            )
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $limit, $offset]);
        $posts = $stmt->fetchAll();
        
        foreach ($posts as &$post) {
            $post['tracks'] = $this->getPostTracks($post['id']);
            $post['media'] = $this->getPostMedia($post['id']);
        }
        
        return $posts;
    }
    
    public function getPostById($postId, $userId = null) {
        $stmt = $this->db->prepare("
            SELECT p.*, u.username, u.avatar_url,
                   (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
                   " . ($userId ? "EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = ?) as is_liked" : "0 as is_liked") . "
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        
        $params = $userId ? [$userId, $postId] : [$postId];
        $stmt->execute($params);
        $post = $stmt->fetch();
        
        if ($post) {
            $post['tracks'] = $this->getPostTracks($post['id']);
            $post['media'] = $this->getPostMedia($post['id']);
        }
        
        return $post;
    }
    
    private function getPostTracks($postId) {
        $stmt = $this->db->prepare("
            SELECT t.* 
            FROM post_tracks pt
            JOIN tracks t ON pt.track_id = t.id
            WHERE pt.post_id = ?
        ");
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }
    
    private function getPostMedia($postId) {
        $stmt = $this->db->prepare("
            SELECT id, media_url, media_type 
            FROM post_media 
            WHERE post_id = ?
            ORDER BY id
        ");
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }
    
    public function likePost($userId, $postId) {
        // Check if already liked
        $stmt = $this->db->prepare("
            SELECT 1 FROM post_likes 
            WHERE user_id = ? AND post_id = ?
        ");
        $stmt->execute([$userId, $postId]);
        
        if ($stmt->fetch()) {
            // Unlike
            $stmt = $this->db->prepare("
                DELETE FROM post_likes 
                WHERE user_id = ? AND post_id = ?
            ");
            $stmt->execute([$userId, $postId]);
            $action = 'unliked';
        } else {
            // Like
            $stmt = $this->db->prepare("
                INSERT INTO post_likes (user_id, post_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$userId, $postId]);
            $action = 'liked';
            
            // Log activity
            $this->logActivity($userId, 'like', $postId);
        }
        
        // Get updated like count
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as like_count 
            FROM post_likes 
            WHERE post_id = ?
        ");
        $stmt->execute([$postId]);
        $likeCount = $stmt->fetch()['like_count'];
        
        return [
            'action' => $action,
            'like_count' => $likeCount
        ];
    }
    
    public function addComment($userId, $postId, $content) {
        if (empty($content)) {
            throw new Exception("Comment cannot be empty");
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO post_comments (user_id, post_id, content, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $postId, $content]);
        $commentId = $this->db->lastInsertId();
        
        // Log activity
        $this->logActivity($userId, 'comment', $postId);
        
        // Get comment with user info
        $stmt = $this->db->prepare("
            SELECT c.*, u.username, u.avatar_url
            FROM post_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$commentId]);
        return $stmt->fetch();
    }
    
    public function getComments($postId, $limit = 10, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT c.*, u.username, u.avatar_url
            FROM post_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$postId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public function deletePost($userId, $postId) {
        // Verify ownership
        $stmt = $this->db->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        if (!$post || $post['user_id'] != $userId) {
            throw new Exception("Unauthorized to delete this post");
        }
        
        // Get media files to delete
        $stmt = $this->db->prepare("SELECT media_url FROM post_media WHERE post_id = ?");
        $stmt->execute([$postId]);
        $mediaFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete from database
        $this->db->beginTransaction();
        try {
            // Delete media records
            $stmt = $this->db->prepare("DELETE FROM post_media WHERE post_id = ?");
            $stmt->execute([$postId]);
            
            // Delete track associations
            $stmt = $this->db->prepare("DELETE FROM post_tracks WHERE post_id = ?");
            $stmt->execute([$postId]);
            
            // Delete likes
            $stmt = $this->db->prepare("DELETE FROM post_likes WHERE post_id = ?");
            $stmt->execute([$postId]);
            
            // Delete comments
            $stmt = $this->db->prepare("DELETE FROM post_comments WHERE post_id = ?");
            $stmt->execute([$postId]);
            
            // Delete the post
            $stmt = $this->db->prepare("DELETE FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
            
            $this->db->commit();
            
            // Delete media files
            foreach ($mediaFiles as $filename) {
                $filePath = UPLOAD_DIR . "/$filename";
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function logActivity($userId, $activityType, $targetId, $targetType = 'post') {
        $stmt = $this->db->prepare("
            INSERT INTO user_activity (user_id, activity_type, target_type, target_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $activityType, $targetType, $targetId]);
    }
}
?>