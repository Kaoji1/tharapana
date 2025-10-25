<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ติดต่อเรา</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    /* สีหลัก */
    .text-teal {
      color: #008374 !important;
    }
    .border-teal {
      border-color: #008374 !important;
    }

    .fade-in {
      animation: fadeInUp 0.8s ease-in-out;
    }


    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .avatar-image {
      border-radius: 50%;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    /* ไอคอนโซเชียล */
    .social-links a {
      color: #008374;
      font-size: 1.5rem;
      margin-right: 1rem;
      transition: color 0.3s ease;
    }
    .social-links a:hover {
      color: #005742;
      text-decoration: none;
    }
  </style>
  <?php include_once "header.php"; ?>
</head>

<body style="background-color: #f8f9fa;">

<div class="container py-5" style="margin-top: 100px;">
  <div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">

      <div class="contact-info-wrap text-center p-4 rounded shadow-lg bg-white border border-2 border-teal">
        <h2 class="mb-4 text-teal">ติดต่อสอบถาม</h2>

        <div class="contact-image-wrap d-flex justify-content-center align-items-center fade-in mb-4">
          <img src="assets/img/cat-test.png" class="img-fluid avatar-image me-3" alt="admin" style="width: 80px; height: 80px; object-fit: cover;">
        </div>

        <div class="contact-info text-start fade-in">
          <h5 class="mb-3 text-teal">ข้อมูลติดต่อ</h5>

          <p class="d-flex align-items-center mb-3 text-dark">
            <i class="bi bi-geo-alt me-2 text-teal fs-5"></i>
            289 Moo 5 Tumbon Mu Si, Amphoe Pak Chong, Nakhon Ratchasima, หมูสี
          </p>

          <p class="d-flex align-items-center mb-3">
            <i class="bi bi-telephone me-2 text-teal fs-5"></i>
            <a href="tel:0930199299" class="text-decoration-none text-dark">093-019-9299</a>
          </p>

          <p class="d-flex align-items-center mb-3">
            <i class="bi bi-envelope me-2 text-teal fs-5"></i>
            <a href="mailto:tharapana60@gmail.com" class="text-decoration-none text-dark">tharapana60@gmail.com</a>
          </p>

          <div class="social-links mt-4">
            <a href="https://www.facebook.com/TharapanaKhaoyaiResort/" target="_blank" rel="noopener" aria-label="Facebook">
              <i class="bi bi-facebook"></i>
            </a>
            <a href="https://lin.ee/QRBXItF" target="_blank" rel="noopener" aria-label="LINE">
              <i class="bi bi-chat-dots"></i>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>


  <!-- Bootstrap JS (Optional) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
