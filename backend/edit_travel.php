<?php
// --- edit_travel.php ---
include_once("connectdb.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// --- 1. CONNECT DB ---
$host="localhost"; $usr="root"; $pwd=""; $dbName="tharapana";
$conn = mysqli_connect($host,$usr,$pwd,$dbName);
if (!$conn) { http_response_code(500); die('DB connect failed'); }
mysqli_set_charset($conn, 'utf8mb4');

// --- 2. FETCH DATA FOR DISPLAY ---
$travel_items = [];
// เรียงตาม ID หรือ ชื่อ ก็ได้ แล้วแต่ชอบ
$result_travel = $conn->query("SELECT tv_id, tv_name, distance, tv_map, tp_id FROM travel ORDER BY tv_id ASC");
if($result_travel) {
    while ($row = $result_travel->fetch_assoc()) {
        $travel_items[] = $row;
    }
    $result_travel->free(); // คืน memory
} else {
    // ควรมี error handling ถ้า query ไม่ได้
    echo "Error fetching data: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสถานที่ท่องเที่ยว</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&libraries=places&callback=initMap&v=weekly" async defer></script>
    <style>
        /* (CSS เหมือนเดิม) */
        .table-actions { white-space: nowrap; width: 1%; }
        .alert-container { position: fixed; top: 1rem; right: 1rem; z-index: 1056; }
        .form-control-sm-search { padding-top: 0.4rem; padding-bottom: 0.4rem; }
        .text-truncate-link { max-width: 250px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
        /* CSS เพิ่มเติมเล็กน้อยสำหรับช่องค้นหา */
        #place_search { background-color: #f8f9fa; } /* สีพื้นหลังช่องค้นหา */
    </style>
</head>
<body>

    <div class="container-fluid px-lg-4">

        <div id="alert-container" class="alert-container"></div>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
                <h5 class="card-title mb-0"><i class="bi bi-geo-alt-fill me-2 text-danger"></i>จัดการสถานที่ท่องเที่ยว</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" id="q_travel" class="form-control form-control-sm-search border-start-0" placeholder="ค้นหาสถานที่...">
                    </div>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#editTravelModal" id="openAddTravel">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มสถานที่
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ชื่อสถานที่</th>
                                <th style="width: 15%;">ระยะทาง</th>
                                <th>ลิงก์แผนที่</th>
                                <th class="text-center" style="width: 15%;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_travel">
                            <?php if (empty($travel_items)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-info-circle me-2"></i>ยังไม่มีข้อมูล</td></tr>
                            <?php else: ?>
                                <?php foreach ($travel_items as $item): ?>
                                <tr class="row-item-travel" data-title="<?= strtolower(htmlspecialchars($item['tv_name'])) ?>">
                                    <td><?= htmlspecialchars($item['tv_name']) ?></td>
                                    <td><?= htmlspecialchars($item['distance']) ?></td>
                                    <td>
                                        <?php if (!empty($item['tv_map']) && filter_var($item['tv_map'], FILTER_VALIDATE_URL)): ?>
                                            <a href="<?= htmlspecialchars($item['tv_map']) ?>" target="_blank" rel="noopener noreferrer" class="text-truncate-link">
                                                <?= htmlspecialchars($item['tv_map']) ?> <i class="bi bi-box-arrow-up-right small"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted text-truncate-link"><?= htmlspecialchars($item['tv_map'] ?: '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-actions text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary btn-edit-travel"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editTravelModal"
                                                data-tv-id="<?= (int)$item['tv_id'] ?>"
                                                data-tv-name="<?= htmlspecialchars($item['tv_name'], ENT_QUOTES) ?>"
                                                data-distance="<?= htmlspecialchars($item['distance'], ENT_QUOTES) ?>"
                                                data-tv-map="<?= htmlspecialchars($item['tv_map'], ENT_QUOTES) ?>"
                                                data-tp-id="<?= (int)$item['tp_id'] ?>">
                                                <i class="bi bi-pencil-square"></i> <span class="d-none d-md-inline">แก้ไข</span>
                                            </button>
                                            <button class="btn btn-outline-danger btn-delete-travel"
                                                data-tv-id="<?= (int)$item['tv_id'] ?>"
                                                data-tv-name="<?= htmlspecialchars($item['tv_name'], ENT_QUOTES) ?>">
                                                <i class="bi bi-trash3-fill"></i> <span class="d-none d-md-inline">ลบ</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div> <div class="modal fade" id="editTravelModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-3">
                <form method="POST" id="travelForm">
                    <input type="hidden" name="action" id="travel_action" value="add_travel">
                    <input type="hidden" name="tv_id" id="edit_tv_id" value="0">

                    <div class="modal-header bg-danger text-white"> <h5 class="modal-title" id="travelModalLabel"><i class="bi bi-geo-alt-fill me-2"></i>เพิ่มสถานที่ท่องเที่ยว</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="tv_name" class="form-label">ชื่อสถานที่ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tv_name" name="tv_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="distance" class="form-label">ระยะทาง <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="distance" name="distance" placeholder="เช่น 180 เมตร, 1.2 กม." required>
                        </div>
                        <div class="mb-3">
                            <label for="tv_map" class="form-label">ลิงก์แผนที่ (Google Maps) <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="tv_map" name="tv_map" placeholder="https://www.google.com/maps/search/?api=1&query=..." required>
                        </div>
                         <div class="mb-3">
                            <label for="tp_id" class="form-label">Type ID</label>
                            <input type="number" class="form-control" id="tp_id" name="tp_id" value="4" required>
                             <div class="form-text">ID ประเภทของสถานที่ (ค่าปกติคือ 4)</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-danger" id="travelSubmitButton">
                            <i class="bi bi-save-fill me-1"></i> บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // --- ส่วนที่ 1: จัดการ Modal Add/Edit ---
        const travelModal = document.getElementById('editTravelModal');
        const travelForm = document.getElementById('travelForm');
        const travelModalLabel = document.getElementById('travelModalLabel');
        const travelSubmitButton = document.getElementById('travelSubmitButton');
        const actionInput = document.getElementById('travel_action');
        const tvIdInput = document.getElementById('edit_tv_id');
        const tvNameInput = document.getElementById('tv_name');
        const distanceInput = document.getElementById('distance');
        const tvMapInput = document.getElementById('tv_map');
        const tpIdInput = document.getElementById('tp_id');

        // ฟังก์ชัน Reset ฟอร์ม
        function resetTravelForm() {
            travelForm.reset(); // ใช้ reset() ง่ายกว่า
            actionInput.value = 'add_travel';
            tvIdInput.value = '0';
            tpIdInput.value = '4'; // Reset tp_id กลับเป็น 4
            travelModalLabel.innerHTML = '<i class="bi bi-geo-alt-fill me-2"></i>เพิ่มสถานที่ท่องเที่ยว';
            travelSubmitButton.classList.remove('btn-warning'); // เผื่อเป็นสีเหลืองตอนแก้ไข
            travelSubmitButton.classList.add('btn-danger');
            travelSubmitButton.innerHTML = '<i class="bi bi-save-fill me-1"></i> บันทึก';
        }

        // เมื่อกดปุ่ม "เพิ่มสถานที่"
        document.getElementById('openAddTravel')?.addEventListener('click', resetTravelForm);

        // เมื่อกดปุ่ม "แก้ไข" ในตาราง
        document.querySelectorAll('.btn-edit-travel').forEach(button => {
            button.addEventListener('click', () => {
                resetTravelForm(); // Reset ก่อน เผื่อค่าเก่าค้าง

                actionInput.value = 'edit_travel';
                tvIdInput.value = button.dataset.tvId;
                tvNameInput.value = button.dataset.tvName;
                distanceInput.value = button.dataset.distance;
                tvMapInput.value = button.dataset.tvMap;
                tpIdInput.value = button.dataset.tpId; // ดึง tp_id มาด้วย

                travelModalLabel.innerHTML = '<i class="bi bi-pencil-square me-2"></i>แก้ไขสถานที่ท่องเที่ยว';
                travelSubmitButton.classList.remove('btn-danger');
                travelSubmitButton.classList.add('btn-warning'); // เปลี่ยนเป็นสีเหลือง
                 travelSubmitButton.innerHTML = '<i class="bi bi-save-fill me-1"></i> บันทึกการแก้ไข';

                 // เปิด Modal (ไม่ต้อง new ทุกครั้ง)
                 bootstrap.Modal.getOrCreateInstance(travelModal).show();
            });
        });

        // --- ส่วนที่ 2: จัดการ AJAX และ SweetAlert ---
        const swalConfig = (title, text, icon, confirmButtonColor) => ({
            title: title, text: text, icon: icon, showCancelButton: true,
            confirmButtonColor: confirmButtonColor || '#3085d6', cancelButtonColor: '#6c757d',
            confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก'
        });
        const swalDeleteConfig = (title, html) => ({
             title: title, html: html, icon: 'warning', showCancelButton: true,
             confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
             confirmButtonText: '<i class="bi bi-trash3-fill me-1"></i> ใช่, ลบเลย', cancelButtonText: 'ยกเลิก'
         });
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true,
            didOpen: (toast) => { toast.addEventListener('mouseenter', Swal.stopTimer); toast.addEventListener('mouseleave', Swal.resumeTimer); }
        });

        // --- จัดการ Submit ฟอร์ม Add/Edit ---
        travelForm?.addEventListener('submit', function(event) {
             event.preventDefault();
             const isEditing = (actionInput.value === 'edit_travel');
             const config = swalConfig(
                isEditing ? 'ยืนยันการแก้ไข' : 'ยืนยันการเพิ่ม',
                isEditing ? 'บันทึกการเปลี่ยนแปลงนี้ใช่หรือไม่?' : 'เพิ่มสถานที่นี้ใช่หรือไม่?',
                'question',
                isEditing ? '#ffc107' : '#dc3545' // เหลือง หรือ แดง
            );

            Swal.fire(config).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData(this);
                    const modalInstance = bootstrap.Modal.getInstance(travelModal);
                    if (modalInstance) modalInstance.hide(); // ปิด Modal

                    fetch('api_travel_handler.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Toast.fire({ icon: 'success', title: data.message })
                            .then(() => { location.reload(); });
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด!', data.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + error, 'error');
                    });
                }
            });
        });

        // --- จัดการปุ่ม Delete ---
        document.querySelectorAll('.btn-delete-travel').forEach(button => {
            button.addEventListener('click', function() {
                const tvId = this.dataset.tvId;
                const tvName = this.dataset.tvName;
                const config = swalDeleteConfig(
                    'ยืนยันการลบ',
                    `ต้องการลบ <strong>${tvName}</strong> (ID: ${tvId}) ใช่หรือไม่?<br><strong class='text-danger'>ข้อมูลจะถูกลบถาวร!</strong>`
                );

                Swal.fire(config).then((result) => {
                     if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('action', 'delete_travel');
                        formData.append('tv_id', tvId);

                        fetch('api_travel_handler.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Toast.fire({ icon: 'success', title: data.message })
                                .then(() => { location.reload(); });
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด!', data.message || 'ไม่สามารถลบข้อมูลได้', 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + error, 'error');
                        });
                    }
                });
            });
        });


        // --- ส่วนที่ 3: Script ค้นหา ---
        const qTravelEl = document.getElementById('q_travel');
        qTravelEl?.addEventListener('input', ()=>{
            const q = (qTravelEl.value || '').toLowerCase().trim();
            let count = 0;
            document.querySelectorAll('#tbody_travel .row-item-travel').forEach(tr=>{
                // ค้นหาจากชื่อสถานที่อย่างเดียวตอนนี้
                const text = (tr.dataset.title || '').toLowerCase();
                const show = !q || text.includes(q);
                tr.style.display = show ? '' : 'none';
                if(show) count++;
            });
            // อาจจะเพิ่มการแสดงผลว่า "ไม่พบข้อมูล" ถ้า count = 0
        });

    });
    </script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>