<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

class RecommendationEngine {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getForUser($userId, $limit = 10) {
        // Get user's listening history with features
        $userTracks = $this->getUserTracksWithFeatures($userId);
        
        if (empty($userTracks)) {
            // If no history, return trending tracks
            return $this->getTrendingTracks($limit);
        }
        
        // Get candidate tracks (tracks the user hasn't listened to)
        $candidateTracks = $this->getCandidateTracks($userId, 1000);
        
        if (empty($candidateTracks)) {
            return $this->getTrendingTracks($limit);
        }
        
        // Generate recommendations using hybrid approach
        $recommendations = $this->generateHybridRecommendations($userTracks, $candidateTracks, $limit);
        
        // Log these recommendations
        $this->logRecommendations($userId, $recommendations);
        
        return $recommendations;
    }
    
    private function getUserTracksWithFeatures($userId) {
        $stmt = $this->db->prepare("
            SELECT t.*, tf.*, COUNT(ulh.id) as play_count
            FROM user_listening_history ulh
            JOIN tracks t ON ulh.track_id = t.id
            LEFT JOIN track_features tf ON t.id = tf.track_id
            WHERE ulh.user_id = ?
            GROUP BY t.id
            ORDER BY COUNT(ulh.id) DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    private function getCandidateTracks($userId, $limit) {
        $stmt = $this->db->prepare("
            SELECT t.*, tf.*
            FROM tracks t
            LEFT JOIN track_features tf ON t.id = tf.track_id
            WHERE t.id NOT IN (
                SELECT track_id FROM user_listening_history WHERE user_id = ?
            )
            AND t.popularity > 50
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    private function getTrendingTracks($limit) {
        $stmt = $this->db->prepare("
            SELECT t.*
            FROM trending_tracks tt
            JOIN tracks t ON tt.track_id = t.id
            ORDER BY tt.trend_score DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    private function generateHybridRecommendations($userTracks, $candidateTracks, $limit) {
        // Calculate average features of user's top tracks
        $avgFeatures = $this->calculateAverageFeatures($userTracks);
        
        // Score each candidate track based on similarity to user's preferences
        $scoredTracks = [];
        foreach ($candidateTracks as $track) {
            if (!empty($track['danceability'])) { // Ensure features exist
                $score = $this->calculateTrackScore($track, $avgFeatures);
                $scoredTracks[] = [
                    'track' => $track,
                    'score' => $score
                ];
            }
        }
        
        // Sort by score descending
        usort($scoredTracks, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Apply diversity - don't recommend too many similar tracks
        $recommendations = [];
        $selectedArtists = [];
        $selectedGenres = []; // You would need a genre table for this
        
        foreach ($scoredTracks as $scored) {
            $track = $scored['track'];
            
            // Ensure artist diversity
            if (count($recommendations) >= $limit) break;
            if (in_array($track['artist'], $selectedArtists)) continue;
            
            $recommendations[] = $track;
            $selectedArtists[] = $track['artist'];
        }
        
        // If we didn't get enough recommendations, fill with trending
        if (count($recommendations) < $limit) {
            $needed = $limit - count($recommendations);
            $trending = $this->getTrendingTracks($needed);
            $recommendations = array_merge($recommendations, $trending);
        }
        
        return array_slice($recommendations, 0, $limit);
    }
    
    private function calculateAverageFeatures($tracks) {
        $features = [
            'danceability' => 0,
            'energy' => 0,
            'valence' => 0,
            'acousticness' => 0,
            'instrumentalness' => 0,
            'liveness' => 0,
            'speechiness' => 0
        ];
        
        $count = 0;
        $totalPlays = 0;
        
        // Calculate weighted average based on play count
        foreach ($tracks as $track) {
            if (!empty($track['danceability'])) {
                $plays = $track['play_count'] ?? 1;
                $totalPlays += $plays;
                
                foreach ($features as $key => $value) {
                    $features[$key] += $track[$key] * $plays;
                }
                $count++;
            }
        }
        
        if ($count === 0) return null;
        
        // Normalize
        foreach ($features as $key => $value) {
            $features[$key] = $value / $totalPlays;
        }
        
        return $features;
    }
    
    private function calculateTrackScore($track, $avgFeatures) {
        // Simple similarity score - could be replaced with more sophisticated algorithm
        $score = 0;
        
        foreach ($avgFeatures as $key => $value) {
            $score += 1 - abs($value - $track[$key]);
        }
        
        // Add popularity boost
        $popularityBoost = $track['popularity'] / 100;
        $score += $popularityBoost * 0.5;
        
        return $score;
    }
    
    private function logRecommendations($userId, $recommendations) {
        $modelVersion = $this->getCurrentModelVersion();
        
        $this->db->beginTransaction();
        try {
            // Log the recommendation batch
            $stmt = $this->db->prepare("
                INSERT INTO ai_recommendation_logs 
                (user_id, model_version_id, recommendation_count, generated_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $modelVersion['id'], count($recommendations)]);
            $batchId = $this->db->lastInsertId();
            
            // Log individual recommendations
            foreach ($recommendations as $track) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_recommendations 
                    (user_id, track_id, recommended_at, score, source)
                    VALUES (?, ?, NOW(), ?, 'ai_model')
                ");
                $score = $this->calculateTrackScore($track, $this->calculateAverageFeatures([$track]));
                $stmt->execute([$userId, $track['id'], $score]);
            }
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to log recommendations: " . $e->getMessage());
        }
    }
    
    private function getCurrentModelVersion() {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_model_versions
            WHERE deployed_at IS NOT NULL
            ORDER BY deployed_at DESC
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch() ?? ['id' => 1]; // Default if none deployed
    }
    
    public function trainModel() {
        // This would typically be a separate Python script, but here's the PHP orchestration
        
        // 1. Gather training data
        $trainingData = $this->prepareTrainingData();
        
        // 2. Save to file for Python to process
        $filename = __DIR__ . '/../../scripts/ai/training_data_' . date('Ymd_His') . '.json';
        file_put_contents($filename, json_encode($trainingData));
        
        // 3. Execute Python training script
        $scriptPath = __DIR__ . '/../../scripts/ai/train_model.py';
        $command = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($filename);
        $output = shell_exec($command);
        
        // 4. Process results
        $results = json_decode($output, true);
        if ($results && $results['success']) {
            $this->saveModelVersion($results);
            return true;
        }
        
        throw new Exception("Model training failed: " . ($results['error'] ?? 'Unknown error'));
    }
    
    private function prepareTrainingData() {
        // Get user listening history with features
        $stmt = $this->db->prepare("
            SELECT ulh.user_id, t.id as track_id, tf.*, COUNT(ulh.id) as play_count
            FROM user_listening_history ulh
            JOIN tracks t ON ulh.track_id = t.id
            JOIN track_features tf ON t.id = tf.track_id
            GROUP BY ulh.user_id, t.id
            HAVING play_count > 3
        ");
        $stmt->execute();
        $userTracks = $stmt->fetchAll();
        
        // Group by user
        $data = [];
        foreach ($userTracks as $row) {
            $userId = $row['user_id'];
            if (!isset($data[$userId])) {
                $data[$userId] = [];
            }
            
            $data[$userId][] = [
                'track_id' => $row['track_id'],
                'features' => [
                    'danceability' => $row['danceability'],
                    'energy' => $row['energy'],
                    'valence' => $row['valence'],
                    'acousticness' => $row['acousticness'],
                    'instrumentalness' => $row['instrumentalness'],
                    'liveness' => $row['liveness'],
                    'speechiness' => $row['speechiness']
                ],
                'play_count' => $row['play_count']
            ];
        }
        
        return $data;
    }
    
    private function saveModelVersion($results) {
        $stmt = $this->db->prepare("
            INSERT INTO ai_model_versions 
            (version_name, description, algorithm, training_data_range_start, 
             training_data_range_end, accuracy_score, model_path, deployed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $results['version_name'],
            $results['description'],
            $results['algorithm'],
            $results['training_start'],
            $results['training_end'],
            $results['accuracy'],
            $results['model_path']
        ]);
    }
    
    public function recordRecommendationFeedback($userId, $trackId, $feedback) {
        // feedback can be 'click', 'skip', 'like', 'save', etc.
        $validFeedback = ['click', 'skip', 'like', 'save', 'play'];
        
        if (!in_array($feedback, $validFeedback)) {
            throw new Exception("Invalid feedback type");
        }
        
        // Update the recommendation record
        $stmt = $this->db->prepare("
            UPDATE user_recommendations
            SET 
                viewed = CASE WHEN ? != 'skip' THEN TRUE ELSE viewed END,
                clicked = CASE WHEN ? = 'click' THEN TRUE ELSE clicked END,
                feedback = ?
            WHERE user_id = ? AND track_id = ?
            ORDER BY recommended_at DESC
            LIMIT 1
        ");
        return $stmt->execute([$feedback, $feedback, $feedback, $userId, $trackId]);
    }
}
?>