    <footer>
        <p>&copy; <?php echo date('Y'); ?> Jacob Stephens & Melissa Longua</p>
        <p>Website created by <a href="https://stephens.page/" target="_blank" rel="noopener noreferrer">Jacob Stephens</a></p>
    </footer>
    
    <script src="/js/main.js?v=<?php 
        $jsPath = __DIR__ . '/../js/main.js?v=3';
        echo file_exists($jsPath) ? filemtime($jsPath) : time(); 
    ?>"></script>
</body>
</html>

