<?php
$title = "Room " . htmlspecialchars($room->room_id) . " - UNO Mobile";
ob_start();
?>
<div class="game-screen">

    <!-- Cyber background effects -->
    <div class="cyber-bg-grid"></div>
    <div class="cyber-scanline"></div>

    <!-- Neon card background decorations -->
    <div class="bg-cards-neon">
        <div class="bg-card bg-card-1"><span class="bg-card-inner">7</span></div>
        <div class="bg-card bg-card-2"><span class="bg-card-inner">+2</span></div>
        <div class="bg-card bg-card-3"><span class="bg-card-inner">S</span></div>
        <div class="bg-card bg-card-4"><span class="bg-card-inner">R</span></div>
        <div class="bg-card bg-card-5"><span class="bg-card-inner">4</span></div>
        <div class="bg-card bg-card-6"><span class="bg-card-inner">Ø</span></div>
        <div class="bg-card bg-card-7"><span class="bg-card-inner">+4</span></div>
        <div class="bg-card bg-card-8"><span class="bg-card-inner">3</span></div>
    </div>
    <div class="bg-float-dots">
        <div class="fg-dot"></div>
        <div class="fg-dot"></div>
        <div class="fg-dot"></div>
        <div class="fg-dot"></div>
    </div>

    <!-- Header -->
    <header class="cyber-header">
        <button onclick="exitRoom()" class="cyber-header-back">
            <i class="fas fa-chevron-left"></i>
        </button>
        <div class="cyber-room-title-wrap">
            <h1 class="cyber-room-title">ROOM: <?php echo htmlspecialchars($room->room_id); ?></h1>
            <span id="status-badge" class="cyber-status-badge">Memuat...</span>
        </div>
        <button class="cyber-settings-btn" onclick="openSettings()">
            <i class="fas fa-cog"></i>
        </button>
    </header>

    <!-- 1. Lobby Panel -->
    <div id="lobby-panel" class="game-panel" style="display: none;">
        <div class="lobby-card cyber-lobby-card">
            <div class="cyber-lobby-header">
                <h2>Lobi Game</h2>
                <p class="subtitle">Menunggu pembuat room memulai permainan...</p>
            </div>

            <div class="player-list-container cyber-player-list">
                <h3>Pemain Terhubung (<span id="lobby-player-count">0</span>/10)</h3>
                <div id="lobby-players" class="lobby-players-list">
                    <!-- Dynamic -->
                </div>
            </div>

            <div class="lobby-actions">
                <button id="btn-add-bot" class="btn btn-secondary btn-block cyber-btn-secondary" onclick="addBot()">
                    <i class="fas fa-robot"></i> Tambah Bot
                </button>
                <button id="btn-start-game" class="btn btn-primary btn-block cyber-btn-primary" onclick="startGame()" style="display: none;">
                    <i class="fas fa-play"></i> Mulai Game
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Game Board Panel -->
    <div id="board-panel" class="game-panel" style="display: none;">
        <div class="board-arena cyber-board">
            <!-- Direction indicator ring -->
            <div id="direction-indicator" class="direction-ring cw"></div>

            <!-- Opponents radial ring -->
            <div class="avatar-ring" id="opponents-ring"></div>

            <!-- Center Arena (Discard & Draw) -->
            <div class="center-arena">
                <!-- Active / discard card -->
                <div id="discard-card-container" class="active-card-container"></div>

                <!-- Draw pile -->
                <div class="draw-pile-wrap">
                    <div class="draw-pile" id="draw-pile" onclick="drawCard()">
                        <div class="draw-pile-stack"></div>
                        <div class="draw-pile-stack"></div>
                        <div class="draw-pile-stack">
                            <span class="uno-logo-text">UNO</span>
                            <div class="draw-count" id="deck-count">0</div>
                        </div>
                    </div>
                    <button class="draw-btn" onclick="drawCard()">DRAW</button>
                    <button id="btn-pass" class="pass-btn-under-draw" onclick="passTurn()" style="display:none;">
                        <i class="fas fa-forward"></i> LEWATI
                    </button>
                </div>
            </div>

            <!-- UNO Button -->
            <button id="btn-uno" class="uno-fab cyber-uno-fab" onclick="declareUno()">
                <img src="uno_card/UNO_Logo.webp" alt="UNO" class="uno-fab-img">
            </button>


        </div>

        <!-- Turn Banner -->
        <div class="turn-banner-area">
            <div id="turn-banner" class="turn-banner"></div>
        </div>

        <!-- Player Bottom Area -->
        <div class="player-bottom cyber-player-bottom">
            <!-- Combo Confirm Bar -->
            <div id="combo-bar" class="combo-confirm-bar" style="display:none;">
                <button class="combo-cancel cyber-pill" onclick="cancelCombo()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button class="combo-btn cyber-gradient-btn" onclick="confirmCombo()">
                    MAIN <span class="combo-count-badge" id="combo-count">0</span>
                </button>
            </div>

            <!-- Sort / filter pills -->
            <div class="sort-bar cyber-sort-bar" id="sort-bar" style="display:none;">
                <button class="sort-btn active" data-sort="none" onclick="setHandSort('none')">Semua</button>
                <button class="sort-btn" data-sort="number" onclick="setHandSort('number')"># Angka</button>
                <button class="sort-btn" data-sort="color" onclick="setHandSort('color')">🎨 Warna</button>
            </div>

            <!-- Hand cards -->
            <div class="hand-tray cyber-hand-tray" id="my-cards-container"></div>
        </div>
    </div>

    <!-- 3. Finished Screen -->
    <div id="finished-panel" class="game-panel" style="display: none;">
        <div class="victory-card cyber-victory-card">
            <div class="trophy-glow">
                <i class="fas fa-trophy"></i>
            </div>
            <h2 id="winner-name">Pemenang!</h2>
            <p>Permainan telah selesai.</p>
            <button class="btn btn-primary btn-block cyber-btn-primary" onclick="exitRoom()">
                Kembali ke Menu Utama
            </button>
            <button id="btn-reset-lobby" class="btn btn-secondary btn-block cyber-btn-secondary" onclick="resetToLobby()" style="display: none; margin-top: 10px;">
                <i class="fas fa-undo"></i> Kembali ke Lobi
            </button>
            <p id="finished-lobby-status" style="display: none; margin-top: 15px; font-size: 0.85rem; color: var(--cyber-text-secondary);">
                Menunggu pembuat room kembali ke lobi...
            </p>
        </div>
    </div>

    <!-- Effect Overlay Animation -->
    <div id="effect-overlay" class="effect-overlay" style="display: none;">
        <div class="effect-content">
            <div id="effect-icon" class="effect-icon"></div>
            <div id="effect-text" class="effect-text"></div>
        </div>
    </div>

    <!-- Spin Overlay -->
    <div id="spin-overlay" class="spin-overlay" style="display: none;">
        <div class="spin-container">
            <div class="spin-title">⚡ Pemain Pertama ⚡</div>
            <div id="spin-display" class="spin-display">
                <div class="spin-avatar">?</div>
                <div class="spin-name">Memilih...</div>
            </div>
        </div>
    </div>

    <!-- Countdown Overlay -->
    <div id="countdown-overlay" class="countdown-overlay" style="display: none;">
        <div id="countdown-number" class="countdown-number"></div>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="modal-overlay" style="display: none;">
        <div class="modal-card settings-card">
            <div class="settings-header">
                <h3><i class="fas fa-cog"></i> Pengaturan</h3>
                <button class="settings-close-btn" onclick="closeSettings()"><i class="fas fa-times"></i></button>
            </div>
            <div class="settings-body">
                <div class="setting-row">
                    <span><i class="fas fa-music"></i> Musik</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="setting-music-toggle" checked onchange="toggleMusicSetting(this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <span><i class="fas fa-volume-up"></i> Volume Musik</span>
                    <input type="range" class="setting-slider" id="setting-music-volume" min="0" max="100" value="50" oninput="setMusicVolume(this.value)">
                </div>
                <div class="setting-row">
                    <span><i class="fas fa-volume-off"></i> Volume Efek</span>
                    <input type="range" class="setting-slider" id="setting-sfx-volume" min="0" max="100" value="70" oninput="setSfxVolume(this.value)">
                </div>
                <div class="setting-row">
                    <span><i class="fas fa-tv"></i> Intensitas CRT</span>
                    <input type="range" class="setting-slider" id="setting-crt-intensity" min="0" max="100" value="50" oninput="setCrtIntensity(this.value)">
                </div>
                <hr class="settings-divider">
                <button class="btn settings-leave-btn" onclick="confirmLeave()">
                    <i class="fas fa-sign-out-alt"></i> Keluar Room
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm Dialog Modal -->
    <div id="confirm-modal" class="modal-overlay" style="display: none;">
        <div class="modal-card confirm-card">
            <div class="confirm-icon-wrap">
                <div class="confirm-icon"><i class="fas fa-sign-out-alt"></i></div>
            </div>
            <h3 id="confirm-title" class="confirm-title">Keluar Room</h3>
            <p id="confirm-message" class="confirm-message">Apakah kamu yakin ingin meninggalkan room ini?</p>
            <div class="confirm-actions">
                <button class="btn confirm-btn confirm-btn-cancel" onclick="confirmAnswer(false)">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button class="btn confirm-btn confirm-btn-ok" onclick="confirmAnswer(true)">
                    <i class="fas fa-check"></i> Ya, Keluar
                </button>
            </div>
        </div>
    </div>

    <!-- Color Selection Modal -->
    <div id="color-modal" class="modal-overlay" style="display: none;">
        <div class="modal-card cyber-modal-card">
            <h3>Pilih Warna</h3>
            <div class="color-grid">
                <button class="color-choice-btn red" onclick="selectColor('red')"><i class="fas fa-circle"></i></button>
                <button class="color-choice-btn blue" onclick="selectColor('blue')"><i class="fas fa-circle"></i></button>
                <button class="color-choice-btn green" onclick="selectColor('green')"><i class="fas fa-circle"></i></button>
                <button class="color-choice-btn yellow" onclick="selectColor('yellow')"><i class="fas fa-circle"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
    const ROOM_ID = '<?php echo $room->room_id; ?>';
    const PLAYER_ID = '<?php echo $playerId; ?>';
    const CREATOR_ID = '<?php echo $room->creator_id; ?>';
</script>
<script src="assets/js/game.js?v=8"></script>

<?php
$musicVideoId = '-scpRk2xZcI';
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
