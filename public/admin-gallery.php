<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/admin_auth.php';
require_once __DIR__ . '/../private/admin_sample.php';

session_start();

$error = '';
$success = '';
$sampleMode = isAdminSampleMode();
$authenticated = $sampleMode;

$storySections = [
    ''                   => '(None — gallery only)',
    'a_prayer_and_dance' => 'A Prayer and a Dance',
    'the_sidewalk'       => 'The Sidewalk',
    'tacos_and_theology' => 'Tacos and Theology',
    'the_balcony'        => 'The Balcony',
    'the_first_snow'     => 'The First Snow',
    'pastaio'            => 'Pastaio',
    'the_novena'         => 'The Novena',
    'the_blessing'       => 'The Blessing',
    'proposal'           => 'Written in Stone and Sky (Proposal)',
    'blessing'           => 'Written in Stone and Sky (Blessing)',
    'divine_mercy'       => 'Divine Mercy\'s Design',
];

if (!$sampleMode && isAdminAuthenticated()) {
    $authenticated = true;
}

if (!$sampleMode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['admin_login'])) {
    $password = trim($_POST['password'] ?? '');
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
    if ($password === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $authenticated = true;
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

if (!$sampleMode && isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin-gallery');
    exit;
}

/**
 * Handle an uploaded photo file. HEIC files are converted to JPEG.
 * Returns the relative path (from private/photos/) on success, or null on failure.
 */
function handlePhotoUpload(array $file, string &$error): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed (error code ' . $file['error'] . ').';
        return null;
    }

    $uploadDir = __DIR__ . '/../private/photos/gallery/';
    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseName);
    $isHeic = in_array($ext, ['heic', 'heif']);

    $targetExt = $isHeic ? 'jpg' : $ext;
    $targetName = $safeName . '_' . time() . '.' . $targetExt;
    $targetPath = $uploadDir . $targetName;

    if ($isHeic) {
        $tmpSrc = escapeshellarg($file['tmp_name']);
        $tmpDst = escapeshellarg($targetPath);
        $converted = false;
        $errors = [];

        $pyScript = "import sys;from pillow_heif import register_heif_opener;from PIL import Image;"
            . "register_heif_opener();img=Image.open(sys.argv[1]);"
            . "img.save(sys.argv[2],'JPEG',quality=90)";
        $pyCmd = "python3 -c " . escapeshellarg($pyScript) . " $tmpSrc $tmpDst";
        exec("$pyCmd 2>&1", $out1, $r1);
        if ($r1 === 0 && file_exists($targetPath)) {
            $converted = true;
        } else {
            $errors[] = implode(' ', $out1);
        }

        if (!$converted) {
            $out2 = [];
            exec("heif-convert -q 90 $tmpSrc $tmpDst 2>&1", $out2, $r2);
            if ($r2 === 0 && file_exists($targetPath)) {
                $converted = true;
            } else {
                $errors[] = implode(' ', $out2);
            }
        }

        if (!$converted) {
            $error = 'HEIC conversion failed: ' . implode(' | ', $errors);
            return null;
        }
    } else {
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            $error = 'Failed to save uploaded file.';
            return null;
        }
    }

    return 'gallery/' . $targetName;
}

// Handle add photo
if (!$sampleMode && $authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_photo'])) {
    try {
        $pdo = getDbConnection();
        $path = trim($_POST['path'] ?? '');
        $alt = trim($_POST['alt'] ?? '');
        $photoDate = trim($_POST['photo_date'] ?? '');
        $position = trim($_POST['position'] ?? '');

        if (!empty($_FILES['photo_upload']['name'])) {
            $uploadPath = handlePhotoUpload($_FILES['photo_upload'], $error);
            if ($uploadPath) {
                $path = $uploadPath;
            }
        }

        $storySection = trim($_POST['story_section'] ?? '');
        $storyPosition = trim($_POST['story_position'] ?? '');

        if (empty($path) || empty($photoDate)) {
            $error = $error ?: 'File path (or upload) and date are required.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO gallery_photos (path, alt, photo_date, position, story_section, story_position)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $path,
                $alt,
                $photoDate,
                $position !== '' ? $position : null,
                $storySection !== '' ? $storySection : null,
                $storyPosition !== '' ? (int)$storyPosition : null,
            ]);
            header('Location: /admin-gallery?added=1');
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error adding photo: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle update photo
if (!$sampleMode && $authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_photo'])) {
    try {
        $pdo = getDbConnection();
        $id = (int)$_POST['photo_id'];
        $path = trim($_POST['path'] ?? '');
        $alt = trim($_POST['alt'] ?? '');
        $photoDate = trim($_POST['photo_date'] ?? '');
        $position = trim($_POST['position'] ?? '');

        if (!empty($_FILES['photo_upload']['name'])) {
            $uploadPath = handlePhotoUpload($_FILES['photo_upload'], $error);
            if ($uploadPath) {
                $path = $uploadPath;
            }
        }

        $storySection = trim($_POST['story_section'] ?? '');
        $storyPosition = trim($_POST['story_position'] ?? '');

        if (empty($path) || empty($photoDate)) {
            $error = $error ?: 'File path (or upload) and date are required.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE gallery_photos
                SET path = ?, alt = ?, photo_date = ?, position = ?, story_section = ?, story_position = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $path,
                $alt,
                $photoDate,
                $position !== '' ? $position : null,
                $storySection !== '' ? $storySection : null,
                $storyPosition !== '' ? (int)$storyPosition : null,
                $id,
            ]);
            header('Location: /admin-gallery?updated=1');
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error updating photo: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle delete photo
if (!$sampleMode && $authenticated && isset($_GET['delete'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM gallery_photos WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header('Location: /admin-gallery?deleted=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error deleting photo: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch photo for editing
$editPhoto = null;
if ($sampleMode && isset($_GET['edit'])) {
    foreach (getSampleGalleryPhotos() as $samplePhoto) {
        if ((int) $samplePhoto['id'] === (int) $_GET['edit']) {
            $editPhoto = $samplePhoto;
            break;
        }
    }
} elseif ($authenticated && isset($_GET['edit'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM gallery_photos WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editPhoto = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error loading photo: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch all photos
$photos = [];
if ($sampleMode) {
    $photos = getSampleGalleryPhotos();
} elseif ($authenticated) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT * FROM gallery_photos ORDER BY photo_date ASC, id ASC");
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error loading photos: ' . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Manage Gallery - Jacob & Melissa";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/includes/theme_init.php'; ?>
    <?php renderAdminSampleModeAssets(); ?>
    <link rel="stylesheet" href="/css/style.css?v=<?php
        $cssPath = __DIR__ . '/css/style.css';
        echo file_exists($cssPath) ? filemtime($cssPath) : time();
    ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400&family=Beloved+Script&family=Crimson+Text:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .back-to-site {
            text-align: center;
            margin-bottom: 2rem;
        }
        .back-to-site a {
            color: var(--color-green);
            text-decoration: none;
            font-size: 1.1rem;
            transition: color 0.3s;
        }
        .back-to-site a:hover {
            color: var(--color-gold);
            text-decoration: underline;
        }
        .logout-link {
            text-align: right;
            margin-bottom: 1rem;
        }
        .logout-link a {
            color: var(--color-dark);
            text-decoration: none;
        }
        .logout-link a:hover {
            color: var(--color-green);
        }
        .photo-form {
            background: var(--color-surface);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
            margin-bottom: 2rem;
        }
        .photo-form h2 {
            color: var(--color-green);
            margin-bottom: 1.5rem;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 180px;
        }
        .form-row .form-group.wide {
            flex: 2;
            min-width: 300px;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .btn-secondary:hover { background-color: #5a6268; color: white; }
        .photo-form .form-group select {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            background: var(--color-surface);
        }
        .photo-form .form-group select:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
        }
        .photo-preview {
            margin-top: 0.5rem;
            max-width: 200px;
            max-height: 140px;
            border-radius: 4px;
            object-fit: cover;
        }
        .gallery-admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
        }
        .gallery-admin-card {
            background: var(--color-surface);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px var(--color-shadow);
        }
        .gallery-admin-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }
        .gallery-admin-card-body {
            padding: 0.75rem 1rem;
        }
        .gallery-admin-card-body .photo-alt {
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            color: var(--color-dark);
            margin-bottom: 0.25rem;
        }
        .gallery-admin-card-body .photo-date {
            font-family: 'Crimson Text', serif;
            font-size: 0.85rem;
            color: var(--color-text-muted);
            margin-bottom: 0.5rem;
        }
        .gallery-admin-card-body .photo-path {
            font-family: monospace;
            font-size: 0.75rem;
            color: var(--color-text-muted);
            word-break: break-all;
            margin-bottom: 0.5rem;
        }
        .card-actions {
            display: flex;
            gap: 0.75rem;
        }
        .photo-story-badge {
            display: inline-block;
            font-family: 'Crimson Text', serif;
            font-size: 0.75rem;
            color: white;
            background: var(--color-green);
            padding: 0.15rem 0.5rem;
            border-radius: 10px;
            margin-bottom: 0.4rem;
        }
        .card-actions a {
            font-size: 0.85rem;
            text-decoration: none;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            transition: background 0.3s;
        }
        .card-actions .edit-link { color: var(--color-green); }
        .card-actions .edit-link:hover { background: rgba(127, 143, 101, 0.1); }
        .card-actions .delete-link { color: #dc3545; }
        .card-actions .delete-link:hover { background: rgba(220, 53, 69, 0.1); }
        .photo-count-label {
            font-family: 'Crimson Text', serif;
            color: var(--color-text-secondary);
            margin-bottom: 1rem;
            display: block;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <main class="page-container">
        <?php renderAdminSampleBanner('Gallery Sample Mode'); ?>
        <div class="back-to-site">
            <a href="/">← Back to Main Site</a>
        </div>

        <?php if (!$authenticated): ?>
            <div class="form-container">
                <h1 class="page-title">Manage Gallery</h1>
                <?php if ($error): ?>
                    <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>
                <form method="POST" action="/admin-gallery">
                    <input type="hidden" name="admin_login" value="1">
                    <div class="form-group required">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-container">
                <div class="logout-link">
                    <a href="/admin-gallery?logout=1">Logout</a>
                </div>
                <h1 class="page-title">Manage Gallery</h1>

                <?php if ($error): ?>
                    <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>
                <?php if (isset($_GET['added'])): ?>
                    <div class="alert alert-success"><p>Photo added successfully!</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success"><p>Photo updated successfully!</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success"><p>Photo deleted.</p></div>
                <?php endif; ?>

                <!-- Add/Edit Photo Form -->
                <div class="photo-form">
                    <h2><?php echo $editPhoto ? 'Edit Photo' : 'Add Photo'; ?></h2>
                    <form method="POST" action="/admin-gallery" enctype="multipart/form-data">
                        <?php if ($editPhoto): ?>
                            <input type="hidden" name="update_photo" value="1">
                            <input type="hidden" name="photo_id" value="<?php echo htmlspecialchars($editPhoto['id']); ?>">
                        <?php else: ?>
                            <input type="hidden" name="add_photo" value="1">
                        <?php endif; ?>
                        <div class="form-row">
                            <div class="form-group wide">
                                <label for="photo_upload">Upload Photo</label>
                                <input type="file" id="photo_upload" name="photo_upload"
                                       accept="image/*,.heic,.heif"
                                       style="font-family:'Crimson Text',serif; font-size:1rem;">
                                <small style="color:#888; font-family:'Crimson Text',serif;">HEIC files are automatically converted to JPEG.</small>
                            </div>
                            <div class="form-group required">
                                <label for="photo_date">Date</label>
                                <input type="date" id="photo_date" name="photo_date" required
                                       value="<?php echo htmlspecialchars($editPhoto['photo_date'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group wide">
                                <label for="path">File Path (relative to private/photos/)</label>
                                <input type="text" id="path" name="path"
                                       placeholder="e.g., meeting/photo.jpg — leave blank if uploading"
                                       value="<?php echo htmlspecialchars($editPhoto['path'] ?? ''); ?>">
                                <small style="color:#888; font-family:'Crimson Text',serif;">Only needed if not uploading a file.</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group wide">
                                <label for="alt">Description</label>
                                <input type="text" id="alt" name="alt"
                                       placeholder="Brief description of the photo"
                                       value="<?php echo htmlspecialchars($editPhoto['alt'] ?? ''); ?>"
                                       style="font-family:'Crimson Text',serif;">
                            </div>
                            <div class="form-group">
                                <label for="position">Thumbnail Crop Position</label>
                                <select id="position" name="position">
                                    <option value="" <?php echo empty($editPhoto['position']) ? 'selected' : ''; ?>>Center (default)</option>
                                    <option value="top" <?php echo (($editPhoto['position'] ?? '') === 'top') ? 'selected' : ''; ?>>Top</option>
                                    <option value="bottom" <?php echo (($editPhoto['position'] ?? '') === 'bottom') ? 'selected' : ''; ?>>Bottom</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group wide">
                                <label for="story_section">Story Page Section</label>
                                <select id="story_section" name="story_section">
                                    <?php foreach ($storySections as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"
                                        <?php echo (($editPhoto['story_section'] ?? '') === $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="story_position">Position in Carousel</label>
                                <input type="number" id="story_position" name="story_position" min="1"
                                       placeholder="e.g., 1, 2, 3"
                                       value="<?php echo htmlspecialchars($editPhoto['story_position'] ?? ''); ?>">
                            </div>
                        </div>
                        <?php if ($editPhoto): ?>
                        <img src="/assets.php?type=photo&path=<?php echo urlencode($editPhoto['path']); ?>" alt="Preview" class="photo-preview">
                        <?php endif; ?>
                        <div class="form-actions">
                            <button type="submit" class="btn"><?php echo $editPhoto ? 'Update Photo' : 'Add Photo'; ?></button>
                            <?php if ($editPhoto): ?>
                                <a href="/admin-gallery" class="btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Photo Grid -->
                <span class="photo-count-label"><?php echo count($photos); ?> photo<?php echo count($photos) !== 1 ? 's' : ''; ?> in gallery</span>
                <div class="gallery-admin-grid">
                    <?php foreach ($photos as $photo): ?>
                    <div class="gallery-admin-card">
                        <img src="/assets.php?type=photo&path=<?php echo urlencode($photo['path']); ?>"
                             alt="<?php echo htmlspecialchars($photo['alt']); ?>"
                             <?php if (!empty($photo['position'])): ?>style="object-position: <?php echo htmlspecialchars($photo['position']); ?>;"<?php endif; ?>>
                        <div class="gallery-admin-card-body">
                            <div class="photo-alt"><?php echo htmlspecialchars($photo['alt'] ?: '(no description)'); ?></div>
                            <div class="photo-date"><?php echo htmlspecialchars($photo['photo_date']); ?></div>
                            <div class="photo-path"><?php echo htmlspecialchars($photo['path']); ?></div>
                            <?php if (!empty($photo['story_section'])): ?>
                            <div class="photo-story-badge"><?php echo htmlspecialchars($storySections[$photo['story_section']] ?? $photo['story_section']); ?> (#<?php echo (int)$photo['story_position']; ?>)</div>
                            <?php endif; ?>
                            <div class="card-actions">
                                <a href="/admin-gallery?edit=<?php echo $photo['id']; ?>" class="edit-link">Edit</a>
                                <a href="/admin-gallery?delete=<?php echo $photo['id']; ?>" class="delete-link"
                                   onclick="return confirm('Remove this photo from the gallery?');">Delete</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($photos)): ?>
                        <p style="color:#666; font-family:'Crimson Text',serif; grid-column: 1/-1; text-align:center; padding:2rem;">No photos in gallery yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var fileInput = document.getElementById('photo_upload');
                var dateInput = document.getElementById('photo_date');
                var altInput = document.getElementById('alt');
                if (!fileInput) return;

                fileInput.addEventListener('change', function() {
                    if (!this.files || !this.files[0]) return;
                    var name = this.files[0].name;
                    var base = name.replace(/\.[^.]+$/, '');
                    var parsed = parseFilename(base);
                    if (parsed.date && !dateInput.value) {
                        dateInput.value = parsed.date;
                    }
                    if (parsed.desc && !altInput.value) {
                        altInput.value = parsed.desc;
                    }
                });

                function parseFilename(base) {
                    var date = null;
                    var desc = null;

                    // MM-DD-YY or MM-DD-YYYY at start: "11-16-24 Balcony View"
                    var m = base.match(/^(\d{1,2})-(\d{1,2})-(\d{2,4})\s+(.+)$/);
                    if (m) {
                        var y = m[3].length === 2 ? (parseInt(m[3]) < 50 ? '20' + m[3] : '19' + m[3]) : m[3];
                        date = y + '-' + m[1].padStart(2, '0') + '-' + m[2].padStart(2, '0');
                        desc = cleanDesc(m[4]);
                        return { date: date, desc: desc };
                    }

                    // YYYY-MM-DD at start: "2024-11-16_Description" or "2024-11-16 Description"
                    m = base.match(/^(\d{4})-(\d{2})-(\d{2})[_\s]+(.+)$/);
                    if (m) {
                        date = m[1] + '-' + m[2] + '-' + m[3];
                        desc = cleanDesc(m[4]);
                        return { date: date, desc: desc };
                    }

                    // Just a description, no date found
                    return { date: null, desc: cleanDesc(base) };
                }

                function cleanDesc(s) {
                    return s.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
                }
            });
            </script>
        <?php endif; ?>
    </main>
</body>
</html>
