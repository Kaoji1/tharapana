<?php
include_once("connectdb.php");
session_start();

/* ====== รับพารามิเตอร์จากฟอร์ม ====== */
$checkin   = $_GET['checkin']   ?? '';
$checkout  = $_GET['checkout']  ?? '';
$rooms     = isset($_GET['rooms'])    ? (int)$_GET['rooms']    : 1;
$adults    = isset($_GET['adults'])   ? (int)$_GET['adults']   : 2;
$children  = isset($_GET['children']) ? (int)$_GET['children'] : 0;

/* เก็บลง session */
$_SESSION['search'] = [
  'checkin'  => $checkin,
  'checkout' => $checkout,
  'rooms'    => $rooms,
  'adults'   => $adults,
  'children' => $children,
];

/* ถ้าไม่มีวันที่ */
if (!$checkin || !$checkout) {
  echo "<!doctype html><meta charset='utf-8'><link rel='stylesheet' href='search.css?v=4'><div class='container'><div class='empty'>กรุณาเลือกวันที่เข้าพักและวันที่ออกก่อน</div></div>";
  exit;
}

$sql = "
SELECT
  rt.rt_id,
  rt.rt_name,
  rt.price,

  -- ✅ คำนวณจำนวนห้องว่างโดยดูจาก admin_manage_opening ด้วย
  GREATEST(
    IFNULL(
      (SELECT o.open_rooms 
       FROM room_opening o 
       WHERE o.rt_id = rt.rt_id 
         AND o.date BETWEEN ? AND DATE_SUB(?, INTERVAL 1 DAY)
       ORDER BY o.date DESC LIMIT 1),
      COUNT(r.room_id)
    )
    -
    IFNULL((
      SELECT SUM(b.rooms)
      FROM booking b
      WHERE b.rt_id = rt.rt_id
        AND b.status <> 'cancelled'
        AND NOT (b.checkout <= ? OR b.checkin >= ?)
    ), 0),
    0
  ) AS left_rooms,

  ri.r_images AS img_path
FROM room_type rt
JOIN rooms r ON r.rt_id = rt.rt_id
LEFT JOIN (
  SELECT x.rt_id, i.r_images
  FROM (
    SELECT rt_id, MIN(rm_id) AS any_img_id
    FROM room_images
    GROUP BY rt_id
  ) x
  JOIN room_images i ON i.rm_id = x.any_img_id
) ri ON ri.rt_id = rt.rt_id
GROUP BY rt.rt_id, rt.rt_name, rt.price, ri.r_images
ORDER BY rt.rt_id;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $checkin, $checkout, $checkin, $checkout);
$stmt->execute();
$res = $stmt->get_result();


$IMG_BASE = 'assets/img/';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ผลลัพธ์การค้นหา | Tharapana</title>
  <link rel="stylesheet" href="search.css?v=4">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .avg-rating { font-size: 1rem; color: #f1c40f; margin-top: 5px; }
    .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
    .modal-content { background:#fff; margin:5% auto; padding:20px; border-radius:12px; width:90%; max-width:700px; position:relative; }
    .close { position:absolute; top:10px; right:15px; cursor:pointer; font-size:22px; color:#666; }
    .reviews { margin-top:15px; background:#fafafa; border-radius:8px; padding:10px 15px; }
    .review-list li { margin-bottom:8px; border-bottom:1px solid #eee; padding-bottom:6px; }
    .review-list li:last-child { border-bottom:none; }
  </style>
</head>

<body>
<header class="header">
  <div class="wrap">
    <div class="brand">Tharapana – ผลลัพธ์การค้นหา</div>
    <span class="badge">
      <span>🗓️ <?= htmlspecialchars($checkin) ?> → <?= htmlspecialchars($checkout) ?></span>
      <small>•</small>
      <span>👥 <?= (int)$adults ?> ผู้ใหญ่ · <?= (int)$children ?> เด็ก</span>
      <small>•</small>
      <span>🛏️ <?= (int)$rooms ?> ห้อง</span>
    </span>
    <span class="spacer"></span>
    <a class="btn-outline" href="index.php#search">แก้ไขการค้นหา</a>
  </div>
</header>

<main class="container">
  <div class="results-title">ประเภทห้องที่ว่างในวันที่เลือก</div>
  <?php
  $hasAny = false;
  $cards = [];

  while ($row = $res->fetch_assoc()) {
    $hasAny = true;
    $rt_id = (int)$row['rt_id'];
    $name  = htmlspecialchars($row['rt_name']);
    $price = number_format((float)$row['price'], 0);
    $left  = max(0, (int)$row['left_rooms']);
    $img   = $row['img_path'] ? $IMG_BASE . rawurlencode($row['img_path']) : null;

    $enough = ($left >= $rooms);
    $params = http_build_query([
      'rt_id'    => $rt_id,
      'checkin'  => $checkin,
      'checkout' => $checkout,
      'rooms'    => $rooms,
      'adults'   => $adults,
      'children' => $children,
    ]);

    $chipClass = $left === 0 ? 'left-chip low' : ($left <= 2 ? 'left-chip low' : 'left-chip ok');
    $leftText  = $left === 0 ? "เต็มแล้ว" : "เหลือ $left ห้อง";

    /* สิ่งอำนวยความสะดวก */
    $features = [];
    $qf = $conn->query("SELECT rin_name FROM room_into WHERE rt_id=$rt_id");
    while ($f = $qf->fetch_assoc()) {
      $features[] = $f['rin_name'];
    }

    /* สิทธิประโยชน์ */
    $benefits = [];
    $qben = $conn->query("SELECT ben_icon, ben_text FROM booking_benefits WHERE rt_id=$rt_id");
    while ($b = $qben->fetch_assoc()) {
      $benefits[] = $b;
    }

    /* รีวิว + คะแนนเฉลี่ย */
    $reviewAvg = null;
    $reviewList = [];
    $qrev = $conn->query("SELECT rating, name, comment, created_at 
                          FROM reviews 
                          WHERE rt_id=$rt_id 
                          ORDER BY created_at DESC 
                          LIMIT 3");
    if ($qrev->num_rows > 0) {
      $qavg = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE rt_id=$rt_id");
      $reviewAvg = $qavg->fetch_assoc();
      while ($rv = $qrev->fetch_assoc()) {
        $reviewList[] = $rv;
      }
    }

    ob_start(); ?>
    <article class="card">
      <?php if ($img): ?>
        <img src="<?= htmlspecialchars($img) ?>" alt="<?= $name ?>" class="card-media-img">
      <?php else: ?>
        <div class="card-media">ภาพประเภทห้อง</div>
      <?php endif; ?>

      <div class="card-body">
        <div class="card-title">
          <h3><?= $name ?></h3>
          <span class="<?= $chipClass ?>"><?= $leftText ?></span>
        </div>

        <?php if ($reviewAvg): ?>
          <div class="avg-rating">
            ⭐ <?= number_format($reviewAvg['avg_rating'], 1) ?>/5 (<?= $reviewAvg['total'] ?> รีวิว)
          </div>
        <?php endif; ?>

        <div class="meta">
          วันที่: <?= htmlspecialchars($checkin) ?> → <?= htmlspecialchars($checkout) ?> ·
          ผู้เข้าพัก: <?= (int)$adults ?> ผู้ใหญ่ · <?= (int)$children ?> เด็ก ·
          ขอ <?= (int)$rooms ?> ห้อง
        </div>

        <div class="cta">
          <div class="price">ราคาเริ่มต้น: <?= $price ?> บาท/คืน</div>
          <div class="actions">
            <button type="button" class="btn-outline btn-detail" data-rt="<?= $rt_id ?>">รายละเอียดที่พัก</button>
            <?php if ($left > 0): ?>
              <?php if ($enough): ?>
                <a class="btn" href="booking.php?<?= $params ?>">จองประเภทนี้</a>
              <?php else: ?>
                <button class="btn" disabled>ห้องไม่พอสำหรับ <?= (int)$rooms ?> ห้อง</button>
                <span class="note-warn">เหลือเพียง <?= $left ?> ห้อง</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="note-warn">ไม่มีห้องว่างในวันที่คุณเลือก</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </article>

    <!-- 🔍 Modal รายละเอียด -->
    <div id="modal-<?= $rt_id ?>" class="modal">
      <div class="modal-content">
        <span class="close" data-close="<?= $rt_id ?>">&times;</span>
        <h2><?= $name ?></h2>
        <p><strong>ราคา:</strong> <?= $price ?> บาท/คืน</p>
        <?php if ($img): ?>
          <img src="<?= htmlspecialchars($img) ?>" alt="<?= $name ?>" style="width:100%;border-radius:8px;margin:10px 0;">
        <?php endif; ?>
        <?php if (!empty($features)): ?>
          <h3>สิ่งอำนวยความสะดวก</h3>
          <ul><?php foreach ($features as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if (!empty($benefits)): ?>
          <h3>สิทธิประโยชน์</h3>
          <ul><?php foreach ($benefits as $b): ?><li><i class="fa fa-<?= htmlspecialchars($b['ben_icon']) ?>"></i> <?= htmlspecialchars($b['ben_text']) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($reviewAvg): ?>
          <div class="reviews">
            <h4>รีวิวจากผู้เข้าพัก</h4>
            <div class="avg-rating">⭐ <?= number_format($reviewAvg['avg_rating'], 1) ?>/5 <small>(<?= $reviewAvg['total'] ?> รีวิว)</small></div>
            <ul class="review-list">
              <?php foreach ($reviewList as $rv): ?>
                <li>
                  <div class="stars"><?= str_repeat("★", $rv['rating']) . str_repeat("☆", 5 - $rv['rating']); ?></div>
                  <p><strong><?= htmlspecialchars($rv['name']) ?></strong> : <?= htmlspecialchars($rv['comment']) ?></p>
                  <small><?= $rv['created_at'] ?></small>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    $cards[] = ob_get_clean();
  }

  if (!$hasAny) {
    echo '<div class="empty">ไม่มีห้องว่างในวันที่คุณเลือก</div>';
  } else {
    echo '<section class="grid">' . implode("\n", $cards) . '</section>';
  }

  $stmt->close();
  $conn->close();
  ?>
  <p class="footer-note">* หมายเหตุ: ลูกค้าเลือกเฉพาะ “ประเภทห้อง” พนักงานจะจัดเลขห้องให้ภายหลัง</p>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".btn-detail").forEach(btn => {
    btn.addEventListener("click", () => {
      const rt = btn.dataset.rt;
      document.getElementById("modal-" + rt).style.display = "block";
    });
  });
  document.querySelectorAll(".modal .close").forEach(btn => {
    btn.addEventListener("click", () => {
      const rt = btn.dataset.close;
      document.getElementById("modal-" + rt).style.display = "none";
    });
  });
  window.addEventListener("click", e => {
    if (e.target.classList.contains("modal")) e.target.style.display = "none";
  });
});
</script>

</body>
</html>
