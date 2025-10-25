<?php
// ========= Path Resolver =========
function find_project_root(string $start): string {
  $d=$start; for($i=0;$i<6;$i++){ if(is_dir($d.'/uploads')) return $d; $up=dirname($d); if($up===$d)break; $d=$up; } return $start;
}
function url_prefix_to(string $from, string $to): string {
  $from=realpath($from)?:$from; $to=realpath($to)?:$to; if(!$from||!$to) return '';
  $p=''; while($from!==$to && strpos($from,$to)!==0){ $p.='../'; $from=dirname($from); if(strlen($p)>30)break; } return $p;
}
$PROJECT_DIR=find_project_root(__DIR__); $URL_PREFIX=url_prefix_to(__DIR__,$PROJECT_DIR);
define('UPLOAD_URL',$URL_PREFIX.'uploads/');

if (file_exists(__DIR__.'/connectdb.php')) include_once __DIR__.'/connectdb.php';
elseif (file_exists($PROJECT_DIR.'/connectdb.php')) include_once $PROJECT_DIR.'/connectdb.php';
else die('ไม่พบไฟล์ connectdb.php');

function h($s){ return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8'); }

// ====== ดึงหมวดหมู่ ======
$categories=[];
$sql="SELECT t.gt_id, t.gt_name, COUNT(g.g_id) AS total
      FROM gallery_type t
      LEFT JOIN gallery_edit g ON g.gt_id=t.gt_id
      GROUP BY t.gt_id,t.gt_name
      ORDER BY t.gt_name ASC";
if($rs=$conn->query($sql)){ while($r=$rs->fetch_assoc()) $categories[]=$r; $rs->close(); }

// ====== ดึงรูป ======
$images=[];
$sql="SELECT g.image_filename, g.image_title, t.gt_name AS category_name
      FROM gallery_edit g
      LEFT JOIN gallery_type t ON g.gt_id=t.gt_id
      ORDER BY g.upload_date DESC, g.g_id DESC";
if($rs=$conn->query($sql)){ while($r=$rs->fetch_assoc()) $images[]=$r; $rs->close(); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>แกลเลอรี่</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
:root{
  --primary:#008374;
  --primary-strong:#006c61;
  --chip-bg:#e8f4f2;
  --chip-border:#b9e0d9;
  --text:#0f172a;
}

/* layout */
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Prompt,Arial,sans-serif;color:var(--text);background:#fff;}
.wrapper{max-width:1100px;margin:20px auto;padding:0 16px;}

/* --- Category bar --- */
.nav-wrap{display:flex; gap:10px; align-items:flex-start; margin-bottom:18px;}
.home-nav-btn{
  display:inline-flex; align-items:center; justify-content:center;
  width:40px; height:40px; border-radius:50%;
  background:var(--chip-bg); color:var(--primary-strong); text-decoration:none;
  border:1px solid var(--chip-border);
}
.home-nav-btn:hover{ background:#f1fbf9; }

/* ปุ่มหมวดหมู่ให้กว้างเท่ากันด้วย grid */
.cat-list{
  flex:1;
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap:10px;
}
@media (max-width:640px){
  .cat-list{ grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap:8px; }
}
.cat-btn{
  display:flex; align-items:center; justify-content:center;
  height:42px; padding:0 .8rem; border-radius:999px;
  background:var(--chip-bg); border:1px solid var(--chip-border); color:var(--text);
  text-decoration:none; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  transition:background .2s, color .2s, border-color .2s, transform .05s;
}
.cat-btn:hover{ background:#f1fbf9; border-color:#a8d8cf; }
.cat-btn.active{
  background:var(--primary); color:#fff; border-color:var(--primary);
}
.cat-btn:active{ transform:scale(.98); }
.cat-btn .count{
  margin-left:.4rem; font-size:.85em; opacity:.9;
}

/* --- Uniform grid for images --- */
.gallery{
  display:grid; gap:14px;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
}
@media (max-width:640px){
  .gallery{ grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap:10px; }
}
.tile{ position:relative; border-radius:12px; overflow:hidden; background:#e5e7eb; cursor:zoom-in; }
.tile > span{ display:block; width:100%; aspect-ratio:4/3; overflow:hidden; }
.tile img{ width:100%; height:100%; object-fit:cover; display:block; transition:transform .25s ease; }
.tile:hover img{ transform:scale(1.03); }
.tile.hide{ display:none; }

/* --- Preview modal --- */
.preview{ position:fixed; inset:0; background:rgba(0,0,0,.86);
  display:none; align-items:center; justify-content:center; padding:18px; z-index:9999; }
.preview.show{ display:flex; }
.preview-inner{ position:relative; max-width:min(96vw,1200px); max-height:88vh; }
.preview-img{ max-width:100%; max-height:88vh; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.35); }
.preview-close{
  position:absolute; top:-8px; right:-8px; width:40px; height:40px; border-radius:50%;
  background:#ffffffd9; border:1px solid #fff; color:#0f172a; display:flex; align-items:center; justify-content:center;
  font-size:20px; cursor:pointer;
}
.preview-close:hover{ background:#fff; }
@media (max-width:640px){ .preview-img{ max-height:80vh; } }
</style>
</head>
<body>

<div class="wrapper">

  <!-- Category Bar -->
  <div class="nav-wrap">
    <a href="index.php" class="home-nav-btn" title="หน้าแรก"><i class="bi bi-house"></i></a>
    <div class="cat-list" id="catList">
      <a href="#" class="cat-btn active" data-name="all">ทั้งหมด</a>
      <?php foreach($categories as $c): ?>
        <a href="#" class="cat-btn" data-name="<?=h($c['gt_name'])?>">
          <?=h($c['gt_name'])?>
          <span class="count">(<?= (int)$c['total'] ?>)</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Gallery -->
  <div class="gallery" id="gallery">
    <?php if($images): foreach($images as $img): ?>
      <div class="tile show" data-name="<?=h($img['category_name'])?>">
        <span><img src="<?=h(UPLOAD_URL.$img['image_filename'])?>" alt="<?=h($img['image_title'] ?: $img['category_name'])?>" loading="lazy"></span>
      </div>
    <?php endforeach; else: ?>
      <p>ไม่พบรูปภาพในแกลเลอรี่</p>
    <?php endif; ?>
  </div>

</div>

<!-- Preview -->
<div class="preview" id="preview" aria-hidden="true">
  <div class="preview-inner">
    <img src="" class="preview-img" id="previewImg" alt="Preview">
    <button class="preview-close" id="previewClose" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
  </div>
</div>

<script>
// ===== Filter =====
const buttons = document.querySelectorAll('.cat-list .cat-btn');
const tiles   = document.querySelectorAll('.gallery .tile');

buttons.forEach(btn=>{
  btn.addEventListener('click', e=>{
    e.preventDefault();
    buttons.forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const key = btn.getAttribute('data-name');
    tiles.forEach(t=>{
      const match = (key === 'all') || (t.getAttribute('data-name') === key);
      t.classList.toggle('hide', !match);
      t.classList.toggle('show',  match);
    });
  });
});

// ===== Preview =====
const preview     = document.getElementById('preview');
const previewImg  = document.getElementById('previewImg');
const previewClose= document.getElementById('previewClose');

function openPreview(src){
  previewImg.src = src;
  preview.classList.add('show');
  document.body.style.overflow='hidden';
  preview.setAttribute('aria-hidden','false');
}
function closePreview(){
  preview.classList.remove('show');
  document.body.style.overflow='';
  previewImg.src='';
  preview.setAttribute('aria-hidden','true');
}
document.querySelectorAll('.tile').forEach(t=>{
  t.addEventListener('click', ()=>{
    const img=t.querySelector('img'); if(img) openPreview(img.src);
  });
});
previewClose.addEventListener('click', closePreview);
preview.addEventListener('click', e=>{ if(e.target===preview) closePreview(); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape' && preview.classList.contains('show')) closePreview(); });
</script>

</body>
</html>
<?php $conn->close(); ?>
