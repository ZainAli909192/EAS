<?php
header("Content-Type: application/json");
require_once './config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    try {
        // Unset all session variables
        $_SESSION = array();
    
        // Destroy the session
        session_destroy();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Logout failed: ' . $e->getMessage()
        ]);
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($input['id']) || empty($input['password']) || empty($input['role'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    $id = $input['id'];
    $password = $input['password'];
    $role = $input['role'];

    try {
        // Start session
        session_start();

        // Check based on role
        switch ($role) {
            case 'employee':
                $stmt = $conn->prepare("SELECT * FROM employee WHERE employee_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if ($password== $user['Password']) {
                    // if (password_verify($password, $dbuser['Password'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['employee_id'];
                        $_SESSION['name'] = $user['Name'];
                        $_SESSION['role'] = 'employee';
                        $_SESSION['member_id'] = $user['Member_id'];
                        
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Login successful',
                            'user' => [
                                'id' => $user['employee_id'],
                                'name' => $user['Name'],
                                'role' => 'employee'
                            ]
                        ]);
                    } else {
                        http_response_code(401);
                        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
                    $_SESSION['member_id'] = $user['Member_id'];
                }
                break;

            case 'member':
                $stmt = $conn->prepare("SELECT * FROM member WHERE Member_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    // if (password_verify($password, $user['Password'])) {
                    if ($password== $user['Password']) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['Member_id'];
                        $_SESSION['name'] = $user['Company_Name'];
                        $_SESSION['role'] = 'member';
                        $_SESSION['member_id'] = $user['Member_id'];
                        
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Login successful',
                            'user' => [
                                'id' => $user['Member_id'],
                                'name' => $user['Company_Name'],
                                'role' => 'member'
                            ]
                        ]);
                    } else {
                        http_response_code(401);
                        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
                }
                break;

            case 'admin':
                $stmt = $conn->prepare("SELECT * FROM admin WHERE Admin_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    // if (password_verify($password, $user['Password'])) {
                    if ($password== $user['Password']) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['Admin_id'];
                        $_SESSION['role'] = 'admin';
                        
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Login successful',
                            'user' => [
                                'id' => $user['Admin_id'],
                                'role' => 'admin'
                            ]
                        ]);
                    } else {
                        http_response_code(401);
                        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid role specified']);
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>