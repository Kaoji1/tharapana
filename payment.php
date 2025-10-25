<?php
include_once("connectdb.php");
session_start();

$b_id = $_GET['b_id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM booking WHERE b_id=?");
$stmt->bind_param("i", $b_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die("❌ ไม่พบรหัสการจอง");
}

$now = date("Y-m-d H:i:s");

// 🕐 ตรวจสอบว่าหมดเวลาชำระหรือยัง
if ($booking['expire_at'] && $now > $booking['expire_at'] && $booking['status'] === 'pending') {
    $conn->query("UPDATE booking SET status='expired' WHERE b_id=" . (int)$b_id);
    echo "<script>alert('หมดเวลาชำระเงินแล้ว'); window.location='index.php';</script>";
    exit;
}

// 🕒 เวลาเหลือ
if (empty($booking['expire_at'])) {
    $remain = 0;
} else {
    $expire_ts = strtotime($booking['expire_at']);
    $remain = max(0, $expire_ts - time());
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ชำระเงิน | Tharapana Resort</title>
  <link rel="stylesheet" href="assets/css/payment.css">
  <style>
    body { font-family: "Prompt", sans-serif; background:#f9fafb; margin:0; padding:0; }
    .container { max-width:600px; margin:40px auto; background:#fff; border-radius:16px;
        padding:30px; box-shadow:0 4px 20px rgba(0,0,0,.1); text-align:center; }
    h1 { color:#0f8b79; margin-bottom:10px; }
    .price { font-size:26px; font-weight:bold; margin:20px 0; }
    .qr-box { margin:20px 0; }
    #countdown { font-size:22px; color:#dc2626; margin:20px 0; }
    button { background:#0f8b79; color:#fff; border:none; padding:12px 20px; border-radius:8px;
        font-size:16px; cursor:pointer; transition:0.2s; }
    button:hover { background:#0d6e63; }
    input[type=file] { margin:10px 0; }
  </style>
  <script>
    let remain = <?= $remain ?>;
    function startTimer() {
        let timer = setInterval(() => {
            let m = Math.floor(remain / 60);
            let s = remain % 60;
            document.getElementById("countdown").innerText =
                m.toString().padStart(2,"0") + ":" + s.toString().padStart(2,"0");
            if (remain <= 0) {
                clearInterval(timer);
                alert("หมดเวลาชำระเงินแล้ว");
                window.location = "index.php";
            }
            remain--;
        }, 1000);
    }
    window.onload = startTimer;

    function disableButton(){
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerText = '⏳ กำลังอัปโหลด...';
    }
  </script>
</head>
<body>
  <div class="container">
    <h1>ชำระเงิน</h1>
    <p>โปรดสแกน QR Code เพื่อชำระเงิน</p>

    <?php
      // ✅ ใช้ราคาหลังหักส่วนลด (ถ้ามี)
      $amountToPay = $booking['final_price'] ?: $booking['total_price'];
    ?>
    <div class="price"><?= number_format($amountToPay, 2) ?> บาท</div>

    <div class="qr-box">
      <?php
      $promptpayID = "1309801469108"; // 🔸ใส่เลขพร้อมเพย์ของรีสอร์ต
      $qrUrl = "https://promptpay.io/$promptpayID/$amountToPay";
      echo "<img src='$qrUrl' alt='QR PromptPay' width='250'>";
      ?>
    </div>

    <div id="countdown"></div>

    <form id="payForm" action="payment_success.php" method="post" enctype="multipart/form-data" onsubmit="disableButton()">
      <input type="hidden" name="b_id" value="<?= $booking['b_id'] ?>">
      <p><input type="file" name="slip" accept="image/*,application/pdf" required></p>
      <button id="submitBtn" type="submit">อัปโหลดสลิป & ยืนยันการชำระเงิน</button>
    </form>
  </div>
</body>
</html>



