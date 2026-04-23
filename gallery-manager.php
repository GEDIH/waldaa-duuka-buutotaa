<?php
/**
 * Gallery Manager - Admin interface for managing uploaded photos
 */

// Simple authentication (in production, use proper session management)
session_start();
$admin_password = 'WDB2024Admin!'; // Change this password!

if (isset($_POST['login'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = 'Password sirrii miti!';
    }
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check if admin is logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];

?>
<!DOCTYPE html>
<html lang="om">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Manager - Waldaa Duuka Bu'ootaa</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', serif;
            background: linear-gradient(135deg, #1a3b5c, #2c5282);
            color: #333;
            min-height: 100vh;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        .login-card h1 {
            color: #1a3b5c;
            margin-bottom: 2rem;
            font-size: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1a3b5c;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #d4af37;
        }

        .btn {
            background: linear-gradient(135deg, #d4af37, #b8941f);
            color: #1a3b5c;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn:hover {
            background: linear-gradient(135deg, #1a3b5c, #2c5282);
            color: white;
            transform: translateY(-2px);
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }

        .admin-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1.5rem 0;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .gallery-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .gallery-info {
            padding: 1rem;
        }

        .gallery-info h4 {
            color: #1a3b5c;
            margin-bottom: 0.5rem;
        }

        .gallery-info p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .gallery-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            flex: 1;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .btn-edit {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .upload-section {
            border: 3px dashed #d4af37;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: #fafafa;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #d4af37, #b8941f);
            color: #1a3b5c;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            font-weight: bold;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .gallery-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php if (!$is_logged_in): ?>
    <!-- Login Form -->
    <div class="login-container">
        <div class="login-card">
            <h1>🔐 Admin Login</h1>
            <p style="margin-bottom: 2rem; color: #666;">Waldaa Duuka Bu'ootaa Gallery Manager</p>
            
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo $login_error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn">🚪 Seeni</button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- Admin Dashboard -->
    <form method="POST" style="display: inline;">
        <button type="submit" name="logout" class="logout-btn">🚪 Ba'i</button>
    </form>

    <div class="admin-header">
        <h1>🖼️ Gallery Manager</h1>
        <p>Suuraawwan Waldichaa Bulchuu</p>
    </div>

    <div class="admin-container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3 id="totalImages">0</h3>
                <p>Suuraawwan Hunda</p>
            </div>
            <div class="stat-card">
                <h3 id="totalSize">0 MB</h3>
                <p>Guddina Hunda</p>
            </div>
            <div class="stat-card">
                <h3 id="recentUploads">0</h3>
                <p>Har'a Upload Ta'e</p>
            </div>
        </div>

        <!-- Upload Section -->
        <div class="admin-card">
            <h2>📤 Suuraawwan Haaraa Ol Fe'uu</h2>
            <div class="upload-section">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📸</div>
                <h3>Suuraawwan Filadhu</h3>
                <p>Tuqaa ykn suuraawwan as harkisaa</p>
                <input type="file" id="fileInput" multiple accept="image/*" style="margin-top: 1rem;">
                <div id="uploadProgress" style="margin-top: 1rem; display: none;">
                    <div style="background: #e9ecef; height: 10px; border-radius: 5px; overflow: hidden;">
                        <div id="progressBar" style="background: #d4af37; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                    </div>
                    <p id="progressText" style="margin-top: 0.5rem;">0%</p>
                </div>
            </div>
            <button onclick="uploadFiles()" class="btn">📤 Upload Gochuu</button>
        </div>

        <!-- Gallery Management -->
        <div class="admin-card">
            <h2>🖼️ Suuraawwan Jiran</h2>
            <div id="galleryContainer" class="gallery-grid">
                <!-- Gallery items will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Load gallery on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadGallery();
            loadStats();
        });

        // File upload handling
        function uploadFiles() {
            const fileInput = document.getElementById('fileInput');
            const files = fileInput.files;
            
            if (files.length === 0) {
                alert('Duraan suura filadhu!');
                return;
            }

            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('photos[]', files[i]);
            }

            const progressContainer = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            progressContainer.style.display = 'block';

            fetch('upload.php?action=upload', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                progressContainer.style.display = 'none';
                
                if (data.success) {
                    alert(`✅ ${data.uploaded_count} suura milkaa'inaan upload ta'e!`);
                    fileInput.value = '';
                    loadGallery();
                    loadStats();
                } else {
                    alert('❌ Upload failed: ' + data.message);
                }
                
                if (data.errors && data.errors.length > 0) {
                    console.log('Upload errors:', data.errors);
                }
            })
            .catch(error => {
                progressContainer.style.display = 'none';
                console.error('Upload error:', error);
                alert('❌ Upload error occurred');
            });
        }

        // Load gallery images
        function loadGallery() {
            fetch('upload.php?action=gallery')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('galleryContainer');
                
                if (data.success && data.images.length > 0) {
                    container.innerHTML = data.images.map(image => `
                        <div class="gallery-item">
                            <img src="images/gallery/thumbnails/thumb_${image.filename}" alt="${image.title || image.original_name}">
                            <div class="gallery-info">
                                <h4>${image.title || image.original_name}</h4>
                                <p>${image.description || 'Ibsa hin jiru'}</p>
                                <p style="font-size: 0.8rem; color: #999;">
                                    ${new Date(image.upload_date).toLocaleDateString()} | 
                                    ${Math.round(image.file_size / 1024)} KB
                                </p>
                                <div class="gallery-actions">
                                    <button class="btn btn-small btn-edit" onclick="editImage(${image.id})">✏️ Foyyaa</button>
                                    <button class="btn btn-small btn-danger" onclick="deleteImage(${image.id})">🗑️ Haqii</button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<p style="text-align: center; color: #666; grid-column: 1/-1;">Suura tokkollee hin jiru</p>';
                }
            })
            .catch(error => {
                console.error('Gallery load error:', error);
            });
        }

        // Load statistics
        function loadStats() {
            fetch('upload.php?action=gallery')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('totalImages').textContent = data.count;
                    
                    const totalSize = data.images.reduce((sum, img) => sum + parseInt(img.file_size), 0);
                    document.getElementById('totalSize').textContent = Math.round(totalSize / (1024 * 1024)) + ' MB';
                    
                    const today = new Date().toDateString();
                    const recentUploads = data.images.filter(img => 
                        new Date(img.upload_date).toDateString() === today
                    ).length;
                    document.getElementById('recentUploads').textContent = recentUploads;
                }
            });
        }

        // Delete image
        function deleteImage(imageId) {
            if (!confirm('Suura kana haquu barbaaddaa?')) {
                return;
            }

            const formData = new FormData();
            formData.append('image_id', imageId);

            fetch('upload.php?action=delete', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Suura haqame!');
                    loadGallery();
                    loadStats();
                } else {
                    alert('❌ Delete failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('❌ Delete error occurred');
            });
        }

        // Edit image (placeholder)
        function editImage(imageId) {
            alert('Edit functionality coming soon!');
        }

        // Drag and drop functionality
        const uploadSection = document.querySelector('.upload-section');
        
        uploadSection.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.background = '#e8f4f8';
        });
        
        uploadSection.addEventListener('dragleave', function(e) {
            this.style.background = '#fafafa';
        });
        
        uploadSection.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.background = '#fafafa';
            
            const files = e.dataTransfer.files;
            document.getElementById('fileInput').files = files;
        });
    </script>

<?php endif; ?>

</body>
</html>