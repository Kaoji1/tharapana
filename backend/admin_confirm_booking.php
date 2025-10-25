<?php
include_once("connectdb.php");
session_start();

// ✅ ตรวจสอบสิทธิ์ admin ก่อนเข้า
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ReandLog.php");
  exit;
}

// ✅ ดึงข้อมูลการจองที่ยังไม่ยืนยัน (pending หรือ NULL)
$sql = "
SELECT 
  b.b_id,
  u.first_name,
  u.last_name,
  rt.rt_name,
  b.checkin,
  b.checkout,
  b.total_price,
  b.created_at,
  b.status
FROM booking b
LEFT JOIN users u ON b.user_id = u.user_id
LEFT JOIN room_type rt ON b.rt_id = rt.rt_id
WHERE b.status = 'pending' OR b.status IS NULL OR b.status = ''
ORDER BY b.created_at ASC
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>🧾 ยืนยันการจองห้องพัก | Tharapana Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafb;
      font-family: 'Sarabun', sans-serif;
      padding: 30px;
    }
    h2 {
      color: #00695c;
      font-weight: 700;
      text-align: center;
      margin-bottom: 25px;
    }
    .table {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      overflow: hidden;
    }
    .status-null { color: #9e9e9e; font-weight: 600; }
    .status-pending { color: #fbc02d; font-weight: 600; }
    .status-confirmed { color: #0288d1; font-weight: 600; }
    .status-checked_in { color: #2e7d32; font-weight: 600; }
    .status-cancelled { color: #d32f2f; font-weight: 600; }
  </style>
</head>
<body>

<div class="container">
  <h2>📋 ยืนยันการจองห้องพัก</h2>

  <div class="text-end mb-3">
    <a href="admin.php?page=admin_calendar" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-calendar-week"></i> กลับไปปฏิทิน
    </a>
  </div>

  <?php if ($result->num_rows > 0): ?>
  <table class="table table-striped align-middle" id="bookingTable">
    <thead class="table-success">
      <tr>
        <th>ลำดับ</th>
        <th>ชื่อผู้จอง</th>
        <th>ประเภทห้อง</th>
        <th>วันที่เข้าพัก</th>
        <th>วันที่ออก</th>
        <th>ราคารวม</th>
        <th>สถานะ</th>
        <th>การจัดการ</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
      <tr id="row<?= $row['b_id'] ?>">
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
        <td><?= htmlspecialchars($row['rt_name']) ?></td>
        <td><?= htmlspecialchars($row['checkin']) ?></td>
        <td><?= htmlspecialchars($row['checkout']) ?></td>
        <td><?= number_format($row['total_price'], 2) ?> บาท</td>
        <td class="status-cell">
          <?php 
            $status = $row['status'];
            if (is_null($status) || $status == '') {
              echo "<span class='status-null'>รอการยืนยัน (ใหม่)</span>";
            } elseif ($status == 'pending') {
              echo "<span class='status-pending'>รอการยืนยัน</span>";
            } elseif ($status == 'confirmed') {
              echo "<span class='status-confirmed'>ยืนยันแล้ว</span>";
            } elseif ($status == 'checked_in') {
              echo "<span class='status-checked_in'>กำลังเข้าพัก</span>";
            } elseif ($status == 'cancelled') {
              echo "<span class='status-cancelled'>ยกเลิก</span>";
            }
          ?>
        </td>
        <td>
          <button class="btn btn-success btn-sm" onclick="updateStatus(<?= $row['b_id'] ?>, 'confirmed')">✅ ยืนยัน</button>
          <button class="btn btn-danger btn-sm" onclick="updateStatus(<?= $row['b_id'] ?>, 'cancelled')">❌ ยกเลิก</button>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="alert alert-info text-center">
    <i class="bi bi-info-circle"></i> ไม่มีรายการรอยืนยันในขณะนี้
  </div>
  <?php endif; ?>
</div>

<script>
function updateStatus(b_id, status) {
  if (!confirm(`คุณต้องการ${status === 'confirmed' ? 'ยืนยัน' : 'ยกเลิก'}การจองนี้หรือไม่?`)) return;

  fetch('admin_update_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `b_id=${b_id}&status=${status}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      const row = document.getElementById(`row${b_id}`);
      const statusCell = row.querySelector('.status-cell');

      if (status === 'confirmed') {
        statusCell.innerHTML = "<span class='status-confirmed'>ยืนยันแล้ว</span>";
      } else if (status === 'cancelled') {
        statusCell.innerHTML = "<span class='status-cancelled'>ยกเลิก</span>";
      }

      alert('✅ อัปเดตสถานะเรียบร้อยแล้ว!');
    } else {
      alert('❌ เกิดข้อผิดพลาด: ' + data.error);
    }
  })
  .catch(err => alert('⚠️ เกิดข้อผิดพลาด: ' + err));
}
</script>

</body>
</html>
<?php $conn->close(); ?>
