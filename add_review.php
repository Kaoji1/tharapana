<?php
session_start();
require_once "connectdb.php";

// ต้อง login
if (!isset($_SESSION["user_id"])) {
    header("Location: ReandLog.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_SESSION["user_id"];
    $b_id    = (int)$_POST["b_id"];
    $rating  = (int)$_POST["rating"];
    $comment = trim($_POST["comment"]);

    // ✅ ดึง rt_id จาก booking
    $sql = "SELECT rt_id FROM booking WHERE b_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $b_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die("❌ ไม่พบข้อมูลการจองนี้ หรือคุณไม่มีสิทธิ์เขียนรีวิว");
    }

    $rt_id = $row["rt_id"];

    // ✅ บันทึก review
    $sql = "INSERT INTO reviews (b_id, user_id, rt_id, rating, comment, created_at) 
            VALUES (?,?,?,?,?,NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiis", $b_id, $user_id, $rt_id, $rating, $comment);

    if ($stmt->execute()) {
        header("Location: profile.php?review=success");
        exit;
    } else {
        echo "❌ เกิดข้อผิดพลาด: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: profile.php");
    exit;
}
