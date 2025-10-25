<?php
function getAvailableRooms($conn, $rt_id, $checkin, $checkout) {
    $sql = "
        SELECT r.room_id, r.room_number
        FROM rooms r
        WHERE r.rt_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM bookings b
            WHERE b.room_id = r.room_id
            AND b.status = 'reserved'
            AND (
                (b.checkin <= ? AND b.checkout > ?) OR
                (b.checkin < ? AND b.checkout >= ?) OR
                (b.checkin >= ? AND b.checkout <= ?)
            )
        )
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issssss", $rt_id, $checkin, $checkin, $checkout, $checkout, $checkin, $checkout);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $available = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $available[] = $row;
    }

    return $available;
}
?>