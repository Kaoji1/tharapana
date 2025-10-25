<?php
// (ไฟล์นี้ต้องติดตั้ง PhpSpreadsheet ก่อน)
require 'vendor/autoload.php'; // ถ้าใช้ Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// --- 1. ส่วนเชื่อมต่อฐานข้อมูล (เหมือนเดิม) ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$host = "localhost"; $usr = "root"; $pwd = ""; $dbName = "tharapana";
$conn = mysqli_connect($host, $usr, $pwd, $dbName);
if (!$conn) { die("Connection failed"); }
mysqli_set_charset($conn, 'utf8mb4');

// --- 2. ตรวจสอบการเข้าสู่ระบบ (เหมือนเดิม) ---
if (!isset($_SESSION["user_id"])) {
    header("Location: ReandLog.php");
    exit;
}

// --- 3. รับค่า Filter (เหมือนเดิม) ---
$period = $_GET['period'] ?? 'week';
$check_date = $_GET['check_date'] ?? date('Y-m-d');

// --- 4. สร้าง Excel ---
$spreadsheet = new Spreadsheet();

// === Sheet 1: การจองล่าสุด ===
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('การจองล่าสุด');

// ดึงข้อมูล (เหมือนใน Dashboard)
$result = mysqli_query($conn, "SELECT firstname, lastname, checkin, checkout, created_at, final_price FROM booking WHERE status!='cancelled' ORDER BY created_at DESC LIMIT 50"); // ดึงมา 50 รายการ

// เขียนหัวตาราง
$sheet1->setCellValue('A1', 'ชื่อผู้จอง');
$sheet1->setCellValue('B1', 'วันที่เข้าพัก');
$sheet1->setCellValue('C1', 'วันที่ออก');
$sheet1->setCellValue('D1', 'วันที่จอง');
$sheet1->setCellValue('E1', 'ราคาสุทธิ');

// เขียนข้อมูล
$row = 2;
while ($data = mysqli_fetch_assoc($result)) {
    $sheet1->setCellValue('A' . $row, $data['firstname'] . ' ' . $data['lastname']);
    $sheet1->setCellValue('B' . $row, $data['checkin']);
    $sheet1->setCellValue('C' . $row, $data['checkout']);
    $sheet1->setCellValue('D' . $row, $data['created_at']);
    $sheet1->setCellValue('E' . $row, $data['final_price']);
    $row++;
}

// === Sheet 2: ห้องยอดนิยม ===
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('ห้องยอดนิยม');

// ดึงข้อมูล (เหมือนใน Dashboard)
$query = "SELECT T2.rt_name, COUNT(T1.b_id) AS booking_count FROM booking AS T1 JOIN room_type AS T2 ON T1.rt_id = T2.rt_id WHERE T1.status!='cancelled' GROUP BY T1.rt_id, T2.rt_name ORDER BY booking_count DESC";
$result = mysqli_query($conn, $query);

// เขียนหัวตาราง
$sheet2->setCellValue('A1', 'ประเภทห้อง');
$sheet2->setCellValue('B1', 'จำนวนการจอง');

// เขียนข้อมูล
$row = 2;
while ($data = mysqli_fetch_assoc($result)) {
    $sheet2->setCellValue('A' . $row, $data['rt_name']);
    $sheet2->setCellValue('B' . $row, $data['booking_count']);
    $row++;
}

mysqli_close($conn);

// --- 5. สั่งดาวน์โหลด ---
$filename = 'report_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

?>