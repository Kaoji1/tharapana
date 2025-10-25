<?php
session_start();
require_once "connectdb.php";

// 🔒 ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION["user_id"])) {
  header("Location: ReandLog.php");
  exit;
}

$user_id = (int)$_SESSION["user_id"];
$update_status = "";

// --- บันทึกการแก้ไขโปรไฟล์ ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST['action'] ?? '';
  if ($action === 'update_profile') {
    $new_first_name = trim($_POST['first_name'] ?? '');
    $new_last_name  = trim($_POST['last_name'] ?? '');
    $new_phone      = trim($_POST['phone'] ?? '');
    $stmt_update = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, updated_at=NOW() WHERE user_id=?");
    $stmt_update->bind_param("sssi", $new_first_name, $new_last_name, $new_phone, $user_id);
    if ($stmt_update->execute()) {
      $_SESSION['temp_first_name'] = $new_first_name;
      $_SESSION['temp_last_name']  = $new_last_name;
      $_SESSION['temp_phone']      = $new_phone;
      $update_status = '<div class="alert alert-success mt-3"><i class="bi bi-check-circle-fill"></i> บันทึกข้อมูลเรียบร้อย</div>';
    } else {
      $update_status = '<div class="alert alert-danger mt-3"><i class="bi bi-x-octagon-fill"></i> บันทึกไม่สำเร็จ</div>';
    }
    $stmt_update->close();
  }
}

// --- ดึงข้อมูลผู้ใช้ ---
$sqlUser = "SELECT user_id, first_name, last_name, email, phone, role, created_at FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_from_db = $stmt->get_result()->fetch_assoc();
$stmt->close();

$user['first_name'] = $_SESSION['temp_first_name'] ?? $user_from_db['first_name'];
$user['last_name']  = $_SESSION['temp_last_name'] ?? $user_from_db['last_name'];
$user['phone']      = $_SESSION['temp_phone'] ?? $user_from_db['phone'];
$user['email']      = $user_from_db['email'];
$user['role']       = $user_from_db['role'];
$user['created_at'] = $user_from_db['created_at'];

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_th = ($user['role'] === 'admin') ? 'แอดมิน' : 'ผู้ใช้งาน';

// --- ดึงข้อมูลการจอง ---
$limitBooking = 5;
$pageBooking = isset($_GET['pageBooking']) ? max(1, (int)$_GET['pageBooking']) : 1;
$offsetBooking = ($pageBooking - 1) * $limitBooking;
$stmt = $conn->prepare("
  SELECT b.*, rt.rt_name 
  FROM booking b
  JOIN room_type rt ON b.rt_id = rt.rt_id
  WHERE b.user_id = ? ORDER BY b.created_at DESC
  LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $limitBooking, $offsetBooking);
$stmt->execute();
$bookings = $stmt->get_result();
$stmt->close();

$totalBooking = $conn->query("SELECT COUNT(*) AS total FROM booking WHERE user_id=$user_id")->fetch_assoc()['total'];
$totalPagesBooking = ceil($totalBooking / $limitBooking);

// --- ดึงรีวิว ---
$limitReview = 5;
$pageReview = isset($_GET['pageReview']) ? max(1, (int)$_GET['pageReview']) : 1;
$offsetReview = ($pageReview - 1) * $limitReview;
$stmt = $conn->prepare("
  SELECT r.rating, r.comment, r.created_at, rt.rt_name
  FROM reviews r
  JOIN booking b ON r.b_id=b.b_id
  JOIN room_type rt ON b.rt_id=rt.rt_id
  WHERE b.user_id=? ORDER BY r.created_at DESC
  LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $limitReview, $offsetReview);
$stmt->execute();
$reviews = $stmt->get_result();
$stmt->close();

$totalReview = $conn->query("
  SELECT COUNT(*) AS total FROM reviews r
  JOIN booking b ON r.b_id=b.b_id
  WHERE b.user_id=$user_id")->fetch_assoc()['total'];
$totalPagesReview = ceil($totalReview / $limitReview);
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>โปรไฟล์ของฉัน | Tharapana</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    body {
      background: #f9fbfc;
      font-family: "Prompt", sans-serif;
      margin-top: 100px;
      color: #37474f;
    }

    .profile-hero {
      background: linear-gradient(135deg, rgba(0, 150, 136, 0.9), rgba(0, 121, 107, 0.9));
      color: #fff;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    }

    .mini-stat {
      background: #fff;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      text-align: center;
    }

    .mini-stat i {
      font-size: 24px;
      color: #00796b;
    }

    .table-custom {
      border-radius: 16px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    }

    .table-custom th {
      background: #00796b;
      color: #fff;
      text-align: center;
      vertical-align: middle;
    }

    .table-custom td {
      vertical-align: middle;
      text-align: center;
    }

    .btn-review {
      background: #ffca28;
      border: none;
      color: #333;
      font-weight: 500;
    }

    .btn-review:hover {
      background: #ffb300;
      color: #fff;
    }

    .badge {
      font-size: 0.9rem;
    }
  </style>
</head>

<body>
  <?php include "header.php"; ?>
  <main class="container mt-4">
    <?= $update_status ?>
    <div class="profile-hero d-flex flex-wrap justify-content-between align-items-start mb-4">
      <div>
        <h2 class="fw-bold mb-2">สวัสดี, <?= htmlspecialchars($fullName ?: 'ผู้ใช้งาน') ?></h2>
        <p class="mb-1"><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
        <p><i class="bi bi-phone"></i> <?= htmlspecialchars($user['phone'] ?: '-') ?></p>
      </div>
      <div class="d-flex flex-column flex-lg-row gap-2">
        <button class="btn btn-light text-success" data-bs-toggle="modal" data-bs-target="#editProfileModal"
          data-first_name="<?= htmlspecialchars($user['first_name']) ?>"
          data-last_name="<?= htmlspecialchars($user['last_name']) ?>"
          data-phone="<?= htmlspecialchars($user['phone']) ?>">
          <i class="bi bi-pencil-square"></i> แก้ไขโปรไฟล์
        </button>
        <a href="logout.php" class="btn btn-warning text-dark"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-3 col-6">
        <div class="mini-stat"><i class="bi bi-calendar-check"></i>
          <div>การจอง<br><strong><?= (int)$totalBooking ?> ครั้ง</strong></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="mini-stat"><i class="bi bi-star"></i>
          <div>รีวิว<br><strong><?= (int)$totalReview ?> รายการ</strong></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="mini-stat"><i class="bi bi-clock-history"></i>
          <div>สมัครเมื่อ<br><strong><?= date('d M Y', strtotime($user['created_at'])) ?></strong></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="mini-stat"><i class="bi bi-award"></i>
          <div>สิทธิ์<br><strong><?= htmlspecialchars($role_th) ?></strong></div>
        </div>
      </div>
    </div>

    <!-- ตารางการจอง -->
    <div class="card mb-4 border-0 table-custom">
      <div class="card-header bg-white fw-bold py-3"><i class="bi bi-journal-check"></i> ประวัติการจอง</div>
      <div class="card-body">
        <?php if ($bookings->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>ประเภทห้อง</th>
                  <th>เช็คอิน</th>
                  <th>เช็คเอาท์</th>
                  <th>ห้อง</th>
                  <th>ผู้ใหญ่</th>
                  <th>เด็ก</th>
                  <th>ยอดรวม</th>
                  <th>สลิป</th>
                  <th>สถานะ</th>
                  <th>รีวิว</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($b = $bookings->fetch_assoc()):
                  $today = date("Y-m-d");
                  if ($b["status"] === "cancelled" || $b["status"] === "expired") {
                    $text = "ยกเลิก";
                    $badge = "danger";
                  } elseif ($b["checkout"] < $today) {
                    $text = "เข้าพักแล้ว";
                    $badge = "success";
                  } elseif ($b["checkin"] > $today) {
                    $text = "รอเข้าพัก";
                    $badge = "warning";
                  } else {
                    $text = "กำลังเข้าพัก";
                    $badge = "info";
                  }
                  $hasReview = $conn->query("SELECT 1 FROM reviews WHERE b_id=" . (int)$b['b_id'] . " LIMIT 1")->num_rows > 0;
                ?>
                  <tr>
                    <td><?= htmlspecialchars($b["rt_name"]) ?></td>
                    <td><?= htmlspecialchars($b["checkin"]) ?></td>
                    <td><?= htmlspecialchars($b["checkout"]) ?></td>
                    <td><?= (int)$b["rooms"] ?></td>
                    <td><?= (int)$b["adults"] ?></td>
                    <td><?= (int)$b["children"] ?></td>
                    <td><?= number_format((float)$b["total_price"], 2) ?> บาท</td>
                    <td>
                      <?php if (!empty($b["slip"])): ?>
                        <a href="assets/slips/<?= htmlspecialchars($b["slip"]) ?>" target="_blank" class="text-decoration-none text-primary fw-bold">ดูสลิป</a>
                      <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $badge ?>"><?= $text ?></span></td>
                    <td>
                      <?php if ($b["checkout"] < $today && !$hasReview): ?>
                        <button class="btn btn-sm btn-review" data-bs-toggle="modal" data-bs-target="#reviewModal"
                          data-bid="<?= $b['b_id'] ?>" data-room="<?= htmlspecialchars($b['rt_name']) ?>">เขียนรีวิว</button>
                      <?php elseif ($hasReview): ?>
                        <span class="text-success">✔ รีวิวแล้ว</span>
                      <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?><p class="text-muted text-center">ยังไม่มีการจอง</p><?php endif; ?>
      </div>
    </div>

    <!-- รีวิว -->
    <div class="card border-0 table-custom">
      <div class="card-header bg-white fw-bold py-3"><i class="bi bi-chat-left-heart"></i> รีวิวของฉัน</div>
      <div class="card-body">
        <?php if ($reviews->num_rows > 0): while ($r = $reviews->fetch_assoc()): ?>
            <div class="border-bottom mb-3 pb-2">
              <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($r['rt_name']) ?></strong><small><?= htmlspecialchars($r['created_at']) ?></small></div>
              <p class="text-warning mb-1"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= $r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i><?php endfor; ?></p>
              <p><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
            </div>
          <?php endwhile;
        else: ?>
          <p class="text-muted text-center">ยังไม่มีรีวิว</p>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Modal แก้ไขโปรไฟล์ -->
  <div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <input type="hidden" name="action" value="update_profile">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil"></i> แก้ไขโปรไฟล์</h5>
          </div>
          <div class="modal-body">
            <div class="mb-3"><label>ชื่อ</label><input type="text" class="form-control" id="edit_first_name" name="first_name" required></div>
            <div class="mb-3"><label>นามสกุล</label><input type="text" class="form-control" id="edit_last_name" name="last_name" required></div>
            <div class="mb-3"><label>เบอร์โทร</label><input type="tel" class="form-control" id="edit_phone" name="phone" pattern="[0-9]{10}" required></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-success">บันทึก</button></div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal รีวิว -->
  <div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="add_review.php" method="post" id="reviewForm">
          <div class="modal-header">
            <h5 class="modal-title">เขียนรีวิว <span id="roomName"></span></h5>
          </div>
          <div class="modal-body">
            <input type="hidden" name="b_id" id="modal_b_id">
            <input type="hidden" name="rating" id="ratingInput" value="0">
            <label>ให้คะแนน:</label>
            <div class="star-rating mb-3">
              <i class="bi bi-star" data-value="1"></i>
              <i class="bi bi-star" data-value="2"></i>
              <i class="bi bi-star" data-value="3"></i>
              <i class="bi bi-star" data-value="4"></i>
              <i class="bi bi-star" data-value="5"></i>
            </div>
            <label>ความคิดเห็น:</label>
            <textarea name="comment" class="form-control" rows="3" required></textarea>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-success">บันทึกรีวิว</button></div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const stars = document.querySelectorAll(".star-rating i");
      const ratingInput = document.getElementById("ratingInput");
      stars.forEach(star => {
        star.addEventListener("click", () => {
          const rating = star.getAttribute("data-value");
          ratingInput.value = rating;
          stars.forEach(s => {
            s.classList.toggle("bi-star-fill", s.getAttribute("data-value") <= rating);
            s.classList.toggle("bi-star", s.getAttribute("data-value") > rating);
          });
        });
      });
      const reviewModal = document.getElementById('reviewModal');
      reviewModal.addEventListener('show.bs.modal', event => {
        const btn = event.relatedTarget;
        document.getElementById('modal_b_id').value = btn.get
        document.getElementById('modal_b_id').value = btn.getAttribute('data-bid');
        document.getElementById('roomName').textContent = btn.getAttribute('data-room');
        ratingInput.value = 0;
        stars.forEach(s => {
          s.classList.remove('bi-star-fill');
          s.classList.add('bi-star');
        });
      });
    });
  </script>
</body>

</html>