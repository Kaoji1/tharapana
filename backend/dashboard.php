<?php
// ตรวจสอบก่อนว่า session เริ่มหรือยัง
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. ส่วนเชื่อมต่อฐานข้อมูล ---
$host = "localhost";
$usr = "root";
$pwd = "";
$dbName = "tharapana";
$conn = mysqli_connect($host, $usr, $pwd, $dbName);
if (!$conn) {
    echo "<div style='padding:20px;background:#f8d7da;color:#721c24;'>การเชื่อมต่อล้มเหลว: " . mysqli_connect_error() . "</div>";
    die();
}
mysqli_set_charset($conn, 'utf8mb4');

// --- 2. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION["user_id"])) {
    header("Location: ReandLog.php");
    exit;
}
$user_id = $_SESSION["user_id"];

// --- 3. ดึงข้อมูลผู้ใช้ที่ล็อกอิน (สำหรับ Navbar) ---
$user_first_name = "User";
$user_last_name = "";
$user_role = "User";
$stmt = mysqli_prepare($conn, "SELECT first_name, last_name, role FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
if ($user_data = mysqli_fetch_assoc($user_result)) {
    $user_first_name = htmlspecialchars($user_data['first_name']);
    $user_last_name = htmlspecialchars($user_data['last_name']);
    $user_role = htmlspecialchars(ucfirst($user_data['role']));
}
$user_fullname = trim($user_first_name . " " . $user_last_name);

// --- 4. รับค่า Filter ทั้งหมด ---
$period = $_GET['period'] ?? 'week';
$chart_type = $_GET['chart'] ?? 'line';
$stats_period = $_GET['stats_period'] ?? 'week';
$check_date = $_GET['check_date'] ?? date('Y-m-d');

// ✅ 1. (แก้ไข) เพิ่มการรับค่าสำหรับ custom date range
$default_start_date = date('Y-m-d', strtotime('-6 days')); // เริ่มต้นย้อนหลัง 7 วัน
$default_end_date = date('Y-m-d');
$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;


// --- 5. ตรรกะสำหรับ Filter ยอดขาย (Sales Chart) ---
$date_filter_sql = "";
$period_label = "ทั้งหมด";
switch ($period) {
    case 'today': $date_filter_sql = " AND DATE(created_at) = CURDATE()"; $period_label = "วันนี้"; break;
    case '3days': $date_filter_sql = " AND created_at >= CURDATE() - INTERVAL 2 DAY"; $period_label = "3 วันล่าสุด"; break;
    case 'week': $date_filter_sql = " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)"; $period_label = "สัปดาห์นี้"; break;
    case 'month': $date_filter_sql = " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"; $period_label = "เดือนนี้"; break;
    case 'year': $date_filter_sql = " AND YEAR(created_at) = YEAR(CURDATE())"; $period_label = "ปีนี้"; break;
    
    // ✅ 2. (เพิ่ม) เพิ่ม Case สำหรับ 'custom'
    case 'custom':
        // Basic sanitation
        $safe_start_date = mysqli_real_escape_string($conn, $start_date);
        $safe_end_date = mysqli_real_escape_string($conn, $end_date);
        $date_filter_sql = " AND DATE(created_at) BETWEEN '$safe_start_date' AND '$safe_end_date'"; 
        // สร้าง Label สวยๆ
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);
        if ($start_ts == $end_ts) {
            $period_label = "วันที่ " . date('j M Y', $start_ts);
        } else {
            $period_label = "จาก " . date('j M Y', $start_ts) . " ถึง " . date('j M Y', $end_ts);
        }
        break;

    default: $date_filter_sql = ""; $period_label = "ทั้งหมด"; break;
}
$revenue_query_sql = "SELECT SUM(final_price) AS total_revenue FROM booking WHERE status != 'cancelled' $date_filter_sql";
$revenue_query = mysqli_query($conn, $revenue_query_sql);
$total_revenue_filtered_raw = mysqli_fetch_assoc($revenue_query)['total_revenue'];
$total_revenue_filtered = number_format($total_revenue_filtered_raw ?? 0, 2);

// --- 6. ดึงข้อมูลสำหรับกราฟยอดขาย (Sales Chart) ---
$chart_labels = []; $chart_data = [];
// (โค้ด switch case $period ... เหมือนเดิม)
switch ($period) {
    case 'today': $chart_labels = ['00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23']; $chart_data = array_fill(0, 24, 0); $sql = "SELECT HOUR(created_at) as hour, SUM(final_price) as total FROM booking WHERE status != 'cancelled' AND DATE(created_at) = CURDATE() GROUP BY HOUR(created_at)"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $chart_data[intval($row['hour'])] = (float)$row['total']; } break;
    case '3days': $chart_labels = [date('j M', strtotime('-2 days')), date('j M', strtotime('-1 days')), date('j M', strtotime('today'))]; $chart_data = [0, 0, 0]; $sql = "SELECT DATE(created_at) as date, SUM(final_price) as total FROM booking WHERE status != 'cancelled' AND created_at >= CURDATE() - INTERVAL 2 DAY GROUP BY DATE(created_at) ORDER BY date ASC"; $result = mysqli_query($conn, $sql); $data_map = []; while ($row = mysqli_fetch_assoc($result)) { $data_map[$row['date']] = (float)$row['total']; } if (isset($data_map[date('Y-m-d', strtotime('-2 days'))])) $chart_data[0] = $data_map[date('Y-m-d', strtotime('-2 days'))]; if (isset($data_map[date('Y-m-d', strtotime('-1 days'))])) $chart_data[1] = $data_map[date('Y-m-d', strtotime('-1 days'))]; if (isset($data_map[date('Y-m-d', strtotime('today'))])) $chart_data[2] = $data_map[date('Y-m-d', strtotime('today'))]; break;
    case 'week': $chart_labels = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.']; $chart_data = array_fill(0, 7, 0); $sql = "SELECT DAYOFWEEK(created_at) as day_of_week, SUM(final_price) as total FROM booking WHERE status != 'cancelled' AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) GROUP BY DAYOFWEEK(created_at)"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $chart_data[$row['day_of_week'] - 1] = (float)$row['total']; } break;
    case 'month': $days_in_month = date('t'); $chart_labels = range(1, $days_in_month); $chart_data = array_fill(0, $days_in_month, 0); $sql = "SELECT DAY(created_at) as day, SUM(final_price) as total FROM booking WHERE status != 'cancelled' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) GROUP BY DAY(created_at)"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $chart_data[$row['day'] - 1] = (float)$row['total']; } break;
    case 'year': $chart_labels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']; $chart_data = array_fill(0, 12, 0); $sql = "SELECT MONTH(created_at) as month, SUM(final_price) as total FROM booking WHERE status != 'cancelled' AND YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at)"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $chart_data[$row['month'] - 1] = (float)$row['total']; } break;
    
    // ✅ 3. (เพิ่ม) เพิ่ม Case 'custom' สำหรับข้อมูลกราฟ
    case 'custom':
        $safe_start_date = mysqli_real_escape_string($conn, $start_date);
        $safe_end_date = mysqli_real_escape_string($conn, $end_date);
        
        $begin = new DateTime($safe_start_date);
        $end = new DateTime($safe_end_date);
        $diff = $begin->diff($end)->days;

        if ($diff <= 90) { // ถ้าน้อยกว่า 90 วัน แสดงผลรายวัน
            $end->modify('+1 day'); // เพิ่ม 1 วันเพื่อให้ DatePeriod คลุมวันสุดท้าย
            $interval = DateInterval::createFromDateString('1 day');
            $period_range = new DatePeriod($begin, $interval, $end);
            
            $data_map = [];
            foreach ($period_range as $dt) {
                $date_key = $dt->format("Y-m-d");
                $chart_labels[] = $dt->format("j M"); // Label แบบสั้น
                $data_map[$date_key] = 0; // สร้างข้อมูลตั้งต้น 0
            }

            $sql = "SELECT DATE(created_at) as date, SUM(final_price) as total 
                    FROM booking 
                    WHERE status != 'cancelled' AND DATE(created_at) BETWEEN '$safe_start_date' AND '$safe_end_date' 
                    GROUP BY DATE(created_at) 
                    ORDER BY date ASC";
            $result = mysqli_query($conn, $sql);
            
            while ($row = mysqli_fetch_assoc($result)) {
                if (isset($data_map[$row['date']])) {
                    $data_map[$row['date']] = (float)$row['total']; // อัปเดตด้วยข้อมูลจริง
                }
            }
            $chart_data = array_values($data_map); // แปลงกลับเป็น array
        
        } else { // ถ้ามากกว่า 90 วัน แสดงผลรายเดือน
            $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month_year, SUM(final_price) as total 
                    FROM booking 
                    WHERE status != 'cancelled' AND DATE(created_at) BETWEEN '$safe_start_date' AND '$safe_end_date' 
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                    ORDER BY month_year ASC";
            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_assoc($result)) {
                $chart_labels[] = $row['month_year'];
                $chart_data[] = (float)$row['total'];
            }
        }
        break;

    case 'all': default: $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month_year, SUM(final_price) as total FROM booking WHERE status != 'cancelled' GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month_year ASC"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $chart_labels[] = $row['month_year']; $chart_data[] = (float)$row['total']; } break;
}
$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);

// --- 7. ดึงข้อมูลหลัก (สำหรับ 5 การ์ดเล็ก) ---
$total_rooms = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(room_id) AS total_rooms FROM rooms"))['total_rooms'];
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(user_id) AS total_users FROM users"))['total_users'];
$bookings_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(b_id) AS bookings_today FROM booking WHERE DATE(checkin)=CURDATE() AND status!='cancelled'"))['bookings_today'];
$total_room_types = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(rt_id) AS total_room_types FROM room_type"))['total_room_types'];
$pending_bookings_query = mysqli_query($conn, "SELECT COUNT(b_id) AS pending_bookings FROM booking WHERE status = ''");
$pending_bookings = mysqli_fetch_assoc($pending_bookings_query)['pending_bookings'];

// --- 7.1 เตรียมข้อมูลสรุปสำหรับ Excel (และ PDF สรุป) ---
$summary_stats = [
    ['หัวข้อ', 'จำนวน'],
    ['ห้องพักทั้งหมด', (int)$total_rooms],
    ['การจองรออนุมัติ', (int)$pending_bookings],
    ['ผู้ใช้ทั้งหมด', (int)$total_users],
    ['เข้าพักวันนี้', (int)$bookings_today],
    ['ประเภทห้องทั้งหมด', (int)$total_room_types]
];


// --- 8. ดึงข้อมูลสำหรับกราฟโดนัท (ห้องว่าง) ---
$stmt = mysqli_prepare($conn, "SELECT COUNT(DISTINCT T1.room_id) AS occupied_rooms FROM booking AS T1 WHERE T1.status != 'cancelled' AND (? >= T1.checkin AND ? < T1.checkout)");
mysqli_stmt_bind_param($stmt, "ss", $check_date, $check_date);
mysqli_stmt_execute($stmt);
$occupied_result = mysqli_stmt_get_result($stmt);
$occupied_rooms = (int)mysqli_fetch_assoc($occupied_result)['occupied_rooms'];
$available_rooms = max(0, $total_rooms - $occupied_rooms);
$donut_data_json = json_encode([$occupied_rooms, $available_rooms]);


// --- 9. ดึงข้อมูลสำหรับกราฟเส้น (สถิติการจอง) ---
$stats_chart_labels = []; $stats_chart_data = [];
// (โค้ด switch case $stats_period ... เหมือนเดิม)
switch ($stats_period) {
    case 'today': $stats_chart_labels = ['00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23']; $stats_chart_data = array_fill(0, 24, 0); $sql = "SELECT HOUR(created_at) as hour, COUNT(b_id) as total FROM booking WHERE DATE(created_at) = CURDATE() GROUP BY HOUR(created_at)"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $stats_chart_data[intval($row['hour'])] = (int)$row['total']; } break;
    case '3days': $stats_chart_labels = [date('j M', strtotime('-2 days')), date('j M', strtotime('-1 days')), date('j M', strtotime('today'))]; $stats_chart_data = [0, 0, 0]; $sql = "SELECT DATE(created_at) as date, COUNT(b_id) as total FROM booking WHERE created_at >= CURDATE() - INTERVAL 2 DAY GROUP BY DATE(created_at) ORDER BY date ASC"; $result = mysqli_query($conn, $sql); $data_map = []; while ($row = mysqli_fetch_assoc($result)) { $data_map[$row['date']] = (int)$row['total']; } if (isset($data_map[date('Y-m-d', strtotime('-2 days'))])) $stats_chart_data[0] = $data_map[date('Y-m-d', strtotime('-2 days'))]; if (isset($data_map[date('Y-m-d', strtotime('-1 days'))])) $stats_chart_data[1] = $data_map[date('Y-m-d', strtotime('-1 days'))]; if (isset($data_map[date('Y-m-d', strtotime('today'))])) $stats_chart_data[2] = $data_map[date('Y-m-d', strtotime('today'))]; break;
    case 'week': $stats_chart_labels = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.']; $stats_chart_data = array_fill(0, 7, 0); $sql = "SELECT DAYOFWEEK(created_at) as day_of_week, COUNT(b_id) as total FROM booking WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) GROUP BY DAYOFWEEK(created_at)"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $stats_chart_data[$row['day_of_week'] - 1] = (int)$row['total']; } break;
    case 'month': $days_in_month = date('t'); $stats_chart_labels = range(1, $days_in_month); $stats_chart_data = array_fill(0, $days_in_month, 0); $sql = "SELECT DAY(created_at) as day, COUNT(b_id) as total FROM booking WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) GROUP BY DAY(created_at)"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $stats_chart_data[$row['day'] - 1] = (int)$row['total']; } break;
    case 'year': $stats_chart_labels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']; $stats_chart_data = array_fill(0, 12, 0); $sql = "SELECT MONTH(created_at) as month, COUNT(b_id) as total FROM booking WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at)"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $stats_chart_data[$row['month'] - 1] = (int)$row['total']; } break;
    case 'all': default: $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month_year, COUNT(b_id) as total FROM booking GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month_year ASC"; $result = mysqli_query($conn, $sql); while ($row = mysqli_fetch_assoc($result)) { $stats_chart_labels[] = $row['month_year']; $stats_chart_data[] = (int)$row['total']; } break;
}
$stats_labels_json = json_encode($stats_chart_labels);
$stats_data_json = json_encode($stats_chart_data);

// --- 10. ดึงข้อมูลการจองล่าสุด และ ห้องยอดนิยม ---
$result = mysqli_query($conn, "SELECT firstname, lastname, checkin, created_at FROM booking WHERE status!='cancelled' ORDER BY created_at DESC LIMIT 5");
$recent_bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);
$query = "SELECT T2.rt_name, COUNT(T1.b_id) AS booking_count FROM booking AS T1 JOIN room_type AS T2 ON T1.rt_id = T2.rt_id WHERE T1.status!='cancelled' GROUP BY T1.rt_id, T2.rt_name ORDER BY booking_count DESC LIMIT 3";
$result = mysqli_query($conn, $query);
$popular_rooms = mysqli_fetch_all($result, MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบจองห้องพัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>


    <style>
        /* (... CSS เดิมทั้งหมด ...) */
        :root {
            --bs-body-font-family: 'Sarabun', sans-serif;
            --bs-primary-rgb: 13, 110, 253; /* สีหลัก Bootstrap */
            --bs-secondary-rgb: 108, 117, 125; /* สีรอง */
            --bs-success-rgb: 25, 135, 84; /* สีเขียว */
            --bs-info-rgb: 13, 202, 240;    /* สีฟ้า */
            --bs-warning-rgb: 255, 193, 7;  /* สีเหลือง */
            --bs-danger-rgb: 220, 53, 69;   /* สีแดง */
            --bs-light-rgb: 248, 249, 250; /* สีเทาอ่อน */
            --bs-dark-rgb: 33, 37, 41;    /* สีเข้ม */
        }
        body { background-color: #f0f2f5; /* สีพื้นหลังอ่อนลง */ }
        #main-content { padding: 0; background-color: transparent; min-height: 100vh; }

        .top-navbar {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 0.8rem 2rem; /* ปรับ Padding */
            display: flex; justify-content: space-between; align-items: center;
        }
        .top-navbar .profile-pic { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-left: 0.75rem; border: 2px solid #eee;}

        .content-wrapper { padding: 2.5rem; }

        /* การ์ดทั่วไป */
        .card {
            border: none;
            border-radius: 0.8rem; /* เพิ่มความมน */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06); /* เงาที่นุ่มนวลขึ้น */
            margin-bottom: 1.75rem; /* เพิ่มระยะห่าง */
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e9ecef; /* เส้นขอบบางลง */
            padding: 1rem 1.5rem; /* ปรับ Padding */
            font-size: 1rem; /* ปรับขนาด */
            font-weight: 600; /* ตัวหนาขึ้น */
            color: var(--bs-dark);
        }
        /* ✅ (เพิ่ม) CSS สำหรับ Card Footer ที่เราจะใส่ฟอร์ม */
        .card-footer {
            background-color: #f8f9fa; /* สีอ่อนกว่า header */
            border-top: 1px solid #e9ecef;
            padding: 0.75rem 1.5rem;
        }

        .card-body { padding: 1.5rem; }

        /* การ์ดสถิติ (4 ใบแรก) */
        .stat-card {
            color: #fff;
            padding: 1.75rem 1.5rem; /* ปรับ Padding */
            position: relative; overflow: hidden;
            border-radius: 1rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            height: 100%; display: flex; flex-direction: column; justify-content: center;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); }

        .stat-card.bg-primary { background: linear-gradient(135deg, #0d6efd, #0b5ed7); }
        .stat-card.bg-success { background: linear-gradient(135deg, #198754, #157347); }
        .stat-card.bg-warning { background: linear-gradient(135deg, #ffc107, #e0a800); color: #333 !important; } /* ปรับสีตัวอักษรของ warning */
        .stat-card.bg-danger { background: linear-gradient(135deg, #dc3545, #b02a37); }
        .stat-card.bg-info { background: linear-gradient(135deg, #0dcaf0, #0aa3c2); } /* สีสำหรับยอดรวม (ถ้ามี) */

        .stat-card h5 { font-size: 0.95rem; font-weight: 500; margin-bottom: 0.4rem; color: rgba(255, 255, 255, 0.95); z-index: 2; position: relative; }
        .stat-card .stat-value { font-size: 2.2rem; font-weight: 700; z-index: 2; position: relative; }
        .stat-card .stat-icon { position: absolute; bottom: -15px; right: 1rem; font-size: 5rem; opacity: 0.2; color: rgba(255, 255, 255, 0.9); z-index: 1; }

        /* การ์ดกราฟยอดขาย */
        .sales-chart-card .card-header { background-color: #fff; border-bottom: 1px solid #e9ecef; }
        .sales-chart-card .card-body { background-color: #e9f7ff; height: 370px; padding: 1.5rem; } /* ปรับสีและความสูง */
        .btn-group .btn { font-weight: 500; font-size: 0.85rem; padding: 0.4rem 0.8rem; } /* ปรับปุ่ม Filter */
        .btn-group > .btn.active { font-weight: 600; } /* ทำให้ปุ่ม Active หนาขึ้น */

        /* การ์ดกราฟโดนัทและสถิติ */
        .chart-card .card-body { padding: 1rem; } /* ลด Padding */
        #donutChart, #statsChart { max-height: 280px !important; } /* ลดความสูงกราฟล่าง */

        /* ตาราง */
        .table { margin-bottom: 0; } /* ลบ margin ล่างของตาราง */
        .table thead th { background-color: #f8f9fa; border-bottom-width: 1px; font-weight: 600; font-size: 0.9rem; padding: 0.8rem 1rem; }
        .table tbody td { vertical-align: middle; font-size: 0.9rem; padding: 0.8rem 1rem; }
        .table-hover tbody tr:hover { background-color: rgba(var(--bs-primary-rgb), 0.05); }

        /* Quick Actions */
        .quick-action-card { border-radius: 1rem; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.07); transition: transform 0.3s ease, box-shadow 0.3s ease; height: 100%; background-color: #fff; }
        .quick-action-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
        .quick-action-card .card-body { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 1.75rem 1rem; }
        .quick-action-card .icon-circle { width: 55px; height: 55px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.6rem; margin-bottom: 1rem; }
        .quick-action-card .action-title { font-weight: 600; color: var(--bs-dark); margin-bottom: 0.3rem; font-size: 1rem; }
        .quick-action-card .action-desc { font-size: 0.8rem; color: #6c757d; }

        /* 2. CSS สำหรับการพิมพ์ */
        @media print {
            body { background-color: #fff; }
            /* ซ่อนส่วนที่ไม่ต้องการพิมพ์ */
            .top-navbar, 
            #report-button-group, /* ID ของปุ่มที่เพิ่มใหม่ */
            .quick-action-card, 
            .card-header .btn-group, 
            .card-header form,
            .card-footer, /* ✅ ซ่อน card-footer ด้วย */
            .content-wrapper > .h4,
            .content-wrapper > .row.g-4.mt-4:has(.quick-action-card),
            #summary-print-area /* ซ่อนแม่แบบสรุปด้วย */
             {
                display: none !important;
            }
            .content-wrapper { padding: 0; }
            .card { 
                box-shadow: none; 
                border: 1px solid #dee2e6;
                page-break-inside: avoid; /* พยายามไม่ให้การ์ดถูกตัดข้ามหน้า */
            }
            .sales-chart-card .card-body { background-color: #fff; }
            .stat-card {
                background: #f8f9fa !important;
                color: #000 !important;
                border: 1px solid #dee2e6;
            }
            .stat-card h5 { color: #555 !important; }
            .stat-card .stat-icon { display: none; }
            canvas { max-width: 100% !important; }
        }

    </style>
</head>
<body>

    <div id="loading-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9998; color: white; text-align: center; padding-top: 45vh; font-size: 1.5rem; font-weight: bold; backdrop-filter: blur(5px);">
        <div class="spinner-border text-light me-3" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        กำลังสร้างไฟล์...
    </div>

    <div id="main-content">
        <nav class="top-navbar">
            <form class="d-flex" role="search">
                <div class="input-group input-group-sm"> <span class="input-group-text bg-light border-0" id="basic-addon1"><i class="bi bi-search"></i></span>
                    <input class="form-control bg-light border-0" type="search" placeholder="ค้นหาที่นี่..." aria-label="Search">
                </div>
            </form>
            <ul class="nav align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center pe-0" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div>
                            <div class="fw-bold small"><?= $user_fullname ?></div> <small class="text-muted d-block" style="font-size: 0.75rem;"><?= $user_role ?></small> </div>
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_fullname) ?>&background=0d6efd&color=fff&size=40" alt="Profile" class="profile-pic"> </a>
                    
                    
                </li>
            </ul>
        </nav>

        <main class="content-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-dark fw-bold">Dashboard</h1>
                
                <div class="btn-group" id="report-button-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i> รายงาน / พิมพ์
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" id="printButton"><i class="bi bi-printer me-2"></i>พิมพ์หน้านี้</a></li>
                        <li><a class="dropdown-item" href="#" id="pdfButton"><i class="bi bi-file-earmark-pdf me-2"></i>ดาวน์โหลด PDF (เต็มหน้า)</a></li>
                        <li><a class="dropdown-item" href="#" id="summaryPdfButton"><i class="bi bi-file-earmark-text me-2"></i>ดาวน์โหลด PDF (สรุป)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" id="excelButton"><i class="bi bi-file-earmark-spreadsheet me-2"></i>ดาวน์โหลด Excel</a></li>
                    </ul>
                </div>
            </div>

            <h4 class="h6 mb-3 text-secondary fw-bold">ภาพรวมระบบ</h4>
            <div class="row row-cols-1 row-cols-md-3 row-cols-xl-5 g-4 mb-4">
                
                <div class="col">
                    <div class="stat-card bg-primary">
                        <h5>ห้องพักทั้งหมด</h5>
                        <div class="stat-value"><?= $total_rooms ?></div>
                        <i class="bi bi-building stat-icon"></i>
                    </div>
                </div>

                <div class="col">
                    <div class="stat-card bg-info"> <h5>รออนุมัติ</h5>
                        <div class="stat-value"><?= $pending_bookings ?></div>
                        <i class="bi bi-clock-history stat-icon"></i> </div>
                </div>

                <div class="col">
                    <div class="stat-card bg-success">
                        <h5>ผู้ใช้ทั้งหมด</h5>
                        <div class="stat-value"><?= $total_users ?></div>
                        <i class="bi bi-people stat-icon"></i>
                    </div>
                </div>

                <div class="col">
                    <div class="stat-card bg-warning text-dark">
                        <h5>เข้าพักวันนี้</h5>
                        <div class="stat-value"><?= $bookings_today ?></div>
                        <i class="bi bi-calendar-check stat-icon"></i>
                    </div>
                </div>

                

                <div class="col">
                    <div class="stat-card bg-danger">
                        <h5>ประเภทห้องทั้งหมด</h5>
                        <div class="stat-value"><?= $total_room_types ?></div>
                        <i class="bi bi-grid-1x2 stat-icon"></i>
                    </div>
                </div>
            </div>

            <div class="card sales-chart-card mb-4" id="charts-main-row">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                    <div class="me-3 mb-2 mb-md-0">
                        <h6 class="mb-0 text-muted">ยอดขาย (<?= $period_label ?>)</h6>
                        <h3 class="mb-0 fw-bold text-primary">฿<?= $total_revenue_filtered ?></h3>
                    </div>
                    <div class="d-flex flex-wrap">
                        <div class="btn-group btn-group-sm me-2 mb-2 mb-md-0" role="group" aria-label="Chart Type"> 
                            <a href="?period=<?= $period ?>&chart=line&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($chart_type == 'line') ? 'active' : '' ?>" title="กราฟเส้น"><i class="bi bi-graph-up"></i></a>
                            <a href="?period=<?= $period ?>&chart=bar&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($chart_type == 'bar') ? 'active' : '' ?>" title="กราฟแท่ง"><i class="bi bi-bar-chart"></i></a>
                        </div>
                        
                        <div class="btn-group btn-group-sm" role="group" aria-label="Sales Period"> 
                            <a href="?period=all&chart=<?= $chart_type ?>&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($period == 'all') ? 'active' : '' ?>">ทั้งหมด</a>
                            <a href="?period=today&chart=<?= $chart_type ?>&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($period == 'today') ? 'active' : '' ?>">วันนี้</a>
                            <a href="?period=3days&chart=<?= $chart_type ?>&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($period == '3days') ? 'active' : '' ?>">3 วัน</a>
                            <a href="?period=week&chart=<?= $chart_type ?>&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($period == 'week') ? 'active' : '' ?>">สัปดาห์</a>
                            <a href="?period=month&chart=<?= $chart_type ?>&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($period == 'month') ? 'active' : '' ?>">เดือน</a>
                            <a href="?period=year&chart=<?= $chart_type ?>&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($period == 'year') ? 'active' : '' ?>">ปี</a>
                            
                            <a href="?period=custom&chart=<?= $chart_type ?>&stats_period=<?= $stats_period ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-main-row" class="btn btn-outline-primary <?= ($period == 'custom') ? 'active' : '' ?>">กำหนดเอง</a>
                        </div>
                    </div>
                </div>
                
                <?php if ($period == 'custom'): ?>
                <div class="card-footer" id="custom-date-filter">
                    <form method="get" class="row g-2 align-items-center" action="#charts-main-row">
                        <input type="hidden" name="period" value="custom">
                        <input type="hidden" name="chart" value="<?= htmlspecialchars($chart_type) ?>">
                        <input type="hidden" name="stats_period" value="<?= htmlspecialchars($stats_period) ?>">
                        <input type="hidden" name="check_date" value="<?= htmlspecialchars($check_date) ?>">
                        
                        <div class="col-auto">
                            <label for="start_date_input" class="form-label small mb-0 fw-bold">จาก:</label>
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control form-control-sm" id="start_date_input" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-auto">
                            <label for="end_date_input" class="form-label small mb-0 fw-bold">ถึง:</label>
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control form-control-sm" id="end_date_input" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> กรอง</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                <div class="card-body"><canvas id="salesChart"></canvas></div>
            </div>

            <div class="row g-4 mt-4" id="charts-secondary-row">
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">ห้องว่าง</h6>
                            <form method="get" class="d-flex align-items-center" action="#charts-secondary-row">
                                <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
                                <input type="hidden" name="chart" value="<?= htmlspecialchars($chart_type) ?>">
                                <input type="hidden" name="stats_period" value="<?= htmlspecialchars($stats_period) ?>">
                                <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                                <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                                <label for="check_date_input" class="form-label small me-2 mb-0">ณ วันที่:</label> <input type="date" id="check_date_input" name="check_date" class="form-control form-control-sm" value="<?= htmlspecialchars($check_date) ?>" style="width: auto;"> <button type="submit" class="btn btn-primary btn-sm ms-2"><i class="bi bi-search"></i></button> </form>
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <canvas id="donutChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                                <h6 class="mb-0 me-3">สถิติการจอง</h6>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Stats Period">
                                    <a href="?stats_period=all&period=<?= $period ?>&chart=<?= $chart_type ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-secondary-row" class="btn btn-outline-secondary <?= ($stats_period == 'all') ? 'active' : '' ?>">ทั้งหมด</a>
                                    <a href="?stats_period=today&period=<?= $period ?>&chart=<?= $chart_type ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-secondary-row" class="btn btn-outline-secondary <?= ($stats_period == 'today') ? 'active' : '' ?>">วันนี้</a>
                                    <a href="?stats_period=3days&period=<?= $period ?>&chart=<?= $chart_type ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-secondary-row" class="btn btn-outline-secondary <?= ($stats_period == '3days') ? 'active' : '' ?>">3 วัน</a>
                                    <a href="?stats_period=week&period=<?= $period ?>&chart=<?= $chart_type ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-secondary-row" class="btn btn-outline-secondary <?= ($stats_period == 'week') ? 'active' : '' ?>">สัปดาห์</a>
                                    <a href="?stats_period=month&period=<?= $period ?>&chart=<?= $chart_type ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-secondary-row" class="btn btn-outline-secondary <?= ($stats_period == 'month') ? 'active' : '' ?>">เดือน</a>
                                    <a href="?stats_period=year&period=<?= $period ?>&chart=<?= $chart_type ?>&check_date=<?= $check_date ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>#charts-secondary-row" class="btn btn-outline-secondary <?= ($stats_period == 'year') ? 'active' : '' ?>">ปี</a>
                                </div>
                        </div>
                        <div class="card-body">
                            <canvas id="statsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-4"> <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header">การจองล่าสุด</div>
                        <div class="card-body p-0"> <div class="table-responsive"> 
                            <table class="table table-hover mb-0" id="recentBookingsTable">
                                <thead class="table-light"><tr><th class="ps-3">ชื่อผู้จอง</th><th>วันที่เข้าพัก</th><th>วันที่จอง</th></tr></thead>
                                <tbody>
                                    <?php if (empty($recent_bookings)): ?>
                                        <tr><td colspan="3" class="text-center text-muted p-4">ยังไม่มีการจอง</td></tr>
                                    <?php else: foreach ($recent_bookings as $b): ?>
                                        <tr>
                                            <td class="fw-bold ps-3"><?= htmlspecialchars($b['firstname'] . ' ' . $b['lastname']) ?></td>
                                            <td><?= date('d M Y', strtotime($b['checkin'])) ?></td>
                                            <td class="text-muted"><small><?= date('d M Y, H:i', strtotime($b['created_at'])) ?> น.</small></td> </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header">ห้องพักยอดนิยม</div>
                        <div class="card-body">
                            <?php if (empty($popular_rooms)): ?>
                                <div class="text-center text-muted p-4">ยังไม่มีข้อมูลการจอง</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php $rank = 1; foreach ($popular_rooms as $room): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2"> <div class="fw-medium"><strong><?= $rank++ ?>.</strong> <?= htmlspecialchars($room['rt_name']) ?></div>
                                            <span class="badge bg-primary rounded-pill fw-normal"><?= $room['booking_count'] ?> ครั้ง</span> </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <h4 class="h6 mt-5 mb-3 text-secondary fw-bold">การดำเนินการด่วน</h4> <div class="row g-4">
                <div class="col-lg-3 col-md-6"><a href="manage_room_types.php" class="text-decoration-none"><div class="card quick-action-card"><div class="card-body"><div class="icon-circle bg-primary"><i class="bi bi-pencil-square"></i></div><div class="action-title">จัดการห้องพัก</div><div class="action-desc">เพิ่ม แก้ไข หรือลบห้องพัก</div></div></div></a></div>
                <div class="col-lg-3 col-md-6"><a href="admin_confirm_booking.php" class="text-decoration-none"><div class="card quick-action-card"><div class="card-body"><div class="icon-circle bg-success"><i class="bi bi-check-circle"></i></div><div class="action-title">อนุมัติการจอง</div><div class="action-desc">ตรวจสอบและอนุมัติการจอง</div></div></div></a></div>
                <div class="col-lg-3 col-md-6"><a href="admin_calendar.php" class="text-decoration-none"><div class="card quick-action-card"><div class="card-body"><div class="icon-circle bg-danger"><i class="bi bi-calendar3"></i></div><div class="action-title">ปฏิทินการจอง</div><div class="action-desc">ดูภาพรวมการจองแบบปฏิทิน</div></div></div></a></div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ข้อมูลสำหรับส่งออก Excel
        const summaryData = <?php echo json_encode($summary_stats); ?>;
        const popularRoomsData = <?php echo json_encode($popular_rooms); ?>;
        const salesChartRawData = { labels: <?php echo $chart_labels_json; ?>, data: <?php echo $chart_data_json; ?> };
        const statsChartRawData = { labels: <?php echo $stats_labels_json; ?>, data: <?php echo $stats_data_json; ?> };
        const { jsPDF } = window.jspdf; // สำหรับ PDF
    </script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            
            // --- 1. กราฟยอดขาย (Sales Chart) ---
            const salesCtx = document.getElementById('salesChart')?.getContext('2d');
            if (salesCtx) { // ✅ Check if element exists
                const salesLabels = salesChartRawData.labels; // ใช้ข้อมูลจากตัวแปรด้านบน
                const salesData = salesChartRawData.data;   // ใช้ข้อมูลจากตัวแปรด้านบน
                const salesChartType = '<?php echo $chart_type; ?>';
                const isLineChart = salesChartType === 'line';
                const gradient = salesCtx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(0, 172, 230, 0.5)');
                gradient.addColorStop(1, 'rgba(0, 172, 230, 0.05)');
                new Chart(salesCtx, { /* ... chart config ... */
                    type: salesChartType,
                    data: { labels: salesLabels, datasets: [{ label: 'ยอดขาย', data: salesData, backgroundColor: isLineChart ? gradient : '#00ace6', borderColor: '#00ace6', borderWidth: isLineChart ? 3 : 0, fill: isLineChart, tension: isLineChart ? 0.4 : 0, pointBackgroundColor: '#00ace6', pointBorderColor: '#fff', pointHoverRadius: 6, pointRadius: 4, }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#000', titleFont: { size: 14, family: 'Sarabun' }, bodyFont: { size: 12, family: 'Sarabun' }, callbacks: { label: function(context) { let label = context.dataset.label || ''; if (label) { label += ': '; } if (context.parsed.y !== null) { label += new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(context.parsed.y); } return label; } } } }, scales: { y: { beginAtZero: true, grid: { display: true, drawBorder: false }, ticks: { display: true, font: { family: 'Sarabun' }, callback: function(value) { return '฿' + new Intl.NumberFormat('th-TH').format(value); } } }, x: { grid: { display: false }, ticks: { font: { family: 'Sarabun', weight: '500' }, color: '#555' } } } }
                });
            }

            // --- 2. กราฟโดนัท (Donut Chart) ---
            const donutCtx = document.getElementById('donutChart')?.getContext('2d');
            if (donutCtx) { // ✅ Check if element exists
                const donutData = <?php echo $donut_data_json; ?>;
                new Chart(donutCtx, { /* ... chart config ... */
                    type: 'doughnut',
                    data: { labels: ['ห้องที่ถูกจอง', 'ห้องว่าง'], datasets: [{ label: 'สถานะห้อง', data: donutData, backgroundColor: ['#fd7e14', '#198754'], borderColor: '#fff', borderWidth: 2 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { family: 'Sarabun', size: 14 } } }, tooltip: { titleFont: { family: 'Sarabun' }, bodyFont: { family: 'Sarabun' }, callbacks: { label: function(context) { return ' ' + context.label + ': ' + context.raw + ' ห้อง'; } } } } }
                });
            }

            // --- 3. กราฟเส้นสถิติ (Stats Line Chart) ---
            const statsCtx = document.getElementById('statsChart')?.getContext('2d');
            if (statsCtx) { // ✅ Check if element exists
                const statsLabels = statsChartRawData.labels; // ใช้ข้อมูลจากตัวแปรด้านบน
                const statsData = statsChartRawData.data;   // ใช้ข้อมูลจากตัวแปรด้านบน
                new Chart(statsCtx, { /* ... chart config ... */
                    type: 'line',
                    data: { labels: statsLabels, datasets: [{ label: 'จำนวนการจอง', data: statsData, backgroundColor: 'rgba(220, 53, 69, 0.1)', borderColor: '#dc3545', borderWidth: 2, fill: true, tension: 0.1 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0, font: { family: 'Sarabun' }, callback: function(value) { return value + ' ครั้ง'; } } }, x: { ticks: { font: { family: 'Sarabun' } } } } }
                });
            }

            // --- 7. JAVASCRIPT สำหรับปุ่ม EXPORT ---
            const loadingOverlay = document.getElementById('loading-overlay');

            // 7.1 ปุ่มพิมพ์
            document.getElementById('printButton').addEventListener('click', function(e) {
                e.preventDefault();
                window.print();
            });

            // 7.2 ปุ่ม PDF (เต็มหน้า)
            document.getElementById('pdfButton').addEventListener('click', function(e) {
                e.preventDefault();
                loadingOverlay.style.display = 'block';
                
                // ใช้ .content-wrapper เป็นเป้าหมาย
                const content = document.querySelector('.content-wrapper');
                
                html2canvas(content, {
                    scale: 2, // เพิ่มความละเอียด
                    useCORS: true,
                    ignoreElements: (element) => element.id === 'report-button-group' 
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new jsPDF('p', 'mm', 'a4'); 
                    const pdfWidth = pdf.internal.pageSize.getWidth();
                    const pageHeight = pdf.internal.pageSize.getHeight();
                    const margin = 10;
                    
                    const canvasWidth = canvas.width;
                    const canvasHeight = canvas.height;
                    
                    const imgWidth = pdfWidth - (margin * 2);
                    const imgHeight = (canvasHeight * imgWidth) / canvasWidth;
                    
                    let heightLeft = imgHeight;
                    let position = margin; 

                    pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
                    heightLeft -= (pageHeight - (margin * 2));

                    while (heightLeft > 0) {
                        pdf.addPage();
                        position = -heightLeft + margin; 
                        pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
                        heightLeft -= (pageHeight - (margin * 2));
                    }

                    pdf.save('dashboard-report-full.pdf'); // เปลี่ยนชื่อไฟล์
                    loadingOverlay.style.display = 'none';

                }).catch(err => {
                    console.error("Error generating PDF:", err);
                    loadingOverlay.style.display = 'none';
                    alert('เกิดข้อผิดพลาดในการสร้าง PDF');
                });
            });

            // 7.3 ปุ่ม PDF (สรุป)
            document.getElementById('summaryPdfButton').addEventListener('click', function(e) {
                e.preventDefault();
                loadingOverlay.style.display = 'block';
                
                // ใช้ #summary-print-area เป็นเป้าหมาย
                const summaryContent = document.getElementById('summary-print-area');
                
                html2canvas(summaryContent, {
                    scale: 2, // เพิ่มความละเอียด
                    useCORS: true,
                    width: 800 // กำหนดความกว้างให้ตรงกับ CSS
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const pdfWidth = pdf.internal.pageSize.getWidth();
                    const margin = 10;

                    const canvasWidth = canvas.width;
                    const canvasHeight = canvas.height;
                    
                    // คำนวณอัตราส่วนสำหรับ A4
                    const imgWidth = pdfWidth - (margin * 2);
                    const imgHeight = (canvasHeight * imgWidth) / canvasWidth;
                    
                    pdf.addImage(imgData, 'PNG', margin, margin, imgWidth, imgHeight);
                    
                    pdf.save('dashboard-report-summary.pdf'); // เปลี่ยนชื่อไฟล์
                    loadingOverlay.style.display = 'none';

                }).catch(err => {
                    console.error("Error generating Summary PDF:", err);
                    loadingOverlay.style.display = 'none';
                    alert('เกิดข้อผิดพลาดในการสร้าง PDF สรุป');
                });
            });


            // 7.4 ปุ่ม Excel
            document.getElementById('excelButton').addEventListener('click', function(e) {
                e.preventDefault();
                loadingOverlay.style.display = 'block';
                
                try {
                    const wb = XLSX.utils.book_new();

                    // Sheet 1: ภาพรวม
                    const ws1 = XLSX.utils.aoa_to_sheet(summaryData);
                    XLSX.utils.book_append_sheet(wb, ws1, "ภาพรวม");

                    // Sheet 2: ข้อมูลยอดขาย (จากกราฟ)
                    const salesExport = salesChartRawData.labels.map((label, index) => ({
                        'ช่วงเวลา': label,
                        'ยอดขาย (บาท)': salesChartRawData.data[index]
                    }));
                    const ws2 = XLSX.utils.json_to_sheet(salesExport);
                    XLSX.utils.book_append_sheet(wb, ws2, "ข้อมูลยอดขาย");

                    // Sheet 3: สถิติการจอง (จากกราฟ)
                    const statsExport = statsChartRawData.labels.map((label, index) => ({
                        'ช่วงเวลา': label,
                        'จำนวนการจอง': statsChartRawData.data[index]
                    }));
                    const ws3 = XLSX.utils.json_to_sheet(statsExport);
                    XLSX.utils.book_append_sheet(wb, ws3, "สถิติการจอง");

                    // Sheet 4: การจองล่าสุด (จากตาราง)
                    const recentTable = document.getElementById('recentBookingsTable');
                    if (recentTable) {
                        const ws4 = XLSX.utils.table_to_sheet(recentTable);
                        XLSX.utils.book_append_sheet(wb, ws4, "การจองล่าสุด");
                    }
                    
                    // Sheet 5: ห้องยอดนิยม (จากข้อมูล JS)
                    const popularExport = popularRoomsData.map((room, index) => ({
                        'อันดับ': index + 1,
                        'ชื่อห้อง': room.rt_name,
                        'จำนวนครั้ง': room.booking_count
                    }));
                    const ws5 = XLSX.utils.json_to_sheet(popularExport);
                    XLSX.utils.book_append_sheet(wb, ws5, "ห้องยอดนิยม");

                    // สร้างและดาวน์โหลดไฟล์
                    XLSX.writeFile(wb, 'dashboard-export-<?= date('Y-m-d') ?>.xlsx');
                    
                    loadingOverlay.style.display = 'none';

                } catch (err) {
                    console.error("Error generating Excel:", err);
                    loadingOverlay.style.display = 'none';
                    alert('เกิดข้อผิดพลาดในการสร้าง Excel');
                }
            });

        });
    </script>


    <div id="summary-print-area" style="position: absolute; left: -9999px; top: 0; width: 800px; background: #fff; padding: 40px; font-family: 'Sarabun', sans-serif; color: #000; line-height: 1.6;">
        
        <h1 style="font-size: 24px; font-weight: bold; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
            รายงานสรุป Dashboard
        </h1>
        <p style="font-size: 14px; margin-bottom: 25px;">วันที่ออกรายงาน: <?= date('d M Y H:i') ?> น.</p>
    
        <h2 style="font-size: 18px; font-weight: bold; margin-top: 30px; margin-bottom: 15px;">ภาพรวมระบบ</h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 16px;">
            <?php foreach ($summary_stats as $index => $stat): if($index == 0) continue; // ข้าม Header ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;"><?= htmlspecialchars($stat[0]) ?></td>
                <td style="padding: 10px; font-weight: bold; text-align: right;"><?= htmlspecialchars($stat[1]) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    
        <h2 style="font-size: 18px; font-weight: bold; margin-top: 30px; margin-bottom: 15px;">สรุปยอดขาย (<?= htmlspecialchars($period_label) ?>)</h2>
        <p style="font-size: 16px; margin-left: 10px;">ยอดรวม: <strong><?= $total_revenue_filtered ?> บาท</strong></p>
    
        <h2 style="font-size: 18px; font-weight: bold; margin-top: 30px; margin-bottom: 15px;">ห้องพักยอดนิยม</h2>
        <?php if (empty($popular_rooms)): ?>
            <p style="font-size: 16px; margin-left: 10px;">ไม่มีข้อมูล</p>
        <?php else: ?>
            <ol style="font-size: 16px; margin-left: 30px; line-height: 1.8;">
                <?php foreach ($popular_rooms as $room): ?>
                    <li><?= htmlspecialchars($room['rt_name']) ?> (<?= $room['booking_count'] ?> ครั้ง)</li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    
        <h2 style="font-size: 18px; font-weight: bold; margin-top: 30px; margin-bottom: 15px;">การจองล่าสุด</h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 15px;">
            <tr style="background-color: #f4f4f4; border-bottom: 1px solid #ddd;">
                <th style="padding: 10px; text-align: left;">ชื่อผู้จอง</th>
                <th style="padding: 10px; text-align: left;">วันที่เข้าพัก</th>
                <th style="padding: 10px; text-align: left;">วันที่จอง</th>
            </tr>
            <?php if (empty($recent_bookings)): ?>
                <tr><td colspan="3" style="padding: 10px; text-align: center;">ไม่มีข้อมูล</td></tr>
            <?php else: foreach ($recent_bookings as $b): ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;"><?= htmlspecialchars($b['firstname'] . ' ' . $b['lastname']) ?></td>
                <td style="padding: 10px;"><?= date('d M Y', strtotime($b['checkin'])) ?></td>
                <td style="padding: 10px;"><?= date('d M Y, H:i', strtotime($b['created_at'])) ?> น.</td>
            </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

</body>
</html>
<?php
mysqli_close($conn);
?>