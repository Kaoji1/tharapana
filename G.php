<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Responsive Resort Gallery</title>
  <style>
    * {
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background: #f2f2f2;
    }
    .gallery-container {
      max-width: 1000px;
      margin: 20px auto;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .gallery-header {
      padding: 20px;
    }
    .gallery-header h1 {
      margin: 0;
      font-size: 24px;
    }
    .gallery-header p {
      margin: 5px 0 0;
      color: #555;
    }
    .gallery-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 5px;
      padding: 5px;
    }
    .main-image {
      grid-row: span 2;
    }
    .gallery-grid img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 4px;
      cursor: pointer;
    }
    .small-images {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 5px;
    }
    .overlay {
      position: relative;
    }
    .overlay::after {
      content: "";
      position: absolute;
      bottom: 0;
      right: 0;
      background: rgba(0, 0, 0, 0.6);
      color: white;
      padding: 5px 10px;
      border-bottom-right-radius: 4px;
      font-weight: bold;
    }

    /* Lightbox */
    .lightbox {
      display: none;
      position: fixed;
      z-index: 9999;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.8);
      justify-content: center;
      align-items: center;
    }
    .lightbox img {
      max-width: 90vw;
      max-height: 90vh;
      border-radius: 10px;
    }
    .lightbox.active {
      display: flex;
    }
    .close-btn {
      position: absolute;
      top: 20px;
      right: 30px;
      color: white;
      font-size: 30px;
      cursor: pointer;
    }
    .arrow {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      font-size: 40px;
      color: white;
      cursor: pointer;
      padding: 10px;
      z-index: 10000;
    }
    .arrow.prev {
      left: 20px;
    }
    .arrow.next {
      right: 20px;
    }

    /* Popup Gallery */
    .lightbox-gallery {
      display: none;
      position: fixed;
      z-index: 9998;
      top: 0; left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0,0,0,0.85);
      overflow-y: auto;
      padding: 40px 20px;
    }
    .lightbox-gallery-content {
      max-width: 1000px;
      margin: auto;
      position: relative;
    }
    .gallery-grid-popup {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 10px;
    }
    .gallery-grid-popup img {
      width: 100%;
      border-radius: 6px;
      cursor: pointer;
      transition: transform 0.2s;
    }
    .gallery-grid-popup img:hover {
      transform: scale(1.05);
    }

    @media (max-width: 768px) {
      .gallery-grid {
        grid-template-columns: 1fr;
      }
      .main-image {
        grid-row: span 1;
      }
      .small-images {
        grid-template-columns: 1fr 1fr;
      }
    }
  </style>
</head>
<body>

<div class="gallery-container">
  <div class="gallery-header">
    <h1>Tharapana Khaoyai Resort</h1>
    <p>289 Moo 5, Pak Chong, Nakhon Ratchasima, Thailand</p>
  </div>
  <div class="gallery-grid">
    <div class="main-image">
      <img src="assets/img/ปกปก.png" alt="Main Image" onclick="openLightbox(0)">
    </div>
    <div class="small-images" id="thumbnailContainer">
      <!-- รูปภาพจะถูกเพิ่มด้วย JS -->
    </div>
  </div>
</div>

<!-- Lightbox for single image -->
<div class="lightbox" id="lightbox">
  <span class="close-btn" onclick="closeLightbox()">✖</span>
  <span class="arrow prev" onclick="prevImage()">❮</span>
  <img id="lightbox-img" src="" alt="Zoomed Image">
  <span class="arrow next" onclick="nextImage()">❯</span>
</div>

<!-- Popup full gallery -->
<div class="lightbox-gallery" id="popupGallery">
  <div class="lightbox-gallery-content">
    <span class="close-btn" onclick="closeGallery()">✖</span>
    <div class="gallery-grid-popup" id="popupGalleryGrid">
      <!-- รูปจะถูกใส่ด้วย JS -->
    </div>
  </div>
</div>

<script>
  const images = [
  "assets/img/ปกปก.png", // <<-- เพิ่มเข้ามาเป็นลำดับแรก
  "assets/img/ห้องริมธาร.jpg",
  "assets/img/วิวมุมสูง.png",
  "assets/img/ห้องริมธาร.jpg",
  "assets/img/ห้องริมธาร.jpg",
  "assets/img/ห้องริมธาร.jpg",
  "assets/img/ห้องริมธาร.jpg",
  "assets/img/ห้องริมธาร.jpg",
  "assets/img/ห้องริมธาร.jpg",
  "assets/img/ห้องริมธาร.jpg"
];

  const thumbnailContainer = document.getElementById("thumbnailContainer");
  const popupGalleryGrid = document.getElementById("popupGalleryGrid");

  let currentIndex = 0;

  function createThumbnails() {
    const visibleCount = 7;
    images.forEach((src, index) => {
      if (index < visibleCount) {
        const img = document.createElement("img");
        img.src = src;
        img.alt = "Thumbnail";
        img.onclick = () => openLightbox(index);
        thumbnailContainer.appendChild(img);
      } else if (index === visibleCount) {
        const overlay = document.createElement("div");
        overlay.className = "overlay";
        overlay.onclick = openGallery;
        const img = document.createElement("img");
        img.src = src;
        img.alt = "More Images";
        overlay.appendChild(img);
        overlay.style.position = "relative";
        overlay.style.cursor = "pointer";
        overlay.querySelector("::after");
        overlay.style.setProperty('--image-count', images.length - visibleCount);
        overlay.style.setProperty('--image-count-text', `" +${images.length - visibleCount} ภาพ"`);
        overlay.innerHTML += `<div style="
          position: absolute;
          bottom: 0;
          right: 0;
          background: rgba(0,0,0,0.6);
          color: white;
          padding: 5px 10px;
          border-bottom-right-radius: 4px;
          font-weight: bold;
        ">+${images.length - visibleCount} ภาพ</div>`;
        thumbnailContainer.appendChild(overlay);
      }
    });
  }

  function populatePopupGallery() {
    popupGalleryGrid.innerHTML = "";
    images.forEach((src, index) => {
      const img = document.createElement("img");
      img.src = src;
      img.onclick = () => openLightbox(index);
      popupGalleryGrid.appendChild(img);
    });
  }

  function openLightbox(index) {
    currentIndex = index;
    document.getElementById("lightbox-img").src = images[index];
    document.getElementById("lightbox").classList.add("active");
  }

  function closeLightbox() {
    document.getElementById("lightbox").classList.remove("active");
  }

  function prevImage() {
    currentIndex = (currentIndex - 1 + images.length) % images.length;
    document.getElementById("lightbox-img").src = images[currentIndex];
  }

  function nextImage() {
    currentIndex = (currentIndex + 1) % images.length;
    document.getElementById("lightbox-img").src = images[currentIndex];
  }

  function openGallery() {
    document.getElementById("popupGallery").style.display = "block";
  }

  function closeGallery() {
    document.getElementById("popupGallery").style.display = "none";
  }

  // ปิด lightbox ถ้าคลิกด้านนอกภาพ
  document.getElementById('lightbox').addEventListener('click', (e) => {
    if (e.target.id === 'lightbox') {
      closeLightbox();
    }
  });

  // เรียกตอนโหลดหน้า
  createThumbnails();
  populatePopupGallery();
</script>

</body>
</html>




