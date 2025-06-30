<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

function getTrendingTracks($limit = 10) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT t.* 
        FROM trending_tracks tt
        JOIN tracks t ON tt.track_id = t.id
        ORDER BY tt.trend_score DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getRecentActivity($userId, $limit = 20) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT ua.*, 
               u.username as actor_username,
               u.avatar_url as actor_avatar
        FROM user_activity ua
        JOIN users u ON ua.user_id = u.id
        WHERE ua.user_id IN (
            SELECT following_id FROM user_followers WHERE follower_id = ?
        ) OR ua.user_id = ?
        ORDER BY ua.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $userId, $limit]);
    return $stmt->fetchAll();
}

function getTrackById($trackId) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT t.*, tf.*
        FROM tracks t
        LEFT JOIN track_features tf ON t.id = tf.track_id
        WHERE t.id = ?
    ");
    $stmt->execute([$trackId]);
    return $stmt->fetch();
}

function getPopularArtists($limit = 10) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT artist, COUNT(*) as track_count
        FROM tracks
        GROUP BY artist
        ORDER BY track_count DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getTrackFeatures($trackId) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT * FROM track_features
        WHERE track_id = ?
    ");
    $stmt->execute([$trackId]);
    return $stmt->fetch();
}
?>