<?php

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $package_id = $_GET['id'];

    $stmt = $conn->prepare("SELECT Package_id, Package_name, Price, Number_of_employees FROM package WHERE Package_id = ?");
    $stmt->bind_param("s", $package_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $package = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'package' => $package]);
    }
     }
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Handle PUT request to update a package
        $data = json_decode(file_get_contents('php://input'), true);
    
        // Validate the input data
        if (!isset($data['package_id']) || !isset($data['package_name']) || !isset($data['price']) || !isset($data['num_employees'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
            exit;
        }
    
        $package_id = $data['package_id'];
        $package_name = $data['package_name'];
        $price = $data['price'];
        $num_employees = $data['num_employees'];
    
        // Prepare and execute the SQL query
        $stmt = $conn->prepare("UPDATE package SET Package_name = ?, Price = ?, Number_of_employees = ? WHERE Package_id = ?");
        $stmt->bind_param("sdis", $package_name, $price, $num_employees, $package_id);
    
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Package updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error updating package: ' . $stmt->error]);
        }
    
        $stmt->close();
        $conn->close();
        exit;
    }
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Retrieve form data
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
        $num_employees = isset($_POST['num_employees']) ? intval($_POST['num_employees']) : 0;
    
        // Basic validation
        if (empty($name) || $price <= 0 || $num_employees <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid form data. Please check your inputs.']);
            exit;
        }
    
        // Generate a unique ID (incrementing from the max existing ID)
        $stm1 = $conn->prepare("SELECT MAX(package_id) FROM package");
        $stm1->execute();
        $result = $stm1->get_result();
        $row = $result->fetch_row();
        $max_id = $row[0];
    
        if ($max_id) {
            // Increment the max ID
            $response = $max_id + 1;
        } else {
            // If the table is empty, start with 1
            $response = 1;
        }
    
        // Prepare and execute the SQL query
        $stmt = $conn->prepare("INSERT INTO package (package_id, package_name, price, Number_of_employees) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isdi", $response, $name, $price, $num_employees);
    
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Package added successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error adding package: ' . $stmt->error]);
        }
    
        $stmt->close();
        $conn->close();
        exit;
    }else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request to retrieve packages
    $packages = [];
    $result = $conn->query("SELECT Package_id, Package_name, Price, Number_of_employees FROM package");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'packages' => $packages]);
    $conn->close();
    exit;
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id'])) {
    // Handle DELETE request to delete a package
    $package_id = $_GET['id'];

    // Prepare and execute the SQL query
    $stmt = $conn->prepare("DELETE FROM package WHERE Package_id = ?");
    $stmt->bind_param("s", $package_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Package deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting package: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

?>