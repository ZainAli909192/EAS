<?php

// Set headers FIRST to prevent any output
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once './config.php';

// Check connection
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// insert query 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate the input data (add more validation as needed)
    if (empty($data['Name']) || empty($data['Department_id']) || empty($data['Designation_id']) || empty($data['Password']) || empty($data['job_in_time']) || empty($data['job_out_time']) || empty($data['Number']) || empty($data['Member_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        $conn->close();
        exit;
    }

    $name = $data['Name'];
    $department_id = $data['Department_id'];
    $designation_id = $data['Designation_id'];
    $password = password_hash($data['Password'], PASSWORD_DEFAULT); // Hash the password!
    $job_in_time = $data['job_in_time'];
    $job_out_time = $data['job_out_time'];
    $number = $data['Number'];
    $member_id = $data['Member_id'];

    // Get max employee_id and increment
    $result = $conn->query("SELECT MAX(employee_id) AS max_id FROM employee");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $max_id = $row['max_id'];

        // Extract the numeric part of the ID and increment it
        preg_match('/(\d+)$/', $max_id, $matches);
        if (isset($matches[1])) {
            $next_id_num = intval($matches[1]) + 1;
            $employee_id = preg_replace('/(\d+)$/', sprintf('%03d', $next_id_num), $max_id); // Assuming ID format is EMP-001
        } else {
            // Handle cases where the ID format doesn't match the expected pattern
            $employee_id = '1'; // Or whatever your default ID should be
        }
    } else {
        // If the table is empty, start with EMP-001 or your desired starting ID
        $employee_id = '1';
    }

    // Prepare and execute the SQL query
    $stmt = $conn->prepare("INSERT INTO employee (employee_id, Name, Department_id, Designation_id, Password, job_in_time, job_out_time, Number, Member_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssi", $employee_id, $name, $department_id, $designation_id, $password, $job_in_time, $job_out_time, $number, $member_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Employee created successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error creating employee: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// To get only a single record 
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $employeeId = $_GET['id'];
    try {
        $query = "SELECT 
                    e.employee_id,
                    e.Name,
                    e.Number,
                    d.Department_name,
                    des.Designation_name,
                    e.job_in_time,
                    e.job_out_time,
                    e.Member_id,
                    m.Company_Name,
                    d.Department_id,
                    des.Designation_id
                FROM employee e
                LEFT JOIN department d ON e.Department_id = d.Department_id
                LEFT JOIN designation des ON e.Designation_id = des.Designation_id
                LEFT JOIN member m ON e.Member_id = m.Member_id
                WHERE e.employee_id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $employee = [
                'employee_id' => $row['employee_id'],
                'name' => $row['Name'],
                'phone_number' => $row['Number'],
                'department' => $row['Department_name'],
                'designation' => $row['Designation_name'],
                'job_in_time' => $row['job_in_time'],
                'job_out_time' => $row['job_out_time'],
                'member_id' => $row['Member_id'],
                'company_name' => $row['Company_Name'],
                'department_id' => $row['Department_id'],
                'designation_id' => $row['Designation_id']
            ];

            // Fetch department and designation lists for dropdowns/datalists
            $departments = [];
            $designations = [];

            $deptResult = $conn->query("SELECT Department_id, Department_name FROM department");
            while ($deptRow = $deptResult->fetch_assoc()) {
                $departments[] = $deptRow;
            }

            $desigResult = $conn->query("SELECT Designation_id, Designation_name FROM designation");
            while ($desigRow = $desigResult->fetch_assoc()) {
                $designations[] = $desigRow;
            }

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'data' => $employee, 'departments' => $departments, 'designations' => $designations]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Employee not found']);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve employee data: ' . $e->getMessage()
        ]);
    }
    $conn->close();
    exit;
}
if (isset($_GET['lists']) && $_GET['lists'] === 'true') {
    // Fetch departments
    $departments = [];
    $deptResult = $conn->query("SELECT Department_id, Department_name FROM department");
    if ($deptResult && $deptResult->num_rows > 0) {
        while ($row = $deptResult->fetch_assoc()) {
            $departments[] = $row;
        }
    }

    // Fetch designations
    $designations = [];
    $desigResult = $conn->query("SELECT Designation_id, Designation_name FROM designation");
    if ($desigResult && $desigResult->num_rows > 0) {
        while ($row = $desigResult->fetch_assoc()) {
            $designations[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'departments' => $departments, 'designations' => $designations]);
    $conn->close();
    exit;
}

// For all records  
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Query to get all employee data with department and designation names
        $query = "SELECT 
                    e.employee_id,
                    e.Name,
                    e.Number,
                    d.Department_name,
                    des.Designation_name,
                    e.job_in_time,
                    e.job_out_time,
                    e.Member_id
                  FROM employee e
                  LEFT JOIN department d ON e.Department_id = d.Department_id
                  LEFT JOIN designation des ON e.Designation_id = des.Designation_id
                  ORDER BY e.employee_id
                  
                  ";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = [
                'employee_id' => $row['employee_id'],
                'name' => $row['Name'],
                'phone_number' => $row['Number'],
                'department' => $row['Department_name'],
                'designation' => $row['Designation_name'],
                'job_in_time' => $row['job_in_time'],
                'job_out_time' => $row['job_out_time'],
                'member_id' => $row['Member_id']
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $employees
        ]);

    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve employee data: ' . $e->getMessage()
        ]);
    }
    
    $conn->close();
    exit;
}
else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Get the raw input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate required fields
    if (!isset($data['employee_id']) ||
        !isset($data['Name']) ||
        !isset($data['Number']) ||
        !isset($data['Department_id']) ||
        !isset($data['Designation_id']) ||
        !isset($data['job_in_time']) ||
        !isset($data['job_out_time'])) {

        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields'
        ]);
        exit;
    }

    try {
        // Prepare the update query
  $query = "UPDATE employee SET 
            Name = ?,
            Number = ?,
            Department_id = ?,
            Designation_id = ?,
            job_in_time = ?,
            job_out_time = ?
        WHERE employee_id = ?";

        $stmt = $conn->prepare($query);

        // Bind parameters (with the type definition string)
        $stmt->bind_param(
            "ssiissi", // Corrected type string (6 parameters + 1 for WHERE)
            $data['Name'],
            $data['Number'],
            $data['Department_id'],
            $data['Designation_id'],
            $data['job_in_time'],
            $data['job_out_time'],
            $data['employee_id']
        );

        // Execute the update
            $success = $stmt->execute();

        if ($success) {
            // Get the updated employee data to return

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Employee updated successfully',
            ]);
        } else {
            header('Content-Type: application/json'); // Add this line
            echo json_encode([
                'status' => 'error',
                'message' => 'Employee not updated',
            ]);
        }

    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update employee: ' . $e->getMessage()
        ]);
    }

    $conn->close();
    exit;
}

else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the raw input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate required field
    if (!isset($data['employee_id'])) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing employee_id field'
        ]);
        exit;
    }

    try {
        // First, get the employee data before deleting (for the response)
        $selectQuery = "SELECT 
                        e.employee_id,
                        e.Name,
                        e.Number,
                        d.Department_name,
                        des.Designation_name,
                        e.job_in_time,
                        e.job_out_time,
                        e.Member_id
                      FROM employee e
                      LEFT JOIN department d ON e.Department_id = d.Department_id
                      LEFT JOIN designation des ON e.Designation_id = des.Designation_id
                      WHERE e.employee_id = ?";
        
        $selectStmt = $conn->prepare($selectQuery);
        $selectStmt->bind_param("i", $data['employee_id']);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $employeeToDelete = $result->fetch_assoc();

        if (!$employeeToDelete) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Employee not found'
            ]);
            exit;
        }

        // Prepare the delete query
        $query = "DELETE FROM employee WHERE employee_id = ?";
        $stmt = $conn->prepare($query);
        
        // Bind parameter
        $stmt->bind_param("i", $data['employee_id']);

        // Execute the delete
        $success = $stmt->execute();

        if ($success) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Employee deleted successfully',
                'data' => $employeeToDelete // Return the deleted employee data
            ]);
        } else {
            throw new Exception("Failed to delete employee");
        }

    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete employee: ' . $e->getMessage()
        ]);
    }
    
    $conn->close();
    exit;
}
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);

?>