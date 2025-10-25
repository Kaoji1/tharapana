<?php
include_once("connectdb.php");
session_start();
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Tharapana Khaoyai Resort</title>

  <!-- Bootstrap / Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Vendor CSS -->
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <!-- Fancybox -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css" />

  <!-- Main CSS -->
  <link href="assets/css/main.css" rel="stylesheet">
  <link href="assets/css/new.css" rel="stylesheet">

  <style>
    /* ปุ่มหน้าแรก */
    .btn {
      padding: 0.8em 1.75em;
      background-color: transparent;
      border: none;
      border-radius: 0;
      transition: 0.4s;
      cursor: pointer;
      font-size: 17px;
      text-transform: uppercase;
      color: white;
      display: inline-block;
    }

    .btn:hover {
      border: 1px solid white;
      border-radius: 6px;
    }

    @media screen and (max-width: 768px) {
      .btn {
        display: block;
        margin: 1rem auto;
        font-size: 18px;
        padding: 10px 20px;
        background-color: #008374;
        color: white;
        border-radius: 6px;
      }

      .btn:hover {
        background-color: #00a38c;
      }
    }
  </style>
</head>

<body>

  <?php include_once "header.php"; ?>

  <main class="main">
    <?php

    // ✅ รับค่าหมวดหมู่จาก GET (ถ้ามี)
    $selected_category = $_GET['category'] ?? "";

    // ✅ ดึงรายการหมวดหมู่จาก gallery_type
    $categories = [];
    $cat_sql = "SELECT gt_id, gt_name FROM gallery_type ORDER BY gt_id ASC";
    $cat_res = $conn->query($cat_sql);
    while ($c = $cat_res->fetch_assoc()) {
      $categories[] = $c;
    }

    // ✅ ดึงรูปภาพจาก gallery_edit + gallery_type
    $gallery_items = [];
    if (!empty($selected_category)) {
      $sql = "SELECT e.image_filename, e.image_title, e.gt_id, t.gt_name 
          FROM gallery_edit e
          INNER JOIN gallery_type t ON e.gt_id = t.gt_id
          WHERE e.gt_id = ?
          ORDER BY e.upload_date ASC";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $selected_category);
      $stmt->execute();
      $res = $stmt->get_result();
    } else {
      $res = $conn->query("SELECT e.image_filename, e.image_title, e.gt_id, t.gt_name 
                       FROM gallery_edit e
                       INNER JOIN gallery_type t ON e.gt_id = t.gt_id
                       ORDER BY e.upload_date ASC");
    }
    while ($r = $res->fetch_assoc()) {
      $gallery_items[] = $r;
    }
    ?>

    <!-- ==================== GALLERY SECTION ==================== -->
    <section id="gallery" class="gallery-pharapana-main">
      <div class="gallery-container">
        <div class="gallery-header">
          <h1>Tharapana Khaoyai Resort</h1>
          <p style="text-align:center;">289 Moo 5, Pak Chong, Nakhon Ratchasima, Thailand</p>

          <!-- ✅ Dropdown เลือกหมวดหมู่ -->
          <form method="GET" id="categoryForm" style="text-align:center; margin:15px 0;">
            <select name="category" class="form-select d-inline-block w-auto"
              onchange="document.getElementById('categoryForm').submit();">
              <option value="">📁 แสดงทั้งหมด</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['gt_id']; ?>"
                  <?php echo ($cat['gt_id'] == $selected_category) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat['gt_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>

        <div class="gallery-grid">
          <div class="main-image">
            <?php if (!empty($gallery_items)): ?>
              <img src="uploads/<?php echo htmlspecialchars($gallery_items[0]['image_filename']); ?>"
                alt="<?php echo htmlspecialchars($gallery_items[0]['image_title']); ?>"
                onclick="openGallery()">
            <?php else: ?>
              <img src="assets/img/placeholder.jpg" alt="No Image">
            <?php endif; ?>
          </div>

          <div class="small-images" id="thumbnailContainer"></div>
        </div>
      </div>

      <!-- ✅ Lightbox (ซูมภาพ) -->
      <div class="lightbox" id="lightbox">
        <span class="close-btn" onclick="closeLightbox()">✖</span>
        <span class="arrow prev" onclick="prevImage()">❮</span>
        <img id="lightbox-img" src="" alt="Zoomed Image">
        <span class="arrow next" onclick="nextImage()">❯</span>
      </div>

      <!-- ✅ Popup Gallery (ดูภาพทั้งหมดในปอปอัพ) -->
      <div class="popup" id="popupGallery">
        <div class="popup-box">
          <div class="popup-header">
            <h3>แกลเลอรี่ภาพทั้งหมด</h3>
            <button class="close-btn" onclick="closeGallery()">✖</button>
          </div>

          <div class="popup-grid" id="popupGalleryGrid"></div>
        </div>
      </div>

      <style>
        /* === Popup เบา ไม่กระตุก === */
        .popup {
          display: none;
          position: fixed;
          inset: 0;
          background: rgba(0, 0, 0, 0.8);
          justify-content: center;
          align-items: center;
          z-index: 1000;
        }

        .popup-box {
          background: #fff;
          border-radius: 10px;
          padding: 20px;
          width: 85%;
          max-width: 1000px;
          max-height: 85vh;
          overflow-y: auto;
        }

        .popup-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 10px;
          border-bottom: 1px solid #ddd;
          padding-bottom: 8px;
        }

        .popup-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
          gap: 10px;
        }

        .popup-grid img {
          width: 100%;
          height: 150px;
          object-fit: cover;
          border-radius: 8px;
          cursor: pointer;
          transition: 0.2s;
        }

        .popup-grid img:hover {
          transform: scale(1.03);
          border: 1px solid #00bfa5;
        }

        .close-btn {
          background: none;
          border: none;
          font-size: 1.5rem;
          color: #333;
          cursor: pointer;
        }

        .close-btn:hover {
          color: #e53935;
        }
      </style>

      <script>
        const galleryImages = <?php echo json_encode($gallery_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const thumbnailContainer = document.getElementById("thumbnailContainer");
        const popupGalleryGrid = document.getElementById("popupGalleryGrid");
        const popupGallery = document.getElementById("popupGallery");
        let currentIndex = 0;

        function createThumbnails() {
          const visibleCount = 7;
          thumbnailContainer.innerHTML = "";
          galleryImages.forEach((img, index) => {
            const src = "uploads/" + img.image_filename;
            if (index < visibleCount) {
              const thumb = document.createElement("img");
              thumb.src = src;
              thumb.alt = img.image_title;
              thumb.onclick = () => openLightbox(index);
              thumbnailContainer.appendChild(thumb);
            } else if (index === visibleCount) {
              const overlay = document.createElement("div");
              overlay.className = "overlay";
              overlay.onclick = openGallery;
              const thumb = document.createElement("img");
              thumb.src = src;
              overlay.appendChild(thumb);
              overlay.innerHTML += `<div>+${galleryImages.length - visibleCount} ภาพ</div>`;
              thumbnailContainer.appendChild(overlay);
            }
          });
        }

        function openGallery() {
          popupGallery.style.display = "flex";
          renderPopup();
        }

        function closeGallery() {
          popupGallery.style.display = "none";
        }

        function renderPopup() {
          popupGalleryGrid.innerHTML = "";
          galleryImages.forEach((img, index) => {
            const el = document.createElement("img");
            el.src = "uploads/" + img.image_filename;
            el.alt = img.image_title;
            el.onclick = () => openLightbox(index);
            popupGalleryGrid.appendChild(el);
          });
        }

        function openLightbox(index) {
          currentIndex = index;
          document.getElementById("lightbox-img").src = "uploads/" + galleryImages[index].image_filename;
          document.getElementById("lightbox").classList.add("active");
        }

        function closeLightbox() {
          document.getElementById("lightbox").classList.remove("active");
        }

        function prevImage() {
          currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length;
          document.getElementById("lightbox-img").src = "uploads/" + galleryImages[currentIndex].image_filename;
        }

        function nextImage() {
          currentIndex = (currentIndex + 1) % galleryImages.length;
          document.getElementById("lightbox-img").src = "uploads/" + galleryImages[currentIndex].image_filename;
        }

        document.getElementById('lightbox').addEventListener('click', e => {
          if (e.target.id === 'lightbox') closeLightbox();
        });

        createThumbnails();
      </script>
    </section>








    <section id="booking" class="booking">
      <script>
        function openModal() {
          document.getElementById("myModal").style.display = "block";
        }

        function closeModal() {
          document.getElementById("myModal").style.display = "none";
        }

        function showImageFull(src) {
          const win = window.open();
          win.document.write('<img src="' + src + '" style="width:100%">');
        }

        window.onclick = function(event) {
          const modal = document.getElementById("myModal");
          if (event.target == modal) {
            modal.style.display = "none";
          }
        }

        document.getElementById('dateForm').addEventListener('submit', function(e) {
          e.preventDefault();
          const checkin = document.getElementById('checkin').value;
          const checkout = document.getElementById('checkout').value;
          const rooms = document.getElementById('rooms').value;
          const adults = document.getElementById('adults').value;
          const children = document.getElementById('children').value;

          if (!checkin || !checkout) {
            alert("กรุณาเลือกวันที่เช็คอินและเช็คเอาต์ก่อน");
            return;
          }

          const query =
            `checkin=${checkin}&checkout=${checkout}&rooms=${rooms}&adults=${adults}&children=${children}`;
          window.location.href = `search.php?${query}`;
        });
      </script>




      <?php
      // include 'db_connect.php'; // ← แก้ให้ตรงโปรเจ็กต์

      /* 1) โหลด limits จาก room_base */
      $limits = [
        'rooms'    => ['min' => 1, 'max' => 10, 'def' => 1],
        'adults'   => ['min' => 1, 'max' => 20, 'def' => 2],
        'children' => ['min' => 0, 'max' => 10, 'def' => 0],
      ];
      $sqlBase = "SELECT rb_name, `min`, `max`, `def` FROM room_base";
      if ($res = $conn->query($sqlBase)) {
        while ($r = $res->fetch_assoc()) {
          $key = null;
          if ($r['rb_name'] === 'ห้อง')   $key = 'rooms';
          if ($r['rb_name'] === 'ผู้ใหญ่') $key = 'adults';
          if ($r['rb_name'] === 'เด็ก')    $key = 'children';
          if ($key) $limits[$key] = ['min' => (int)$r['min'], 'max' => (int)$r['max'], 'def' => (int)$r['def']];
        }
      }

      /* helper clamp */
      $clamp = function ($v, $mi, $ma) {
        $v = (int)$v;
        if ($v < $mi) $v = $mi;
        if ($v > $ma) $v = $ma;
        return $v;
      };

      /* 2) รับค่า GET + บีบให้อยู่ในช่วง */
      $checkin  = $_GET['checkin']  ?? '';
      $checkout = $_GET['checkout'] ?? '';
      $rooms    = isset($_GET['rooms'])    ? $clamp($_GET['rooms'],    $limits['rooms']['min'],    $limits['rooms']['max'])    : $limits['rooms']['def'];
      $adults   = isset($_GET['adults'])   ? $clamp($_GET['adults'],   $limits['adults']['min'],   $limits['adults']['max'])   : $limits['adults']['def'];
      $children = isset($_GET['children']) ? $clamp($_GET['children'], $limits['children']['min'], $limits['children']['max']) : $limits['children']['def'];

      /* 3) โหลดประเภทห้อง + สิ่งอำนวยความสะดวก */
      $roomtype = [];
      $sql = "SELECT * FROM room_type";
      $result = $conn->query($sql);
      while ($row = $result->fetch_assoc()) {
        $roomtype[$row['rt_id']] = ['rt_name' => $row['rt_name'], 'features' => []];
      }
      $sql = "SELECT * FROM room_into";
      $result = $conn->query($sql);
      while ($row = $result->fetch_assoc()) {
        $rt_id = $row['rt_id'];
        if (isset($roomtype[$rt_id])) $roomtype[$rt_id]['features'][] = $row['rin_name'];
      }

      /* 4) ค้นหาห้องว่างตามช่วงวัน + ต้องพอสำหรับจำนวนห้องที่เลือก */
      $availableRoomTypes = [];
      if ($checkin && $checkout) {
        $sql = "
    SELECT rt.rt_id, rt.rt_name, COUNT(r.room_id) AS total_rooms
    FROM room r
    JOIN room_type rt ON r.rt_id = rt.rt_id
    WHERE r.room_id NOT IN (
      SELECT room_id FROM booking
      WHERE status = 'confirmed'
        AND NOT (checkout <= ? OR checkin >= ?)
    )
    GROUP BY rt.rt_id, rt.rt_name
    HAVING COUNT(r.room_id) >= ?  -- ต้องมีห้องว่างมากพอ
  ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $checkin, $checkout, $rooms);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
          $availableRoomTypes[$row['rt_id']] = $row;
        }

        /* ถ้าตาราง room_type มีคอลัมน์ความจุ เช่น max_adults, max_children
     ให้กรองเพิ่มได้ด้วย โดยแก้ WHERE/HAVING:
       WHERE rt.max_adults >= ? AND (rt.max_adults + rt.max_children) >= ?
     และ bind_param เพิ่ม $adults, ($adults+$children)
  */
      }

      /* 5) ส่ง limits + default ไปให้ JS/HTML */
      $limitsJson = json_encode($limits, JSON_UNESCAPED_UNICODE);
      ?>


      <!-- Call To Action Section -->
      <section id="call-to-action" class="call-to-action section dark-background">
        <div class="container">
          <img src="assets/img/มือถือธาราพานา.jpg" alt="">
          <div class="content row justify-content-center" data-aos="zoom-int" data-aos-delay="100"></div>
        </div>
      </section>


      <!-- ✅ ฟอร์มเลือกวันที่และค้นหาห้องว่าง -->
      <form class="date-form" id="dateForm" action="search.php" method="GET">
        <label>
          เลือกวันที่:
          <input type="text" id="dateRange" placeholder="เลือกวันที่" readonly />
          <input type="hidden" name="checkin" id="checkin" value="<?= htmlspecialchars($checkin) ?>">
          <input type="hidden" name="checkout" id="checkout" value="<?= htmlspecialchars($checkout) ?>">
        </label>

        <div class="stepper-group" aria-label="ตัวเลือกห้องและผู้เข้าพัก">
          <!-- ห้อง -->
          <div class="stepper" data-type="rooms">
            <div class="stepper-label">
              <span class="title">ห้อง</span>
              <small class="desc"><?= $limits['rooms']['min'] ?>–<?= $limits['rooms']['max'] ?> ห้อง</small>
            </div>
            <div class="stepper-controls">
              <button type="button" class="btn-step" data-target="rooms" data-action="dec" aria-label="ลดจำนวนห้อง">−</button>
              <output id="rooms_display" for="rooms" aria-live="polite"><?= $rooms ?></output>
              <button type="button" class="btn-step" data-target="rooms" data-action="inc" aria-label="เพิ่มจำนวนห้อง">+</button>
            </div>
            <input type="hidden" name="rooms" id="rooms" value="<?= $rooms ?>">
          </div>

          <!-- ผู้ใหญ่ -->
          <div class="stepper" data-type="adults">
            <div class="stepper-label">
              <span class="title">ผู้ใหญ่</span>
              <small class="desc"><?= $limits['adults']['min'] ?>–<?= $limits['adults']['max'] ?> คน</small>
            </div>
            <div class="stepper-controls">
              <button type="button" class="btn-step" data-target="adults" data-action="dec" aria-label="ลดจำนวนผู้ใหญ่">−</button>
              <output id="adults_display" for="adults" aria-live="polite"><?= $adults ?></output>
              <button type="button" class="btn-step" data-target="adults" data-action="inc" aria-label="เพิ่มจำนวนผู้ใหญ่">+</button>
            </div>
            <input type="hidden" name="adults" id="adults" value="<?= $adults ?>">
          </div>

          <!-- เด็ก -->
          <div class="stepper" data-type="children">
            <div class="stepper-label">
              <span class="title">เด็ก</span>
              <small class="desc"><?= $limits['children']['min'] ?>–<?= $limits['children']['max'] ?> คน</small>
            </div>
            <div class="stepper-controls">
              <button type="button" class="btn-step" data-target="children" data-action="dec" aria-label="ลดจำนวนเด็ก">−</button>
              <output id="children_display" for="children" aria-live="polite"><?= $children ?></output>
              <button type="button" class="btn-step" data-target="children" data-action="inc" aria-label="เพิ่มจำนวนเด็ก">+</button>
            </div>
            <input type="hidden" name="children" id="children" value="<?= $children ?>">
          </div>
        </div>

        <div class="guest-summary" id="guestSummary">
          <?= $adults ?> ผู้ใหญ่ · <?= $children ?> เด็ก · <?= $rooms ?> ห้อง
        </div>

        <input type="hidden" name="rt_id" id="rt_id">
        <button type="submit">ค้นหา</button>
      </form>

      <div class="container my-4">
        <h2 class="text-center mb-4">ประเภทห้องพักทั้งหมด</h2>

        <?php foreach ($roomtype as $rt_id => $room): ?>
          <div class="room-card mb-4">
            <img src="assets/img/<?= htmlspecialchars($room['rt_name']) ?>.jpg"
              alt="<?= htmlspecialchars($room['rt_name']) ?>"
              class="room-img">
            <div class="room-info">
              <h3><?= htmlspecialchars($room['rt_name']) ?></h3>
              <ul>
                <?php foreach ($room['features'] as $feature): ?>
                  <li><?= htmlspecialchars($feature) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Litepicker -->
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
      <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
      <script>
        document.addEventListener('DOMContentLoaded', () => {
          // helper แปลง Date -> YYYY-MM-DD
          const toYMD = d => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
          };

          const checkinEl = document.getElementById('checkin');
          const checkoutEl = document.getElementById('checkout');
          const dateRangeEl = document.getElementById('dateRange');

          // สร้าง Litepicker
          const picker = new Litepicker({
            element: dateRangeEl,
            singleMode: false,
            format: 'YYYY-MM-DD', // แค่รูปแบบที่ “แสดง” ในกล่อง
            minDate: new Date(),
            lang: 'th',
            autoApply: true,
            numberOfMonths: 2,
            // ⬇️ ใช้ Date ปกติ ไม่มี .format()
            onSelect: (start, end) => {
              if (start && end) {
                checkinEl.value = toYMD(start);
                checkoutEl.value = toYMD(end);
                // อัปเดตข้อความที่โชว์ในช่อง
                dateRangeEl.value = `${toYMD(start)} - ${toYMD(end)}`;
              }
            }
          });

          // ถ้ามีค่ามาจาก $_GET ให้ set ย้อนเข้า picker ด้วย (รีเฟรชแล้วยังเห็นช่วงเดิม)
          if (checkinEl.value && checkoutEl.value) {
            picker.setDateRange(new Date(checkinEl.value), new Date(checkoutEl.value));
            dateRangeEl.value = `${checkinEl.value} - ${checkoutEl.value}`;
          }

          // กันผู้ใช้เผลอกด submit โดยยังไม่เลือกวันที่ (hidden ใช้ required ไม่ได้)
          const form = document.getElementById('dateForm');
          form.addEventListener('submit', (e) => {
            if (!checkinEl.value || !checkoutEl.value) {
              e.preventDefault();
              alert('กรุณาเลือกวันที่เข้าพักและวันที่ออกก่อน');
              dateRangeEl.focus();
            }
          });

          // ---- stepper logic เดิมของคุณคงไว้ (ถ้ามี) ----
          const limits = <?= $limitsJson ?>;
          const clamp = (v, mi, ma) => Math.max(mi, Math.min(ma, v));

          function updateSummary() {
            const r = +document.getElementById('rooms').value;
            const a = +document.getElementById('adults').value;
            const c = +document.getElementById('children').value;
            document.getElementById('guestSummary').textContent = `${a} ผู้ใหญ่ · ${c} เด็ก · ${r} ห้อง`;
          }

          function setValue(key, newVal) {
            const {
              min,
              max
            } = limits[key];
            const val = clamp(parseInt(newVal || 0, 10), min, max);
            document.getElementById(key).value = val;
            document.getElementById(`${key}_display`).textContent = val;
            updateSummary();
            updateStepperStates();
          }

          function updateStepperStates() {
            ['rooms', 'adults', 'children'].forEach(key => {
              const val = +document.getElementById(key).value;
              const {
                min,
                max
              } = limits[key];
              document.querySelectorAll(`.btn-step[data-target="${key}"]`).forEach(btn => {
                const act = btn.dataset.action;
                const disabled = (act === 'dec' && val <= min) || (act === 'inc' && val >= max);
                btn.toggleAttribute('disabled', disabled);
                btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
              });
            });
          }
          document.querySelectorAll('.btn-step').forEach(btn => {
            btn.addEventListener('click', (e) => {
              e.preventDefault();
              const key = btn.dataset.target;
              const act = btn.dataset.action;
              const cur = +document.getElementById(key).value || 0;
              setValue(key, act === 'inc' ? cur + 1 : cur - 1);
            });
          });
          ['rooms', 'adults', 'children'].forEach(k => setValue(k, document.getElementById(k).value));
          updateSummary();
          updateStepperStates();

          // ปุ่ม “ดูห้องว่าง” รายประเภท
          window.submitWithRoom = function(rt_id) {
            if (!checkinEl.value || !checkoutEl.value) {
              alert("กรุณาเลือกวันที่เข้าพักและออกก่อน");
              return;
            }
            document.getElementById('rt_id').value = rt_id;
            form.submit();
          };
        });
        // ======= PATCH กันพลาดเวลา onSelect ไม่ทำงาน หรือเลือกวันเดียว =======
        (function() {
          const dateRangeEl = document.getElementById('dateRange');
          const checkinEl = document.getElementById('checkin');
          const checkoutEl = document.getElementById('checkout');
          const form = document.getElementById('dateForm');

          const parseRange = (str) => {
            // รองรับรูปแบบ "YYYY-MM-DD - YYYY-MM-DD"
            const m = String(str).match(/(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})/);
            return m ? {
              in: m[1],
              out: m[2]
            } : null;
          };

          // เผื่อผู้ใช้แก้ไขด้วยคีย์บอร์ด หรือปลั๊กอินเปลี่ยนค่าแล้วไม่ได้เรียก onSelect
          dateRangeEl.addEventListener('change', () => {
            const r = parseRange(dateRangeEl.value);
            if (r) {
              checkinEl.value = r.in;
              checkoutEl.value = r.out;
            }
          });

          // ก่อน submit: ถ้า hidden ยังว่าง ให้พยายามดึงจากช่องแสดงผล
          form.addEventListener('submit', (e) => {
            if (!checkinEl.value || !checkoutEl.value) {
              const r = parseRange(dateRangeEl.value);
              if (r) { // ตั้งให้เรียบร้อยแล้วค่อยส่ง
                checkinEl.value = r.in;
                checkoutEl.value = r.out;
              }
            }
            if (!checkinEl.value || !checkoutEl.value) {
              e.preventDefault();
              alert('กรุณาเลือกวันที่เข้าพักและวันที่ออกก่อน');
              dateRangeEl.focus();
            } else {
              // ดีบักดูค่าจริงที่กำลังจะส่ง (เปิด Console)
              console.log('submit with', {
                checkin: checkinEl.value,
                checkout: checkoutEl.value
              });
            }
          });
        })();
      </script>


      <style>
        /* ===== Booking-like Search Bar (เฉพาะสไตล์) ===== */
        :root {
          --primary: #0f8b79;
          --primary-contrast: #fff;
          --chip-bg: #f7f9fb;
          --chip-border: #e6ecf1;
          --chip-text: #0b1f2a;
          --muted: #6b7b88;
          --focus: #7dd3c7;
          --shadow: 0 6px 20px rgba(13, 60, 54, .12);
        }

        /* แถบฟอร์มหลัก */
        .date-form {
          display: flex;
          gap: .6rem;
          align-items: center;
          flex-wrap: wrap;
          background: #fff;
          border: 1px solid var(--chip-border);
          border-radius: 999px;
          padding: .5rem;
          box-shadow: var(--shadow);
          width: fit-content;
          margin: 32px auto;
        }

        /* ช่องเลือกวันที่เป็นชิป */
        .date-form>label {
          display: flex;
          align-items: center;
          gap: .55rem;
          background: var(--chip-bg);
          border: 1px solid var(--chip-border);
          padding: .65rem 1rem;
          border-radius: 999px;
          min-width: 240px;
          color: var(--chip-text);
          font-weight: 600;
        }

        .date-form input[type="text"] {
          border: none;
          outline: 0;
          background: transparent;
          font: 600 15px/1.2 system-ui;
          color: var(--chip-text);
          width: 160px;
        }

        .date-form input[type="text"]::placeholder {
          color: var(--muted);
        }

        /* กลุ่มสเต็ปเปอร์เป็นชิปเรียงแนวนอน */
        .stepper-group {
          display: flex;
          gap: .5rem;
          flex-wrap: wrap;
          align-items: center;
        }

        .stepper {
          display: flex;
          align-items: center;
          gap: 1rem;
          background: var(--chip-bg);
          border: 1px solid var(--chip-border);
          padding: .55rem .8rem;
          border-radius: 999px;
        }

        .stepper-label .title {
          font-weight: 700;
          font-size: .95rem;
          color: var(--chip-text)
        }

        .stepper-label .desc {
          display: block;
          font-size: .75rem;
          color: var(--muted);
          margin-top: 2px
        }

        /* ปุ่ม +/− กลมใหญ่ */
        .stepper-controls {
          display: flex;
          align-items: center;
          gap: .25rem;
          background: #fff;
          border: 1px solid var(--chip-border);
          border-radius: 999px;
          padding: .25rem;
        }

        .btn-step {
          width: 36px;
          height: 36px;
          border-radius: 999px;
          border: 0;
          cursor: pointer;
          background: var(--primary);
          color: var(--primary-contrast);
          font-size: 18px;
          display: grid;
          place-items: center;
          transition: .15s transform ease, .15s opacity ease;
        }

        .btn-step:hover {
          transform: translateY(-1px)
        }

        .btn-step[disabled] {
          opacity: .35;
          cursor: not-allowed;
          transform: none
        }

        .stepper output {
          min-width: 28px;
          text-align: center;
          font: 700 16px/1 system-ui;
          color: #0a1e28;
        }

        /* สรุปผู้เข้าพักแบบชิป */
        .guest-summary {
          margin-left: auto;
          margin-right: .25rem;
          font-weight: 700;
          color: #0a1e28;
          padding: .65rem 1rem;
          background: var(--chip-bg);
          border: 1px solid var(--chip-border);
          border-radius: 999px;
        }

        /* ปุ่มค้นหา */
        .date-form button[type="submit"] {
          border: 0;
          border-radius: 16px;
          padding: .9rem 1.4rem;
          font-weight: 800;
          font-size: 1rem;
          background: var(--primary);
          color: #fff;
          cursor: pointer;
          transition: .15s;
          box-shadow: 0 8px 20px rgba(15, 139, 121, .25);
        }

        .date-form button[type="submit"]:hover {
          transform: translateY(-1px)
        }

        .date-form button[type="submit"]:focus-visible {
          outline: 3px solid var(--focus);
          outline-offset: 2px;
        }

        /* --------- Room Card (คงโครงสร้างเดิม แต่ปรับให้คลีน) --------- */
        .room-list {
          display: flex;
          flex-direction: column;
          gap: 20px;
          margin: 20px 0;
        }

        .room-card {
          margin: 0 80px;
          display: flex;
          gap: 16px;
          align-items: center;
          background: #fff;
          border: 1px solid #e7eef3;
          border-radius: 14px;
          box-shadow: 0 4px 16px rgba(16, 24, 40, .06);
          padding: 12px;
        }

        .room-card img {
          width: 180px;
          height: 120px;
          object-fit: cover;
          border-radius: 10px;
        }

        .room-info {
          flex: 1;
        }

        .room-info h2 {
          font-size: 18px;
          margin: 0 0 6px 0;
        }

        .room-info ul {
          list-style: none;
          padding: 0;
          margin: 6px 0;
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
          font-size: 14px;
          color: #334155;
        }

        .room-info ul li::before {
          content: "• ";
          color: #94a3b8;
        }

        .room-actions {
          display: flex;
          align-items: center;
        }

        .room-actions button {
          background: var(--primary);
          color: #fff;
          border: 0;
          border-radius: 10px;
          padding: .6rem 1rem;
          font-weight: 700;
          cursor: pointer;
        }

        .room-actions button:hover {
          filter: brightness(.95);
        }

        /* --------- Responsive --------- */
        @media (max-width: 992px) {
          .date-form {
            border-radius: 20px;
            padding: .75rem;
          }
        }

        @media (max-width: 768px) {
          .date-form {
            width: 100%;
            justify-content: center;
            gap: .5rem;
          }

          .guest-summary {
            order: 5;
            width: 100%;
            text-align: center;
            margin: 0
          }

          .date-form>label {
            width: 100%;
          }

          .room-card {
            flex-direction: column;
            margin: 0 20px;
            align-items: flex-start;
          }

          .room-card img {
            width: 100%;
            height: auto;
          }

          .room-actions {
            width: 100%;
            justify-content: center;
            padding-top: 8px;
          }

          .date-form button[type="submit"] {
            width: 100%;
          }
        }
      </style>

    </section>















    <?php
    $topics = [];
    $sql = "SELECT tp_id, tp_name, tp_detail,
               IFNULL(tp_color,'') AS tp_color,
               IFNULL(tp_fontsize,0) AS tp_fontsize,
               IFNULL(tp_fontfamily,'') AS tp_fontfamily,
               IFNULL(tp_bold,0) AS tp_bold,
               IFNULL(tp_italic,0) AS tp_italic
        FROM topic";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
      $topics[(int)$row['tp_id']] = [
        'tp_name'       => $row['tp_name'],
        'tp_detail'     => $row['tp_detail'],
        'tp_color'      => $row['tp_color'],
        'tp_fontsize'   => (int)$row['tp_fontsize'],
        'tp_fontfamily' => $row['tp_fontfamily'],
        'tp_bold'       => (int)$row['tp_bold'],
        'tp_italic'     => (int)$row['tp_italic'],
      ];
    }
    function t_style(array $t): string
    {
      $s = '';
      if (!empty($t['tp_color']))      $s .= 'color:' . htmlspecialchars($t['tp_color'], ENT_QUOTES) . ';';
      if (!empty($t['tp_fontsize']))   $s .= 'font-size:' . ((int)$t['tp_fontsize']) . 'px;';
      if (!empty($t['tp_fontfamily'])) $s .= 'font-family:' . htmlspecialchars($t['tp_fontfamily'], ENT_QUOTES) . ';';
      if (!empty($t['tp_bold']))       $s .= 'font-weight:bold;';
      if (!empty($t['tp_italic']))     $s .= 'font-style:italic;';
      return $s;
    }
    ?>





    <!-- Call To Action Section -->
    <section id="call-to-action" class="call-to-action section dark-background">
      <div class="container">
        <img src="assets/img/มือถือธาราพานา.jpg" alt="">
        <div class="content row justify-content-center" data-aos="zoom-int" data-aos-delay="100"></div>
      </div>
    </section>




    <section id="about" class="about section">
      <!-- Section Title -->
      <div class="container text-center section-title" data-aos="fade-up"
        style="max-width: 800px; margin: 0 auto; padding: 40px 20px;">
        <h2 class="fw-bold mb-4" style="color: #2C6D6A;">
          <?= htmlspecialchars($topics[1]['tp_name'] ?? 'About') ?>
        </h2>

        <p class="about-description"
          style="font-size: 1.1rem; line-height: 1.8; color: #444; <?= t_style($topics[1] ?? []) ?>">
          <?= nl2br(htmlspecialchars($topics[1]['tp_detail'] ?? '')) ?>
        </p>
      </div>

      <div class="container">
        <div class="row align-items-start">
          <div class="row gy-4">

            <!-- Column 1: Why Choose Us -->
            <div class="col-lg-6 mb-4" data-aos="fade-up">
              <h2 class="fw-bold mb-3">
                <?= htmlspecialchars($topics[2]['tp_name'] ?? 'Why Choose Us') ?>
              </h2>
              <img src="assets/img/ห้องพักพร้อมริมธาน.jpg" class="img-fluid rounded-4 mb-3" alt="ห้องพัก">

              <div class="resort-description"
                style="line-height: 1.8; font-size: 1.05rem; color: #333; <?= t_style($topics[2] ?? []) ?>">
                <?= nl2br(htmlspecialchars($topics[2]['tp_detail'] ?? '')) ?>
              </div>
            </div>

            <!-- Column 2: จุดเด่นของที่พัก -->
            <?php
            // ---------- ตรวจการเชื่อมต่อ ----------
            if (!isset($conn) || !$conn) {
              http_response_code(500);
              die("DB connection not initialized. Please check connectdb.php");
            }

            function h($s)
            {
              return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
            }

            // ---------- ตั้งค่า section ----------
            $tpId = 3; // ส่วน/หน้า ที่ต้องการแสดง (จากรูปเป็น tp_id = 3)

            // ---------- ดึงรายการ highlight ----------
            $highlights = [];
            $sqlHl  = "SELECT hl_name, hl_detail FROM highlight WHERE tp_id = ? ORDER BY hl_id ASC";
            if ($stmtHl = $conn->prepare($sqlHl)) {
              $stmtHl->bind_param("i", $tpId);
              if ($stmtHl->execute()) {
                $res = $stmtHl->get_result();
                while ($row = $res->fetch_assoc()) {
                  $highlights[] = ['name' => $row['hl_name'] ?? '', 'detail' => $row['hl_detail'] ?? ''];
                }
              }
              $stmtHl->close();
            }

            // ---------- ดึงชื่อหัวข้อ (ถ้ามีตาราง topics) ----------
            $sectionTitle = 'หัวข้อ';
            if (isset($topics[3]['tp_name'])) {
              // ถ้ามีตัวแปร $topics อยู่แล้ว
              $sectionTitle = $topics[3]['tp_name'];
            } else {
              // ลองอ่านจากตาราง topics (ถ้ามี)
              if ($stmtTp = @$conn->prepare("SELECT tp_name FROM topics WHERE tp_id = ? LIMIT 1")) {
                $stmtTp->bind_param("i", $tpId);
                if ($stmtTp->execute()) {
                  $resTp = $stmtTp->get_result();
                  if ($rowTp = $resTp->fetch_assoc()) {
                    $sectionTitle = $rowTp['tp_name'] ?? $sectionTitle;
                  }
                }
                $stmtTp->close();
              }
            }
            ?>

            <!-- ---------- highlights ---------- -->
            <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-delay="100">
              <h2 class="fw-bold mb-3"><?= h($sectionTitle) ?></h2>

              <ul style="padding-left: 0; list-style: none;">
                <?php if (!empty($highlights)): ?>
                  <?php foreach ($highlights as $hl): ?>
                    <li class="mb-3" style="display: flex; align-items: center;">
                      <i class="bi bi-check-circle-fill"
                        style="color: #2C6D6A; margin-right: 8px; font-size: 1.2rem; line-height: 1;"></i>
                      <div>
                        <div style="font-weight: bold; color: #3d3b3b; line-height: 1.2;">
                          <?= h($hl['name']) ?></div>
                        <div style="color: #6c757d;"><?= nl2br(h($hl['detail'])) ?></div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                <?php else: ?>
                  <li class="text-muted">ยังไม่มีข้อมูล</li>
                <?php endif; ?>
              </ul>

              <div class="position-relative mt-4">
                <img src="assets/img/ธาราพานา.jpg" class="img-fluid rounded-4" alt="ภาพเกี่ยวกับเรา">
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>



    <?php
    // ===== ดึงข้อมูลจาก DB =====
    $topics = [];
    $res = mysqli_query($conn, "SELECT tp_id, tp_name, tp_detail FROM topic");
    if ($res) {
      while ($row = mysqli_fetch_assoc($res)) {
        $topics[(int)$row['tp_id']] = [
          'tp_name'   => $row['tp_name'],
          'tp_detail' => $row['tp_detail'],
        ];
      }
      mysqli_free_result($res);
    }

    $highlights = [];
    $res = mysqli_query($conn, "SELECT hl_name, hl_detail FROM highlight WHERE tp_id = 3");
    if ($res) {
      while ($row = mysqli_fetch_assoc($res)) {
        $highlights[] = [
          'name'   => $row['hl_name'],
          'detail' => $row['hl_detail'],
        ];
      }
      mysqli_free_result($res);
    }

    $travel = [];
    $res = mysqli_query($conn, "SELECT tv_name, distance, tv_map FROM travel WHERE tp_id = 4");
    if ($res) {
      while ($row = mysqli_fetch_assoc($res)) {
        $travel[] = [
          'tv_name'  => $row['tv_name'],
          'distance' => $row['distance'],
          'tv_map'   => $row['tv_map'],
        ];
      }
      mysqli_free_result($res);
    }
    ?>
    <!-- <<< สำคัญ: ปิด PHP ก่อนเริ่ม HTML -->

    <!-- Section Title -->
    <div class="container section-title" data-aos="fade-up">
      <h2><i class="bi bi-pin-map-fill"></i><?php echo htmlspecialchars($topics[4]['tp_name']); ?></h2>
      <?php echo nl2br(htmlspecialchars($topics[4]['tp_detail'])); ?>
    </div>

    <!-- Scrollable Horizontal List -->
    <div class="container" data-aos="fade-up" data-aos-delay="250"
      style="overflow-x: auto; white-space: nowrap; padding: 20px; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; border: 1px solid #ddd; border-radius: 16px; background-color: #fff; box-shadow: 0 2px 12px rgba(0,0,0,0.05);">

      <div style="display: inline-flex; gap: 20px; justify-content: center;">

        <!-- คอลัมน์ 1 -->
        <ul style="list-style: none; padding: 0; margin: 0; min-width: 220px; scroll-snap-align: start;">
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="<?php echo htmlspecialchars($travel[0]['tv_map']); ?>" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="<?php echo htmlspecialchars($travel[0]['tv_map']); ?>" target="_blank"
                style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[0]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[0]['distance']); ?></div>
            </div>
          </li>
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=Next story cafe & camp khaoyai"
              target="_blank" style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=Next story cafe & camp khaoyai"
                target="_blank" style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[1]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[1]['distance']); ?></div>
            </div>
          </li>
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=ศูนย์เรียนรู้ประวัติศาสตร์เขาใหญ่"
              target="_blank" style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=ศูนย์เรียนรู้ประวัติศาสตร์เขาใหญ่"
                target="_blank" style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[2]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[2]['distance']); ?></div>
            </div>
          </li>
        </ul>

        <!-- คอลัมน์ 2 -->
        <ul style="list-style: none; padding: 0; margin: 0; min-width: 220px; scroll-snap-align: start;">
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=Chirpy Campground เขาใหญ่" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=Chirpy Campground เขาใหญ่"
                target="_blank" style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[3]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[3]['distance']); ?></div>
            </div>
          </li>
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=เขาใหญ่ แคมป์ปิ้ง" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=เขาใหญ่ แคมป์ปิ้ง" target="_blank"
                style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[4]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[4]['distance']); ?></div>
            </div>
          </li>
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=Rimpha Music Festival" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=Rimpha Music Festival" target="_blank"
                style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[5]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[5]['distance']); ?></div>
            </div>
          </li>
        </ul>

        <!-- คอลัมน์ 3 -->
        <ul style="list-style: none; padding: 0; margin: 0; min-width: 220px; scroll-snap-align: start;">
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=Thai Massage Centre" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=Thai Massage Centre" target="_blank"
                style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[6]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[6]['distance']); ?></div>
            </div>
          </li>
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=BLUE SUN HILL CAFE' CAMP" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=BLUE SUN HILL CAFE' CAMP"
                target="_blank" style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[7]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[7]['distance']); ?></div>
            </div>
          </li>
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=K2 BaseCamp @Khaoyai" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=K2 BaseCamp @Khaoyai" target="_blank"
                style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[8]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[8]['distance']); ?></div>
            </div>
          </li>
        </ul>

        <!-- คอลัมน์ 4 -->
        <ul style="list-style: none; padding: 0; margin: 0; min-width: 220px; scroll-snap-align: start;">
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=Strawberry Picking in Khaoyai"
              target="_blank" style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=Strawberry Picking in Khaoyai"
                target="_blank" style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[9]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[9]['distance']); ?></div>
            </div>
          </li>
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=ซีนิคอลเวิลด์ เขาใหญ่" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=ซีนิคอลเวิลด์ เขาใหญ่" target="_blank"
                style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[10]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[10]['distance']); ?></div>
            </div>
          </li>
          <li style="display: flex; align-items: flex-start; margin-bottom: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=Khao Yai Convention Center" target="_blank"
              style="color: #2C6D6A; font-size: 1.5rem; text-decoration: none;">
              <i class="bi bi-geo-alt"></i>
            </a>
            <div style="margin-left: 8px;">
              <a href="https://www.google.com/maps/search/?api=1&query=Khao Yai Convention Center"
                target="_blank" style="font-weight: bold; color: #3d3b3b; text-decoration: none;">
                <?php echo htmlspecialchars($travel[11]['tv_name']); ?> <small
                  style="color: #2C6D6A;">(กดเพื่อดู)</small>
              </a>
              <div style="color: #6c757d;"><?php echo htmlspecialchars($travel[11]['distance']); ?></div>
            </div>
          </li>
        </ul>

      </div>
    </div>






    <!-- Call To Action Section -->
    <section id="call-to-action" class="call-to-action section dark-background">

      <div class="container">
        <img src="assets/img/มือถือธาราพานา.jpg" alt="">
        <div class="content row justify-content-center" data-aos="zoom-int" data-aos-delay="100">

        </div>
      </div>

    </section><!-- /Call To Action Section -->









    <?php
    // ===== helper =====
    if (!function_exists('h')) {
      function h($s)
      {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
      }
    }

    // ดึงหมวด (มี st_icon)
    $serveTypes = [];
    $res = mysqli_query($conn, "SELECT st_id, st_name, st_icon FROM serve_type ORDER BY st_id ASC");
    while ($r = mysqli_fetch_assoc($res)) $serveTypes[] = $r;
    mysqli_free_result($res);

    // ดึงรายการแล้วจัดกลุ่มตามหมวด
    $serveByType = [];
    $res = mysqli_query($conn, "SELECT s_id, s_name, st_id FROM serve ORDER BY st_id ASC, s_id ASC");
    while ($r = mysqli_fetch_assoc($res)) {
      $tid = (int)$r['st_id'];
      if (!isset($serveByType[$tid])) $serveByType[$tid] = [];
      $serveByType[$tid][] = $r;
    }
    mysqli_free_result($res);

    /**
     * ไอคอนสำรองตามชื่อหมวด (fallback ถ้า st_icon ว่าง)
     * ใช้คลาส Bootstrap Icons เช่น 'bi bi-ui-checks'
     */
    $iconMap = [
      'ทั่วไป'                      => 'bi bi-ui-checks',
      'ความปลอดภัย'                 => 'bi bi-shield-fill-check',
      'พื้นที่ส่วนกลาง'             => 'bi bi-house-heart-fill',
      'ครัว'                        => 'bi bi-egg-fried',
      'อุปกรณ์สื่อและเทคโนโลยี'    => 'bi bi-tv',
      'ที่จอดรถ'                    => 'bi bi-p-square-fill',
      'สระว่ายน้ำกลางแจ้ง'          => 'bi bi-door-closed',
      'กิจกรรมสำหรับครอบครัวและเด็ก' => 'bi bi-bicycle',
      'บริการ'                      => 'bi bi-people-fill',
    ];

    // หาไอคอนจากชื่อ (ใช้ตอน fallback)
    function mapIconByName(string $name, array $iconMap)
    {
      foreach ($iconMap as $k => $cls) {
        if (mb_strpos($name, $k) !== false) return $cls;
      }
      return 'bi bi-check-circle-fill'; // default
    }

    // ตัดสินใจไอคอนต่อแถว: ใช้ st_icon ก่อน, ว่างค่อย fallback, ใส่ '-' หรือ 'none' เพื่อซ่อนไอคอนได้
    function resolveIcon(array $row, array $iconMap)
    {
      $stIcon = trim((string)($row['st_icon'] ?? ''));
      if ($stIcon !== '') {
        if ($stIcon === '-' || strcasecmp($stIcon, 'none') === 0) return false; // ไม่แสดง
        return $stIcon; // ใช้คลาสจาก DB โดยตรง
      }
      return mapIconByName((string)$row['st_name'], $iconMap);
    }
    ?>

    <section id="services" class="services section">
      <div class="container">

        <div class="container section-title" data-aos="fade-up">
          <h2>สิ่งอำนวยความสะดวก</h2>
          <p>ใส่ใจทุกรายละเอียด เพื่อความสะดวกสบายในทุกช่วงเวลาของการพักผ่อน</p>
        </div>

        <div class="row gy-4">
          <?php
          // ===== แสดง 3 หมวดแรก =====
          $first = array_slice($serveTypes, 0, 3);
          foreach ($first as $i => $t):
            $tid   = (int)$t['st_id'];
            $items = $serveByType[$tid] ?? [];
            $icon  = resolveIcon($t, $iconMap); // ← เปลี่ยนมาใช้ st_icon ก่อน
          ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= 100 + ($i * 100) ?>">
              <div class="service-item position-relative">
                <?php if ($icon !== false): ?>
                  <div class="icon"><i class="<?= h($icon) ?>"></i></div>
                <?php endif; ?>
                <h3><?= h($t['st_name']) ?></h3>
                <?php if (empty($items)): ?>
                  <div class="text-muted" style="margin-bottom:10px">—</div>
                  <?php else: foreach ($items as $s): ?>
                    <div style="color:#6c757d;margin-bottom:10px;">
                      <i class="bi bi-check-lg" style="margin-right:6px;color:#2C6D6A;"></i><?= h($s['s_name']) ?>
                    </div>
                <?php endforeach;
                endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div><!-- /row -->

        <?php if (count($serveTypes) > 3): ?>
          <div id="moreServices" class="collapse-section" style="overflow:hidden;max-height:0;transition:max-height .5s ease;">
            <div class="row gy-4" style="margin-top:8px;">
              <?php
              $rest = array_slice($serveTypes, 3);
              foreach ($rest as $idx => $t):
                $tid   = (int)$t['st_id'];
                $items = $serveByType[$tid] ?? [];
                $icon  = resolveIcon($t, $iconMap); // ← ตรงนี้ด้วย
              ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= 300 + (($idx % 3) * 100) ?>">
                  <div class="service-item position-relative">
                    <?php if ($icon !== false): ?>
                      <div class="icon"><i class="<?= h($icon) ?>"></i></div>
                    <?php endif; ?>
                    <h3><?= h($t['st_name']) ?></h3>
                    <?php if (empty($items)): ?>
                      <div class="text-muted" style="margin-bottom:10px">—</div>
                      <?php else: foreach ($items as $s): ?>
                        <div style="color:#6c757d;margin-bottom:10px;">
                          <i class="bi bi-check-lg" style="margin-right:6px;color:#2C6D6A;"></i><?= h($s['s_name']) ?>
                        </div>
                    <?php endforeach;
                    endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div><!-- /row -->
          </div><!-- /#moreServices -->
        <?php endif; ?>

        <?php if (count($serveTypes) > 3): ?>
          <div class="col-12 text-center mt-4">
            <button id="toggleServicesBtn" class="btn btn-outline-green">ดูเพิ่มเติม</button>
          </div>
        <?php endif; ?>

        <style>
          .btn-outline-green {
            color: #008374;
            border: 1px solid #008374;
            background-color: transparent;
            transition: all .3s ease
          }

          .btn-outline-green:hover {
            background-color: #008374;
            color: #fff
          }
        </style>

        <script>
          (function() {
            const more = document.getElementById('moreServices');
            const btn = document.getElementById('toggleServicesBtn');
            if (!more || !btn) return;

            function hide() {
              more.style.maxHeight = '0';
              btn.textContent = 'ดูเพิ่มเติม';
            }

            function show() {
              more.style.maxHeight = more.scrollHeight + 'px';
              btn.textContent = 'ดูน้อย';
            }
            btn.addEventListener('click', () => (more.style.maxHeight && more.style.maxHeight !== '0px') ? hide() : show());
            window.addEventListener('load', hide);
          })();
        </script>

      </div>
    </section>









    <?php
    // ==== ปรับ path ให้ถูกกับโปรเจ็กต์คุณ ====
    require_once __DIR__ . '/connectdb.php';

    // ดึง header แรก (ถ้ามี) เพื่อทำหัวเรื่อง/คำโปรย
    $header = [
      'title' => 'ข้อกำหนดของที่พัก',
      'subtitle' => 'โปรดอ่านเงื่อนไขก่อนทำการจอง เพื่อประสบการณ์ที่ราบรื่น'
    ];
    $hdrRes = mysqli_query($conn, "SELECT heading_th, content_th
                               FROM policies
                               WHERE section='header'
                               ORDER BY sort_order, id_poli
                               LIMIT 1");
    if ($hdrRes && $row = mysqli_fetch_assoc($hdrRes)) {
      $header['title']    = htmlspecialchars($row['heading_th'] ?? '', ENT_QUOTES, 'UTF-8');
      $header['subtitle'] = htmlspecialchars($row['content_th'] ?? '', ENT_QUOTES, 'UTF-8');
    }
    mysqli_free_result($hdrRes);

    // ดึงรายการทั้งหมด (ยกเว้น header) เรียงตาม section, sort_order
    $sql = "SELECT id_poli, sort_order, section, icon_class, heading_th, item_type, content_th
        FROM policies
        WHERE section <> 'header' OR section IS NULL
        ORDER BY COALESCE(section,''), sort_order, id_poli";
    $res = mysqli_query($conn, $sql);

    // ตัวช่วยเรนเดอร์เนื้อหา
    function pol_render_content($type, $content)
    {
      $type = strtolower(trim($type ?? 'text'));
      if ($type === 'html' || $type === 'table') {
        // เชื่อใจ HTML (ควรกรอกจากหลังบ้านเท่านั้น)
        return (string)$content;
      }

      $safe = htmlspecialchars($content ?? '', ENT_QUOTES, 'UTF-8');

      switch ($type) {
        case 'list':
          $lines = preg_split("/\r\n|\n|\r/", $safe);
          $lis = '';
          foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln !== '') $lis .= "<li>{$ln}</li>";
          }
          return "<ul class=\"pol__list\">{$lis}</ul>";

        case 'text':
        default:
          return "<p class=\"pol__text\">" . nl2br($safe) . "</p>";
      }
    }
    ?>
    <!-- (ถ้ายังไม่มีไอคอน) ใส่ครั้งเดียวใน <head> ของหน้า -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <section id="policies" class="pol">
      <div class="pol__container">
        <div class="pol__card">
          <header class="pol__header">
            <h2 class="pol__title"><?= $header['title'] ?></h2>
            <p class="pol__subtitle"><?= $header['subtitle'] ?></p>
          </header>

          <div class="pol__grid">
            <?php while ($row = mysqli_fetch_assoc($res)): ?>
              <?php
              $icon   = htmlspecialchars($row['icon_class'] ?? '', ENT_QUOTES, 'UTF-8');
              $title  = htmlspecialchars($row['heading_th'] ?? '', ENT_QUOTES, 'UTF-8');
              $type   = $row['item_type'] ?? 'text';
              $cont   = $row['content_th'] ?? '';
              $isFull = (strtolower(trim($row['section'] ?? '')) === 'full');
              ?>
              <article class="pol__item<?= $isFull ? ' pol__item--full' : '' ?>">
                <div class="pol__item-head">
                  <?php if ($icon !== ''): ?><i class="<?= $icon ?>"></i><?php endif; ?>
                  <h3><?= $title ?></h3>
                </div>
                <?= pol_render_content($type, $cont) ?>
              </article>
            <?php endwhile;
            mysqli_free_result($res); ?>
          </div>
        </div>
      </div>
    </section>

    <style>
      /* ===== Policies (Sleek & Minimal) ===== */
      .pol {
        --pol-primary: #008374;
        --pol-primary-700: #006b5f;
        --pol-title: #2c3e50;
        /* สีฟ้อนของหัวข้อหลัก */
        --pol-text: #34495e;
        /* สีฟ้อนทั่วไป */
        --pol-muted: #6a7d79;
        --pol-line: #e8eeec;
        --pol-surface: #ffffff;
        --pol-bg: #ffffff;
        --pol-radius: 16px;
      }

      .pol {
        background: var(--pol-bg);
        padding: 56px 0
      }

      .pol__container {
        max-width: 1100px;
        margin: auto;
        padding: 0 16px
      }

      .pol__card {
        background: var(--pol-surface);
        border: 1px solid var(--pol-line);
        border-radius: var(--pol-radius);
        padding: 28px 24px;
        box-shadow: 0 8px 28px rgba(16, 24, 20, .06);
      }

      .pol__header {
        text-align: center;
        margin-bottom: 8px
      }

      .pol__title {
        margin: 0;
        font-weight: 800;
        font-size: clamp(22px, 2.6vw, 32px);
        color: var(--pol-title);
        letter-spacing: .2px;
      }

      .pol__subtitle {
        margin: 6px 0 0;
        color: var(--pol-text);
        font-size: 14px
      }

      .pol__grid {
        display: grid;
        gap: 16px;
        margin-top: 18px;
        grid-template-columns: 1fr;
      }

      @media (min-width: 900px) {
        .pol__grid {
          grid-template-columns: 1fr 1fr
        }

        .pol__item--full {
          grid-column: 1 / -1
        }
      }

      .pol__item {
        border: 1px solid var(--pol-line);
        border-radius: 12px;
        padding: 16px 14px;
        background: #fff;
      }

      .pol__item-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px
      }

      .pol__item-head i {
        color: var(--pol-primary);
        background: #eef7f4;
        border: 1px solid #d9efe9;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        font-size: 1.5rem;
      }

      .pol__item-head h3 {
        margin: 0;
        font-size: 16px;
        color: var(--pol-text);
        font-weight: 700
      }

      .pol__text {
        margin: 0;
        color: var(--pol-text);
        font-size: 16px;
        line-height: 1.55
      }

      .pol__sep {
        border: none;
        border-top: 1px dashed var(--pol-line);
        margin: 10px 0
      }

      .pol__list {
        margin: 0;
        padding-left: 18px;
        display: grid;
        gap: 6px;
        color: var(--pol-text);
        font-size: 16px;
        line-height: 1.55
      }

      .pol__chip {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        background: #ecf7f3;
        border: 1px solid #d9efe9;
        color: var(--pol-text);
        font-weight: 600;
        font-size: 14px;
      }

      .pol__tablewrap {
        overflow: auto;
        border: 1px solid var(--pol-line);
        border-radius: 10px;
        background: #fafdfc;
        margin-top: 2px
      }

      .pol__table {
        width: 100%;
        border-collapse: collapse;
        font-size: 16px;
        color: var(--pol-text)
      }

      .pol__table th,
      .pol__table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--pol-line)
      }

      .pol__table thead th {
        text-align: left;
        background: #f3fbf9;
        color: var(--pol-text)
      }

      .pol__table tbody tr:last-child td {
        border-bottom: none
      }

      .pol__badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        background: #eef7f4;
        border: 1px solid #d9efe9;
        color: var(--pol-text);
        font-size: 14px;
      }

      .pol__note {
        margin: 8px 0 0;
        color: var(--pol-text);
        font-size: 13px
      }

      .pol__link {
        color: var(--pol-primary);
        text-decoration: none;
        font-weight: 700
      }

      .pol__link:hover {
        text-decoration: underline
      }

      @media (prefers-color-scheme: dark) {
        .pol {
          --pol-text: #e7f1ee;
          --pol-muted: #a7bab6;
          --pol-line: #22312d;
          --pol-surface: #111b18;
          --pol-bg: #0e1513;
          --pol-title: #cfd6d4;
        }

        .pol__card,
        .pol__item {
          background: var(--pol-surface)
        }

        .pol__tablewrap {
          background: #0f1a18
        }

        .pol__table thead th {
          background: #112421;
          color: #c9f1e9
        }
      }
    </style>










    <!-- (ถ้ายังไม่มีไอคอน) ใส่ครั้งเดียวใน <head> -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> -->

    <style>
      /* ===== Contact (match FAQ theme) ===== */
      :root {
        --ct-primary: #008374;
        --ct-primary-700: #006650;
        --ct-bg: #ffffff;
        --ct-surface: #fff;
        --ct-line: #bfe3dc;
        --ct-muted: #6a7d79;
        --ct-text: #2f3d3a;
        --ct-radius: 12px;
        --ct-shadow: 0 8px 20px rgba(0, 131, 116, .15);
      }

      .contact-wrap {
        max-width: 600px;
        margin: 0 auto;
        background: var(--ct-surface);
        border-radius: 10px;
        box-shadow: var(--ct-shadow);
        padding: 30px 22px;
        border: 1.5px solid var(--ct-primary);
      }

      .contact-head {
        text-align: center;
        margin-bottom: 22px;
      }

      .contact-head h2 {
        color: var(--ct-primary);
        margin: 0 0 6px 0;
        font-weight: 700;
        letter-spacing: 1px;
        font-size: 2.2rem;
        text-transform: uppercase;
      }

      .contact-head p {
        margin: 0;
        color: var(--ct-muted);
      }

      .contact-grid {
        display: grid;
        gap: 14px;
      }

      .ct-item {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 14px 14px;
        border: 1.5px solid var(--ct-primary);
        border-radius: 8px;
        background: #e0f2ef;
        box-shadow: 0 2px 6px rgba(0, 131, 116, .10);
        transition: box-shadow .25s ease, transform .06s ease;
      }

      .ct-item:hover {
        box-shadow: 0 5px 15px rgba(0, 131, 116, .25);
        transform: translateY(-1px);
      }

      .ct-ic {
        flex: 0 0 auto;
        width: 42px;
        height: 42px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: #fff;
        border: 1.5px solid var(--ct-line);
        color: var(--ct-primary);
        font-size: 1.25rem;
      }

      .ct-body h3 {
        margin: 0 0 2px 0;
        font-size: 1.1rem;
        color: var(--ct-primary-700);
        font-weight: 700;
      }

      .ct-body p,
      .ct-body a {
        margin: 0;
        font-size: 1rem;
        line-height: 1.6;
        color: #004d40;
        text-decoration: none;
      }

      .ct-body a:hover {
        text-decoration: underline;
      }

      /* ปุ่มแอคชันเล็ก (เช่น เปิดแผนที่) */
      .ct-actions {
        margin-top: 6px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
      }

      .ct-btn {
        appearance: none;
        border: 1px solid var(--ct-primary);
        background: #fff;
        color: var(--ct-primary-700);
        padding: 6px 10px;
        border-radius: 8px;
        font-size: .9rem;
        cursor: pointer;
      }

      .ct-btn:hover {
        background: #f0fbf8;
      }

      @media (max-width: 480px) {
        .ct-item {
          padding: 12px
        }

        .ct-ic {
          width: 38px;
          height: 38px;
          font-size: 1.1rem
        }
      }
    </style>




    <!-- Style เพิ่มเติมสำหรับ Contact -->
    <style>
      .text-teal {
        color: #008374 !important;
      }

      .border-teal {
        border-color: #008374 !important;
      }

      .fade-in {
        animation: fadeInUp 0.8s ease-in-out;
      }

      @keyframes fadeInUp {
        from {
          opacity: 0;
          transform: translateY(20px);
        }

        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .avatar-image {
        border-radius: 50%;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      }

      .social-links a {
        color: #008374;
        font-size: 1.5rem;
        margin-right: 1rem;
        transition: color .3s;
      }

      .social-links a:hover {
        color: #005742;
      }
    </style>







  </main>

  

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i
      class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader -->
  <div id="preloader"></div>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
  <script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
  <script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>