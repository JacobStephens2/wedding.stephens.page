<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
$page_title = "Gallery - Jacob & Melissa";
include __DIR__ . '/includes/header.php';

$photos = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT path, alt, photo_date, position FROM gallery_photos ORDER BY photo_date ASC, id ASC");
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Gallery error: " . $e->getMessage());
}
?>

<main class="page-container">
    <h1 class="page-title">Gallery</h1>

    <div class="gallery-grid">
        <?php foreach ($photos as $i => $photo): ?>
        <div class="gallery-item">
            <img
                src="/assets.php?type=photo&path=<?php echo urlencode($photo['path']); ?>"
                alt="<?php echo htmlspecialchars($photo['alt']); ?>"
                class="gallery-image"
                loading="lazy"
                data-gallery-index="<?php echo $i; ?>"
                <?php if (!empty($photo['position'])): ?>style="object-position: <?php echo htmlspecialchars($photo['position']); ?>;"<?php endif; ?>
            >
            <div class="gallery-caption">
                <?php if (!empty($photo['alt'])): ?><span class="gallery-caption-desc"><?php echo htmlspecialchars($photo['alt']); ?></span><?php endif; ?>
                <span class="gallery-caption-date"><?php echo date('F j, Y', strtotime($photo['photo_date'])); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Lightbox Modal -->
<div id="lightbox" class="lightbox">
    <span class="lightbox-close">&times;</span>
    <img class="lightbox-content" id="lightbox-image" src="" alt="">
</div>

<style>
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.25rem;
        max-width: 1100px;
        margin: 0 auto 3rem;
        padding: 0 1rem;
    }

    .gallery-item {
        border-radius: 8px;
        overflow: hidden;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .gallery-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .gallery-image {
        width: 100%;
        height: 260px;
        object-fit: cover;
        display: block;
        cursor: pointer;
    }

    .gallery-caption {
        padding: 0.65rem 0.85rem;
        font-family: 'Crimson Text', serif;
        text-align: center;
    }
    .gallery-caption-desc {
        display: block;
        font-size: 0.95rem;
        color: #444;
    }
    .gallery-caption-date {
        display: block;
        font-size: 0.82rem;
        color: #999;
    }

    @media (max-width: 600px) {
        .gallery-grid {
            grid-template-columns: 1fr;
        }
        .gallery-image {
            height: 220px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var galleryImages = document.querySelectorAll('.gallery-image');
    var allImages = Array.from(galleryImages);
    var lightbox = document.getElementById('lightbox');
    var lightboxImg = document.getElementById('lightbox-image');
    var lightboxClose = document.querySelector('.lightbox-close');
    var currentIndex = 0;

    function openLightbox(index) {
        currentIndex = index;
        lightboxImg.src = allImages[currentIndex].src;
        lightboxImg.alt = allImages[currentIndex].alt;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }

    function navigate(direction) {
        currentIndex += direction;
        if (currentIndex < 0) currentIndex = allImages.length - 1;
        if (currentIndex >= allImages.length) currentIndex = 0;
        lightboxImg.src = allImages[currentIndex].src;
        lightboxImg.alt = allImages[currentIndex].alt;
    }

    galleryImages.forEach(function(img, i) {
        img.addEventListener('click', function(e) {
            e.stopPropagation();
            openLightbox(i);
        });
    });

    if (lightboxClose) lightboxClose.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function(e) {
        if (!lightbox.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        else if (e.key === 'ArrowRight') navigate(1);
        else if (e.key === 'ArrowLeft') navigate(-1);
    });

    var touchStartX = 0;
    var swipeThreshold = 50;
    lightbox.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    lightbox.addEventListener('touchend', function(e) {
        var endX = e.changedTouches[0].screenX;
        var diff = endX - touchStartX;
        if (Math.abs(diff) > swipeThreshold) {
            navigate(diff < 0 ? 1 : -1);
        } else if (e.target === lightboxImg) {
            var rect = lightboxImg.getBoundingClientRect();
            var tapX = endX - rect.left;
            navigate(tapX < rect.width / 2 ? -1 : 1);
        }
    }, { passive: true });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
