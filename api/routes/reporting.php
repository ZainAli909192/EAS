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
    // Handle report generation
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $startDate = $data['start_date'] ?? date('Y-m-01');
        $endDate = $data['end_date'] ?? date('Y-m-d');
        $departmentId = $data['department_id'] ?? 'all';
        
        // Base query
        $query = "SELECT 
                 a.Date, 
                 e.Name as employee_name,
                 d.Department_name,
                 a.Time_in,
                 a.Time_out,
                 a.Attendance_type,
                 a.Photo,
                 a.Photo_out
                 FROM attendance a
                 JOIN employee e ON a.Employee_id = e.employee_id
                 JOIN department d ON e.Department_id = d.Department_id
                 WHERE a.Date BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        $types = "ss";
        
        // Add department filter if not 'all'
        if ($departmentId !== 'all') {
            $query .= " AND e.Department_id = ?";
            $params[] = $departmentId;
            $types .= "i";
        }
        
        // Get report data
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reportData = [];
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        
        // Get quick stats
        $stats = getQuickStats($conn, $startDate, $endDate, $departmentId);
        
        // Get chart data
        $chartData = getChartData($conn, $startDate, $endDate, $departmentId);
        
        echo json_encode([
            'status' => 'success',
            'data' => $reportData,
            'stats' => $stats,
            'charts' => $chartData
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle quick stats request
    try {
        $stats = getQuickStats($conn);
        echo json_encode(['status' => 'success', 'stats' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
// Handle export requests
if (isset($_GET['export'])) {
    try {
        $exportType = $_GET['export'];
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $departmentId = $_GET['department_id'] ?? 'all';
        
        // Get the data to export
        $query = "SELECT 
                 a.Date, 
                 e.Name as employee_name,
                 d.Department_name,
                 a.Time_in,
                 a.Time_out,
                 a.Attendance_type
                 FROM attendance a
                 JOIN employee e ON a.Employee_id = e.employee_id
                 JOIN department d ON e.Department_id = d.Department_id
                 WHERE a.Date BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        $types = "ss";
        
        if ($departmentId !== 'all') {
            $query .= " AND e.Department_id = ?";
            $params[] = $departmentId;
            $types .= "i";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reportData = [];
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        
        switch ($exportType) {
            case 'csv':
                exportCSV($reportData, $startDate, $endDate);
                break;
                
            case 'pdf':
                exportPDF($reportData, $startDate, $endDate);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid export type']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

function exportCSV($data, $startDate, $endDate) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $startDate . '_to_' . $endDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV header
    fputcsv($output, array_keys($data[0]));
    
    // CSV data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportPDF($data, $startDate, $endDate) {
    // This is a basic implementation - you might want to use a library like TCPDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attendance_report_' . $startDate . '_to_' . $endDate . '.pdf"');
    
    $html = '<h1>Attendance Report</h1>';
    $html .= '<p>Date Range: ' . $startDate . ' to ' . $endDate . '</p>';
    $html .= '<table border="1" cellpadding="5">';
    $html .= '<tr>';
    
    // Table header
    foreach (array_keys($data[0]) as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr>';
    
    // Table data
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    // In a real implementation, you would use a PDF library here
    // This is just a simple example
    echo $html;
}

// Helper function to get quick stats
function getQuickStats($conn, $startDate = null, $endDate = null, $departmentId = null) {
    $today = date('Y-m-d');
    
    // Total Employees (removed active filter since column doesn't exist)
    $query = "SELECT COUNT(*) as total FROM employee";
    $params = [];
    $types = "";
    
    if ($departmentId && $departmentId !== 'all') {
        $query .= " WHERE Department_id = ?";
        $params[] = $departmentId;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalEmployees = $stmt->get_result()->fetch_assoc()['total'];
    
    // Present Today
    $query = "SELECT COUNT(DISTINCT a.Employee_id) as present 
              FROM attendance a
              JOIN employee e ON a.Employee_id = e.employee_id
              WHERE a.Date = ? AND a.Attendance_type = 'Present'";
    $params = [$today];
    $types = "s";
    
    if ($departmentId && $departmentId !== 'all') {
        $query .= " AND e.Department_id = ?";
        $params[] = $departmentId;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $presentToday = $stmt->get_result()->fetch_assoc()['present'];
    
    // Absent Today
    $absentToday = $totalEmployees - $presentToday;
    
    // Late Arrivals (assuming late is after 9:30 AM)
    $query = "SELECT COUNT(*) as late 
              FROM attendance a
              JOIN employee e ON a.Employee_id = e.employee_id
              WHERE a.Date = ? AND a.Attendance_type = 'Present' 
              AND TIME(a.Time_in) > '09:30:00'";
    $params = [$today];
    $types = "s";
    
    if ($departmentId && $departmentId !== 'all') {
        $query .= " AND e.Department_id = ?";
        $params[] = $departmentId;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $lateArrivals = $stmt->get_result()->fetch_assoc()['late'];
    
    return [
        'total_employees' => $totalEmployees,
        'present_today' => $presentToday,
        'absent_today' => $absentToday,
        'late_arrivals' => $lateArrivals
    ];
}

// Helper function to get chart data
function getChartData($conn, $startDate, $endDate, $departmentId = null) {
    // Attendance Trend (daily counts)
    $query = "SELECT 
              a.Date,
              COUNT(CASE WHEN a.Attendance_type = 'Present' THEN 1 END) as present,
              COUNT(CASE WHEN a.Attendance_type = 'Absent' THEN 1 END) as absent,
              COUNT(CASE WHEN a.Attendance_type = 'Late' THEN 1 END) as late
              FROM attendance a
              JOIN employee e ON a.Employee_id = e.employee_id
              WHERE a.Date BETWEEN ? AND ?";
    
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($departmentId && $departmentId !== 'all') {
        $query .= " AND e.Department_id = ?";
        $params[] = $departmentId;
        $types .= "i";
    }
    
    $query .= " GROUP BY a.Date ORDER BY a.Date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $trendData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Attendance Distribution (pie chart)
    $query = "SELECT 
              a.Attendance_type,
              COUNT(*) as count
              FROM attendance a
              JOIN employee e ON a.Employee_id = e.employee_id
              WHERE a.Date BETWEEN ? AND ?";
    
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($departmentId && $departmentId !== 'all') {
        $query .= " AND e.Department_id = ?";
        $params[] = $departmentId;
        $types .= "i";
    }
    
    $query .= " GROUP BY a.Attendance_type";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $distributionData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return [
        'trend' => $trendData,
        'distribution' => $distributionData
    ];
}

$conn->close();
?>