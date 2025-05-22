<?php
// Assuming you have your database connection established as $conn
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : ''; // Plain text password from form
    $number = isset($_POST['number']) ? trim($_POST['number']) : '';

    $package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    $membership_start_date = isset($_POST['membership_start_date']) ? $_POST['membership_start_date'] : null;
    $membership_end_date = isset($_POST['membership_end_date']) ? $_POST['membership_end_date'] : null;

    // Basic validation
    if (empty($company_name) || empty($email) || empty($password) || empty($number)  || $package_id <= 0 || empty($membership_start_date) || empty($membership_end_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid form data. Please check all fields.']);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
        exit;
    }

    // Check if email already exists
    $check_email_stmt = $conn->prepare("SELECT Email FROM member WHERE Email = ?");
    $check_email_stmt->bind_param("s", $email);
    $check_email_stmt->execute();
    $check_email_stmt->store_result();

    if ($check_email_stmt->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered with us. Try a different one.']);
        $check_email_stmt->close();
        $conn->close();
        exit;
    }
    $check_email_stmt->close();

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Get the next Member_id
    $stm1 = $conn->prepare("SELECT MAX(Member_id) FROM member");
    $stm1->execute();
    $result = $stm1->get_result();
    $row = $result->fetch_row();
    $next_member_id = ($row[0] !== null) ? $row[0] + 1 : 1;
    $stm1->close();

    // Prepare and execute the SQL query
    $stmt = $conn->prepare("INSERT INTO member (Member_id, Company_Name, Email, Password, Number, Package_id, Membership_start_date, Membership_end_date ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssisss", $next_member_id, $company_name, $email, $hashed_password, $number, $package_id, $membership_start_date, $membership_end_date);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Membership added successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error adding membership: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}


elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id'])) {
    // Handle DELETE request to delete a member
    $member_id = $_GET['id'];

    // Delete related employees first
    $delete_employees_stmt = $conn->prepare("DELETE FROM employee WHERE Member_id = ?");
    $delete_employees_stmt->bind_param("i", $member_id);

    if ($delete_employees_stmt->execute()) {
        // Now delete the member
        $delete_employees_stmt->close();
        $stmt = $conn->prepare("DELETE FROM member WHERE Member_id = ?");
        $stmt->bind_param("i", $member_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Member and related employees deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error deleting member: ' . $stmt->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting related employees: ' . $delete_employees_stmt->error]);
        $delete_employees_stmt->close();
    }

    $conn->close();
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['Member_id'])) {
    // Handle GET request to retrieve a specific member
    $member_id = $_GET['Member_id'];

    $stmt = $conn->prepare("SELECT Member_id, Company_Name, Email, Password, Number, Package_id, Membership_start_date, Membership_end_date FROM member WHERE Member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $member = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'member' => $member]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Member not found.']);
    }
    $stmt->close();
    $conn->close();
    exit;
} 

elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Handle PUT request to update a member
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate the input data
    if (!isset($data['Member_id']) || !isset($data['Company_Name']) || !isset($data['Email']) || !isset($data['Password']) || !isset($data['Number']) || !isset($data['Package_id']) || !isset($data['Membership_start_date']) || !isset($data['Membership_end_date'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        exit;
    }

    $member_id = $data['Member_id'];
    $company_name = $data['Company_Name'];
    $email = $data['Email'];
    $password = $data['Password'];
    $number = $data['Number'];
    $package_id = $data['Package_id'];
    $membership_start_date = $data['Membership_start_date'];
    $membership_end_date = $data['Membership_end_date'];

    // Prepare and execute the SQL query
    $stmt = $conn->prepare("UPDATE member SET Company_Name = ?, Email = ?, Password = ?, Number = ?, Package_id = ?, Membership_start_date = ?, Membership_end_date = ? WHERE Member_id = ?");
    $stmt->bind_param("ssssissi", $company_name, $email, $password, $number, $package_id, $membership_start_date, $membership_end_date, $member_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Member updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error updating member: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['company_name']) || isset($_GET['company_id'])) {
    // Handle GET request to retrieve members with optional filters
    $companyNameFilter = isset($_GET['company_name']) ? $_GET['company_name'] : null;
    $companyIdFilter = isset($_GET['company_id']) ? $_GET['company_id'] : null;

    $query = "SELECT member.Member_id, member.Company_Name, member.Number, member.Email, 
              package.Number_of_employees, package.Package_name 
              FROM member 
              INNER JOIN package ON member.Package_id = package.Package_id 
              WHERE 1=1";
    
    $params = [];
    $types = '';

    if ($companyNameFilter) {
        $query .= " AND member.Company_Name LIKE ?";
        $params[] = '%' . $companyNameFilter . '%';
        $types .= 's';
    }

    if ($companyIdFilter) {
        $query .= " AND member.Member_id = ?";
        $params[] = $companyIdFilter;
        $types .= 'i';
    }

    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $members = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'members' => $members]);
    $stmt->close();
    $conn->close();
    exit;
}
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request to retrieve members
    $members = [];
    $result = $conn->query("SELECT member.Member_id, member.Company_Name, member.Number, member.Email, package.Number_of_employees, package.Package_name FROM member INNER JOIN package ON member.Package_id = package.Package_id");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'members' => $members]);
    $conn->close();
    exit;
} 
?>