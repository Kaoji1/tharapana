<?php
$host = "localhost";
$usr = "root";
$pwd = "";
$dbName = "tharapana";
$conn = mysqli_connect($host, $usr, $pwd, $dbName);
if (!$conn) {
    http_response_code(500);
    die('DB connect failed');
}
mysqli_set_charset($conn, 'utf8mb4');
// ไม่มี HTML ไม่มี tag ปิด
