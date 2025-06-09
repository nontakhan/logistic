<?php
// php/db_connect.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "logistic"; // หรือชื่อฐานข้อมูลที่คุณใช้

$conn = new mysqli($servername, $username, $password, $dbname);

if (!$conn->set_charset("utf8mb4")) {
    // printf("Error loading character set utf8mb4: %s\n", $conn->error);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully";
?>