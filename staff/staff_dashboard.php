<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Dashboard</title>
</head>
<body>
    <h1>Welcome Staff: <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
    <p>Your role: <?php echo htmlspecialchars($_SESSION['role']); ?></p>
    <a href="../logout.php">Logout</a>
</body>
</html>