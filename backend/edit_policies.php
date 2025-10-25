<?php
// 1. เริ่ม session และตรวจสอบการเข้าถึง
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ADMIN_EMBED')) { // ตรวจสอบว่าถูก include จาก admin.php
    exit('Direct access denied');
}

// เรียกใช้ connectdb.php
require_once __DIR__ . '/connectdb.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('DB connection error in edit_policies.php');
}

// --- การตั้งค่า ---
define('TABLE_NAME', 'policies');      // เปลี่ยนชื่อตาราง
$page_key = 'edit_policies';             // ชื่อ key ของหน้านี้
$page_title = 'จัดการ Policies';     // ชื่อ Title
$page_icon = 'bi-file-earmark-text-fill'; // ไอคอน Bootstrap (เปลี่ยนไอคอน)

// --- ตัวแปรและฟังก์ชัน ---
$admin_logging_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$message = '';
$message_type = '';
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ฟังก์ชันสร้าง Log (เหมือนเดิม)
function create_log($conn, $admin_id, $action, $target_id, $details = '') {
    if (empty($admin_id)) { $admin_id = 0; }
    $sql = "INSERT INTO admin_logs (admin_user_id, action_type, target_user_id, details) VALUES (?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt_log, "isis", $admin_id, $action, $target_id, $details);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
}

// (ลบส่วนตรวจสอบคอลัมน์ Style ออก - ตารางนี้ไม่มี)
$editable_columns = ['heading_th', 'content_th', 'icon_class', 'item_type']; // คอลัมน์ที่จะแก้ไข

// --- [ส่วนจัดการ Logic POST] ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        $message = 'CSRF token ไม่ถูกต้อง!'; $message_type = 'danger';
    } else {
        // [จัดการแก้ไข]
        if (isset($_POST['action']) && $_POST['action'] === 'update_policy') { // เปลี่ยน action
            $target_id = (int)$_POST['id_poli']; // เปลี่ยน id
            
            // ดึงข้อมูลใหม่จาก POST ตามคอลัมน์ที่กำหนด
            $new_data = [];
            foreach ($editable_columns as $col) {
                $new_data[$col] = trim($_POST[$col] ?? '');
            }

            // ดึงข้อมูลเก่า (เหมือนเดิม)
            $old_data = null; 
            $sql_old = "SELECT * FROM `".TABLE_NAME."` WHERE id_poli = ?"; // เปลี่ยน id
            $stmt_old = mysqli_prepare($conn, $sql_old);
            mysqli_stmt_bind_param($stmt_old, "i", $target_id);
            if (mysqli_stmt_execute($stmt_old)) { $result_old = mysqli_stmt_get_result($stmt_old); $old_data = mysqli_fetch_assoc($result_old); }
            mysqli_stmt_close($stmt_old);

            // สร้าง SQL UPDATE (เหมือนเดิม)
            $set_parts = []; $types = ""; $params = []; 
            foreach ($new_data as $key => $value) {
                $set_parts[] = "`$key` = ?";
                $types .= "s"; // ตารางนี้ส่วนใหญ่เป็น string
                $params[] = ($value === '') ? null : $value; // อนุญาตค่าว่าง
            }

            if (!empty($set_parts)) {
                $sql_update = "UPDATE `".TABLE_NAME."` SET " . implode(", ", $set_parts) . " WHERE id_poli = ?"; // เปลี่ยน id
                $types .= "i"; $params[] = $target_id;
                
                $stmt_update = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, $types, ...$params);
                
                if (mysqli_stmt_execute($stmt_update)) {
                    $message = 'แก้ไขข้อมูล Policy สำเร็จ!'; $message_type = 'success';
                    if ($old_data) { // สร้าง Log (เหมือนเดิม)
                         $details_arr = [];
                         foreach ($new_data as $key => $new_val) {
                             if (isset($old_data[$key]) && $old_data[$key] != $new_val) { $old_val_display = $old_data[$key] ?? 'NULL'; $new_val_display = $new_val ?? 'NULL'; $details_arr[] = "$key: '{$old_val_display}' -> '$new_val_display'"; }
                             elseif (!isset($old_data[$key]) && $new_val !== null) { $details_arr[] = "$key: NULL -> '$new_val'"; }
                         }
                         $details = empty($details_arr) ? "กดแก้ไข ID: $target_id (ไม่มีการเปลี่ยนแปลง)" : "แก้ไข ID: $target_id. เปลี่ยน -> " . implode(", ", $details_arr);
                         create_log($conn, $admin_logging_id, 'UPDATE_POLICY', $target_id, $details); // เปลี่ยน Log type
                    }
                } else { $message = 'เกิดข้อผิดพลาดในการแก้ไข: ' . mysqli_error($conn); $message_type = 'danger'; }
                mysqli_stmt_close($stmt_update);
            } else { $message = 'ไม่มีข้อมูลให้อัปเดต'; $message_type = 'warning'; }
        }
        // [จัดการลบ]
        else if (isset($_POST['action']) && $_POST['action'] === 'delete_policy') { // เปลี่ยน action
            $target_id = (int)$_POST['id_poli']; // เปลี่ยน id
            
            // ดึงข้อมูลก่อนลบ
            $policy_info = "ID: $target_id"; 
            $sql_old_del = "SELECT heading_th FROM `".TABLE_NAME."` WHERE id_poli = ?"; // เปลี่ยน id และ field
            $stmt_old_del = mysqli_prepare($conn, $sql_old_del);
            mysqli_stmt_bind_param($stmt_old_del, "i", $target_id);
            if (mysqli_stmt_execute($stmt_old_del)) { $result_old_del = mysqli_stmt_get_result($stmt_old_del); if ($old_data_del = mysqli_fetch_assoc($result_old_del)) { $policy_info = "ID: $target_id, ชื่อ: '{$old_data_del['heading_th']}'"; } } // เปลี่ยน field
            mysqli_stmt_close($stmt_old_del);

            // ทำการ DELETE
            $sql_delete = "DELETE FROM `".TABLE_NAME."` WHERE id_poli = ?"; // เปลี่ยน id
            $stmt_delete = mysqli_prepare($conn, $sql_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $target_id);
            if (mysqli_stmt_execute($stmt_delete)) {
                $message = 'ลบข้อมูล Policy สำเร็จ!'; $message_type = 'success';
                create_log($conn, $admin_logging_id, 'DELETE_POLICY', $target_id, "ลบ Policy: " . $policy_info); // เปลี่ยน Log type
            } else { $message = 'เกิดข้อผิดพลาดในการลบ: ' . mysqli_error($conn); $message_type = 'danger'; }
            mysqli_stmt_close($stmt_delete);
        }
    }
}

// --- [ส่วนดึงข้อมูลแสดงผล] ---
$records_per_page = 10;
$current_page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) { $current_page = 1; }
$offset = ($current_page - 1) * $records_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "WHERE 1"; $params = []; $param_types = "";
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    // เปลี่ยน field ที่ค้นหา
    $where_clause .= " AND (heading_th LIKE ? OR content_th LIKE ? OR icon_class LIKE ?)";
    array_push($params, $search_term, $search_term, $search_term); $param_types .= "sss";
}
// นับจำนวน
$sql_count = "SELECT COUNT(id_poli) as total FROM `".TABLE_NAME."` $where_clause"; // เปลี่ยน id
$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($search_query)) { mysqli_stmt_bind_param($stmt_count, $param_types, ...$params); }
mysqli_stmt_execute($stmt_count); $result_count = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_assoc($result_count)['total'] ?? 0;
$total_pages = ceil($total_records / $records_per_page); mysqli_stmt_close($stmt_count);

// ดึงข้อมูล (ใช้ระบบดึงคอลัมน์อัตโนมัติจากต้นแบบ)
$cols = [];
if ($res = mysqli_query($conn, "SHOW COLUMNS FROM `".TABLE_NAME."`")) {
    while ($row = mysqli_fetch_assoc($res)) $cols[] = $row['Field'];
    mysqli_free_result($res);
} else {
    die("Error checking columns: " . mysqli_error($conn));
}

$select_fields = implode(", ", array_map(function($c){ return "`$c`"; }, $cols));
$items = []; // เปลี่ยนชื่อตัวแปร
$sql_select = "SELECT $select_fields FROM `".TABLE_NAME."` $where_clause ORDER BY sort_order ASC LIMIT ? OFFSET ?"; // เปลี่ยน ORDER BY
$stmt_select = mysqli_prepare($conn, $sql_select);
array_push($params, $records_per_page, $offset); $param_types .= "ii";
mysqli_stmt_bind_param($stmt_select, $param_types, ...$params); mysqli_stmt_execute($stmt_select);
$result = mysqli_stmt_get_result($stmt_select);
if ($result) { $items = mysqli_fetch_all($result, MYSQLI_ASSOC); mysqli_free_result($result); }
else { $message = "เกิดข้อผิดพลาดดึงข้อมูล: " . mysqli_error($conn); $message_type = 'danger'; }
mysqli_stmt_close($stmt_select);
mysqli_close($conn);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    /* CSS จากต้นแบบ (เหมือนเดิม) */
    .preview-box {
        border: 1px dashed #ced4da; padding: 0.5rem 0.75rem;
        border-radius: 0.375rem; background-color: #f8f9fa;
        max-height: 100px; overflow-y: auto;
        font-size: 0.9em; line-height: 1.6;
    }
    .preview-box:empty::before { 
        content: "(ไม่มีเนื้อหา)"; color: #6c757d; font-style: italic;
    }
    /* เพิ่ม style สำหรับ icon preview ในตาราง */
    .icon-preview { font-size: 1.2em; min-width: 25px; display: inline-block; }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if ($message): ?>
<div class="alert alert-<?= h($message_type) ?> alert-dismissible fade show mb-4" role="alert">
    <i class="bi <?= $message_type == 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
    <?= h($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
        <h5 class="card-title mb-0"><i class="bi <?= h($page_icon) ?> me-2"></i><?= h($page_title) ?></h5>
        <form method="GET" action="admin.php" class="d-flex" role="search">
            <input type="hidden" name="page" value="<?= h($page_key) ?>">
            <input class="form-control form-control-sm me-2" type="search" name="search" placeholder="ค้นหา หัวข้อ, เนื้อหา, ไอคอน..." value="<?= h($search_query) ?>" aria-label="Search">
            <button class="btn btn-sm btn-outline-primary" type="submit">
                <i class="bi bi-search"></i> ค้นหา
            </button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle caption-top">
                <caption>แสดง <?= count($items) ?> รายการ จากทั้งหมด <?= $total_records ?> รายการ</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="text-center" style="width: 5%;">ID</th>
                        <th scope="col" style="width: 25%;">หัวข้อ (Heading)</th>
                        <th scope="col" style="width: 35%;">เนื้อหา (Content)</th>
                        <th scope="col" style="width: 15%;">Icon</th>
                        <th scope="col" class="text-center" style="width: 10%;">Type</th>
                        <th scope="col" class="text-center" style="width: 10%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <?= !empty($search_query) ? "ไม่พบข้อมูลที่ตรงกับ '".h($search_query)."'" : "<i class='bi bi-info-circle me-2'></i>ไม่มีข้อมูลในตาราง" ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="text-center fw-bold"><?= h($item['id_poli']) ?></td>
                                <td><?= h($item['heading_th']) ?></td>
                                <td>
                                    <div class="preview-box">
                                        <?= nl2br(h($item['content_th'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <i class="bi <?= h($item['icon_class']) ?> icon-preview" title="<?= h($item['icon_class']) ?>"></i>
                                    <small class="text-muted"><?= h($item['icon_class']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= h($item['item_type']) ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary edit-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editPolicyModal" 
                                                <?php foreach ($item as $key => $value): ?>
                                                data-<?= h($key) ?>="<?= h($value ?? '', ENT_QUOTES) ?>"
                                                <?php endforeach; ?>>
                                            <i class="bi bi-pencil-square me-1"></i> แก้ไข
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-btn"
                                                data-id="<?= (int)$item['id_poli'] ?>"
                                                data-name="<?= h($item['heading_th'], ENT_QUOTES) ?>">
                                            <i class="bi bi-trash3-fill me-1"></i> ลบ
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4 d-flex justify-content-center">
            <ul class="pagination shadow-sm">
                <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="admin.php?page=<?= h($page_key) ?>&search=<?= urlencode($search_query) ?>&p=<?= $current_page - 1 ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php
                $range = 2; $start = max(1, $current_page - $range); $end = min($total_pages, $current_page + $range);
                if ($start > 1) { echo '<li class="page-item"><a class="page-link" href="admin.php?page='.h($page_key).'&search='.urlencode($search_query).'&p=1">1</a></li>'; if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>'; } }
                for ($i = $start; $i <= $end; $i++) { echo '<li class="page-item '.($i == $current_page ? 'active' : '').'"><a class="page-link" href="admin.php?page='.h($page_key).'&search='.urlencode($search_query).'&p='.$i.'">'.$i.'</a></li>'; }
                if ($end < $total_pages) { if ($end < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>'; } echo '<li class="page-item"><a class="page-link" href="admin.php?page='.h($page_key).'&search='.urlencode($search_query).'&p='.$total_pages.'">'.$total_pages.'</a></li>'; }
                ?>
                <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="admin.php?page=<?= h($page_key) ?>&search=<?= urlencode($search_query) ?>&p=<?= $current_page + 1 ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div> 
</div> 

<div class="modal fade" id="editPolicyModal" tabindex="-1" aria-labelledby="editPolicyModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <form id="editPolicyForm" method="POST" action="admin.php?page=<?= h($page_key) ?>&search=<?= h(urlencode($search_query)) ?>&p=<?= $current_page ?>">
                <div class="modal-header bg-primary text-white">
                    <h1 class="modal-title fs-5" id="editPolicyModalLabel"><i class="bi bi-pencil-fill me-2"></i>แก้ไขข้อมูล Policy</h1>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="update_policy"> <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" id="edit-id_poli" name="id_poli"> <div class="mb-3">
                        <label for="edit-heading_th" class="form-label">หัวข้อ (heading_th) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-heading_th" name="heading_th" required>
                    </div>

                    <div class="row g-3 mb-3">
                         <div class="col-md-6">
                            <label for="edit-icon_class" class="form-label">Icon Class</label>
                            <input type="text" class="form-control" id="edit-icon_class" name="icon_class" placeholder="เช่น bi bi-cash">
                         </div>
                         <div class="col-md-6">
                            <label for="edit-item_type" class="form-label">Item Type</label>
                            <select class="form-select" id="edit-item_type" name="item_type">
                                <option value="text">text</option>
                                <option value="list">list</option>
                                <option value="table">table</option>
                                <option value="html">html</option>
                            </select>
                         </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-content_th" class="form-label">เนื้อหา (content_th)</label>
                        <textarea class="form-control" id="edit-content_th" name="content_th" rows="8" placeholder="กรอกเนื้อหา..."></textarea>
                    </div>
                    
                    </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>ยกเลิก
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save-fill me-1"></i>บันทึกการเปลี่ยนแปลง
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deletePolicyForm" method="POST" action="admin.php?page=<?= h($page_key) ?>&search=<?= h(urlencode($search_query)) ?>&p=<?= $current_page ?>" style="display: none;">
    <input type="hidden" name="action" value="delete_policy"> <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" id="delete-id_poli" name="id_poli"> </form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- จัดการ Modal แก้ไข (เหมือนเดิม) ---
    const editPolicyModal = document.getElementById('editPolicyModal'); // เปลี่ยน ID
    if (editPolicyModal) {
        editPolicyModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const modalForm = editPolicyModal.querySelector('form');
            const data = button.dataset; 

            // (Script auto-fill อัจฉริยะจากต้นแบบ ใช้ได้เลย)
            for (const key in data) {
                // แปลง data-heading_th เป็น edit-heading_th
                const inputId = 'edit-' + key.replace(/([A-Z])/g, '_$1').toLowerCase();
                const input = modalForm.querySelector(`#${inputId}`);
                if (input) {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                         input.checked = (data[key] == '1');
                    } else if (input.type === 'color') {
                         input.value = data[key] || '#000000';
                    } else {
                         input.value = data[key] ?? ''; 
                    }
                }
            }
            // (แก้ ID เฉพาะจุด)
            modalForm.querySelector('#edit-id_poli').value = button.getAttribute('data-id_poli') || '';
        });
    }

    // --- ยืนยันก่อนแก้ไข (เหมือนเดิม) ---
    const editForm = document.getElementById('editPolicyForm'); // เปลี่ยน ID
    if(editForm) {
        editForm.addEventListener('submit', function(event) {
            event.preventDefault();
            Swal.fire({
                title: 'ยืนยันการแก้ไข', text: "บันทึกการเปลี่ยนแปลงใช่หรือไม่?", icon: 'question',
                showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-lg me-1"></i> ใช่, บันทึก', cancelButtonText: 'ยกเลิก'
            }).then((result) => { if (result.isConfirmed) { this.submit(); } });
        });
    }

    // --- ยืนยันก่อนลบ (เหมือนเดิม) ---
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const policyId = this.getAttribute('data-id');   // เปลี่ยนตัวแปร
            const policyName = this.getAttribute('data-name'); // เปลี่ยนตัวแปร
            Swal.fire({
                title: 'ยืนยันการลบ', html: `ต้องการลบ <strong>${policyName}</strong> (ID: ${policyId}) ใช่หรือไม่?<br><strong class='text-danger'>ข้อมูลจะถูกลบถาวร!</strong>`, icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3-fill me-1"></i> ใช่, ลบเลย', cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-id_poli').value = policyId; // เปลี่ยน ID
                    document.getElementById('deletePolicyForm').submit(); // เปลี่ยน ID
                }
            });
        });
    });

    // --- ทำให้ Alert หายไปเอง (เหมือนเดิม) ---
    const alertMessage = document.querySelector('.alert.alert-dismissible');
    if(alertMessage) { setTimeout(() => { bootstrap.Alert.getOrCreateInstance(alertMessage).close(); }, 5000); }
});
</script>