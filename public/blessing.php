<?php
require_once __DIR__ . '/../private/config.php';
$page_title = "Blessing - Jacob & Melissa";
include __DIR__ . '/includes/header.php';
?>

<main class="page-container">
    <h1 class="page-title">Blessing</h1>
    
    <section class="story-section">
        <p>The day after the proposal, September 28, 2025, Fr. Remi Morales of St. Agatha St. James parish blessed Jacob and Melissa's engagement, surrounded by many friends and their parents. Afterwards, Jacob, Melissa, their parents, and Melissa's brother Matt went to dinner in South Philly at Scannichio's.</p>
        <div class="story-media">
            <img src="/assets.php?type=photo&path=blessing/Landscape_JM_at_Altar.jpg" alt="Jacob and Melissa at altar" class="clickable-image">
            <img src="/assets.php?type=photo&path=blessing/Portrait_JM_at_Altar.jpg" alt="Jacob and Melissa at altar portrait" class="clickable-image">
            <img src="/assets.php?type=photo&path=blessing/JM_With_Parents_at_Scannichios.jpg" alt="Jacob and Melissa with parents at Scannichio's" class="clickable-image">
            <iframe src="https://www.youtube.com/embed/dko2cded45E" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </section>
</main>

<!-- Lightbox Modal -->
<div id="lightbox" class="lightbox">
    <span class="lightbox-close">&times;</span>
    <img class="lightbox-content" id="lightbox-image" src="" alt="">
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


