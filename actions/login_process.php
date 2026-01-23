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

    // 2. Prepare SQL
    $sql = "SELECT user_id, username, password_hash, role FROM users WHERE email = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        // 3. Check if user exists
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $username, $hashed_password, $role);
            $stmt->fetch();

            // --- FIX: Check if hash exists BEFORE verifying to prevent crash ---
            if (!empty($hashed_password) && password_verify($password, $hashed_password)) {

                // --- Producer Verification Check ---
                if ($role === 'producer') {
                    $check_sql = "SELECT verification_status FROM producers WHERE user_id = ?";

                    if ($p_stmt = $conn->prepare($check_sql)) {
                        $p_stmt->bind_param("i", $id);
                        $p_stmt->execute();
                        $p_stmt->bind_result($verification_status);

                        if ($p_stmt->fetch()) {
                            if ($verification_status !== 'verified') {
                                header("Location: ../auth/auth.php?error=account_pending");
                                exit();
                            }
                        }
                        $p_stmt->close();
                    }
                }

                // 5. Set Session Variables
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['logged_in'] = true;

                // 6. Redirect
                switch ($role) {
                    case 'admin':
                        header("Location: ../admin/Dashboard.php");
                        break;
                    case 'producer':
                        header("Location: ../producer/Dashboard.php");
                        break;
                    case 'viewer':
                    default:
                        header("Location: ../index.php");
                        break;
                }
                exit();
            } else {
                // Wrong Password (or empty hash)
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
        header("Location: ../auth/auth.php?error=sql_error");
        exit();
    }
    $conn->close();
} else {
    header("Location: ../auth/auth.php");
    exit();
}
