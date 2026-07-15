<?php
// Database settings
$host = "localhost";
$username = "root";
$password = ""; // XAMPP default has no password
$database = "program_manager";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check if connection worked
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "Connected successfully";
?>