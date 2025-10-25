<?php
require_once "connectdb.php";
header('Content-Type: application/json; charset=UTF-8');

$rt_id = $_GET['rt_id'] ?? null;
if (!$rt_id) { echo json_encode([]); exit; }

$sql = "SELECT room_id, room_number FROM rooms WHERE rt_id = ? ORDER BY room_number ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $rt_id);
$stmt->execute();
$res = $stmt->get_result();

$rooms = [];
while ($r = $res->fetch_assoc()) {
    $rooms[] = $r;
}

echo json_encode($rooms);
$conn->close();
?>
