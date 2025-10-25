<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include_once("connectdb.php");
session_start();

/* ===== helper: แทนค่า {{key}} ใน template ===== */
function parseTemplate($text, $data) {
    foreach ($data as $key => $val) {
        $text = str_replace("{{".$key."}}", htmlspecialchars($val ?? ''), $text);
    }
    return $text;
}

/* ตรวจสอบ method */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

/* ✅ รับค่าการจอง */
$b_id = isset($_POST['b_id']) ? (int)$_POST['b_id'] : 0;
if (!$b_id) die("ไม่พบรหัสการจอง");

/* ✅ ตรวจสอบว่ายังไม่ชำระ */
$check = $conn->prepare("SELECT * FROM booking WHERE b_id=?");
$check->bind_param("i", $b_id);
$check->execute();
$booking = $check->get_result()->fetch_assoc();
$check->close();

if (!$booking) die("❌ ไม่พบข้อมูลการจอง");
if ($booking['status'] === 'paid') {
    echo "<script>alert('รายการนี้ชำระเงินแล้ว');window.location='index.php';</script>";
    exit;
}

/* ✅ อัปโหลดสลิป */
$slipFile = null;
if (!empty($_FILES['slip']['name'])) {
    $ext = strtolower(pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf','webp'])) {
        die("❌ ชนิดไฟล์ไม่ถูกต้อง");
    }
    $slipFile = "slip_" . time() . "_" . mt_rand(1000,9999) . "." . $ext;
    $dest = __DIR__ . "/assets/slips/";
    if (!is_dir($dest)) mkdir($dest, 0777, true);
    move_uploaded_file($_FILES['slip']['tmp_name'], $dest . $slipFile);
}

/* ✅ อัปเดตสถานะ */
$update = $conn->prepare("UPDATE booking SET status='paid', slip=? WHERE b_id=?");
$update->bind_param("si", $slipFile, $b_id);
$update->execute();
$update->close();

/* ✅ โหลด email config */
$config = [];
$res = $conn->query("SELECT config_key, config_value FROM email_config");
while ($row = $res->fetch_assoc()) {
    $config[$row['config_key']] = $row['config_value'];
}
$res->close();

/* ตั้งค่า SMTP */
$smtp_user   = $config['smtp_user']      ?? 'example@gmail.com';
$smtp_pass   = $config['smtp_pass']      ?? '';
$from_name   = $config['smtp_from_name'] ?? 'Tharapana Resort';
$admin_email = $config['admin_email']    ?? $smtp_user;

/* ✅ โหลด template อีเมล */
$templates = [];
$res = $conn->query("SELECT template_key, subject, body FROM email_template");
while ($row = $res->fetch_assoc()) {
    $templates[$row['template_key']] = $row;
}
$res->close();

/* ✅ placeholders สำหรับแทนในเทมเพลต */
$data = [
    'b_id'      => $booking['b_id'],
    'firstname' => $booking['firstname'],
    'lastname'  => $booking['lastname'],
    'email'     => $booking['email'],
    'phone'     => $booking['phone'],
    'checkin'   => $booking['checkin'] ?? '',
    'checkout'  => $booking['checkout'] ?? '',
    'rooms'     => $booking['rooms'],
    'adults'    => $booking['adults'],
    'children'  => $booking['children'],
    'note'      => $booking['note'] ?? '',
    'total'     => number_format((float)$booking['total_price'], 2)
];

/* ✅ ฟังก์ชันสร้าง mailer */
function make_mailer($user, $pass) {
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host = 'smtp.gmail.com';
    $m->SMTPAuth = true;
    $m->Username = $user;
    $m->Password = $pass;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port = 587;
    $m->CharSet = "UTF-8";
    return $m;
}

try {
    /* ---------- ส่งถึงลูกค้า ---------- */
    $mailUser = make_mailer($smtp_user, $smtp_pass);
    $mailUser->setFrom($smtp_user, $from_name);
    $mailUser->addAddress($booking['email'], "{$booking['firstname']} {$booking['lastname']}");

    if (isset($templates['booking_user'])) {
        $tpl = $templates['booking_user'];
        $subject = parseTemplate($tpl['subject'], $data);
        $mailUser->Subject = "{$subject} #{$b_id}"; // ✅ เพิ่ม b_id ในหัวเรื่อง
        $mailUser->Body    = parseTemplate($tpl['body'], $data);
    } else {
        $mailUser->Subject = "ยืนยันการชำระเงิน #{$b_id} - {$from_name}";
        $mailUser->Body = "สวัสดีคุณ {$booking['firstname']} {$booking['lastname']} ยอดชำระ {$data['total']} บาท";
    }

    if ($slipFile) {
        $mailUser->addAttachment(__DIR__ . "/assets/slips/" . $slipFile);
    }
    $mailUser->send();

    /* ---------- ส่งถึงแอดมิน ---------- */
    $mailAdmin = make_mailer($smtp_user, $smtp_pass);
    $mailAdmin->setFrom($smtp_user, $from_name);
    $mailAdmin->addAddress($admin_email, 'Admin '.$from_name);

    if (isset($templates['booking_admin'])) {
        $tpl = $templates['booking_admin'];
        $subject = parseTemplate($tpl['subject'], $data);
        $mailAdmin->Subject = "{$subject} #{$b_id}"; // ✅ เพิ่ม b_id ในหัวเรื่อง
        $mailAdmin->Body    = parseTemplate($tpl['body'], $data);
    } else {
        $mailAdmin->Subject = "มีการชำระเงินใหม่ #{$b_id}";
        $mailAdmin->Body = "ลูกค้า {$booking['firstname']} {$booking['lastname']} ชำระเงิน {$data['total']} บาท";
    }

    if ($slipFile) {
        $mailAdmin->addAttachment(__DIR__ . "/assets/slips/" . $slipFile);
    }
    $mailAdmin->send();

    echo "<script>alert('✅ ชำระเงินสำเร็จและส่งอีเมลเรียบร้อย');window.location='index.php';</script>";

} catch (Exception $e) {
    echo "❌ การส่งอีเมลล้มเหลว: {$e->getMessage()}";
}
$conn->close();
?>




