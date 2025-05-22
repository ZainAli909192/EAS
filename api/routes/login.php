<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
require_once './config.php';
require '../vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_current_user') {
    session_start();
    try {
        $response = [
            'status' => 'success',
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['name'] ?? '',
                'role' => $_SESSION['role'],
                'member_id' => $_SESSION['member_id'] ?? null
            ]
        ];
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'contact') {
    try {
        
        $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode(["status" => "debug", "message" => "Input read"]);
        
        // Validate input
        if (empty($input['name']) || empty($input['email']) || empty($input['message'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            exit;
        }

        // Sanitize inputs
        $name = filter_var($input['name'], FILTER_SANITIZE_STRING);
        $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
        $message = filter_var($input['message'], FILTER_SANITIZE_STRING);
        $number = filter_var($input['number'], FILTER_SANITIZE_STRING);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            exit;
        }

        // Load PHPMailer
        require '../vendor/autoload.php';
        $mail = new PHPMailer(true);

        // Server settings (configure with your SMTP details)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'malikzain909192@gmail.com';
        $mail->Password   = 'flbb idmt kfet lqxp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom($email, $name);
        $mail->addAddress('malikzain909192@gmail.com', 'Support Team'); // Your contact email
        // $mail->addAddress('no-reply@gmail.com', 'Support Team'); // Your contact email
        
      // Content
    $mail->isHTML(true);
    $mail->Subject = 'New Contact Form Submission';
    $mail->Body = "
        <h2>New Contact Message</h2>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Message:</strong> $message</p>
        <p><strong>Number:</strong> $number</p>
        <h2>Thank you! :)</h2>
    ";
    $mail->AltBody = "Name: $name\nEmail: $email\nMessage: $message\nNumber: $number";

    $mail->send();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Your message has been sent successfully. We will contact you as soon as possible...'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send message. Please try again later.'.$e
        ]);
        error_log("Contact form error: " . $e->getMessage());
    }
    exit;
}

else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    // echo json_encode(["status" => "debug", "message" => "Input read"]);
    // exit;
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
                    if (password_verify($password, $user['Password'])) {  // SECURE CHECK
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
                }
                break;

            case 'member':
                $stmt = $conn->prepare("SELECT * FROM member WHERE Member_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['Password'])) {  // SECURE CHECK
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
                    if (password_verify($password, $user['Password'])) {  // SECURE CHECK
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
}

else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>