<?php
session_start();
require_once "connectdb.php";
date_default_timezone_set('Asia/Bangkok'); // ตั้งค่า Timezone ให้ตรงกับฐานข้อมูล

// --- รับค่า Filter จาก GET ---
$filter_rt_id = isset($_GET['rt_id']) ? (int)$_GET['rt_id'] : 0;
$filter_period = $_GET['period'] ?? 'all';
$filter_custom_date = $_GET['custom_date'] ?? '';

// --- โหลดประเภทห้อง ---
$roomTypes = $conn->query("SELECT rt_id, rt_name FROM room_type ORDER BY rt_name");

// --- สร้าง SQL Query แบบไดนามิก ---
$sql_select = "
    SELECT r.rating, r.comment, r.created_at, r.admin_reply, r.replied_at,
           u.first_name, u.last_name, rt.rt_name, r.name 
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.user_id
    LEFT JOIN room_type rt ON r.rt_id = rt.rt_id
";

$whereClauses = [];
$params = [];
$types = "";

// 1. กรองตามประเภทห้อง
if ($filter_rt_id > 0) {
    $whereClauses[] = "r.rt_id = ?";
    $params[] = $filter_rt_id;
    $types .= "i";
}

// 2. กรองตามช่วงเวลา/วันที่
$today_end = date('Y-m-d 23:59:59');

switch ($filter_period) {
    case 'today':
        $start = date('Y-m-d 00:00:00');
        $whereClauses[] = "r.created_at >= ? AND r.created_at <= ?";
        $params[] = $start; $params[] = $today_end; $types .= "ss";
        break;
    case '3days':
        $start = date('Y-m-d 00:00:00', strtotime('-2 days'));
        $whereClauses[] = "r.created_at >= ? AND r.created_at <= ?";
        $params[] = $start; $params[] = $today_end; $types .= "ss";
        break;
    case 'week':
        $start = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $whereClauses[] = "r.created_at >= ? AND r.created_at <= ?";
        $params[] = $start; $params[] = $today_end; $types .= "ss";
        break;
    case 'month':
        $start = date('Y-m-01 00:00:00');
        $whereClauses[] = "r.created_at >= ? AND r.created_at <= ?";
        $params[] = $start; $params[] = $today_end; $types .= "ss";
        break;
    case 'year':
        $start = date('Y-01-01 00:00:00');
        $whereClauses[] = "r.created_at >= ? AND r.created_at <= ?";
        $params[] = $start; $params[] = $today_end; $types .= "ss";
        break;
    case 'custom':
        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filter_custom_date)) {
            $start = $filter_custom_date . ' 00:00:00';
            $end = $filter_custom_date . ' 23:59:59';
            $whereClauses[] = "r.created_at >= ? AND r.created_at <= ?";
            $params[] = $start; $params[] = $end; $types .= "ss";
        } else {
             // ถ้าวันที่ไม่ถูกต้อง ให้แสดงทั้งหมดแทน
             $filter_period = 'all';
             $filter_custom_date = '';
        }
        break;
    case 'all':
        break;
}

// --- รวม WHERE clauses และรัน Query ---
$sql_where = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";
$sql_final = $sql_select . $sql_where . " ORDER BY r.created_at DESC";

$reviews = null; // ใช้สำหรับ Loop ใน HTML
$stmt = $conn->prepare($sql_final);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $reviews = $stmt->get_result();
    } else {
        error_log("SQL Error: " . $stmt->error);
    }
    // ไม่ปิด $stmt ตรงนี้ เพราะต้องใช้ $reviews ใน loop
} else {
    error_log("SQL Prepare Error: " . $conn->error);
}

// Helper function
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รีวิวทั้งหมด | Tharapana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        body { background: #f4f8fb; padding-top: 110px; } 
        .review-card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .rating { color: #f4b400; font-size: 1.1em; }
        .stars span { margin-right: 2px; }
        /* สไตล์สำหรับ Admin Reply */
        .admin-reply {
            background-color: #f0fdf4;
            border-left: 5px solid #198754;
            padding: 15px;
            margin-top: 15px;
            border-radius: 5px;
        }
        .admin-reply p { margin-bottom: 0; }
        .reply-header { margin-bottom: 8px; }
        /* ปรับปรุงฟอร์ม Filter ให้สวยขึ้น */
        .filter-container { background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
        .filter-group { display: flex; align-items: flex-end; justify-content: center; gap: 1rem; flex-wrap: wrap; }
        .filter-item { min-width: 150px; }
        .filter-item label { font-weight: 500; margin-bottom: 0.25rem; display: block; text-align: left; }
    </style>
</head>
<body class="index-page">

<?php include_once "header.php"; ?>

<main id="main" class="container mt-4 mb-5">
    <h2 class="mb-4 text-center">รีวิวทั้งหมดจากลูกค้า</h2>

   
    <div class="filter-container mb-5">
        <form method="get">
            <div class="filter-group">
                
                <div class="filter-item">
                    <label for="rt_id_filter">ประเภทห้อง:</label>
                    <select name="rt_id" id="rt_id_filter" class="form-select">
                        <option value="0">ทั้งหมด</option>
                        <?php mysqli_data_seek($roomTypes, 0); ?>
                        <?php while($r = $roomTypes->fetch_assoc()): ?>
                            <option value="<?= $r['rt_id'] ?>" <?= $filter_rt_id == $r['rt_id'] ? 'selected' : '' ?>>
                                <?= h($r['rt_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="period_filter">ช่วงเวลา:</label>
                    <select name="period" id="period_filter" class="form-select">
                        <option value="all" <?= ($filter_period == 'all') ? 'selected' : '' ?>>ทั้งหมด</option>
                        <option value="today" <?= ($filter_period == 'today') ? 'selected' : '' ?>>วันนี้</option>
                        <option value="3days" <?= ($filter_period == '3days') ? 'selected' : '' ?>>3 วันล่าสุด</option>
                        <option value="week" <?= ($filter_period == 'week') ? 'selected' : '' ?>>สัปดาห์นี้</option>
                        <option value="month" <?= ($filter_period == 'month') ? 'selected' : '' ?>>เดือนนี้</option>
                        <option value="year" <?= ($filter_period == 'year') ? 'selected' : '' ?>>ปีนี้</option>
                        <option value="custom" <?= ($filter_period == 'custom') ? 'selected' : '' ?>>เลือกวันที่</option>
                    </select>
                </div>

                <div class="filter-item" id="custom_date_wrapper_php" style="display: <?= ($filter_period != 'custom') ? 'none' : 'block' ?>;">
                     <label for="custom_date_input">วันที่:</label>
                     <input type="date" name="custom_date" id="custom_date_input" class="form-control" value="<?= h($filter_custom_date) ?>" required>
                </div>

                <div class="filter-item" style="min-width: 100px;">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel-fill"></i> กรอง
                    </button>
                </div>

            </div>
        </form>
    </div>

  
    <?php if ($reviews && $reviews->num_rows > 0): ?>
        <?php while($row = $reviews->fetch_assoc()): ?>
            <?php
                $display_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if (empty($display_name)) {
                    $display_name = !empty($row['name']) ? $row['name'] : 'ผู้เยี่ยมชม';
                }
            ?>
            <div class="review-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="mb-1"><?= h($display_name) ?></h5>
                        <p class="text-muted small mb-1">
                            <i class="bi bi-door-open"></i> ห้อง: <?= h($row["rt_name"] ?? 'ไม่ระบุ') ?>
                        </p>
                    </div>
                    <small class="text-muted text-nowrap ms-3">
                         <i class="bi bi-clock"></i> <?= date('d M Y, H:i', strtotime($row["created_at"])) ?>
                    </small>
                </div>

                <p class="rating stars mb-2">
                    <?php for($i=1;$i<=5;$i++): ?>
                        <span><?= $i <= $row["rating"] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>' ?></span>
                    <?php endfor; ?>
                </p>
                <p class="mb-3">"<?= nl2br(h($row["comment"] ?? '')) ?>"</p>

                <?php if (!empty($row['admin_reply'])): ?>
                    <hr class="my-3">
                    <div class="admin-reply">
                        <div class="d-flex justify-content-between align-items-center reply-header">
                            <p class="mb-0 fw-bold text-success"><i class="bi bi-chat-right-dots-fill"></i> ตอบกลับจาก Admin:</p>
                            <?php if (!empty($row['replied_at'])): ?>
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> <?= date('d M Y, H:i', strtotime($row['replied_at'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <p class="ms-4">"<?= nl2br(h($row['admin_reply'])) ?>"</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        <?php $stmt->close(); // ปิด statement ที่สร้างจาก prepare ?>
    <?php else: ?>
        <div class="text-center p-5">
            <p class="text-muted fs-4"><i class="bi bi-chat-square-quote display-5"></i></p>
            <p class="text-center text-muted fs-5 mt-3">ยังไม่มีรีวิว<?php echo ($filter_rt_id > 0 ? 'สำหรับประเภทห้องนี้' : ''); ?></p>
        </div>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- JavaScript สำหรับซ่อน/แสดงช่องเลือกวันที่ ---
    const periodSelect = document.getElementById('period_filter');
    const customDateWrapper = document.getElementById('custom_date_wrapper_php');
    const customDateInput = document.getElementById('custom_date_input');
    const filterForm = document.querySelector('form'); // อ้างอิง form

    function toggleCustomDate() {
        if (!periodSelect || !customDateWrapper || !customDateInput) return;
        
        if (periodSelect.value === 'custom') {
            customDateWrapper.style.display = 'block'; // แสดงช่อง
            customDateInput.required = true;
        } else {
            customDateWrapper.style.display = 'none'; // ซ่อนช่อง
            customDateInput.required = false;
            // ไม่ต้องล้าง customDateInput.value เพราะการ submit จะทำให้อยู่ใน URL
        }
        
        // ** (ใหม่) Submit ฟอร์มทันทีเมื่อเปลี่ยนจาก 'custom' ไปเป็นอย่างอื่น **
        if (periodSelect.value !== 'custom') {
            filterForm.submit();
        }
    }

    if(periodSelect) {
        periodSelect.addEventListener('change', toggleCustomDate);
        
        // ** (ใหม่) ถ้าเลือก custom และมีการเลือกวันที่ ให้ submit ฟอร์ม **
        customDateInput.addEventListener('change', function() {
            if (periodSelect.value === 'custom' && this.value) {
                filterForm.submit();
            }
        });
        
        // ** (ใหม่) ถ้าเลือก rt_id ให้ submit ฟอร์ม **
        document.getElementById('rt_id_filter').addEventListener('change', function() {
            filterForm.submit();
        });
    }

    // --- ทำให้ Alert Message (Success/Error) หายไปเองใน 5 วินาที (หากมี) ---
    const alertMessage = document.querySelector('.alert-dismissible');
    if(alertMessage) {
        setTimeout(() => {
            const bsAlertInstance = bootstrap.Alert.getInstance(alertMessage) || new bootstrap.Alert(alertMessage);
            bsAlertInstance.close();
        }, 5000);
    }
</script>
</body>
</html>