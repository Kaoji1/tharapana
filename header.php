<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<header id="header" class="header fixed-top">

  <!-- 🔝 Top Bar -->
  <div class="topbar d-flex align-items-center">
    <div class="container d-flex justify-content-center justify-content-md-between">
      <div class="contact-info d-flex align-items-center">
        <i class="bi bi-envelope d-flex align-items-center">
          <a href="mailto:tharapana60@gmail.com">tharapana60@gmail.com</a>
        </i>
        <i class="bi bi-phone d-flex align-items-center ms-4">
          <span>093 019 9299</span>
        </i>
      </div>
      <div class="social-links d-none d-md-flex align-items-center">
        <a href="#" class="twitter"><i class="bi bi-twitter-x"></i></a>
        <a href="https://www.facebook.com/TharapanaKhaoyaiResort/" class="facebook"><i class="bi bi-facebook"></i></a>
        <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
        <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
      </div>
    </div>
  </div>
  <!-- End Top Bar -->

  <!-- ✅ Navbar -->
  <div class="container position-relative d-flex align-items-center justify-content-between">
    <a href="index.php" class="logo d-flex align-items-center">
      <img src="assets/img/logo.png" alt="Logo" style="width: 150px; height: auto;">
      <span>.</span>
    </a>
    <nav id="navmenu" class="navmenu">
      <ul>
        <?php if (isset($_SESSION["user_id"])): ?>
          <li class="dropdown">
            <a href="#">
              <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION["user_name"] ?? 'บัญชีของฉัน') ?>
              <i class="bi bi-chevron-down toggle-dropdown"></i>
            </a>
            <ul>
              <li><a href="profile.php">📄 โปรไฟล์ของฉัน</a></li>
              <li><a href="logout.php" class="text-danger">🚪 ออกจากระบบ</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li><a href="ReandLog.php"><button class="btn">เข้าสู่ระบบ</button></a></li>
        <?php endif; ?>
        <li><a href="index.php">Home</a></li>
        <li><a href="index.php#booking">Booking</a></li>
        <li><a href="index.php#about">About</a></li>
        <li><a href="index.php#services">Services</a></li>
        <li><a href="index.php#policies">Policies</a></li>
        <li><a href="contact.php">Contact</a></li>
        <li><a href="reviews.php">Reviews</a></li>
      </ul>
      <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
    </nav>
  </div>
</header>