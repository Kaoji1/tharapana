<?php
ob_clean();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// ✅ PromptPay ID
$promptpayID = "1309801469108";
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

// ✅ ฟังก์ชันสร้าง payload พร้อมเพย์
function generatePromptPayPayload($promptpayID, $amount = 0){
    $idType = strlen($promptpayID) === 13 ? "02" : "01";
    $id = strlen($promptpayID) === 13 ? $promptpayID : preg_replace("/^0/", "66", $promptpayID);

    $payload = "000201010211";
    $payload .= "2937";
    $payload .= "0016A000000677010111";
    $payload .= sprintf("0113%s%s", $idType, $id);
    $payload .= "5802TH5303764";

    if($amount > 0){
        $amt = number_format($amount, 2, '.', '');
        $payload .= "54".sprintf("%02d", strlen($amt)).$amt;
    }

    $payload .= "6304";
    return $payload;
}

// ✅ สร้าง payload สำหรับ QR
$payload = generatePromptPayPayload($promptpayID, $amount);

// ✅ ตั้งค่า (ไม่ใช้ QRGdImage โดยตรงแล้ว)
$options = new QROptions([
    'version'    => 7,
    'outputBase64' => false, // สั่งให้ render ออกมาเป็น binary image
    'scale'      => 6,
]);

// ✅ แสดง QR ออกเป็นภาพ PNG
header('Content-Type: image/png');
echo (new QRCode($options))->render($payload);
exit;


