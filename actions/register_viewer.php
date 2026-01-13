<?php
session_start();
include '../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get inputs
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // 1. Check if email or username already exists
    $check = "SELECT * FROM users WHERE email='$email' OR username='$username'";
    $rs = $conn->query($check);

    if ($rs->num_rows > 0) {
        echo "<script>alert('Username or Email already taken!'); window.location.href='../auth/auth.php';</script>";
        exit();
    }

    // 2. Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 3. Insert into USERS table
    $sql_user = "INSERT INTO users (username, email, password_hash, role, is_active) 
                 VALUES ('$username', '$email', '$hashed_password', 'viewer', 1)";

    if ($conn->query($sql_user) === TRUE) {
        $new_user_id = $conn->insert_id;

        // 4. Insert into VIEWERS profile table
        $sql_profile = "INSERT INTO viewers (user_id, full_name, subscription_plan) 
                        VALUES ('$new_user_id', '$full_name', 'free')";
        
        if ($conn->query($sql_profile) === TRUE) {
            echo "<script>alert('Account created! Please login.'); window.location.href='../auth/auth.php';</script>";
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        echo "Error: " . $conn->error;
    }
}
$conn->close();
?>