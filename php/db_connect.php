<?php
// php/db_connect.php
// Prefer environment variables in production. Fallbacks keep the current XAMPP setup working.
$servername = getenv('LOGISTIC_DB_HOST') ?: "10.10.202.156";
$username = getenv('LOGISTIC_DB_USER') ?: "nr";
$password = getenv('LOGISTIC_DB_PASS') ?: "P@ssw0rd";
$dbname = getenv('LOGISTIC_DB_NAME') ?: "logistic"; // หรือชื่อฐานข้อมูลที่คุณใช้

$conn = new mysqli($servername, $username, $password, $dbname);

if (!$conn->set_charset("utf8mb4")) {
    // printf("Error loading character set utf8mb4: %s\n", $conn->error);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully";
?>
