<?php
$title = "UNO Mobile - Main Menu";
ob_start();
?>
<div class="lobby-screen">
    <!-- Background Card Decorations -->
    <div class="bg-decorations">
        <div class="bg-suit spade">♠</div>
        <div class="bg-suit heart">♥</div>
        <div class="bg-suit diamond">♦</div>
        <div class="bg-suit club">♣</div>
        <div class="bg-card-shape card-big"></div>
        <div class="bg-card-shape card-small"></div>
    </div>

    <div class="lobby-content">
        <div class="logo-area">
        <div class="uno-logo-glow"></div>
        <img src="uno_card/UNO_Logo.webp" alt="UNO" class="logo-img">
        <p class="tagline">Bermain UNO dengan teman dan bot cerdas</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="tabs-container">
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab('create')">
                <i class="fas fa-plus-circle"></i> Buat Room
            </button>
            <button class="tab-btn" onclick="switchTab('join')">
                <i class="fas fa-sign-in-alt"></i> Gabung Room
            </button>
        </div>

        <!-- Create Room Tab -->
        <div id="create-tab" class="tab-content active">
            <form action="index.php?action=create_room" method="POST" autocomplete="off">
                <div class="input-group">
                    <label for="create_name"><i class="fas fa-user-ninja"></i> Nama Kamu</label>
                    <input type="text" id="create_name" name="player_name" placeholder="Nickname..." value="<?php echo htmlspecialchars($playerName); ?>" required maxlength="12">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    Mulai Room Baru <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>

        <!-- Join Room Tab -->
        <div id="join-tab" class="tab-content">
            <form action="index.php?action=join_room" method="POST" autocomplete="off">
                <div class="input-group">
                    <label for="join_name"><i class="fas fa-user-ninja"></i> Nama Kamu</label>
                    <input type="text" id="join_name" name="player_name" placeholder="Nickname..." value="<?php echo htmlspecialchars($playerName); ?>" required maxlength="12">
                </div>
                <div class="input-group">
                    <label for="room_id"><i class="fas fa-key"></i> Kode Room</label>
                    <input type="text" id="room_id" name="room_id" placeholder="Kode 5 Digit..." style="text-transform: uppercase;" required maxlength="5">
                </div>
                <button type="submit" class="btn btn-secondary btn-block">
                    Gabung Game <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="lobby-footer">
        <button id="btn-music-home" class="music-toggle-home" onclick="toggleHomeMusic()" title="Musik Latar">
            <i class="fas fa-music"></i>
        </button>
        <p>PHP MVC &bull; Vanilla CSS &bull; Local JSON Database</p>
    </div>
</div><!-- /.lobby-content -->
</div><!-- /.lobby-screen -->

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    if (tab === 'create') {
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
        document.getElementById('create-tab').classList.add('active');
        setTimeout(() => document.getElementById('create_name').focus(), 100);
    } else {
        document.querySelectorAll('.tab-btn')[1].classList.add('active');
        document.getElementById('join-tab').classList.add('active');
        setTimeout(() => document.getElementById('join_name').focus(), 100);
    }
}

function toggleHomeMusic() {
    if (typeof MusicController !== 'undefined') {
        const playing = MusicController.toggle();
        const btn = document.getElementById('btn-music-home');
        if (btn) {
            btn.classList.toggle('on', playing);
        }
    }
}

// Sync home music button state with MusicController
if (typeof MusicController !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('btn-music-home');
        if (btn && MusicController.playing) {
            btn.classList.add('on');
        }
    });
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
