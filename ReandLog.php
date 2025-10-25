<?php
session_start();

/* เชื่อมต่อฐานข้อมูล */
require_once __DIR__ . "/connectdb.php";

$alert = "";

/* ---------------- Register ---------------- */
if (
  $_SERVER["REQUEST_METHOD"] === "POST"
  && isset($_POST["action"]) && $_POST["action"] === "register"
) {

  $first = trim($_POST["first_name"] ?? "");
  $last  = trim($_POST["last_name"] ?? "");
  $email = trim($_POST["email_register"] ?? "");
  $email_confirm = trim($_POST["email_confirm"] ?? "");
  $phone = trim($_POST["phone_number"] ?? "");
  $pass  = $_POST["password_register"] ?? "";
  $pass2 = $_POST["password_confirm"] ?? "";

  if ($first === "" || $last === "" || $email === "" || $pass === "") {
    $alert = "กรอกข้อมูลให้ครบถ้วน";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $alert = "รูปแบบอีเมลไม่ถูกต้อง";
  } elseif ($email !== $email_confirm) {
    $alert = "อีเมลยืนยันไม่ตรงกัน";
  } elseif ($pass !== $pass2) {
    $alert = "รหัสผ่านยืนยันไม่ตรงกัน";
  } else {
    $sql = "SELECT id FROM users WHERE email = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
      mysqli_stmt_bind_param($stmt, "s", $email);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_store_result($stmt);

      if (mysqli_stmt_num_rows($stmt) > 0) {
        $alert = "อีเมลนี้ถูกใช้แล้ว";
      } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        // สมัครใหม่ role = user
        $ins = "INSERT INTO users(first_name, last_name, email, phone, password_hash, role) 
                        VALUES(?,?,?,?,?, 'user')";
        if ($stmt2 = mysqli_prepare($conn, $ins)) {
          mysqli_stmt_bind_param($stmt2, "sssss", $first, $last, $email, $phone, $hash);
          if (mysqli_stmt_execute($stmt2)) {
            $alert = "สมัครสมาชิกสำเร็จ! เข้าระบบได้เลย";
          } else {
            $alert = "บันทึกไม่สำเร็จ: " . mysqli_error($conn);
          }
          mysqli_stmt_close($stmt2);
        } else {
          $alert = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง (insert)";
        }
      }
      mysqli_stmt_close($stmt);
    } else {
      $alert = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง (select)";
    }
  }
}

/* ---------------- Login ---------------- */
if (
  $_SERVER["REQUEST_METHOD"] === "POST"
  && isset($_POST["action"]) && $_POST["action"] === "login"
) {

  $email = trim($_POST["email_login"] ?? "");
  $pass  = $_POST["password_login"] ?? "";

  if ($email === "" || $pass === "") {
    $alert = "กรอกอีเมลและรหัสผ่าน";
  } else {
    $sql = "SELECT user_id, first_name, last_name, email, password_hash, role 
        FROM users WHERE email = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
      mysqli_stmt_bind_param($stmt, "s", $email);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $user = $res ? mysqli_fetch_assoc($res) : null;

      if ($user && password_verify($pass, $user["password_hash"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
        $_SESSION["role"]      = $user["role"];

        // 🔹 redirect ตาม role
        if ($user["role"] === "admin") {
          header("Location: choose_page.php");
        } else {
          header("Location: index.php");
        }
        exit;
      } else {
        $alert = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
      }
      mysqli_stmt_close($stmt);
    } else {
      $alert = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง (login)";
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login/Register</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet" />
  <style>
    body {
      background: #f5f5f5;
    }

    .container {
      margin-top: 80px;
      width: 400px;
      padding: 30px 20px;
      border-radius: 10px;
    }

    .tabs .indicator {
      background-color: #e0f2f1;
      height: 60px;
      opacity: 0.3;
    }

    .form-container {
      padding: 20px 10px 30px 10px;
    }

    .teal {
      background-color: #008374 !important;
    }

    .teal-text {
      color: #008374 !important;
    }
  </style>
</head>

<body>
  <div class="container white z-depth-2">
    <ul class="tabs teal">
      <li class="tab col s3"><a class="white-text active" href="#login">Login</a></li>
      <li class="tab col s3"><a class="white-text" href="#register">Register</a></li>
    </ul>

    <?php if ($alert): ?>
      <div class="card-panel red lighten-4" style="margin-top:10px;">
        <span class="red-text text-darken-4"><?= htmlspecialchars($alert) ?></span>
      </div>
    <?php endif; ?>

    <!-- Login Form -->
    <div id="login" class="col s12">
      <form class="col s12" method="post" onsubmit="saveLoginData()">
        <input type="hidden" name="action" value="login">
        <div class="form-container">
          <h3 class="teal-text">Log In</h3>
          <div class="row">
            <div class="input-field col s12">
              <input id="email-login" name="email_login" type="email" class="validate" required />
              <label for="email-login">Email</label>
            </div>
          </div>
          <div class="row">
            <div class="input-field col s12">
              <input id="password-login" name="password_login" type="password" class="validate" required />
              <label for="password-login">Password</label>
            </div>
          </div>
          <p>
            <label>
              <input type="checkbox" id="remember" />
              <span>Remember Me</span>
            </label>
          </p>
          <center>
            <button class="btn waves-effect waves-light teal" type="submit">Connect</button>
          </center>
        </div>
      </form>
    </div>

    <!-- Register Form -->
    <div id="register" class="col s12">
      <form class="col s12" method="post">
        <input type="hidden" name="action" value="register">
        <div class="form-container">
          <h4 class="teal-text">Create an account</h4>
          <div class="row">
            <div class="input-field col s6">
              <input id="first-name" name="first_name" type="text" required />
              <label for="first-name">First Name</label>
            </div>
            <div class="input-field col s6">
              <input id="last-name" name="last_name" type="text" required />
              <label for="last-name">Last Name</label>
            </div>
          </div>
          <div class="row">
            <div class="input-field col s12">
              <input id="email-register" name="email_register" type="email" required />
              <label for="email-register">Email</label>
            </div>
          </div>
          <div class="row">
            <div class="input-field col s12">
              <input id="email-confirm" name="email_confirm" type="email" required />
              <label for="email-confirm">Email Confirmation</label>
            </div>
          </div>
          <div class="row">
            <div class="input-field col s12">
              <input id="phone-number" name="phone_number" type="tel" />
              <label for="phone-number">Phone</label>
            </div>
          </div>
          <div class="row">
            <div class="input-field col s12">
              <input id="password-register" name="password_register" type="password" required />
              <label for="password-register">Password</label>
            </div>
          </div>
          <div class="row">
            <div class="input-field col s12">
              <input id="password-confirm" name="password_confirm" type="password" required />
              <label for="password-confirm">Password Confirmation</label>
            </div>
          </div>
          <div class="row">
            <div class="col s12 center-align">
              <button class="btn waves-effect waves-light teal" type="submit" style="width: 100%">Submit</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Materialize JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      var tabs = document.querySelectorAll(".tabs");
      M.Tabs.init(tabs);
    });
  </script>

  <!-- CryptoJS สำหรับ Remember Me -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
  <script>
    const secretKey = "my_secret_key";

    function saveLoginData() {
      if (document.getElementById("remember").checked) {
        localStorage.setItem("email", document.getElementById("email-login").value);
        let encryptedPassword = CryptoJS.AES.encrypt(
          document.getElementById("password-login").value,
          secretKey
        ).toString();
        localStorage.setItem("password", encryptedPassword);
        localStorage.setItem("remember", "true");
      } else {
        localStorage.removeItem("email");
        localStorage.removeItem("password");
        localStorage.setItem("remember", "false");
      }
    }

    window.onload = function() {
      if (localStorage.getItem("remember") === "true") {
        document.getElementById("remember").checked = true;

        let storedEmail = localStorage.getItem("email");
        let storedPassword = localStorage.getItem("password");

        if (storedEmail) {
          document.getElementById("email-login").value = storedEmail;
        }
        if (storedPassword && storedPassword !== "") {
          try {
            let decryptedPassword = CryptoJS.AES.decrypt(
              storedPassword,
              secretKey
            ).toString(CryptoJS.enc.Utf8);
            if (decryptedPassword) {
              document.getElementById("password-login").value = decryptedPassword;
            }
          } catch (e) {
            console.error("Error decrypting password:", e);
          }
        }
      }
    };
  </script>
</body>

</html>