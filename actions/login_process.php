<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize Inputs
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        header("Location: ../auth/auth.php?error=empty_fields");
        exit();
    }

    // 2. Prepare SQL to fetch user details including 'role'
    $sql = "SELECT user_id, username, password_hash, role FROM users WHERE email = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        // 3. Check if user exists
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $username, $hashed_password, $role);
            $stmt->fetch();

            // 4. Verify Password
            if (password_verify($password, $hashed_password)) {
                
                // 5. Set Session Variables
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['logged_in'] = true;

                // 6. REDIRECT BASED ON ROLE
                switch ($role) {
                    case 'admin':
                        header("Location: ../admin/Dashboard.php");
                        break;
                        
                    case 'producer':
                        header("Location: ../producer/Dashboard.php");
                        break;
                        
                    case 'viewer':
                    default:
                        // Redirect to the main landing page
                        header("Location: ../index.php");
                        break;
                }
                exit();

            } else {
                // Wrong Password
                header("Location: ../auth/auth.php?error=wrong_password");
                exit();
            }
        } else {
            // User not found
            header("Location: ../auth/auth.php?error=no_user");
            exit();
        }
        $stmt->close();
    } else {
        // Database connection failed
        header("Location: ../auth/auth.php?error=sql_error");
        exit();
    }
    $conn->close();
} else {
    // If someone tries to access this file directly without POST
    header("Location: ../auth/auth.php");
    exit();
}
?>