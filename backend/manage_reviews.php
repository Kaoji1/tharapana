<?php
// 1. เริ่ม session (หากยังไม่ได้เริ่ม)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าไฟล์นี้ถูกเรียกผ่าน admin.php ไม่ได้ถูกเข้าถึงโดยตรง
if (!defined('ADMIN_EMBED')) {
    exit('Direct access denied');
}

// เรียกใช้ connectdb.php
require_once __DIR__ . '/connectdb.php';

// 2. ฟังก์ชันสำหรับสร้าง Log
/**
 * บันทึก Log การกระทำของแอดมินลงในฐานข้อมูล
 *
 * @param mysqli $conn - ตัวแปรเชื่อมต่อฐานข้อมูล
 * @param int $admin_id - ID ของแอดมินที่กระทำ
 * @param string $action - ประเภทการกระทำ (เช่น 'REPLY_REVIEW')
 * @param int $target_id - ID ของผู้ใช้ที่ถูกกระทำ (ในที่นี้คือ user_id ของคนรีวิว)
 * @param string $details - รายละเอียด (เช่น ตอบกลับรีวิว ID ไหน)
 */
function create_log($conn, $admin_id, $action, $target_id, $details = '') {
    // ป้องกันกรณี admin_id เป็น null
    if (empty($admin_id)) {
        $admin_id = 0; // 0 หมายถึง "System" หรือ "Unknown Admin"
    }
    // ป้องกันกรณี target_id เป็น null (เช่น รีวิวจาก guest ที่ไม่มี user_id)
    if (empty($target_id)) {
        $target_id = 0;
    }
    
    $sql_log = "INSERT INTO admin_logs (admin_user_id, action_type, target_user_id, details) VALUES (?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($conn, $sql_log);
    // "isis" = integer, string, integer, string
    if ($stmt_log) { // ตรวจสอบว่า prepare สำเร็จหรือไม่
        mysqli_stmt_bind_param($stmt_log, "isis", $admin_id, $action, $target_id, $details);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);
    } else {
        // สามารถเพิ่มการจัดการ Error log ตรงนี้ได้ หากต้องการ
        // error_log("Failed to prepare log statement: " . mysqli_error($conn));
    }
}

// 3. ดึง ID ของแอดมินที่กำลังล็อกอิน
$admin_logging_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;


// --- ส่วนจัดการ Logic (รับค่า POST จากฟอร์มตอบกลับ) ---
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_reply') {
    
    $review_id = (int)$_POST['review_id'];
    $admin_reply = trim($_POST['admin_reply']);

    if (!empty($review_id) && !empty($admin_reply)) {

        // 4.1 LOGGING: ดึง user_id ของคนรีวิว (เพื่อใช้เป็น target_user_id)
        $customer_user_id = 0; // ค่าเริ่มต้น (เผื่อเป็นรีวิวจาก guest)
        $sql_get_user = "SELECT user_id FROM reviews WHERE review_id = ?";
        $stmt_get_user = mysqli_prepare($conn, $sql_get_user);
        if ($stmt_get_user) {
            mysqli_stmt_bind_param($stmt_get_user, "i", $review_id);
            mysqli_stmt_execute($stmt_get_user);
            $result_get_user = mysqli_stmt_get_result($stmt_get_user);
            if ($row = mysqli_fetch_assoc($result_get_user)) {
                $customer_user_id = (int)$row['user_id'];
            }
            mysqli_stmt_close($stmt_get_user);
        }
        // --- จบส่วนดึง user_id ---

        // ทำการ UPDATE (โค้ดเดิม)
        $sql = "UPDATE reviews SET admin_reply = ?, replied_at = NOW() WHERE review_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $admin_reply, $review_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'ตอบกลับรีวิวสำเร็จ!';
            $message_type = 'success';

            // 4.2 LOGGING: บันทึก Log หลังจากตอบกลับสำเร็จ
            // ตัดข้อความให้สั้นลงเพื่อเก็บใน details
            $log_details = "ตอบกลับรีวิว ID: $review_id. ข้อความ: \"" . mb_substr($admin_reply, 0, 150) . (mb_strlen($admin_reply) > 150 ? "..." : "") . "\"";
            // เราใช้ $customer_user_id ที่ดึงมาเป็น "target_user_id"
            create_log($conn, $admin_logging_id, 'REPLY_REVIEW', $customer_user_id, $log_details);
            // --- จบส่วนบันทึก Log ---

        } else {
            $message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}


// --- ส่วนรับค่า GET สำหรับ Filter, Search, Pagination ---
$search = $_GET['search'] ?? '';
$filter_rt_id = $_GET['rt_id'] ?? '';
$filter_period = $_GET['period'] ?? 'all';
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) $current_page = 1;
$reviews_per_page = 10;
$offset = ($current_page - 1) * $reviews_per_page;

// --- ดึงข้อมูลประเภทห้องสำหรับ Filter Dropdown ---
$sqlRooms = "SELECT rt_id, rt_name FROM room_type ORDER BY rt_name";
$rooms_result = mysqli_query($conn, $sqlRooms);
$room_types = mysqli_fetch_all($rooms_result, MYSQLI_ASSOC);

// --- ฟังก์ชันสร้าง Stars ---
function generate_stars($rating) {
    $stars_html = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars_html .= ($i <= $rating) ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-warning"></i>';
    }
    return $stars_html;
}

// --- สร้าง SQL แบบไดนามิกสำหรับ Filter, Search, Pagination ---
$sql_base = "
    FROM reviews r
    LEFT JOIN room_type rt ON r.rt_id = rt.rt_id
    LEFT JOIN users u ON r.user_id = u.user_id
";
$sql_where = "";
$whereClauses = [];
$params = [];
$types = "";

// 1. เพิ่มเงื่อนไขการค้นหา (Search)
if (!empty($search)) {
    $whereClauses[] = "(r.name LIKE ? OR r.comment LIKE ? OR r.admin_reply LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%{$search}%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $types .= "sssss";
}
// 2. เพิ่มเงื่อนไขประเภทห้อง (Room Type)
if (!empty($filter_rt_id)) {
    $whereClauses[] = "r.rt_id = ?";
    $params[] = $filter_rt_id;
    $types .= "i";
}
// 3. เพิ่มเงื่อนไขช่วงเวลา (Period)
if ($filter_period == 'hour') {
    $whereClauses[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
} elseif ($filter_period == 'today') {
    $whereClauses[] = "r.created_at >= CURDATE()";
} elseif ($filter_period == 'week') {
    $whereClauses[] = "r.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($filter_period == 'month') {
    $whereClauses[] = "r.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($filter_period == 'year') {
    $whereClauses[] = "r.created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
}
if (!empty($whereClauses)) {
    $sql_where = " WHERE " . implode(" AND ", $whereClauses);
}

// --- Query 1: นับจำนวนทั้งหมดสำหรับ Pagination ---
$total_reviews = 0;
$sql_count = "SELECT COUNT(DISTINCT r.review_id) " . $sql_base . $sql_where;
$stmt_count = mysqli_prepare($conn, $sql_count);
if (!$stmt_count) {
    $message = "SQL Error (Count Prepare): " . mysqli_error($conn);
    $message_type = 'danger';
} else {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    $total_reviews = mysqli_fetch_row($result_count)[0];
    mysqli_stmt_close($stmt_count);
}
$total_pages = ceil($total_reviews / $reviews_per_page);
if ($total_pages < 1) $total_pages = 1; // กัน $total_pages เป็น 0
if ($current_page > $total_pages) $current_page = $total_pages; // ถ้าหน้าปัจจุบันเกิน ให้ไปหน้าสุดท้าย


// --- Query 2: ดึงข้อมูลรีวิวสำหรับหน้านี้ ---
$reviews = [];
$sql_select = "
    SELECT r.*, rt.rt_name AS room_type_name, u.first_name, u.last_name
    " . $sql_base . $sql_where . "
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";
$params_data = $params;
$types_data = $types;
$params_data[] = $reviews_per_page;
$params_data[] = $offset;
$types_data .= "ii";
$stmt_data = mysqli_prepare($conn, $sql_select);
if (!$stmt_data) {
     $message = "SQL Error (Data Prepare): " . mysqli_error($conn);
     $message_type = 'danger';
} else {
    if (!empty($params_data)) {
        mysqli_stmt_bind_param($stmt_data, $types_data, ...$params_data);
    }
    if (mysqli_stmt_execute($stmt_data)) {
        $result = mysqli_stmt_get_result($stmt_data);
        $reviews = mysqli_fetch_all($result, MYSQLI_ASSOC);
    } else {
        $message = "SQL Error (Data Execute): " . mysqli_error($conn);
        $message_type = 'danger';
    }
    mysqli_stmt_close($stmt_data);
}

// 5. ปิดการเชื่อมต่อฐานข้อมูล
mysqli_close($conn);

// --- สร้าง Query String สำหรับลิงก์ Pagination ---
$filter_query_string = "";
if (!empty($search)) $filter_query_string .= "&search=" . urlencode($search);
if (!empty($filter_rt_id)) $filter_query_string .= "&rt_id=" . urlencode($filter_rt_id);
if (!empty($filter_period)) $filter_query_string .= "&period=" . urlencode($filter_period);

?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<div class="container-fluid px-4">
    <h1 class="mt-4">จัดการรีวิวจากลูกค้า</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">ดูและตอบกลับความคิดเห็นของลูกค้า</li>
    </ol>

    <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-search me-2"></i>ตัวกรองการค้นหา</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="admin.php">
                <input type="hidden" name="page" value="manage_reviews">
                <div class="row g-3">
                    <div class="col-lg-4 col-md-12">
                        <label for="search" class="form-label">ค้นหา (ชื่อ, คอมเมนต์, คำตอบ):</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="พิมพ์แล้วกด Enter หรือปุ่มค้นหา...">
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <label for="rt_id" class="form-label">ประเภทห้อง:</label>
                        <select id="rt_id" name="rt_id" class="form-select" onchange="this.form.submit()">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($room_types as $room): ?>
                                <option value="<?= $room['rt_id'] ?>" <?= ($filter_rt_id == $room['rt_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($room['rt_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label for="period" class="form-label">ช่วงเวลา:</label>
                        <select id="period" name="period" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= ($filter_period == 'all') ? 'selected' : '' ?>>ทั้งหมด</option>
                            <option value="hour" <?= ($filter_period == 'hour') ? 'selected' : '' ?>>ชั่วโมงที่ผ่านมา</option>
                            <option value="today" <?= ($filter_period == 'today') ? 'selected' : '' ?>>วันนี้</option>
                            <option value="week" <?= ($filter_period == 'week') ? 'selected' : '' ?>>สัปดาห์นี้</option>
                            <option value="month" <?= ($filter_period == 'month') ? 'selected' : '' ?>>เดือนนี้</option>
                            <option value="year" <?= ($filter_period == 'year') ? 'selected' : '' ?>>ปีนี้</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-2 col-md-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> ค้นหา
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-chat-square-quote-fill me-2"></i>
                รายการรีวิว (พบ <?= $total_reviews ?> รายการ)
            </h5>
        </div>
        <div class="card-body bg-light p-2 p-md-3">
            <?php if (empty($reviews) && $message_type !== 'danger'): ?>
                <div class="text-center p-5">
                    <p class="text-muted fs-4">
                        <i class="bi bi-emoji-frown display-5"></i>
                    </p>
                    <p class="text-muted fs-4">ไม่พบรีวิวตามเงื่อนไขที่กำหนด</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <?php
                        // ตรวจสอบชื่อที่จะแสดง
                        $display_name = trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? ''));
                        if (empty($display_name)) {
                            $display_name = $review['name'] ?? 'ผู้ใช้ทั่วไป'; // $review['name'] คือชื่อที่ guest กรอก
                        }
                    ?>
                    <div class="card mb-3 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($display_name) ?></h5>
                                    <h6 class="card-subtitle mb-2 text-muted">
                                        <i class="bi bi-door-open"></i> ห้อง: <?= htmlspecialchars($review['room_type_name'] ?? 'ไม่ระบุ') ?>
                                    </h6>
                                    <div class="mb-2">
                                        <?= generate_stars($review['rating']) ?>
                                    </div>
                                </div>
                                <small class="text-muted text-nowrap ms-3">
                                    <i class="bi bi-clock"></i> <?= date('d M Y, H:i', strtotime($review['created_at'])) ?>
                                </small>
                            </div>
                            
                            <p class="card-text mt-2 bg-white p-3 border rounded">"<?= nl2br(htmlspecialchars($review['comment'] ?? '')) ?>"</p>

                            <hr>

                            <?php if (!empty($review['admin_reply'])): ?>
                                <div class="p-3 mt-3 rounded border-start border-5 border-success" style="background-color: #f0fdf4;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <p class="mb-1 fw-bold text-success"><i class="bi bi-chat-right-dots-fill"></i> การตอบกลับของคุณ:</p>
                                        <small class="text-muted">
                                            ตอบเมื่อ: <?= date('d M Y, H:i', strtotime($review['replied_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-0 ms-4">"<?= nl2br(htmlspecialchars($review['admin_reply'] ?? '')) ?>"</p>
                                </div>
                            <?php else: ?>
                                <div class="text-end">
                                    <button class="btn btn-primary reply-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#replyModal"
                                            data-review-id="<?= $review['review_id'] ?>"
                                            data-customer-name="<?= htmlspecialchars($display_name, ENT_QUOTES) ?>">
                                        <i class="bi bi-reply-fill"></i> ตอบกลับ
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="admin.php?page=manage_reviews&p=<?= $current_page - 1 ?><?= $filter_query_string ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    // Logic การแสดงผลเลขหน้า (เพื่อไม่ให้เยอะเกินไป)
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="admin.php?page=manage_reviews&p=1'.$filter_query_string.'">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                        <a class="page-link" href="admin.php?page=manage_reviews&p=<?= $i ?><?= $filter_query_string ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; 

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="admin.php?page=manage_reviews&p='.$total_pages.$filter_query_string.'">'.$total_pages.'</a></li>';
                    }
                    ?>

                    <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="admin.php?page=manage_reviews&p=<?= $current_page + 1 ?><?= $filter_query_string ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>


<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="replyModalLabel">ตอบกลับรีวิวของคุณ <span id="customerName" class="fw-bold"></span></h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="replyForm" method="POST" action="admin.php?page=manage_reviews&p=<?= $current_page ?><?= $filter_query_string ?>">
        <div class="modal-body">
            <input type="hidden" name="action" value="submit_reply">
            <input type="hidden" id="reviewIdInput" name="review_id">
            <div class="mb-3">
                <label for="adminReplyTextarea" class="form-label">ข้อความตอบกลับ:</label>
                <textarea class="form-control" id="adminReplyTextarea" name="admin_reply" rows="5" required></textarea>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary">ส่งคำตอบ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- จัดการการส่งข้อมูลไปยัง Modal ตอบกลับ ---
    const replyModal = document.getElementById('replyModal');
    if(replyModal) {
        replyModal.addEventListener('show.bs.modal', function (event) {
            // ปุ่มที่ถูกคลิก
            const button = event.relatedTarget;
            // ดึงข้อมูล
            const reviewId = button.getAttribute('data-review-id');
            const customerName = button.getAttribute('data-customer-name');
            
            // อัปเดตค่าใน Modal
            const modalTitle = replyModal.querySelector('#customerName');
            const reviewIdInput = replyModal.querySelector('#reviewIdInput');
            const adminReplyTextarea = replyModal.querySelector('#adminReplyTextarea');
            
            modalTitle.textContent = customerName;
            reviewIdInput.value = reviewId;
            adminReplyTextarea.value = ''; // เคลียร์ค่าเก่า
        });
    }

    // --- ยืนยันก่อนส่งคำตอบ (ใช้ SweetAlert2) ---
    const replyForm = document.getElementById('replyForm');
    if(replyForm) {
        replyForm.addEventListener('submit', function(event) {
            event.preventDefault(); // หยุด submit
            
            Swal.fire({
                title: 'ยืนยันการส่งคำตอบ',
                text: "คุณต้องการส่งคำตอบนี้ใช่หรือไม่?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ส่งเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit(); // ถ้ายืนยัน ก็ submit
                }
            });
        });
    }

    // --- ทำให้ Alert Message (Success/Error) หายไปเองใน 5 วินาที ---
    const alertMessage = document.querySelector('.alert-dismissible');
    if(alertMessage) {
        setTimeout(() => {
            // ใช้ Bootstrap's Alert.getInstance ถ้ามี หรือ new Alert ถ้าไม่มี
            const bsAlertInstance = bootstrap.Alert.getInstance(alertMessage);
            if(bsAlertInstance) {
                bsAlertInstance.close();
            } else {
                new bootstrap.Alert(alertMessage).close();
            }
        }, 5000);
    }
});
</script>