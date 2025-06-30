<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/classes/RecommendationEngine.php';
require_once __DIR__ . '/../app/classes/Charts.php';
require_once __DIR__ . '/../includes/auth_functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'recommendations':
                        if (!$userId) {
                            throw new Exception("Login required for recommendations");
                        }
                        
                        $limit = $_GET['limit'] ?? 10;
                        $recommendations = (new RecommendationEngine())->getForUser($userId, $limit);
                        $response = [
                            'success' => true,
                            'recommendations' => $recommendations
                        ];
                        break;
                        
                    case 'charts':
                        $chartName = $_GET['chart'] ?? 'hot-100';
                        $limit = $_GET['limit'] ?? 10;
                        
                        $charts = (new Charts())->getCurrentTop($chartName, $limit);
                        $response = [
                            'success' => true,
                            'chart' => $charts
                        ];
                        break;
                        
                    case 'track-history':
                        if (!$userId) {
                            throw new Exception("Login required");
                        }
                        
                        if (empty($_GET['trackId'])) {
                            throw new Exception("Track ID required");
                        }
                        
                        $history = (new Charts())->getChartHistory($_GET['trackId']);
                        $response = [
                            'success' => true,
                            'history' => $history
                        ];
                        break;
                        
                    case 'search':
                        if (empty($_GET['query'])) {
                            throw new Exception("Search query required");
                        }
                        
                        $limit = $_GET['limit'] ?? 10;
                        $tracks = (new RecommendationEngine())->searchTracks($_GET['query'], $limit);
                        $response = [
                            'success' => true,
                            'results' => $tracks
                        ];
                        break;
                        
                    case 'top-artists':
                        $limit = $_GET['limit'] ?? 10;
                        $timeRange = $_GET['range'] ?? 'month';
                        $artists = (new Charts())->getTopArtists($limit, $timeRange);
                        $response = [
                            'success' => true,
                            'artists' => $artists
                        ];
                        break;
                        
                    case 'trending':
                        $limit = $_GET['limit'] ?? 10;
                        $trending = (new RecommendationEngine())->getTrendingTracks($limit);
                        $response = [
                            'success' => true,
                            'tracks' => $trending
                        ];
                        break;
                        
                    default:
                        throw new Exception("Invalid action");
                }
            } else {
                throw new Exception("No action specified");
            }
            break;
            
        case 'POST':
            if (!$userId) {
                throw new Exception("Login required");
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'record-play':
                        if (empty($data['trackId'])) {
                            throw new Exception("Track ID required");
                        }
                        
                        $source = $data['source'] ?? 'user';
                        $engine = new RecommendationEngine();
                        $engine->recordPlay($userId, $data['trackId'], $source);
                        
                        $response = [
                            'success' => true,
                            'message' => 'Play recorded'
                        ];
                        break;
                        
                    case 'feedback':
                        if (empty($data['trackId']) || empty($data['feedback'])) {
                            throw new Exception("Track ID and feedback required");
                        }
                        
                        $engine = new RecommendationEngine();
                        $engine->recordRecommendationFeedback($userId, $data['trackId'], $data['feedback']);
                        
                        $response = [
                            'success' => true,
                            'message' => 'Feedback recorded'
                        ];
                        break;
                        
                    default:
                        throw new Exception("Invalid action");
                }
            } else {
                throw new Exception("No action specified");
            }
            break;
            
        default:
            throw new Exception("Method not allowed");
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
?>