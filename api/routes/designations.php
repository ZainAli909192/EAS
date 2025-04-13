<?php
require_once './config.php';

// Handle POST request for adding a new designation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $designationName = isset($data['name']) ? trim($data['name']) : '';

    if (empty($designationName)) {
        echo json_encode(['status' => 'error', 'message' => 'Designation name is required.']);
        exit;
    }

    $stm1 = $conn->prepare("SELECT MAX(Designation_id) FROM designation");
    $stm1->execute();
    $result = $stm1->get_result();
    $row = $result->fetch_row();
    $next_designation_id = ($row[0] !== null) ? $row[0] + 1 : 1;
    $stm1->close();

    $stmt = $conn->prepare("INSERT INTO designation (Designation_id, Designation_name) VALUES (?, ?)");
    $stmt->bind_param("is", $next_designation_id, $designationName);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Designation added successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error adding designation: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Handle GET request for retrieving all designations
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $designations = [];
    $result = $conn->query("SELECT Designation_id, Designation_name FROM designation");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $designations[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'designations' => $designations]);
    $conn->close();
    exit;
}

// Handle GET request for retrieving a specific designation
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $designationId = $_GET['id'];

    $stmt = $conn->prepare("SELECT Designation_id, Designation_name FROM designation WHERE Designation_id = ?");
    $stmt->bind_param("i", $designationId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $designation = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'designation' => $designation]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Designation not found.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Handle PUT request for updating a designation
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['designation_id']) || !isset($data['designation_name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        exit;
    }

    $designationId = $data['designation_id'];
    $designationName = $data['designation_name'];

    $stmt = $conn->prepare("UPDATE designation SET Designation_name = ? WHERE Designation_id = ?");
    $stmt->bind_param("si", $designationName, $designationId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Designation updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error updating designation: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Handle DELETE request for deleting a designation
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id'])) {
    $designationId = $_GET['id'];

    // **Important:** Check for foreign key constraints in other tables (e.g., employee)
    // and handle them appropriately (delete related records or set ON DELETE CASCADE).
    // The following code assumes no such constraints or that you've handled them.

    $stmt = $conn->prepare("DELETE FROM designation WHERE Designation_id = ?");
    $stmt->bind_param("i", $designationId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Designation deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting designation: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Handle invalid request method
else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
?>