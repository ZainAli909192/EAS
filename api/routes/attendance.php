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
// storing attendance 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get full address from location data
        $fullAddress = isset($_POST['full_address']) ? $_POST['full_address'] : null;
        


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
        $memberId = $_SESSION['member_id'];
        $date = $_POST['date'] ?? date('Y-m-d');
        $currentTime = date('H:i:s');

        if ($action === 'clock_in') {
            $stmt = $conn->prepare("INSERT INTO attendance 
                (Employee_id, Member_id, Date, Time_in, Photo, Attendance_type, clock_in_location) 
                VALUES (?, ?, ?, ?, ?, 'Present', ?)");
            $stmt->bind_param("iissss", 
            // $stmt->bind_param("iisss", 
                $employeeId, $memberId, $date, $currentTime, $photoPath, $fullAddress);
                // $employeeId, $memberId, $date, $currentTime, $photoPath);
        } else {
            // Update with clock-out photo and location data
            $stmt = $conn->prepare("UPDATE attendance
                SET Time_out = ?,
                    Photo_out = IFNULL(?, Photo_out),
                    clock_out_location = IFNULL(?, clock_out_location)
                WHERE Employee_id = ?
                  AND Member_id = ?
                  AND Date = ?
                  AND Time_out IS NULL 
                ORDER BY Time_in DESC
                LIMIT 1");
            $stmt->bind_param("sssiis", 
                $currentTime, $photoOutPath, $fullAddress,
                $employeeId, $memberId, $date);
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

// filters 
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $employeeId = $_SESSION['user_id'];
        $memberId = $_SESSION['member_id'];
        $role = $_SESSION['role'];
        
        // Get filter parameters
        $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
        $rows = isset($_GET['rows']) ? (int)$_GET['rows'] : 50;
        
        // Base query
        $query = "SELECT Attendance_id, Date, Time_in, Time_out, Photo, Photo_out, Attendance_type , clock_in_location, clock_out_location
                 FROM attendance 
                 WHERE ";
        
        // Add role-based condition
        if($role == "member") {
            $query .= "member_id = ?";
            $idParam = $memberId;
        } else {
            $query .= "Employee_id = ?";
            $idParam = $employeeId;
        }
        
        // Add date filters if provided
        $params = [$idParam];
        $types = "i";
        
        if ($month !== null && $year !== null) {
            $query .= " AND MONTH(Date) = ? AND YEAR(Date) = ?";
            $params[] = $month;
            $params[] = $year;
            $types .= "ii";
        } elseif ($year !== null) {
            $query .= " AND YEAR(Date) = ?";
            $params[] = $year;
            $types .= "i";
        }
        
        $query .= " ORDER BY Date DESC, Time_in DESC LIMIT ?";
        $params[] = $rows;
        $types .= "i";
        
        $stmt = $conn->prepare($query);
        
        // Bind parameters
        $stmt->bind_param($types, ...$params);
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
                'attendance_type' => $row['Attendance_type'],
                'clock_in_location' => $row['clock_in_location'],
                'clock_out_location' => $row['clock_out_location']
            ];
        }

        // Update statistics to use filters
        $stats = [];
        $statsMonth = $month ?? date('m');
        $statsYear = $year ?? date('Y');

        // 1. Total Working Days (with filters)
        $totalDaysQuery = "SELECT COUNT(*) as total_days FROM attendance 
                          WHERE " . ($role == "member" ? "Member_id" : "Employee_id") . " = ?";
        $totalParams = [$idParam];
        $totalTypes = "i";
        
        if ($month !== null && $year !== null) {
            $totalDaysQuery .= " AND MONTH(Date) = ? AND YEAR(Date) = ?";
            $totalParams[] = $month;
            $totalParams[] = $year;
            $totalTypes .= "ii";
        } elseif ($year !== null) {
            $totalDaysQuery .= " AND YEAR(Date) = ?";
            $totalParams[] = $year;
            $totalTypes .= "i";
        } else {
            $totalDaysQuery .= " AND MONTH(Date) = ? AND YEAR(Date) = ?";
            $totalParams[] = $statsMonth;
            $totalParams[] = $statsYear;
            $totalTypes .= "ii";
        }
        
        $stmt = $conn->prepare($totalDaysQuery);
        $stmt->bind_param($totalTypes, ...$totalParams);
        $stmt->execute();
        $stats['total_days'] = $stmt->get_result()->fetch_assoc()['total_days'] ?? 0;

        // 2. Average Clock-In Time (with filters)
        $avgClockQuery = "SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(Time_in))) as avg_clock_in 
                         FROM attendance 
                         WHERE " . ($role == "member" ? "member_id" : "Employee_id") . " = ?";
        $avgParams = [$idParam];
        $avgTypes = "i";
        
        if ($month !== null && $year !== null) {
            $avgClockQuery .= " AND MONTH(Date) = ? AND YEAR(Date) = ?";
            $avgParams[] = $month;
            $avgParams[] = $year;
            $avgTypes .= "ii";
        } elseif ($year !== null) {
            $avgClockQuery .= " AND YEAR(Date) = ?";
            $avgParams[] = $year;
            $avgTypes .= "i";
        } else {
            $avgClockQuery .= " AND MONTH(Date) = ? AND YEAR(Date) = ?";
            $avgParams[] = $statsMonth;
            $avgParams[] = $statsYear;
            $avgTypes .= "ii";
        }
        
        $stmt = $conn->prepare($avgClockQuery);
        $stmt->bind_param($avgTypes, ...$avgParams);
        $stmt->execute();
        $stats['avg_clock_in'] = $stmt->get_result()->fetch_assoc()['avg_clock_in'] ?? 'N/A';

        // 3. Attendance Rate (with filters)
        $attendanceRateQuery = "SELECT 
                              COUNT(CASE WHEN Attendance_type = 'Present' THEN 1 END) as present_days,
                              COUNT(*) as total_days
                              FROM attendance 
                              WHERE " . ($role == "member" ? "member_id" : "Employee_id") . " = ?";
        $rateParams = [$idParam];
        $rateTypes = "i";
        
        if ($month !== null && $year !== null) {
            $attendanceRateQuery .= " AND MONTH(Date) = ? AND YEAR(Date) = ?";
            $rateParams[] = $month;
            $rateParams[] = $year;
            $rateTypes .= "ii";
        } elseif ($year !== null) {
            $attendanceRateQuery .= " AND YEAR(Date) = ?";
            $rateParams[] = $year;
            $rateTypes .= "i";
        } else {
            $attendanceRateQuery .= " AND MONTH(Date) = ? AND YEAR(Date) = ?";
            $rateParams[] = $statsMonth;
            $rateParams[] = $statsYear;
            $rateTypes .= "ii";
        }
        
        $stmt = $conn->prepare($attendanceRateQuery);
        $stmt->bind_param($rateTypes, ...$rateParams);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['attendance_rate'] = ($result['total_days'] > 0) ? 
            round(($result['present_days'] / $result['total_days']) * 100) : 0;

        // Get unique status types from database
        $statusResult = $conn->query("SELECT DISTINCT Attendance_type FROM attendance");
        $statuses = [];
        while ($row = $statusResult->fetch_assoc()) {
            $statuses[] = $row['Attendance_type'];
        }

        $response = [
            'status' => 'success',
            'attendance' => $attendance,
            'stats' => $stats,
            'status_options' => $statuses
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $employeeId = $_SESSION['user_id'];
        $memberId = $_SESSION['member_id']; // Get from session
        $role = $_SESSION['role']; // Get from session
        $stmt=null;
        if($role=="member"){

            $stmt = $conn->prepare("SELECT Attendance_id, Date, Time_in, Time_out, Photo, Photo_out, Attendance_type 
                                  FROM attendance 
                                --   WHERE (Employee_id = ? OR Member_id = ?)
                                  WHERE member_id = ? 
                                  ORDER BY Date DESC, Time_in DESC
                                  LIMIT 50"); // Added limit
            $stmt->bind_param("i", $memberId);
        }else{

            $stmt = $conn->prepare("SELECT Attendance_id, Date, Time_in, Time_out, Photo, Photo_out, Attendance_type 
                              FROM attendance 
                            --   WHERE (Employee_id = ? OR Member_id = ?)
                              WHERE Employee_id = ? 
                              ORDER BY Date DESC, Time_in DESC
                              LIMIT 50"); // Added limit
        // $stmt->bind_param("ii", $employeeId, $memberId);
        $stmt->bind_param("i", $employeeId);
    }
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

        // Get statistics
        $stats = [];
        $currentMonth = date('m');
        $currentYear = date('Y');

        // 1. Total Working Days
        $stmt = $conn->prepare("SELECT COUNT(*) as total_days FROM attendance 
                            WHERE Member_id = ? 
                            AND MONTH(Date) = ? 
                            AND YEAR(Date) = ?");
        $stmt->bind_param("iii", $employeeId, $currentMonth, $currentYear);
        $stmt->execute();
        $stats['total_days'] = $stmt->get_result()->fetch_assoc()['total_days'] ?? 0;

        // 2. Average Clock-In Time
        $stmt = $conn->prepare("SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(Time_in))) as avg_clock_in 
                            FROM attendance 
                            WHERE member_id = ? 
                            AND MONTH(Date) = ? 
                            AND YEAR(Date) = ?");
        $stmt->bind_param("iii", $employeeId, $currentMonth, $currentYear);
        $stmt->execute();
        $stats['avg_clock_in'] = $stmt->get_result()->fetch_assoc()['avg_clock_in'] ?? 'N/A';

        // 3. Attendance Rate
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

        // Get unique status types from database
        $statusResult = $conn->query("SELECT DISTINCT Attendance_type FROM attendance");
        $statuses = [];
        while ($row = $statusResult->fetch_assoc()) {
            $statuses[] = $row['Attendance_type'];
        }

        $response = [
            'status' => 'success',
            'attendance' => $attendance,
            'stats' => $stats,
            'status_options' => $statuses // Add status options to response
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} 

$conn->close();
?>


