<?php
// Database connection details (replace with your actual credentials)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "employee_management_system";

// $servername = "localhost:3306";
// $username = "techcomq_malikzain909192";
// $password = "~YVmY)fz5[fW";
// $dbname = "techcomq_employee_management_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
//     echo json_encode(['status' => 'success', 'message' => 'Connected to database successfully']);
//     exit;
    die("Connection failed: " . $conn->connect_error);
}
// }else{

//     echo json_encode(['status' => 'success', 'message' => 'Connection to database failed']);
//     exit;
// }
?>
