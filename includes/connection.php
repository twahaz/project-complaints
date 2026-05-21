<?php
// includes/connection.php
// Database connection for IAA Complaint Management System using MySQLi

// Database configuration
$host = 'localhost';
$dbname = 'iaa_complaint_system';
$username = 'root';
$password = '';

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    error_log("Connection failed: " . mysqli_connect_error());
    die("Sorry, we are unable to connect to the database. Please try again later.");
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");

// No echo statements here to avoid header issues
?>