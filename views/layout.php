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
    <link rel="stylesheet" href="assets/css/style.css?v=14">
</head>
<body>
    <div class="mobile-container">
        <script>document.documentElement.setAttribute('data-theme','dark');</script>
        <?php echo $content; ?>
    </div>

   <!-- Music Controller -->
    <script>const UNO_MUSIC_ID = '<?php echo isset($musicVideoId) ? $musicVideoId : '_Q8Ih2SW-TE'; ?>';</script>
    <script src="assets/js/music.js"></script>

    <script>
    /* ========== AUTO-START MUSIC ========== */
    document.addEventListener('DOMContentLoaded', () => {
        // Try to start music
        setTimeout(() => {
            try {
                MusicController.start();
            } catch(e) {}
        }, 500);

        // Handle autoplay policy — start on first user interaction if needed
        const resumeMusic = () => {
            try {
                if (!MusicController.playing) {
                    MusicController.start();
                }
            } catch(e) {}
            // Only remove listeners once player is ready (playback was attempted)
            if (MusicController._ready) {
                document.removeEventListener('click', resumeMusic);
                document.removeEventListener('touchstart', resumeMusic);
            }
        };
        document.addEventListener('click', resumeMusic);
        document.addEventListener('touchstart', resumeMusic);
    });
    </script>
</body>
</html>
