<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/classes/News.php';
require_once __DIR__ . '/../includes/auth_functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $news = new News();
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single news item
                $newsItem = $news->getById($_GET['id'], $userId);
                if ($newsItem) {
                    $response = [
                        'success' => true,
                        'news' => $newsItem
                    ];
                } else {
                    throw new Exception("News item not found");
                }
            } else if (isset($_GET['category'])) {
                // Get news by category
                $limit = $_GET['limit'] ?? 10;
                $offset = $_GET['offset'] ?? 0;
                
                $newsItems = $news->getByCategory($_GET['category'], $limit, $offset);
                $response = [
                    'success' => true,
                    'news' => $newsItems,
                    'hasMore' => count($news->getByCategory($_GET['category'], $limit, $offset + $limit)) > 0
                ];
            } else {
                // Get trending news
                $limit = $_GET['limit'] ?? 5;
                $response = [
                    'success' => true,
                    'news' => $news->getTrending($limit)
                ];
            }
            break;
            
        case 'POST':
            if (!$userId) {
                throw new Exception("Login required");
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'like':
                        if (empty($data['newsId'])) {
                            throw new Exception("News ID required");
                        }
                        
                        $result = $news->likeNews($userId, $data['newsId']);
                        $response = [
                            'success' => true,
                            'action' => $result['action'],
                            'likeCount' => $result['like_count']
                        ];
                        break;
                        
                    case 'search':
                        if (empty($data['query'])) {
                            throw new Exception("Search query required");
                        }
                        
                        $limit = $_GET['limit'] ?? 10;
                        $results = $news->search($data['query'], $limit);
                        $response = [
                            'success' => true,
                            'results' => $results
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