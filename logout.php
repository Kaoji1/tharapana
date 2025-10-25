<?php
session_start();
session_unset();  // ลบ session ทั้งหมด
session_destroy();
header("Location: index.php"); // กลับไปหน้าแรก
exit;
