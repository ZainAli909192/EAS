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

try {
    $role = $_SESSION['role'];
    $employeeId = $_SESSION['user_id'];
    $memberId = $_SESSION['member_id'];

    $departmentId = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;

    $attendance = [];

//     if ($role === "member" && $departmentId !== null) {
//         // For regular member
//         $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type 
//         FROM attendance a
//         JOIN employee e ON a.Employee_id = e.Employee_id 
//         WHERE e.Department_id = ?
//         ORDER BY a.Date DESC, a.Time_in DESC
//         LIMIT 50");
// $stmt->bind_param("i", $departmentId);

//     } else {
        // For admin/manager
        if ($departmentId && $date && $status) {
            $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type , a.clock_in_location, a.clock_out_location
                                    FROM attendance a
                                    JOIN employee e ON a.Employee_id = e.Employee_id 
                                    WHERE e.Department_id = ? AND a.Date = ? AND a.Attendance_type = ?
                                    ORDER BY a.Date DESC, a.Time_in DESC
                                    LIMIT 50");
            $stmt->bind_param("iss", $departmentId, $date, $status);
        }elseif ($date && $status ) {
            $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type , a.clock_in_location, a.clock_out_location
                                    FROM attendance a
                                    JOIN employee e ON a.Employee_id = e.Employee_id 
                                    WHERE  a.Date = ? AND a.Attendance_type = ?
                                    ORDER BY a.Date DESC, a.Time_in DESC
                                    LIMIT 50");
            $stmt->bind_param("ss", $date, $status);
        } elseif ($status && $departmentId) {
            $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type , a.clock_in_location, a.clock_out_location
                                    FROM attendance a
                                    JOIN employee e ON a.Employee_id = e.Employee_id 
                                    WHERE a.Attendance_type = ? AND e.Department_id = ?
                                    ORDER BY a.Date DESC, a.Time_in DESC
                                    LIMIT 50");
            $stmt->bind_param("si", $status, $departmentId);
        } elseif ($departmentId) {
            $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type , a.clock_in_location, a.clock_out_location
                                    FROM attendance a
                                    JOIN employee e ON a.Employee_id = e.Employee_id 
                                    WHERE e.Department_id = ?
                                    ORDER BY a.Date DESC, a.Time_in DESC
                                    LIMIT 50");
            $stmt->bind_param("i", $departmentId);
        
        } elseif ($status) {
            $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type , a.clock_in_location, a.clock_out_location
                                    FROM attendance a
                                    JOIN employee e ON a.Employee_id = e.Employee_id 
                                    WHERE a.status = ?
                                    ORDER BY a.Date DESC, a.Time_in DESC
                                    LIMIT 50");
            $stmt->bind_param("s", $status);
        
        } elseif ($date) {
            $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type , a.clock_in_location, a.clock_out_location
                                    FROM attendance a
                                    JOIN employee e ON a.Employee_id = e.Employee_id 
                                    WHERE a.Date = ?
                                    ORDER BY a.Date DESC, a.Time_in DESC
                                    LIMIT 50");
            $stmt->bind_param("s", $date);
        } elseif ($date && $departmentId) {
            $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type , a.clock_in_location, a.clock_out_location
                                    FROM attendance a
                                    JOIN employee e ON a.Employee_id = e.Employee_id 
                                    WHERE a.Date = ? AND e.Department_id = ?
                                    ORDER BY a.Date DESC, a.Time_in DESC
                                    LIMIT 50");
            $stmt->bind_param("si", $date, $departmentId);
        } elseif ($date && $status) {
            $stmt = $conn->prepare("SELECT a.Attendance_id, a.Date, a.Time_in, a.Time_out, a.Photo, a.Photo_out, a.Attendance_type , a.clock_in_location, a.clock_out_location
                                    FROM attendance a
                                    JOIN employee e ON a.Employee_id = e.Employee_id 
                                    WHERE a.Date = ? AND a.status = ?
                                    ORDER BY a.Date DESC, a.Time_in DESC
                                    LIMIT 50");
            $stmt->bind_param("ss", $date, $status);
        } 
        
        else {
            $stmt = $conn->prepare("SELECT Attendance_id, Date, Time_in, Time_out, Photo, Photo_out, Attendance_type , clock_in_location, clock_out_location
                                    FROM attendance 
                                    ORDER BY Date DESC, Time_in DESC
                                    LIMIT 50");
        }
    // }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $attendance[] = [
            'id' => $row['Attendance_id'],
            'date' => $row['Date'],
            'time_in' => $row['Time_in'],
            'time_out' => $row['Time_out'],
            'photo_in' => $row['Photo'],
            'photo_out' => $row['Photo_out'],
            'attendance_type' => $row['Attendance_type'],  
            'location_in' => $row['clock_in_location'],
            'location_out' => $row['clock_out_location']
        ];
    }

    // Optional: add stats if needed (reuse logic from original script)

    echo json_encode([
        'status' => 'success',
        'attendance' => $attendance
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
