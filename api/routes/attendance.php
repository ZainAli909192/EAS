<?php
header("Content-Type: application/json");
require_once './config.php';
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $photoPath = null;
        $photoOutPath = null;
        
        if (isset($_FILES['photo'])) {
            $targetDir = "../attendance_pics/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $action = $_POST['action'];
            $fileName = 'attendance_' . $_SESSION['user_id'] . '_' . time() . '.png';
            $targetFile = $targetDir . $fileName;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                if ($action === 'clock_in') {
                    $photoPath = $fileName;
                } else {
                    $photoOutPath = $fileName;
                }
            }
        }

        $action = $_POST['action'];
        $employeeId = $_SESSION['user_id'];
        $date = $_POST['date'] ?? date('Y-m-d');
        $currentTime = date('H:i:s');

        if ($action === 'clock_in') {
            $stmt = $conn->prepare("INSERT INTO attendance (Employee_id, Date, Time_in, Photo, Attendance_type) 
                                   VALUES (?, ?, ?, ?, 'Present')");
            $stmt->bind_param("isss", $employeeId, $date, $currentTime, $photoPath);
        } else {
            // Update with clock-out photo
            $stmt = $conn->prepare("UPDATE attendance SET Time_out = ?, Photo_out = IFNULL(?, Photo_out) 
                                   WHERE Employee_id = ? AND Date = ? AND Time_out IS NULL");
            $stmt->bind_param("ssis", $currentTime, $photoOutPath, $employeeId, $date);
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Attendance recorded']);
        } else {
            throw new Exception("Database error: " . $stmt->error);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $employeeId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT Attendance_id, Date, Time_in, Time_out, Photo, Photo_out, Attendance_type 
                              FROM attendance 
                              WHERE Employee_id = ?
                              ORDER BY Date DESC");
        $stmt->bind_param("i", $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();

        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = [
                'id' => $row['Attendance_id'],
                'date' => $row['Date'],
                'time_in' => $row['Time_in'],
                'time_out' => $row['Time_out'],
                'photo_in' => $row['Photo'],
                'photo_out' => $row['Photo_out'],
                'attendance_type' => $row['Attendance_type']
            ];
        }
// Get statistics (add this before the final echo json_encode in the GET handler)
$stats = [];
$currentMonth = date('m');
$currentYear = date('Y');

// 1. Total Working Days
$stmt = $conn->prepare("SELECT COUNT(*) as total_days FROM attendance 
                       WHERE Employee_id = ? 
                       AND MONTH(Date) = ? 
                       AND YEAR(Date) = ?");
$stmt->bind_param("iii", $employeeId, $currentMonth, $currentYear);
$stmt->execute();
$stats['total_days'] = $stmt->get_result()->fetch_assoc()['total_days'] ?? 0;

// 2. Average Clock-In Time
$stmt = $conn->prepare("SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(Time_in))) as avg_clock_in 
                       FROM attendance 
                       WHERE Employee_id = ? 
                       AND MONTH(Date) = ? 
                       AND YEAR(Date) = ?");
$stmt->bind_param("iii", $employeeId, $currentMonth, $currentYear);
$stmt->execute();
$stats['avg_clock_in'] = $stmt->get_result()->fetch_assoc()['avg_clock_in'] ?? 'N/A';

// 3. Attendance Rate (Present days vs total work days)
$stmt = $conn->prepare("SELECT 
                       COUNT(CASE WHEN Attendance_type = 'Present' THEN 1 END) as present_days,
                       COUNT(*) as total_days
                       FROM attendance 
                       WHERE Employee_id = ? 
                       AND MONTH(Date) = ? 
                       AND YEAR(Date) = ?");
$stmt->bind_param("iii", $employeeId, $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['attendance_rate'] = ($result['total_days'] > 0) ? 
    round(($result['present_days'] / $result['total_days']) * 100) : 0;

// Add stats to the response
$response = [
    'status' => 'success',
    'attendance' => $attendance,
    'stats' => $stats
];

echo json_encode($response);
        // echo json_encode(['status' => 'success', 'attendance' => $attendance]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

$conn->close();
?>