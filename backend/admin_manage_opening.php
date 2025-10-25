<?php
include_once("connectdb.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์ admin (optional)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// ** MODIFIED: ปรับปรุงส่วนเพิ่ม/แก้ไขข้อมูลจากฟอร์มด้านบน **
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_opening'])) { // MODIFIED: เปลี่ยนชื่อ name ของปุ่ม
    $rt_id = (int)$_POST['rt_id'];
    $date = $_POST['date'];
    // MODIFIED: ไม่จำเป็นต้องบังคับให้เป็นค่า > 0 อีกต่อไป
    $open_rooms = (int)$_POST['open_rooms']; 

    // ใช้ ON DUPLICATE KEY UPDATE เพื่อให้สามารถเพิ่มใหม่ หรือ อัปเดตของเดิมได้ในคำสั่งเดียว
    // ถ้ามี rt_id และ date ซ้ำกันอยู่แล้ว จะทำการอัปเดต open_rooms
    $stmt = $conn->prepare("INSERT INTO room_opening (rt_id, date, open_rooms) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE open_rooms = VALUES(open_rooms)");
    $stmt->bind_param("isi", $rt_id, $date, $open_rooms);
    $stmt->execute();
    $stmt->close();
    
    // MODIFIED: ส่ง parameter กลับไปเพื่อแสดงข้อความที่เหมาะสม
    if ($open_rooms > 0) {
        header("Location: admin.php?page=admin_manage_opening&success=1");
    } else {
        header("Location: admin.php?page=admin_manage_opening&closed_top=1"); // parameter ใหม่สำหรับแจ้งว่าปิดจากฟอร์มบน
    }
    exit;
}


// ลบข้อมูล
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM room_opening WHERE id = $id");
    header("Location: admin.php?page=admin_manage_opening&deleted=1");
    exit;
}

// ปิดการจอง (ตั้งค่า open_rooms = 0) จากปุ่มในตาราง
if (isset($_GET['close'])) {
    $id = (int)$_GET['close'];
    $stmt = $conn->prepare("UPDATE room_opening SET open_rooms = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php?page=admin_manage_opening&closed=1");
    exit;
}

// แก้ไขข้อมูลจาก Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_opening'])) {
    $id = (int)$_POST['id'];
    $rt_id = (int)$_POST['rt_id'];
    $date = $_POST['date'];
    $open_rooms = (int)$_POST['open_rooms'];

    $stmt = $conn->prepare("UPDATE room_opening SET rt_id=?, date=?, open_rooms=? WHERE id=?");
    $stmt->bind_param("isii", $rt_id, $date, $open_rooms, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php?page=admin_manage_opening&updated=1");
    exit;
}

// ดึงข้อมูลห้องทั้งหมด
$rooms = $conn->query("SELECT rt_id, rt_name FROM room_type ORDER BY rt_id")->fetch_all(MYSQLI_ASSOC);

// ดึงรายการเปิดห้องทั้งหมด
$sql = "
SELECT o.id, o.rt_id, rt.rt_name, o.date, o.open_rooms
FROM room_opening o
JOIN room_type rt ON o.rt_id = rt.rt_id
ORDER BY o.date ASC, rt.rt_name ASC
";
$result = $conn->query($sql);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>จัดการจำนวนห้องที่เปิดให้จอง | Tharapana Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">จัดการจำนวนห้องที่เปิดให้จอง</h2>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">กำหนดจำนวนห้องที่เปิด/ปิดการจอง</div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label">ประเภทห้องพัก</label>
                        <select name="rt_id" class="form-select" required>
                            <option value="">-- เลือกประเภทห้อง --</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['rt_id'] ?>"><?= htmlspecialchars($r['rt_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">วันที่</label>
                        <input type="date" name="date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">จำนวนห้อง (ใส่ 0 เพื่อปิด)</label>
                        <input type="number" name="open_rooms" min="0" class="form-control" required value="0">
                    </div>
                    <div class="col-md-2 d-grid mt-4">
                        <button type="submit" name="set_opening" class="btn btn-success">กำหนดค่า</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">รายการวันที่เปิดให้จอง</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>ประเภทห้อง</th>
                    <th>วันที่เปิด</th>
                    <th>สถานะ / จำนวนห้อง</th>
                    <th>จัดการ</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="<?= (int)$row['open_rooms'] === 0 ? 'table-secondary' : '' ?>">
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['rt_name']) ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td>
                                <?php if ((int)$row['open_rooms'] > 0): ?>
                                    <span class="badge bg-success">เปิดอยู่ (<?= (int)$row['open_rooms'] ?> ห้อง)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">ปิดการจอง</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning"
                                        onclick="openEditModal(<?= $row['id'] ?>, <?= $row['rt_id'] ?>, '<?= $row['date'] ?>', <?= $row['open_rooms'] ?>)">
                                    <i class="bi bi-pencil-fill"></i> แก้ไข
                                </button>

                                <?php if ((int)$row['open_rooms'] > 0): ?>
                                    <a href="?page=admin_manage_opening&close=<?= $row['id'] ?>" class="btn btn-sm btn-secondary"
                                       onclick="return confirm('ยืนยันการปิดการจองสำหรับรายการนี้?')">
                                        <i class="bi bi-calendar-x-fill"></i> ปิดการจอง
                                    </a>
                                <?php endif; ?>

                                <a href="?page=admin_manage_opening&delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('ยืนยันการลบรายการนี้?')">
                                    <i class="bi bi-trash3-fill"></i> ลบ
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted">ยังไม่มีข้อมูล</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">แก้ไขข้อมูลเปิดห้อง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">ประเภทห้อง</label>
                        <select name="rt_id" id="edit_rt_id" class="form-select" required>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['rt_id'] ?>"><?= htmlspecialchars($r['rt_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">วันที่เปิดให้จอง</label>
                        <input type="date" name="date" id="edit_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">จำนวนห้องที่เปิด (ใส่ 0 เพื่อปิด)</label>
                        <input type="number" name="open_rooms" id="edit_open_rooms" class="form-control" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_opening" class="btn btn-success">บันทึกการแก้ไข</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openEditModal(id, rt_id, date, open_rooms) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_rt_id').value = rt_id;
        document.getElementById('edit_date').value = date;
        document.getElementById('edit_open_rooms').value = open_rooms;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
</script>
</body>
</html>