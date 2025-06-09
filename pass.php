<?php
// logistic/generate_hash.php

$passwordToHash = 'password123';
$hash = password_hash($passwordToHash, PASSWORD_DEFAULT);

echo "<h3>Password Hash Generator</h3>";
echo "<p><strong>Password:</strong> " . htmlspecialchars($passwordToHash) . "</p>";
echo "<p><strong>Generated Hash:</strong></p>";
echo "<textarea rows='3' cols='70' readonly>" . htmlspecialchars($hash) . "</textarea>";
echo "<p><small>คัดลอก Hash ที่สร้างขึ้นนี้ไปอัปเดตในคอลัมน์ 'password_hash' ของตาราง 'users' ในฐานข้อมูลของคุณ</small></p>";

?>
