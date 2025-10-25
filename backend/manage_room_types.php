<?php
// 1. Start session & Check embed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ADMIN_EMBED')) {
    // exit('Direct access denied'); // Allow direct access for now if needed, or keep secured
}
// Needed for logging
$current_admin_id = $_SESSION['user_id'] ?? 0;

// 2. Connect DB & Config Paths
require_once __DIR__ . '/connectdb.php'; // Make sure this path is correct
define('IMG_UPLOAD_PATH', __DIR__ . '/../assets/img/');
define('IMG_DISPLAY_PATH', '../assets/img/');

// --- 🌟 [MODIFIED] Function: Upload Image (with Cropping) ---
function uploadRoomImage($fileInputName, $roomTypeName, $cropDataJson = null) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName = $_FILES[$fileInputName]['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $safeRoomTypeName = preg_replace('/[^a-z0-9]+/', '-', strtolower($roomTypeName));
        $newFileName = $safeRoomTypeName . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (!is_dir(IMG_UPLOAD_PATH)) { mkdir(IMG_UPLOAD_PATH, 0775, true); }
            if (!is_writable(IMG_UPLOAD_PATH)) { error_log('Upload directory is not writable: ' . IMG_UPLOAD_PATH); return ['error' => 'โฟลเดอร์สำหรับอัปโหลดไม่สามารถเขียนได้']; }
            
            $dest_path = IMG_UPLOAD_PATH . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                
                // --- 🌟 NEW CROPPING LOGIC 🌟 ---
                if ($cropDataJson && $cropDataJson !== 'null' && $cropDataJson !== '') {
                    try {
                        $cropData = json_decode($cropDataJson, true);
                        if (json_last_error() !== JSON_ERROR_NONE) { throw new Exception('Invalid JSON crop data.'); }

                        $x = round($cropData['x']);
                        $y = round($cropData['y']);
                        $w = round($cropData['width']);
                        $h = round($cropData['height']);

                        list($orig_w, $orig_h, $type) = getimagesize($dest_path);
                        $sourceImage = null;
                        switch ($type) {
                            case IMAGETYPE_JPEG: $sourceImage = imagecreatefromjpeg($dest_path); break;
                            case IMAGETYPE_PNG: $sourceImage = imagecreatefrompng($dest_path); break;
                            case IMAGETYPE_GIF: $sourceImage = imagecreatefromgif($dest_path); break;
                            case IMAGETYPE_WEBP: $sourceImage = imagecreatefromwebp($dest_path); break;
                            default: throw new Exception('Unsupported image type for cropping.');
                        }
                        if (!$sourceImage) throw new Exception('Could not create image resource.');

                        // เราจะ Crop ให้เป็นสัดส่วน 2:1 โดยมีความกว้างมาตรฐาน 1200px
                        $targetWidth = 1200;
                        $targetHeight = 600; // 2:1 Aspect Ratio
                        
                        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

                        // Handle PNG/GIF transparency
                        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                            imagealphablending($targetImage, false);
                            imagesavealpha($targetImage, true);
                            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
                        }

                        // Crop และ Resize
                        imagecopyresampled($targetImage, $sourceImage, 0, 0, $x, $y, $targetWidth, $targetHeight, $w, $h);

                        // บันทึกทับไฟล์เดิมที่ย้ายมา
                        switch ($type) {
                            case IMAGETYPE_JPEG: imagejpeg($targetImage, $dest_path, 90); break;
                            case IMAGETYPE_PNG: imagepng($targetImage, $dest_path, 9); break;
                            case IMAGETYPE_GIF: imagegif($targetImage, $dest_path); break;
                            case IMAGETYPE_WEBP: imagewebp($targetImage, $dest_path, 90); break;
                        }
                        imagedestroy($sourceImage);
                        imagedestroy($targetImage);

                    } catch (Exception $e) {
                        // หาก Crop ผิดพลาด อย่างน้อยก็ยังใช้รูปเดิมได้
                        error_log('Image Cropping Error: ' . $e->getMessage() . ' | File: ' . $newFileName);
                        // หากต้องการบังคับว่าต้อง Crop เท่านั้น ให้ uncomment บรรทัดล่าง
                        // return ['error' => 'เกิดข้อผิดพลาดในการ Crop รูป: ' . $e->getMessage()];
                    }
                }
                // --- 🌟 END CROPPING LOGIC 🌟 ---
                return $newFileName; // Success
            }
            else { error_log('Error moving uploaded file to: ' . $dest_path . ' from ' . $fileTmpPath); return ['error' => 'ไม่สามารถย้ายไฟล์ไปยัง Destination ได้']; }
        } else { return ['error' => 'นามสกุลไฟล์ไม่ถูกต้อง (' . implode(', ', $allowedfileExtensions) . ')']; }
    } elseif (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_NO_FILE) {
        return ['error' => 'เกิดข้อผิดพลาดในการอัปโหลด: รหัส ' . $_FILES[$fileInputName]['error']];
    }
    return null;
}

// --- Function: Update Related Items (Modified) ---
// (No changes to this function)
function updateRelatedItems($conn, $rt_id, $tableName, $columnName, $items) {
    $sql_delete = "DELETE FROM {$tableName} WHERE rt_id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $rt_id);
    if (!mysqli_stmt_execute($stmt_delete)) { mysqli_stmt_close($stmt_delete); throw new Exception("Error deleting from {$tableName}: " . mysqli_error($conn)); }
    mysqli_stmt_close($stmt_delete);
    $filteredItems = array_filter(array_map('trim', $items), function($value) { return $value !== ''; });
    if (!empty($filteredItems)) {
        $sql_insert = "";
        if ($tableName === 'room_into') {
            $sql_insert = "INSERT INTO {$tableName} ({$columnName}, rt_id) VALUES (?, ?)";
        } elseif ($tableName === 'booking_benefits') {
            $default_icon = 'bi-check-circle';
            $sql_insert = "INSERT INTO {$tableName} (rt_id, ben_icon, {$columnName}) VALUES (?, ?, ?)";
        } elseif ($tableName === 'rooms') {
            $default_status = 'available';
            $sql_insert = "INSERT INTO {$tableName} (rt_id, {$columnName}, status) VALUES (?, ?, ?)";
        } else {
             throw new Exception("Unsupported table for updateRelatedItems: {$tableName}");
        }
        $stmt_insert = mysqli_prepare($conn, $sql_insert);
        foreach ($filteredItems as $item) {
            if ($tableName === 'room_into') { mysqli_stmt_bind_param($stmt_insert, "si", $item, $rt_id); }
            elseif ($tableName === 'booking_benefits') { mysqli_stmt_bind_param($stmt_insert, "iss", $rt_id, $default_icon, $item); }
            elseif ($tableName === 'rooms') { mysqli_stmt_bind_param($stmt_insert, "iss", $rt_id, $item, $default_status); }
            if (!mysqli_stmt_execute($stmt_insert)) { mysqli_stmt_close($stmt_insert); throw new Exception("Error inserting into {$tableName} ('{$item}'): " . mysqli_error($conn)); }
        }
        mysqli_stmt_close($stmt_insert);
    }
}


// --- POST Request Handling ---
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Add Room Type (Modified: Pass crop data) ---
    if ($action === 'add_room_type') {
        $rt_name = trim($_POST['rt_name'] ?? '');
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $amenityItems = $_POST['amenities'] ?? [];
        $benefitItems = $_POST['benefits'] ?? [];
        $roomNumberItems = $_POST['room_numbers'] ?? [];
        $uploadedFileName = null;
        
        // 🌟 GET CROP DATA
        $cropData = $_POST['add_crop_data'] ?? null;

        if (empty($rt_name) || $price === false || $price < 0) {
            $message = 'กรุณากรอกชื่อประเภทห้องพักและราคาให้ถูกต้อง';
            $message_type = 'danger';
        } else {
            mysqli_begin_transaction($conn);
            try {
                // 1. Insert room_type
                $sql_type = "INSERT INTO room_type (rt_name, price) VALUES (?, ?)";
                $stmt_type = mysqli_prepare($conn, $sql_type);
                mysqli_stmt_bind_param($stmt_type, "sd", $rt_name, $price);
                if (!mysqli_stmt_execute($stmt_type)) throw new Exception("Error inserting room type: " . mysqli_stmt_error($stmt_type));
                $new_rt_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_type);

                // 2. Image Upload (Passing crop data)
                $uploadedFileNameResult = uploadRoomImage('r_image', $rt_name, $cropData);
                if (is_array($uploadedFileNameResult) && isset($uploadedFileNameResult['error'])) { throw new Exception("Image upload error: " . $uploadedFileNameResult['error']); }
                $uploadedFileName = $uploadedFileNameResult;
                if ($uploadedFileName) {
                    $sql_img = "INSERT INTO room_images (rt_id, r_images) VALUES (?, ?)";
                    $stmt_img = mysqli_prepare($conn, $sql_img);
                    mysqli_stmt_bind_param($stmt_img, "is", $new_rt_id, $uploadedFileName);
                    if (!mysqli_stmt_execute($stmt_img)) throw new Exception("Error inserting room image: " . mysqli_stmt_error($stmt_img));
                    mysqli_stmt_close($stmt_img);
                }

                // 3. Insert Amenities, Benefits, Room Numbers
                updateRelatedItems($conn, $new_rt_id, 'room_into', 'rin_name', $amenityItems);
                updateRelatedItems($conn, $new_rt_id, 'booking_benefits', 'ben_text', $benefitItems);
                updateRelatedItems($conn, $new_rt_id, 'rooms', 'room_number', $roomNumberItems);

                mysqli_commit($conn);
                $message = 'เพิ่มประเภทห้องพัก "' . htmlspecialchars($rt_name) . '" สำเร็จ!';
                $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage());
                $message_type = 'danger';
                error_log("Add Room Type Error: " . $e->getMessage());
                if ($uploadedFileName && is_string($uploadedFileName) && file_exists(IMG_UPLOAD_PATH . $uploadedFileName)) { unlink(IMG_UPLOAD_PATH . $uploadedFileName); }
            }
        }
    }

    // --- Edit Room Type (Modified: Pass crop data) ---
    elseif ($action === 'edit_room_type') {
        $rt_id = filter_input(INPUT_POST, 'rt_id', FILTER_VALIDATE_INT);
        $rt_name = trim($_POST['rt_name'] ?? '');
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $amenityItems = $_POST['amenities'] ?? [];
        $benefitItems = $_POST['benefits'] ?? [];
        $current_image = $_POST['r_image_current'] ?? null;
        $newUploadedFileName = null;

        // 🌟 GET CROP DATA
        $cropData = $_POST['edit_crop_data'] ?? null;

        if (!$rt_id || empty($rt_name) || $price === false || $price < 0) {
            $message = 'ข้อมูลประเภทห้องพักหรือราคาไม่ถูกต้อง';
            $message_type = 'danger';
        } else {
            mysqli_begin_transaction($conn);
            try {
                // 1. Update room_type table
                $sql_update_type = "UPDATE room_type SET rt_name = ?, price = ? WHERE rt_id = ?";
                $stmt_update_type = mysqli_prepare($conn, $sql_update_type);
                mysqli_stmt_bind_param($stmt_update_type, "sdi", $rt_name, $price, $rt_id);
                if (!mysqli_stmt_execute($stmt_update_type)) { throw new Exception("Error updating room type: " . mysqli_stmt_error($stmt_update_type)); }
                mysqli_stmt_close($stmt_update_type);

                // 2. Handle Image Update (Passing crop data)
                $newUploadedFileNameResult = uploadRoomImage('r_image_new', $rt_name, $cropData);
                if (is_array($newUploadedFileNameResult) && isset($newUploadedFileNameResult['error'])) { throw new Exception("Image upload error: " . $newUploadedFileNameResult['error']); }
                $newUploadedFileName = $newUploadedFileNameResult;
                if ($newUploadedFileName) {
                    $sql_img_update = "INSERT INTO room_images (rt_id, r_images) VALUES (?, ?) ON DUPLICATE KEY UPDATE r_images = ?";
                    $stmt_img_update = mysqli_prepare($conn, $sql_img_update);
                    mysqli_stmt_bind_param($stmt_img_update, "iss", $rt_id, $newUploadedFileName, $newUploadedFileName);
                    if (!mysqli_stmt_execute($stmt_img_update)) { throw new Exception("Error updating room image in DB: " . mysqli_stmt_error($stmt_img_update)); }
                    mysqli_stmt_close($stmt_img_update);
                    if ($current_image && $current_image != $newUploadedFileName && file_exists(IMG_UPLOAD_PATH . $current_image)) { unlink(IMG_UPLOAD_PATH . $current_image); }
                }

                // 3. Update Amenities, Benefits
                updateRelatedItems($conn, $rt_id, 'room_into', 'rin_name', $amenityItems);
                updateRelatedItems($conn, $rt_id, 'booking_benefits', 'ben_text', $benefitItems);

                mysqli_commit($conn);
                $message = 'แก้ไขประเภทห้องพัก "' . htmlspecialchars($rt_name) . '" สำเร็จ!';
                $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = 'เกิดข้อผิดพลาดในการแก้ไข: ' . htmlspecialchars($e->getMessage());
                $message_type = 'danger';
                error_log("Edit Room Type Error (rt_id: {$rt_id}): " . $e->getMessage());
                if ($newUploadedFileName && is_string($newUploadedFileName) && file_exists(IMG_UPLOAD_PATH . $newUploadedFileName)) { unlink(IMG_UPLOAD_PATH . $newUploadedFileName); }
            }
        }
    }

    // --- Update Room Numbers Action ---
    // (No changes to this action)
    elseif ($action === 'update_room_numbers') {
        $rt_id = filter_input(INPUT_POST, 'rt_id', FILTER_VALIDATE_INT);
        $roomNumberItems = $_POST['room_numbers'] ?? []; 
        if (!$rt_id) {
             $message = 'ID ประเภทห้องพักไม่ถูกต้อง';
             $message_type = 'danger';
        } else {
             mysqli_begin_transaction($conn);
             try {
                 updateRelatedItems($conn, $rt_id, 'rooms', 'room_number', $roomNumberItems);
                 mysqli_commit($conn);
                 $message = 'แก้ไขหมายเลขห้องสำเร็จ!';
                 $message_type = 'success';
             } catch (Exception $e) {
                 mysqli_rollback($conn);
                 $message = 'เกิดข้อผิดพลาดในการแก้ไขหมายเลขห้อง: ' . htmlspecialchars($e->getMessage());
                 $message_type = 'danger';
                 error_log("Update Room Numbers Error (rt_id: {$rt_id}): " . $e->getMessage());
             }
        }
    }

    // --- Delete Room Type (Same as before) ---
    // (No changes to this action)
    elseif ($action === 'delete_room_type') {
        $rt_id = filter_input(INPUT_POST, 'rt_id', FILTER_VALIDATE_INT);
        $imageFilesToDelete = [];
        if (!$rt_id) { $message = 'ID ประเภทห้องพักไม่ถูกต้องสำหรับการลบ'; $message_type = 'danger'; }
        else {
            $rt_name_to_delete = '(ไม่ทราบชื่อ)';
            $sql_get_name = "SELECT rt_name FROM room_type WHERE rt_id = ?";
            $stmt_get_name = mysqli_prepare($conn, $sql_get_name);
            if($stmt_get_name) { mysqli_stmt_bind_param($stmt_get_name, "i", $rt_id); mysqli_stmt_execute($stmt_get_name); $result_name = mysqli_stmt_get_result($stmt_get_name); if ($row_name = mysqli_fetch_assoc($result_name)) { $rt_name_to_delete = $row_name['rt_name']; } mysqli_stmt_close($stmt_get_name); }

            mysqli_begin_transaction($conn);
            try {
                $sql_get_images = "SELECT r_images FROM room_images WHERE rt_id = ?";
                $stmt_get_images = mysqli_prepare($conn, $sql_get_images); mysqli_stmt_bind_param($stmt_get_images, "i", $rt_id); mysqli_stmt_execute($stmt_get_images); $result_images = mysqli_stmt_get_result($stmt_get_images);
                while ($row_image = mysqli_fetch_assoc($result_images)) { if (!empty($row_image['r_images'])) { $imageFilesToDelete[] = $row_image['r_images']; } } mysqli_stmt_close($stmt_get_images);

                $tablesToDeleteFrom = ['rooms', 'room_into', 'booking_benefits', 'room_images']; 
                foreach ($tablesToDeleteFrom as $table) {
                    $sql_delete_child = "DELETE FROM {$table} WHERE rt_id = ?"; $stmt_delete_child = mysqli_prepare($conn, $sql_delete_child); mysqli_stmt_bind_param($stmt_delete_child, "i", $rt_id);
                    if (!mysqli_stmt_execute($stmt_delete_child)) { throw new Exception("Error deleting from {$table}: " . mysqli_stmt_error($stmt_delete_child)); } mysqli_stmt_close($stmt_delete_child);
                }
                $sql_delete_parent = "DELETE FROM room_type WHERE rt_id = ?"; $stmt_delete_parent = mysqli_prepare($conn, $sql_delete_parent); mysqli_stmt_bind_param($stmt_delete_parent, "i", $rt_id);
                if (!mysqli_stmt_execute($stmt_delete_parent)) { throw new Exception("Error deleting from room_type: " . mysqli_stmt_error($stmt_delete_parent)); } mysqli_stmt_close($stmt_delete_parent);

                mysqli_commit($conn);
                $deletedFilesCount = 0;
                foreach ($imageFilesToDelete as $filename) { $filePath = IMG_UPLOAD_PATH . $filename; if (file_exists($filePath) && is_file($filePath)) { if (unlink($filePath)) { $deletedFilesCount++; } else { error_log("Failed to delete image file: " . $filePath); } } }
                $message = 'ลบประเภทห้องพัก "' . htmlspecialchars($rt_name_to_delete) . '" และข้อมูลที่เกี่ยวข้อง (' . $deletedFilesCount . ' รูป) สำเร็จ!';
                $message_type = 'success';
            } catch (Exception $e) { mysqli_rollback($conn); $message = 'เกิดข้อผิดพลาดในการลบ: ' . htmlspecialchars($e->getMessage()); $message_type = 'danger'; error_log("Delete Room Type Error (rt_id: {$rt_id}): " . $e->getMessage()); }
        }
    }
}

// --- Fetch Data for Display ---
// (No changes here)
$roomTypes = [];
$sql_select = "SELECT rt.rt_id, rt.rt_name, rt.price, MIN(ri.r_images) as first_image
                FROM room_type rt
                LEFT JOIN room_images ri ON rt.rt_id = ri.rt_id
                GROUP BY rt.rt_id, rt.rt_name, rt.price
                ORDER BY rt.rt_id DESC";
$result = mysqli_query($conn, $sql_select);
if ($result) {
    $roomTypes = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    if (empty($message)) { $message = "Error fetching room types: " . mysqli_error($conn); $message_type = 'danger'; }
}
if ($conn && mysqli_ping($conn)) { mysqli_close($conn); }
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<style>
    /* ✅ (No changes to original CSS) */
    body { font-family: 'Sarabun', sans-serif; background-color: #f0f2f5; }
    .card { border: none; border-radius: 0.8rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06); margin-bottom: 1.75rem; }
    .card-header { background-color: #fff; border-bottom: 1px solid #e9ecef; padding: 1rem 1.5rem; font-size: 1rem; font-weight: 600; color: var(--bs-dark); }
    .card-title { font-weight: 600; }
    .table thead th { background-color: #f8f9fa; border-bottom-width: 1px; font-weight: 600; font-size: 0.9rem; padding: 0.8rem 1rem; }
    .table tbody td { vertical-align: middle; font-size: 0.9rem; padding: 0.8rem 1rem; }
    .table-hover tbody tr:hover { background-color: rgba(var(--bs-primary-rgb), 0.05); }
    .img-thumbnail-small { max-width: 60px; max-height: 40px; object-fit: cover; border-radius: 0.25rem; }
    .modal-header { border-bottom: 1px solid #dee2e6; }
    .modal-footer { border-top: 1px solid #dee2e6; background-color: #f8f9fa; }
    .input-group-dynamic { margin-bottom: 0.5rem; }
    .input-group-dynamic input { border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; }
    .input-group-dynamic .btn-danger { border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; }
    /* 🌟 [REPLACED] โค้ดที่แก้ไขแล้วสำหรับ Scrollbar 🌟 */
.modal-dialog-scrollable .modal-body {
    /* --- Firefox --- */
    scrollbar-width: thin;
    scrollbar-color: #adb5bd #f8f9fa;
}

/* --- Webkit (Chrome, Edge, Safari) --- */
.modal-dialog-scrollable .modal-body::-webkit-scrollbar {
    width: 8px; /* ความกว้างของ Scrollbar */
}
.modal-dialog-scrollable .modal-body::-webkit-scrollbar-track {
    background: #f1f1f1; /* สีพื้นหลังของ Track */
    border-radius: 10px;
}
.modal-dialog-scrollable .modal-body::-webkit-scrollbar-thumb {
    background: #adb5bd; /* สีเทาของแถบเลื่อน */
    border-radius: 10px;
}
.modal-dialog-scrollable .modal-body::-webkit-scrollbar-thumb:hover {
    background: #555; /* สีเข้มขึ้นเมื่อ Hover */
}
/* 🌟 สิ้นสุดโค้ดที่แก้ไข 🌟 */
    /* 🌟 [NEW] CROPPER STYLES */
    #cropper-image-container {
        max-height: 60vh; /* จำกัดความสูงของรูปใน modal */
        overflow: hidden;
    }
    #cropper-image-container img {
        max-width: 100%;
    }
    /* 🌟 [NEW] IMAGE PREVIEW STYLES */
    .image-preview-box {
        width: 100%;
        height: 150px; /* 2:1 ratio (approx 300px width) */
        background-color: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin-bottom: 1rem;
    }
    .image-preview-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .image-preview-box .text-muted {
        font-size: 0.9rem;
    }

</style>

<div class="container-fluid px-4">
    <h1 class="mt-4 fw-bold">จัดการข้อมูลห้องพัก</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">เพิ่ม ลบ แก้ไข ประเภทห้องพักและรายละเอียด</li>
    </ol>

    <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="bi bi-door-open-fill me-2"></i>ประเภทห้องพัก</h5>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRoomTypeModal">
                <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มประเภทห้องพัก
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 5%;">#ID</th>
                            <th scope="col" style="width: 10%;">รูปภาพ</th>
                            <th scope="col">ชื่อประเภทห้องพัก</th>
                            <th scope="col" style="width: 15%;">ราคา (บาท)</th>
                            <th scope="col" class="text-center" style="width: 20%;">เครื่องมือ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roomTypes)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีข้อมูลประเภทห้องพัก</td></tr>
                        <?php else: ?>
                            <?php foreach ($roomTypes as $type): ?>
                            <tr>
                                <th scope="row"><?= $type['rt_id'] ?></th>
                                <td>
                                    <?php if (!empty($type['first_image'])): ?>
                                        <img src="<?= IMG_DISPLAY_PATH . htmlspecialchars($type['first_image']) ?>" alt="<?= htmlspecialchars($type['rt_name']) ?>" class="img-thumbnail-small">
                                    <?php else: ?>
                                        <div class="text-center text-muted small" style="width: 60px;">-</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($type['rt_name']) ?></td>
                                <td><?= number_format($type['price'], 2) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-secondary btn-sm edit-rooms-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editRoomsModal"
                                            data-id="<?= $type['rt_id'] ?>"
                                            data-name="<?= htmlspecialchars($type['rt_name'], ENT_QUOTES) ?>"
                                            title="แก้ไขหมายเลขห้อง">
                                        <i class="bi bi-hash"></i> แก้ไขห้อง
                                    </button>
                                    <button class="btn btn-primary btn-sm edit-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editRoomTypeModal"
                                            data-id="<?= $type['rt_id'] ?>"
                                            data-name="<?= htmlspecialchars($type['rt_name'], ENT_QUOTES) ?>"
                                            data-price="<?= $type['price'] ?>"
                                            title="แก้ไขรายละเอียดประเภท">
                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-btn"
                                            data-id="<?= $type['rt_id'] ?>"
                                            data-name="<?= htmlspecialchars($type['rt_name'], ENT_QUOTES) ?>"
                                            title="ลบประเภทห้องพัก">
                                        <i class="bi bi-trash3-fill"></i> ลบ
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addRoomTypeModal" tabindex="-1" aria-labelledby="addRoomTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="addRoomTypeModalLabel"><i class="bi bi-plus-circle-fill me-2"></i>เพิ่มประเภทห้องพักใหม่</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addRoomTypeForm" method="POST" action="admin.php?page=manage_room_types" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_room_type">
                        <input type="hidden" name="add_crop_data" id="add-crop-data">
                        
                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-info-circle-fill me-1"></i> ข้อมูลหลัก</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-8"><label for="add-rt-name" class="form-label">ชื่อประเภทห้องพัก <span class="text-danger">*</span></label><input type="text" class="form-control" id="add-rt-name" name="rt_name" required></div>
                            <div class="col-md-4"><label for="add-price" class="form-label">ราคา (บาท) <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" class="form-control" id="add-price" name="price" required></div>
                            <div class="col-12">
                                <label for="add-r-image" class="form-label">รูปภาพหลัก (ถ้ามี)</label>
                                <div class="image-preview-box" id="add-image-preview">
                                    <span class="text-muted">เลือกรูปภาพเพื่อแสดงตัวอย่าง</span>
                                </div>
                                <input class="form-control" type="file" id="add-r-image" name="r_image" accept="image/png, image/jpeg, image/gif, image/webp" 
                                       data-cropper-trigger="true" 
                                       data-target-hidden-input="#add-crop-data"
                                       data-target-preview="#add-image-preview">
                                <div class="form-text">เลือกรูปภาพ (ระบบจะให้คุณ Crop เป็นสัดส่วน 2:1)</div>
                            </div>
                        </div>

                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-list-check me-1"></i> สิ่งอำนวยความสะดวก</h6>
                        <div class="mb-3" id="add-amenities-list"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-4 add-input-btn" data-target="#add-amenities-list" data-name="amenities[]"><i class="bi bi-plus-circle me-1"></i>เพิ่มรายการ</button>

                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-gift-fill me-1"></i> สิทธิประโยชน์</h6>
                        <div class="mb-3" id="add-benefits-list"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-4 add-input-btn" data-target="#add-benefits-list" data-name="benefits[]"><i class="bi bi-plus-circle me-1"></i>เพิ่มรายการ</button>

                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-hash me-1"></i> หมายเลขห้อง</h6>
                        <div class="mb-3" id="add-room-numbers-list"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3 add-input-btn" data-target="#add-room-numbers-list" data-name="room_numbers[]"><i class="bi bi-plus-circle me-1"></i>เพิ่มหมายเลขห้อง</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editRoomTypeModal" tabindex="-1" aria-labelledby="editRoomTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="editRoomTypeModalLabel"><i class="bi bi-pencil-fill me-2"></i>แก้ไขประเภทห้องพัก</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editRoomTypeForm" method="POST" action="admin.php?page=manage_room_types" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_room_type">
                        <input type="hidden" id="edit-rt-id" name="rt_id">
                        <input type="hidden" name="edit_crop_data" id="edit-crop-data">
                        
                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-info-circle-fill me-1"></i> ข้อมูลหลัก</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-8"><label for="edit-rt-name" class="form-label">ชื่อประเภทห้องพัก <span class="text-danger">*</span></label><input type="text" class="form-control" id="edit-rt-name" name="rt_name" required></div>
                            <div class="col-md-4"><label for="edit-price" class="form-label">ราคา (บาท) <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" class="form-control" id="edit-price" name="price" required></div>
                            <div class="col-12">
                                <label class="form-label">รูปภาพปัจจุบัน:</label>
                                <div class="image-preview-box" id="edit-image-preview">
                                    <span class="text-muted small">กำลังโหลด...</span>
                                </div>
                                <label for="edit-r-image" class="form-label">เปลี่ยนรูปภาพ (ถ้าต้องการ)</label>
                                <input class="form-control" type="file" id="edit-r-image" name="r_image_new" accept="image/png, image/jpeg, image/gif, image/webp"
                                       data-cropper-trigger="true" 
                                       data-target-hidden-input="#edit-crop-data"
                                       data-target-preview="#edit-image-preview">
                                <input type="hidden" id="edit-r-image-current" name="r_image_current">
                            </div>
                        </div>

                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-list-check me-1"></i> สิ่งอำนวยความสะดวก</h6>
                        <div class="mb-3" id="edit-amenities-list">
                            <span class="text-muted small">กำลังโหลด...</span>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-4 add-input-btn" data-target="#edit-amenities-list" data-name="amenities[]"><i class="bi bi-plus-circle me-1"></i>เพิ่มรายการ</button>

                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-gift-fill me-1"></i> สิทธิประโยชน์</h6>
                        <div class="mb-3" id="edit-benefits-list">
                            <span class="text-muted small">กำลังโหลด...</span>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-4 add-input-btn" data-target="#edit-benefits-list" data-name="benefits[]"><i class="bi bi-plus-circle me-1"></i>เพิ่มรายการ</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editRoomsModal" tabindex="-1" aria-labelledby="editRoomsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="editRoomsModalLabel"><i class="bi bi-hash me-2"></i>แก้ไขหมายเลขห้อง</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editRoomsForm" method="POST" action="admin.php?page=manage_room_types">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_room_numbers">
                        <input type="hidden" id="edit-rooms-rt-id" name="rt_id">
                        <p>กำลังแก้ไขหมายเลขห้องสำหรับ: <strong id="edit-rooms-rt-name-display"></strong></p>

                        <h6 class="text-primary border-bottom pb-2 mb-3">รายการหมายเลขห้อง</h6>
                        <div class="mb-3" id="edit-room-numbers-list">
                            <span class="text-muted small">กำลังโหลด...</span>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3 add-input-btn" data-target="#edit-room-numbers-list" data-name="room_numbers[]"><i class="bi bi-plus-circle me-1"></i>เพิ่มหมายเลขห้อง</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกหมายเลขห้อง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <form id="deleteRoomTypeForm" method="POST" action="admin.php?page=manage_room_types" style="display: none;">
        <input type="hidden" name="action" value="delete_room_type">
        <input type="hidden" id="delete-rt-id" name="rt_id">
    </form>
</div>


<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cropperModalLabel"><i class="bi bi-crop me-2"></i>ตัดรูปภาพ (สัดส่วน 2:1)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">ลากกรอบสี่เหลี่ยมเพื่อเลือกมุมมองที่ต้องการ และซูมเข้า-ออกได้</p>
                <div id="cropper-image-container">
                    <img id="cropper-image-src" src="" alt="Source Image">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cropper-cancel-btn">ยกเลิก</button>
                <button type="button" class="btn btn-primary" id="cropper-save-btn">ตัดและบันทึก</button>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- (No changes to Helper Function) ---
    function addInputRow(targetSelector, inputName, value = '') {
        const list = document.querySelector(targetSelector);
        if (!list) return;
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm input-group-dynamic';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.name = inputName;
        input.value = value;
        input.placeholder = 'กรอกรายการ...';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-danger';
        button.innerHTML = '<i class="bi bi-trash"></i>';
        button.onclick = function() { div.remove(); };
        div.appendChild(input);
        div.appendChild(button);
        list.appendChild(div);
    }

    // --- (No changes to Add Input Button) ---
    document.querySelectorAll('.add-input-btn').forEach(button => {
        button.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            const name = this.getAttribute('data-name');
            addInputRow(target, name);
            const list = document.querySelector(target);
            if(list && list.lastElementChild){
                list.lastElementChild.querySelector('input').focus();
            }
        });
    });

    // --- (No changes to Add Room Type Modal show/hide) ---
    const addModal = document.getElementById('addRoomTypeModal');
    if(addModal){
        addModal.addEventListener('shown.bs.modal', function () {
            if (!document.querySelector('#add-amenities-list .input-group')) { addInputRow('#add-amenities-list', 'amenities[]'); }
            if (!document.querySelector('#add-benefits-list .input-group')) { addInputRow('#add-benefits-list', 'benefits[]'); }
            if (!document.querySelector('#add-room-numbers-list .input-group')) { addInputRow('#add-room-numbers-list', 'room_numbers[]'); }
            document.getElementById('add-rt-name').focus();
        });
        addModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('add-amenities-list').innerHTML = '';
            document.getElementById('add-benefits-list').innerHTML = '';
            document.getElementById('add-room-numbers-list').innerHTML = '';
            document.getElementById('addRoomTypeForm').reset();
            // 🌟 [NEW] Clear add preview
            document.getElementById('add-image-preview').innerHTML = '<span class="text-muted">เลือกรูปภาพเพื่อแสดงตัวอย่าง</span>';
            document.getElementById('add-crop-data').value = '';
        });
    }

    // --- 🌟 [MODIFIED] Edit Room Type Modal (AJAX part) ---
    const editModal = document.getElementById('editRoomTypeModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const rtId = button.getAttribute('data-id');
            const rtName = button.getAttribute('data-name');
            const rtPrice = button.getAttribute('data-price');
            editModal.querySelector('#edit-rt-id').value = rtId;
            editModal.querySelector('#edit-rt-name').value = rtName;
            editModal.querySelector('#edit-price').value = rtPrice;
            editModal.querySelector('#editRoomTypeModalLabel').textContent = `แก้ไขประเภทห้องพัก: ${rtName}`;
            
            // 🌟 [MODIFIED] Use new preview box
            const imagePreview = editModal.querySelector('#edit-image-preview');
            const amenitiesList = editModal.querySelector('#edit-amenities-list');
            const benefitsList = editModal.querySelector('#edit-benefits-list');
            const currentImageInput = editModal.querySelector('#edit-r-image-current');

            // Clear previous data
            imagePreview.innerHTML = '<span class="text-muted small">กำลังโหลด...</span>';
            amenitiesList.innerHTML = '<span class="text-muted small">กำลังโหลด...</span>';
            benefitsList.innerHTML = '<span class="text-muted small">กำลังโหลด...</span>';
            currentImageInput.value = '';
            editModal.querySelector('#edit-r-image').value = '';
            editModal.querySelector('#edit-crop-data').value = '';

            // Fetch details
            fetch(`ajax_get_room_details.php?rt_id=${rtId}`) 
                .then(response => {
                    if (!response.ok) { throw new Error('Network response was not ok ' + response.statusText); }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // 🌟 [MODIFIED] Set Image Preview
                        if (data.image) {
                            imagePreview.innerHTML = `<img src="<?= IMG_DISPLAY_PATH ?>${data.image}" alt="${rtName}">`;
                            currentImageInput.value = data.image;
                        } else {
                            imagePreview.innerHTML = '<span class="text-muted small">ไม่มีรูปภาพ</span>';
                        }
                        // Amenities
                        amenitiesList.innerHTML = ''; 
                        if (data.amenities && data.amenities.length > 0) {
                            data.amenities.forEach(item => addInputRow('#edit-amenities-list', 'amenities[]', item));
                        } else {
                            addInputRow('#edit-amenities-list', 'amenities[]');
                        }
                        // Benefits
                        benefitsList.innerHTML = ''; 
                        if (data.benefits && data.benefits.length > 0) {
                            data.benefits.forEach(item => addInputRow('#edit-benefits-list', 'benefits[]', item));
                        } else {
                            addInputRow('#edit-benefits-list', 'benefits[]');
                        }
                    } else {
                        imagePreview.innerHTML = '<span class="text-danger small">โหลดข้อมูลผิดพลาด</span>';
                        amenitiesList.innerHTML = '<span class="text-danger small">โหลดข้อมูลผิดพลาด</span>';
                        benefitsList.innerHTML = '<span class="text-danger small">โหลดข้อมูลผิดพลาด</span>';
                        console.error("Error fetching details:", data.error);
                    }
                })
                .catch(error => {
                    imagePreview.innerHTML = '<span class="text-danger small">เกิดข้อผิดพลาด Network</span>';
                    amenitiesList.innerHTML = '<span class="text-danger small">เกิดข้อผิดพลาด Network</span>';
                    benefitsList.innerHTML = '<span class="text-danger small">เกิดข้อผิดพลาด Network</span>';
                    console.error("Network error fetching details:", error);
                });
        });
        editModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('edit-amenities-list').innerHTML = '';
            document.getElementById('edit-benefits-list').innerHTML = '';
            // 🌟 [NEW] Clear edit preview on hide
            document.getElementById('edit-image-preview').innerHTML = '';
            document.getElementById('edit-crop-data').value = '';
        });
    }

    // --- (No changes to Edit Rooms Modal) ---
    const editRoomsModal = document.getElementById('editRoomsModal');
    if (editRoomsModal) {
        editRoomsModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const rtId = button.getAttribute('data-id');
            const rtName = button.getAttribute('data-name');
            editRoomsModal.querySelector('#edit-rooms-rt-id').value = rtId;
            editRoomsModal.querySelector('#edit-rooms-rt-name-display').textContent = rtName;
            editRoomsModal.querySelector('#editRoomsModalLabel').textContent = `แก้ไขหมายเลขห้อง: ${rtName}`;
            const roomsList = editRoomsModal.querySelector('#edit-room-numbers-list');
            roomsList.innerHTML = '<span class="text-muted small">กำลังโหลด...</span>'; 
            fetch(`ajax_get_rooms.php?rt_id=${rtId}`)
                .then(response => {
                     if (!response.ok) { throw new Error('Network response was not ok ' + response.statusText); }
                     return response.json();
                })
                .then(data => {
                    roomsList.innerHTML = '';
                    if (data.success && data.rooms && data.rooms.length > 0) {
                        data.rooms.forEach(roomNumber => addInputRow('#edit-room-numbers-list', 'room_numbers[]', roomNumber));
                    } else if (data.success) {
                        addInputRow('#edit-room-numbers-list', 'room_numbers[]');
                    }
                    else {
                        roomsList.innerHTML = '<span class="text-danger small">โหลดข้อมูลผิดพลาด</span>';
                        console.error("Error fetching rooms:", data.error);
                    }
                })
                .catch(error => {
                    roomsList.innerHTML = '<span class="text-danger small">เกิดข้อผิดพลาด Network</span>';
                    console.error("Network error fetching rooms:", error);
                });
        });
        editRoomsModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('edit-room-numbers-list').innerHTML = '';
        });
    }

    // --- (No changes to Delete Button) ---
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const rtId = this.getAttribute('data-id');
            const rtName = this.getAttribute('data-name');
            Swal.fire({
                title: 'ยืนยันการลบ',
                html: `คุณต้องการลบประเภทห้องพัก "<strong>${rtName}</strong>" ใช่หรือไม่?<br><strong class='text-danger'>ข้อมูลห้องพัก, รูปภาพ, และรายละเอียดที่เกี่ยวข้องทั้งหมดจะถูกลบอย่างถาวร!</strong>`,
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d', confirmButtonText: 'ใช่, ลบเลย', cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) { document.getElementById('delete-rt-id').value = rtId; document.getElementById('deleteRoomTypeForm').submit(); }
            });
        });
    });

    // --- (No changes to Auto-close Alerts) ---
    const alertMessages = document.querySelectorAll('.alert-dismissible');
    alertMessages.forEach(alert => { setTimeout(() => { const bsAlert = bootstrap.Alert.getInstance(alert); if (bsAlert) { bsAlert.close(); } }, 5000); });


    // --- 🌟 [NEW] CROPPER.JS LOGIC ---
    const cropperModalEl = document.getElementById('cropperModal');
    const cropperImage = document.getElementById('cropper-image-src');
    const cropperSaveBtn = document.getElementById('cropper-save-btn');
    const cropperCancelBtn = document.getElementById('cropper-cancel-btn');
    const bsCropperModal = new bootstrap.Modal(cropperModalEl);
    let cropper = null;
    let currentFileTrigger = null;     // The <input type="file"> that was clicked
    let currentHiddenInput = null;   // The <input type="hidden"> to save data to
    let currentPreviewBox = null;    // The <div> to show the preview in
    let originalFileInputValue = null; // To reset file input on cancel

    // 1. Listen for file selection on any trigger
    document.querySelectorAll('[data-cropper-trigger="true"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) {
                return;
            }

            // Store which elements we're working with
            currentFileTrigger = e.target;
            originalFileInputValue = e.target.value; // Save this to reset on cancel
            currentHiddenInput = document.querySelector(e.target.dataset.targetHiddenInput);
            currentPreviewBox = document.querySelector(e.target.dataset.targetPreview);

            // Read the file and show the cropper modal
            const reader = new FileReader();
            reader.onload = (event) => {
                cropperImage.src = event.target.result;
                bsCropperModal.show();
            };
            reader.readAsDataURL(file);
        });
    });

    // 2. Initialize Cropper when modal is shown
    cropperModalEl.addEventListener('shown.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
        }
        cropper = new Cropper(cropperImage, {
            aspectRatio: 2 / 1, // 🌟 Force 2:1 Aspect Ratio
            viewMode: 1,        // Restrict crop box to canvas
            background: false,
            autoCropArea: 0.9,
        });
    });

    // 3. Handle Cropper "Save" button
    cropperSaveBtn.addEventListener('click', function() {
        if (!cropper) {
            return;
        }

        // Get rounded crop data
        const cropData = cropper.getData(true);
        currentHiddenInput.value = JSON.stringify(cropData);

        // Create a preview
        const previewCanvas = cropper.getCroppedCanvas({
            width: 600, // Create a 600x300 preview
            height: 300
        });
        
        currentPreviewBox.innerHTML = ''; // Clear "loading" text
        currentPreviewBox.appendChild(previewCanvas);
        previewCanvas.style.width = '100%'; // Make canvas responsive in preview box
        previewCanvas.style.height = '100%';

        // Hide the modal
        bsCropperModal.hide();
    });

    // 4. Handle Cropper "Cancel" button
    cropperCancelBtn.addEventListener('click', function() {
        // Reset the file input so the user can select the same file again
        if (currentFileTrigger) {
            currentFileTrigger.value = null; 
        }
        // Clear the hidden crop data
        if (currentHiddenInput) {
            currentHiddenInput.value = '';
        }
        // Don't reset the preview, let it show the *original* image (from ajax)
        // bsCropperModal.hide() is handled by data-bs-dismiss
    });

    // 5. Clean up when modal is hidden
    cropperModalEl.addEventListener('hidden.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        cropperImage.src = ''; // Clear image
    });

});
</script>