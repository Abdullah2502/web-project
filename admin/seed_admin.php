<?php
// admin/seed_admin.php

// 1. Include Database Connection
require_once '../config/db_connect.php';

// 2. Admin Credentials to create
$admin_username = "admin";
$admin_email    = "admin@msp.com";
$admin_password = "admin123"; // This will be hashed
$department     = "Headquarters";

// 3. Check if Admin already exists to prevent duplicates
$check_sql = "SELECT user_id FROM users WHERE email = ? OR username = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ss", $admin_email, $admin_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<h3>Error: Admin user already exists!</h3>";
    echo "Username: $admin_username <br> Email: $admin_email";
    exit();
}

// 4. Hash the Password
$password_hash = password_hash($admin_password, PASSWORD_DEFAULT);

// 5. Insert into 'users' table
$role = 'admin';
$sql_user = "INSERT INTO users (username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("ssss", $admin_username, $admin_email, $password_hash, $role);

if ($stmt_user->execute()) {
    // Get the newly created user_id
    $new_user_id = $conn->insert_id;

    // 6. Insert into 'admins' profile table
    $sql_profile = "INSERT INTO admins (user_id, department) VALUES (?, ?)";
    $stmt_profile = $conn->prepare($sql_profile);
    $stmt_profile->bind_param("is", $new_user_id, $department);

    if ($stmt_profile->execute()) {
        echo "<h2 style='color:green;'>Success! Admin Seeded.</h2>";
        echo "<p>You can now login with:</p>";
        echo "<ul>";
        echo "<li><strong>Email:</strong> $admin_email</li>";
        echo "<li><strong>Password:</strong> $admin_password</li>";
        echo "</ul>";
        echo "<a href='../auth/auth.php'>Go to Login</a>";
    } else {
        echo "Error creating admin profile: " . $conn->error;
    }

} else {
    echo "Error creating user record: " . $conn->error;
}

$conn->close();
?>