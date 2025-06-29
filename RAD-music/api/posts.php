<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/classes/Post.php';
require_once __DIR__ . '/../app/classes/User.php';
require_once __DIR__ . '/../includes/auth_functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isLoggedIn()) {
    http_response_code(401);
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $post = new Post();
    $user = new User();
    $userId = $_SESSION['user_id'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single post
                $postId = $_GET['id'];
                $response = [
                    'success' => true,
                    'post' => $post->getPostById($postId, $userId)
                ];
            } else {
                // Get feed posts
                $limit = $_GET['limit'] ?? 10;
                $offset = $_GET['offset'] ?? 0;
                
                $response = [
                    'success' => true,
                    'posts' => $post->getFeedPosts($userId, $limit, $offset),
                    'hasMore' => count($post->getFeedPosts($userId, $limit, $offset + $limit)) > 0
                ];
            }
            break;
            
        case 'POST':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'create':
                        $content = $_POST['content'] ?? '';
                        $trackId = $_POST['trackId'] ?? null;
                        $media = $_FILES['media'] ?? [];
                        
                        // Handle multiple file uploads
                        $mediaFiles = [];
                        if (!empty($media['name'])) {
                            if (is_array($media['name'])) {
                                // Multiple files
                                foreach ($media['name'] as $index => $name) {
                                    if ($media['error'][$index] === UPLOAD_ERR_OK) {
                                        $mediaFiles[] = [
                                            'name' => $name,
                                            'type' => $media['type'][$index],
                                            'tmp_name' => $media['tmp_name'][$index],
                                            'error' => $media['error'][$index],
                                            'size' => $media['size'][$index]
                                        ];
                                    }
                                }
                            } else {
                                // Single file
                                if ($media['error'] === UPLOAD_ERR_OK) {
                                    $mediaFiles[] = $media;
                                }
                            }
                        }
                        
                        $postId = $post->create($userId, $content, $trackId, $mediaFiles);
                        $response = [
                            'success' => true,
                            'message' => 'Post created',
                            'post' => $post->getPostById($postId, $userId)
                        ];
                        break;
                        
                    case 'like':
                        if (empty($_POST['postId'])) {
                            throw new Exception("Post ID required");
                        }
                        
                        $result = $post->likePost($userId, $_POST['postId']);
                        $response = [
                            'success' => true,
                            'action' => $result['action'],
                            'likeCount' => $result['like_count']
                        ];
                        break;
                        
                    case 'comment':
                        if (empty($_POST['postId']) || empty($_POST['content'])) {
                            throw new Exception("Post ID and content required");
                        }
                        
                        $comment = $post->addComment($userId, $_POST['postId'], $_POST['content']);
                        $response = [
                            'success' => true,
                            'message' => 'Comment added',
                            'comment' => $comment
                        ];
                        break;
                        
                    default:
                        throw new Exception("Invalid action");
                }
            } else {
                throw new Exception("No action specified");
            }
            break;
            
        case 'DELETE':
            if (empty($_GET['id'])) {
                throw new Exception("Post ID required");
            }
            
            if ($post->deletePost($userId, $_GET['id'])) {
                $response = [
                    'success' => true,
                    'message' => 'Post deleted'
                ];
            } else {
                throw new Exception("Failed to delete post");
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