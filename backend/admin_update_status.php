<?php  
session_start();
require_once "connectdb.php";
header('Content-Type: application/json; charset=UTF-8');

// 🔒 ตรวจสอบสิทธิ์แอดมินและวิธีเรียก
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Access Denied']);
  exit;
}

// 🧾 รับค่าจากฟอร์ม
$b_id = $_POST['b_id'] ?? null;
$status = $_POST['status'] ?? null;
$room_id = $_POST['room_id'] ?? null;

// ✅ แปลงค่าว่างให้เป็น NULL จริง ๆ (ป้องกันค่า "" ทำให้ไม่อัปเดต)
if ($room_id === '' || $room_id === 'null') {
  $room_id = null;
}

// ❌ ตรวจสอบความครบถ้วนของข้อมูล
if (!$b_id || !$status) {
  echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
  exit;
}

// ✅ 1. ดึงสถานะเดิมก่อนอัปเดต
$stmt_old = $conn->prepare("SELECT status FROM booking WHERE b_id = ?");
$stmt_old->bind_param("i", $b_id);
$stmt_old->execute();
$result_old = $stmt_old->get_result();
$old_status = $result_old->fetch_assoc()['status'] ?? 'ไม่ทราบ';
$stmt_old->close();

// ✅ 2. อัปเดตสถานะและเลขห้อง
$sql = "UPDATE booking SET status = ?, room_id = ? WHERE b_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $status, $room_id, $b_id);

if ($stmt->execute()) {

  // ✅ 3. ดึงข้อมูลหลังอัปเดต สำหรับบันทึก Log
  $sql_info = "
    SELECT 
      b.b_id, b.status, b.checkin, b.checkout,
      CONCAT(u.first_name, ' ', u.last_name) AS guest_name,
      rt.rt_name, r.room_number
    FROM booking b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN room_type rt ON b.rt_id = rt.rt_id
    LEFT JOIN rooms r ON b.room_id = r.room_id
    WHERE b.b_id = ?
  ";
  $stmt_info = $conn->prepare($sql_info);
  $stmt_info->bind_param("i", $b_id);
  $stmt_info->execute();
  $info = $stmt_info->get_result()->fetch_assoc();
  $stmt_info->close();

  // ✅ 4. เพิ่มบันทึกลง Log
  $admin_id = $_SESSION['user_id'] ?? 0;
  $action_type = 'UPDATE_BOOKING_STATUS';
  $details = sprintf(
    "เปลี่ยนสถานะการจอง ID: %d (%s - ห้อง %s) จาก '%s' → '%s' โดยผู้จอง: %s (วันที่ %s → %s)",
    $b_id,
    $info['rt_name'] ?? 'ไม่ระบุประเภทห้อง',
    $info['room_number'] ?? 'ไม่ระบุ',
    $old_status ?: 'ไม่มีสถานะ',
    $status,
    $info['guest_name'] ?? 'ไม่ระบุ',
    $info['checkin'] ?? '-',
    $info['checkout'] ?? '-'
  );

  $sql_log = "INSERT INTO admin_logs (admin_user_id, action_type, details, log_timestamp, target_user_id) 
              VALUES (?, ?, ?, NOW(), (SELECT user_id FROM booking WHERE b_id = ?))";
  $stmt_log = $conn->prepare($sql_log);
  $stmt_log->bind_param("issi", $admin_id, $action_type, $details, $b_id);
  $stmt_log->execute();
  $stmt_log->close();

  echo json_encode(['success' => true]);
} else {
  echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
