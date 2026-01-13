<?php
session_start();
include '../config/db_connect.php'; // Notice the '../' to go up one level

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // Check if user exists
    $sql = "SELECT user_id, username, password_hash, role, is_active FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        
        // Verify Password
        if (password_verify($password, $row['password_hash'])) {
            
            if ($row['is_active'] == 0) {
                echo "<script>alert('Account is deactivated.'); window.location.href='../auth/auth.php';</script>";
                exit();
            }

            // Set Session Variables
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            // Redirect based on Role
            if ($row['role'] == 'admin') {
                header("Location: ../admin/Dashboard.php");
            } elseif ($row['role'] == 'producer') {
                header("Location: ../producer/producerPage.php");
            } else {
                // Viewers go to the main home page
                header("Location: ../index.php"); 
            }
            exit();
        } else {
            // Incorrect Password
            echo "<script>alert('Invalid Password'); window.location.href='../auth/auth.php';</script>";
        }
    } else {
        // User not found
        echo "<script>alert('No account found with this email'); window.location.href='../auth/auth.php';</script>";
    }
}
$conn->close();
?>