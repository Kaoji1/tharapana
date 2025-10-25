<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Basic security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/connectdb.php';

$rt_id = filter_input(INPUT_GET, 'rt_id', FILTER_VALIDATE_INT);

if (!$rt_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Room Type ID']);
    exit;
}

$response = ['success' => true, 'rooms' => []];

try {
    $sql = "SELECT room_number FROM rooms WHERE rt_id = ? ORDER BY room_number ASC"; // Optional ordering
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $rt_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $response['rooms'][] = $row['room_number'];
    }
    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    $response = ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    error_log("AJAX Get Rooms Error (rt_id: {$rt_id}): " . $e->getMessage());
}

if ($conn && mysqli_ping($conn)) {
    mysqli_close($conn);
}

echo json_encode($response);
?>