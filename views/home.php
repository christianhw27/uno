<?php
$title = "UNO Mobile - Main Menu";
ob_start();
?>
<div class="lobby-screen">
    <!-- Settings Gear Button (top-right) -->
    <button class="home-settings-btn" onclick="openHomeSettings()" title="Pengaturan">
        <i class="fas fa-cog"></i>
    </button>

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
        <p>PHP MVC &bull; Vanilla CSS &bull; Local JSON Database</p>
    </div>
</div><!-- /.lobby-content -->
</div><!-- /.lobby-screen -->

<!-- Home Settings Modal -->
<div id="home-settings-modal" class="modal-overlay" style="display: none;">
    <div class="modal-card settings-card">
        <div class="settings-header">
            <h3><i class="fas fa-cog"></i> Pengaturan</h3>
            <button class="settings-close-btn" onclick="closeHomeSettings()"><i class="fas fa-times"></i></button>
        </div>
        <div class="settings-body">
            <div class="setting-row">
                <span><i class="fas fa-music"></i> Musik</span>
                <label class="toggle-switch">
                    <input type="checkbox" id="home-music-toggle" checked onchange="toggleHomeMusicSetting(this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="setting-row">
                <span><i class="fas fa-volume-up"></i> Volume Musik</span>
                <input type="range" class="setting-slider" id="home-music-volume" min="0" max="100" value="50" oninput="setHomeMusicVolume(this.value)">
            </div>
            <div class="setting-row">
                <span><i class="fas fa-volume-off"></i> Volume Efek</span>
                <input type="range" class="setting-slider" id="home-sfx-volume" min="0" max="100" value="70" oninput="setHomeSfxVolume(this.value)">
            </div>
            <div class="setting-row">
                <span><i class="fas fa-tv"></i> Intensitas CRT</span>
                <input type="range" class="setting-slider" id="home-crt-intensity" min="0" max="100" value="50" oninput="setHomeCrtIntensity(this.value)">
            </div>
        </div>
    </div>
</div>

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

/* ========== HOME SETTINGS ========== */
const _homeSettings = {
    musicEnabled: true,
    musicVolume: 50,
    sfxVolume: 70,
    crtIntensity: 50
};

function openHomeSettings() {
    // Sync from MusicController actual state
    if (typeof MusicController !== 'undefined') {
        _homeSettings.musicEnabled = MusicController.playing;
    }
    document.getElementById('home-music-toggle').checked = _homeSettings.musicEnabled;
    document.getElementById('home-music-volume').value = _homeSettings.musicVolume;
    document.getElementById('home-sfx-volume').value = _homeSettings.sfxVolume;
    document.getElementById('home-crt-intensity').value = _homeSettings.crtIntensity;
    document.getElementById('home-settings-modal').style.display = 'flex';
}
function closeHomeSettings() {
    document.getElementById('home-settings-modal').style.display = 'none';
}

function toggleHomeMusicSetting(on) {
    _homeSettings.musicEnabled = on;
    if (typeof MusicController !== 'undefined') {
        if (on) MusicController.start();
        else MusicController.stop();
    }
}
function setHomeMusicVolume(val) {
    _homeSettings.musicVolume = parseInt(val);
    if (typeof MusicController !== 'undefined' && MusicController.setVolume) {
        MusicController.setVolume(val);
    }
}
function setHomeSfxVolume(val) {
    _homeSettings.sfxVolume = parseInt(val);
}
function setHomeCrtIntensity(val) {
    _homeSettings.crtIntensity = parseInt(val);
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
