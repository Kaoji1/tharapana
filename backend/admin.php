<?php

ob_start(); // ✅ เปิด output buffering เพื่อกันไม่ให้ header() error
session_start();

// --------- Guard & Bootstrap ----------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: ../login.php');
  exit;
}

// ===== เมนู Sidebar (เหมือนเดิม) =====
$menuDashboard = [
  'dashboard' => ['file' => 'dashboard.php', 'label' => 'แดชบอร์ด', 'icon' => 'bi-grid-1x2-fill']
];
$menuManagement = [
  'admin_manage_booking'   => ['file' => 'admin_manage_booking.php', 'label' => 'จัดการการจองห้องพัก', 'icon' => 'bi-journal-check'],
  'admin_manage_opening'   => ['file' => 'admin_manage_opening.php', 'label' => 'จัดการจำนวนห้องที่เปิดให้จอง', 'icon' => 'bi-sliders'],
  'manage_room_types'     => ['file' => 'manage_room_types.php', 'label' => 'จัดการข้อมูลห้องพัก', 'icon' => 'bi-key-fill'], // หรือใช้ bi-door-open-fill เดิมก็ได้ครับ
  'admin_calendar'   => ['file' => 'admin_calendar.php', 'label' => 'ปฏิทินการจองห้องพัก ', 'icon' => 'bi-calendar-check-fill'],
];
$menuMarketing = [
  'manage_promotions' => ['file' => 'admin_coupons.php', 'label' => 'โปรโมชั่นและคูปอง', 'icon' => 'bi-tags-fill'],
  'manage_reviews'    => ['file' => 'manage_reviews.php', 'label' => 'รีวิวห้องพัก', 'icon' => 'bi-star-half'],
];
$menuSystem = [
  
  'manage_admins'       => ['file' => 'manage_admins.php', 'label' => 'จัดการแอดมิน', 'icon' => 'bi-person-lock'],
  'manage_members'    => ['file' => 'manage_members.php', 'label' => 'จัดการสมาชิก', 'icon' => 'bi-people-fill'],
  'Logging'       => ['file' => 'Logging.php', 'label' => 'Logging', 'icon' => 'bi-file-earmark-bar-graph-fill'],
];
$menuEdit= [
  'edit_aboutus'    => ['file' => 'edit_aboutus.php', 'label' => 'แก้ไข AboutUs', 'icon' => 'bi-info-circle-fill'],
  'edit_gallery'    => ['file' => 'edit_gallery.php', 'label' => 'แก้ไข Gallery', 'icon' => 'bi-images'],
  'edit_highlights' => ['file' => 'edit_highlights.php', 'label' => 'แก้ไข Highlights', 'icon' => 'bi-star-fill'],
  'edit_policies'   => ['file' => 'edit_policies.php', 'label' => 'แก้ไข Policies', 'icon' => 'bi-shield-check'],
  'edit_service'    => ['file' => 'edit_service.php', 'label' => 'แก้ไข Service', 'icon' => 'bi-gear-wide-connected'],
  'edit_travel'     => ['file' => 'edit_travel.php', 'label' => 'แก้ไข Travel', 'icon' => 'bi-map-fill'],
];

// รวมทุกเมนูเพื่อใช้ตรวจสอบสิทธิ์
$allowedPages = $menuDashboard + $menuManagement + $menuMarketing + $menuSystem + $menuEdit;
$current = $_POST['page'] ?? $_GET['page'] ?? 'dashboard';

// --- ✅ FIX: ย้ายโค้ดตรวจสอบหน้าที่ไม่ถูกต้องมาไว้ตรงนี้ ---
// ถ้าหน้าที่เรียกไม่มีอยู่ในรายการที่อนุญาต ให้ Redirect ไปที่ Dashboard ทันที
if (!isset($allowedPages[$current])) {
  header('Location: admin.php?page=dashboard');
  exit;
}
// -----------------------------------------------------------

// ตรวจสอบสถานะเมนู (เหมือนเดิม)
$isCurrentInManagement = array_key_exists($current, $menuManagement);
$isCurrentInMarketing  = array_key_exists($current, $menuMarketing);
$isCurrentInSystem     = array_key_exists($current, $menuSystem);
// --- ✅ FIX 1: แก้ไขชื่อตัวแปร ไม่ให้ซ้ำซ้อน ---
$isCurrentInEdit       = array_key_exists($current, $menuEdit); 



?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <title>Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    /* ===== THEME: Teal #008374 (rgb(0,131,116)) — refined UI (พร้อมใช้) ===== */
    :root {
      --primary: #008374;
      --primary-50: #e6f5f3;
      --primary-100: #cdebe7;
      --primary-600: #007569;
      --primary-700: #00695f;
      --primary-800: #005e55;
      --primary-contrast: #fff;
      --primary-dark: var(--primary-700);

      --bg: #f4f6f9;
      --card: #fff;
      --text: #2c3e50;
      --muted: #7f8c8d;
      --shadow: 0 6px 20px rgba(0, 0, 0, .08);
      --radius: 14px;

      --sb-1: #004d43;
      --sb-2: #00332d;
      --sb-text: #cdd3d2;
      --sb-header: #fff;
      --sb-hover: rgba(255, 255, 255, .06);
      --sb-border: rgba(255, 255, 255, .12);
    }

    * {
      box-sizing: border-box
    }

    html,
    body {
      height: 100%
    }

    body {
      margin: 0;
      font-family: 'Sarabun', sans-serif;
      background: var(--bg);
      color: var(--text)
    }

    .wrapper {
      display: flex;
      min-height: 100vh;
      align-items: stretch
    }

    /* ===== Sidebar ===== */
    .sidebar {
      width: 280px;
      color: var(--sb-text);
      background: linear-gradient(180deg, var(--sb-1) 0%, var(--sb-2) 100%);
      box-shadow: 0 0 30px rgba(0, 0, 0, .1);
      position: sticky;
      top: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      transition: transform .3s ease-in-out
    }

    .sidebar-inner {
      overflow-y: auto;
      flex: 1;
      padding: 1rem .5rem
    }

    .sidebar-inner::-webkit-scrollbar {
      width: 6px
    }

    .sidebar-inner::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, .25);
      border-radius: 99px
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 1rem;
      margin-bottom: 1rem
    }

    .brand .logo {
      font-size: 1.6rem;
      color: var(--primary);
      background: #fff;
      width: 46px;
      height: 46px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      box-shadow: 0 8px 20px rgba(0, 131, 116, .18)
    }

    .brand .meta strong {
      font-size: 1.05rem;
      color: var(--sb-header)
    }

    .brand .meta small {
      opacity: .75
    }

    hr.sidebar-divider {
      border-color: var(--sb-border);
      margin: 0 .5rem 1rem;
      border-top: 1px solid var(--sb-border)
    }

    .nav-section {
      margin-bottom: .5rem
    }

    .section-toggle {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      background: transparent;
      border: none;
      padding: .85rem 1rem;
      margin: .2rem 0;
      font: 600 .8rem/1 'Sarabun', sans-serif;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--sb-header);
      border-radius: 10px;
      cursor: pointer;
      transition: background .2s
    }

    .section-toggle:hover {
      background: var(--sb-hover)
    }

    .section-toggle .chevron {
      font-size: 1rem;
      transition: transform .3s cubic-bezier(.25, .1, .25, 1)
    }

    .collapse-content {
      max-height: 0;
      overflow: hidden;
      transition: max-height .4s ease;
      /
    }

    .nav-section.open .collapse-content {
      max-height: 560px
    }

    .nav-section.open .section-toggle .chevron {
      transform: rotate(90deg)
    }

    .nav ul {
      list-style: none;
      padding: 0;
      margin: 0
    }

    .nav li {
      margin: 2px 0
    }

    .nav .link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: .72rem 1.1rem;
      border-radius: 10px;
      color: var(--sb-text);
      text-decoration: none;
      font-weight: 600;
      position: relative;
      transition: color .2s, background-color .2s;
      overflow: hidden
    }

    .nav .link:hover {
      background: var(--sb-hover);
      color: #fff
    }

    .nav .link.active {
      color: var(--primary-contrast);
      background: var(--primary);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .06), 0 10px 24px rgba(0, 131, 116, .28)
    }

    .nav .link.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      height: 100%;
      width: 4px;
      background: #fff;
      box-shadow: 0 0 14px #fff, 0 0 18px rgba(0, 131, 116, .55)
    }

    .nav .link i {
      font-size: 1.1rem;
      width: 24px;
      text-align: center
    }

    .sidebar-footer {
      padding: 1rem;
      border-top: 1px solid var(--sb-border)
    }

    .logout {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, .06);
      border: 1px solid var(--sb-border);
      color: #fff;
      padding: .6rem 1rem;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 600;
      transition: all .2s;
      width: 100%
    }

    .logout:hover {
      background: #fff;
      color: var(--sb-1)
    }

    /* ===== Topbar & Content ===== */
    .content {
      flex: 1;
      min-width: 0;
      padding: 2rem
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      background: linear-gradient(90deg, rgba(0, 131, 116, .12), rgba(0, 131, 116, .04));
      border: 1px solid rgba(0, 131, 116, .12);
      padding: 1rem 1.25rem;
      border-radius: 14px
    }

    .title {
      margin: 0;
      font-size: 1.7rem;
      font-weight: 800;
      color: var(--primary-dark);
      letter-spacing: .2px
    }

    .role-badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .35rem .65rem;
      border-radius: 999px;
      background: var(--primary-50);
      color: var(--primary-700);
      border: 1px solid var(--primary-100);
      font-weight: 700
    }

    .page-content {
      background: var(--card);
      padding: var(--pad, 1.5rem);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      animation: fadeIn .25s ease
    }

    @keyframes fadeIn {
      from {
        opacity: .0;
        transform: translateY(4px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    /* ===== Mobile ===== */
    .mobile-toggle {
      display: none
    }

    @media (max-width:992px) {
      .sidebar {
        position: fixed;
        transform: translateX(-100%);
        z-index: 1000
      }

      .sidebar.open {
        transform: translateX(0)
      }

      .content {
        padding: 6rem 1rem 1rem
      }

      .mobile-toggle {
        display: grid;
        place-items: center;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: #fff;
        border: 1px solid #e9eef3;
        border-radius: 12px;
        width: 44px;
        height: 44px;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: var(--shadow)
      }

      .mobile-toggle:hover {
        border-color: var(--primary)
      }

      .overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .4);
        z-index: 999
      }

      .overlay:not(.show) {
        display: none
      }
    }

    :focus-visible {
      outline: 3px solid rgba(0, 131, 116, .35);
      outline-offset: 2px;
      border-radius: 10px
    }

    ::selection {
      background: rgba(0, 131, 116, .2)
    }

    a {
      color: var(--primary-700)
    }

    a:hover {
      color: var(--primary)
    }
  </style>
</head>

<body>

  <div id="overlay" class="overlay"></div>
  <button class="mobile-toggle" id="btnToggle" aria-label="เปิดเมนู"><i class="bi bi-list"></i></button>

  <div class="wrapper">
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <div class="logo"><i class="bi bi-shield-check"></i></div>
        <div class="meta">
          <strong>Admin Panel</strong> <br>
          <small><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></small>
        </div>
      </div>

      <div class="sidebar-inner">
        <nav class="nav" style="padding: 0 .5rem;">
          <ul>
            <li>
              <a class="link <?= $current === 'dashboard' ? 'active' : '' ?>" href="admin.php?page=dashboard">
                <i class="bi <?= htmlspecialchars($menuDashboard['dashboard']['icon']) ?>"></i>
                <span><?= htmlspecialchars($menuDashboard['dashboard']['label']) ?></span>
              </a>
            </li>
          </ul>
        </nav>
        <hr class="sidebar-divider">

        <div class="nav-section <?= $isCurrentInManagement ? 'open' : '' ?>">
          <button class="section-toggle" aria-expanded="<?= $isCurrentInManagement ? 'true' : 'false' ?>">
            <span>การจัดการหลัก</span>
            <i class="bi bi-chevron-right chevron"></i>
          </button>
          <div class="collapse-content">
            <nav class="nav" aria-label="เมนูการจัดการหลัก">
              <ul>
                <?php foreach ($menuManagement as $pageKey => $details): ?>
                  <li>
                    <a class="link <?= $current === $pageKey ? 'active' : '' ?>" href="admin.php?page=<?= $pageKey ?>">
                      <i class="bi <?= htmlspecialchars($details['icon']) ?>"></i>
                      <span><?= htmlspecialchars($details['label']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </nav>
          </div>
        </div>

        <div class="nav-section <?= $isCurrentInMarketing ? 'open' : '' ?>">
          <button class="section-toggle" aria-expanded="<?= $isCurrentInMarketing ? 'true' : 'false' ?>">
            <span>การตลาดและลูกค้าสัมพันธ์</span>
            <i class="bi bi-chevron-right chevron"></i>
          </button>
          <div class="collapse-content">
            <nav class="nav" aria-label="เมนูการตลาดและลูกค้าสัมพันธ์">
              <ul>
                <?php foreach ($menuMarketing as $pageKey => $details): ?>
                  <li>
                    <a class="link <?= $current === $pageKey ? 'active' : '' ?>" href="admin.php?page=<?= $pageKey ?>">
                      <i class="bi <?= htmlspecialchars($details['icon']) ?>"></i>
                      <span><?= htmlspecialchars($details['label']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </nav>
          </div>
        </div>

        <div class="nav-section <?= $isCurrentInSystem ? 'open' : '' ?>">
          <button class="section-toggle" aria-expanded="<?= $isCurrentInSystem ? 'true' : 'false' ?>">
            <span>ระบบและรายงาน</span>
            <i class="bi bi-chevron-right chevron"></i>
          </button>
          <div class="collapse-content">
            <nav class="nav" aria-label="เมนูระบบและรายงาน">
              <ul>
                <?php foreach ($menuSystem as $pageKey => $details): ?>
                  <li>
                    <a class="link <?= $current === $pageKey ? 'active' : '' ?>" href="admin.php?page=<?= $pageKey ?>">
                      <i class="bi <?= htmlspecialchars($details['icon']) ?>"></i>
                      <span><?= htmlspecialchars($details['label']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </nav>
          </div>
        </div>

        <div class="nav-section <?= $isCurrentInEdit ? 'open' : '' ?>">
          <button class="section-toggle" aria-expanded="<?= $isCurrentInEdit? 'true' : 'false' ?>">
            <span>จัดการหน้าเว็ป</span>
            <i class="bi bi-chevron-right chevron"></i>
          </button>
          <div class="collapse-content">
            <nav class="nav" aria-label="จัดการหน้าเว็ป">
              <ul>
                <?php foreach ($menuEdit as $pageKey => $details): ?>
                  <li>
                    <a class="link <?= $current === $pageKey ? 'active' : '' ?>" href="admin.php?page=<?= $pageKey ?>">
                      <i class="bi <?= htmlspecialchars($details['icon']) ?>"></i>
                      <span><?= htmlspecialchars($details['label']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </nav>
          </div>
        </div>
        </div> <div class="sidebar-footer">
        <a class="logout" href="../logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
      </div>
    </aside>

    <main class="content">
      <div class="topbar">
        <h1 class="title">
          <?= htmlspecialchars($allowedPages[$current]['label'] ?? 'ไม่พบหน้า') ?>
        </h1>
        <div>
          <?php
          $role = $_SESSION['role'] ?? '';
          $role_th = $role === 'admin' ? 'ผู้ดูแลระบบ' : ($role === 'staff' ? 'พนักงาน' : 'ผู้จัดการ');
          ?>
          <span class="role-badge"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($role_th) ?></span>
        </div>
      </div>

      <div class="page-content">
        <?php
        if (isset($allowedPages[$current])) {
          $file = __DIR__ . '/' . $allowedPages[$current]['file'];
          if (is_file($file)) {
            // ส่งค่าตัวแปรเพื่อให้ไฟล์ที่ include รู้ว่าถูกเรียกจาก admin panel
            if (!defined('ADMIN_EMBED')) define('ADMIN_EMBED', true);
            include $file;
          } else {
            echo "<h3><i class='bi bi-exclamation-triangle-fill'></i> Error</h3><p>ไม่พบไฟล์: <code>" . htmlspecialchars($allowedPages[$current]['file']) . "</code></p>";
          }
        } else {
          // ส่วนนี้จริงๆ ไม่จำเป็นแล้ว เพราะเรามี Guard อยู่ด้านบน แต่เก็บไว้เผื่อก็ได้
          header('Location: admin.php?page=dashboard');
          exit;
        }
        ?>
      </div>
    </main>
  </div>

  <script>
    // --- Toggle Sidebar (สำหรับ Mobile) ---
    const btnToggle = document.getElementById('btnToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    function closeMenu() {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    }
    btnToggle?.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show', sidebar.classList.contains('open'));
    });
    overlay?.addEventListener('click', closeMenu);

    // --- Accordion Menu ---
    document.addEventListener('DOMContentLoaded', function() {
      const accordions = document.querySelectorAll('.nav-section .section-toggle');
      accordions.forEach(button => {
        button.addEventListener('click', () => {
          const section = button.parentElement;
          const isExpanded = section.classList.contains('open');

          // ปิด accordion อื่นๆ ทั้งหมดก่อนเปิดอันใหม่
          document.querySelectorAll('.nav-section').forEach(s => {
            if (s !== section) {
              s.classList.remove('open');
              s.querySelector('.section-toggle').setAttribute('aria-expanded', 'false');
            }
          });

          // เปิด/ปิดอันที่คลิก
          section.classList.toggle('open');
          button.setAttribute('aria-expanded', !isExpanded);
        });
      });
    });
  </script>

</body>
<?php ob_end_flush(); // ✅ ปล่อย output ออกหลังสุด ?>

</html>