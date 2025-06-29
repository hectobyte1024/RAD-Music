<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/classes/News.php';
require_once __DIR__ . '/../app/classes/Charts.php';
require_once __DIR__ . '/../app/classes/RecommendationEngine.php';
require_once __DIR__ . '/../includes/auth_functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'import-billboard':
                        $chartType = $data['chartType'] ?? 'hot-100';
                        $count = (new Charts())->importBillboardChart($chartType);
                        $response = [
                            'success' => true,
                            'message' => "Imported $count entries from Billboard $chartType chart"
                        ];
                        break;
                        
                    case 'import-news':
                        $count = (new News())->importFromBillboard();
                        $response = [
                            'success' => true,
                            'message' => "Imported $count news items from Billboard"
                        ];
                        break;
                        
                    case 'train-model':
                        $engine = new RecommendationEngine();
                        if ($engine->trainModel()) {
                            $response = [
                                'success' => true,
                                'message' => 'AI model trained successfully'
                            ];
                        } else {
                            throw new Exception("Model training failed");
                        }
                        break;
                        
                    case 'update-featured':
                        if (empty($data['newsId']) || !isset($data['featured'])) {
                            throw new Exception("News ID and featured status required");
                        }
                        
                        $news = new News();
                        $news->updateFeaturedStatus($data['newsId'], $data['featured']);
                        $response = [
                            'success' => true,
                            'message' => 'Featured status updated'
                        ];
                        break;
                        
                    default:
                        throw new Exception("Invalid action");
                }
            } else {
                throw new Exception("No action specified");
            }
            break;
            
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'stats':
                        $db = Database::getInstance();
                        
                        // User stats
                        $stmt = $db->query("SELECT COUNT(*) as user_count FROM users");
                        $userCount = $stmt->fetch()['user_count'];
                        
                        // Post stats
                        $stmt = $db->query("SELECT COUNT(*) as post_count FROM posts");
                        $postCount = $stmt->fetch()['post_count'];
                        
                        // Activity stats
                        $stmt = $db->query("
                            SELECT activity_type, COUNT(*) as count 
                            FROM user_activity 
                            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            GROUP BY activity_type
                        ");
                        $activityStats = $stmt->fetchAll();
                        
                        $response = [
                            'success' => true,
                            'stats' => [
                                'users' => $userCount,
                                'posts' => $postCount,
                                'activity' => $activityStats
                            ]
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

function isAdmin() {
    // Implement your admin check logic here
    // This might check a database field or session variable
    return $_SESSION['user_id'] == 1; // Example: user ID 1 is admin
}
?>