<?php
$servername = "localhost";
$username = "root";
$password = ""; // Default XAMPP password is usually empty
$dbname = "msp_project_final"; // Make sure this matches your Database Name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>