<?php
// --- api_travel_handler.php ---
// จัดการข้อมูลตาราง travel

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. CONNECT DB ---
$host="localhost"; $usr="root"; $pwd=""; $dbName="tharapana";
$conn = mysqli_connect($host,$usr,$pwd,$dbName);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB connect failed']);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

// --- 2. PREPARE RESPONSE ---
$response = ['status' => 'error', 'message' => 'Invalid action or request method.'];

// --- 3. HANDLE POST REQUESTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // เตรียมข้อมูลที่รับมา (ป้องกันค่าว่าง)
    $tv_id = isset($_POST['tv_id']) ? (int)$_POST['tv_id'] : 0;
    $tv_name = trim($_POST['tv_name'] ?? '');
    $distance = trim($_POST['distance'] ?? '');
    $tv_map = trim($_POST['tv_map'] ?? '');
    $tp_id = isset($_POST['tp_id']) ? (int)$_POST['tp_id'] : 4; // Default tp_id to 4 if not provided

    try {
        if ($action == 'add_travel') {
            if (empty($tv_name) || empty($distance) || empty($tv_map)) {
                 throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน (ชื่อ, ระยะทาง, ลิงก์แผนที่)");
            }
            $stmt = $conn->prepare("INSERT INTO travel (tv_name, distance, tv_map, tp_id) VALUES (?, ?, ?, ?)");
            if (!$stmt) { throw new Exception("Prepare failed (add travel): " . $conn->error); }
            $stmt->bind_param("sssi", $tv_name, $distance, $tv_map, $tp_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'เพิ่มสถานที่ท่องเที่ยวสำเร็จ'];
            } else {
                $response = ['status' => 'error', 'message' => 'เพิ่มข้อมูลไม่สำเร็จ: ' . $stmt->error];
            }
            $stmt->close();

        } else if ($action == 'edit_travel') {
            if ($tv_id <= 0 || empty($tv_name) || empty($distance) || empty($tv_map)) {
                 throw new Exception("ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องสำหรับการแก้ไข");
            }
            $stmt = $conn->prepare("UPDATE travel SET tv_name = ?, distance = ?, tv_map = ?, tp_id = ? WHERE tv_id = ?");
             if (!$stmt) { throw new Exception("Prepare failed (edit travel): " . $conn->error); }
            $stmt->bind_param("sssii", $tv_name, $distance, $tv_map, $tp_id, $tv_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'แก้ไขสถานที่ท่องเที่ยวสำเร็จ'];
            } else {
                $response = ['status' => 'error', 'message' => 'แก้ไขข้อมูลไม่สำเร็จ: ' . $stmt->error];
            }
            $stmt->close();

        } else if ($action == 'delete_travel') {
             if ($tv_id <= 0) {
                 throw new Exception("ID สำหรับลบไม่ถูกต้อง");
            }
            $stmt = $conn->prepare("DELETE FROM travel WHERE tv_id = ?");
             if (!$stmt) { throw new Exception("Prepare failed (delete travel): " . $conn->error); }
            $stmt->bind_param("i", $tv_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'ลบสถานที่ท่องเที่ยวสำเร็จ'];
            } else {
                $response = ['status' => 'error', 'message' => 'ลบข้อมูลไม่สำเร็จ: ' . $stmt->error];
            }
            $stmt->close();
        } else {
             $response = ['status' => 'error', 'message' => 'Action ที่ร้องขอไม่ถูกต้อง'];
        }

    } catch (Exception $e) {
        // ถ้ามี Exception เกิดขึ้น ให้ใช้ message จาก Exception นั้นๆ
        $response = ['status' => 'error', 'message' => $e->getMessage()];
        // อาจจะตั้ง http_response_code(400) สำหรับ Bad Request ถ้าข้อมูล input ไม่ถูกต้อง
         if ($e->getMessage() === "กรุณากรอกข้อมูลให้ครบถ้วน (ชื่อ, ระยะทาง, ลิงก์แผนที่)" || $e->getMessage() === "ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องสำหรับการแก้ไข" || $e->getMessage() === "ID สำหรับลบไม่ถูกต้อง") {
            http_response_code(400); // Bad Request
        } else {
             http_response_code(500); // Internal Server Error สำหรับ lỗi อื่นๆ
             error_log("API Error (travel): " . $e->getMessage()); // บันทึก log สำหรับ lỗi อื่นๆ
        }
    }

    $conn->close();
}

// --- 4. RETURN JSON RESPONSE ---
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>