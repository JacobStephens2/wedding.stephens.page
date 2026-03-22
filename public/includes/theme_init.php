    <script>
        (function() {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
                var m = document.querySelector('meta[name="theme-color"]');
                if (m) m.content = '#1a1a1a';
            }
        })();
    </script>
