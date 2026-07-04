<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo isset($title) ? htmlspecialchars($title) : 'UNO Mobile'; ?></title>
    
    <!-- Google Fonts: Outfit (headings) and Inter (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="mobile-container">
        <!-- Theme Toggle Button (top-right) -->
        <button id="btn-theme" class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">
            <i class="fas fa-moon"></i>
        </button>

        <?php echo $content; ?>
    </div>

    <!-- Music Controller -->
    <script src="assets/js/music.js"></script>

    <script>
    /* ========== THEME TOGGLE ========== */
    (function() {
        const saved = localStorage.getItem('uno_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', saved);
        const btn = document.getElementById('btn-theme');
        if (btn) {
            btn.innerHTML = saved === 'dark' ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
        }
    })();

    function toggleTheme() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme') || 'dark';
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('uno_theme', next);
        const btn = document.getElementById('btn-theme');
        if (btn) {
            btn.innerHTML = next === 'dark' ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
        }
    }

    /* ========== AUTO-START MUSIC ========== */
    document.addEventListener('DOMContentLoaded', () => {
        // Try to start music
        setTimeout(() => {
            try {
                MusicController.start();
                // Sync home music button if it exists
                const homeBtn = document.getElementById('btn-music-home');
                if (homeBtn && MusicController.playing) homeBtn.classList.add('on');
            } catch(e) {}
        }, 500);

        // Handle autoplay policy — start on first user interaction if needed
        const resumeMusic = () => {
            try {
                if (MusicController.ctx && MusicController.ctx.state === 'suspended') {
                    MusicController.ctx.resume();
                }
                if (!MusicController.playing) {
                    MusicController.start();
                    const homeBtn = document.getElementById('btn-music-home');
                    if (homeBtn) homeBtn.classList.add('on');
                }
            } catch(e) {}
            document.removeEventListener('click', resumeMusic);
            document.removeEventListener('touchstart', resumeMusic);
        };
        document.addEventListener('click', resumeMusic);
        document.addEventListener('touchstart', resumeMusic);
    });
    </script>
</body>
</html>
