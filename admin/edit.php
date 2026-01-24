<?php
/**
 * Admin post editor - create and edit posts
 */
require_once '../config.php';
requireLogin(); // Ensure user is logged in

// Generate CSRF token
$csrfToken = generateCSRFToken();

$db = getDB();

$post = null;
$postId = null;
$isEdit = false;

// Check if editing existing post
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $postId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($post) {
        $isEdit = true;
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ogiltig begäran. Försök igen.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        // Determine status based on which button was clicked
        $status = isset($_POST['publish']) ? 'published' : 'draft';
        
        // Validation
        if (empty($title)) {
            $error = 'Titel krävs.';
        } elseif (empty($content)) {
            $error = 'Innehåll krävs.';
        } else {
        // Handle featured image upload
        $featuredImage = null;
        $deleteImage = isset($_POST['delete_image']) && $_POST['delete_image'] == '1';
        
        // Check for cropped image (base64) first, then regular file upload
        if (!empty($_POST['cropped_image_data'])) {
            // Upload cropped image from base64
            $featuredImage = uploadFeaturedImageFromBase64($_POST['cropped_image_data'], $postId);
            if ($featuredImage === false) {
                $error = 'Bilduppladdning misslyckades. Kontrollera att bilden är giltig.';
            } else {
                // Delete old image if editing
                if ($isEdit && $post && $post['featured_image']) {
                    deleteFeaturedImage($post['featured_image']);
                }
            }
        } elseif (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            // Upload new image (fallback for direct file upload)
            $featuredImage = uploadFeaturedImage($_FILES['featured_image'], $postId);
            if ($featuredImage === false) {
                $error = 'Bilduppladdning misslyckades. Kontrollera att filen är en giltig bildfil (JPG, PNG, GIF eller WebP).';
            } else {
                // Delete old image if editing
                if ($isEdit && $post && $post['featured_image']) {
                    deleteFeaturedImage($post['featured_image']);
                }
            }
        } elseif ($isEdit && $post) {
            // Keep existing image unless deleted
            if ($deleteImage) {
                if ($post['featured_image']) {
                    deleteFeaturedImage($post['featured_image']);
                }
                $featuredImage = null;
            } else {
                $featuredImage = $post['featured_image'];
            }
        }
        
        if (!isset($error) || empty($error)) {
            // Generate slug from title
            $slug = generateSlug($title);
            
            // Check if slug already exists (for new posts or if slug changed)
            $checkStmt = $db->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
            $checkStmt->execute([$slug, $postId ?: 0]);
            if ($checkStmt->fetch()) {
                // If slug exists, append a number
                $originalSlug = $slug;
                $counter = 1;
                do {
                    $slug = $originalSlug . '-' . $counter;
                    $checkStmt->execute([$slug, $postId ?: 0]);
                    $counter++;
                } while ($checkStmt->fetch());
            }
            
            try {
                if ($isEdit) {
                    // Update existing post
                    $stmt = $db->prepare("UPDATE posts SET title = ?, content = ?, slug = ?, featured_image = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$title, $content, $slug, $featuredImage, $status, $postId]);
                } else {
                    // Create new post
                    $stmt = $db->prepare("INSERT INTO posts (title, content, slug, featured_image, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $content, $slug, $featuredImage, $status]);
                }
                
                $message = $status === 'published' ? 'published=1' : 'saved=1';
                header('Location: dashboard.php?' . $message);
                exit;
            } catch (PDOException $e) {
                $error = 'Fel vid sparande av inlägg: ' . $e->getMessage();
            }
        }
    }
}
}

// Pre-fill form if editing
$titleValue = $post['title'] ?? ($_POST['title'] ?? '');
$contentValue = $post['content'] ?? ($_POST['content'] ?? '');
$currentImage = $post['featured_image'] ?? null;
$currentStatus = $post['status'] ?? 'draft';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Redigera inlägg' : 'Nytt inlägg'; ?> - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <!-- Cropper.js - Lightweight image cropper -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
    <style>
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }
        .btn {
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            font-size: 0.9rem;
        }
        .btn-secondary {
            background: #666;
            color: white;
        }
        .btn-secondary:hover {
            background: #888;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-group input[type="text"] {
            font-size: 1.2rem;
        }
        .form-group input[type="file"] {
            padding: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
        }
        .current-image {
            margin-bottom: 1rem;
        }
        .current-image img {
            display: block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-group textarea {
            min-height: 400px;
            resize: vertical;
            line-height: 1.6;
        }
        /* Quill editor styling */
        #editor {
            min-height: 400px;
            background: white;
        }
        .ql-container {
            font-size: 16px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .ql-editor {
            min-height: 400px;
            line-height: 1.6;
        }
        .btn-primary {
            padding: 0.75rem 2rem;
            font-size: 1rem;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #555;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .btn-success {
            padding: 0.75rem 2rem;
            font-size: 1rem;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-success:hover {
            background: #1b5e20;
        }
        .status-indicator {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 1rem;
        }
        .status-draft {
            background: #ff9800;
            color: white;
        }
        .status-published {
            background: #4caf50;
            color: white;
        }
        .error {
            color: #d32f2f;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #ffebee;
            border-radius: 4px;
        }
        .help-text {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        /* Image Crop Modal Styles */
        .crop-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .crop-modal-content {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
            position: relative;
        }
        .crop-container {
            position: relative;
            width: 100%;
            height: 500px;
            background: #333;
            margin-bottom: 1rem;
        }
        .crop-controls {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        .crop-preview {
            margin-top: 1rem;
            text-align: center;
        }
        .crop-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 4px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        .image-upload-area:hover {
            border-color: #333;
            background: #f9f9f9;
        }
        .image-upload-area.has-image {
            border-style: solid;
            border-color: #4caf50;
            padding: 1rem;
        }
        .image-preview-container {
            position: relative;
            display: inline-block;
        }
        .image-preview-container img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
            display: block;
        }
        .image-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-small-danger {
            background: #d32f2f;
            color: white;
        }
        .btn-small-danger:hover {
            background: #c62828;
        }
        .btn-small-secondary {
            background: #666;
            color: white;
        }
        .btn-small-secondary:hover {
            background: #888;
        }
        .hidden-file-input {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>
                <?php echo $isEdit ? 'Redigera inlägg' : 'Nytt inlägg'; ?>
                <?php if ($isEdit && isset($currentStatus)): ?>
                    <span class="status-indicator status-<?php echo htmlspecialchars($currentStatus); ?>">
                        <?php echo $currentStatus === 'published' ? 'Publicerad' : 'Utkast'; ?>
                    </span>
                <?php endif; ?>
            </h1>
            <a href="dashboard.php" class="btn btn-secondary">← Tillbaka till adminpanelen</a>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <div class="form-group">
                <label for="title">Titel</label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       value="<?php echo htmlspecialchars($titleValue); ?>" 
                       required 
                       autofocus>
            </div>
            
            <div class="form-group">
                <label for="featured_image">Huvudbild</label>
                <?php if ($currentImage): ?>
                    <div class="current-image-preview" style="margin-bottom: 1rem;">
                        <img id="current-image-preview" src="../<?php echo htmlspecialchars(getFeaturedImageUrl($currentImage)); ?>" alt="Nuvarande huvudbild" style="width: 100%; height: auto; border-radius: 4px; display: block;">
                        <div style="margin-top: 0.5rem;">
                            <button type="button" id="delete-current-image" class="btn-small btn-small-danger">Radera nuvarande bild</button>
                        </div>
                    </div>
                <?php endif; ?>
                <div id="image-upload-container">
                    <div id="image-upload-area" class="image-upload-area" style="display: none;">
                        <p style="margin: 0; font-size: 1.1rem;">Klicka för att välja bild</p>
                        <p style="margin: 0.5rem 0 0 0; color: #666; font-size: 0.9rem;">JPG, PNG, GIF eller WebP</p>
                    </div>
                    <div id="crop-container" style="display: none; margin-bottom: 1rem;">
                        <img id="crop-image" style="max-width: 100%; max-height: 400px;">
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" id="rotate-left" class="btn-small btn-small-secondary" title="Rotera vänster 90°">↺ Rotera vänster</button>
                                <button type="button" id="rotate-right" class="btn-small btn-small-secondary" title="Rotera höger 90°">↻ Rotera höger</button>
                            </div>
                            <div style="flex: 1;"></div>
                            <div>
                                <button type="button" id="crop-confirm" class="btn-small btn-primary">Bekräfta beskärning</button>
                                <button type="button" id="crop-cancel" class="btn-small btn-small-secondary" style="margin-left: 0.5rem;">Avbryt</button>
                            </div>
                        </div>
                    </div>
                    <div id="cropped-preview" style="display: none; margin-bottom: 1rem;">
                        <img id="cropped-preview-img" style="width: 100%; height: auto; border-radius: 4px; display: block;">
                        <div style="margin-top: 0.5rem;">
                            <button type="button" id="select-new-image" class="btn-small btn-small-secondary">Välj annan bild</button>
                            <button type="button" id="remove-image" class="btn-small btn-small-danger" style="margin-left: 0.5rem;">Radera bild</button>
                        </div>
                    </div>
                </div>
                <input type="file" 
                       id="featured_image" 
                       name="featured_image" 
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                       style="display: none;">
                <input type="hidden" id="cropped_image_data" name="cropped_image_data">
                <input type="hidden" id="delete_image" name="delete_image" value="0">
                <p class="help-text">Ladda upp en bild (JPG, PNG, GIF eller WebP). Du kan beskära bilden innan uppladdning.</p>
            </div>
            
            <div class="form-group">
                <label for="content">Innehåll</label>
                <!-- Hidden textarea to store HTML content for form submission -->
                <textarea id="content" 
                          name="content" 
                          style="display: none;"><?php echo htmlspecialchars($contentValue); ?></textarea>
                <!-- Quill editor container -->
                <div id="editor"></div>
                <p class="help-text">Använd verktygsraden ovan för att formatera din text. Fet, kursiv, listor, länkar med mera.</p>
            </div>
            
            <script>
                // Initialize Quill editor
                var quill = new Quill('#editor', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'color': [] }, { 'background': [] }],
                            ['link'],
                            ['clean']
                        ]
                    }
                });
                
                // Set initial content if editing
                <?php if (!empty($contentValue)): ?>
                quill.root.innerHTML = <?php echo json_encode($contentValue); ?>;
                <?php endif; ?>
                
                // Update hidden textarea with HTML content before form submission
                var form = document.querySelector('form');
                form.addEventListener('submit', function(e) {
                    var content = document.querySelector('#content');
                    var htmlContent = quill.root.innerHTML;
                    
                    // Check if content is empty (just whitespace or empty HTML tags)
                    var textContent = quill.getText().trim();
                    if (!textContent || textContent.length === 0) {
                        e.preventDefault();
                        alert('Vänligen ange innehåll för ditt inlägg.');
                        return false;
                    }
                    
                    // Update hidden textarea with HTML content
                    content.value = htmlContent;
                });
            </script>
            
            <script>
                // Simple image upload and crop using Cropper.js
                document.addEventListener('DOMContentLoaded', function() {
                    let cropper = null;
                    let currentImageFile = null;
                    
                    const uploadArea = document.getElementById('image-upload-area');
                    const fileInput = document.getElementById('featured_image');
                    const cropContainer = document.getElementById('crop-container');
                    const cropImage = document.getElementById('crop-image');
                    const croppedPreview = document.getElementById('cropped-preview');
                    const croppedPreviewImg = document.getElementById('cropped-preview-img');
                    const croppedImageDataInput = document.getElementById('cropped_image_data');
                    const deleteImageInput = document.getElementById('delete_image');
                    const currentImagePreview = document.getElementById('current-image-preview');
                    const currentImageContainer = document.querySelector('.current-image-preview');
                    
                    // Show upload area if no current image
                    <?php if (!$currentImage): ?>
                    if (uploadArea) uploadArea.style.display = 'block';
                    <?php endif; ?>
                    
                    // File input change handler
                    if (fileInput) {
                        fileInput.addEventListener('change', function(e) {
                            const file = e.target.files[0];
                            if (file) {
                                currentImageFile = file;
                                const reader = new FileReader();
                                reader.onload = function(event) {
                                    if (cropImage) {
                                        cropImage.src = event.target.result;
                                        if (uploadArea) uploadArea.style.display = 'none';
                                        if (cropContainer) cropContainer.style.display = 'block';
                                        if (croppedPreview) croppedPreview.style.display = 'none';
                                        if (currentImageContainer) currentImageContainer.style.display = 'none';
                                        
                                        // Initialize Cropper.js
                                        if (typeof Cropper !== 'undefined') {
                                            if (cropper) {
                                                cropper.destroy();
                                            }
                                            cropper = new Cropper(cropImage, {
                                                aspectRatio: NaN, // No aspect ratio constraint - free crop
                                                viewMode: 1,
                                                dragMode: 'move',
                                                autoCropArea: 0.8,
                                                restore: false,
                                                guides: true,
                                                center: true,
                                                highlight: false,
                                                cropBoxMovable: true,
                                                cropBoxResizable: true,
                                                toggleable: false,
                                                zoomable: true,
                                                scalable: true,
                                                rotatable: true,
                                                responsive: true
                                            });
                                        } else {
                                            alert('Bildverktyget laddas fortfarande. Vänta ett ögonblick och försök igen.');
                                        }
                                    }
                                };
                                reader.readAsDataURL(file);
                            }
                        });
                    }
                    
                    // Upload area click handler
                    if (uploadArea) {
                        uploadArea.addEventListener('click', function() {
                            if (fileInput) fileInput.click();
                        });
                    }
                    
                    // Select new image button
                    const selectNewImageBtn = document.getElementById('select-new-image');
                    if (selectNewImageBtn) {
                        selectNewImageBtn.addEventListener('click', function() {
                            if (fileInput) fileInput.click();
                        });
                    }
                    
                    // Rotate left button
                    const rotateLeftBtn = document.getElementById('rotate-left');
                    if (rotateLeftBtn) {
                        rotateLeftBtn.addEventListener('click', function() {
                            if (cropper) {
                                cropper.rotate(-90);
                            }
                        });
                    }
                    
                    // Rotate right button
                    const rotateRightBtn = document.getElementById('rotate-right');
                    if (rotateRightBtn) {
                        rotateRightBtn.addEventListener('click', function() {
                            if (cropper) {
                                cropper.rotate(90);
                            }
                        });
                    }
                    
                    // Crop confirm button
                    const cropConfirmBtn = document.getElementById('crop-confirm');
                    if (cropConfirmBtn) {
                        cropConfirmBtn.addEventListener('click', function() {
                            if (cropper && croppedImageDataInput) {
                                // Get cropped canvas without fixed dimensions - use whatever was cropped
                                const canvas = cropper.getCroppedCanvas();
                                
                                canvas.toBlob(function(blob) {
                                    const reader = new FileReader();
                                    reader.onload = function() {
                                        croppedImageDataInput.value = reader.result;
                                        croppedPreviewImg.src = reader.result;
                                        cropContainer.style.display = 'none';
                                        croppedPreview.style.display = 'block';
                                        deleteImageInput.value = '0';
                                    };
                                    reader.readAsDataURL(blob);
                                }, 'image/jpeg', 0.95);
                            }
                        });
                    }
                    
                    // Crop cancel button
                    const cropCancelBtn = document.getElementById('crop-cancel');
                    if (cropCancelBtn) {
                        cropCancelBtn.addEventListener('click', function() {
                            if (cropper) {
                                cropper.destroy();
                                cropper = null;
                            }
                            cropContainer.style.display = 'none';
                            <?php if (!$currentImage): ?>
                            if (uploadArea) uploadArea.style.display = 'block';
                            <?php else: ?>
                            if (currentImageContainer) currentImageContainer.style.display = 'block';
                            <?php endif; ?>
                            if (fileInput) fileInput.value = '';
                            currentImageFile = null;
                        });
                    }
                    
                    // Remove image button
                    const removeImageBtn = document.getElementById('remove-image');
                    if (removeImageBtn) {
                        removeImageBtn.addEventListener('click', function() {
                            if (confirm('Är du säker på att du vill radera bilden?')) {
                                croppedPreview.style.display = 'none';
                                croppedImageDataInput.value = '';
                                deleteImageInput.value = '1';
                                <?php if (!$currentImage): ?>
                                if (uploadArea) uploadArea.style.display = 'block';
                                <?php endif; ?>
                            }
                        });
                    }
                    
                    // Delete current image button
                    const deleteCurrentImageBtn = document.getElementById('delete-current-image');
                    if (deleteCurrentImageBtn) {
                        deleteCurrentImageBtn.addEventListener('click', function() {
                            if (confirm('Är du säker på att du vill radera den nuvarande bilden?')) {
                                deleteImageInput.value = '1';
                                if (currentImageContainer) currentImageContainer.style.display = 'none';
                                <?php if (!$currentImage): ?>
                                if (uploadArea) uploadArea.style.display = 'block';
                                <?php endif; ?>
                            }
                        });
                    }
                });
            </script>
            
            <script>
                // Old React code removed - keeping this comment for reference
                /*
                function initImageUploadCrop() {
                        const [imageSrc, setImageSrc] = useState(null);
                        const [crop, setCrop] = useState({ x: 0, y: 0 });
                        const [zoom, setZoom] = useState(1);
                        const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);
                        const [showCropModal, setShowCropModal] = useState(false);
                        const [croppedImageUrl, setCroppedImageUrl] = useState(currentImageUrl || null);
                        const fileInputRef = useRef(null);
                        const hiddenFileInput = document.getElementById('featured_image');
                        const croppedImageDataInput = document.getElementById('cropped_image_data');
                        const deleteImageInput = document.getElementById('delete_image');
                        
                        const aspectRatio = <?php echo number_format((float)FEATURED_IMAGE_WIDTH / (float)FEATURED_IMAGE_HEIGHT, 10, '.', ''); ?>;
                        
                        const onCropComplete = useCallback(function(croppedArea, croppedAreaPixels) {
                            setCroppedAreaPixels(croppedAreaPixels);
                        }, []);
                        
                        const createImage = function(url) {
                            return new Promise(function(resolve, reject) {
                                const image = new Image();
                                image.addEventListener('load', function() { resolve(image); });
                                image.addEventListener('error', function(error) { reject(error); });
                                image.setAttribute('crossOrigin', 'anonymous');
                                image.src = url;
                            });
                        };
                        
                        const getCroppedImg = function(imageSrc, pixelCrop) {
                            return createImage(imageSrc).then(function(image) {
                                const canvas = document.createElement('canvas');
                                const ctx = canvas.getContext('2d');
                                
                                canvas.width = pixelCrop.width;
                                canvas.height = pixelCrop.height;
                                
                                ctx.drawImage(
                                    image,
                                    pixelCrop.x,
                                    pixelCrop.y,
                                    pixelCrop.width,
                                    pixelCrop.height,
                                    0,
                                    0,
                                    pixelCrop.width,
                                    pixelCrop.height
                                );
                                
                                return new Promise(function(resolve) {
                                    canvas.toBlob(function(blob) {
                                        resolve(blob);
                                    }, 'image/jpeg', 0.95);
                                });
                            });
                        };
                        
                        const handleFileSelect = function(e) {
                            const file = e.target.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = function(event) {
                                    setImageSrc(event.target.result);
                                    setShowCropModal(true);
                                    setZoom(1);
                                    setCrop({ x: 0, y: 0 });
                                };
                                reader.readAsDataURL(file);
                            }
                        };
                        
                        const handleCropComplete = function() {
                            if (!croppedAreaPixels) return;
                            
                            getCroppedImg(imageSrc, croppedAreaPixels).then(function(croppedImage) {
                                const croppedImageUrl = URL.createObjectURL(croppedImage);
                                setCroppedImageUrl(croppedImageUrl);
                                
                                // Convert blob to base64 for form submission
                                const reader = new FileReader();
                                reader.onload = function() {
                                    const base64 = reader.result;
                                    croppedImageDataInput.value = base64;
                                    // Clear the file input since we're using base64
                                    hiddenFileInput.value = '';
                                };
                                reader.readAsDataURL(croppedImage);
                                
                                setShowCropModal(false);
                                if (onImageChange) onImageChange(croppedImageUrl);
                            }).catch(function(error) {
                                console.error('Error cropping image:', error);
                                alert('Ett fel uppstod vid beskärning av bilden.');
                            });
                        };
                        
                        const handleCancelCrop = function() {
                            setShowCropModal(false);
                            setImageSrc(null);
                            hiddenFileInput.value = '';
                        };
                        
                        const handleDeleteImage = function() {
                            if (confirm('Är du säker på att du vill radera bilden?')) {
                                setImageSrc(null);
                                setCroppedImageUrl(null);
                                croppedImageDataInput.value = '';
                                hiddenFileInput.value = '';
                                deleteImageInput.value = '1';
                                if (onImageDelete) onImageDelete();
                            }
                        };
                        
                        const handleSelectNewImage = function() {
                            if (fileInputRef.current) {
                                fileInputRef.current.click();
                            }
                        };
                    
                        if (!Cropper) {
                            return React.createElement('div', null,
                                React.createElement('p', { style: { color: '#d32f2f' } }, 'Bildverktyget laddas... Om detta meddelande kvarstår, ladda om sidan.'),
                                React.createElement('input', {
                                    ref: fileInputRef,
                                    type: 'file',
                                    accept: 'image/jpeg,image/jpg,image/png,image/gif,image/webp',
                                    onChange: handleFileSelect,
                                    style: { display: 'none' }
                                })
                            );
                        }
                        
                        return React.createElement('div', null,
                            (showCropModal && imageSrc && Cropper) ? React.createElement('div', { className: 'crop-modal' },
                                React.createElement('div', { className: 'crop-modal-content' },
                                    React.createElement('h3', { style: { marginTop: 0 } }, 'Beskär bild'),
                                    React.createElement('p', { style: { fontSize: '0.9rem', color: '#666', marginBottom: '1rem' } },
                                        'Dra bilden för att positionera och använd zoom för att justera storleken.'
                                    ),
                                    React.createElement('div', { className: 'crop-container' },
                                        React.createElement(Cropper, {
                                            image: imageSrc,
                                            crop: crop,
                                            zoom: zoom,
                                            aspect: aspectRatio,
                                            onCropChange: setCrop,
                                            onZoomChange: setZoom,
                                            onCropComplete: onCropComplete
                                        })
                                    ),
                                    React.createElement('div', { style: { marginBottom: '1rem' } },
                                        React.createElement('label', { style: { display: 'block', marginBottom: '0.5rem' } },
                                            'Zoom: ' + Math.round(zoom * 100) + '%'
                                        ),
                                        React.createElement('input', {
                                            type: 'range',
                                            min: 1,
                                            max: 3,
                                            step: 0.1,
                                            value: zoom,
                                            onChange: function(e) { setZoom(parseFloat(e.target.value)); },
                                            style: { width: '100%' }
                                        })
                                    ),
                                    React.createElement('div', { className: 'crop-controls' },
                                        React.createElement('button', {
                                            type: 'button',
                                            className: 'btn-small btn-small-secondary',
                                            onClick: handleCancelCrop
                                        }, 'Avbryt'),
                                        React.createElement('button', {
                                            type: 'button',
                                            className: 'btn-small btn-primary',
                                            onClick: handleCropComplete
                                        }, 'Bekräfta beskärning')
                                    )
                                ) : null,
                            (!showCropModal) ? React.createElement(React.Fragment, null,
                                croppedImageUrl ? React.createElement('div', { className: 'image-upload-area has-image' },
                                    React.createElement('div', { className: 'image-preview-container' },
                                        React.createElement('img', {
                                            src: croppedImageUrl,
                                            alt: 'Förhandsgranskning'
                                        })
                                    ),
                                    React.createElement('div', { className: 'image-actions' },
                                        React.createElement('button', {
                                            type: 'button',
                                            className: 'btn-small btn-small-secondary',
                                            onClick: handleSelectNewImage
                                        }, 'Välj annan bild'),
                                        React.createElement('button', {
                                            type: 'button',
                                            className: 'btn-small btn-small-danger',
                                            onClick: handleDeleteImage
                                        }, 'Radera bild')
                                    )
                                ) : React.createElement('div', {
                                    className: 'image-upload-area',
                                    onClick: handleSelectNewImage
                                },
                                    React.createElement('p', { style: { margin: 0, fontSize: '1.1rem' } },
                                        'Klicka för att välja bild'
                                    ),
                                    React.createElement('p', { style: { margin: '0.5rem 0 0 0', color: '#666', fontSize: '0.9rem' } },
                                        'JPG, PNG, GIF eller WebP'
                                    )
                                ),
                                React.createElement('input', {
                                    ref: fileInputRef,
                                    type: 'file',
                                    accept: 'image/jpeg,image/jpg,image/png,image/gif,image/webp',
                                    onChange: handleFileSelect,
                                    style: { display: 'none' }
                                })
                            ) : null
                        );
                    }
                    
                */
            </script>
            
            <div class="form-actions">
                <button type="submit" name="save_draft" class="btn-primary">
                    <?php echo $isEdit ? 'Spara som utkast' : 'Spara som utkast'; ?>
                </button>
                <button type="submit" name="publish" class="btn-success">
                    <?php echo $isEdit ? 'Publicera' : 'Publicera'; ?>
                </button>
                <a href="dashboard.php" class="btn btn-secondary">Avbryt</a>
            </div>
        </form>
    </div>
</body>
</html>

