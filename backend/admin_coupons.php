<?php
ob_start(); // ✅ ป้องกัน header error
include_once("connectdb.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ ตรวจสิทธิ์แอดมิน
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    die("ต้องเข้าสู่ระบบในฐานะแอดมินก่อน");
}

// ✅ ลบคูปอง
if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
    $conn->query("DELETE FROM coupons WHERE coupon_id = $id");
    header("Location: admin.php?page=manage_promotions");
    exit;
}

// ✅ วันที่อ้างอิง
$today = date("Y-m-d");
$next7 = date("Y-m-d", strtotime("+7 days"));

// ✅ ถ้ามีการกดปุ่มแก้ไข
$editData = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editData = $conn->query("SELECT * FROM coupons WHERE coupon_id = $editId")->fetch_assoc();
}

// ✅ เพิ่ม / แก้ไขคูปอง
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["coupon_id"] ?? null;
    $code = trim($_POST["code"]);
    $desc = trim($_POST["description"]);
    $type = $_POST["discount_type"];
    $value = (float)$_POST["discount_value"];
    $min = (float)($_POST["min_spend"] ?? 0);
    $max = $_POST["max_discount"] !== "" ? (float)$_POST["max_discount"] : null;
    $total_limit = (int)($_POST["usage_limit_total"] ?? 10);
    $per_user_limit = (int)($_POST["usage_limit_per_user"] ?? 1);
    $status = $_POST["status"];
    $start = $_POST["start_date"] ?: $today;
    $end = $_POST["end_date"] ?: $next7;

    // ✅ ตรวจสอบวันเริ่มและวันสิ้นสุด
    if (strtotime($start) > strtotime($end)) {
        echo "<script>alert('❌ วันเริ่มต้นต้องไม่เกินวันสิ้นสุด'); window.history.back();</script>";
        exit;
    }

    if ($id) {
        // ✅ อัปเดตคูปอง
        $stmt = $conn->prepare("UPDATE coupons 
            SET code=?, description=?, discount_type=?, discount_value=?, 
                min_spend=?, max_discount=?, start_date=?, end_date=?, 
                usage_limit_total=?, usage_limit_per_user=?, status=? 
            WHERE coupon_id=?");
        $stmt->bind_param("sssdddssiisi",
            $code, $desc, $type, $value, $min, $max,
            $start, $end, $total_limit, $per_user_limit, $status, $id
        );
    } else {
        // ✅ เพิ่มคูปองใหม่
        $stmt = $conn->prepare("
            INSERT INTO coupons 
            (code, description, discount_type, discount_value, min_spend, max_discount, start_date, end_date, usage_limit_total, usage_limit_per_user, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("sssdddssiis",
            $code, $desc, $type, $value, $min, $max,
            $start, $end, $total_limit, $per_user_limit, $status
        );
    }

    if ($stmt->execute()) {
        header("Location: admin.php?page=manage_promotions");
        exit;
    } else {
        echo "<p style='color:red;'>เกิดข้อผิดพลาด: {$stmt->error}</p>";
    }
}

// ✅ ดึงคูปองทั้งหมด
$coupons = $conn->query("SELECT * FROM coupons ORDER BY coupon_id DESC");
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการคูปอง | Tharapana Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f5f8fa; }
    .card { border-radius: 10px; }
    small.text-muted { font-size: 0.8em; display: block; margin-top: 2px; }
    input, select { font-size: 14px; }
    table td, table th { vertical-align: middle !important; }
  </style>
</head>
<body class="p-4">

  <h2 class="mb-4"><i class="bi bi-ticket-detailed"></i> 🎫 จัดการคูปองส่วนลด</h2>

  <!-- ✅ ฟอร์มเพิ่ม/แก้ไขคูปอง -->
  <form method="post" class="card p-3 mb-4 shadow-sm">
    <input type="hidden" name="coupon_id" value="<?= $editData['coupon_id'] ?? '' ?>">
    <div class="row g-3">

      <div class="col-md-2">
        <label class="form-label">รหัสคูปอง*</label>
        <input type="text" name="code" class="form-control"
               value="<?= htmlspecialchars($editData['code'] ?? '') ?>" required>
        <small class="text-muted">ใช้ตัวอักษร/ตัวเลข ห้ามซ้ำ เช่น THA01</small>
      </div>

      <div class="col-md-3">
        <label class="form-label">รายละเอียด</label>
        <input type="text" name="description" class="form-control"
               value="<?= htmlspecialchars($editData['description'] ?? '') ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label">ประเภทส่วนลด</label>
        <select name="discount_type" class="form-select">
          <option value="amount" <?= (isset($editData) && $editData['discount_type'] == 'amount') ? 'selected' : '' ?>>บาท</option>
          <option value="percent" <?= (isset($editData) && $editData['discount_type'] == 'percent') ? 'selected' : '' ?>>เปอร์เซ็นต์</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">มูลค่าส่วนลด*</label>
        <input type="number" step="0.01" name="discount_value" class="form-control"
               value="<?= $editData['discount_value'] ?? '' ?>" required>
      </div>

      <div class="col-md-2">
        <label class="form-label">ยอดขั้นต่ำ</label>
        <input type="number" step="0.01" name="min_spend" class="form-control"
               value="<?= $editData['min_spend'] ?? '' ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label">ลดสูงสุด</label>
        <input type="number" step="0.01" name="max_discount" class="form-control"
               value="<?= $editData['max_discount'] ?? '' ?>">
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-2">
        <label class="form-label">วันที่เริ่มต้น</label>
        <input type="date" name="start_date" class="form-control"
               value="<?= $editData['start_date'] ?? $today ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label">วันที่สิ้นสุด</label>
        <input type="date" name="end_date" class="form-control"
               value="<?= $editData['end_date'] ?? $next7 ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label">จำนวนครั้งทั้งหมด</label>
        <input type="number" name="usage_limit_total" class="form-control"
               value="<?= $editData['usage_limit_total'] ?? 10 ?>" min="1">
        <small class="text-muted">เช่น 20 = ใช้ได้รวม 20 ครั้ง</small>
      </div>

      <div class="col-md-2">
        <label class="form-label">จำกัดต่อผู้ใช้</label>
        <input type="number" name="usage_limit_per_user" class="form-control"
               value="<?= $editData['usage_limit_per_user'] ?? 1 ?>" min="1">
        <small class="text-muted">เช่น 2 = 1 คนใช้ได้สูงสุด 2 ครั้ง</small>
      </div>

      <div class="col-md-2">
        <label class="form-label">สถานะ</label>
        <select name="status" class="form-select">
          <option value="active" <?= (isset($editData) && $editData['status'] == 'active') ? 'selected' : '' ?>>เปิดใช้งาน</option>
          <option value="disabled" <?= (isset($editData) && $editData['status'] == 'disabled') ? 'selected' : '' ?>>ปิด</option>
          <option value="expired" <?= (isset($editData) && $editData['status'] == 'expired') ? 'selected' : '' ?>>หมดอายุ</option>
        </select>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-<?= isset($editData) ? 'warning' : 'primary' ?> w-100">
          <?= isset($editData) ? '✏️ อัปเดตคูปอง' : '💾 บันทึกคูปอง' ?>
        </button>
      </div>
    </div>
  </form>

  <!-- ✅ ตารางคูปอง -->
  <table class="table table-bordered table-striped align-middle bg-white">
    <thead class="table-success">
      <tr>
        <th>รหัส</th>
        <th>ประเภท</th>
        <th>มูลค่า</th>
        <th>ขั้นต่ำ</th>
        <th>ลดสูงสุด</th>
        <th>วันที่ใช้ได้</th>
        <th>ใช้ไป / รวม</th>
        <th>ต่อผู้ใช้</th>
        <th>สถานะ</th>
        <th>จัดการ</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($c = $coupons->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($c['code']) ?></td>
        <td><?= htmlspecialchars($c['discount_type']) ?></td>
        <td><?= number_format($c['discount_value'], 2) ?></td>
        <td><?= number_format($c['min_spend'], 2) ?></td>
        <td><?= $c['max_discount'] ? number_format($c['max_discount'], 2) : '-' ?></td>
        <td><?= $c['start_date'] ?> → <?= $c['end_date'] ?></td>
        <td><?= $c['used_count'] ?? 0 ?>/<?= $c['usage_limit_total'] ?></td>
        <td><?= $c['usage_limit_per_user'] ?></td>
        <td>
          <?php
            $status = $c['status'];
            $badge = $status === 'active' ? 'success' : ($status === 'expired' ? 'secondary' : 'danger');
          ?>
          <span class="badge bg-<?= $badge ?>"><?= ucfirst($status) ?></span>
        </td>
        <td>
          <a href="admin.php?page=manage_promotions&edit=<?= $c['coupon_id'] ?>" class="btn btn-warning btn-sm">
            <i class="bi bi-pencil-square"></i> แก้ไข
          </a>
          <a href="admin.php?page=manage_promotions&delete=<?= $c['coupon_id'] ?>" 
             class="btn btn-danger btn-sm"
             onclick="return confirm('ลบคูปองนี้แน่ไหม?')">ลบ</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

<?php ob_end_flush(); ?>
</body>
</html>
