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
    die('DB connection error in edit_aboutus.php');
}

// --- การตั้งค่า ---
define('TABLE_TOPIC', 'topic');
$page_key = 'edit_aboutus'; // ชื่อ key ของหน้านี้ใน admin.php (สำหรับ link)
$page_title = 'จัดการ Topic (About Us)';
$page_icon = 'bi-pin-angle-fill'; // ไอคอน Bootstrap

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

// ตรวจสอบคอลัมน์ Style ที่มีในตาราง
$cols = [];
if ($res = mysqli_query($conn, "SHOW COLUMNS FROM `".TABLE_TOPIC."`")) {
    while ($row = mysqli_fetch_assoc($res)) $cols[] = $row['Field'];
    mysqli_free_result($res);
} else {
    die("Error checking columns: " . mysqli_error($conn));
}
$style_columns = ['tp_color', 'tp_fontsize', 'tp_fontfamily', 'tp_bold', 'tp_italic'];
$available_style_columns = array_intersect($style_columns, $cols);

// --- [ส่วนจัดการ Logic POST - เหมือนเดิม] ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        $message = 'CSRF token ไม่ถูกต้อง!'; $message_type = 'danger';
    } else {
        // [จัดการแก้ไข]
        if (isset($_POST['action']) && $_POST['action'] === 'update_topic') {
            $target_topic_id = (int)$_POST['tp_id'];
            $new_data = ['tp_name' => trim($_POST['tp_name'] ?? ''), 'tp_detail' => trim($_POST['tp_detail'] ?? '')];
            foreach ($available_style_columns as $col) {
                if ($col === 'tp_color') { $new_data[$col] = !empty($_POST[$col]) ? $_POST[$col] : null; }
                elseif ($col === 'tp_fontsize') { $new_data[$col] = !empty($_POST[$col]) ? (int)$_POST[$col] : null; }
                elseif ($col === 'tp_fontfamily') { $new_data[$col] = !empty($_POST[$col]) ? trim($_POST[$col]) : null; }
                elseif ($col === 'tp_bold' || $col === 'tp_italic') { $new_data[$col] = isset($_POST[$col]) ? 1 : 0; }
            }

            $old_data = null; // ดึงข้อมูลเก่า (เหมือนเดิม) ...
            $sql_old = "SELECT * FROM `".TABLE_TOPIC."` WHERE tp_id = ?";
            $stmt_old = mysqli_prepare($conn, $sql_old);
            mysqli_stmt_bind_param($stmt_old, "i", $target_topic_id);
            if (mysqli_stmt_execute($stmt_old)) { $result_old = mysqli_stmt_get_result($stmt_old); $old_data = mysqli_fetch_assoc($result_old); }
            mysqli_stmt_close($stmt_old);

            $set_parts = []; $types = ""; $params = []; // สร้าง SQL UPDATE (เหมือนเดิม) ...
            foreach ($new_data as $key => $value) {
                $set_parts[] = "`$key` = ?";
                if (is_int($value)) { $types .= "i"; } else { $types .= "s"; }
                $params[] = ($value === null) ? null : $value;
            }

            if (!empty($set_parts)) {
                $sql_update = "UPDATE `".TABLE_TOPIC."` SET " . implode(", ", $set_parts) . " WHERE tp_id = ?";
                $types .= "i"; $params[] = $target_topic_id;
                $stmt_update = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, $types, ...$params);
                if (mysqli_stmt_execute($stmt_update)) {
                    $message = 'แก้ไขข้อมูล Topic สำเร็จ!'; $message_type = 'success';
                    if ($old_data) { // สร้าง Log (เหมือนเดิม) ...
                         $details_arr = [];
                         foreach ($new_data as $key => $new_val) {
                             if (isset($old_data[$key]) && $old_data[$key] != $new_val) { $old_val_display = $old_data[$key] ?? 'NULL'; $new_val_display = $new_val ?? 'NULL'; $details_arr[] = "$key: '{$old_val_display}' -> '$new_val_display'"; }
                             elseif (!isset($old_data[$key]) && $new_val !== null) { $details_arr[] = "$key: NULL -> '$new_val'"; }
                         }
                         $details = empty($details_arr) ? "กดแก้ไข ID: $target_topic_id (ไม่มีการเปลี่ยนแปลง)" : "แก้ไข ID: $target_topic_id. เปลี่ยน -> " . implode(", ", $details_arr);
                         create_log($conn, $admin_logging_id, 'UPDATE_TOPIC', $target_topic_id, $details);
                    }
                } else { $message = 'เกิดข้อผิดพลาดในการแก้ไข: ' . mysqli_error($conn); $message_type = 'danger'; }
                mysqli_stmt_close($stmt_update);
            } else { $message = 'ไม่มีข้อมูลให้อัปเดต'; $message_type = 'warning'; }
        }
        // [จัดการลบ]
        else if (isset($_POST['action']) && $_POST['action'] === 'delete_topic') {
            $target_topic_id = (int)$_POST['tp_id'];
            $topic_info = "ID: $target_topic_id"; // ดึงข้อมูลก่อนลบ (เหมือนเดิม) ...
            $sql_old_del = "SELECT tp_name FROM `".TABLE_TOPIC."` WHERE tp_id = ?";
            $stmt_old_del = mysqli_prepare($conn, $sql_old_del);
            mysqli_stmt_bind_param($stmt_old_del, "i", $target_topic_id);
            if (mysqli_stmt_execute($stmt_old_del)) { $result_old_del = mysqli_stmt_get_result($stmt_old_del); if ($old_data_del = mysqli_fetch_assoc($result_old_del)) { $topic_info = "ID: $target_topic_id, ชื่อ: '{$old_data_del['tp_name']}'"; } }
            mysqli_stmt_close($stmt_old_del);

            $sql_delete = "DELETE FROM `".TABLE_TOPIC."` WHERE tp_id = ?"; // ทำการ DELETE (เหมือนเดิม) ...
            $stmt_delete = mysqli_prepare($conn, $sql_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $target_topic_id);
            if (mysqli_stmt_execute($stmt_delete)) {
                $message = 'ลบข้อมูล Topic สำเร็จ!'; $message_type = 'success';
                create_log($conn, $admin_logging_id, 'DELETE_TOPIC', $target_topic_id, "ลบ Topic: " . $topic_info); // สร้าง Log
            } else { $message = 'เกิดข้อผิดพลาดในการลบ: ' . mysqli_error($conn); $message_type = 'danger'; }
            mysqli_stmt_close($stmt_delete);
        }
    }
}

// --- [ส่วนดึงข้อมูลแสดงผล - เหมือนเดิม] ---
$records_per_page = 10;
$current_page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) { $current_page = 1; }
$offset = ($current_page - 1) * $records_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "WHERE 1"; $params = []; $param_types = "";
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clause .= " AND (tp_name LIKE ? OR tp_detail LIKE ?)";
    array_push($params, $search_term, $search_term); $param_types .= "ss";
}
$sql_count = "SELECT COUNT(tp_id) as total FROM `".TABLE_TOPIC."` $where_clause"; // นับจำนวน (เหมือนเดิม) ...
$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($search_query)) { mysqli_stmt_bind_param($stmt_count, $param_types, ...$params); }
mysqli_stmt_execute($stmt_count); $result_count = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_assoc($result_count)['total'] ?? 0;
$total_pages = ceil($total_records / $records_per_page); mysqli_stmt_close($stmt_count);

$select_fields = implode(", ", array_map(function($c){ return "`$c`"; }, $cols)); // ดึงข้อมูล (เหมือนเดิม) ...
$topics = [];
$sql_select = "SELECT $select_fields FROM `".TABLE_TOPIC."` $where_clause ORDER BY tp_id ASC LIMIT ? OFFSET ?";
$stmt_select = mysqli_prepare($conn, $sql_select);
array_push($params, $records_per_page, $offset); $param_types .= "ii";
mysqli_stmt_bind_param($stmt_select, $param_types, ...$params); mysqli_stmt_execute($stmt_select);
$result = mysqli_stmt_get_result($stmt_select);
if ($result) { $topics = mysqli_fetch_all($result, MYSQLI_ASSOC); mysqli_free_result($result); }
else { $message = "เกิดข้อผิดพลาดดึงข้อมูล: " . mysqli_error($conn); $message_type = 'danger'; }
mysqli_stmt_close($stmt_select);
mysqli_close($conn);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    /* เพิ่ม CSS เล็กน้อยสำหรับ Preview Box */
    .preview-box {
        border: 1px dashed #ced4da; /* สีเทาอ่อน */
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem; /* ความโค้งมนเท่า input */
        background-color: #f8f9fa; /* สีพื้นหลังเทาอ่อนๆ */
        max-height: 100px;
        overflow-y: auto;
        font-size: 0.9em; /* ลดขนาดฟอนต์เล็กน้อย */
        line-height: 1.6;
    }
    .preview-box:empty::before { /* แสดงข้อความถ้าไม่มีข้อมูล */
        content: "(ไม่มีรายละเอียด)";
        color: #6c757d; /* สีเทา */
        font-style: italic;
    }
    .style-indicator { font-size: 1.1em; opacity: 0.7; } /* ไอคอน Style */
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
            <input class="form-control form-control-sm me-2" type="search" name="search" placeholder="ค้นหา..." value="<?= h($search_query) ?>" aria-label="Search">
            <button class="btn btn-sm btn-outline-primary" type="submit">
                <i class="bi bi-search"></i> ค้นหา
            </button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle caption-top">
                <caption>แสดง <?= count($topics) ?> รายการ จากทั้งหมด <?= $total_records ?> รายการ</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="text-center" style="width: 5%;">ID</th>
                        <th scope="col" style="width: 25%;">ชื่อหัวข้อ</th>
                        <th scope="col" style="width: 45%;">รายละเอียด (พรีวิว)</th>
                        <th scope="col" class="text-center" style="width: 5%;">Style</th>
                        <th scope="col" class="text-center" style="width: 20%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topics)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <?= !empty($search_query) ? "ไม่พบข้อมูลที่ตรงกับ '".h($search_query)."'" : "<i class='bi bi-info-circle me-2'></i>ไม่มีข้อมูลในตาราง" ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topics as $topic):
                            // สร้าง Style สำหรับ Preview
                            $style_preview = '';
                            if (!empty($topic['tp_color'])) $style_preview .= 'color:'.h($topic['tp_color']).';';
                            if (!empty($topic['tp_fontsize']) && $topic['tp_fontsize'] > 0) $style_preview .= 'font-size:'.(int)$topic['tp_fontsize'].'px;';
                            if (!empty($topic['tp_fontfamily'])) $style_preview .= 'font-family:'.h($topic['tp_fontfamily']).';';
                            if (!empty($topic['tp_bold'])) $style_preview .= 'font-weight:bold;';
                            if (!empty($topic['tp_italic'])) $style_preview .= 'font-style:italic;';
                            // เช็คว่ามี Style อะไรถูกตั้งค่าไว้บ้าง (ไม่นับค่า null หรือ 0)
                            $has_style = false;
                            foreach ($available_style_columns as $sc) {
                                if (!empty($topic[$sc]) && ($sc !== 'tp_bold' && $sc !== 'tp_italic' || $topic[$sc] == 1)) {
                                    $has_style = true; break;
                                }
                            }
                        ?>
                            <tr>
                                <td class="text-center fw-bold"><?= h($topic['tp_id']) ?></td>
                                <td><?= h($topic['tp_name']) ?></td>
                                <td>
                                    <div class="preview-box" style="<?= $style_preview ?>">
                                        <?= nl2br(h($topic['tp_detail'])) ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?= $has_style ? '<i class="bi bi-palette-fill style-indicator text-primary" title="มีการตั้งค่า Style"></i>' : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary edit-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editTopicModal"
                                                <?php foreach ($topic as $key => $value): ?>
                                                data-<?= h($key) ?>="<?= h($value ?? '', ENT_QUOTES) // ส่งค่าว่างถ้าเป็น NULL ?>"
                                                <?php endforeach; ?>>
                                            <i class="bi bi-pencil-square me-1"></i> แก้ไข
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-btn"
                                                data-id="<?= (int)$topic['tp_id'] ?>"
                                                data-name="<?= h($topic['tp_name'], ENT_QUOTES) ?>">
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
                // Logic การแสดงผลเลขหน้า (เหมือนเดิม)
                $range = 2; // จำนวนเลขหน้าก่อนและหลังหน้าปัจจุบัน
                $start = max(1, $current_page - $range);
                $end = min($total_pages, $current_page + $range);

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

    </div> </div> <div class="modal fade" id="editTopicModal" tabindex="-1" aria-labelledby="editTopicModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg border-0 rounded-3">
      <form id="editTopicForm" method="POST" action="admin.php?page=<?= h($page_key) ?>&search=<?= h(urlencode($search_query)) ?>&p=<?= $current_page ?>">
        <div class="modal-header bg-primary text-white">
          <h1 class="modal-title fs-5" id="editTopicModalLabel"><i class="bi bi-pencil-fill me-2"></i>แก้ไขข้อมูล Topic</h1>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
            <input type="hidden" name="action" value="update_topic">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" id="edit-tp_id" name="tp_id">

            <div class="mb-3">
                <label for="edit-tp_name" class="form-label">ชื่อหัวข้อ <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit-tp_name" name="tp_name" required>
            </div>
            <div class="mb-4">
                <label for="edit-tp_detail" class="form-label">รายละเอียด</label>
                <textarea class="form-control" id="edit-tp_detail" name="tp_detail" rows="5" placeholder="กรอกรายละเอียดเนื้อหา..."></textarea>
            </div>

            <hr class="my-4">
            <h6 class="mb-3 text-primary"><i class="bi bi-palette me-2"></i>ตั้งค่าการแสดงผล (Style)</h6>
            <div class="row g-3 align-items-center">
                <?php if (in_array('tp_color', $available_style_columns)): ?>
                <div class="col-md-3">
                    <label for="edit-tp_color" class="form-label">สีตัวอักษร</label>
                    <input type="color" class="form-control form-control-color w-100" id="edit-tp_color" name="tp_color" title="เลือกสี">
                </div>
                <?php endif; ?>

                <?php if (in_array('tp_fontsize', $available_style_columns)): ?>
                <div class="col-md-3">
                    <label for="edit-tp_fontsize" class="form-label">ขนาด (px)</label>
                    <input type="number" class="form-control" id="edit-tp_fontsize" name="tp_fontsize" min="1" placeholder="(ค่าเริ่มต้น)">
                </div>
                <?php endif; ?>

                <?php if (in_array('tp_fontfamily', $available_style_columns)): ?>
                <div class="col-md-6">
                    <label for="edit-tp_fontfamily" class="form-label">ฟอนต์ (CSS)</label>
                    <input type="text" class="form-control" id="edit-tp_fontfamily" name="tp_fontfamily" placeholder="เช่น 'Sarabun', sans-serif">
                </div>
                <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap gap-4 mt-4">
                 <?php if (in_array('tp_bold', $available_style_columns)): ?>
                <div class="form-check form-switch fs-6">
                    <input class="form-check-input" type="checkbox" role="switch" id="edit-tp_bold" name="tp_bold" value="1">
                    <label class="form-check-label" for="edit-tp_bold"><i class="bi bi-type-bold me-1"></i> ตัวหนา</label>
                </div>
                <?php endif; ?>

                <?php if (in_array('tp_italic', $available_style_columns)): ?>
                <div class="form-check form-switch fs-6">
                    <input class="form-check-input" type="checkbox" role="switch" id="edit-tp_italic" name="tp_italic" value="1">
                    <label class="form-check-label" for="edit-tp_italic"><i class="bi bi-type-italic me-1"></i> ตัวเอียง</label>
                </div>
                <?php endif; ?>
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

<form id="deleteTopicForm" method="POST" action="admin.php?page=<?= h($page_key) ?>&search=<?= h(urlencode($search_query)) ?>&p=<?= $current_page ?>" style="display: none;">
    <input type="hidden" name="action" value="delete_topic">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" id="delete-tp_id" name="tp_id"> </form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- จัดการ Modal แก้ไข (เหมือนเดิม) ---
    const editTopicModal = document.getElementById('editTopicModal');
    if (editTopicModal) {
        editTopicModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const modalForm = editTopicModal.querySelector('form');
            const data = button.dataset; // ใช้ dataset สะดวกกว่า

            // วนลูปใส่ค่าในฟอร์ม (ปรับปรุงให้รองรับ data-* แบบ auto)
            for (const key in data) {
                // แปลง key จาก data-tp_name เป็น edit-tp_name
                const inputId = 'edit-' + key.replace(/([A-Z])/g, '_$1').toLowerCase();
                const input = modalForm.querySelector(`#${inputId}`);
                if (input) {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                         input.checked = (data[key] == '1');
                    } else if (input.type === 'color') {
                        input.value = data[key] || '#000000';
                    } else {
                        input.value = data[key] ?? ''; // ใส่ค่าว่างถ้าเป็น null
                    }
                }
            }
            // จัดการ id แยกต่างหาก (เพราะ data-tp-id อาจกลายเป็น tpId)
             modalForm.querySelector('#edit-tp_id').value = button.getAttribute('data-tp_id') || '';
        });
    }

    // --- ยืนยันก่อนแก้ไข (เหมือนเดิม) ---
    const editForm = document.getElementById('editTopicForm');
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
            const topicId = this.getAttribute('data-id');
            const topicName = this.getAttribute('data-name');
            Swal.fire({
                title: 'ยืนยันการลบ', html: `ต้องการลบ <strong>${topicName}</strong> (ID: ${topicId}) ใช่หรือไม่?<br><strong class='text-danger'>ข้อมูลจะถูกลบถาวร!</strong>`, icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3-fill me-1"></i> ใช่, ลบเลย', cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-tp_id').value = topicId; // แก้ id ให้ตรง
                    document.getElementById('deleteTopicForm').submit();
                }
            });
        });
    });

    // --- ทำให้ Alert หายไปเอง (เหมือนเดิม) ---
    const alertMessage = document.querySelector('.alert.alert-dismissible');
    if(alertMessage) { setTimeout(() => { bootstrap.Alert.getOrCreateInstance(alertMessage).close(); }, 5000); }
});
</script>