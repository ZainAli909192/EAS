<?php
header("Content-Type: application/json");
require_once './config.php';
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Get profile data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'];
        $profileData = [];

        switch ($role) {
            case 'admin':
                $stmt = $conn->prepare("SELECT Admin_id as id, Email, '' as Number FROM admin WHERE Admin_id = ?");
                break;
            case 'member':
                $stmt = $conn->prepare("SELECT Member_id as id, Company_Name as Name, Email, Number FROM member WHERE Member_id = ?");
                break;
            case 'employee':
                $stmt = $conn->prepare("SELECT employee_id as id, Name, Number , job_in_time, job_out_time ,email FROM employee WHERE employee_id = ?");
                break;
            default:
                throw new Exception("Invalid user role");
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profileData = $result->fetch_assoc();
        $profileData['role'] = $role;

        echo json_encode(['status' => 'success', 'profile' => $profileData]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Update profile data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start session and get user info
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'];
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate JSON input
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON input");
        }

        switch ($role) {
            case 'admin':
                if (!isset($data['email']) || !isset($data['password'])) {
                    throw new Exception("Missing required fields");
                }
                
                // Hash the password
                $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
                if ($hashedPassword === false) {
                    throw new Exception("Failed to hash password");
                }
                
                $stmt = $conn->prepare("UPDATE admin SET Email = ?, Password = ? WHERE Admin_id = ?");
                $stmt->bind_param("ssi", $data['email'], $hashedPassword, $userId);
                break;
                
            case 'member':
                if (!isset($data['company_name']) || !isset($data['email']) || 
                    !isset($data['password']) || !isset($data['number'])) {
                    throw new Exception("Missing required fields");
                }
                
                // Hash the password
                $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
                if ($hashedPassword === false) {
                    throw new Exception("Failed to hash password");
                }
                
                $stmt = $conn->prepare("UPDATE member SET Company_Name = ?, Email = ?, Password = ?, Number = ? WHERE Member_id = ?");
                $stmt->bind_param("ssssi", $data['company_name'], $data['email'], $hashedPassword, $data['number'], $userId);
                break;
                
            case 'employee':
                if (!isset($data['password']) || !isset($data['number'])) {
                    throw new Exception("Missing required fields");
                }
                
                // Hash the password
                $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
                if ($hashedPassword === false) {
                    throw new Exception("Failed to hash password");
                }
                
                $stmt = $conn->prepare("UPDATE employee SET Password = ?, Number = ? , email=? WHERE employee_id = ?");
                $stmt->bind_param("sssi", $hashedPassword, $data['number'], $data['email'],$userId);
                break;
                
            default:
                throw new Exception("Invalid user role");
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
        } else {
            throw new Exception("Database error: " . $stmt->error);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
    exit;
}

$conn->close();
?>