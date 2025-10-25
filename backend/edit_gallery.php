<?php
// ⭐️ 1. เริ่ม session (หากยังไม่ได้เริ่ม)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าไฟล์นี้ถูกเรียกผ่าน admin.php ไม่ได้ถูกเข้าถึงโดยตรง
if (!defined('ADMIN_EMBED')) {
    exit('Direct access denied');
}

// *** ID ของแอดมินที่ล็อกอินอยู่ (โค้ดเดิมของคุณ) ***
$current_admin_id = $_SESSION['user_id'] ?? 0; // ใช้สำหรับป้องกันการลบตัวเอง และใช้บันทึก Log

// เรียกใช้ connectdb.php
require_once __DIR__ . '/connectdb.php';

// ⭐️ 2. คัดลอกฟังก์ชัน create_log มาไว้ที่นี่
/**
 * บันทึก Log การกระทำของแอดมินลงในฐานข้อมูล
 *
 * @param mysqli $conn - ตัวแปรเชื่อมต่อฐานข้อมูล
 * @param int $admin_id - ID ของแอดมินที่กระทำ
 * @param string $action - ประเภทการกระทำ (เช่น 'CREATE_ADMIN')
 * @param int $target_id - ID ของผู้ใช้ที่ถูกกระทำ
 * @param string $details - รายละเอียด
 */
function create_log($conn, $admin_id, $action, $target_id, $details = '') {
    if (empty($admin_id)) $admin_id = 0;
    if (empty($target_id)) $target_id = 0;
    
    $sql_log = "INSERT INTO admin_logs (admin_user_id, action_type, target_user_id, details) VALUES (?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($conn, $sql_log);
    if ($stmt_log) {
        mysqli_stmt_bind_param($stmt_log, "isis", $admin_id, $action, $target_id, $details);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);
    }
}


/* โครงสร้างไดเรกทอรี (เหมือนเดิม) */
$PROJECT_DIR = realpath(__DIR__ . '/..'); if ($PROJECT_DIR === false) { die('Project root not found'); }
define('UPLOAD_DIR', $PROJECT_DIR . '/uploads');
define('PUBLIC_URL', '../');
define('UPLOAD_URL', PUBLIC_URL . 'uploads/');

require_once __DIR__ . '/connectdb.php';
if (!isset($conn) || !($conn instanceof mysqli)) { die('ไม่พบการเชื่อมต่อฐานข้อมูล'); }
mysqli_set_charset($conn, 'utf8mb4');

/* ===== Helpers (เหมือนเดิม) ===== */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function thaidate($dt){ return date("d/m/Y H:i", strtotime($dt)); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function csrf_ok($t){ return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t ?? ''); }
function slugify_filename($s){ $s = trim((string)$s); $s = preg_replace('/[^\p{Thai}A-Za-z0-9\._-]+/u', '-', $s); $s = preg_replace('/-+/u', '-', $s); $s = trim($s, '-._ '); if ($s === '' ) $s = 'image'; if (mb_strlen($s,'UTF-8') > 80) { $s = mb_substr($s, 0, 80, 'UTF-8'); } return $s; }
function valid_image($tmp_path, $client_mime){ $finfo=finfo_open(FILEINFO_MIME_TYPE); $real=finfo_file($finfo,$tmp_path); finfo_close($finfo); $allow=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif']; if(!isset($allow[$real])) return [false,null]; if($client_mime && stripos($client_mime,'image/')!==0) return [false,null]; return [true,$allow[$real]]; }
function next_available_name($base_no_ext, $ext){ $candidate = $base_no_ext . '.' . $ext; if (!file_exists(UPLOAD_DIR.'/'.$candidate)) return $candidate; for($i=1;$i<10000;$i++){ $candidate = $base_no_ext.'-'.$i.'.'.$ext; if (!file_exists(UPLOAD_DIR.'/'.$candidate)) return $candidate; } return $base_no_ext.'_'.bin2hex(random_bytes(3)).'.'.$ext; }
function flash($key, $val=null){ if (!isset($_SESSION['flash'])) $_SESSION['flash'] = []; if ($val===null){ $v=$_SESSION['flash'][$key]??null; unset($_SESSION['flash'][$key]); return $v; } $_SESSION['flash'][$key]=$val; }
function redirect_self(){ $qs = $_GET; unset($qs['t']); $url = basename($_SERVER['PHP_SELF']) . ($qs ? ('?'.http_build_query($qs)) : ''); $url .= (strpos($url,'?')!==false ? '&' : '?') . 't=' . time(); if (!headers_sent()){ while (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_clean(); } header('Cache-Control: no-store'); header('Location: '.$url, true, 303); exit; } $u = $url; echo '<script>location.replace(', json_encode($u), ');</script>','<noscript><meta http-equiv="refresh" content="0;url=', h($u), '"></noscript>'; exit; }

/* ===== Handle POST (เหมือนเดิมทุกประการ) ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? ''; $errors = []; $okmsg = null;
    if (in_array($action, ['create','update'], true)) {
        $id = (int)($_POST['id'] ?? 0); $gt_id = (int)($_POST['gt_id'] ?? 0);
        if ($gt_id<=0) $errors[]='กรุณาเลือกหมวดหมู่';
        $desired_base = slugify_filename(trim($_POST['image_filename'] ?? ''));
        $final_filename = null; $hasFile = isset($_FILES['image_file']) && is_array($_FILES['image_file']) && $_FILES['image_file']['error']!==UPLOAD_ERR_NO_FILE && trim($_FILES['image_file']['name'])!=='';
        if ($hasFile) {
            if ($_FILES['image_file']['error']===UPLOAD_ERR_OK) {
                if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR,0777,true);
                if (!is_writable(UPLOAD_DIR)) { $errors[]='โฟลเดอร์ uploads ไม่สามารถเขียนได้'; }
                else {
                    list($ok,$ext)=valid_image($_FILES['image_file']['tmp_name'], $_FILES['image_file']['type'] ?? '');
                    if(!$ok){ $errors[]='ไฟล์รูปไม่ถูกต้อง (JPG/PNG/WebP/GIF)'; }
                    else {
                        $base = $desired_base!=='' ? $desired_base : slugify_filename(pathinfo($_FILES['image_file']['name'], PATHINFO_FILENAME));
                        $base = date('Ymd_His').'_'.$base;
                        $final_filename = next_available_name($base, $ext);
                        if(!move_uploaded_file($_FILES['image_file']['tmp_name'], UPLOAD_DIR.'/'.$final_filename)){ $errors[]='อัปโหลดไฟล์ล้มเหลว'; $final_filename=null; }
                    }
                }
            } else { $errors[]='อัปโหลดไฟล์ล้มเหลว (รหัส '.$_FILES['image_file']['error'].')'; }
        } else if ($action==='create') { $errors[]='กรุณาเลือกไฟล์รูป'; }

        if (!$errors){
            if ($action==='create'){
                $stmt=$conn->prepare("INSERT INTO gallery_edit (image_filename, gt_id, upload_date) VALUES (?,?,NOW())");
                $stmt->bind_param("si",$final_filename,$gt_id);
                if($stmt->execute()){ $okmsg='บันทึกรายการใหม่สำเร็จ'; }
                else { $errors[]='บันทึก DB ล้มเหลว: '.$conn->error; if($final_filename && is_file(UPLOAD_DIR.'/'.$final_filename)) @unlink(UPLOAD_DIR.'/'.$final_filename); }
                $stmt->close();
            } else { // UPDATE
                $get=$conn->prepare("SELECT image_filename FROM gallery_edit WHERE g_id=?"); $get->bind_param("i",$id); $get->execute(); $res=$get->get_result(); $row=$res->fetch_assoc(); $get->close(); $old = $row['image_filename'] ?? null;
                if ($hasFile){ if($old && is_file(UPLOAD_DIR.'/'.$old)) @unlink(UPLOAD_DIR.'/'.$old); }
                else {
                    if ($old){
                        $ext = pathinfo($old, PATHINFO_EXTENSION); $base = $desired_base!=='' ? $desired_base : pathinfo($old, PATHINFO_FILENAME);
                        $base = slugify_filename($base); $new_name = next_available_name($base, $ext);
                        if ($new_name !== $old){ if (@rename(UPLOAD_DIR.'/'.$old, UPLOAD_DIR.'/'.$new_name)){ $final_filename = $new_name; } else { $final_filename = $old; $okmsg = '⚠ ไม่สามารถเปลี่ยนชื่อไฟล์ได้'; } }
                        else { $final_filename = $old; }
                    }
                }
                if(!$final_filename) $final_filename = $old;
                $stmt=$conn->prepare("UPDATE gallery_edit SET image_filename=?, gt_id=? WHERE g_id=?"); $stmt->bind_param("sii",$final_filename,$gt_id,$id);
                if($stmt->execute()){ $okmsg = $okmsg ? $okmsg : 'อัปเดตรายการสำเร็จ'; }
                else { $errors[]='อัปเดต DB ล้มเหลว: '.$conn->error; if(!$hasFile && $old && $final_filename && $final_filename !== $old){ @rename(UPLOAD_DIR.'/'.$final_filename, UPLOAD_DIR.'/'.$old); } if($hasFile && $final_filename && is_file(UPLOAD_DIR.'/'.$final_filename)) @unlink(UPLOAD_DIR.'/'.$final_filename); }
                $stmt->close();
            }
        }
    }
    if ($action==='delete') {
        $id=(int)($_POST['id'] ?? 0);
        $get=$conn->prepare("SELECT image_filename FROM gallery_edit WHERE g_id=?"); $get->bind_param("i",$id); $get->execute(); $res=$get->get_result(); $row=$res->fetch_assoc(); $get->close();
        $stmt=$conn->prepare("DELETE FROM gallery_edit WHERE g_id=?"); $stmt->bind_param("i",$id);
        if($stmt->execute()){ $old=$row['image_filename'] ?? null; if($old && is_file(UPLOAD_DIR.'/'.$old)) @unlink(UPLOAD_DIR.'/'.$old); $okmsg='ลบรายการสำเร็จ'; }
        else { $errors[]='ลบ DB ล้มเหลว: '.$conn->error; }
        $stmt->close();
    }
    if ($errors){ flash('err', implode('<br>', array_map('h', $errors))); } if ($okmsg){ flash('msg', h($okmsg)); }
    redirect_self(); // PRG
}

/* ===== Load data (เหมือนเดิม) ===== */
$categories=[]; $cat_rs = $conn->query("SELECT gt_id, gt_name FROM gallery_type ORDER BY gt_name ASC"); if ($cat_rs) while($r=$cat_rs->fetch_assoc()){ $categories[]=$r; }
$perPage = 10; $pg = max(1, (int)($_GET['pg'] ?? 1));
$totalRows = (int)($conn->query("SELECT COUNT(*) AS c FROM gallery_edit")->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage)); $pg = min($pg, $totalPages); $offset = ($pg - 1) * $perPage;
$items=[]; $stmt = $conn->prepare("SELECT g.g_id, g.image_filename, g.upload_date, g.gt_id, t.gt_name FROM gallery_edit g LEFT JOIN gallery_type t ON g.gt_id=t.gt_id ORDER BY g.upload_date DESC, g.g_id DESC LIMIT ? OFFSET ?"); $stmt->bind_param("ii", $perPage, $offset); $stmt->execute(); $res = $stmt->get_result(); while($r=$res->fetch_assoc()){ $items[]=$r; } $stmt->close();
$startN = $totalRows ? ($offset + 1) : 0; $endN = $offset + count($items);
function page_url($p){ $qs = $_GET; $qs['pg'] = $p; return basename($_SERVER['PHP_SELF']).'?'.http_build_query($qs); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>จัดการแกลเลอรี่</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    /* เพิ่ม CSS ตกแต่งเล็กน้อย */
    .img-thumbnail-sm {
        width: 60px; height: 60px; object-fit: cover; border-radius: 0.375rem;
    }
    .form-control-sm-search { /* ทำให้ช่องค้นหาไม่สูงเท่าปุ่ม */
        padding-top: 0.4rem; padding-bottom: 0.4rem;
    }
    .badge-category { /* สี Badge หมวดหมู่ */
        background-color: var(--bs-primary-bg-subtle);
        color: var(--bs-primary-text-emphasis);
        border: 1px solid var(--bs-primary-border-subtle);
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<div class="container-fluid px-lg-4"> <?php if ($m = flash('msg')): ?>
        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $m ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; if ($e = flash('err')): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $e ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mt-3">
        <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
            <h5 class="card-title mb-0"><i class="bi bi-images me-2 text-primary"></i>จัดการแกลเลอรี่</h5>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" id="q" class="form-control form-control-sm-search border-start-0" placeholder="ค้นหาชื่อไฟล์, หมวดหมู่...">
                </div>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal" id="openCreate">
                    <i class="bi bi-plus-lg me-1"></i> เพิ่มรูปภาพ
                </button>
            </div>
        </div>
        <div class="card-body p-0"> <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 caption-top"> <caption>
                        <div class="px-3 pt-2 text-muted small">
                            แสดง <?= $startN ?: 0 ?>–<?= $endN ?: 0 ?> จากทั้งหมด <?= $totalRows ?> รายการ
                        </div>
                    </caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-center" style="width: 5%;">#</th>
                            <th scope="col" class="text-center" style="width: 8%;">รูป</th>
                            <th scope="col">ชื่อไฟล์</th>
                            <th scope="col" style="width: 20%;">หมวดหมู่</th>
                            <th scope="col" style="width: 15%;">อัปโหลดเมื่อ</th>
                            <th scope="col" class="text-center" style="width: 15%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="tbody">
                        <?php if(!$items): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-info-circle me-2"></i>ยังไม่มีข้อมูล</td></tr>
                        <?php else: $i=$startN; foreach($items as $it): ?>
                            <tr class="row-item"
                                data-title="<?= strtolower(h($it['image_filename'])) ?>"
                                data-catname="<?= strtolower(h($it['gt_name'])) ?>"
                                data-gtid="<?= (int)$it['gt_id'] ?>"
                                data-id="<?= (int)$it['g_id'] ?>">
                                <td class="text-center fw-bold"><?= $i++ ?></td>
                                <td class="text-center">
                                    <?php if (!empty($it['image_filename']) && is_file(UPLOAD_DIR.'/'.$it['image_filename'])): ?>
                                        <a href="<?= h(UPLOAD_URL.$it['image_filename']) ?>" target="_blank" rel="noopener">
                                            <img class="img-thumbnail-sm" src="<?= h(UPLOAD_URL.$it['image_filename']) ?>" alt="Preview">
                                        </a>
                                    <?php else: ?>
                                        <i class="bi bi-image-alt text-muted fs-4"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-break"><?= h($it['image_filename']) ?></div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill badge-category"><?= h($it['gt_name'] ?: 'N/A') ?></span>
                                </td>
                                <td>
                                    <?php [$d,$t]=explode(' ', thaidate($it['upload_date'])); ?>
                                    <div class="small"><?= $d ?></div>
                                    <div class="text-muted small"><?= $t ?> น.</div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-edit">
                                            <i class="bi bi-pencil-square"></i> <span class="d-none d-md-inline">แก้ไข</span>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-del">
                                            <i class="bi bi-trash3-fill"></i> <span class="d-none d-md-inline">ลบ</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div><?php if ($totalPages > 1): ?>
            <div class="card-footer bg-light py-2">
                <nav aria-label="Page navigation" class="d-flex justify-content-center">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pg<=1?'disabled':''; ?>"><a class="page-link" href="<?= h(page_url(max(1,$pg-1))); ?>" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>
                        <?php
                        $range = 2; $start = max(1, $pg - $range); $end = min($totalPages, $pg + $range);
                        if ($start > 1) { echo '<li class="page-item"><a class="page-link" href="'.h(page_url(1)).'">1</a></li>'; if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>'; } }
                        for($p=$start;$p<=$end;$p++){ echo '<li class="page-item '.($p===$pg?'active':'').'"><a class="page-link" href="'.h(page_url($p)).'">'.$p.'</a></li>'; }
                        if ($end < $totalPages) { if ($end < $totalPages - 1) { echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>'; } echo '<li class="page-item"><a class="page-link" href="'.h(page_url($totalPages)).'">'.$totalPages.'</a></li>'; }
                        ?>
                        <li class="page-item <?= $pg>=$totalPages?'disabled':''; ?>"><a class="page-link" href="<?= h(page_url(min($totalPages,$pg+1))); ?>" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>

    </div></div><div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" enctype="multipart/form-data" class="modal-content shadow-lg border-0 rounded-3" id="formImage">
      <div class="modal-header bg-primary text-white">
        <h1 class="modal-title fs-5" id="editModalLabel"><i class="bi bi-card-image me-2"></i>เพิ่มรูปภาพ</h1>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="create" id="action">
        <input type="hidden" name="id" value="" id="edit_id">

        <div class="row g-4">
          <div class="col-md-5 text-center">
            <label for="image_file" class="form-label mb-2">ไฟล์รูปภาพ <span class="text-danger" id="fileReq">*</span></label>
            <img id="prev" src="#" alt="Preview" class="img-thumbnail mb-2 d-none" style="max-height: 200px; object-fit: contain;">
            <input class="form-control form-control-sm" type="file" name="image_file" id="image_file" accept="image/jpeg,image/png,image/webp,image/gif" required>
            <div class="form-text mt-2">รองรับ: JPG, PNG, WebP, GIF</div>
          </div>
          <div class="col-md-7">
            <div class="mb-3">
              <label for="image_filename" class="form-label">ชื่อไฟล์/คำอธิบาย (ไม่ต้องใส่นามสกุล)</label>
              <input class="form-control" type="text" name="image_filename" id="image_filename" placeholder="เช่น banner_home_1">
              <div class="form-text">ถ้าเว้นว่าง จะใช้ชื่อไฟล์เดิมเป็นฐานในการสร้างชื่อใหม่</div>
            </div>
            <div class="mb-3">
              <label for="gt_id" class="form-label">หมวดหมู่ <span class="text-danger">*</span></label>
              <select class="form-select" name="gt_id" id="gt_id" required>
                <option value="" disabled selected>-- กรุณาเลือก --</option>
                <?php foreach($categories as $c): ?>
                  <option value="<?= (int)$c['gt_id'] ?>"><?= h($c['gt_name']) ?></option>
                <?php endforeach; ?>
                 <?php if (empty($categories)): ?>
                    <option value="" disabled>! ไม่มีหมวดหมู่ในระบบ</option>
                 <?php endif; ?>
              </select>
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer bg-light justify-content-between">
          <button class="btn btn-outline-secondary" type="button" id="btnReset">
              <i class="bi bi-arrow-counterclockwise me-1"></i> ล้างข้อมูล
          </button>
          <button class="btn btn-primary" type="submit" id="btnSubmit">
              <i class="bi bi-save-fill me-1"></i> บันทึกข้อมูล
          </button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content shadow border-0 rounded-3">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
          <p id="confirmText" class="mb-0 fs-6">คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้?</p>
          <p class="text-danger small mt-2"><i class="bi bi-info-circle me-1"></i>การกระทำนี้จะลบทั้งข้อมูลในฐานข้อมูลและไฟล์รูปภาพอย่างถาวร ไม่สามารถกู้คืนได้</p>
      </div>
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="confirmId">
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

<script>
/* ค้นหาอย่างง่าย */
const qEl=document.getElementById('q');
qEl?.addEventListener('input', ()=>{
  const q=(qEl.value||'').toLowerCase().trim();
  let count = 0;
  document.querySelectorAll('.row-item').forEach(tr=>{
    const text=(tr.dataset.title+' '+tr.dataset.catname).toLowerCase();
    const show = !q || text.includes(q);
    tr.style.display = show ? '' : 'none';
    if(show) count++;
  });
  // Optional: Update caption
  // const caption = document.querySelector('.table caption div');
  // if(caption) caption.textContent = `พบ ${count} รายการ`;
});

/* เปิดสร้างใหม่ */
document.getElementById('openCreate')?.addEventListener('click', resetForm);

/* ปุ่มแก้ไข: เติมโมดัล */
document.querySelectorAll('.btn-edit').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const tr=btn.closest('tr'); if(!tr) return;
    const id=tr.dataset.id, gtid=tr.dataset.gtid;
    const file=tr.querySelector('td:nth-child(3) .fw-semibold').textContent.trim();

    resetForm(false); // Reset แต่ไม่ต้องซ่อน modal
    document.getElementById('editModalLabel').innerHTML='<i class="bi bi-pencil-square me-2"></i> แก้ไขรูปภาพ';
    document.getElementById('action').value='update';
    document.getElementById('edit_id').value=id;
    document.getElementById('gt_id').value=gtid;
    document.getElementById('image_filename').value = file.replace(/\.[^.]+$/,''); // เอาเฉพาะชื่อ ไม่เอานามสกุล
    document.getElementById('image_file').required = false; // การแก้ไข ไม่บังคับอัปโหลดใหม่
    document.getElementById('fileReq').style.display = 'none';

    const prev=document.getElementById('prev');
    const imgSrc = "<?= h(UPLOAD_URL) ?>"+file;
    // Check if image exists before showing preview
    const img = new Image();
    img.onload = () => { prev.src=imgSrc; prev.classList.remove('d-none'); }
    img.onerror = () => { prev.src='#'; prev.classList.add('d-none'); } // Hide if file not found
    img.src = imgSrc;

    new bootstrap.Modal(document.getElementById('editModal')).show();
  });
});

/* ปุ่มลบ: เปิด confirm modal */
document.querySelectorAll('.btn-del').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const tr=btn.closest('tr'); if(!tr) return; const id=tr.dataset.id;
    const file=tr.querySelector('td:nth-child(3) .fw-semibold').textContent.trim();
    document.getElementById('confirmId').value=id;
    document.getElementById('confirmText').innerHTML=`ต้องการลบรูปภาพ <strong>“${file}”</strong> (ID: ${id}) ใช่หรือไม่?`;
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
  });
});

/* Preview รูปเมื่อเลือกไฟล์ */
document.getElementById('image_file')?.addEventListener('change', (ev)=>{
  const file=ev.target.files?.[0];
  const prev=document.getElementById('prev');
  if(!file) { prev.src='#'; prev.classList.add('d-none'); return; }
  prev.src=URL.createObjectURL(file); prev.classList.remove('d-none');
});

/* Reset form */
function resetForm(showModal = true){
  const form = document.getElementById('formImage'); if(!form) return;
  form.reset(); // ใช้ reset() ง่ายกว่า
  document.getElementById('action').value='create';
  document.getElementById('edit_id').value='';
  document.getElementById('image_filename').value=''; // ล้างค่า input text ด้วย
  document.getElementById('gt_id').value=''; // ล้างค่า select ด้วย
  document.getElementById('image_file').required = true; // กลับมาบังคับเลือกไฟล์
  document.getElementById('fileReq').style.display = ''; // แสดง * สีแดง

  const prev=document.getElementById('prev'); prev.src='#'; prev.classList.add('d-none');
  document.getElementById('editModalLabel').innerHTML='<i class="bi bi-card-image me-2"></i> เพิ่มรูปภาพ';

  // ถ้าถูกเรียกจากปุ่ม "เพิ่ม" ให้เปิด Modal ด้วย (ถ้ายังไม่เปิด)
  if(showModal) {
      const modalInstance = bootstrap.Modal.getInstance(document.getElementById('editModal'));
      if (!modalInstance || !modalInstance._isShown) {
           new bootstrap.Modal(document.getElementById('editModal')).show();
      }
  }
}
document.getElementById('btnReset')?.addEventListener('click', () => resetForm(false)); // ปุ่ม Reset ใน Modal ไม่ต้องเปิดใหม่

/* ทำให้ Alert หายไปเอง */
document.querySelectorAll('.alert-dismissible').forEach(alert => {
    setTimeout(() => { bootstrap.Alert.getOrCreateInstance(alert).close(); }, 5000);
});
</script>

</body>
</html>
<?php $conn->close(); ?>