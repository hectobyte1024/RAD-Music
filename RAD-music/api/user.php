<?php
require_once __DIR__ . '/../config/database.php';
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
    $user = new User();
    $userId = $_SESSION['user_id'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $profileId = $_GET['id'];
                $response = [
                    'success' => true,
                    'profile' => $user->getProfile($profileId),
                    'isFollowing' => $user->isFollowing($userId, $profileId)
                ];
            } else {
                // Get current user's profile
                $response = [
                    'success' => true,
                    'profile' => $user->getProfile($userId)
                ];
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'update':
                        $updated = $user->updateProfile($userId, [
                            'username' => $data['username'] ?? null,
                            'bio' => $data['bio'] ?? null,
                            'location' => $data['location'] ?? null
                        ]);
                        
                        if ($updated) {
                            $_SESSION['username'] = $data['username'] ?? $_SESSION['username'];
                            $response = [
                                'success' => true,
                                'message' => 'Profile updated',
                                'profile' => $user->getProfile($userId)
                            ];
                        } else {
                            throw new Exception("No changes made");
                        }
                        break;
                        
                    case 'follow':
                        if (empty($data['userId'])) {
                            throw new Exception("User ID required");
                        }
                        
                        $action = $user->followUser($userId, $data['userId']);
                        $response = [
                            'success' => true,
                            'action' => $action,
                            'followers' => $user->getFollowerCount($data['userId'])
                        ];
                        break;
                        
                    case 'upload-avatar':
                        if (empty($_FILES['avatar'])) {
                            throw new Exception("No file uploaded");
                        }
                        
                        $filename = $user->updateAvatar($userId, $_FILES['avatar']);
                        $_SESSION['avatar_url'] = $filename;
                        $response = [
                            'success' => true,
                            'message' => 'Avatar updated',
                            'avatarUrl' => $filename
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
            // Handle account deletion
            if ($user->deleteAccount($userId)) {
                session_destroy();
                $response = [
                    'success' => true,
                    'message' => 'Account deleted'
                ];
            } else {
                throw new Exception("Failed to delete account");
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