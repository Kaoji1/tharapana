<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Personal Details</title>
  <style>
    :root {
      --primary-color: #008374;
      --bg-color: #f7f9fc;
      --text-color: #333;
      --border-radius: 12px;
      --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: var(--bg-color);
      margin: 0;
      padding: 0;
      color: var(--text-color);
    }

    nav {
      background: white;
      padding: 16px 32px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      position: sticky;
      top: 0;
      z-index: 10;
    }

    nav h1 {
      margin: 0;
      color: var(--primary-color);
      font-size: 20px;
    }

    .container {
      max-width: 700px;
      margin: 40px auto;
      background: white;
      padding: 30px;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    h2 {
      font-size: 24px;
      margin-bottom: 10px;
      border-bottom: 2px solid #eee;
      padding-bottom: 10px;
    }

    p.description {
      color: #666;
      font-size: 14px;
      margin-bottom: 25px;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px solid #f0f0f0;
      transition: background 0.3s;
    }

    .detail-row:hover {
      background: #f9fbff;
    }

    .detail-row:last-child {
      border-bottom: none;
    }

    .label {
      font-weight: 600;
      font-size: 15px;
    }

    .value {
      flex: 1;
      text-align: right;
      color: #555;
      margin-left: 15px;
      transition: color 0.3s;
    }

    input[type="text"] {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.3s ease;
    }

    input[type="text"]:focus {
      border-color: var(--primary-color);
      outline: none;
      box-shadow: 0 0 5px rgba(0,102,255,0.2);
    }

    .edit-link,
    .save-link {
      color: var(--primary-color);
      font-weight: 500;
      text-decoration: none;
      cursor: pointer;
      margin-left: 12px;
      transition: color 0.2s ease;
    }

    .edit-link:hover,
    .save-link:hover {
      text-decoration: underline;
      color: #008374;
    }

    .button {
      background: var(--primary-color);
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 16px;
      transition: background 0.2s, transform 0.2s;
      text-decoration: none;
      display: inline-block;
    }

    .button:hover {
      background: #006f66;
      transform: translateY(-2px);
    }

    .cancel {
      color: var(--primary-color);
      float: right;
      cursor: pointer;
      margin-top: -40px;
    }

    @media (max-width: 600px) {
      .detail-row {
        flex-direction: column;
        align-items: flex-start;
      }

      .value {
        text-align: left;
        margin-top: 5px;
      }

      .cancel {
        float: none;
        display: block;
        margin: 10px 0;
      }
    }
  </style>
</head>
<body>

  <nav>
    <h1>My Profile</h1>
  </nav>

  <div class="container">
    <h2>Personal details</h2>
    <p class="description">หหหหหหหหหหหหหหหห</p>

    <?php
    $data = [
      'Name' => 'สุทธิชัย สังขมณี',
      'Display name' => 'Choose a display name',
      'Email address' => '65010912569@msu.ac.th',
      'Phone number' => 'Add your phone number',
      'Date of birth' => 'Enter your date of birth',
      'Nationality' => 'Select the country/region you\'re from',
      'Gender' => 'Select your gender',
      'Address' => 'Add your address',
    ];

    $index = 0;
    foreach ($data as $label => $value) {
      echo '<div class="detail-row" data-label="'.$label.'">';
      echo "<div class='label'>{$label}</div>";
      echo "<div class='value' id='value-$index'>{$value}</div>";
      echo "<span class='edit-link' onclick='editField($index)'>Edit</span>";
      echo "<span class='save-link' onclick='saveField($index)' style='display:none; color:green;'>Save</span>";
      echo '</div>';
      $index++;
    }
    ?>

    <div style="text-align: center; margin-top: 30px;">
      <a href="index.php" class="button">ย้อนกลับ</a>
    </div>
  </div>

  <script>
    function editField(index) {
      const valueDiv = document.getElementById('value-' + index);
      const originalValue = valueDiv.innerText.trim();
      const input = document.createElement('input');
      input.type = 'text';
      input.value = originalValue;
      input.id = 'input-' + index;

      valueDiv.innerHTML = '';
      valueDiv.appendChild(input);

      const parent = valueDiv.parentElement;
      parent.querySelector('.edit-link').style.display = 'none';
      parent.querySelector('.save-link').style.display = 'inline';
    }

    function saveField(index) {
      const input = document.getElementById('input-' + index);
      const newValue = input.value;

      const valueDiv = document.getElementById('value-' + index);
      valueDiv.innerHTML = newValue;

      const parent = valueDiv.parentElement;
      parent.querySelector('.edit-link').style.display = 'inline';
      parent.querySelector('.save-link').style.display = 'none';
    }
  </script>

</body>
</html>
