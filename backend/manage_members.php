<?php
// 1. เริ่ม session เพื่อให้เราสามารถดึง ID ของแอดมินได้
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าไฟล์นี้ถูกเรียกผ่าน admin.php ไม่ได้ถูกเข้าถึงโดยตรง
if (!defined('ADMIN_EMBED')) {
    exit('Direct access denied');
}

// เรียกใช้ connectdb.php ซึ่งจะสร้างตัวแปร $conn (แบบ MySQLi)
require_once __DIR__ . '/connectdb.php';

// 2. ฟังก์ชันสำหรับสร้าง Log (เหมือนเดิม)
/**
 * บันทึก Log การกระทำของแอดมินลงในฐานข้อมูล
 *
 * @param mysqli $conn - ตัวแปรเชื่อมต่อฐานข้อมูล
 * @param int $admin_id - ID ของแอดมินที่กระทำ
 * @param string $action - ประเภทการกระทำ (เช่น 'UPDATE_USER', 'DELETE_USER')
 * @param int $target_id - ID ของผู้ใช้ที่ถูกกระทำ
 * @param string $details - รายละเอียด (เช่น แก้ไขอะไรไปบ้าง)
 */
function create_log($conn, $admin_id, $action, $target_id, $details = '') {
    // ป้องกันกรณี admin_id เป็น null หรือไม่ได้ล็อกอิน
    if (empty($admin_id)) {
        $admin_id = 0; // 0 หมายถึง "System" หรือ "Unknown Admin"
    }
    
    $sql = "INSERT INTO admin_logs (admin_user_id, action_type, target_user_id, details) VALUES (?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($conn, $sql);
    // "isis" = integer, string, integer, string
    mysqli_stmt_bind_param($stmt_log, "isis", $admin_id, $action, $target_id, $details);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
}

// 3. ดึง ID ของแอดมินที่กำลังล็อกอิน (สำคัญ!)
$admin_logging_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;


// --- ส่วนจัดการ Logic (รับค่าจาก Form POST สำหรับ เพิ่ม/แก้ไข/ลบ) ---
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; // ⭐️ [MODIFIED] ใช้ตัวแปร $action

    // ⭐️ [NEW] ---- จัดการการเพิ่ม User ใหม่ (คัดลอกจาก manage_admins) ----
    if ($action === 'add_user') {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password !== $confirm_password) {
            $message = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน!';
            $message_type = 'danger';
        } else {
            // ตรวจสอบว่ามีอีเมลนี้ในระบบแล้วหรือยัง
            $stmt_check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt_check, "s", $email);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $message = 'อีเมลนี้ถูกใช้งานแล้วในระบบ!';
                $message_type = 'danger';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // ⭐️ [NEW] เปลี่ยน 'admin' เป็น 'user'
                $sql = "INSERT INTO users (first_name, last_name, email, phone, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, 'user', NOW())";
                $stmt_insert = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt_insert, "sssss", $first_name, $last_name, $email, $phone, $hashed_password);
                
                if (mysqli_stmt_execute($stmt_insert)) {
                    $new_user_id = mysqli_insert_id($conn); // ดึง ID ของ user ที่เพิ่งสร้าง
                    $message = 'เพิ่มข้อมูลสมาชิกสำเร็จ!';
                    $message_type = 'success';
                    
                    // ⭐️ [NEW] LOGGING: บันทึก Log การ "เพิ่ม User"
                    $log_details = "สร้าง User ใหม่: ID: $new_user_id, ชื่อ: '$first_name $last_name', อีเมล: '$email'";
                    create_log($conn, $admin_logging_id, 'CREATE_USER', $new_user_id, $log_details);
                    
                } else {
                    $message = 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' . mysqli_stmt_error($stmt_insert);
                    $message_type = 'danger';
                }
                mysqli_stmt_close($stmt_insert);
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    // ---- จัดการการแก้ไขข้อมูล ----
    if ($action === 'update_user') {
        
        $target_user_id = (int)$_POST['user_id'];
        $new_first_name = $_POST['first_name'];
        $new_last_name = $_POST['last_name'];
        $new_email = $_POST['email'];
        $new_phone = $_POST['phone'];

        // 4.1 LOGGING: ดึงข้อมูล "ก่อน" แก้ไข เพื่อเปรียบเทียบ
        $old_data = null;
        $sql_old = "SELECT first_name, last_name, email, phone FROM users WHERE user_id = ?";
        $stmt_old = mysqli_prepare($conn, $sql_old);
        mysqli_stmt_bind_param($stmt_old, "i", $target_user_id);
        if (mysqli_stmt_execute($stmt_old)) {
            $result_old = mysqli_stmt_get_result($stmt_old);
            $old_data = mysqli_fetch_assoc($result_old);
        }
        mysqli_stmt_close($stmt_old);

        // ทำการ UPDATE (โค้ดเดิมของคุณ)
        $sql_update = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE user_id = ? AND role = 'user'";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "ssssi", $new_first_name, $new_last_name, $new_email, $new_phone, $target_user_id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            $message = 'แก้ไขข้อมูลสมาชิกสำเร็จ!';
            $message_type = 'success';

            // 4.2 LOGGING: บันทึก Log "หลัง" แก้ไขสำเร็จ
            if ($old_data) {
                $details_arr = [];
                if ($old_data['first_name'] != $new_first_name) $details_arr[] = "ชื่อ: '{$old_data['first_name']}' -> '{$new_first_name}'";
                if ($old_data['last_name'] != $new_last_name) $details_arr[] = "นามสกุล: '{$old_data['last_name']}' -> '{$new_last_name}'";
                if ($old_data['email'] != $new_email) $details_arr[] = "อีเมล: '{$old_data['email']}' -> '{$new_email}'";
                if ($old_data['phone'] != $new_phone) $details_arr[] = "เบอร์โทร: '{$old_data['phone']}' -> '{$new_phone}'";
                
                if (empty($details_arr)) {
                    $details = "กดแก้ไข ID: $target_user_id แต่ไม่มีการเปลี่ยนแปลงข้อมูล";
                } else {
                    $details = "แก้ไข ID: $target_user_id. เปลี่ยน -> " . implode(", ", $details_arr);
                }
                
                create_log($conn, $admin_logging_id, 'UPDATE_USER', $target_user_id, $details);
            }

        } else {
            $message = 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
        mysqli_stmt_close($stmt_update);
    }
    
    // ---- จัดการการลบข้อมูล ----
    if ($action === 'delete_user') {
        
        $target_user_id = (int)$_POST['user_id'];

        // 5.1 LOGGING: ดึงข้อมูล "ก่อน" ลบ (สำคัญมาก!)
        $user_info = "ID: $target_user_id"; // ค่าเริ่มต้น
        $sql_old_del = "SELECT first_name, last_name, email FROM users WHERE user_id = ?";
        $stmt_old_del = mysqli_prepare($conn, $sql_old_del);
        mysqli_stmt_bind_param($stmt_old_del, "i", $target_user_id);
        if (mysqli_stmt_execute($stmt_old_del)) {
            $result_old_del = mysqli_stmt_get_result($stmt_old_del);
            if ($old_data_del = mysqli_fetch_assoc($result_old_del)) {
                $user_info = "ID: $target_user_id, ชื่อ: '{$old_data_del['first_name']} {$old_data_del['last_name']}', อีเมล: '{$old_data_del['email']}'";
            }
        }
        mysqli_stmt_close($stmt_old_del);

        // ทำการ DELETE (โค้ดเดิมของคุณ)
        $sql_delete = "DELETE FROM users WHERE user_id = ? AND role = 'user'";
        $stmt_delete = mysqli_prepare($conn, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $target_user_id);
        
        if (mysqli_stmt_execute($stmt_delete)) {
            $message = 'ลบข้อมูลสมาชิกอย่างถาวรสำเร็จ!';
            $message_type = 'success';

            // 5.2 LOGGING: บันทึก Log "หลัง" ลบสำเร็จ
            $details = "ลบผู้ใช้: " . $user_info;
            create_log($conn, $admin_logging_id, 'DELETE_USER', $target_user_id, $details);

        } else {
            $message = 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
        mysqli_stmt_close($stmt_delete);
    }
}

// --- ส่วนจัดการการแสดงผล (ค้นหา และ แบ่งหน้า) ---
// (ส่วนนี้เหมือนเดิมทุกประการ)
$records_per_page = 10;
$current_page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "WHERE role = 'user'";
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clause .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    array_push($params, $search_term, $search_term, $search_term);
    $param_types .= "sss";
}

$sql_count = "SELECT COUNT(user_id) as total FROM users $where_clause";
$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($search_query)) {
    mysqli_stmt_bind_param($stmt_count, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_records / $records_per_page);
mysqli_stmt_close($stmt_count);

$users = [];
$sql_select = "SELECT user_id, first_name, last_name, email, phone, created_at, updated_at FROM users $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt_select = mysqli_prepare($conn, $sql_select);
array_push($params, $records_per_page, $offset);
$param_types .= "ii"; 
mysqli_stmt_bind_param($stmt_select, $param_types, ...$params);
mysqli_stmt_execute($stmt_select);
$result = mysqli_stmt_get_result($stmt_select);
if ($result) {
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    echo "<div class='alert alert-danger'>เกิดข้อผิดพลาดในการดึงข้อมูล: " . mysqli_error($conn) . "</div>";
}
mysqli_stmt_close($stmt_select);
mysqli_close($conn); 
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<?php if ($message): ?>
<div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="card-title mb-0"><i class="bi bi-people-fill me-2"></i>จัดการสมาชิก (Users)</h5>
        <div class="d-flex gap-2">
            <form method="GET" action="admin.php" class="d-flex" role="search">
                <input type="hidden" name="page" value="manage_members">
                <input class="form-control form-control-sm me-2" type="search" name="search" placeholder="ค้นหาชื่อ, อีเมล..." value="<?= htmlspecialchars($search_query) ?>" aria-label="Search">
                <button class="btn btn-sm btn-outline-secondary" type="submit">ค้นหา</button>
            </form>
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-circle-fill"></i> เพิ่มสมาชิก
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">ชื่อ-นามสกุล</th>
                        <th scope="col">อีเมล</th>
                        <th scope="col">เบอร์โทร</th>
                        <th scope="col">สร้างเมื่อ</th>
                        <th scope="col" class="text-center">เครื่องมือ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">
                                <?= !empty($search_query) ? "ไม่พบข้อมูลที่ตรงกับ '" . htmlspecialchars($search_query) . "'" : "ไม่พบข้อมูลสมาชิก" ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $index => $user): ?>
                        <tr>
                            <th scope="row"><?= $offset + $index + 1 ?></th>
                            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone']) ?></td>
                            <td><?= date('d M Y, H:i', strtotime($user['created_at'])) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary edit-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-id="<?= $user['user_id'] ?>"
                                        data-fname="<?= htmlspecialchars($user['first_name'], ENT_QUOTES) ?>"
                                        data-lname="<?= htmlspecialchars($user['last_name'], ENT_QUOTES) ?>"
                                        data-email="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>"
                                        data-phone="<?= htmlspecialchars($user['phone'], ENT_QUOTES) ?>">
                                    <i class="bi bi-pencil-square"></i> แก้ไข
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" 
                                        data-id="<?= $user['user_id'] ?>" 
                                        data-name="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES) ?>">
                                    <i class="bi bi-trash3-fill"></i> ลบ
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-3">
                <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=manage_members&search=<?= urlencode($search_query) ?>&p=<?= $current_page - 1 ?>">Previous</a>
                </li>
                
                <?php 
                // Logic การแสดงผลเลขหน้า (เหมือนเดิม)
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);

                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=manage_members&search='.urlencode($search_query).'&p=1">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=manage_members&search=<?= urlencode($search_query) ?>&p=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; 

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=manage_members&search='.urlencode($search_query).'&p='.$total_pages.'">'.$total_pages.'</a></li>';
                }
                ?>

                <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=manage_members&search=<?= urlencode($search_query) ?>&p=<?= $current_page + 1 ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="addUserModalLabel"><i class="bi bi-person-plus-fill me-2"></i>เพิ่มสมาชิกใหม่</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="addUserForm" method="POST" action="admin.php?page=manage_members">
        <div class="modal-body">
          <input type="hidden" name="action" value="add_user">
          <div class="mb-3">
              <label for="add-first-name" class="form-label">ชื่อจริง</label>
              <input type="text" class="form-control" id="add-first-name" name="first_name" required>
          </div>
          <div class="mb-3">
              <label for="add-last-name" class="form-label">นามสกุล</label>
              <input type="text" class="form-control" id="add-last-name" name="last_name" required>
          </div>
          <div class="mb-3">
              <label for="add-email" class="form-label">อีเมล</label>
              <input type="email" class="form-control" id="add-email" name="email" required>
          </div>
          <div class="mb-3">
              <label for="add-phone" class="form-label">เบอร์โทร</label>
              <input type="text" class="form-control" id="add-phone" name="phone">
          </div>
          <hr>
          <div class="mb-3">
              <label for="add-password" class="form-label">รหัสผ่าน</label>
              <input type="password" class="form-control" id="add-password" name="password" required>
          </div>
          <div class="mb-3">
              <label for="add-confirm-password" class="form-label">ยืนยันรหัสผ่าน</label>
              <input type="password" class="form-control" id="add-confirm-password" name="confirm_password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-success">เพิ่มสมาชิก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="editUserModalLabel"><i class="bi bi-pencil-fill me-2"></i>แก้ไขข้อมูลสมาชิก</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editUserForm" method="POST" action="admin.php?page=manage_members&search=<?= urlencode($search_query) ?>&p=<?= $current_page ?>">
        <div class="modal-body">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" id="edit-user-id" name="user_id">
            <div class="mb-3">
                <label for="edit-first-name" class="form-label">ชื่อจริง</label>
                <input type="text" class="form-control" id="edit-first-name" name="first_name" required>
            </div>
            <div class="mb-3">
                <label for="edit-last-name" class="form-label">นามสกุล</label>
                <input type="text" class="form-control" id="edit-last-name" name="last_name" required>
            </div>
            <div class="mb-3">
                <label for="edit-email" class="form-label">อีเมล</label>
                <input type="email" class="form-control" id="edit-email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="edit-phone" class="form-label">เบอร์โทร</label>
                <input type="text" class="form-control" id="edit-phone" name="phone" required>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
        </div>
      </form>
    </div>
  </div>
</div>

<form id="deleteUserForm" method="POST" action="admin.php?page=manage_members&search=<?= urlencode($search_query) ?>&p=<?= $current_page ?>" style="display: none;">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" id="delete-user-id" name="user_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- จัดการการส่งข้อมูลไปยัง Modal แก้ไข --- (โค้ดเดิม)
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-id');
            const firstName = button.getAttribute('data-fname');
            const lastName = button.getAttribute('data-lname');
            const email = button.getAttribute('data-email');
            const phone = button.getAttribute('data-phone');

            const modalForm = editUserModal.querySelector('form');
            modalForm.querySelector('#edit-user-id').value = userId;
            modalForm.querySelector('#edit-first-name').value = firstName;
            modalForm.querySelector('#edit-last-name').value = lastName;
            modalForm.querySelector('#edit-email').value = email;
            modalForm.querySelector('#edit-phone').value = phone;
        });
    }
    
    // ⭐️ [NEW] --- จัดการการยืนยันก่อน "เพิ่ม" (ใช้ SweetAlert2) ---
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(event) {
            event.preventDefault(); 
            
            // ตรวจสอบรหัสผ่านก่อน
            const pass = this.querySelector('#add-password').value;
            const confirmPass = this.querySelector('#add-confirm-password').value;
            
            if (pass !== confirmPass) {
                 Swal.fire(
                    'เกิดข้อผิดพลาด!',
                    'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน',
                    'error'
                );
                return; // หยุดทำงาน
            }

            Swal.fire({
                title: 'ยืนยันการเพิ่มสมาชิก',
                text: "คุณต้องการเพิ่มสมาชิกคนนี้ใช่หรือไม่?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754', // สีเขียว
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, เพิ่มเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    // --- จัดการการยืนยันก่อน "แก้ไข" (ใช้ SweetAlert2) --- (โค้ดเดิม)
    const editForm = document.getElementById('editUserForm');
    if(editForm) {
        editForm.addEventListener('submit', function(event) {
            event.preventDefault(); // หยุดการ submit ปกติ
            
            Swal.fire({
                title: 'ยืนยันการแก้ไข',
                text: "คุณต้องการบันทึกการเปลี่ยนแปลงข้อมูลนี้ใช่หรือไม่?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, บันทึกเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit(); // ถ้าผู้ใช้ยืนยัน ก็ทำการ submit ฟอร์ม
                }
            });
        });
    }

    // --- จัดการการยืนยันก่อน "ลบ" (ใช้ SweetAlert2) --- (โค้ดเดิม)
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                html: `คุณต้องการลบบัญชีของ <strong>${userName}</strong> อย่างถาวรใช่หรือไม่?<br><strong class='text-danger'>การกระทำนี้ไม่สามารถย้อนกลับได้!</strong>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-user-id').value = userId;
                    document.getElementById('deleteUserForm').submit();
                }
            });
        });
    });

    // ทำให้ Alert Message (Success/Error) หายไปเองใน 5 วินาที (โค้ดเดิม)
    const alertMessage = document.querySelector('.alert-dismissible');
    if(alertMessage) {
        setTimeout(() => {
            // ⭐️ [MODIFIED] แก้ไขเล็กน้อยเพื่อให้ปิด Alert ได้ชัวร์ขึ้น
             const bsAlertInstance = bootstrap.Alert.getInstance(alertMessage);
            if(bsAlertInstance) {
                bsAlertInstance.close();
            } else if (typeof bootstrap !== 'undefined') {
                 new bootstrap.Alert(alertMessage).close();
            }
        }, 5000);
    }
});
</script>