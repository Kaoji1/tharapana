<?php
require_once "connectdb.php";
header('Content-Type: application/json; charset=UTF-8');

$date = $_GET['date'] ?? '';
if (!$date) {
    echo json_encode(['success' => false, 'error' => 'missing date']);
    exit;
}

// 🏘 ดึงข้อมูลห้องทั้งหมด + ประเภทห้อง
$sql_all = "
    SELECT r.room_id, r.room_number, rt.rt_name
    FROM rooms r
    LEFT JOIN room_type rt ON r.rt_id = rt.rt_id
";
$res_all = $conn->query($sql_all);
$rooms_all = [];
while ($r = $res_all->fetch_assoc()) {
    $rooms_all[$r['room_id']] = [
        'number' => $r['room_number'],
        'type'   => $r['rt_name']
    ];
}

// 🔒 ดึงห้องที่ถูกจองในวันนั้น
$sql_booked = "
    SELECT b.room_id, b.checkin, b.checkout, b.status, 
           CONCAT(u.first_name, ' ', u.last_name) AS guest_name,
           rt.rt_name
    FROM booking b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN room_type rt ON b.rt_id = rt.rt_id
    WHERE ? BETWEEN b.checkin AND DATE_SUB(b.checkout, INTERVAL 1 DAY)
      AND b.status IN ('pending','confirmed','checked_in')
";
$stmt = $conn->prepare($sql_booked);
$stmt->bind_param('s', $date);
$stmt->execute();
$res = $stmt->get_result();

$booked = [];
while ($b = $res->fetch_assoc()) {
    if ($b['room_id']) {
        $booked[(int)$b['room_id']] = [
            'guest'   => $b['guest_name'] ?? 'ไม่ระบุชื่อ',
            'status'  => $b['status'],
            'checkin' => $b['checkin'],
            'checkout'=> $b['checkout'],
            'type'    => $b['rt_name']
        ];
    }
}

// 🟩 แยกห้องว่าง/ไม่ว่าง
$available = [];
$occupied = [];
foreach ($rooms_all as $id => $info) {
    $type = $info['type'] ?: 'ไม่ทราบประเภท';
    if (isset($booked[$id])) {
        $occupied[$type][] = [
            'number'  => $info['number'],
            'guest'   => $booked[$id]['guest'],
            'status'  => $booked[$id]['status'],
            'checkin' => $booked[$id]['checkin'],
            'checkout'=> $booked[$id]['checkout']
        ];
    } else {
        $available[$type][] = $info['number'];
    }
}

$total = 0;
foreach ($available as $arr) $total += count($arr);

// ✅ จัดรูปแบบข้อมูลให้ง่ายต่อการใช้ใน modal
$rooms_by_type = [];
foreach ($available as $type => $nums) {
    $rooms_by_type[$type] = [];
    foreach ($nums as $n) {
        $rooms_by_type[$type][] = [
            'number' => $n,
            'available' => true
        ];
    }
}
foreach ($occupied as $type => $rooms) {
    if (!isset($rooms_by_type[$type])) $rooms_by_type[$type] = [];
    foreach ($rooms as $r) {
        $rooms_by_type[$type][] = [
            'number' => $r['number'],
            'available' => false,
            'guest' => $r['guest'],
            'checkin' => $r['checkin'],
            'checkout' => $r['checkout']
        ];
    }
}

// ✅ ส่งกลับไปพร้อมห้องที่ไม่ว่าง (จะขึ้นแดงใน modal)
echo json_encode([
    'success' => true,
    'left' => $total,
    'rooms_by_type' => $rooms_by_type
]);
$conn->close();
?>
