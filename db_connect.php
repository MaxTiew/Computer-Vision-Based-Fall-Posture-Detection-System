<?php
// db_connect.php
$host = "localhost";
$username = "root"; // Default XAMPP username
$password = "";     // Default XAMPP password is empty
$database = "goodlife_vision";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Ensure consistent timezone (Malaysia)
date_default_timezone_set("Asia/Kuala_Lumpur");
$conn->query("SET time_zone = '+08:00'");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>