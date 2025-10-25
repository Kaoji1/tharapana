<?php
// 1. Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 2. Check embed
if (!defined('ADMIN_EMBED')) {
    exit('Direct access denied');
}
// 3. Connect DB
require_once __DIR__ . '/connectdb.php';
// [เพิ่ม] ตรวจสอบ Connection
if (!$conn) {
     die("<div class='container-fluid px-4'><div class='alert alert-danger mt-4'>Database connection failed: " . mysqli_connect_error() . "</div></div>");
}

// ⭐️⭐️⭐️ [ฟังก์ชันใหม่] จัดรูปแบบ Log (ฉบับปรับปรุงหน้าตา) ⭐️⭐️⭐️
function formatLogEntry($log) {
    $action_html = '';
    // [ปรับปรุง] ใช้ small + text-muted เป็น default และเพิ่ม N/A
    $details_html = '<small class="text-muted">' . htmlspecialchars($log['details'] ?? 'N/A') . '</small>';
    $target_name_html = '';
    $target_icon = 'bi-person'; // Default icon

    // --- Format Target Name (ทำก่อน เผื่อใช้ใน Details) ---
    $target_name = trim(htmlspecialchars(($log['target_fname'] ?? '') . ' ' . ($log['target_lname'] ?? '')));
    $target_id_display = $log['target_user_id'] ?? 0; // ID ที่จะแสดง

    if (empty($target_name)) {
        if ($target_id_display == 0) {
            $target_name_html = "<span class='text-muted'>(System/Guest)</span>";
            $target_icon = 'bi-hdd-stack'; // System icon
        } else {
             // ถ้า target_id ไม่ใช่ 0 แต่ไม่มีชื่อ อาจจะเป็น User ที่ถูกลบ หรือ ID ของอย่างอื่น
             $target_name_html = "<span class='text-muted fst-italic'>(ID: {$target_id_display})</span>";
             // ลองเดาไอคอนจาก Action
             if(str_contains($log['action_type'], 'REVIEW')) $target_icon = 'bi-chat-quote'; // Review icon
        }
    } else {
        // มีชื่อ Target
        $target_name_html = htmlspecialchars($target_name);
         // ลองเดาไอคอนจาก Action
        if(str_contains($log['action_type'], 'ADMIN')) $target_icon = 'bi-shield-lock'; // Admin icon
        else if(str_contains($log['action_type'], 'USER')) $target_icon = 'bi-person'; // User icon
    }
    // [ปรับปรุง] รวม icon และชื่อ
    $target_html = '<i class="bi ' . $target_icon . ' text-secondary me-1"></i> ' . $target_name_html;

    // --- Format Action and Details ---
    switch ($log['action_type']) {
        case 'CREATE_ADMIN':
        case 'CREATE_USER': // เพิ่มเผื่อไว้
            $is_admin = ($log['action_type'] === 'CREATE_ADMIN');
            // [ปรับปรุง] เพิ่ม fw-semibold
            $action_html = '<span class="text-success fw-semibold"><i class="bi bi-' . ($is_admin ? 'shield-plus' : 'person-plus-fill') . ' me-1"></i> เพิ่ม' . ($is_admin ? 'ผู้ดูแล' : 'สมาชิก') . '</span>';
            // Example: "สร้าง Admin ใหม่: ID: 7, ชื่อ: 'สุทธิชัย ตั้งธงชัย', อีเมล: 'Goodnight15land@gmail.com'"
            // [ปรับปรุง] แยกชื่อและอีเมลให้อ่านง่าย
            if (preg_match("/ID: (\d+), ชื่อ: '(.*?)'(?:, อีเมล: '(.*?)')?/", $log['details'], $matches)) {
                 $details_html = "<strong>" . htmlspecialchars($matches[2]) . "</strong>"; // ชื่อตัวหนา
                 if (!empty($matches[3])) {
                     $details_html .= "<br><small class='text-muted'><i class='bi bi-envelope me-1'></i>" . htmlspecialchars($matches[3]) . "</small>"; // อีเมลเล็ก + icon
                 }
                 // [ปรับปรุง] ถ้า Target แสดงแค่ ID ตอนแรก ให้ update เป็นชื่อเลย
                 if (str_contains($target_name_html, 'ID:')) {
                      $target_html = '<i class="bi ' . ($is_admin ? 'bi-shield-lock' : 'bi-person') . ' text-secondary me-1"></i> ' . htmlspecialchars($matches[2]);
                 }
            } else {
                 $details_html = "<small class='text-muted'>เพิ่มข้อมูล ID: {$target_id_display}</small>"; // Fallback
            }
            break;

        case 'UPDATE_ADMIN':
        case 'UPDATE_USER':
            $is_admin = ($log['action_type'] === 'UPDATE_ADMIN');
             // [ปรับปรุง] เพิ่ม fw-semibold
            $action_html = '<span class="text-primary fw-semibold"><i class="bi bi-' . ($is_admin ? 'person-gear' : 'pencil-square') . ' me-1"></i> แก้ไข' . ($is_admin ? 'ผู้ดูแล' : 'สมาชิก') . '</span>';
            // Example: "แก้ไข ID: 2. เปลี่ยน -> ชื่อ: 'SSSS' -> 'SSSS2', นามสกุล: ..." OR "กดแก้ไข ID: 2 แต่ไม่มีการเปลี่ยนแปลงข้อมูล"
            if (str_contains($log['details'], "ไม่มีการเปลี่ยนแปลง")) {
                 // [ปรับปรุง] ใช้ fst-italic ชัดเจน
                 $details_html = "<small class='text-muted fst-italic'>(ไม่มีการเปลี่ยนแปลงข้อมูล)</small>";
            } elseif (preg_match("/แก้ไข ID: \d+\. (.*)/", $log['details'], $matches)) {
                $changes = $matches[1];
                 // [ปรับปรุง] ใช้ list + icon + style ให้ดูง่าย
                $change_parts = explode(", ", str_replace("เปลี่ยน -> ", "", $changes));
                $details_html = "<ul class='list-unstyled mb-0 small ps-3'>"; // Use padding start for indent
                foreach($change_parts as $part) {
                    if (preg_match("/(.*?): '(.*?)' -> '(.*?)'/", $part, $change_match)) {
                        $field = htmlspecialchars(trim($change_match[1]));
                        $old = htmlspecialchars(trim($change_match[2]));
                        $new = htmlspecialchars(trim($change_match[3]));
                        // ทำให้ดูง่ายขึ้น
                        $field_map = ['ชื่อ' => 'ชื่อจริง', 'นามสกุล' => 'นามสกุล', 'อีเมล' => 'อีเมล', 'เบอร์โทร' => 'เบอร์โทร'];
                        $field_display = $field_map[$field] ?? $field; // ใช้ชื่อที่อ่านง่าย ถ้ามี
                        // [ปรับปรุง] แสดงผลการเปลี่ยนแปลงชัดเจน
                         $details_html .= "<li class='mb-1'><i class='bi bi-dot'></i> <strong>{$field_display}:</strong> <span class='text-decoration-line-through text-muted me-1'>{$old}</span> <i class='bi bi-arrow-right-short'></i> <span class='text-primary fw-semibold'>{$new}</span></li>";
                    }
                }
                $details_html .= "</ul>";
            }
            break;

        case 'DELETE_ADMIN':
        case 'DELETE_USER':
             $is_admin = ($log['action_type'] === 'DELETE_ADMIN');
              // [ปรับปรุง] เพิ่ม fw-semibold และเปลี่ยน icon user
             $action_html = '<span class="text-danger fw-semibold"><i class="bi bi-' . ($is_admin ? 'shield-x' : 'person-x-fill') . ' me-1"></i> ลบ' . ($is_admin ? 'ผู้ดูแล' : 'สมาชิก') . '</span>';
             // Example: "ลบ Admin: ID: 7, ชื่อ: 'สุทธิชัย ตั้งธงชัย', อีเมล: 'Goodnight15land@gmail.com'"
             // [ปรับปรุง] แยกชื่อและอีเมล
             if (preg_match("/(?:Admin|ผู้ใช้): ID: (\d+), ชื่อ: '(.*?)'(?:, อีเมล: '(.*?)')?/", $log['details'], $matches)) {
                 $details_html = "<strong>" . htmlspecialchars($matches[2]) . "</strong>";
                 if (!empty($matches[3])) {
                     $details_html .= "<br><small class='text-muted'><i class='bi bi-envelope me-1'></i>" . htmlspecialchars($matches[3]) . "</small>";
                 }
                  // [ปรับปรุง] ถ้า Target แสดงแค่ ID ตอนแรก ให้ update เป็นชื่อเลย
                 if (str_contains($target_name_html, 'ID:')) {
                      $target_html = '<i class="bi ' . ($is_admin ? 'bi-shield-lock' : 'bi-person') . ' text-secondary me-1"></i> ' . htmlspecialchars($matches[2]);
                 }
             } else {
                  $details_html = "<small class='text-muted'>ลบข้อมูล ID: {$target_id_display}</small>"; // Fallback
             }
            break;

        case 'REPLY_REVIEW':
             // [ปรับปรุง] เพิ่ม fw-semibold
             $action_html = '<span class="text-info fw-semibold"><i class="bi bi-reply-fill me-1"></i> ตอบรีวิว</span>';
             // Example: "ตอบกลับรีวิว ID: 5. ข้อความ: "ขอบคุณสำหรับรีวิวครับ...""
             // [ปรับปรุง] ใช้ tooltip แสดงข้อความเต็ม + ปรับ Target
             if (preg_match("/ตอบกลับรีวิว ID: (\d+)\. ข้อความ: \"(.*?)\"?$/s", $log['details'], $matches)) { // Added 's' modifier for multiline
                 $review_id = $matches[1];
                 $reply_text = htmlspecialchars($matches[2]);
                 // [ปรับปรุง] ใช้ tooltip และจำกัดความยาวเริ่มต้น
                 $tooltip_text = str_replace('"', '&quot;', $reply_text); // Escape quotes for HTML attribute
                 $truncated_text = mb_substr($reply_text, 0, 80); // Show first 80 chars
                 if (mb_strlen($reply_text) > 80) $truncated_text .= '...';

                 $details_html = "<span data-bs-toggle='tooltip' data-bs-placement='top' title=\"{$tooltip_text}\">"
                                . "<small class='text-muted fst-italic'>\"{$truncated_text}\"</small>"
                                . "</span>";

                 // Update target to show review icon and ID
                 $target_html = '<i class="bi bi-chat-quote text-secondary me-1"></i> <span class="text-muted fst-italic">(รีวิว ID: ' . $review_id . ')</span>';

             } else {
                 $details_html = "<small class='text-muted'>ตอบกลับรีวิว (ไม่ทราบ ID)</small>"; // Fallback
             }
            break;

        default: // ถ้าไม่ตรงกับ Case ไหนเลย
             $action_html = '<span class="text-secondary"><i class="bi bi-gear me-1"></i> ' . htmlspecialchars($log['action_type']) . '</span>';
             // Details ใช้ค่า Default ที่ตั้งไว้ตอนแรก
            break;
    }

    // --- ตรวจสอบค่า HTML เผื่อกรณี preg_match ไม่สำเร็จ ---
    if(empty($action_html)) {
         $action_html = '<span class="text-secondary"><i class="bi bi-gear me-1"></i> ' . htmlspecialchars($log['action_type']) . '</span>';
    }
     // Don't overwrite if details were intentionally set to empty (like no changes)
     if(empty($details_html) && !str_contains($log['details'] ?? '', 'ไม่มีการเปลี่ยนแปลง')) {
         $details_html = '<small class="text-muted">' . htmlspecialchars($log['details'] ?? 'N/A') . '</small>';
     }

    return [
        'action' => $action_html,
        'target' => $target_html,
        'details' => $details_html,
    ];
}
// ⭐️⭐️⭐️ --- จบฟังก์ชัน formatLogEntry (ฉบับปรับปรุง) --- ⭐️⭐️⭐️


// --- (โค้ด PHP ส่วนที่เหลือเหมือนเดิม ตั้งแต่ Fetch Admin Users จนถึง ปิด Connection) ---
// ... (Fetch Admin Users) ...
$admins_list = []; $sql_admins = "SELECT user_id, first_name, last_name FROM users WHERE role = 'admin' ORDER BY first_name ASC, last_name ASC"; $result_admins = mysqli_query($conn, $sql_admins); if ($result_admins) { $admins_list = mysqli_fetch_all($result_admins, MYSQLI_ASSOC); }
// ... (Handle filters for initial load) ...
$period = isset($_GET['period']) ? $_GET['period'] : 'all'; $filter_admin_id = isset($_GET['admin_id']) && is_numeric($_GET['admin_id']) ? (int)$_GET['admin_id'] : ''; $current_page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1; if ($current_page < 1) $current_page = 1; $records_per_page = 15;
// ... (Build SQL for initial load) ...
$sql_base_from = " FROM admin_logs l JOIN users admin ON l.admin_user_id = admin.user_id LEFT JOIN users target ON l.target_user_id = target.user_id"; $sql_where = ""; $whereClauses = []; $params = []; $types = ""; if ($period == 'hour') $whereClauses[] = "l.log_timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"; elseif ($period == 'today') $whereClauses[] = "l.log_timestamp >= CURDATE()"; elseif ($period == 'week') $whereClauses[] = "l.log_timestamp >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)"; elseif ($period == 'month') $whereClauses[] = "l.log_timestamp >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"; elseif ($period == 'year') $whereClauses[] = "l.log_timestamp >= DATE_FORMAT(CURDATE(), '%Y-01-01')"; if (!empty($filter_admin_id)) { $whereClauses[] = "l.admin_user_id = ?"; $params[] = $filter_admin_id; $types .= "i"; } if (!empty($whereClauses)) { $sql_where = " WHERE " . implode(" AND ", $whereClauses); }
// ... (Count total records) ...
$total_records = 0; $total_pages = 1; $sql_count = "SELECT COUNT(l.log_id) as total " . $sql_base_from . $sql_where; $stmt_count = mysqli_prepare($conn, $sql_count); if ($stmt_count) { if (!empty($params)) mysqli_stmt_bind_param($stmt_count, $types, ...$params); if (mysqli_stmt_execute($stmt_count)) { $result_count = mysqli_stmt_get_result($stmt_count); $row_count = mysqli_fetch_assoc($result_count); if ($row_count && isset($row_count['total'])) { $total_records = (int)$row_count['total']; } } else { echo "<div class='container-fluid px-4'><div class='alert alert-warning mt-4'>Error counting records: " . mysqli_stmt_error($stmt_count) . "</div></div>"; } mysqli_stmt_close($stmt_count); } else { echo "<div class='container-fluid px-4'><div class='alert alert-warning mt-4'>Error preparing count query: " . mysqli_error($conn) . "</div></div>"; } if ($total_records > 0) { $total_pages = ceil($total_records / $records_per_page); } if ($current_page > $total_pages) { $current_page = $total_pages; } $offset = ($current_page - 1) * $records_per_page;
// ... (Fetch logs) ...
$logs = []; $sql_select = "SELECT l.log_id, l.action_type, l.details, l.log_timestamp, l.target_user_id, admin.first_name as admin_fname, admin.last_name as admin_lname, target.first_name as target_fname, target.last_name as target_lname" . $sql_base_from . $sql_where . " ORDER BY l.log_timestamp DESC LIMIT ? OFFSET ?"; $params_data = $params; $types_data = $types; $params_data[] = $records_per_page; $params_data[] = $offset; $types_data .= "ii"; $stmt_data = mysqli_prepare($conn, $sql_select); if ($stmt_data) { if (!empty($params_data)) mysqli_stmt_bind_param($stmt_data, $types_data, ...$params_data); if (mysqli_stmt_execute($stmt_data)) { $result = mysqli_stmt_get_result($stmt_data); $logs = mysqli_fetch_all($result, MYSQLI_ASSOC); } else { echo "<div class='container-fluid px-4'><div class='alert alert-warning mt-4'>Error fetching logs: " . mysqli_stmt_error($stmt_data) . "</div></div>"; } mysqli_stmt_close($stmt_data); } else { echo "<div class='container-fluid px-4'><div class='alert alert-warning mt-4'>Error preparing log query: " . mysqli_error($conn) . "</div></div>"; }
// ... (Close connection) ...
mysqli_close($conn);
// ... (Build query string) ...
$filter_query_string = ""; if (!empty($period) && $period !== 'all') $filter_query_string .= "&period=" . urlencode($period); if (!empty($filter_admin_id)) $filter_query_string .= "&admin_id=" . urlencode($filter_admin_id);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<div class="container-fluid px-4">
    <h1 class="mt-4">ประวัติการใช้งานระบบ (Logs)</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">ตรวจสอบการกระทำย้อนหลังของผู้ดูแลระบบ</li>
    </ol>

    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-filter-circle-fill me-2"></i>ตัวกรองข้อมูล</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-6 col-md-6">
                    <label for="adminFilterSelect" class="form-label">แสดง Log ของ:</label>
                    <select id="adminFilterSelect" name="admin_id" class="form-select">
                        <option value="">-- แอดมินทั้งหมด --</option>
                        <?php foreach ($admins_list as $admin_user): ?>
                            <option value="<?= $admin_user['user_id'] ?>" <?= ($filter_admin_id == $admin_user['user_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin_user['first_name'] . ' ' . $admin_user['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-6 col-md-6">
                    <label for="period" class="form-label">ช่วงเวลา:</label>
                    <select id="period" name="period" class="form-select">
                        <option value="all" <?= ($period == 'all') ? 'selected' : '' ?>>ทั้งหมด</option>
                        <option value="hour" <?= ($period == 'hour') ? 'selected' : '' ?>>ชั่วโมงที่ผ่านมา</option>
                        <option value="today" <?= ($period == 'today') ? 'selected' : '' ?>>วันนี้</option>
                        <option value="week" <?= ($period == 'week') ? 'selected' : '' ?>>สัปดาห์นี้</option>
                        <option value="month" <?= ($period == 'month') ? 'selected' : '' ?>>เดือนนี้</option>
                        <option value="year" <?= ($period == 'year') ? 'selected' : '' ?>>ปีนี้</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0" id="log-total-count">
                <i class="bi bi-list-task me-2"></i>
                รายการ Log (พบ <?= $total_records ?> รายการ)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col" style="width: 15%;">เวลา</th>
                            <th scope="col" style="width: 15%;">ผู้กระทำ</th>
                            <th scope="col" style="width: 15%;">การกระทำ</th>
                            <th scope="col" style="width: 15%;">เป้าหมาย</th>
                            <th scope="col">รายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody id="log-table-body">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-info-circle fs-1"></i><h4 class="mt-2">ไม่พบข้อมูล</h4><p class="mb-0">ไม่พบ Log ที่ตรงกับเงื่อนไขที่เลือก</p></td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    // ⭐️⭐️⭐️ [แก้ไข] เรียกใช้ฟังก์ชันใหม่ ⭐️⭐️⭐️
                                    $formatted = formatLogEntry($log);
                                    $admin_name = trim(htmlspecialchars($log['admin_fname'] . ' ' . $log['admin_lname']));
                                ?>
                                <tr>
                                    <td><small class="text-muted"><?= date('d M Y', strtotime($log['log_timestamp'])) ?></small><br><strong><?= date('H:i:s', strtotime($log['log_timestamp'])) ?></strong></td>
                                    <td><i class="bi bi-person-circle text-primary me-1"></i> <?= $admin_name ?></td>
                                    <td style="vertical-align: top;"><?= $formatted['action'] // ใช้ค่าจากฟังก์ชัน ?></td>
                                    <td style="vertical-align: top;"><?= $formatted['target'] // ใช้ค่าจากฟังก์ชัน ?></td>
                                     <td style="vertical-align: top;"><?= $formatted['details'] // ใช้ค่าจากฟังก์ชัน ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer" id="log-pagination-wrapper">
                 <?php if ($total_pages > 1): ?>
                 <nav aria-label="Page navigation">
                     <ul class="pagination justify-content-center mb-0">
                         <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>"> <a class="page-link" href="ajax_get_logs.php?page=logging&p=<?= $current_page - 1 ?><?= $filter_query_string ?>"> <i class="bi bi-chevron-left"></i> </a> </li>
                         <?php $start_page = max(1, $current_page - 2); $end_page = min($total_pages, $current_page + 2); if ($start_page > 1) { echo '<li class="page-item"><a class="page-link" href="ajax_get_logs.php?page=logging&p=1'.$filter_query_string.'">1</a></li>'; if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } } for ($i = $start_page; $i <= $end_page; $i++): ?> <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>"> <a class="page-link" href="ajax_get_logs.php?page=logging&p=<?= $i ?><?= $filter_query_string ?>"> <?= $i ?> </a> </li> <?php endfor; if ($end_page < $total_pages) { if ($end_page < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } echo '<li class="page-item"><a class="page-link" href="ajax_get_logs.php?page=logging&p='.$total_pages.$filter_query_string.'">'.$total_pages.'</a></li>'; } ?>
                         <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>"> <a class="page-link" href="ajax_get_logs.php?page=logging&p=<?= $current_page + 1 ?><?= $filter_query_string ?>"> <i class="bi bi-chevron-right"></i> </a> </li>
                     </ul>
                 </nav>
                 <?php endif; ?>
             </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // 1. Find elements (เหมือนเดิม)
    const periodSelect = document.getElementById('period');
    const adminFilterSelect = document.getElementById('adminFilterSelect');
    const tableBody = document.getElementById('log-table-body');
    const paginationWrapper = document.getElementById('log-pagination-wrapper');
    const totalCount = document.getElementById('log-total-count');

    // ⭐️⭐️⭐️ [เพิ่ม] ฟังก์ชันเปิดใช้งาน Tooltips ⭐️⭐️⭐️
    function initializeTooltips() {
         // ทำลาย Tooltip เก่าก่อน (ถ้ามี) - สำคัญมากสำหรับ AJAX
         document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
             const instance = bootstrap.Tooltip.getInstance(el);
             if (instance) {
                 instance.dispose();
             }
         });

        // สร้าง Tooltip ใหม่
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
             return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }


    // 2. Main fetch function (แก้ไข ให้เรียก init Tooltip)
    function fetchLogs(url) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
             })
            .then(data => {
                if(data.error) { // Check if PHP sent an error message
                    console.error('Error from server:', data.error);
                    tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-5">เกิดข้อผิดพลาด: ${data.error}</td></tr>`;
                    paginationWrapper.innerHTML = ''; // Clear pagination on error
                    totalCount.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> เกิดข้อผิดพลาด';

                } else {
                    tableBody.innerHTML = data.table_html;
                    paginationWrapper.innerHTML = data.pagination_html;
                    totalCount.innerHTML = data.total_text;
                    initializeTooltips(); // ⭐️⭐️⭐️ เรียกใช้หลังจากวาดตารางใหม่ ⭐️⭐️⭐️
                }
            })
            .catch(error => {
                console.error('Error fetching logs:', error);
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-5">เกิดข้อผิดพลาดในการโหลดข้อมูล (Network or JSON error)</td></tr>';
                paginationWrapper.innerHTML = ''; // Clear pagination on error
                totalCount.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> เกิดข้อผิดพลาด';
            });
    }

    // 3. Function to handle filter changes (เหมือนเดิม)
    function handleFilterChange() {
        const selectedPeriod = periodSelect.value;
        const selectedAdminId = adminFilterSelect.value;
        let newUrl = `ajax_get_logs.php?p=1&period=${selectedPeriod}`;
        if (selectedAdminId) { newUrl += `&admin_id=${selectedAdminId}`; }
        fetchLogs(newUrl);
    }

    // 4. Attach event listeners (เหมือนเดิม)
    if (periodSelect) { periodSelect.addEventListener('change', handleFilterChange); }
    if (adminFilterSelect) { adminFilterSelect.addEventListener('change', handleFilterChange); }

    // 5. Handle pagination clicks (เหมือนเดิม)
    document.body.addEventListener('click', function(event) {
        const target = event.target.closest('#log-pagination-wrapper .page-link');
        if (target) {
            event.preventDefault();
            const url = target.getAttribute('href');
            if (url) { fetchLogs(url); }
        }
    });

    // ⭐️⭐️⭐️ [เพิ่ม] เรียกใช้ Tooltip ครั้งแรกตอนโหลดหน้า ⭐️⭐️⭐️
    initializeTooltips();
});
</script>