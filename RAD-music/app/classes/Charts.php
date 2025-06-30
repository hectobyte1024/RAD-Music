<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

class Charts {
    private $db;
    
    // Add this to your Charts class constructor
    public function __construct() {
        $this->db = Database::getInstance();
        
        // Send favicon headers if requesting icon
        if (strpos($_SERVER['REQUEST_URI'], 'favicon.ico') !== false) {
            header('Content-Type: image/x-icon');
            exit(base64_decode('AAABAAEAEBAAAAEAIABoBAAAFgAAACgAAAAQAAAAIAAAAAEAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA...')); // Truncated base64
        }
    }
    
    public function getCurrentTop($chartName = 'hot-100', $limit = 10) {
        // Get the most recent chart
        $chartId = $this->getLatestChartId($chartName);
        
        if (!$chartId) {
            return [];
        }
        
        return $this->getChartEntries($chartId, $limit);
    }
    
    public function getChartHistory($trackId, $chartName = 'hot-100', $weeks = 12) {
        $stmt = $this->db->prepare("
            SELECT c.chart_date, ce.position, ce.weeks_on_chart, ce.peak_position
            FROM chart_entries ce
            JOIN charts c ON ce.chart_id = c.id
            WHERE ce.track_id = ? AND c.chart_name = ?
            ORDER BY c.chart_date DESC
            LIMIT ?
        ");
        $stmt->execute([$trackId, $chartName, $weeks]);
        return $stmt->fetchAll();
    }
    
    public function importBillboardChart($chartName = 'hot-100') {
        $chartData = $this->scrapeBillboardChart($chartName);
        
        if (empty($chartData)) {
            throw new Exception("Failed to fetch chart data");
        }
        
        // Check if we already have this chart date
        $stmt = $this->db->prepare("
            SELECT 1 FROM charts 
            WHERE chart_name = ? AND chart_date = ?
        ");
        $stmt->execute([$chartName, $chartData['date']]);
        
        if ($stmt->fetch()) {
            return false; // Already exists
        }
        
        $this->db->beginTransaction();
        try {
            // Create the chart record
            $stmt = $this->db->prepare("
                INSERT INTO charts (chart_name, source, chart_date)
                VALUES (?, 'Billboard', ?)
            ");
            $stmt->execute([$chartName, $chartData['date']]);
            $chartId = $this->db->lastInsertId();
            
            // Add chart entries
            foreach ($chartData['entries'] as $entry) {
                // Ensure track exists
                $this->ensureTrackExists($entry['track']);
                
                // Add chart entry
                $stmt = $this->db->prepare("
                    INSERT INTO chart_entries 
                    (chart_id, track_id, position, previous_position, weeks_on_chart, peak_position)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $chartId,
                    $entry['track']['id'],
                    $entry['position'],
                    $entry['last_position'],
                    $entry['weeks_on_chart'],
                    $entry['peak_position']
                ]);
                
                // Update trending tracks
                $this->updateTrendingTrack($entry['track']['id'], $entry['position'], $entry['last_position']);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function getLatestChartId($chartName) {
        $stmt = $this->db->prepare("
            SELECT id FROM charts 
            WHERE chart_name = ? 
            ORDER BY chart_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$chartName]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    private function getChartEntries($chartId, $limit) {
        $stmt = $this->db->prepare("
            SELECT ce.position, t.*, 
                   ce.weeks_on_chart, ce.peak_position,
                   (SELECT position FROM chart_entries WHERE chart_id = ? AND track_id = t.id) as last_position
            FROM chart_entries ce
            JOIN tracks t ON ce.track_id = t.id
            WHERE ce.chart_id = ?
            ORDER BY ce.position
            LIMIT ?
        ");
        $stmt->execute([$chartId - 1, $chartId, $limit]);
        return $stmt->fetchAll();
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
        }
    }
    
    private function updateTrendingTrack($trackId, $currentPosition, $lastPosition) {
        // Calculate trend direction and score
        $direction = 'stable';
        $score = 0;
        
        if ($lastPosition) {
            if ($currentPosition < $lastPosition) {
                $direction = 'up';
                $score = ($lastPosition - $currentPosition) * 10;
            } elseif ($currentPosition > $lastPosition) {
                $direction = 'down';
                $score = ($currentPosition - $lastPosition) * 5;
            }
        }
        
        // Update or insert
        $stmt = $this->db->prepare("
            INSERT INTO trending_tracks (track_id, trend_score, trend_direction)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                trend_score = VALUES(trend_score),
                trend_direction = VALUES(trend_direction),
                last_updated = NOW()
        ");
        $stmt->execute([$trackId, $score, $direction]);
    }
    
    
    public function getTopArtists($limit = 10, $timeRange = 'month') {
        $validRanges = ['week', 'month', 'year', 'all'];
        if (!in_array($timeRange, $validRanges)) {
            $timeRange = 'month';
        }
        
        $dateCondition = '';
        switch ($timeRange) {
            case 'week': $dateCondition = "AND c.chart_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"; break;
            case 'month': $dateCondition = "AND c.chart_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"; break;
            case 'year': $dateCondition = "AND c.chart_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; break;
            case 'all': $dateCondition = ""; break;
        }
        
        $stmt = $this->db->prepare("
            SELECT t.artist, 
                   COUNT(*) as chart_appearances,
                   MIN(ce.position) as peak_position,
                   AVG(ce.position) as avg_position
            FROM chart_entries ce
            JOIN tracks t ON ce.track_id = t.id
            JOIN charts c ON ce.chart_id = c.id
            WHERE c.chart_name = 'hot-100'
            $dateCondition
            GROUP BY t.artist
            ORDER BY chart_appearances DESC, avg_position ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function getChartMovement($chartName = 'hot-100', $limit = 10) {
        $currentChartId = $this->getLatestChartId($chartName);
        $previousChartId = $currentChartId ? $this->getPreviousChartId($currentChartId, $chartName) : null;
        
        if (!$currentChartId || !$previousChartId) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                curr.position as current_position,
                prev.position as previous_position,
                (prev.position - curr.position) as position_change,
                t.*
            FROM chart_entries curr
            JOIN chart_entries prev ON curr.track_id = prev.track_id
            JOIN tracks t ON curr.track_id = t.id
            WHERE curr.chart_id = ? AND prev.chart_id = ?
            ORDER BY ABS(prev.position - curr.position) DESC
            LIMIT ?
        ");
        $stmt->execute([$currentChartId, $previousChartId, $limit]);
        return $stmt->fetchAll();
    }
    
    private function getPreviousChartId($currentChartId, $chartName) {
        $stmt = $this->db->prepare("
            SELECT id FROM charts 
            WHERE chart_name = ? AND id < ?
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$chartName, $currentChartId]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
}
?>