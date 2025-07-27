<?php
// index.php

// تنظیمات اولیه و مسیریابی
ini_set('display_errors', 0);
error_reporting(0);

$uploadDir = __DIR__ . '/uploads/';
$coverDir = $uploadDir . 'covers/';

// ایجاد پوشه‌ها در صورت عدم وجود
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($coverDir)) mkdir($coverDir, 0755, true);

// بارگذاری getID3
require_once('getid3/getid3.php');

// پاسخ JSON برای درخواست AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['mp3file'])) {
    header('Content-Type: application/json');

    $file = $_FILES['mp3file'];

    // اعتبارسنجی فایل
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Upload error.']);
        exit;
    }

    if ($file['size'] > 20 * 1024 * 1024) {
        echo json_encode(['error' => 'File size exceeds 20MB limit.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime !== 'audio/mpeg') {
        echo json_encode(['error' => 'Only MP3 files allowed.']);
        exit;
    }

    // ذخیره موقت فایل
    $filename = uniqid('mp3_') . '.mp3';
    $filepath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['error' => 'Failed to save uploaded file.']);
        exit;
    }

    // استخراج کاور
    $getID3 = new getID3;
    $info = $getID3->analyze($filepath);
    getid3_lib::CopyTagsToComments($info);
    
    $coverData = null;
    $coverMime = null;

    // بررسی چند موقعیت کاور در تگ‌های مختلف ID3
    if (!empty($info['comments']['picture'][0]['data'])) {
        $coverData = $info['comments']['picture'][0]['data'];
        $coverMime = $info['comments']['picture'][0]['image_mime'] ?? 'image/jpeg';
    } elseif (!empty($info['id3v2']['APIC'][0]['data'])) {
        $coverData = $info['id3v2']['APIC'][0]['data'];
        $coverMime = $info['id3v2']['APIC'][0]['image_mime'] ?? 'image/jpeg';
    }

    if ($coverData) {
        // تعیین پسوند بر اساس mime
        $allowedMimes = ['image/jpeg' => '.jpg', 'image/png' => '.png'];
        $ext = $allowedMimes[$coverMime] ?? '.jpg';

        $coverFilename = uniqid('cover_') . $ext;
        file_put_contents($coverDir . $coverFilename, $coverData);

        // حذف فایل MP3 پس از استخراج کاور
        unlink($filepath);

        echo json_encode([
            'success' => true,
            'coverUrl' => 'uploads/covers/' . $coverFilename,
            'message' => 'Cover extracted successfully.',
            'filename' => $filename
        ]);
        exit;
    } else {
        // حذف فایل MP3 اگر کاور نیست
        unlink($filepath);
        echo json_encode(['error' => 'No cover art found in this MP3.']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="icon" href="icon.svg" type="image/svg+xml">
<meta name="google-site-verification" content="Ye1ynN5RFYSbrFDLYTM6LKs44arvkqhxkwdcEpNzTVM" />
<title>MP3 Cover Downloader/Extractor Online Free</title>
<meta name="description" content="Free online tool to quickly extract cover art from MP3 files. Upload your MP3 and instantly get its embedded cover image.">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap');
  * {
    box-sizing: border-box;
  }
  body {
    margin:0; font-family: 'Poppins', sans-serif; background: #f7f9fc; color:#333;
  }
  .hero {
    height: 100vh;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: #fff;
    text-align: center;
    padding: 20px;
  }
  h1 {
    font-size: 3rem;
    margin-bottom: 0.2em;
    text-shadow: 0 2px 6px rgba(0,0,0,0.3);
  }
  p.subtitle {
    font-size: 1.25rem;
    margin-bottom: 1.5em;
    font-weight: 500;
  }
  .upload-btn-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
  }
  button.upload-btn {
    background-color: #4caf50;
    color: white;
    font-size: 1.2rem;
    font-weight: 700;
    padding: 15px 40px;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(76,175,80,0.6);
    transition: background-color 0.3s ease;
  }
  button.upload-btn:hover {
    background-color: #43a047;
  }
  input[type=file] {
    font-size: 100px;
    position: absolute;
    left: 0; top: 0;
    opacity: 0;
    cursor: pointer;
  }
  #progress-container {
    margin-top: 25px;
    width: 300px;
    height: 20px;
    background: #ddd;
    border-radius: 10px;
    overflow: hidden;
    display: none;
    margin-left: auto;
    margin-right: auto;
  }
  #progress-bar {
    height: 100%;
    width: 0;
    background: #4caf50;
    transition: width 0.3s ease;
  }
  #status-message {
    margin-top: 20px;
    font-size: 1.1rem;
    font-weight: 600;
  }
  #cover-container {
    margin-top: 40px;
    text-align: center;
  }
  #cover-container img {
    max-width: 320px;
    border-radius: 15px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
  }
  #download-cover-btn {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 30px;
    background: #4caf50;
    color: white;
    font-weight: 700;
    border-radius: 40px;
    text-decoration: none;
    box-shadow: 0 5px 15px rgba(76,175,80,0.7);
    transition: background-color 0.3s ease;
  }
  #download-cover-btn:hover {
    background-color: #43a047;
  }
  .footer {
    padding: 40px 20px;
    background: #f0f0f0;
    color: #444;
    font-size: 0.9rem;
    text-align: center;
    line-height: 1.6;
  }
  .footer strong {
    color: #667eea;
  }
  @media(max-width: 400px) {
    h1 { font-size: 2rem; }
    button.upload-btn { padding: 12px 30px; font-size: 1rem; }
    #progress-container { width: 90%; }
  }

   .cover-extractor-info {
    max-width: 700px;
    margin: 40px auto;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    color: #2c3e50;
    background: #f9fafb;
    border-radius: 12px;
    padding: 30px 40px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
  }
  .cover-extractor-info h2 {
    font-size: 2rem;
    margin-bottom: 18px;
    color: #34495e;
    font-weight: 700;
  }
  .cover-extractor-info h3 {
    margin-top: 28px;
    margin-bottom: 14px;
    font-size: 1.3rem;
    color: #2c3e50;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .cover-extractor-info p,
  .cover-extractor-info li {
    font-size: 1rem;
  }
  .cover-extractor-info p strong,
  .cover-extractor-info li strong {
    color: #2980b9;
  }
  .cover-extractor-info ol,
  .cover-extractor-info ul {
    padding-left: 22px;
    margin-top: 10px;
    margin-bottom: 20px;
  }
  .cover-extractor-info ol li {
    margin-bottom: 10px;
  }
  .cover-extractor-info ul li {
    margin-bottom: 8px;
  }
  .cover-extractor-info a {
    color: #2980b9;
    text-decoration: none;
    font-weight: 600;
  }
  .cover-extractor-info a:hover {
    text-decoration: underline;
  }
  .cover-extractor-info {
    text-align: left;
}
</style>
</head>
<body>

<div class="hero">
  <h1>MP3 Cover Downloader (Extractor)</h1>
  <p class="subtitle">Upload your MP3 file to extract its embedded cover art instantly.</p>
  <div class="upload-btn-wrapper">
    <button class="upload-btn">Upload MP3 File</button>
    <input type="file" id="mp3file" accept=".mp3" />
  </div>

  <div id="progress-container">
    <div id="progress-bar"></div>
  </div>
  <div id="status-message"></div>

  <div id="cover-container" style="display:none;">
    <img id="cover-img" src="" alt="Extracted Cover" />
    <br />
    <a id="download-cover-btn" href="#" download="mp3_cover.jpg">Download Cover</a>
  </div>
</div>

<div class="footer">
<div class="cover-extractor-info" style="font-family: Arial, sans-serif; line-height: 1.7;">
  <h2>🎵 MP3 Cover Image Extractor – Free Online Tool</h2>
  <p><strong>This free online tool helps you extract embedded album artwork (cover images) from MP3 files quickly and effortlessly.</strong> Whether you're a music enthusiast, a content creator, or an archivist, this tool is designed to make retrieving MP3 cover art as seamless as possible.</p>

  <h3>✅ How It Works:</h3>
  <ol>
    <li>Upload your <code>.mp3</code> file using the upload box.</li>
    <li>The server will process the file and extract the embedded cover image (if available).</li>
    <li>You’ll see a preview of the artwork and a download button.</li>
    <li>All uploaded files and extracted covers are <strong>automatically deleted</strong> from the server after 2 minutes to ensure your privacy.</li>
  </ol>

  <h3>🔐 Privacy & Security:</h3>
  <ul>
    <li>Your files are <strong>never shared</strong> or stored permanently.</li>
    <li>All uploads are processed <strong>entirely on our secure server</strong>.</li>
    <li>Files are automatically deleted within <strong>2 minutes</strong> after upload.</li>
  </ul>

  <h3>📦 Limitations:</h3>
  <ul>
    <li>Maximum file size: <strong>30 MB</strong></li>
    <li>Only MP3 files are supported</li>
    <li>If the uploaded file does <strong>not contain embedded artwork</strong>, nothing will be extracted</li>
  </ul>

  <h3>🛠️ Use Cases:</h3>
  <ul>
    <li>Download missing album covers from personal MP3 collections</li>
    <li>Restore or archive music artwork for tagging tools (like MP3Tag, MusicBrainz, etc.)</li>
    <li>Quickly preview embedded images in audio files</li>
  </ul>

  <h3>👤 Created by:</h3>
  <p>This tool is built and maintained by <a href="https://ememay.ir" target="_blank"><strong>Ememay.ir</strong></a> – a hub for music, technology, and creative tools.</p>
</div>

</div>

<script>
  const mp3Input = document.getElementById('mp3file');
  const progressBar = document.getElementById('progress-bar');
  const progressContainer = document.getElementById('progress-container');
  const statusMessage = document.getElementById('status-message');
  const coverContainer = document.getElementById('cover-container');
  const coverImg = document.getElementById('cover-img');
  const downloadBtn = document.getElementById('download-cover-btn');

  let coverTimeout;

  mp3Input.addEventListener('change', () => {
    if (!mp3Input.files.length) return;

    // Reset UI
    progressBar.style.width = '0%';
    progressContainer.style.display = 'block';
    statusMessage.textContent = 'Uploading...';
    coverContainer.style.display = 'none';

    const file = mp3Input.files[0];
    const formData = new FormData();
    formData.append('mp3file', file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);

    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        const percent = (e.loaded / e.total) * 100;
        progressBar.style.width = percent + '%';
        statusMessage.textContent = `Uploading... ${Math.floor(percent)}%`;
      }
    };

    xhr.onload = () => {
      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.success) {
            statusMessage.textContent = response.message;
            coverImg.src = response.coverUrl + '?v=' + Date.now();
            downloadBtn.href = response.coverUrl;
            coverContainer.style.display = 'block';

            // Reset progress after a short delay
            clearTimeout(coverTimeout);
            coverTimeout = setTimeout(() => {
              progressContainer.style.display = 'none';
              progressBar.style.width = '0%';
            }, 2000);
          } else if (response.error) {
            statusMessage.textContent = 'Error: ' + response.error;
            progressContainer.style.display = 'none';
          } else {
            statusMessage.textContent = 'Unexpected response from server.';
            progressContainer.style.display = 'none';
          }
        } catch {
          statusMessage.textContent = 'Invalid response from server.';
          progressContainer.style.display = 'none';
        }
      } else {
        statusMessage.textContent = 'Upload failed. Server error.';
        progressContainer.style.display = 'none';
      }
      mp3Input.value = '';
    };

    xhr.onerror = () => {
      statusMessage.textContent = 'Upload failed due to a network error.';
      progressContainer.style.display = 'none';
      mp3Input.value = '';
    };

    xhr.send(formData);
  });
</script>

</body>
</html>
