<?php
// 1. เริ่ม session และตรวจสอบการเข้าถึง
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ตรวจสอบว่าถูก include จาก admin.php (ถ้าใช้)
if (!defined('ADMIN_EMBED')) {
     // ถ้าต้องการให้รันไฟล์นี้ตรงๆ ได้ ให้ comment บรรทัด exit() ข้างล่างออก
     // exit('Direct access denied. Please access via admin.php');
}

// เรียกใช้ connectdb.php
require_once __DIR__ . '/connectdb.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('DB connection error in edit_highlights.php');
}
mysqli_set_charset($conn, 'utf8mb4');

// --- การตั้งค่า ---
define('TABLE_HIGHLIGHT', 'highlight');
$page_key = 'edit_highlights'; // ชื่อ key ของหน้านี้ใน admin.php (สำหรับ link)
$page_title = 'จัดการ Highlights';
$page_icon = 'bi-star-fill'; // ไอคอน Bootstrap

// --- ตัวแปรและฟังก์ชัน ---
$admin_logging_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; // ID Admin สำหรับ Log
$message = ''; // ข้อความแจ้งเตือนหลัง POST
$message_type = ''; // ประเภทข้อความ (success, danger)
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); // สร้าง CSRF token
$csrf = $_SESSION['csrf'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function plain($s){ return trim(html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8')); }

// --- ฟังก์ชันสร้าง Log (เหมือน manage_members.php) ---
function create_log($conn, $admin_id, $action, $target_id, $details = '') {
    if (empty($admin_id)) { $admin_id = 0; } // Default to 0 if not logged in
    $sql = "INSERT INTO admin_logs (admin_user_id, action_type, target_user_id, details) VALUES (?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt_log, "isis", $admin_id, $action, $target_id, $details);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
}

// --- [ส่วนจัดการ Logic POST - แบบโหลดหน้าใหม่] ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        $message = 'CSRF token ไม่ถูกต้อง!'; $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        // [จัดการแก้ไข]
        if ($action === 'update_highlight') {
            $target_id = (int)$_POST['hl_id'];
            $new_name = trim($_POST['hl_name'] ?? '');
            $new_detail = trim($_POST['hl_detail'] ?? '');

            if ($target_id <= 0 || $new_name === '' || $new_detail === '') {
                $message = 'กรุณากรอกข้อมูลให้ครบถ้วน'; $message_type = 'warning';
            } else {
                // LOGGING: ดึงข้อมูลเก่า
                $old_data = null;
                $sql_old = "SELECT hl_name, hl_detail FROM `".TABLE_HIGHLIGHT."` WHERE hl_id = ?";
                $stmt_old = mysqli_prepare($conn, $sql_old);
                mysqli_stmt_bind_param($stmt_old, "i", $target_id);
                if (mysqli_stmt_execute($stmt_old)) { $result_old = mysqli_stmt_get_result($stmt_old); $old_data = mysqli_fetch_assoc($result_old); }
                mysqli_stmt_close($stmt_old);

                // ทำการ UPDATE
                $sql_update = "UPDATE `".TABLE_HIGHLIGHT."` SET hl_name = ?, hl_detail = ? WHERE hl_id = ?";
                $stmt_update = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "ssi", $new_name, $new_detail, $target_id);
                if (mysqli_stmt_execute($stmt_update)) {
                    $message = 'แก้ไขข้อมูล Highlight สำเร็จ!'; $message_type = 'success';
                    // LOGGING: บันทึกหลังแก้ไข
                    if ($old_data) {
                        $details_arr = [];
                        if ($old_data['hl_name'] != $new_name) $details_arr[] = "ชื่อ: '{$old_data['hl_name']}' -> '{$new_name}'";
                        if ($old_data['hl_detail'] != $new_detail) $details_arr[] = "รายละเอียด: เปลี่ยนแปลง"; // ไม่เก็บ detail ยาวๆ ใน log
                        $details = empty($details_arr) ? "กดแก้ไข ID: $target_id (ไม่มีการเปลี่ยนแปลง)" : "แก้ไข ID: $target_id. เปลี่ยน -> " . implode(", ", $details_arr);
                        create_log($conn, $admin_logging_id, 'UPDATE_HIGHLIGHT', $target_id, $details);
                    }
                } else { $message = 'เกิดข้อผิดพลาดในการแก้ไข: ' . mysqli_error($conn); $message_type = 'danger'; }
                mysqli_stmt_close($stmt_update);
            }
        }
        // [จัดการลบ]
        else if ($action === 'delete_highlight') {
            $target_id = (int)$_POST['hl_id'];

            // LOGGING: ดึงข้อมูลก่อนลบ
            $item_info = "ID: $target_id";
            $sql_old_del = "SELECT hl_name FROM `".TABLE_HIGHLIGHT."` WHERE hl_id = ?";
            $stmt_old_del = mysqli_prepare($conn, $sql_old_del);
            mysqli_stmt_bind_param($stmt_old_del, "i", $target_id);
            if (mysqli_stmt_execute($stmt_old_del)) { $result_old_del = mysqli_stmt_get_result($stmt_old_del); if ($d = mysqli_fetch_assoc($result_old_del)) { $item_info = "ID: $target_id, ชื่อ: '{$d['hl_name']}'"; } }
            mysqli_stmt_close($stmt_old_del);

            // ทำการ DELETE
            $sql_delete = "DELETE FROM `".TABLE_HIGHLIGHT."` WHERE hl_id = ?";
            $stmt_delete = mysqli_prepare($conn, $sql_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $target_id);
            if (mysqli_stmt_execute($stmt_delete)) {
                $message = 'ลบข้อมูล Highlight สำเร็จ!'; $message_type = 'success';
                create_log($conn, $admin_logging_id, 'DELETE_HIGHLIGHT', $target_id, "ลบ Highlight: " . $item_info); // สร้าง Log
            } else { $message = 'เกิดข้อผิดพลาดในการลบ: ' . mysqli_error($conn); $message_type = 'danger'; }
            mysqli_stmt_close($stmt_delete);
        }
    } // End CSRF check
    // เก็บ message ลง session เพื่อแสดงหลัง redirect (ถ้าต้องการ PRG)
    // $_SESSION['message'] = $message;
    // $_SESSION['message_type'] = $message_type;
    // header("Location: admin.php?page=" . $page_key); // Redirect กลับไปหน้าเดิม (ถ้าใช้ PRG)
    // exit;
}

// --- [ส่วนดึงข้อมูลแสดงผล] ---
$rows = [];
$sql = "SELECT hl_id, hl_name, hl_detail FROM `".TABLE_HIGHLIGHT."` ORDER BY hl_id ASC";
if ($res = mysqli_query($conn, $sql)) {
    while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
    mysqli_free_result($res);
} else {
    $message = "เกิดข้อผิดพลาดดึงข้อมูล: " . mysqli_error($conn); $message_type = 'danger';
}
mysqli_close($conn); // ปิด connection ตรงนี้ถ้าไม่ใช้แล้ว
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    .preview-box { /* เหมือน edit_aboutus */
        border: 1px dashed #ced4da; padding: 0.5rem 0.75rem; border-radius: 0.375rem;
        background-color: #f8f9fa; max-height: 100px; overflow-y: auto;
        font-size: 0.9em; line-height: 1.6; white-space: pre-wrap; /* ให้ตัดคำ */
    }
    .preview-box:empty::before { content: "(ไม่มีรายละเอียด)"; color: #6c757d; font-style: italic; }
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
        <h5 class="card-title mb-0"><i class="bi <?= h($page_icon) ?> me-2 text-warning"></i><?= h($page_title) ?></h5>
        <span class="text-muted small">ตาราง: <?= h(TABLE_HIGHLIGHT) ?></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="text-center" style="width: 5%;">ID</th>
                        <th scope="col" style="width: 30%;">ชื่อ Highlight (hl_name)</th>
                        <th scope="col">รายละเอียด (hl_detail)</th>
                        <th scope="col" class="text-center" style="width: 15%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4"><i class='bi bi-info-circle me-2'></i>ไม่มีข้อมูล</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="text-center fw-bold"><?= h($row['hl_id']) ?></td>
                                <td><?= h($row['hl_name']) ?></td>
                                <td>
                                    <div class="preview-box">
                                        <?= h($row['hl_detail']) // ไม่ใช้ nl2br เพราะ textarea จะแสดง \n เอง ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary edit-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editHighlightModal"
                                                data-hl_id="<?= (int)$row['hl_id'] ?>"
                                                data-hl_name="<?= h($row['hl_name'], ENT_QUOTES) ?>"
                                                data-hl_detail="<?= h($row['hl_detail'], ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil-square me-1"></i> แก้ไข
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-btn"
                                                data-id="<?= (int)$row['hl_id'] ?>"
                                                data-name="<?= h($row['hl_name'], ENT_QUOTES) ?>">
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
        </div> </div> <div class="modal fade" id="editHighlightModal" tabindex="-1" aria-labelledby="editHighlightModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg border-0 rounded-3">
      <form id="editHighlightForm" method="POST" action="admin.php?page=<?= h($page_key) ?>">
        <div class="modal-header bg-primary text-white">
          <h1 class="modal-title fs-5" id="editHighlightModalLabel"><i class="bi bi-pencil-fill me-2"></i>แก้ไขข้อมูล Highlight</h1>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
            <input type="hidden" name="action" value="update_highlight">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" id="edit-hl_id" name="hl_id">

            <div class="mb-3">
                <label for="edit-hl_name" class="form-label">ชื่อ Highlight <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit-hl_name" name="hl_name" required>
            </div>
            <div class="mb-3">
                <label for="edit-hl_detail" class="form-label">รายละเอียด <span class="text-danger">*</span></label>
                <textarea class="form-control" id="edit-hl_detail" name="hl_detail" rows="6" required placeholder="กรอกรายละเอียด..."></textarea>
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

<form id="deleteHighlightForm" method="POST" action="admin.php?page=<?= h($page_key) ?>" style="display: none;">
    <input type="hidden" name="action" value="delete_highlight">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" id="delete-hl_id" name="hl_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- จัดการ Modal แก้ไข ---
    const editModalEl = document.getElementById('editHighlightModal');
    if (editModalEl) {
        editModalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const modalForm = editModalEl.querySelector('form');
            // ใช้ getAttribute ปลอดภัยกว่า dataset ที่อาจเปลี่ยน case
            modalForm.querySelector('#edit-hl_id').value = button.getAttribute('data-hl_id') || '';
            modalForm.querySelector('#edit-hl_name').value = button.getAttribute('data-hl_name') || '';
            modalForm.querySelector('#edit-hl_detail').value = button.getAttribute('data-hl_detail') || '';
        });
    }

    // --- ยืนยันก่อนแก้ไข (SweetAlert -> Submit Form) ---
    const editForm = document.getElementById('editHighlightForm');
    if(editForm) {
        editForm.addEventListener('submit', function(event) {
            event.preventDefault(); // หยุด submit ปกติ
            Swal.fire({
                title: 'ยืนยันการแก้ไข', text: "บันทึกการเปลี่ยนแปลงใช่หรือไม่?", icon: 'question',
                showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-lg me-1"></i> ใช่, บันทึก', cancelButtonText: 'ยกเลิก'
            }).then((result) => { if (result.isConfirmed) { this.submit(); } }); // Submit จริง
        });
    }

    // --- ยืนยันก่อนลบ (SweetAlert -> Submit Form) ---
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            const itemName = this.getAttribute('data-name');
            Swal.fire({
                title: 'ยืนยันการลบ', html: `ต้องการลบ <strong>${itemName}</strong> (ID: ${itemId}) ใช่หรือไม่?<br><strong class='text-danger'>ข้อมูลจะถูกลบถาวร!</strong>`, icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3-fill me-1"></i> ใช่, ลบเลย', cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-hl_id').value = itemId; // ใส่ ID ในฟอร์มลบ
                    document.getElementById('deleteHighlightForm').submit(); // Submit จริง
                }
            });
        });
    });

    // --- ทำให้ Alert หายไปเอง ---
    const alertMessage = document.querySelector('.alert.alert-dismissible');
    if(alertMessage) { setTimeout(() => { bootstrap.Alert.getOrCreateInstance(alertMessage).close(); }, 5000); }
});
</script>