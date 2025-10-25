<?php 
use PHPMailer\PHPMailer\PHPMailer; 
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include_once("connectdb.php");
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rt_id    = $_POST['rt_id'];
    $checkin  = $_POST['checkin'];
    $checkout = $_POST['checkout'];
    $rooms    = $_POST['rooms'];
    $adults   = $_POST['adults'];
    $children = $_POST['children'];
    $total    = $_POST['total_price'];

    // ✅ ส่วนคูปอง
    $coupon_code = $_POST['coupon_code'] ?? null;
    $discount    = (float)($_POST['discount'] ?? 0);
    $final_price = $total - $discount; // ✅ ราคาหลังหักส่วนลด

    $firstname = $_POST['firstname'];
    $lastname  = $_POST['lastname'];
    $email     = $_POST['email'];
    $phone     = $_POST['phone'];
    $note      = $_POST['note'];

    // ✅ ดึง user_id ถ้ามี (ล็อกอิน)
    $user_id = $_SESSION['user_id'] ?? null;

    // ✅ วิธีการชำระเงิน
    $payment_method = $_POST['payment_method'] ?? 'pay_on_arrival';

    // ✅ ตั้งค่า expire_at 20 นาที
    $expire_at = date("Y-m-d H:i:s", strtotime("+20 minutes"));
    $status = "pending"; // รอการชำระเงิน

    // ✅ เพิ่ม final_price ลงใน booking table
    $sql = "INSERT INTO booking 
        (rt_id, checkin, checkout, rooms, adults, children, total_price, discount, final_price, coupon_code, status,
         firstname, lastname, email, phone, note, user_id, payment_method, expire_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "issiiiddsdssssssiss",
        $rt_id, $checkin, $checkout, $rooms, $adults, $children,
        $total, $discount, $final_price, $coupon_code, $status,
        $firstname, $lastname, $email, $phone, $note,
        $user_id, $payment_method, $expire_at
    );

    if ($stmt->execute()) {
        $b_id = $conn->insert_id; 

        // ✅ ถ้ามีคูปอง → เพิ่มจำนวนการใช้
        if ($coupon_code) {
            $conn->query("UPDATE coupons SET used_count = used_count + 1 WHERE code = '$coupon_code'");
        }

        // ➡️ ไปหน้า payment.php
        header("Location: payment.php?b_id=" . $b_id);
        exit;
    } else {
        echo "❌ เกิดข้อผิดพลาด: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: search.php");
    exit;
}
?>








