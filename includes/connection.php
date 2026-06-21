<?php
// includes/connection.php
// Railway MySQL + Render compatible connection

// Get environment variables
$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$pass = getenv("DB_PASS");
$db   = getenv("DB_NAME");
$port = getenv("DB_PORT") ?: 3306;

// Create connection (IMPORTANT: include port)
$conn = mysqli_connect($host, $user, $pass, $db, $port);

// Check connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Sorry, we are unable to connect to the database at the moment.");
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");
?>