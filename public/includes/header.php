<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-DQN0TVHB1Z"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-DQN0TVHB1Z');
    </script>
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Jacob & Melissa - April 11, 2026'; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400&family=Beloved+Script&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <div class="header-content">
            <h1 class="site-title"><a href="/">Jacob & Melissa</a></h1>
            
            <!-- Mobile menu button -->
            <button class="mobile-menu-toggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <!-- Desktop navigation -->
            <nav class="desktop-nav">
                <a href="/">Home</a>
                <a href="/story">Story</a>
                <a href="/rsvp">RSVP</a>
                <a href="/registry">Registry</a>
                <a href="/contact">Contact</a>
            </nav>
        </div>
        
        <!-- Mobile navigation -->
        <nav class="mobile-nav">
            <a href="/">Home</a>
            <a href="/story">Story</a>
            <a href="/rsvp">RSVP</a>
            <a href="/registry">Registry</a>
            <a href="/contact">Contact</a>
        </nav>
    </header>

