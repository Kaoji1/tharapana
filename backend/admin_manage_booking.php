<?php

include_once("connectdb.php");
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ✅ ดึงข้อมูลการจองทั้งหมด
$sql = "
SELECT 
  b.b_id,
  u.first_name, u.last_name,
  rt.rt_name,
  b.room_number,
  b.checkin,
  b.checkout,
  b.status,
  b.created_at
FROM booking b
LEFT JOIN users u ON b.user_id = u.user_id
LEFT JOIN room_type rt ON b.rt_id = rt.rt_id
ORDER BY b.created_at DESC
";
$result = $conn->query($sql);

// ✅ ฟังก์ชันสถานะภาษาไทย
function getStatusText($status)
{
  $map = [
    'pending' => 'รอการเข้าพัก',
    'confirmed' => 'ยืนยันแล้ว',
    'checked_in' => 'กำลังเข้าพัก',
    'checked_out' => 'เช็คเอาท์แล้ว',
    'cancelled' => 'ยกเลิก'
  ];
  return $map[$status] ?? 'ไม่ทราบสถานะ';
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>🛎️ จัดการการจอง | Tharapana Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background: #f6f8fa;
      font-family: 'Sarabun', sans-serif;
    }

    h2 {
      color: #00695c;
      font-weight: 700;
      text-align: center;
      margin: 30px 0;
    }

    table {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    thead {
      background: #00796b;
      color: #fff;
    }

    .status-badge {
      padding: 6px 10px;
      border-radius: 8px;
      font-weight: 600;
    }

    .status-pending {
      background: #ffca28;
      color: #333;
    }

    .status-confirmed {
      background: #29b6f6;
      color: #fff;
    }

    .status-checked_in {
      background: #66bb6a;
      color: #fff;
    }

    .status-checked_out {
      background: #bdbdbd;
      color: #fff;
    }

    .status-cancelled {
      background: #ef5350;
      color: #fff;
    }
  </style>
</head>

<body>
  <div class="container py-4">
    <h2><i class="bi bi-journal-text me-2"></i>จัดการการจองห้องพัก</h2>

    <!-- 🔍 ฟิลเตอร์ -->
    <div class="card p-3 mb-4 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-bold">สถานะ</label>
          <select id="filterStatus" class="form-select">
            <option value="all">ทั้งหมด</option>
            <option value="pending">รอการเข้าพัก</option>
            <option value="confirmed">ยืนยันแล้ว</option>
            <option value="checked_in">กำลังเข้าพัก</option>
            <option value="checked_out">เช็คเอาท์แล้ว</option>
            <option value="cancelled">ยกเลิก</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">กรองวันที่เช็คอิน</label>
          <input type="date" id="filterCheckin" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">กรองวันที่เช็คเอาท์</label>
          <input type="date" id="filterCheckout" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">เลขห้อง</label>
          <select id="filterRoomNumber" class="form-select">
            <option value="all">ทั้งหมด</option>
            <option value="no-room">ยังไม่ระบุเลขห้อง</option>
          </select>
        </div>
      </div>

      <div class="text-end mt-3">
        <button class="btn btn-success" onclick="applyFilters()"><i class="bi bi-funnel"></i> กรอง</button>
        <button class="btn btn-secondary" onclick="resetFilters()"><i class="bi bi-arrow-clockwise"></i> รีเซ็ต</button>
      </div>
    </div>

    <!-- 📋 ตาราง -->
    <div class="table-responsive">
      <table class="table table-hover align-middle" id="bookingTable">
        <thead>
          <tr class="text-center">
            <th>รหัส</th>
            <th>ผู้จอง</th>
            <th>ประเภทห้อง</th>
            <th>เลขห้อง</th>
            <th>เช็คอิน</th>
            <th>เช็คเอาท์</th>
            <th>สถานะ</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr class="text-center"
              data-status="<?= $row['status'] ?>"
              data-room="<?= $row['room_number'] ?: 'no-room' ?>"
              data-checkin="<?= $row['checkin'] ?>"
              data-checkout="<?= $row['checkout'] ?>">
              <td><?= $row['b_id'] ?></td>
              <td><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></td>
              <td><?= htmlspecialchars($row['rt_name']) ?></td>
              <td><?= $row['room_number'] ?: '<span class="text-danger">ยังไม่ระบุ</span>' ?></td>
              <td><?= $row['checkin'] ?></td>
              <td><?= $row['checkout'] ?></td>
              <td><span class="status-badge status-<?= $row['status'] ?>"><?= getStatusText($row['status']) ?></span></td>
              <td>
                <button class="btn btn-primary btn-sm" onclick="editBooking(<?= $row['b_id'] ?>)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-danger btn-sm" onclick="deleteBooking(<?= $row['b_id'] ?>)"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ✏️ Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> แก้ไขการจอง</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="editForm">
            <input type="hidden" id="editId">
            <div class="mb-2">
              <label class="form-label">เลขห้อง</label>
              <input type="text" class="form-control" id="editRoomNumber">
            </div>
            <div class="mb-2">
              <label class="form-label">เช็คอิน</label>
              <input type="date" class="form-control" id="editCheckin">
            </div>
            <div class="mb-2">
              <label class="form-label">เช็คเอาท์</label>
              <input type="date" class="form-control" id="editCheckout">
            </div>
            <div class="mb-3">
              <label class="form-label">สถานะ</label>
              <select class="form-select" id="editStatus">
                <option value="pending">รอการเข้าพัก</option>
                <option value="confirmed">ยืนยันแล้ว</option>
                <option value="checked_in">กำลังเข้าพัก</option>
                <option value="checked_out">เช็คเอาท์แล้ว</option>
                <option value="cancelled">ยกเลิก</option>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
          <button class="btn btn-primary" onclick="saveEdit()">บันทึก</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // 🧭 กรองข้อมูล
    function applyFilters() {
      const status = document.getElementById('filterStatus').value;
      const room = document.getElementById('filterRoomNumber').value;
      const checkin = document.getElementById('filterCheckin').value;
      const checkout = document.getElementById('filterCheckout').value;

      document.querySelectorAll('#bookingTable tbody tr').forEach(row => {
        const rowStatus = row.dataset.status;
        const rowRoom = row.dataset.room;
        const rowCheckin = row.dataset.checkin;
        const rowCheckout = row.dataset.checkout;

        let visible = true;

        if (status !== 'all' && rowStatus !== status) visible = false;
        if (room !== 'all' && rowRoom !== room) visible = false;
        if (checkin && rowCheckin < checkin) visible = false;
        if (checkout && rowCheckout > checkout) visible = false;

        row.style.display = visible ? '' : 'none';
      });
    }

    function resetFilters() {
      document.getElementById('filterStatus').value = 'all';
      document.getElementById('filterRoomNumber').value = 'all';
      document.getElementById('filterCheckin').value = '';
      document.getElementById('filterCheckout').value = '';
      applyFilters();
    }

    // ✏️ ฟังก์ชันแก้ไข
    function editBooking(id) {
      fetch('admin_booking_action.php?action=get&id=' + id)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            document.getElementById('editId').value = data.booking.b_id;
            document.getElementById('editRoomNumber').value = data.booking.room_number || '';
            document.getElementById('editCheckin').value = data.booking.checkin;
            document.getElementById('editCheckout').value = data.booking.checkout;
            document.getElementById('editStatus').value = data.booking.status;
            new bootstrap.Modal(document.getElementById('editModal')).show();
          } else alert(data.error);
        });
    }

    // 💾 ฟังก์ชันบันทึก
    function saveEdit() {
      const fd = new FormData();
      fd.append('action', 'update');
      fd.append('b_id', document.getElementById('editId').value);
      fd.append('room_number', document.getElementById('editRoomNumber').value);
      fd.append('checkin', document.getElementById('editCheckin').value);
      fd.append('checkout', document.getElementById('editCheckout').value);
      fd.append('status', document.getElementById('editStatus').value);

      fetch('admin_booking_action.php', {
          method: 'POST',
          body: fd
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert('✅ บันทึกสำเร็จ');
            location.reload();
          } else alert('❌ ' + data.error);
        });
    }

    // ❌ ลบการจอง
    function deleteBooking(id) {
      if (!confirm('แน่ใจหรือไม่ว่าต้องการลบการจองนี้?')) return;
      const fd = new URLSearchParams();
      fd.append('action', 'delete');
      fd.append('b_id', id);

      fetch('admin_booking_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: fd.toString()
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert('🗑️ ลบสำเร็จ');
            location.reload();
          } else alert('❌ ' + data.error);
        });
    }
  </script>
</body>

</html>