<?php
header('Content-Type: application/json; charset=utf-8');
require_once "connectdb.php";

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // 📘 ดึงข้อมูลการจอง
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสการจอง']);
            exit;
        }

        $sql = "SELECT b.*, u.first_name, u.last_name, rt.rt_name 
                FROM booking b 
                LEFT JOIN users u ON b.user_id = u.user_id
                LEFT JOIN room_type rt ON b.rt_id = rt.rt_id
                WHERE b.b_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        echo $result ? json_encode(['success' => true, 'booking' => $result])
                     : json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']);
        break;

    // ✏️ อัปเดตข้อมูลการจอง
    case 'update':
        $b_id = intval($_POST['b_id'] ?? 0);
        $room_number = trim($_POST['room_number'] ?? '');
        $checkin = trim($_POST['checkin'] ?? '');
        $checkout = trim($_POST['checkout'] ?? '');
        $status = trim($_POST['status'] ?? '');

        if (!$b_id || !$checkin || !$checkout) {
            echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        $sql = "UPDATE booking 
                SET room_number=?, checkin=?, checkout=?, status=? 
                WHERE b_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $room_number, $checkin, $checkout, $status, $b_id);

        echo $stmt->execute()
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'error' => 'อัปเดตไม่สำเร็จ']);
        break;

    // ❌ ลบการจอง
    case 'delete':
        $b_id = intval($_POST['b_id'] ?? 0);
        if (!$b_id) {
            echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสการจอง']);
            exit;
        }

        $sql = "DELETE FROM booking WHERE b_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $b_id);

        echo $stmt->execute()
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'error' => 'ไม่สามารถลบข้อมูลได้']);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
$conn->close();
?>
