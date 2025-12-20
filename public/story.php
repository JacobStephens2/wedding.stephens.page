<?php
require_once __DIR__ . '/../private/config.php';
$page_title = "Our Story - Jacob & Melissa";
include __DIR__ . '/includes/header.php';
?>

<main class="page-container">
    <h1 class="page-title">Our Story</h1>
    
    <section class="story-section">
        <h2>Meet Cute</h2>
        <p><em>Jacob and I met fusion dancing in Old City, Philadelphia, and kept meeting at various dance events in the city in October-November of 2024.</em></p>
        
        <div class="photo-carousel">
            <div class="carousel-container">
                <img src="/assets.php?type=photo&path=meeting/2024-11-15_Fusion_dance_at_Concierge_Ballroom.jpg" alt="Fusion dance at Concierge Ballroom" class="carousel-image clickable-image active">
                <img src="/assets.php?type=photo&path=meeting/2024-11-17_Rittenhop_Dip_Landscape.jpg" alt="Jacob and Melissa dancing" class="carousel-image clickable-image">
            </div>
            <button class="carousel-btn carousel-prev" aria-label="Previous image">‹</button>
            <button class="carousel-btn carousel-next" aria-label="Next image">›</button>
            <div class="carousel-indicators">
                <span class="indicator active" data-slide="0"></span>
                <span class="indicator" data-slide="1"></span>
            </div>
        </div>
    </section>
    
    <section class="story-section">
        <h2>Dating</h2>
        <p><em>Jacob had been looking into becoming Catholic, and I had been to St. Agatha-St. James Church a couple of times since recently moving to Philly. We started attending events at St. AJs and dances throughout the city, and after a few dates and a spontaneous knock on my door with a TON of halal food and hummus, we started officially dating.</em></p>
        <div class="story-media">
            <img src="/assets.php?type=photo&path=dating/2025-01-16_Mel_and_Jacob_2_dip_bw.jpg" alt="Jacob and Melissa" class="clickable-image">
        </div>
    </section>
    
    <section class="story-section">
        <h2>Proposal</h2>
        <p>Jacob proposed to Melissa in Banff National Park on Mt. Jimmy Stepson, overlooking Peyto Lake, while a guitarist played Can't Help Falling In Love in the background.</p>
        
        <div class="photo-carousel carousel-landscape">
            <div class="carousel-container">
                <img src="/assets.php?type=photo&path=proposal/PeytoLakeBanff_Proposal_One_Knee_wide.jpg" alt="Proposal on one knee" class="carousel-image clickable-image active">
                <img src="/assets.php?type=photo&path=proposal/PeytoLakeBanff_Proposal_Closeup_Smile.jpg" alt="Proposal closeup" class="carousel-image clickable-image">
            </div>
            <button class="carousel-btn carousel-prev" aria-label="Previous image">‹</button>
            <button class="carousel-btn carousel-next" aria-label="Next image">›</button>
            <div class="carousel-indicators">
                <span class="indicator active" data-slide="0"></span>
                <span class="indicator" data-slide="1"></span>
            </div>
        </div>
        
        <div class="story-media">
            <iframe src="https://www.youtube.com/embed/iEbqiWzH800" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </section>
    
    <section class="story-section">
        <h2>Blessing</h2>
        <p>The day after the proposal, Fr. Remi Morales of St. Agatha St. James church blessed Jacob and Melissa's engagement, surrounded by their parents and many friends. Afterwards, Jacob, Melissa, their parents, and Melissa's brother Matt went to dinner in South Philly at Scannichio's</p>
        
        <div class="photo-carousel carousel-landscape">
            <div class="carousel-container">
                <img src="/assets.php?type=photo&path=blessing/Landscape_JM_at_Altar.jpg" alt="Jacob and Melissa at altar" class="carousel-image clickable-image active">
                <img src="/assets.php?type=photo&path=blessing/JM_With_Parents_at_Scannichios.jpg" alt="Jacob and Melissa with parents at Scannichio's" class="carousel-image clickable-image">
            </div>
            <button class="carousel-btn carousel-prev" aria-label="Previous image">‹</button>
            <button class="carousel-btn carousel-next" aria-label="Next image">›</button>
            <div class="carousel-indicators">
                <span class="indicator active" data-slide="0"></span>
                <span class="indicator" data-slide="1"></span>
            </div>
        </div>
        
        <div class="story-media">
            <iframe src="https://www.youtube.com/embed/dko2cded45E" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </section>
    
    <section class="story-section">
        <h2>Wedding</h2>
        <p>The wedding will be on April 11, 2026 at St. Agatha St. James Parish in Philadelphia. The reception will be hosted at Bala Golf Club.</p>
        <div class="story-media-block">
            <img src="/assets.php?type=photo&path=wedding/Church_Interior_Mass_Kneeling_Ordination.jpg" alt="Church interior" class="clickable-image">
            <img src="/assets.php?type=photo&path=reception/Bala-Golf-Club-outdoor-view.jpg" alt="Bala Golf Club outdoor view" class="clickable-image">
        </div>
        <p style="margin-top: 2rem;"><em>It's been quite the journey of faith and hope. God has been present every step of the way. We still love dancing and being very involved in our parish community, and are excited to be preparing for our sacramental wedding. Jacob entered the Catholic Church in fullness on Divine Mercy Sunday, 2025, and our wedding date is set for the eve of Divine Mercy Sunday, 2026. God has made us new and continues to make us new and give us new life and new hearts, and we see His beauty and His hand in our Easter Octave wedding date.</em></p>
    </section>
</main>

<!-- Lightbox Modal -->
<div id="lightbox" class="lightbox">
    <span class="lightbox-close">&times;</span>
    <img class="lightbox-content" id="lightbox-image" src="" alt="">
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

