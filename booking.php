<?php
include_once("connectdb.php");
session_start();

/* รับค่าจาก search.php */
$rt_id    = $_GET['rt_id']    ?? 0;
$checkin  = $_GET['checkin']  ?? '';
$checkout = $_GET['checkout'] ?? '';
$rooms    = $_GET['rooms']    ?? 1;
$adults   = $_GET['adults']   ?? 2;
$children = $_GET['children'] ?? 0;

/* ดึงข้อมูลห้อง */
$sqlRoom = "SELECT rt_id, rt_name, price FROM room_type WHERE rt_id=?";
$stmt = $conn->prepare($sqlRoom);
$stmt->bind_param("i", $rt_id);
$stmt->execute();
$res = $stmt->get_result();
$room = $res->fetch_assoc();
$stmt->close();

if (!$room) die("ไม่พบข้อมูลห้องที่เลือก");

/* คำนวณราคารวม */
$days = (strtotime($checkout) - strtotime($checkin)) / (60 * 60 * 24);
if ($days <= 0) $days = 1;

$price_per_night = (float)$room['price'];
$total_price = $price_per_night * $days * $rooms;

/* ✅ ถ้าล็อกอินแล้ว ดึงข้อมูลผู้ใช้ */
$user = null;
if (isset($_SESSION["user_id"])) {
  $uid = $_SESSION["user_id"];
  $sqlUser = "SELECT first_name, last_name, email, phone FROM users WHERE user_id = ?";
  $stmtU = $conn->prepare($sqlUser);
  $stmtU->bind_param("i", $uid);
  $stmtU->execute();
  $user = $stmtU->get_result()->fetch_assoc();
  $stmtU->close();
}
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <title>ยืนยันการจอง | Tharapana</title>
  <link rel="stylesheet" href="booking.css?v=2">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

  <div class="booking-container">
    <!-- แถบขั้นตอน -->
    <div class="steps">
      <span class="active">1 เลือกห้องพัก</span>
      <span class="active">2 กรอกข้อมูล</span>
      <span>3 ยืนยันการจอง</span>
    </div>

    <div class="booking-content">
      <!-- ซ้าย: ข้อมูลห้อง -->
      <aside class="sidebar">
        <h2><?= htmlspecialchars($room['rt_name']) ?></h2>
        <p>วันที่: <?= $checkin ?> → <?= $checkout ?></p>
        <p>ผู้เข้าพัก: <?= $adults ?> ผู้ใหญ่ · <?= $children ?> เด็ก</p>
        <p>จำนวนห้อง: <?= $rooms ?></p>
        <hr>
        <h3>สรุปการชำระเงิน</h3>
        <p>ราคาต่อคืน: <?= number_format($price_per_night) ?> บาท</p>
        <p>คืนทั้งหมด: <?= $days ?> คืน</p>
        <p>ห้องทั้งหมด: <?= $rooms ?> ห้อง</p>
        <h2>ยอดรวม: <span id="total_show"><?= number_format($total_price) ?></span> บาท</h2>

        <div class="coupon-box">
          <label for="coupon_code">รหัสคูปองส่วนลด</label>
          <div class="coupon-input">
            <input type="text" id="coupon_code" placeholder="เช่น THARA100">
            <button type="button" id="applyCoupon">ใช้คูปอง</button>
          </div>
          <p class="coupon-result" id="coupon_result"></p>
        </div>
      </aside>

      <!-- ขวา: ฟอร์มผู้จอง -->
      <main class="form-section">
        <h2>กรอกข้อมูลของท่าน</h2>
        <form method="post" action="confirm.php" id="bookingForm">
          <input type="hidden" name="rt_id" value="<?= $rt_id ?>">
          <input type="hidden" name="checkin" value="<?= $checkin ?>">
          <input type="hidden" name="checkout" value="<?= $checkout ?>">
          <input type="hidden" name="rooms" value="<?= $rooms ?>">
          <input type="hidden" name="adults" value="<?= $adults ?>">
          <input type="hidden" name="children" value="<?= $children ?>">
          <input type="hidden" name="total_price" id="total_price" value="<?= $total_price ?>">
          <input type="hidden" name="coupon_code" id="coupon_code_hidden">
          <input type="hidden" name="discount" id="discount_hidden">

          <?php if (isset($_SESSION['user_id'])): ?>
            <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
          <?php endif; ?>

          <?php if ($user): ?>
            <!-- ✅ กรอกให้อัตโนมัติถ้า login -->
            <label>ชื่อจริง*</label>
            <input type="text" name="firstname" value="<?= htmlspecialchars($user['first_name']) ?>" readonly>

            <label>นามสกุล*</label>
            <input type="text" name="lastname" value="<?= htmlspecialchars($user['last_name']) ?>" readonly>

            <label>อีเมล*</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>

            <label>เบอร์โทรศัพท์*</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" readonly>
          <?php else: ?>
            <!-- ❌ ยังไม่ได้ล็อกอิน → ต้องกรอกเอง -->
            <label>ชื่อจริง*</label>
            <input type="text" name="firstname" required>

            <label>นามสกุล*</label>
            <input type="text" name="lastname" required>

            <label>อีเมล*</label>
            <input type="email" name="email" required>

            <label>เบอร์โทรศัพท์*</label>
            <input type="tel" name="phone" required>
          <?php endif; ?>

          <label>คำขอพิเศษ</label>
          <textarea name="note"></textarea>

          <h3>เลือกวิธีชำระเงิน</h3>
          <label><input type="radio" name="payment_method" value="pay_on_arrival" checked> ชำระที่รีสอร์ท</label><br>
          <label><input type="radio" name="payment_method" value="qr"> ชำระผ่าน QR Code (PromptPay)</label>

          <br><br>
          <button type="submit" class="btn-book">ยืนยันการจอง</button>
        </form>
      </main>
    </div>
  </div>

  <script>
    document.getElementById("applyCoupon").addEventListener("click", function () {
      const code = document.getElementById("coupon_code").value.trim();
      const total = parseFloat(document.getElementById("total_price").value);
      if (!code) {
        Swal.fire("⚠️ โปรดกรอกรหัสคูปองก่อน");
        return;
      }
      fetch("apply_coupon.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `coupon_code=${code}&total=${total}`
      })
        .then(r => r.json())
        .then(res => {
          const msg = document.getElementById("coupon_result");
          if (res.success) {
            msg.style.color = "green";
            msg.innerText = res.message;
            document.getElementById("total_show").innerText = (total - res.discount).toFixed(2);
            document.getElementById("coupon_code_hidden").value = code;
            document.getElementById("discount_hidden").value = res.discount;
            Swal.fire("🎉 ใช้คูปองสำเร็จ!", res.message, "success");
          } else {
            msg.style.color = "red";
            msg.innerText = res.message;
            Swal.fire("❌ ไม่สามารถใช้คูปองได้", res.message, "error");
          }
        });
    });
  </script>

</body>
</html>
