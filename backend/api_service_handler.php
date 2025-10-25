<?php
// --- api_service_handler.php ---
// ไฟล์นี้จะจัดการ Logic ของฐานข้อมูลทั้งหมด และส่งผลลัพธ์กลับเป็น JSON

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
$response = [
    'status' => 'error',
    'message' => 'การดำเนินการไม่สำเร็จ หรือ Action ไม่ถูกต้อง' // ข้อความเริ่มต้นที่ชัดเจนขึ้น
];

// --- 3. HANDLE POST REQUESTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    try {
        // --- ACTIONS FOR serve_type ---
        if ($action == 'add_type') {
            // --- 1. เพิ่ม Serve Type ---
            $stmt = $conn->prepare("INSERT INTO serve_type (st_name, tp_id, st_icon) VALUES (?, ?, ?)");
            // ตรวจสอบว่า prepare สำเร็จหรือไม่
            if (!$stmt) {
                 throw new Exception("Prepare failed (serve_type): " . $conn->error);
            }
            $stmt->bind_param("sis", $_POST['st_name'], $_POST['tp_id'], $_POST['st_icon']);

            if ($stmt->execute()) {
                // --- 2. เอา ID ของ Type ที่เพิ่งสร้าง ---
                $new_st_id = $conn->insert_id;
                $added_services_count = 0;

                // --- 3. ตรวจสอบและเพิ่ม Services ---
                if (isset($_POST['service_names']) && !empty(trim($_POST['service_names']))) {
                    // แยกชื่อบริการตามบรรทัด
                    $service_names_raw = preg_split('/\r\n|\r|\n/', trim($_POST['service_names'])); // ใช้ preg_split เพื่อรองรับ \r\n, \r, \n
                    $service_names_clean = [];
                    foreach ($service_names_raw as $name) {
                        $trimmed_name = trim($name);
                        if (!empty($trimmed_name)) {
                            $service_names_clean[] = $trimmed_name;
                        }
                    }

                    if (!empty($service_names_clean)) {
                        // เตรียมคำสั่ง SQL สำหรับเพิ่ม Service (เตรียมครั้งเดียว)
                        $stmt_service = $conn->prepare("INSERT INTO serve (s_name, st_id) VALUES (?, ?)");
                        if (!$stmt_service) {
                            // ถ้า prepare ล้มเหลว อาจจะแค่แจ้งเตือน แต่ยังถือว่าเพิ่ม Type สำเร็จ
                            error_log("Prepare failed (serve): " . $conn->error); // บันทึก error ไว้ดู
                            $response = ['status' => 'warning', 'message' => 'เพิ่มประเภทสำเร็จ แต่เกิดปัญหาในการเตรียมเพิ่มบริการ'];
                        } else {
                            foreach ($service_names_clean as $s_name) {
                                $stmt_service->bind_param("si", $s_name, $new_st_id);
                                if ($stmt_service->execute()) {
                                    $added_services_count++;
                                } else {
                                    // บันทึก error ถ้า insert service ล้มเหลว
                                    error_log("Insert failed (serve): " . $stmt_service->error);
                                }
                            }
                            $stmt_service->close(); // ปิด statement service
                        }
                    }
                }

                // --- 4. ปรับปรุงข้อความตอบกลับ ---
                // เช็คก่อนว่า $response ถูกแก้เป็น warning หรือยัง
                if ($response['status'] !== 'warning') {
                     $message = 'เพิ่มประเภทบริการสำเร็จ';
                     if ($added_services_count > 0) {
                         $message .= ' และเพิ่ม ' . $added_services_count . ' บริการเรียบร้อยแล้ว';
                     }
                     $response = ['status' => 'success', 'message' => $message];
                }

            } else {
                 // ถ้า execute serve_type ล้มเหลว
                 $response = ['status' => 'error', 'message' => 'เพิ่มประเภทบริการไม่สำเร็จ: ' . $stmt->error];
            }
            $stmt->close(); // ปิด statement หลัก

        } else if ($action == 'edit_type') {
            $stmt = $conn->prepare("UPDATE serve_type SET st_name = ?, tp_id = ?, st_icon = ? WHERE st_id = ?");
             if (!$stmt) { throw new Exception("Prepare failed (edit serve_type): " . $conn->error); }
            $stmt->bind_param("sisi", $_POST['st_name'], $_POST['tp_id'], $_POST['st_icon'], $_POST['st_id']);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'แก้ไขประเภทบริการสำเร็จ'];
            } else {
                 $response = ['status' => 'error', 'message' => 'แก้ไขประเภทบริการไม่สำเร็จ: ' . $stmt->error];
            }
            $stmt->close();

        } else if ($action == 'delete_type') {
             // เพิ่มการตรวจสอบ $stmt_check
             $stmt_check = $conn->prepare("SELECT COUNT(*) FROM serve WHERE st_id = ?");
             if (!$stmt_check) { throw new Exception("Prepare failed (check serve): " . $conn->error); }
            $stmt_check->bind_param("i", $_POST['st_id']);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $count = $result_check->fetch_row()[0];
            $stmt_check->close(); // ปิด stmt_check

            if ($count > 0) {
                $response = ['status' => 'error', 'message' => 'ลบไม่สำเร็จ: ยังมีบริการ (' . $count . ' รายการ) ที่ใช้ประเภทนี้อยู่']; // แสดงจำนวน
            } else {
                $stmt = $conn->prepare("DELETE FROM serve_type WHERE st_id = ?");
                 if (!$stmt) { throw new Exception("Prepare failed (delete serve_type): " . $conn->error); }
                $stmt->bind_param("i", $_POST['st_id']);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'ลบประเภทบริการสำเร็จ'];
                } else {
                     $response = ['status' => 'error', 'message' => 'ลบประเภทบริการไม่สำเร็จ: ' . $stmt->error];
                }
                $stmt->close();
            }

        // --- ACTIONS FOR serve ---
        } else if ($action == 'add_service') {
            $stmt = $conn->prepare("INSERT INTO serve (s_name, st_id) VALUES (?, ?)");
             if (!$stmt) { throw new Exception("Prepare failed (add serve): " . $conn->error); }
            $stmt->bind_param("si", $_POST['s_name'], $_POST['st_id']);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'เพิ่มบริการสำเร็จ'];
            } else {
                 $response = ['status' => 'error', 'message' => 'เพิ่มบริการไม่สำเร็จ: ' . $stmt->error];
            }
             $stmt->close();

        } else if ($action == 'edit_service') {
            $stmt = $conn->prepare("UPDATE serve SET s_name = ?, st_id = ? WHERE s_id = ?");
             if (!$stmt) { throw new Exception("Prepare failed (edit serve): " . $conn->error); }
            $stmt->bind_param("sii", $_POST['s_name'], $_POST['st_id'], $_POST['s_id']);
             if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'แก้ไขบริการสำเร็จ'];
            } else {
                 $response = ['status' => 'error', 'message' => 'แก้ไขบริการไม่สำเร็จ: ' . $stmt->error];
            }
             $stmt->close();

        } else if ($action == 'delete_service') {
            $stmt = $conn->prepare("DELETE FROM serve WHERE s_id = ?");
             if (!$stmt) { throw new Exception("Prepare failed (delete serve): " . $conn->error); }
            $stmt->bind_param("i", $_POST['s_id']);
             if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'ลบบริการสำเร็จ'];
            } else {
                 $response = ['status' => 'error', 'message' => 'ลบบริการไม่สำเร็จ: ' . $stmt->error];
            }
             $stmt->close();
        }
        // เพิ่ม else เผื่อ action ไม่ตรงกับอันไหนเลย
        // else {
        //     $response = ['status' => 'error', 'message' => 'Action ที่ร้องขอไม่ถูกต้อง'];
        // }

    } catch (Exception $e) {
        // จับ Exception โดยรวม เพื่อแสดงข้อผิดพลาดที่ไม่คาดคิด
        http_response_code(500); // Internal Server Error
        $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดร้ายแรง: ' . $e->getMessage()];
        // อาจจะบันทึก $e->getMessage() ลง log file จริงๆ แทนการแสดงให้ user เห็น
        error_log("API Error: " . $e->getMessage());
    }

    $conn->close(); // ปิด Connection เสมอ ไม่ว่าจะสำเร็จหรือล้มเหลว
}

// --- 4. RETURN JSON RESPONSE ---
header('Content-Type: application/json; charset=utf-8'); // ระบุ charset utf-8
echo json_encode($response, JSON_UNESCAPED_UNICODE); // เพิ่ม JSON_UNESCAPED_UNICODE เพื่อให้ภาษาไทยแสดงผลถูกต้อง
exit;
?>