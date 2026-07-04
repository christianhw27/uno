<?php
$title = "Room " . htmlspecialchars($room->room_id) . " - UNO Mobile";
ob_start();
?>
<div class="game-screen">
    <!-- Header -->
    <div class="game-header">
        <button onclick="exitRoom()" class="header-btn-back"><i class="fas fa-chevron-left"></i></button>
        <div class="header-room-info">
            <span class="room-label">ROOM</span>
            <span class="room-code"><?php echo htmlspecialchars($room->room_id); ?></span>
        </div>
        <div class="header-status">
            <span id="status-badge" class="badge">Memuat...</span>
        </div>
    </div>

    <!-- 1. Lobby Panel -->
    <div id="lobby-panel" class="game-panel" style="display: none;">
        <div class="lobby-card">
            <h2>Lobi Game</h2>
            <p class="subtitle">Menunggu pembuat room memulai permainan...</p>
            
            <div class="player-list-container">
                <h3>Pemain Terhubung (<span id="lobby-player-count">0</span>/10)</h3>
                <div id="lobby-players" class="lobby-players-list">
                    <!-- Dynamic -->
                </div>
            </div>

            <div class="lobby-actions">
                <button id="btn-add-bot" class="btn btn-secondary btn-block" onclick="addBot()">
                    <i class="fas fa-robot"></i> Tambah Bot
                </button>
                <button id="btn-start-game" class="btn btn-primary btn-block" onclick="startGame()" style="display: none;">
                    <i class="fas fa-play"></i> Mulai Game
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Game Board Panel -->
    <div id="board-panel" class="game-panel" style="display: none;">
        <!-- The Board is a full relative container -->
        <div class="board-arena">

            <!-- Opponents Container (circular layout) -->
            <div id="opponents-container" class="opponents-container"></div>

            <!-- Direction Ring (center of board) -->
            <div id="direction-indicator" class="direction-ring cw">
                <div class="dir-arrow"></div>
            </div>

            <!-- Discard Card (above draw pile) -->
            <div id="discard-card-container" class="discard-center"></div>

            <!-- Selected Color Dot -->
            <div id="wild-color-indicator" class="color-dot-wrap" style="display:none;">
                <div id="wild-color-dot" class="color-dot"></div>
            </div>

            <!-- Draw Pile (left of center) -->
            <div class="draw-pile-float" onclick="drawCard()">
                <div class="draw-stack">
                    <div class="draw-back"><span>UNO</span></div>
                </div>
                <div class="draw-count" id="deck-count">0</div>
                <span class="draw-label" id="draw-label">AMBIL</span>
            </div>

            <!-- UNO Button (right side floating) -->
            <button id="btn-uno" class="uno-fab" onclick="declareUno()">
                <img src="uno_card/UNO_Logo.webp" alt="UNO" class="uno-fab-img">
            </button>

            <!-- Pass Button (above draw pile, left side) -->
            <button id="btn-pass" class="pass-btn-deck" onclick="passTurn()" style="display:none;">
                <i class="fas fa-forward"></i> LEWATI
            </button>

            <!-- Music Toggle -->
            <button id="btn-music" class="music-fab" onclick="MusicController.toggle()" title="Musik Latar">
                <i class="fas fa-music"></i>
            </button>

        </div><!-- .board-arena -->

        <!-- Turn Banner -->
        <div class="center-actions-area">
            <div id="turn-banner" class="turn-banner"></div>
        </div>

        <!-- Player Bottom Area: hand cards only (POV) -->
        <div class="player-bottom">
            <!-- Combo Confirm Bar (hidden by default) -->
            <div id="combo-bar" class="combo-confirm-bar" style="display:none;">
                <button class="combo-cancel" onclick="cancelCombo()">✕ Batal</button>
                <button class="combo-btn" onclick="confirmCombo()">
                    MAIN <span class="combo-count-badge" id="combo-count">0</span>
                </button>
            </div>

            <!-- Hand Cards (the big fanning area) -->
            <div class="sort-bar" id="sort-bar" style="display:none;">
                <button class="sort-btn active" data-sort="none" onclick="setHandSort('none')">Asli</button>
                <button class="sort-btn" data-sort="number" onclick="setHandSort('number')"># Angka</button>
                <button class="sort-btn" data-sort="color" onclick="setHandSort('color')">🎨 Warna</button>
            </div>
            <div class="hand-tray" id="my-cards-container">
                <!-- Dynamic Player Cards -->
            </div>
        </div>
    </div>

    <!-- 3. Finished Screen -->
    <div id="finished-panel" class="game-panel" style="display: none;">
        <div class="victory-card">
            <div class="trophy-glow">
                <i class="fas fa-trophy"></i>
            </div>
            <h2 id="winner-name">Pemenang!</h2>
            <p>Permainan telah selesai.</p>
            <button class="btn btn-primary btn-block" onclick="exitRoom()">
                Kembali ke Menu Utama
            </button>
            <button id="btn-reset-lobby" class="btn btn-secondary btn-block" onclick="resetToLobby()" style="display: none; margin-top: 10px;">
                <i class="fas fa-undo"></i> Kembali ke Lobi
            </button>
            <p id="finished-lobby-status" style="display: none; margin-top: 15px; font-size: 0.85rem; color: var(--text-secondary);">
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

    <!-- Spin Overlay (starting player selection) -->
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

    <!-- Color Selection Modal -->
    <div id="color-modal" class="modal-overlay" style="display: none;">
        <div class="modal-card">
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
<script src="assets/js/game.js"></script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
