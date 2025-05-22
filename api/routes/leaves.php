<?php
header("Content-Type: application/json");
require_once './config.php';

// Start session to get employee_id
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Please login']);
    exit;
}


// else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Handle leave approval/rejection
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['leave_id']) || empty($input['action'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields'
        ]);
        exit;
    }
    
    $validActions = ['approve', 'reject'];
    if (!in_array($input['action'], $validActions)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
        exit;
    }
    $input['action'] = $input['action'] === 'approve' ? 'approved' : 'rejected';
    try {
        // Update leave status
        $stmt = $conn->prepare("UPDATE leaves SET status = ?, processed_at = NOW() WHERE leave_id = ?");
        $stmt->bind_param("si", $input['action'], $input['leave_id']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Leave request updated successfully'
            ]);
        } else {
            throw new Exception("Failed to update leave request");
        }
        
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}


else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle FormData submission
    $leaveType = $_POST['leave_type'] ?? null;
    $startDate = $_POST['start-date'] ?? null;
    $endDate = $_POST['end-date'] ?? null;
    $reason = $_POST['reason'] ?? null;
    $document = $_FILES['document'] ?? null;
    $employeeId = $_SESSION['user_id'];
    $memberId = $_SESSION['member_id'];

    // Validate required fields
    if (!$leaveType || !$startDate || !$endDate || !$reason) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    // Validate leave type against ENUM values
    $result = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_NAME = 'leaves' AND COLUMN_NAME = 'leave_type'");
    $row = $result->fetch_assoc();
    preg_match("/^enum\(\'(.*)\'\)$/", $row['COLUMN_TYPE'], $matches);
    $validTypes = explode("','", $matches[1]);

    if (!in_array($leaveType, $validTypes)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid leave type']);
        exit;
    }

    // Handle file upload
    $documentPath = null;
    if (($leaveType === 'sick' || $leaveType === 'paternity') && (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Document of doctor is required for sick leave']);
        exit;
    }

    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "../leave_documents/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Generate unique filename
        $dateTime = new DateTime();
        $fileName = basename($_FILES['document']['name']) . '_' . $dateTime->format('Y-m-d_H-i-s');
        $targetFile = $targetDir . $fileName;

        // Validate file
        $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        if ($_FILES['document']['size'] > 5000000) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'File is too large (max 5MB)']);
            exit;
        }

        if (!in_array($fileType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Only PDF, DOC, JPG, PNG files are allowed']);
            exit;
        }

        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetFile)) {
            $documentPath = $targetFile;
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error uploading document']);
            exit;
        }
    }

    // Insert leave application
    try {
        $stmt = $conn->prepare("INSERT INTO leaves (employee_id, member_id, leave_type, start_date, end_date, reason, document_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $employeeId, $memberId, $leaveType, $startDate, $endDate, $reason, $documentPath);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Leave application submitted successfully',
                'leave_id' => $conn->insert_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    }
}

// filters 
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get filter parameters
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        
        // Base query parts
        $select = "SELECT l.leave_id, e.Name as employee_name, l.leave_type, 
                  l.start_date, l.end_date, l.reason, l.status";
        $from = " FROM leaves l JOIN employee e ON l.employee_id = e.employee_id";
        $where = " WHERE 1=1"; // Start with a true condition for easier concatenation
        $order = " ORDER BY l.applied_at DESC";
        
        // Parameters array for binding
        $params = [];
        $types = "";
        
        // Role-based filtering
        if ($_SESSION['role'] === 'employee') {
            $where .= " AND l.employee_id = ?";
            $params[] = $_SESSION['user_id'];
            $types .= "i";
        } else if ($_SESSION['role'] === 'member') {
            $from .= " JOIN member m ON e.Member_id = m.Member_id";
            $where .= " AND m.Member_id = ?";
            $params[] = $_SESSION['member_id']; // Use member_id from session
            $types .= "i";
        }
        // Admin doesn't need additional role filtering
        
        // Apply status filter if provided
        if ($status) {
            $where .= " AND l.status = ?";
            $params[] = ucfirst(strtolower($status)); // Ensure consistent case
            $types .= "s";
        }
        
        // Apply date filter if provided
        if ($date) {
            $where .= " AND   l.applied_at like ?";
            $params[] = "%" . $date . "%";
            $types .= "s"; 
        } 
        
        // Build final query
        $query = $select . $from . $where . $order;
        $stmt = $conn->prepare($query);
        
        // Bind parameters if any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = [
                'id' => $row['leave_id'],
                'name' => $row['employee_name'],
                'type' => $row['leave_type'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'reason' => $row['reason'],
                'status' => $row['status']
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'leaves' => $leaves
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'leave_types') {
    // Your existing GET endpoint for leave types
    $result = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_NAME = 'leaves' AND COLUMN_NAME = 'leave_type'");
    $row = $result->fetch_assoc();
    
    preg_match("/^enum\(\'(.*)\'\)$/", $row['COLUMN_TYPE'], $matches);
    $types = explode("','", $matches[1]);
    
    // Map database values to display labels
    $typeLabels = [
        'sick' => 'Sick Leave',
        'annual' => 'Annual Leave',
        'casual' => 'Casual Leave',
        'personal' => 'Personal Leave',
        'maternity' => 'Maternity Leave',
        'paternity' => 'Paternity Leave'
    ];
    
    $formattedTypes = [];
    foreach ($types as $type) {
        $formattedTypes[] = [
            'value' => $type,
            'label' => $typeLabels[$type] ?? ucfirst($type)
        ];
    }
    
    echo json_encode(['status' => 'success', 'leave_types' => $formattedTypes]);
} 
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get filter parameters
        $leaveType = isset($_GET['leave_type']) ? $_GET['leave_type'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        
        // Base query parts
        $select = "SELECT l.leave_id, e.Name as employee_name, l.leave_type, 
                  l.start_date, l.end_date, l.reason, l.status";
        $from = " FROM leaves l JOIN employee e ON l.employee_id = e.employee_id";
        $where = " WHERE 1=1"; // Start with a true condition for easier concatenation
        $order = " ORDER BY l.applied_at DESC";
        
        // Parameters array for binding
        $params = [];
        $types = "";
        
        // Role-based filtering
        if ($_SESSION['role'] === 'employee') {
            $where .= " AND l.employee_id = ?";
            $params[] = $_SESSION['user_id'];
            $types .= "i";
        } else if ($_SESSION['role'] === 'member') {
            $from .= " JOIN member m ON e.Member_id = m.Member_id";
            $where .= " AND m.Member_id = ?";
            $params[] = $_SESSION['user_id'];
            $types .= "i";
        }
        // Admin doesn't need additional role filtering
        
        // Apply filters if provided
        if ($leaveType) {
            $where .= " AND l.leave_type = ?";
            $params[] = $leaveType;
            $types .= "s";
        }
        
        if ($status) {
            $where .= " AND l.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($date) {
            $where .= " AND ? BETWEEN l.start_date AND l.end_date";
            $params[] = $date;
            $types .= "s";
        }
        
        // Build final query
        $query = $select . $from . $where . $order;
        $stmt = $conn->prepare($query);
        
        // Bind parameters if any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = [
                'id' => $row['leave_id'],
                'name' => $row['employee_name'],
                'type' => $row['leave_type'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'reason' => $row['reason'],
                'status' => $row['status']
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'leaves' => $leaves
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
// filters applied 
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Query to get leave requests based on role
        if (isset($_SESSION['role'])) {
            
            $stmt=null;

            if ($_SESSION['role'] === 'employee') {
                $query = "SELECT l.leave_id, e.Name as employee_name, l.leave_type, 
                         l.start_date, l.end_date, l.reason, l.status
                         FROM leaves l
                         JOIN employee e ON l.employee_id = e.employee_id
                         WHERE l.employee_id = ?
                         ORDER BY l.applied_at DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $_SESSION['user_id']);
                
            } else if ($_SESSION['role'] === 'member') {
                // Fixed query for members - shows employees under their company
                $query = "SELECT l.leave_id, e.Name as employee_name, l.leave_type, 
                         l.start_date, l.end_date, l.reason, l.status
                         FROM leaves l
                         JOIN employee e ON l.employee_id = e.employee_id
                         JOIN member m ON e.Member_id = m.Member_id
                         WHERE m.Member_id = ?
                         ORDER BY l.applied_at DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $_SESSION['user_id']);
            } else if ($_SESSION['role'] === 'admin') {
                // Admin can see all leave requests
                $query = "SELECT l.leave_id, e.Name as employee_name, l.leave_type, 
                         l.start_date, l.end_date, l.reason, l.status
                         FROM leaves l
                         JOIN employee e ON l.employee_id = e.employee_id
                         ORDER BY l.applied_at DESC";
                $stmt = $conn->prepare($query);
            }
            
            if (!isset($stmt)) {
                throw new Exception("Invalid user role");
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $leaves = [];
            while ($row = $result->fetch_assoc()) {
                $leaves[] = [
                    'id' => $row['leave_id'],
                    'name' => $row['employee_name'],
                    'type' => $row['leave_type'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'reason' => $row['reason'],
                    'status' => $row['status']
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'leaves' => $leaves
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}


else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

$conn->close();
?>