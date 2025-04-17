<?php
require_once './config.php';

// This script should run daily at 12:00 AM
try {
    // Get all active employees
    $employees = $conn->query("SELECT employee_id FROM employee WHERE active = 1")->fetch_all(MYSQLI_ASSOC);

    foreach ($employees as $employee) {
        $employeeId = $employee['employee_id'];
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Check if attendance exists for yesterday
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance 
                               WHERE Employee_id = ? AND Date = ?");
        $stmt->bind_param("is", $employeeId, $yesterday);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        // If no attendance record found for yesterday, mark as absent
        if ($result['count'] == 0) {
            $stmt = $conn->prepare("INSERT INTO attendance 
                                  (Employee_id, Date, Attendance_type, Time_in, Time_out) 
                                  VALUES (?, ?, 'Absent', NULL, NULL)");
            $stmt->bind_param("is", $employeeId, $yesterday);
            $stmt->execute();
            
            // Also add a leave record if you want to track it in leaves table
            $stmt = $conn->prepare("INSERT INTO leaves 
                                  (employee_id, member_id, leave_type, start_date, end_date, 
                                  reason, status, applied_at) 
                                  VALUES (?, ?, 'Absent', ?, ?, 'Auto marked absent', 'Approved', NOW())");
            $memberId = 1; // Set your default member/admin ID
            $stmt->bind_param("iiss", $employeeId, $memberId, $yesterday, $yesterday);
            $stmt->execute();
        }
    }
    
    echo "Absent marking completed successfully";
} catch (Exception $e) {
    error_log("Error in auto_absent.php: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

$conn->close();