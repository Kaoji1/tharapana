<?php
require_once "connectdb.php";

/**
 * อัปเดตสถานะอัตโนมัติของ booking ตามเวลาและวัน
 * - pending เกิน 20 นาที => cancelled
 * - วันที่วันนี้อยู่ระหว่าง checkIn-checkOut => checked_in
 * - วันที่เลย checkOut => checked_out
 */

date_default_timezone_set('Asia/Bangkok');
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// 🕒 1. ยกเลิกการจองที่ไม่ชำระภายใน 20 นาที
$sql_cancel = "
    UPDATE booking
    SET status = 'cancelled'
    WHERE status = 'pending'
      AND TIMESTAMPDIFF(MINUTE, created_at, ?) > 20
";
$stmt = $conn->prepare($sql_cancel);
$stmt->bind_param('s', $now);
$stmt->execute();
$stmt->close();

// 🏨 2. เปลี่ยนเป็น 'checked_in' ถ้าวันนี้อยู่ในช่วงเข้าพัก
$sql_checkin = "
    UPDATE booking
    SET status = 'checked_in'
    WHERE status IN ('confirmed', 'pending')
      AND CURDATE() >= checkIn
      AND CURDATE() < checkOut
";
$conn->query($sql_checkin);

// 🧳 3. เปลี่ยนเป็น 'checked_out' ถ้าวันนี้เลยวัน checkOut แล้ว
$sql_checkout = "
    UPDATE booking
    SET status = 'checked_out'
    WHERE status IN ('checked_in', 'confirmed')
      AND CURDATE() >= checkOut
";
$conn->query($sql_checkout);
?>
