<?php
require_once './config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve department name from the request body
    $data = json_decode(file_get_contents('php://input'), true);
    $departmentName = isset($data['name']) ? trim($data['name']) : '';

    // Validate department name
    if (empty($departmentName)) {
        echo json_encode(['status' => 'error', 'message' => 'Department name is required.']);
        exit;
    }

    // Get the next Department_id
    $stm1 = $conn->prepare("SELECT MAX(Department_id) FROM department");
    $stm1->execute();
    $result = $stm1->get_result();
    $row = $result->fetch_row();
    $next_department_id = ($row[0] !== null) ? $row[0] + 1 : 1;
    $stm1->close();

    // Prepare and execute the SQL query to insert the new department
    $stmt = $conn->prepare("INSERT INTO department (Department_id, Department_name) VALUES (?, ?)");
    $stmt->bind_param("is", $next_department_id, $departmentName);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Department added successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error adding department: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}



elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id'])) {
    // Handle DELETE request to delete a department
    $department_id = $_GET['id'];

    // Delete related employees first
    $delete_employees_stmt = $conn->prepare("DELETE FROM employee WHERE Department_id = ?");
    $delete_employees_stmt->bind_param("i", $department_id);

    if ($delete_employees_stmt->execute()) {
        // Now delete the department
        $delete_employees_stmt->close();
        $stmt = $conn->prepare("DELETE FROM department WHERE Department_id = ?");
        $stmt->bind_param("i", $department_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Department and related employees deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error deleting department: ' . $stmt->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting related employees: ' . $delete_employees_stmt->error]);
        $delete_employees_stmt->close();
    }

    $conn->close();
    exit;
}

else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // Handle GET request to retrieve a specific department
    $departmentId = $_GET['id'];

    $stmt = $conn->prepare("SELECT Department_id, Department_name FROM department WHERE Department_id = ?");
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $department = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'department' => $department]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Department not found.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request to retrieve departments
    $departments = [];
    $result = $conn->query("SELECT Department_id, Department_name FROM department");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'departments' => $departments]);
    $conn->close();
    exit;
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Handle PUT request to update a department
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate the input data
    if (!isset($data['department_id']) || !isset($data['department_name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        exit;
    }

    $departmentId = $data['department_id'];
    $departmentName = $data['department_name'];

    // Prepare and execute the SQL query
    $stmt = $conn->prepare("UPDATE department SET Department_name = ? WHERE Department_id = ?");
    $stmt->bind_param("si", $departmentName, $departmentId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Department updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error updating department: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

else {
    // Handle cases where the request is not GET or DELETE or the ID is missing.
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method or missing department ID.']);
    exit;
}

?>