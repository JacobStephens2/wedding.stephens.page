<?php
require_once __DIR__ . '/../private/config.php';
$page_title = "Our Story - Jacob & Melissa";
include __DIR__ . '/includes/header.php';
?>

<main class="page-container">
    <h1 class="page-title">Our Story</h1>
    
    <section class="story-section">
        <h2>Meeting</h2>
        <p>Jacob and Melissa met fusion dancing in Philadelphia on October&nbsp;18,&nbsp;2024.</p>
        <div class="story-media">
            <img src="/assets.php?type=photo&path=meeting/2024-11-17_Rittenhop_Dip_Landscape.jpg" alt="Jacob and Melissa dancing" class="clickable-image">
        </div>
    </section>
    
    <section class="story-section">
        <h2>Proposal</h2>
        <p>On September 27, 2025, Jacob proposed to Melissa in Banff National Park on a cliff of Mt. Jimmy Stepson, overlooking Peyto Lake, with guitarist Dave Hirschman playing.</p>
        <div class="story-media">
            <img src="/assets.php?type=photo&path=proposal/PeytoLakeBanff_Proposal_Closeup_Smile.jpg" alt="Proposal closeup" class="clickable-image">
            <img src="/assets.php?type=photo&path=proposal/PeytoLakeBanff_Proposal_One_Knee_wide.jpg" alt="Proposal on one knee" class="clickable-image">
            <video controls>
                <source src="/assets.php?type=video&path=Jacob_and_Melissa_proposal_mobile.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
    </section>
    
    <section class="story-section">
        <h2>Blessing</h2>
        <p>The day after the proposal, Fr. Remi Morales of St. Agatha St. James church blessed Jacob and Melissa's engagement, surrounded by many friends and their parents. Afterwards, Jacob, Melissa, their parents, and Melissa's brother Matt went to dinner in South Philly at Scannichio's.</p>
        <div class="story-media">
            <img src="/assets.php?type=photo&path=blessing/Landscape_JM_at_Altar.jpg" alt="Jacob and Melissa at altar" class="clickable-image">
            <img src="/assets.php?type=photo&path=blessing/Portrait_JM_at_Altar.jpg" alt="Jacob and Melissa at altar portrait" class="clickable-image">
            <img src="/assets.php?type=photo&path=blessing/JM_With_Parents_at_Scannichios.jpg" alt="Jacob and Melissa with parents at Scannichio's" class="clickable-image">
            <video controls>
                <source src="/assets.php?type=video&path=JM_Engagement-Blessing-mobile.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
    </section>
    
    <section class="story-section">
        <h2>Wedding</h2>
        <p>The wedding will be on April 11, 2026 at St. Agatha St. James Parish in Philadelphia. The reception will be hosted at Bala Golf Club.</p>
    </section>
</main>

<!-- Lightbox Modal -->
<div id="lightbox" class="lightbox">
    <span class="lightbox-close">&times;</span>
    <img class="lightbox-content" id="lightbox-image" src="" alt="">
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

