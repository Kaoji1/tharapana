<?php
session_start();
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เลือกหน้าที่ต้องการไป</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f5f5f5;
      font-family: 'Prompt', sans-serif;
    }
    .container {
      margin-top: 120px;
      background: #fff;
      border-radius: 12px;
      padding: 40px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      text-align: center;
    }
    .btn-choice {
      width: 200px;
      margin: 10px;
      padding: 15px 0;
      border-radius: 8px;
      font-size: 18px;
    }
    .btn-main {
      background-color: #008374;
      color: white;
    }
    .btn-admin {
      background-color: #004d40;
      color: white;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2 class="mb-4 text-dark">สวัสดีคุณ <?= htmlspecialchars($_SESSION["user_name"] ?? 'Admin') ?></h2>
    <p class="mb-4">กรุณาเลือกหน้าที่คุณต้องการเข้าสู่ระบบ</p>

    <div>
      <a href="index.php" class="btn btn-main btn-choice">หน้าเว็บไซต์หลัก</a>
      <a href="backend/admin.php" class="btn btn-admin btn-choice">หลังร้าน (Admin)</a>
    </div>
  </div>
</body>
</html>
