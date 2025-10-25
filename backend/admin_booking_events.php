<?php
require_once "connectdb.php";
require_once "auto_update_booking_status.php";
header('Content-Type: application/json; charset=UTF-8');

$sql = "
SELECT 
  b.b_id,
  b.checkin,
  b.checkout,
  b.status,
  b.room_number,
  b.room_id,
  rt.rt_id,
  rt.rt_name,
  CONCAT(u.first_name, ' ', u.last_name) AS guest_name
FROM booking b
LEFT JOIN room_type rt ON b.rt_id = rt.rt_id
LEFT JOIN users u ON b.user_id = u.user_id
WHERE b.status NOT IN ('pending', 'cancelled')
";

$result = $conn->query($sql);
$events = [];

while ($row = $result->fetch_assoc()) {
    $roomDisplay = $row['room_number'] ? " ({$row['room_number']})" : "";
    $events[] = [
        'id' => $row['b_id'],
        'title' => "{$row['rt_name']}{$roomDisplay}",
        'start' => $row['checkin'],
        'end' => $row['checkout'],
        'extendedProps' => [
            'status' => $row['status'],
            'guest' => $row['guest_name'] ?? 'ไม่มีข้อมูล',
            'checkIn' => $row['checkin'],
            'checkOut' => $row['checkout'],
            'rt_id' => $row['rt_id'],
            'room_id' => $row['room_id'],      // ✅ เพิ่มบรรทัดนี้
            'room_number' => $row['room_number']
        ]
    ];
}

echo json_encode($events);
$conn->close();
?>
