<?php
// backend/edit_contact.php
// Admin: จัดการข้อมูลติดต่อ (contacts) — สไตล์ตาม "ต้นแบบแอดมิน" + คงพฤติกรรมเดิม (inline edit + AJAX POST)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/connectdb.php';
// ===== Error hygiene (กัน HTML/WARNING ปน JSON) =====
ini_set('display_errors', 0);     // ไม่แสดง error บนหน้า
ini_set('log_errors', 1);         // ให้ลง error_log แทน
error_reporting(E_ALL);

if (empty($conn) || !($conn instanceof mysqli)) { http_response_code(500); die('DB connection failed (check connectdb.php)'); }

define('TABLE', 'contacts');

/* ===== CSRF ===== */
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== Column check (ยืดหยุ่น) ===== */
$cols = [];
if ($res = mysqli_query($conn, "SHOW COLUMNS FROM `".TABLE."`")) {
  while ($row = mysqli_fetch_assoc($res)) { $cols[] = $row['Field']; }
  mysqli_free_result($res);
}
$hasHeading = in_array('heading_admin', $cols, true);
$hasAvatar  = in_array('avatar_url_admin', $cols, true);
$hasAddr    = in_array('address_admin', $cols, true);
$hasPhone   = in_array('phone_admin', $cols, true);
$hasEmail   = in_array('email_admin', $cols, true);
$hasFB      = in_array('facebook_admin', $cols, true);
$hasLINE    = in_array('line_admin', $cols, true);

$required = ($hasHeading && $hasPhone && $hasEmail); // เกณฑ์ขั้นต่ำว่ามีคอลัมน์หลักครบ

/* ===== Actions (AJAX JSON) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  while (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_clean(); }
  header_remove();
  header('Content-Type: application/json; charset=utf-8');

  if (!hash_equals($csrf ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'msg'=>'Invalid CSRF']); exit;
  }

  $action = $_POST['action'] ?? '';

  /* CREATE */
  if ($action === 'create') {
    $heading = trim($_POST['heading_admin'] ?? '');
    $avatar  = trim($_POST['avatar_url_admin'] ?? '');
    $addr    = trim($_POST['address_admin'] ?? '');
    $phone   = trim($_POST['phone_admin'] ?? '');
    $email   = trim($_POST['email_admin'] ?? '');
    $fb      = trim($_POST['facebook_admin'] ?? '');
    $line    = trim($_POST['line_admin'] ?? '');

    if ($required && $heading === '') { echo json_encode(['ok'=>false,'msg'=>'กรอกหัวข้อ']); exit; }
    if ($phone==='' && $email==='') { echo json_encode(['ok'=>false,'msg'=>'กรอกอย่างน้อย เบอร์โทร หรือ อีเมล']); exit; }

    // ใช้ NOW() สำหรับ created_at และ updated_at โดยไม่ bind ซ้ำ
    $sql = "INSERT INTO `".TABLE."`
            (heading_admin, avatar_url_admin, address_admin, phone_admin, email_admin, facebook_admin, line_admin, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?, NOW(), NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt){ echo json_encode(['ok'=>false,'msg'=>'Prepare failed']); exit; }
    mysqli_stmt_bind_param($stmt, "sssssss", $heading, $avatar, $addr, $phone, $email, $fb, $line);

    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    echo json_encode(['ok'=>$ok, 'id'=>$newId, 'msg'=>$ok?'เพิ่มข้อมูลแล้ว':'เพิ่มไม่สำเร็จ: '.$err]); exit;
  }

  /* UPDATE */
  if ($action === 'update') {
    $id      = (int)($_POST['id_contact'] ?? 0);
    $heading = trim($_POST['heading_admin'] ?? '');
    $avatar  = trim($_POST['avatar_url_admin'] ?? '');
    $addr    = trim($_POST['address_admin'] ?? '');
    $phone   = trim($_POST['phone_admin'] ?? '');
    $email   = trim($_POST['email_admin'] ?? '');
    $fb      = trim($_POST['facebook_admin'] ?? '');
    $line    = trim($_POST['line_admin'] ?? '');

    if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ไม่พบรหัสรายการ']); exit; }
    if ($required && $heading === '') { echo json_encode(['ok'=>false,'msg'=>'กรอกหัวข้อ']); exit; }
    if ($phone==='' && $email==='') { echo json_encode(['ok'=>false,'msg'=>'กรอกอย่างน้อย เบอร์โทร หรือ อีเมล']); exit; }

    $sql = "UPDATE `".TABLE."`
            SET heading_admin=?, avatar_url_admin=?, address_admin=?, phone_admin=?, email_admin=?, facebook_admin=?, line_admin=?, updated_at=NOW()
            WHERE id_contact=?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt){ echo json_encode(['ok'=>false,'msg'=>'Prepare failed']); exit; }
    mysqli_stmt_bind_param($stmt, "sssssssi", $heading, $avatar, $addr, $phone, $email, $fb, $line, $id);

    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['ok'=>$ok, 'msg'=>$ok?'บันทึกแล้ว':'บันทึกไม่สำเร็จ: '.$err]); exit;
  }

  /* DELETE */
  if ($action === 'delete') {
    $id = (int)($_POST['id_contact'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ไม่พบรหัสรายการ']); exit; }

    $stmt = mysqli_prepare($conn, "DELETE FROM `".TABLE."` WHERE id_contact=?");
    if (!$stmt){ echo json_encode(['ok'=>false,'msg'=>'Prepare failed']); exit; }
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['ok'=>$ok, 'msg'=>$ok?'ลบแล้ว':'ลบไม่สำเร็จ: '.$err]); exit;
  }

  echo json_encode(['ok'=>false,'msg'=>'unknown action']); exit;
}

/* ===== READ LIST ===== */
$rows = [];
$sql_list = "SELECT id_contact, heading_admin, avatar_url_admin, address_admin, phone_admin, email_admin, facebook_admin, line_admin
             FROM `".TABLE."` ORDER BY id_contact ASC";
if ($res = mysqli_query($conn, $sql_list)) {
  while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
  mysqli_free_result($res);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin · Contacts</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{
    /* โทนตามต้นแบบ */
    --mint:#008374; --mint-50:#eaf7f4; --border:#e6e9ee; --head:#f8fafc; --text:#0b1220;
    --ok:#22c55e; --danger:#ef4444; --warn:#f59e0b;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{margin:0;background:#f6f8fb;color:var(--text);font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue"}
  a{color:inherit;text-decoration:none}
  .container{max-width:1340px;margin:20px auto;padding:0 16px}

  /* Page head */
  .page-head{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center;margin:6px 0 18px}
  .h-title{margin:0;font-weight:800}
  .quick-actions{display:flex;gap:8px;flex-wrap:wrap}

  /* Buttons / Chips */
  .btn{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;border:1px solid var(--border);background:#fff;font-weight:800;cursor:pointer;transition:.12s}
  .btn:hover{transform:translateY(-1px);box-shadow:0 6px 14px rgba(0,0,0,.06)}
  .btn-primary{background:var(--mint);border-color:var(--mint);color:#fff}
  .btn-danger{background:var(--danger);border-color:var(--danger);color:#fff}
  .btn-outline{background:#fff;color:#0b1220}
  .btn-sm{padding:8px 12px;border-radius:8px}
  .chip{border:1px solid var(--border);background:#fff;color:#111;padding:7px 12px;border-radius:999px;font-weight:800;cursor:pointer}
  .chip[data-on="1"]{border-color:var(--mint);box-shadow:0 0 0 3px rgba(0,131,116,.15)}
  .muted{color:#667085;font-size:12px}
  .text-end{text-align:right}

  /* Tool card */
  .tool-card{border:1px solid var(--border);border-radius:14px;padding:12px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.04)}
  .tools{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
  .search{display:flex;align-items:center;border:1px solid var(--border);border-radius:10px;background:#fff;padding:8px 12px;min-width:320px}
  .search input{border:none;outline:none;background:transparent;width:100%}
  .pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:var(--mint-50);color:#066e62;border:1px solid rgba(0,131,116,.25);font-size:12px}

  /* Card + Table */
  .card{border:1px solid var(--border);border-radius:16px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05)}
  table{width:100%;border-collapse:separate;border-spacing:0 12px}
  thead th{background:var(--head);font-weight:800;border-bottom:1px solid var(--border);padding:.8rem .85rem;text-align:left}
  .data td,.data th{padding:.8rem .85rem;vertical-align:top;border-top:1px solid var(--border)}
  .data tbody tr{background:#fff;border:1px solid var(--border);border-radius:12px}
  .data tbody tr:hover td{background:var(--mint-50)}
  .imgprev{width:72px;height:72px;border-radius:50%;object-fit:cover;border:1px solid var(--border);background:#fff}

  /* Toast / Modal */
  .toast-wrap{position:fixed;right:14px;top:14px;z-index:50;display:flex;flex-direction:column;gap:10px}
  .toast{min-width:240px;max-width:420px;padding:12px 14px;border-radius:12px;background:#fff;border:1px solid var(--border);box-shadow:0 10px 30px rgba(0,0,0,.12);display:flex;align-items:flex-start;gap:10px}
  .toast.ok{border-color:#b7f0dc;background:#ecfdf5}
  .toast.err{border-color:#fecaca;background:#fff1f1}

  .backdrop{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:60}
  .modal{background:#fff;border:1px solid var(--border);border-radius:16px;padding:18px;width:min(760px,94vw);box-shadow:0 20px 60px rgba(0,0,0,.25)}
  .modal h3{margin:0 0 10px;font-size:18px}
  .row-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}

  .txt,.area{width:100%;border:1px solid var(--border);background:#fff;color:#111;border-radius:10px;padding:10px 12px;font-size:14px;outline:none}
  .area{min-height:100px;resize:vertical;line-height:1.6}
  .txt:focus,.area:focus{border-color:var(--mint);box-shadow:0 0 0 3px rgba(0,131,116,.15)}
  .txt[disabled],.area[disabled]{background:#f1f5f4;color:#6b7280}

  .dense .data td{padding:.6rem .7rem}
  @media (max-width: 980px){
    thead{display:none}
    table, tbody, tr, td{display:block;width:100%}
    tbody tr{margin-bottom:12px}
    .search{min-width:0;width:100%}
  }
</style>
</head>
<body>

<div class="container">
  <div class="page-head">
    <h2 class="h-title">จัดการ Contact</h2>
    <div class="quick-actions">
      <a class="btn btn-outline" href="../index.php"><i class="bi bi-house-door"></i> หน้าเว็บ</a>
      <span class="pill"><i class="bi bi-database"></i> TABLE: <b><?=h(TABLE)?></b></span>
    </div>
  </div>

  <div class="tool-card" style="margin-bottom:14px">
    <div class="tools">
      <div class="search">
        <i class="bi bi-search" style="color:var(--mint);margin-right:8px"></i>
        <input type="text" id="kw" placeholder="ค้นหา: หัวข้อ/ที่อยู่/โทร/อีเมล/โซเชียล">
      </div>
      <button id="btnDense" class="chip">โหมดแน่น</button>
      <a href="javascript:;" id="btnNew" class="btn"><i class="bi bi-plus-lg"></i> เพิ่มรายการ</a>
      <span class="muted">แสดง <b id="visCnt"><?= count($rows) ?></b> / ทั้งหมด <b><?= count($rows) ?></b> รายการ</span>
    </div>
  </div>

  <div class="card" id="card">
    <div class="table-responsive">
      <table class="data">
        <thead>
          <tr>
            <th>หัวข้อ</th>
            <th>ที่อยู่</th>
            <th>โทร / อีเมล</th>
            <th>โซเชียล</th>
            <th>Avatar</th>
            <th style="width:240px" class="text-end">การจัดการ</th>
          </tr>
        </thead>
        <tbody id="tbody">
        <?php if (empty($rows)): ?>
          <tr data-id="">
            <td colspan="6" class="muted" style="padding:22px">ยังไม่มีข้อมูล — กด <b>เพิ่มรายการ</b> ได้เลย</td>
          </tr>
        <?php else: foreach ($rows as $r):
          $datatext = strtolower(trim(
            ($r['heading_admin'] ?? '').' '.
            ($r['address_admin'] ?? '').' '.
            ($r['phone_admin'] ?? '').' '.
            ($r['email_admin'] ?? '').' '.
            ($r['facebook_admin'] ?? '').' '.
            ($r['line_admin'] ?? '')
          ));
        ?>
          <tr data-id="<?= (int)$r['id_contact'] ?>" data-text="<?= h($datatext) ?>">
            <!-- หัวข้อ -->
            <td>
              <input class="txt" type="text" name="heading_admin" value="<?= h($r['heading_admin'] ?? '') ?>" disabled>
              <div class="muted" style="font-size:12px;margin-top:6px">กด <b>Edit</b> เพื่อแก้ไข • ช็อตคัท <b>Ctrl/⌘ + S</b> เพื่อบันทึก</div>
            </td>

            <!-- ที่อยู่ -->
            <td>
              <textarea class="area" name="address_admin" placeholder="ที่อยู่หลายบรรทัดได้" disabled><?= h($r['address_admin'] ?? '') ?></textarea>
            </td>

            <!-- โทร / อีเมล -->
            <td>
              <input class="txt" style="margin-bottom:8px" type="text" name="phone_admin" placeholder="เช่น 0930199299" value="<?= h($r['phone_admin'] ?? '') ?>" disabled>
              <input class="txt" type="email" name="email_admin" placeholder="เช่น example@mail.com" value="<?= h($r['email_admin'] ?? '') ?>" disabled>
            </td>

            <!-- โซเชียล -->
            <td>
              <input class="txt" style="margin-bottom:8px" type="url" name="facebook_admin" placeholder="Facebook URL" value="<?= h($r['facebook_admin'] ?? '') ?>" disabled>
              <input class="txt" type="url" name="line_admin" placeholder="LINE OA URL" value="<?= h($r['line_admin'] ?? '') ?>" disabled>
            </td>

            <!-- Avatar -->
            <td>
              <div style="display:flex; align-items:center; gap:10px;">
                <img class="imgprev" data-prev src="<?= h(($r['avatar_url_admin'] ?? '') ?: 'assets/img/cat-test.png') ?>" alt="avatar">
              </div>
              <input class="txt" style="margin-top:8px" type="url" name="avatar_url_admin" placeholder="ลิงก์รูป avatar" value="<?= h($r['avatar_url_admin'] ?? '') ?>" disabled>
            </td>

            <!-- Actions -->
            <td class="text-end">
              <a class="btn btn-primary btn-sm" href="javascript:;" data-edit><i class="bi bi-pencil-square"></i> Edit</a>
              <a class="btn btn-primary btn-sm" style="background:var(--ok);border-color:var(--ok)" href="javascript:;" data-save hidden><i class="bi bi-check2-circle"></i> Save</a>
              <a class="btn btn-outline btn-sm" href="javascript:;" data-cancel hidden><i class="bi bi-x-circle"></i> Cancel</a>
              <a class="btn btn-danger btn-sm" href="javascript:;" data-del><i class="bi bi-trash3"></i> Delete</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Toasts -->
<div class="toast-wrap" id="toasts"></div>

<!-- Confirm Modal -->
<div class="backdrop" id="modal">
  <div class="modal">
    <h3><i class="bi bi-exclamation-triangle-fill" style="color:var(--warn)"></i> ยืนยันการลบ</h3>
    <div class="muted">ต้องการลบรายการนี้หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้</div>
    <div class="row-actions">
      <button class="btn btn-outline" id="mCancel"><i class="bi bi-x-circle"></i> ยกเลิก</button>
      <button class="btn btn-danger" id="mOk"><i class="bi bi-trash3"></i> ลบเลย</button>
    </div>
  </div>
</div>

<script>
(function(){
  const CSRF = <?= json_encode($csrf) ?>;

  /* ===== Helpers (ทน JSON ปน) ===== */
  const $  = (s,sc=document)=>sc.querySelector(s);
  const $$ = (s,sc=document)=>[...sc.querySelectorAll(s)];

  function toast(msg,type='ok'){
    const wrap = $('#toasts');
    const t = document.createElement('div');
    t.className = 'toast ' + (type==='ok'?'ok':'err');
    t.innerHTML = (type==='ok'?'✅':'⚠️') + ' <div style="font-weight:700">'+ msg +'</div>';
    wrap.appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translateY(-6px)'; setTimeout(()=>t.remove(),220); },2400);
  }

  // ทน BOM / HTML / คำเตือนปน JSON
  function safeParseJSON(text){
    if (!text) return null;
    const t = text.replace(/^\uFEFF/, '').trim();
    try { return JSON.parse(t); } catch(e){}
    const i = t.indexOf('{'); const j = t.lastIndexOf('}');
    if (i !== -1 && j !== -1 && j > i) {
      try { return JSON.parse(t.slice(i, j+1)); } catch(e){}
    }
    return null;
  }
  function isLikelySuccessBody(text){
    const t = (text || '').toLowerCase();
    if (/"ok"\s*:\s*true/.test(text)) return true;
    // ถ้าไม่มีข้อความผิดปกติที่มักเจอใน error/notice ก็ถือว่า “พอไปได้”
    if (!/fatal|warning|notice|error|exception/i.test(t) && !/^</.test(t)) return true;
    return false;
  }

  /* ===== Dense mode ===== */
  $('#btnDense')?.addEventListener('click', ()=>{
    const on = $('#card').classList.toggle('dense');
    $('#btnDense').dataset.on = on ? '1' : '0';
  });

  /* ===== Search ===== */
  (function(){
    const kw = $('#kw'); const rows = $$('#tbody tr[data-id]'); const visCnt = $('#visCnt');
    function apply(){
      const q = (kw.value||'').toLowerCase().trim(); let n=0;
      rows.forEach(tr=>{
        const show = !q || (tr.dataset.text||'').includes(q);
        tr.style.display = show ? '' : 'none';
        if (show) n++;
      });
      if (visCnt) visCnt.textContent = n;
    }
    kw?.addEventListener('input', apply);
    apply();
  })();

  /* ===== Modal delete ===== */
  const modal=$('#modal'), btnOk=$('#mOk'), btnCancel=$('#mCancel'); let delId=null;
  function openModal(id){ delId=id; modal.style.display='flex'; }
  function closeModal(){ modal.style.display='none'; delId=null; }
  btnCancel?.addEventListener('click', closeModal);
  modal?.addEventListener('click', e=>{ if(e.target===modal) closeModal(); });

  btnOk?.addEventListener('click', async ()=>{
    if(!delId) return;
    try{
      const resp=await fetch(location.href, {
        method:'POST',
        headers:{
          'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
          'Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest'
        },
        body:new URLSearchParams({action:'delete', csrf:CSRF, id_contact:delId})
      });
      const raw  = await resp.text();
      const data = safeParseJSON(raw);
      if (resp.ok && ((data && data.ok===true) || isLikelySuccessBody(raw))){
        const tr=document.querySelector(`tr[data-id="${delId}"]`);
        if (tr) tr.remove();
        toast('ลบแล้ว ✔','ok'); closeModal();
      } else {
        toast((data && data.msg) || (resp.status+' '+resp.statusText) || 'ลบไม่สำเร็จ','err');
      }
    }catch(err){ toast('เชื่อมต่อไม่สำเร็จ: '+err,'err'); }
  });

  /* ===== Inline edit ===== */
  $$('#tbody tr[data-id]').forEach(tr=>{
    const btnE=tr.querySelector('[data-edit]');
    const btnS=tr.querySelector('[data-save]');
    const btnC=tr.querySelector('[data-cancel]');
    const btnD=tr.querySelector('[data-del]');

    function setEditing(on){
      tr.classList.toggle('is-editing', on);
      tr.querySelectorAll('input,textarea,select').forEach(el=>{
        if (on) el.removeAttribute('disabled'); else el.setAttribute('disabled','');
      });
      btnE.hidden   = on;
      btnS.hidden   = !on;
      btnC.hidden   = !on;
    }

    // live avatar preview
    tr.querySelector('input[name="avatar_url_admin"]')?.addEventListener('input', ()=>{
      const url = tr.querySelector('input[name="avatar_url_admin"]').value.trim();
      const img = tr.querySelector('img[data-prev]');
      if (img) img.src = url || 'assets/img/cat-test.png';
    });

    btnE?.addEventListener('click', (e)=>{ e.preventDefault(); setEditing(true);
      const handler=(ev)=>{ if((ev.ctrlKey||ev.metaKey) && ev.key.toLowerCase()==='s'){ ev.preventDefault(); btnS.click(); } };
      tr.addEventListener('keydown', handler, {once:true});
    });
    btnC?.addEventListener('click', (e)=>{ e.preventDefault(); location.reload(); });
    btnD?.addEventListener('click', (e)=>{ e.preventDefault(); openModal(tr.dataset.id); });

    btnS?.addEventListener('click', async (e)=>{
      e.preventDefault();
      const payload = {
        action: 'update',
        csrf: CSRF,
        id_contact: tr.dataset.id,
        heading_admin: tr.querySelector('input[name="heading_admin"]').value.trim(),
        address_admin: tr.querySelector('textarea[name="address_admin"]').value.trim(),
        phone_admin:   tr.querySelector('input[name="phone_admin"]').value.trim(),
        email_admin:   tr.querySelector('input[name="email_admin"]').value.trim(),
        facebook_admin:tr.querySelector('input[name="facebook_admin"]').value.trim(),
        line_admin:    tr.querySelector('input[name="line_admin"]').value.trim(),
        avatar_url_admin: tr.querySelector('input[name="avatar_url_admin"]').value.trim()
      };
      if (!payload.heading_admin){ toast('กรุณากรอกหัวข้อ','err'); return; }
      if (!payload.phone_admin && !payload.email_admin){ toast('กรอกอย่างน้อย เบอร์โทร หรือ อีเมล','err'); return; }

      btnS.disabled = true; const old = btnS.innerHTML; btnS.innerHTML = '<i class="bi bi-arrow-repeat"></i> กำลังบันทึก…';
      try{
        const resp = await fetch(location.href, {
          method:'POST',
          headers:{
            'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept':'application/json',
            'X-Requested-With':'XMLHttpRequest'
          },
          body:new URLSearchParams(payload)
        });
        const raw  = await resp.text();
        const data = safeParseJSON(raw);

        if (resp.ok && ((data && data.ok===true) || isLikelySuccessBody(raw))){
          toast('บันทึกแล้ว ✔','ok');
          setTimeout(()=> location.reload(), 500);
        } else {
          toast((data && data.msg) || (resp.status+' '+resp.statusText) || 'บันทึกไม่สำเร็จ','err');
          btnS.disabled=false; btnS.innerHTML=old;
        }
      }catch(err){
        toast('เชื่อมต่อไม่สำเร็จ: '+err,'err');
        btnS.disabled=false; btnS.innerHTML=old;
      }
    });
  });

  /* ===== Create new ===== */
  $('#btnNew')?.addEventListener('click', async ()=>{
    try{
      const resp=await fetch(location.href,{
        method:'POST',
        headers:{
          'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
          'Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest'
        },
        body:new URLSearchParams({
          action:'create', csrf:CSRF,
          heading_admin:'ติดต่อสอบถาม',
          avatar_url_admin:'assets/img/cat-test.png',
          address_admin:'',
          phone_admin:'',
          email_admin:'',
          facebook_admin:'',
          line_admin:''
        })
      });
      const raw  = await resp.text();
      const data = safeParseJSON(raw);

      if (resp.ok && ((data && data.ok===true) || isLikelySuccessBody(raw))){
        toast('เพิ่มรายการแล้ว ✔','ok');
        setTimeout(()=> location.reload(), 500);
      } else {
        toast((data && data.msg) || (resp.status+' '+resp.statusText) || 'เพิ่มไม่สำเร็จ','err');
      }
    }catch(err){ toast('เชื่อมต่อไม่สำเร็จ: '+err,'err'); }
  });

})();
</script>

</body>
</html>
