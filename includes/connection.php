<?php
// includes/connection.php
// Database connection for IAA Complaint Management System (MySQLi)

// ===============================
// ENVIRONMENT VARIABLES (Render)
// ===============================
$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$pass = getenv("DB_PASS");
$db   = getenv("DB_NAME");

// ===============================
// LOCAL FALLBACK (XAMPP testing)
// ===============================
if (!$host) {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "iaa_complaints";
}

// ===============================
// CREATE CONNECTION
// ===============================
$conn = mysqli_connect($host, $user, $pass, $db);

// ===============================
// CHECK CONNECTION
// ===============================
if (!$conn) {
    error_log("Database Connection Failed: " . mysqli_connect_error());

    die("
        Sorry, we are unable to connect to the database right now.
        Please try again later.
    ");
}

// ===============================
// SET CHARACTER ENCODING
// ===============================
mysqli_set_charset($conn, "utf8mb4");
?>