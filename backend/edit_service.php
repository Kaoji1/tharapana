<?php
// (ส่วน PHP ด้านบนเหมือนเดิมทุกประการ)
include_once("connectdb.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// --- 1. CONNECT DB ---
$host="localhost"; $usr="root"; $pwd=""; $dbName="tharapana";
$conn = mysqli_connect($host,$usr,$pwd,$dbName);
if (!$conn) { http_response_code(500); die('DB connect failed'); }
mysqli_set_charset($conn, 'utf8mb4');

// --- 3. FETCH DATA FOR DISPLAY ---
$serve_types = [];
$result_types = $conn->query("SELECT * FROM serve_type ORDER BY st_id");
while ($row = $result_types->fetch_assoc()) {
    $serve_types[] = $row;
}
$services = [];
$result_services = $conn->query("SELECT * FROM serve ORDER BY st_id, s_id");
while ($row = $result_services->fetch_assoc()) {
    $services[$row['st_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบริการ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* === CSS ที่นำมาจาก Gallery === */
        .table-actions {
            white-space: nowrap;
            width: 1%;
        }
        .alert-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1056; /* ให้อยู่เหนือ Modal */
        }
        .form-control-sm-search {
            padding-top: 0.4rem; padding-bottom: 0.4rem;
        }
        .badge-category {
            background-color: var(--bs-primary-bg-subtle);
            color: var(--bs-primary-text-emphasis);
            border: 1px solid var(--bs-primary-border-subtle);
        }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-4">
        
        <div id="alert-container" class="alert-container"></div>
    
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
                <h5 class="card-title mb-0"><i class="bi bi-tags-fill me-2 text-primary"></i>จัดการประเภทบริการ</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" id="q1" class="form-control form-control-sm-search border-start-0" placeholder="ค้นหาชื่อประเภท...">
                    </div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTypeModal">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มประเภท
                    </button>
                </div>
            </div>
            <div class="card-body p-0"> <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"> <tr>
                                <th>ชื่อประเภท</th>
                                <th>Icon Class</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="tbody1">
                            <?php foreach ($serve_types as $type): ?>
                            <tr class="row-item-type" data-title="<?= strtolower(htmlspecialchars($type['st_name'])) ?>">
                                <td><?php echo htmlspecialchars($type['st_name']); ?></td>
                                <td><i class="<?php echo htmlspecialchars($type['st_icon']); ?>"></i> <?php echo htmlspecialchars($type['st_icon']); ?></td>
                                <td class="table-actions text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-primary btn-edit-type"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editTypeModal"
                                            data-st-id="<?php echo $type['st_id']; ?>"
                                            data-st-name="<?php echo htmlspecialchars($type['st_name'], ENT_QUOTES); ?>"
                                            data-tp-id="<?php echo $type['tp_id']; ?>"
                                            data-st-icon="<?php echo htmlspecialchars($type['st_icon'], ENT_QUOTES); ?>">
                                            <i class="bi bi-pencil-square"></i> <span class="d-none d-md-inline">แก้ไข</span>
                                        </button>
                                        <button class="btn btn-outline-danger btn-delete-type"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteTypeModal"
                                            data-st-id="<?php echo $type['st_id']; ?>"
                                            data-st-name="<?php echo htmlspecialchars($type['st_name'], ENT_QUOTES); ?>">
                                            <i class="bi bi-trash3-fill"></i> <span class="d-none d-md-inline">ลบ</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
                <h5 class="card-title mb-0"><i class="bi bi-ui-checks me-2 text-success"></i>จัดการบริการ</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" id="q2" class="form-control form-control-sm-search border-start-0" placeholder="ค้นหาบริการ, หมวดหมู่...">
                    </div>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มบริการ
                    </button>
                </div>
            </div>
            <div class="card-body p-3" id="tbody2"> <?php if (empty($serve_types)): ?>
                    <p class="text-center text-muted mt-3">กรุณาเพิ่มประเภทบริการก่อน</p>
                <?php endif; ?>
                
                <?php foreach ($serve_types as $type): ?>
                <div class="service-group" data-catname="<?= strtolower(htmlspecialchars($type['st_name'])) ?>">
                    <h5 class="mt-3"><i class="<?php echo htmlspecialchars($type['st_icon']); ?>"></i> <?php echo htmlspecialchars($type['st_name']); ?></h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-3">
                            <thead class="table-light"> <tr>
                                    <th>ชื่อบริการ</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $has_service = false; ?>
                                <?php if (isset($services[$type['st_id']]) && count($services[$type['st_id']]) > 0): ?>
                                    <?php foreach ($services[$type['st_id']] as $service): ?>
                                    <?php $has_service = true; ?>
                                    <tr class="row-item-service" data-title="<?= strtolower(htmlspecialchars($service['s_name'])) ?>">
                                        <td><?php echo htmlspecialchars($service['s_name']); ?></td>
                                        <td class="table-actions text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary btn-edit-service"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editServiceModal"
                                                    data-s-id="<?php echo $service['s_id']; ?>"
                                                    data-s-name="<?php echo htmlspecialchars($service['s_name'], ENT_QUOTES); ?>"
                                                    data-st-id="<?php echo $service['st_id']; ?>">
                                                    <i class="bi bi-pencil-square"></i> <span class="d-none d-md-inline">แก้ไข</span>
                                                </button>
                                                <button class="btn btn-outline-danger btn-delete-service"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteServiceModal"
                                                    data-s-id="<?php echo $service['s_id']; ?>"
                                                    data-s-name="<?php echo htmlspecialchars($service['s_name'], ENT_QUOTES); ?>">
                                                    <i class="bi bi-trash3-fill"></i> <span class="d-none d-md-inline">ลบ</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!$has_service): ?>
                                    <tr class="row-item-service-empty">
                                        <td colspan="2" class="text-center text-muted">ยังไม่มีบริการในประเภทนี้</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

    <div class="modal fade" id="addTypeModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered"> 
            <div class="modal-content shadow-lg border-0 rounded-3"> 
                <form method="POST" id="addTypeForm">
                    <input type="hidden" name="action" value="add_type">
                    <div class="modal-header bg-primary text-white"> 
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>เพิ่มประเภทบริการ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4"> 
                        <div class="mb-3">
                            <label class="form-label">ชื่อประเภท <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="st_name" required>
                        </div>
                        <input type="hidden" name="tp_id" value="5">
                        <div class="mb-3">
                            <label class="form-label">Icon Class <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="st_icon" placeholder="เช่น bi bi-tags-fill" required>
                        </div>
                        
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">เพิ่มบริการในประเภทนี้ (Optional)</label>
                            <textarea class="form-control" name="service_names" rows="4" placeholder="กรอกชื่อบริการ บรรทัดละ 1 รายการ..."></textarea>
                            <div class="form-text">หากไม่ต้องการเพิ่มบริการ ให้เว้นว่างไว้</div>
                        </div>
                        </div>
                    <div class="modal-footer bg-light"> 
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save-fill me-1"></i> บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editTypeModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-3">
                <form method="POST" id="editTypeForm">
                    <input type="hidden" name="action" value="edit_type">
                    <input type="hidden" id="edit_st_id" name="st_id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>แก้ไขประเภทบริการ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label">ชื่อประเภท</label>
                            <input type="text" class="form-control" id="edit_st_name" name="st_name" required>
                        </div>
                        <input type="hidden" id="edit_tp_id" name="tp_id">
                        <div class="mb-3">
                            <label class="form-label">Icon Class</label>
                            <input type="text" class="form-control" id="edit_st_icon" name="st_icon" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                             <i class="bi bi-x-lg me-1"></i>ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-warning"> <i class="bi bi-save-fill me-1"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteTypeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-3">
                <form method="POST" id="deleteTypeForm">
                    <input type="hidden" name="action" value="delete_type">
                    <input type="hidden" id="delete_st_id" name="st_id">
                    <div class="modal-header bg-danger text-white"> <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p>คุณต้องการลบประเภทบริการ: <strong id="delete_st_name"></strong> ใช่หรือไม่?</p>
                        <p class="text-danger small mt-2"><strong><i class="bi bi-info-circle me-1"></i>คำเตือน:</strong> คุณจะลบได้ก็ต่อเมื่อไม่มีบริการใดๆ สังกัดอยู่กับประเภทนี้</p>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                             <i class="bi bi-x-lg me-1"></i>ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash3-fill me-1"></i> ยืนยันการลบ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div class="modal fade" id="addServiceModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-3">
                <form method="POST" id="addServiceForm">
                    <input type="hidden" name="action" value="add_service">
                    <div class="modal-header bg-success text-white"> <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>เพิ่มบริการ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label">ชื่อบริการ</label>
                            <input type="text" class="form-control" name="s_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ประเภทบริการ</label>
                            <select class="form-select" name="st_id" required>
                                <option value="" disabled selected>-- เลือกประเภท --</option>
                                <?php foreach ($serve_types as $type): ?>
                                    <option value="<?php echo $type['st_id']; ?>">
                                        <?php echo htmlspecialchars($type['st_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                             <i class="bi bi-x-lg me-1"></i>ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save-fill me-1"></i> บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editServiceModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-3">
                <form method="POST" id="editServiceForm">
                    <input type="hidden" name="action" value="edit_service">
                    <input type="hidden" id="edit_s_id" name="s_id">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>แก้ไขบริการ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label">ชื่อบริการ</label>
                            <input type="text" class="form-control" id="edit_s_name" name="s_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ประเภทบริการ</label>
                            <select class="form-select" id="edit_s_st_id" name="st_id" required>
                                <option value="">-- เลือกประเภท --</option>
                                <?php foreach ($serve_types as $type): ?>
                                    <option value="<?php echo $type['st_id']; ?>">
                                        <?php echo htmlspecialchars($type['st_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save-fill me-1"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteServiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-3">
                <form method="POST" id="deleteServiceForm">
                    <input type="hidden" name="action" value="delete_service">
                    <input type="hidden" id="delete_s_id" name="s_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p>คุณต้องการลบบริการ: <strong id="delete_s_name"></strong> ใช่หรือไม่?</p>
                        <p class="text-danger small mt-2"><i class="bi bi-info-circle me-1"></i>การกระทำนี้จะลบข้อมูลออกจากฐานข้อมูลอย่างถาวร</p>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash3-fill me-1"></i> ยืนยันการลบ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // --- ส่วนที่ 1: โค้ดส่งข้อมูลไปที่ Modals (ยังคงจำเป็น) ---
        // (เหมือนเดิม)
        var editTypeModal = document.getElementById('editTypeModal');
        editTypeModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; var modal = this;
            modal.querySelector('#edit_st_id').value = button.getAttribute('data-st-id');
            modal.querySelector('#edit_st_name').value = button.getAttribute('data-st-name');
            modal.querySelector('#edit_tp_id').value = button.getAttribute('data-tp-id');
            modal.querySelector('#edit_st_icon').value = button.getAttribute('data-st-icon');
        });
        var deleteTypeModal = document.getElementById('deleteTypeModal');
        deleteTypeModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; var modal = this;
            modal.querySelector('#delete_st_id').value = button.getAttribute('data-st-id');
            modal.querySelector('#delete_st_name').textContent = button.getAttribute('data-st-name');
        });
        var editServiceModal = document.getElementById('editServiceModal');
        editServiceModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; var modal = this;
            modal.querySelector('#edit_s_id').value = button.getAttribute('data-s-id');
            modal.querySelector('#edit_s_name').value = button.getAttribute('data-s-name');
            modal.querySelector('#edit_s_st_id').value = button.getAttribute('data-st-id');
        });
        var deleteServiceModal = document.getElementById('deleteServiceModal');
        deleteServiceModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; var modal = this;
            modal.querySelector('#delete_s_id').value = button.getAttribute('data-s-id');
            modal.querySelector('#delete_s_name').textContent = button.getAttribute('data-s-name');
        });

        // --- ส่วนที่ 2: โค้ด AJAX (เหมือนเดิม) ---
        // (ฟังก์ชันตั้งค่า SweetAlert เหมือนเดิม)
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

        function handleFormSubmit(formId, configCallback) {
            const form = document.getElementById(formId);
            if (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault(); 
                    const config = configCallback(this); 
                    
                    Swal.fire(config).then((result) => {
                        if (result.isConfirmed) {
                            const formData = new FormData(this);
                            const modal = this.closest('.modal'); 
                            if (modal) { bootstrap.Modal.getInstance(modal).hide(); }

                            fetch('api_service_handler.php', { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    Toast.fire({ icon: 'success', title: data.message })
                                    .then(() => { location.reload(); });
                                } else {
                                    Swal.fire('เกิดข้อผิดพลาด!', data.message, 'error');
                                }
                            })
                            .catch(error => {
                                Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + error, 'error');
                            });
                        }
                    });
                });
            }
        }
        // (การเรียกใช้ AJAX เหมือนเดิม)
        handleFormSubmit('addTypeForm', (form) => swalConfig('ยืนยันการเพิ่ม', 'เพิ่มประเภทบริการนี้ใช่หรือไม่?', 'question', '#0d6efd'));
        handleFormSubmit('editTypeForm', (form) => swalConfig('ยืนยันการแก้ไข', 'บันทึกการเปลี่ยนแปลงนี้ใช่หรือไม่?', 'question', '#ffc107'));
        handleFormSubmit('deleteTypeForm', (form) => swalDeleteConfig('ยืนยันการลบ', `ต้องการลบ <strong>${form.querySelector('#delete_st_name').textContent}</strong> ใช่หรือไม่?<br><strong class='text-danger'>ข้อมูลจะถูกลบถาวร!</strong>`));
        handleFormSubmit('addServiceForm', (form) => swalConfig('ยืนยันการเพิ่ม', 'เพิ่มบริการนี้ใช่หรือไม่?', 'question', '#198754'));
        handleFormSubmit('editServiceForm', (form) => swalConfig('ยืนยันการแก้ไข', 'บันทึกการเปลี่ยนแปลงนี้ใช่หรือไม่?', 'question', '#ffc107'));
        handleFormSubmit('deleteServiceForm', (form) => swalDeleteConfig('ยืนยันการลบ', `ต้องการลบ <strong>${form.querySelector('#delete_s_name').textContent}</strong> ใช่หรือไม่?`));


        // --- ส่วนที่ 3: [ใหม่] Script ค้นหา (จาก Gallery) ---
        const q1El=document.getElementById('q1');
        q1El?.addEventListener('input', ()=>{
            const q=(q1El.value||'').toLowerCase().trim();
            document.querySelectorAll('#tbody1 .row-item-type').forEach(tr=>{
                const text=(tr.dataset.title || '').toLowerCase();
                tr.style.display = !q || text.includes(q) ? '' : 'none';
            });
        });

        const q2El=document.getElementById('q2');
        q2El?.addEventListener('input', ()=>{
            const q=(q2El.value||'').toLowerCase().trim();
            document.querySelectorAll('#tbody2 .service-group').forEach(group=>{
                let groupHasMatch = false;
                const catName = (group.dataset.catname || '').toLowerCase();
                
                group.querySelectorAll('.row-item-service').forEach(tr=>{
                    const text=(tr.dataset.title || '').toLowerCase();
                    const show = !q || text.includes(q) || catName.includes(q);
                    tr.style.display = show ? '' : 'none';
                    if(show) groupHasMatch = true;
                });
                
                // ซ่อน "ไม่มีบริการ" ถ้าแถวอื่นแสดง
                const emptyRow = group.querySelector('.row-item-service-empty');
                if(emptyRow) {
                    emptyRow.style.display = groupHasMatch ? 'none' : (q ? 'none' : ''); // ซ่อนแถว "ไม่มี" ถ้าค้นหา หรือถ้ามีรายการอื่น
                }
                
                // ซ่อนทั้งกลุ่ม (h5 + table) ถ้าไม่มีอะไรตรงเลย
                group.style.display = groupHasMatch || catName.includes(q) ? '' : 'none';
            });
        });

    });
    </script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>