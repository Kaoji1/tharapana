<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Basic security check (optional but recommended)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/connectdb.php';
define('IMG_DISPLAY_PATH', '../assets/img/');

$rt_id = filter_input(INPUT_GET, 'rt_id', FILTER_VALIDATE_INT);

if (!$rt_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Room Type ID']);
    exit;
}

$response = ['success' => true, 'image' => null, 'amenities' => [], 'benefits' => []];

mysqli_begin_transaction($conn);
try {
    // Get Image (assuming one primary image for simplicity)
    $sql_img = "SELECT r_images FROM room_images WHERE rt_id = ? ORDER BY rimg_id LIMIT 1"; // Order might be needed
    $stmt_img = mysqli_prepare($conn, $sql_img);
    mysqli_stmt_bind_param($stmt_img, "i", $rt_id);
    mysqli_stmt_execute($stmt_img);
    $result_img = mysqli_stmt_get_result($stmt_img);
    if ($row_img = mysqli_fetch_assoc($result_img)) {
        $response['image'] = $row_img['r_images'];
    }
    mysqli_stmt_close($stmt_img);

    // Get Amenities
    $sql_amenities = "SELECT rin_name FROM room_into WHERE rt_id = ?";
    $stmt_amenities = mysqli_prepare($conn, $sql_amenities);
    mysqli_stmt_bind_param($stmt_amenities, "i", $rt_id);
    mysqli_stmt_execute($stmt_amenities);
    $result_amenities = mysqli_stmt_get_result($stmt_amenities);
    while ($row_amen = mysqli_fetch_assoc($result_amenities)) {
        $response['amenities'][] = $row_amen['rin_name'];
    }
    mysqli_stmt_close($stmt_amenities);

    // Get Benefits
    $sql_benefits = "SELECT ben_text FROM booking_benefits WHERE rt_id = ?";
    $stmt_benefits = mysqli_prepare($conn, $sql_benefits);
    mysqli_stmt_bind_param($stmt_benefits, "i", $rt_id);
    mysqli_stmt_execute($stmt_benefits);
    $result_benefits = mysqli_stmt_get_result($stmt_benefits);
    while ($row_ben = mysqli_fetch_assoc($result_benefits)) {
        $response['benefits'][] = $row_ben['ben_text'];
    }
    mysqli_stmt_close($stmt_benefits);

    mysqli_commit($conn); // Not strictly needed for SELECTs, but good practice

} catch (Exception $e) {
    mysqli_rollback($conn);
    $response = ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    error_log("AJAX Get Room Details Error (rt_id: {$rt_id}): " . $e->getMessage());
}

if ($conn && mysqli_ping($conn)) {
    mysqli_close($conn);
}

echo json_encode($response);
?>