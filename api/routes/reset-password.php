<?php
header("Content-Type: application/json");
require_once './config.php';
require '../vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper function to send reset email
function sendResetEmail($email, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings (configure with your SMTP details)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'malikzain909192@gmail.com';
        $mail->Password   = 'acup gfdn rebi jxnt'; // Use app password for Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('no-reply@yourdomain.com', 'EAS');
        $mail->addAddress($email);

        // Content
        $resetLink = "http://localhost/EAS/html/reset-password.html?token=$token";
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body    = "
            <h2>Password Reset</h2>
            <p>You requested to reset your password. Click the link below:</p>
            <p><a href='$resetLink' style='padding:10px;background:#2563eb;color:white;text-decoration:none;border-radius:5px;'>Reset Password</a></p>
            <p>This link expires in 1 hour.</p>
            <p>If you didn't request this, please ignore this email.</p>
        <br>
        <h2>Thank You! </h2>
            ";
        $mail->AltBody = "Reset your password: $resetLink (expires in 1 hour)";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'forgot_password') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $role = $input['role'] ?? '';

        // Validate input
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Valid email is required']);
            exit;
        }

        $validRoles = ['member', 'employee', 'admin'];
        if (empty($role) || !in_array($role, $validRoles)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Valid role is required']);
            exit;
        }

        // Check if email exists
        $table = $role;
        $emailColumn = ($role === 'admin') ? 'Email' : 'email';
        
        $stmt = $conn->prepare("SELECT $emailColumn FROM $table WHERE $emailColumn = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        // Always return success to prevent email enumeration
        if ($result->num_rows === 0) {
            // Log failed attempt without revealing to user
            error_log("Password reset attempt for non-existent email: $email");
            echo json_encode([
                'status' => 'success',
                'message' => 'If the email exists, a reset link has been sent'
            ]);
            exit;
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Delete any existing tokens for this email
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();

        // Store new token
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, role, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $email, $token, $role, $expires);
        $stmt->execute();

        // Send email (even if we don't reveal whether email exists)
        if (sendResetEmail($email, $token)) {
            echo json_encode([
                'status' => 'success',
                'message' => 'If the email exists, a reset link has been sent'
            ]);
        } else {
            throw new Exception("Failed to send email");
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again later.']);
    }
    exit;
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'reset_password') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';
        $newPassword = $input['newPassword'] ?? '';
        $confirmPassword = $input['confirmPassword'] ?? '';

        // Validate input
        if (empty($token) || strlen($token) !== 64) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            exit;
        }

        if (empty($newPassword) || strlen($newPassword) < 8) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
            exit;
        }

        // Verify token
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at < NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
            exit;
        }

        $resetRequest = $result->fetch_assoc();
        $email = $resetRequest['email'];
        $role = $resetRequest['role'];

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        if ($hashedPassword === false) {
            throw new Exception("Failed to hash password");
        }

        // Update password in appropriate table
        $table = $role;
        $emailColumn = ($role === 'admin') ? 'Email' : 'email';
        
        $stmt = $conn->prepare("UPDATE $table SET Password = ? WHERE $emailColumn = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update password");
        }

        // Clean up used token
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();

        // Invalidate all sessions for this user (optional)
        // session_start();
        // session_destroy();

        echo json_encode([
            'status' => 'success',
            'message' => 'Password updated successfully'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
?>