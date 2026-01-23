<?php
// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Test session
echo "Session started. User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";

// Test DB connection
require_once '../config/db_connect.php';
echo "Database connected successfully<br>";

// Test query
$user_id = $_SESSION['user_id'] ?? 1;
$sql = "SELECT p.company_name, p.verification_status, w.balance FROM producers p LEFT JOIN wallets w ON p.user_id = w.user_id WHERE p.user_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    echo "Statement prepared successfully<br>";
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo "Statement executed successfully<br>";
        $result = $stmt->get_result();
        echo "Result retrieved. Rows: " . $result->num_rows . "<br>";
    } else {
        echo "Execute error: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "Prepare error: " . $conn->error . "<br>";
}

echo "All tests passed!<br>";
?>
