<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/classes/User.php';
require_once __DIR__ . '/../includes/auth_functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $user = new User();
    
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'register':
                        // Validate input
                        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
                            throw new Exception("All fields are required");
                        }
                        
                        $userId = $user->register(
                            trim($data['username']),
                            trim($data['email']),
                            $data['password']
                        );
                        
                        $response = [
                            'success' => true,
                            'message' => 'Registration successful',
                            'userId' => $userId
                        ];
                        break;
                        
                    case 'login':
                        if (empty($data['email']) || empty($data['password'])) {
                            throw new Exception("Email and password are required");
                        }
                        
                        if ($user->login($data['email'], $data['password'])) {
                            $response = [
                                'success' => true,
                                'message' => 'Login successful',
                                'user' => [
                                    'id' => $_SESSION['user_id'],
                                    'username' => $_SESSION['username'],
                                    'email' => $_SESSION['user_email'],
                                    'avatar' => $_SESSION['avatar_url']
                                ]
                            ];
                        } else {
                            throw new Exception("Invalid email or password");
                        }
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
                    case 'check':
                        $response = [
                            'success' => isLoggedIn(),
                            'isLoggedIn' => isLoggedIn(),
                            'user' => isLoggedIn() ? [
                                'id' => $_SESSION['user_id'],
                                'username' => $_SESSION['username'],
                                'email' => $_SESSION['user_email'],
                                'avatar' => $_SESSION['avatar_url']
                            ] : null
                        ];
                        break;
                        
                    case 'logout':
                        session_destroy();
                        $response = [
                            'success' => true,
                            'message' => 'Logged out successfully'
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