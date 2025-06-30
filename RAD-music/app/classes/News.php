<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

class News {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getTrending($limit = 5) {
        $stmt = $this->db->prepare("
            SELECT n.*, 
                   (SELECT COUNT(*) FROM user_news_interactions WHERE news_id = n.id AND interaction_type = 'view') as views,
                   (SELECT COUNT(*) FROM user_news_interactions WHERE news_id = n.id AND interaction_type = 'like') as likes
            FROM music_news n
            WHERE n.is_featured = TRUE OR n.featured_until > NOW()
            ORDER BY n.hotness_score DESC, n.published_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $newsItems = $stmt->fetchAll();
        
        foreach ($newsItems as &$item) {
            $item['tracks'] = $this->getNewsTracks($item['id']);
            $item['artists'] = $this->getNewsArtists($item['id']);
        }
        
        return $newsItems;
    }
    
    public function getByCategory($category, $limit = 10, $offset = 0) {
        $validCategories = ['charts', 'releases', 'industry', 'artists', 'trending'];
        if (!in_array($category, $validCategories)) {
            throw new Exception("Invalid news category");
        }
        
        $stmt = $this->db->prepare("
            SELECT n.*, 
                   (SELECT COUNT(*) FROM user_news_interactions WHERE news_id = n.id AND interaction_type = 'view') as views,
                   (SELECT COUNT(*) FROM user_news_interactions WHERE news_id = n.id AND interaction_type = 'like') as likes
            FROM music_news n
            WHERE n.category = ?
            ORDER BY n.published_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$category, $limit, $offset]);
        $newsItems = $stmt->fetchAll();
        
        foreach ($newsItems as &$item) {
            $item['tracks'] = $this->getNewsTracks($item['id']);
            $item['artists'] = $this->getNewsArtists($item['id']);
        }
        
        return $newsItems;
    }
    
    public function getById($newsId, $userId = null) {
        $stmt = $this->db->prepare("
            SELECT n.*, 
                   (SELECT COUNT(*) FROM user_news_interactions WHERE news_id = n.id AND interaction_type = 'view') as views,
                   (SELECT COUNT(*) FROM user_news_interactions WHERE news_id = n.id AND interaction_type = 'like') as likes
                   " . ($userId ? ", EXISTS(SELECT 1 FROM user_news_interactions WHERE news_id = n.id AND user_id = ? AND interaction_type = 'like') as is_liked" : "") . "
            FROM music_news n
            WHERE n.id = ?
        ");
        
        $params = $userId ? [$userId, $newsId] : [$newsId];
        $stmt->execute($params);
        $newsItem = $stmt->fetch();
        
        if ($newsItem) {
            $newsItem['tracks'] = $this->getNewsTracks($newsItem['id']);
            $newsItem['artists'] = $this->getNewsArtists($newsItem['id']);
            
            // Record view if user is logged in
            if ($userId) {
                $this->recordInteraction($userId, $newsId, 'view');
            }
        }
        
        return $newsItem;
    }
    
    private function getNewsTracks($newsId) {
        $stmt = $this->db->prepare("
            SELECT t.*, nt.relevance
            FROM news_tracks nt
            JOIN tracks t ON nt.track_id = t.id
            WHERE nt.news_id = ?
            ORDER BY 
                CASE nt.relevance 
                    WHEN 'primary' THEN 1
                    WHEN 'secondary' THEN 2
                    WHEN 'mentioned' THEN 3
                END
        ");
        $stmt->execute([$newsId]);
        return $stmt->fetchAll();
    }
    
    private function getNewsArtists($newsId) {
        $stmt = $this->db->prepare("
            SELECT artist_name, relevance
            FROM news_artists
            WHERE news_id = ?
            ORDER BY 
                CASE relevance 
                    WHEN 'primary' THEN 1
                    WHEN 'secondary' THEN 2
                    WHEN 'mentioned' THEN 3
                END
        ");
        $stmt->execute([$newsId]);
        return $stmt->fetchAll();
    }
    
    public function likeNews($userId, $newsId) {
        // Check if already liked
        $stmt = $this->db->prepare("
            SELECT 1 FROM user_news_interactions 
            WHERE user_id = ? AND news_id = ? AND interaction_type = 'like'
        ");
        $stmt->execute([$userId, $newsId]);
        
        if ($stmt->fetch()) {
            // Unlike
            $stmt = $this->db->prepare("
                DELETE FROM user_news_interactions 
                WHERE user_id = ? AND news_id = ? AND interaction_type = 'like'
            ");
            $stmt->execute([$userId, $newsId]);
            $action = 'unliked';
        } else {
            // Like
            $this->recordInteraction($userId, $newsId, 'like');
            $action = 'liked';
        }
        
        // Get updated like count
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as like_count 
            FROM user_news_interactions 
            WHERE news_id = ? AND interaction_type = 'like'
        ");
        $stmt->execute([$newsId]);
        $likeCount = $stmt->fetch()['like_count'];
        
        // Update hotness score
        $this->updateHotnessScore($newsId);
        
        return [
            'action' => $action,
            'like_count' => $likeCount
        ];
    }
    
    private function recordInteraction($userId, $newsId, $interactionType) {
        // Check if interaction already exists
        $stmt = $this->db->prepare("
            SELECT 1 FROM user_news_interactions
            WHERE user_id = ? AND news_id = ? AND interaction_type = ?
        ");
        $stmt->execute([$userId, $newsId, $interactionType]);
        
        if (!$stmt->fetch()) {
            $stmt = $this->db->prepare("
                INSERT INTO user_news_interactions (user_id, news_id, interaction_type, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $newsId, $interactionType]);
        }
    }
    
    private function updateHotnessScore($newsId) {
        $stmt = $this->db->prepare("
            UPDATE music_news
            SET hotness_score = (
                SELECT COUNT(*) FROM user_news_interactions 
                WHERE news_id = ? AND interaction_type = 'view'
            ) + (
                SELECT COUNT(*) FROM user_news_interactions 
                WHERE news_id = ? AND interaction_type = 'like'
            ) * 2 + (
                SELECT COUNT(*) FROM user_news_interactions 
                WHERE news_id = ? AND interaction_type = 'share'
            ) * 3
            WHERE id = ?
        ");
        $stmt->execute([$newsId, $newsId, $newsId, $newsId]);
    }
    
    public function search($query, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT n.id, n.title, n.excerpt, n.cover_url, n.published_at
            FROM music_news n
            WHERE n.title LIKE ? OR n.content LIKE ?
            ORDER BY n.hotness_score DESC, n.published_at DESC
            LIMIT ?
        ");
        $searchQuery = "%$query%";
        $stmt->execute([$searchQuery, $searchQuery, $limit]);
        return $stmt->fetchAll();
    }
    
    public function importFromBillboard() {
        // Implementation would use Billboard scraping or API
        // This is a placeholder for the actual implementation
        $billboardNews = $this->scrapeBillboardNews();
        
        foreach ($billboardNews as $newsItem) {
            $this->db->beginTransaction();
            try {
                // Insert news item
                $stmt = $this->db->prepare("
                    INSERT INTO music_news 
                    (title, content, cover_url, category, source, source_url, published_at, hotness_score)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $newsItem['title'],
                    $newsItem['content'],
                    $newsItem['cover_url'],
                    $newsItem['category'],
                    'Billboard',
                    $newsItem['url'],
                    $newsItem['date'],
                    50 // Initial score
                ]);
                $newsId = $this->db->lastInsertId();
                
                // Add related artists
                foreach ($newsItem['artists'] as $artist) {
                    $stmt = $this->db->prepare("
                        INSERT INTO news_artists (news_id, artist_name, relevance)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$newsId, $artist['name'], $artist['relevance']]);
                }
                
                // Add related tracks
                foreach ($newsItem['tracks'] as $track) {
                    // First ensure track exists in our database
                    $this->ensureTrackExists($track);
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO news_tracks (news_id, track_id, relevance)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$newsId, $track['id'], $track['relevance']]);
                }
                
                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                error_log("Failed to import Billboard news: " . $e->getMessage());
            }
        }
    }
    
    private function ensureTrackExists($trackData) {
        // Check if track exists
        $stmt = $this->db->prepare("SELECT 1 FROM tracks WHERE id = ?");
        $stmt->execute([$trackData['id']]);
        
        if (!$stmt->fetch()) {
            // Insert new track
            $stmt = $this->db->prepare("
                INSERT INTO tracks 
                (id, title, artist, album, duration_ms, explicit, popularity, cover_url, spotify_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $trackData['id'],
                $trackData['title'],
                $trackData['artist'],
                $trackData['album'],
                $trackData['duration'],
                $trackData['explicit'],
                $trackData['popularity'],
                $trackData['cover_url'],
                $trackData['spotify_url']
            ]);
            
            // If we have features, add them
            if (!empty($trackData['features'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO track_features 
                    (track_id, danceability, energy, key, loudness, mode, speechiness, 
                     acousticness, instrumentalness, liveness, valence, tempo, duration_ms, time_signature)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $trackData['id'],
                    $trackData['features']['danceability'],
                    $trackData['features']['energy'],
                    $trackData['features']['key'],
                    $trackData['features']['loudness'],
                    $trackData['features']['mode'],
                    $trackData['features']['speechiness'],
                    $trackData['features']['acousticness'],
                    $trackData['features']['instrumentalness'],
                    $trackData['features']['liveness'],
                    $trackData['features']['valence'],
                    $trackData['features']['tempo'],
                    $trackData['features']['duration_ms'],
                    $trackData['features']['time_signature']
                ]);
            }
        }
    }
    
    // This would be implemented with actual scraping logic
    private function scrapeBillboardNews() {
        // Placeholder - actual implementation would use DOM scraping or API
        return [];
    }
}
?>