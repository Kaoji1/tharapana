<?php 
include_once("connectdb.php");
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ReandLog.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>📅 ปฏิทินห้องพัก | Tharapana Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background: #f5f7f8;
      font-family: 'Sarabun', sans-serif;
      padding: 20px;
    }

    h2 {
      color: #00796b;
      text-align: center;
      margin-bottom: 15px;
      font-weight: 700;
    }

    #calendar {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
    }

    .status-pending { background: #29b6f6 !important; }
    .status-confirmed { background: #e979f8ff !important; }
    .status-checked_in { background: #66bb6a !important; }
    .status-checked_out { background: #bdbdbd !important; }
    .status-cancelled { background: #ef5350 !important; }

    .legend-box {
      width: 16px;
      height: 16px;
      border-radius: 4px;
      display: inline-block;
      margin-right: 6px;
    }
    .legend-item {
      display: inline-flex;
      align-items: center;
      margin-right: 15px;
      font-size: 14px;
    }
    .legend-container {
      background: #fff;
      border-radius: 10px;
      padding: 10px 15px;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
      margin-bottom: 15px;
    }
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="bi bi-calendar-heart-fill"></i> ปฏิทินการจองห้องพัก (Admin)</h2>
      <div>
        <a href="admin_confirm_booking.php" class="btn btn-primary me-2">
          <i class="bi bi-clipboard-check"></i> หน้ารอยืนยัน
        </a>
        <button class="btn btn-success" onclick="calendar.refetchEvents()">
          <i class="bi bi-arrow-repeat"></i> โหลดใหม่
        </button>
      </div>
    </div>

    <div class="legend-container mb-3">
      <div class="legend-item"><span class="legend-box" style="background:#29b6f6;"></span> รอการยืนยัน</div>
      <div class="legend-item"><span class="legend-box" style="background:#e979f8ff;"></span> ยืนยันแล้ว</div>
      <div class="legend-item"><span class="legend-box" style="background:#66bb6a;"></span> กำลังเข้าพัก</div>
      <div class="legend-item"><span class="legend-box" style="background:#bdbdbd;"></span> เช็คเอาท์แล้ว</div>
      <div class="legend-item"><span class="legend-box" style="background:#ef5350;"></span> ยกเลิก</div>
    </div>

    <div id="calendar"></div>
  </div>

  <!-- 🏨 Modal -->
  <div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:12px;">
        <div id="modalHeader" class="modal-header text-white bg-primary" style="border-radius:12px 12px 0 0;">
          <h5 class="modal-title"><i class="bi bi-info-circle-fill me-2"></i>รายละเอียดการจอง</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2"><strong>ห้องพัก:</strong> <span id="roomName"></span></div>
          <div class="mb-2"><strong>วันที่:</strong> <span id="dateRange"></span></div>
          <div class="mb-2"><strong>ผู้จอง:</strong> <span id="guest"></span></div>
          <div class="mb-3"><strong>สถานะ:</strong> <span id="statusText" class="badge fs-6"></span></div>
          <hr>
          <div class="mb-3">
            <label for="roomSelect" class="form-label fw-bold">เลขห้องที่ลูกค้าเข้าพัก:</label>
            <select id="roomSelect" class="form-select">
              <option value="">-- เลือกเลขห้อง --</option>
            </select>
          </div>
          <div>
            <label for="newStatus" class="form-label fw-bold">เปลี่ยนสถานะ:</label>
            <select id="newStatus" class="form-select">
              <option value="pending">รอการเข้าพัก</option>
              <option value="confirmed">ยืนยันแล้ว</option>
              <option value="checked_in">กำลังเข้าพัก</option>
              <option value="checked_out">เช็คเอาท์แล้ว</option>
              <option value="cancelled">ยกเลิก</option>
            </select>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
          <button class="btn btn-primary" id="saveStatus">บันทึก</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const calendarEl = document.getElementById('calendar');
      const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
      const modalHeader = document.getElementById('modalHeader');
      let currentBookingId = null;

      window.calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'th',
        height: 'auto',
        eventSources: [{ url: 'admin_booking_events.php', method: 'GET' }],
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },

        eventClassNames: (arg) => ['status-' + (arg.event.extendedProps.status || 'pending')],

        eventClick: (info) => {
          const p = info.event.extendedProps;
          const color = statusColor(p.status);
          modalHeader.className = `modal-header text-white bg-${color}`;
          document.getElementById('roomName').textContent = info.event.title;
          document.getElementById('dateRange').textContent = `${p.checkIn} → ${p.checkOut}`;
          document.getElementById('guest').textContent = p.guest;
          const badge = document.getElementById('statusText');
          badge.textContent = getStatusText(p.status);
          badge.className = `badge bg-${color} fs-6`;
          document.getElementById('newStatus').value = p.status;
          currentBookingId = info.event.id;

          // ✅ ใช้ room_id แทน room_number
          loadRooms(p.rt_id, p.room_id);

          document.getElementById('saveStatus').onclick = saveUpdate;
          bookingModal.show();
        },

        dateClick: function(info) {
          const date = info.dateStr;
          fetch(`admin_available_rooms.php?date=${date}`)
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                let html = `<h5>📅 ห้องว่างวันที่ ${date}</h5>`;
                html += `<p><strong>จำนวนห้องว่างทั้งหมด:</strong> ${data.left} ห้อง</p>`;
                if (data.left > 0) {
                  html += "<ul class='list-group'>";
                  for (const [type, rooms] of Object.entries(data.rooms_by_type)) {
                    html += `<li class='list-group-item'>
                               <strong>${type}</strong> 
                               <span class='text-muted'>(${rooms.length} ห้อง)</span><br>`;
                    rooms.forEach(r => {
                      if (r.available) {
                        html += `<span style="color:green;">🟢 ห้อง ${r.number}</span><br>`;
                      } else {
                        html += `<span style="color:red;cursor:pointer;" 
                                 onclick="alert('ห้อง ${r.number} มีผู้เข้าพักชื่อ: ${r.guest}\\nเข้าพักวันที่: ${r.checkin} → ${r.checkout}')">
                                 🔴 ห้อง ${r.number} (ไม่ว่าง)
                                 </span><br>`;
                      }
                    });
                    html += `</li>`;
                  }
                } else {
                  html += `<div class="text-danger mt-2">ไม่มีห้องว่างในวันนี้</div>`;
                }

                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.innerHTML = `
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="bi bi-door-open-fill"></i> ห้องว่างในวันเลือก</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">${html}</div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                      </div>
                    </div>
                  </div>`;
                document.body.appendChild(modal);
                const tempModal = new bootstrap.Modal(modal);
                tempModal.show();
                modal.addEventListener('hidden.bs.modal', () => modal.remove());
              } else alert('❌ ' + data.error);
            })
            .catch(err => alert('เกิดข้อผิดพลาด: ' + err));
        }
      });
      calendar.render();

      // ✅ โหลดเลขห้องโดยใช้ room_id
      function loadRooms(rt_id, selected) {
        fetch('get_rooms_by_type.php?rt_id=' + rt_id)
          .then(r => r.json()).then(data => {
            const sel = document.getElementById('roomSelect');
            sel.innerHTML = '<option value="">-- เลือกเลขห้อง --</option>';
            data.forEach(r => {
              const opt = document.createElement('option');
              opt.value = r.room_id;
              opt.textContent = r.room_number;
              if (selected && selected == r.room_id) opt.selected = true;
              sel.appendChild(opt);
            });
          });
      }

      function saveUpdate() {
        const newStatus = document.getElementById('newStatus').value;
        const roomId = document.getElementById('roomSelect').value;
        fetch('admin_update_status.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `b_id=${currentBookingId}&status=${newStatus}&room_id=${roomId}`
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            bookingModal.hide();
            calendar.refetchEvents();
          } else alert('❌ ' + data.error);
        });
      }

      function statusColor(s) {
        return {
          pending: 'warning',
          confirmed: 'info',
          checked_in: 'success',
          checked_out: 'secondary',
          cancelled: 'danger'
        }[s] || 'light';
      }

      function getStatusText(s) {
        return {
          pending: 'รอการยืนยัน',
          confirmed: 'ยืนยันแล้ว',
          checked_in: 'กำลังเข้าพัก',
          checked_out: 'เช็คเอาท์แล้ว',
          cancelled: 'ยกเลิก'
        }[s] || 'ไม่ทราบสถานะ';
      }
    });
  </script>
</body>
</html>
