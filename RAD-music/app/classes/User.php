<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($username, $email, $password) {
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception("All fields are required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }
        
        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            throw new Exception("Email or username already exists");
        }
        
        // Create user
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $success = $stmt->execute([$username, $email, $hashedPassword]);
        
        if (!$success) {
            throw new Exception("Registration failed");
        }
        
        return $this->db->lastInsertId();
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("
            SELECT id, username, email, password_hash, avatar_url 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            
            $this->updateLastLogin($user['id']);
            return true;
        }
        return false;
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    public function getProfile($userId) {
        $stmt = $this->db->prepare("
            SELECT u.*, 
                   (SELECT COUNT(*) FROM user_followers WHERE following_id = u.id) as followers,
                   (SELECT COUNT(*) FROM user_followers WHERE follower_id = u.id) as following,
                   (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            throw new Exception("User not found");
        }
        
        return $profile;
    }
    
    public function updateProfile($userId, $data) {
        $updatableFields = ['username', 'bio', 'location'];
        $updates = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $updatableFields)) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $userId;
        $query = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    
    public function updateAvatar($userId, $file) {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error");
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("File too large");
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Invalid file type");
        }
        
        // Generate filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = "avatar_{$userId}_" . time() . ".$extension";
        $targetPath = UPLOAD_DIR . "/$filename";
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Failed to save file");
        }
        
        // Update database
        $stmt = $this->db->prepare("UPDATE users SET avatar_url = ?, updated_at = NOW() WHERE id = ?");
        $success = $stmt->execute([$filename, $userId]);
        
        if ($success) {
            $_SESSION['avatar_url'] = $filename;
            return $filename;
        }
        
        return false;
    }
    
    public function followUser($followerId, $followingId) {
        if ($followerId == $followingId) {
            throw new Exception("Cannot follow yourself");
        }
        
        // Check if already following
        $stmt = $this->db->prepare("
            SELECT 1 FROM user_followers 
            WHERE follower_id = ? AND following_id = ?
        ");
        $stmt->execute([$followerId, $followingId]);
        
        if ($stmt->fetch()) {
            // Unfollow
            $stmt = $this->db->prepare("
                DELETE FROM user_followers 
                WHERE follower_id = ? AND following_id = ?
            ");
            $stmt->execute([$followerId, $followingId]);
            return 'unfollowed';
        } else {
            // Follow
            $stmt = $this->db->prepare("
                INSERT INTO user_followers (follower_id, following_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$followerId, $followingId]);
            return 'followed';
        }
    }
    
    public function searchUsers($query, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT id, username, avatar_url 
            FROM users 
            WHERE username LIKE ? 
            ORDER BY username 
            LIMIT ?
        ");
        $stmt->execute(["%$query%", $limit]);
        return $stmt->fetchAll();
    }
    
    public function getFollowers($userId, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.avatar_url 
            FROM user_followers uf
            JOIN users u ON uf.follower_id = u.id
            WHERE uf.following_id = ?
            ORDER BY uf.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getFollowing($userId, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.avatar_url 
            FROM user_followers uf
            JOIN users u ON uf.following_id = u.id
            WHERE uf.follower_id = ?
            ORDER BY uf.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}
?>