<?php
// ไฟล์นี้สำหรับ AJAX เท่านั้น

// 1. Start session, connect DB
session_start();
require_once __DIR__ . '/connectdb.php';
// [เพิ่ม] ตรวจสอบ Connection
if (!$conn) {
     // ถ้าเชื่อมต่อไม่ได้ ส่ง JSON error กลับไป
     header('Content-Type: application/json');
     echo json_encode([
         'error' => 'Database connection failed: ' . mysqli_connect_error(),
         'table_html' => '<tr><td colspan="5" class="text-center text-danger py-5">เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล</td></tr>',
         'pagination_html' => '',
         'total_text' => '<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> เกิดข้อผิดพลาด'
     ]);
     exit;
}


// ⭐️⭐️⭐️ [เพิ่ม] คัดลอกฟังก์ชัน formatLogEntry (ฉบับปรับปรุง) มาวางตรงนี้ ⭐️⭐️⭐️
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


// --- (โค้ดส่วนที่เหลือเหมือนเดิม ตั้งแต่รับค่า GET จนถึงสร้าง JSON response) ---
// 2. รับค่า GET (เหมือนเดิม)
$period = $_GET['period'] ?? 'all'; $filter_admin_id = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : ''; $current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1; if ($current_page < 1) $current_page = 1; $records_per_page = 15;
// 3. สร้าง SQL (เหมือนเดิม)
$sql_base_from = " FROM admin_logs l JOIN users admin ON l.admin_user_id = admin.user_id LEFT JOIN users target ON l.target_user_id = target.user_id"; $sql_where = ""; $whereClauses = []; $params = []; $types = ""; if ($period == 'hour') $whereClauses[] = "l.log_timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"; elseif ($period == 'today') $whereClauses[] = "l.log_timestamp >= CURDATE()"; elseif ($period == 'week') $whereClauses[] = "l.log_timestamp >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)"; elseif ($period == 'month') $whereClauses[] = "l.log_timestamp >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"; elseif ($period == 'year') $whereClauses[] = "l.log_timestamp >= DATE_FORMAT(CURDATE(), '%Y-01-01')"; if (!empty($filter_admin_id)) { $whereClauses[] = "l.admin_user_id = ?"; $params[] = $filter_admin_id; $types .= "i"; } if (!empty($whereClauses)) { $sql_where = " WHERE " . implode(" AND ", $whereClauses); }
// 4. Query 1: Count total (เหมือนเดิม)
$total_records = 0; $total_pages = 1; $sql_count = "SELECT COUNT(l.log_id) as total " . $sql_base_from . $sql_where; $stmt_count = mysqli_prepare($conn, $sql_count); if ($stmt_count) { /* ... bind, execute, fetch ... */ if (!empty($params)) mysqli_stmt_bind_param($stmt_count, $types, ...$params); if (mysqli_stmt_execute($stmt_count)){ $result_count = mysqli_stmt_get_result($stmt_count); $row_count = mysqli_fetch_assoc($result_count); if($row_count && isset($row_count['total'])) $total_records = (int)$row_count['total']; } mysqli_stmt_close($stmt_count); } if ($total_records > 0) $total_pages = ceil($total_records / $records_per_page); if ($current_page > $total_pages) $current_page = $total_pages; $offset = ($current_page - 1) * $records_per_page;
// 5. Query 2: Fetch logs (เหมือนเดิม)
$logs = []; $sql_select = "SELECT l.log_id, l.action_type, l.details, l.log_timestamp, l.target_user_id, admin.first_name as admin_fname, admin.last_name as admin_lname, target.first_name as target_fname, target.last_name as target_lname" . $sql_base_from . $sql_where . " ORDER BY l.log_timestamp DESC LIMIT ? OFFSET ?"; $params_data = $params; $types_data = $types; $params_data[] = $records_per_page; $params_data[] = $offset; $types_data .= "ii"; $stmt_data = mysqli_prepare($conn, $sql_select); if ($stmt_data) { /* ... bind, execute, fetch ... */ if (!empty($params_data)) mysqli_stmt_bind_param($stmt_data, $types_data, ...$params_data); if(mysqli_stmt_execute($stmt_data)) { $result = mysqli_stmt_get_result($stmt_data); $logs = mysqli_fetch_all($result, MYSQLI_ASSOC); } mysqli_stmt_close($stmt_data); } mysqli_close($conn);

// 6. Build response JSON
$response = [];

// --- ⭐️⭐️⭐️ [แก้ไข] สร้าง HTML ส่วนตาราง <tbody> โดยใช้ฟังก์ชันใหม่ ⭐️⭐️⭐️ ---
$table_html = "";
if (empty($logs)) {
    $table_html = '<tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-info-circle fs-1"></i><h4 class="mt-2">ไม่พบข้อมูล</h4><p class="mb-0">ไม่พบ Log ที่ตรงกับเงื่อนไขที่เลือก</p></td></tr>';
} else {
    foreach ($logs as $log) {
        $formatted = formatLogEntry($log); // <-- เรียกใช้ฟังก์ชันใหม่
        $admin_name = trim(htmlspecialchars($log['admin_fname'] . ' ' . $log['admin_lname']));

        $table_html .= '<tr>';
        $table_html .= '<td><small class="text-muted">' . date('d M Y', strtotime($log['log_timestamp'])) . '</small><br><strong>' . date('H:i:s', strtotime($log['log_timestamp'])) . '</strong></td>';
        $table_html .= '<td><i class="bi bi-person-circle text-primary me-1"></i> ' . $admin_name . '</td>';
        $table_html .= '<td style="vertical-align: top;">' . $formatted['action'] . '</td>'; // <-- ใช้ค่าจากฟังก์ชัน
        $table_html .= '<td style="vertical-align: top;">' . $formatted['target'] . '</td>'; // <-- ใช้ค่าจากฟังก์ชัน
        $table_html .= '<td style="vertical-align: top;">' . $formatted['details'] . '</td>'; // <-- ใช้ค่าจากฟังก์ชัน
        $table_html .= '</tr>';
    }
}
$response['table_html'] = $table_html;
// ⭐️⭐️⭐️ --- จบส่วนแก้ไขตาราง --- ⭐️⭐️⭐️


// --- สร้าง HTML ส่วน Pagination (เหมือนเดิม) ---
$filter_query_string_ajax = ""; if (!empty($period) && $period !== 'all') $filter_query_string_ajax .= "&period=" . urlencode($period); if (!empty($filter_admin_id)) $filter_query_string_ajax .= "&admin_id=" . urlencode($filter_admin_id); $pagination_html = ""; if ($total_pages > 1) { /* ... โค้ดสร้าง pagination HTML ... */ $base_url = "ajax_get_logs.php?p="; $pagination_html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mb-0">'; $prev_page = $current_page - 1; $pagination_html .= '<li class="page-item ' . ($current_page <= 1 ? 'disabled' : '') . '">'; $pagination_html .= '<a class="page-link" href="' . $base_url . $prev_page . $filter_query_string_ajax . '"><i class="bi bi-chevron-left"></i></a>'; $pagination_html .= '</li>'; $start_page = max(1, $current_page - 2); $end_page = min($total_pages, $current_page + 2); if ($start_page > 1) { $pagination_html .= '<li class="page-item"><a class="page-link" href="'.$base_url.'1'.$filter_query_string_ajax.'">1</a></li>'; if ($start_page > 2) { $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>'; } } for ($i = $start_page; $i <= $end_page; $i++){ $pagination_html .= '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">'; $pagination_html .= '<a class="page-link" href="' . $base_url . $i . $filter_query_string_ajax . '">' . $i . '</a>'; $pagination_html .= '</li>'; } if ($end_page < $total_pages) { if ($end_page < $total_pages - 1) { $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>'; } $pagination_html .= '<li class="page-item"><a class="page-link" href="'.$base_url.$total_pages.$filter_query_string_ajax.'">'.$total_pages.'</a></li>'; } $next_page = $current_page + 1; $pagination_html .= '<li class="page-item ' . ($current_page >= $total_pages ? 'disabled' : '') . '">'; $pagination_html .= '<a class="page-link" href="' . $base_url . $next_page . $filter_query_string_ajax . '"><i class="bi bi-chevron-right"></i></a>'; $pagination_html .= '</li>'; $pagination_html .= '</ul></nav>'; }
$response['pagination_html'] = $pagination_html;

// --- สร้าง Text ส่วนหัวข้อ (เหมือนเดิม) ---
$response['total_text'] = '<i class="bi bi-list-task me-2"></i> รายการ Log (พบ ' . $total_records . ' รายการ)';

// 7. Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>