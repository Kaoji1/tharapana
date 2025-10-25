<?php
require_once "connectdb.php";

// 📌 ดึงรายการประเภทห้องทั้งหมด (สำหรับ filter dropdown)
$sqlRooms = "SELECT rt_id, rt_name FROM room_type ORDER BY rt_name";
$rooms = $conn->query($sqlRooms);

// 📌 ตรวจสอบว่าผู้ใช้เลือกกรองห้องหรือไม่
$filter_rt_id = $_GET['rt_id'] ?? "";

// 📌 ดึงข้อมูลรีวิวทั้งหมด (หรือเฉพาะที่กรอง)
$sqlReview = "
    SELECT r.rating, r.comment, r.created_at, 
           u.first_name, u.last_name, rt.rt_name
    FROM reviews r
    JOIN booking b ON r.b_id = b.b_id
    JOIN users u ON b.user_id = u.user_id
    JOIN room_type rt ON b.rt_id = rt.rt_id
";
if (!empty($filter_rt_id)) {
    $sqlReview .= " WHERE rt.rt_id = ?";
}
$sqlReview .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sqlReview);
if (!empty($filter_rt_id)) {
    $stmt->bind_param("i", $filter_rt_id);
}
$stmt->execute();
$reviews = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>รีวิวจากผู้เข้าพัก | Tharapana</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f5f7fa; }
    .review-header {
      background: linear-gradient(135deg, #00796b, #004d40);
      color: white;
      text-align: center;
      padding: 40px 20px;
      border-radius: 0 0 25px 25px;
      margin-bottom: 30px;
    }
    .review-card {
      background: white;
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .rating-stars { color: #f4b400; font-size: 1.4rem; }
    .filter-box {
      background: white;
      border-radius: 10px;
      padding: 15px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
<div class="container">

  <div class="review-header">
    <h2>รีวิวจากผู้เข้าพัก</h2>
    <p>เสียงตอบรับจากลูกค้าที่เคยเข้าพักกับ Tharapana</p>
  </div>

  <!-- 🔍 ตัวกรองประเภทห้อง -->
  <div class="filter-box">
    <form method="get" action="reviews.php" class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label mb-0">เลือกประเภทห้อง:</label>
        <select name="rt_id" class="form-select" onchange="this.form.submit()">
          <option value="">ทั้งหมด</option>
          <?php while($room = $rooms->fetch_assoc()): ?>
            <option value="<?= $room['rt_id'] ?>" <?= ($filter_rt_id == $room['rt_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($room['rt_name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    </form>
  </div>

  <!-- 📝 แสดงรีวิวทั้งหมด -->
  <?php if ($reviews->num_rows > 0): ?>
    <?php while($r = $reviews->fetch_assoc()): ?>
      <div class="review-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0 text-success"><?= htmlspecialchars($r["rt_name"]) ?></h5>
          <span class="text-muted small"><?= htmlspecialchars(date("d M Y", strtotime($r["created_at"]))) ?></span>
        </div>
        <p class="rating-stars mb-1">
          <?php for($i=1;$i<=5;$i++): ?>
            <?= $i <= $r["rating"] ? "★" : "☆" ?>
          <?php endfor; ?>
        </p>
        <p class="mb-2"><?= nl2br(htmlspecialchars($r["comment"])) ?></p>
        <small class="text-muted">โดย <?= htmlspecialchars($r["first_name"] . " " . $r["last_name"]) ?></small>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="alert alert-info text-center">ยังไม่มีรีวิวในหมวดนี้</div>
  <?php endif; ?>

</div>
</body>
</html>
