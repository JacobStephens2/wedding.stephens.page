<?php
require_once __DIR__ . '/../private/config.php';
$page_title = "Jacob & Melissa - April 11, 2026";
include __DIR__ . '/includes/header.php';
?>

<main class="home-page">
    <div class="background-overlay"></div>
    <div class="background-media">
        <video autoplay muted loop playsinline>
            <source src="/assets.php?type=video&path=Jacob_and_Melissa_proposal_mobile.mp4" type="video/mp4">
            <img src="/assets.php?type=photo&path=proposal/PeytoLakeBanff_Proposal_One_Knee_wide.jpg" alt="Jacob and Melissa">
        </video>
    </div>
    
    <div class="home-content">
        <h1 class="couple-names">Jacob & Melissa</h1>
        <p class="wedding-date">April 11, 2026 | Philadelphia</p>
        <p class="countdown">
            <span id="days-count">-</span> days to go!
        </p>
    </div>
</main>

<script>
// Countdown timer
function updateCountdown() {
    const weddingDate = new Date('2026-04-11T00:00:00');
    const now = new Date();
    const diff = weddingDate - now;
    
    if (diff > 0) {
        const days = Math.ceil(diff / (1000 * 60 * 60 * 24));
        document.getElementById('days-count').textContent = days;
    } else {
        document.getElementById('days-count').textContent = '0';
    }
}

updateCountdown();
setInterval(updateCountdown, 1000 * 60 * 60); // Update hourly
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

