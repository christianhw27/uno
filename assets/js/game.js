/* ========== AUDIO ========== */
const AudioController = {
    ctx: null,
    init() { if (!this.ctx) this.ctx = new (window.AudioContext || window.webkitAudioContext)(); },
    _vol() { return (Settings ? Settings.sfxVolume : 70) / 100; },
    _tone(type, freqStart, freqEnd, dur, vol) {
        this.init(); if (!this.ctx) return;
        const v = vol * this._vol();
        let o = this.ctx.createOscillator(), g = this.ctx.createGain();
        o.connect(g); g.connect(this.ctx.destination);
        o.type = type;
        o.frequency.setValueAtTime(freqStart, this.ctx.currentTime);
        o.frequency.exponentialRampToValueAtTime(freqEnd, this.ctx.currentTime + dur);
        g.gain.setValueAtTime(v, this.ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + dur);
        o.start(); o.stop(this.ctx.currentTime + dur);
    },
    _noise(dur, vol) {
        this.init(); if (!this.ctx) return;
        const v = vol * this._vol();
        const bufferSize = this.ctx.sampleRate * dur;
        const buffer = this.ctx.createBuffer(1, bufferSize, this.ctx.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < bufferSize; i++) data[i] = Math.random() * 2 - 1;
        const src = this.ctx.createBufferSource();
        src.buffer = buffer;
        const g = this.ctx.createGain();
        src.connect(g); g.connect(this.ctx.destination);
        g.gain.setValueAtTime(v, this.ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + dur);
        src.start();
    },
    playCard() {
        this._tone('square', 150, 1200, 0.1, 0.6);
        this._noise(0.08, 0.4);
    },
    drawCard() {
        this._tone('sawtooth', 500, 80, 0.2, 0.5);
        this._noise(0.1, 0.3);
    },
    uno() {
        this._tone('square', 800, 1200, 0.18, 0.6);
        setTimeout(() => this._tone('square', 1200, 800, 0.18, 0.6), 120);
    },
    turn() {
        this._tone('square', 400, 700, 0.15, 0.6);
        setTimeout(() => this._tone('square', 700, 1000, 0.15, 0.6), 150);
        setTimeout(() => this._tone('square', 1000, 1300, 0.25, 0.6), 300);
        this._noise(0.12, 0.35);
    },
    pass() {
        this._tone('sawtooth', 600, 150, 0.15, 0.4);
    },
    victory() {
        this.init(); if (!this.ctx) return;
        [440, 554.37, 659.25, 880].forEach((f, i) => {
            let o = this.ctx.createOscillator(), g = this.ctx.createGain();
            o.connect(g); g.connect(this.ctx.destination);
            o.type = 'square';
            o.frequency.setValueAtTime(f, this.ctx.currentTime + i * 0.1);
            g.gain.setValueAtTime(0.5, this.ctx.currentTime + i * 0.1);
            g.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + i * 0.1 + 0.35);
            o.start(this.ctx.currentTime + i * 0.1);
            o.stop(this.ctx.currentTime + i * 0.1 + 0.35);
        });
    },
    vibrate(pattern) {
        if (navigator.vibrate) navigator.vibrate(pattern);
    }
};

/* ========== STATE ========== */
let lastState = null;
let _renderHash = null;
let isPolling = false;
let pendingWildCardId = null;
let logsExpanded = false;
let isCountdownRunning = false;

// Combo selection state
let selectedCards = [];   // Array of {id, type, value, color, isWild}
let comboMode = false;

/* ========== API ========== */
async function apiCall(action, params = {}) {
    const opts = { method: Object.keys(params).length ? 'POST' : 'GET' };
    if (opts.method === 'POST') {
        opts.body = new URLSearchParams(params);
        opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
    }
    try {
        const r = await fetch(`index.php?action=${action}&room_id=${ROOM_ID}`, opts);
        return await r.json();
    } catch (e) { console.error(e); return { success: false }; }
}

/* ========== ACTIONS ========== */
/* ========== SETTINGS ========== */
const Settings = {
    musicEnabled: true,
    musicVolume: 50,
    sfxVolume: 70,
    crtIntensity: 50
};

function openSettings() {
    document.getElementById('settings-modal').style.display = 'flex';
    // If music should be playing but isn't (autoplay blocked), try from user gesture
    if (typeof MusicController !== 'undefined' && MusicController._ready && !MusicController.playing && MusicController._intended) {
        MusicController.start();
    }
    // Sync UI with current state
    document.getElementById('setting-music-toggle').checked = Settings.musicEnabled;
    document.getElementById('setting-music-volume').value = Settings.musicVolume;
    document.getElementById('setting-sfx-volume').value = Settings.sfxVolume;
    document.getElementById('setting-crt-intensity').value = Settings.crtIntensity;
}
function closeSettings() {
    document.getElementById('settings-modal').style.display = 'none';
}
function toggleMusicSetting(on) {
    Settings.musicEnabled = on;
    if (on) {
        MusicController.start();
    } else {
        MusicController.stop();
    }
}
function setMusicVolume(val) {
    Settings.musicVolume = parseInt(val);
    if (typeof MusicController !== 'undefined' && MusicController.setVolume) {
        MusicController.setVolume(val);
    }
}
function setSfxVolume(val) {
    Settings.sfxVolume = parseInt(val);
}
function setCrtIntensity(val) {
    Settings.crtIntensity = parseInt(val);
    applyCrtIntensity();
}
function applyCrtIntensity() {
    const el = document.querySelector('.game-screen');
    if (el) {
        el.style.setProperty('--crt-intensity', Settings.crtIntensity / 100);
    }
}

/* ========== CONFIRM DIALOG ========== */
let _confirmResolve = null;

function showConfirm(title, message) {
    return new Promise((resolve) => {
        document.getElementById('confirm-title').textContent = title;
        document.getElementById('confirm-message').textContent = message;
        document.getElementById('confirm-modal').style.display = 'flex';
        _confirmResolve = resolve;
    });
}

function confirmAnswer(answer) {
    document.getElementById('confirm-modal').style.display = 'none';
    if (_confirmResolve) {
        _confirmResolve(answer);
        _confirmResolve = null;
    }
}

function confirmLeave() {
    closeSettings();
    showConfirm('Keluar Room', 'Apakah kamu yakin ingin meninggalkan room ini?').then(ok => {
        if (!ok) return;
        if (lastState && lastState.status === 'playing') {
            resetToLobby();
        } else {
            window.location.href = 'index.php';
        }
    });
}

function exitRoom() {
    showConfirm('Keluar Room', 'Apakah kamu yakin ingin meninggalkan room ini?').then(ok => {
        if (!ok) return;
        if (lastState && lastState.status === 'playing') {
            resetToLobby();
        } else {
            window.location.href = 'index.php';
        }
    });
}
async function addBot()    { await apiCall('add_bot');    forceRefresh(); }
async function removeBot(id) { await apiCall('remove_bot', { target_id: id }); forceRefresh(); }
let _cardCount = 7;
let _handSort = 'none';
function adjustCardCount(delta) {
    _cardCount = Math.max(1, Math.min(15, _cardCount + delta));
    const el = document.getElementById('card-count-display');
    if (el) el.textContent = _cardCount;
}
function setHandSort(mode) {
    _handSort = mode;
    document.querySelectorAll('.sort-btn').forEach(b => b.classList.toggle('active', b.dataset.sort === mode));
    forceRefresh();
}
async function startGame() {
    const count = (parseInt(document.getElementById('card-count-display')?.textContent) || 7);
    await apiCall('start_game', { card_count: count });
    forceRefresh();
}
async function resetToLobby() { await apiCall('reset_to_lobby'); forceRefresh(); }
async function drawCard() {
    if (!lastState) return;
    const me = lastState.players[lastState.current_turn];
    if (!me || me.id !== PLAYER_ID) return;
    cancelCombo();
    const r = await apiCall('draw_card');
    if (r.success) AudioController.drawCard();
    forceRefresh();
}
async function passTurn()    { cancelCombo(); const r = await apiCall('pass_turn'); if (r.success) AudioController.pass(); forceRefresh(); }
async function declareUno()  { const r = await apiCall('declare_uno'); if (r.success) AudioController.uno(); forceRefresh(); }
async function challenge(id) { await apiCall('challenge', { target_id: id }); forceRefresh(); }

/* ---- Card Selection (combo-aware) ---- */
function selectCard(cardId, isPlayable, isWild, cardType, cardValue, cardColor) {
    if (!isPlayable && !comboMode) return;

    // If in combo mode, handle adding/removing from combo
    if (comboMode) {
        const idx = selectedCards.findIndex(c => c.id === cardId);
        if (idx >= 0) {
            // Deselect this card
            selectedCards.splice(idx, 1);
            if (selectedCards.length === 0) {
                cancelCombo();
            }
            updateComboUI();
            return;
        }
        // Check if this card matches the combo (same type+value)
        const first = selectedCards[0];
        if (!first.isWild && cardType === first.type && (cardType !== 'number' || cardValue === first.value)) {
            selectedCards.push({ id: cardId, type: cardType, value: cardValue, color: cardColor, isWild });
            updateComboUI();
            return;
        }
        // Doesn't match combo — ignore
        return;
    }

    // Start selection / combo mode (includes wild cards now)
    selectedCards = [{ id: cardId, type: cardType, value: cardValue, color: cardColor, isWild }];
    comboMode = true;
    updateComboUI();
}

function cancelCombo() {
    selectedCards = [];
    comboMode = false;
    document.getElementById('combo-bar').style.display = 'none';
    // Remove visual classes
    document.querySelectorAll('.hand-card-wrap.selected, .hand-card-wrap.combo-able').forEach(el => {
        el.classList.remove('selected', 'combo-able');
    });
}

function updateComboUI() {
    // Update visual states on cards
    document.querySelectorAll('.hand-card-wrap').forEach(el => {
        const cid = el.dataset.cardId;
        const ctype = el.dataset.cardType;
        const cval = el.dataset.cardValue;

        el.classList.remove('selected', 'combo-able');

        if (selectedCards.find(s => s.id === cid)) {
            el.classList.add('selected');
        } else if (comboMode && selectedCards.length > 0) {
            const first = selectedCards[0];
            if (ctype === first.type && (ctype !== 'number' || cval === String(first.value)) && el.dataset.cardColor !== 'wild') {
                el.classList.add('combo-able');
            }
        }
    });

    // Show/hide combo bar
    const bar = document.getElementById('combo-bar');
    if (selectedCards.length > 0) {
        bar.style.display = 'flex';
        document.getElementById('combo-count').innerText = selectedCards.length;
    } else {
        bar.style.display = 'none';
    }
}

async function confirmCombo() {
    if (selectedCards.length === 0) return;

    // If any selected card is wild, show color picker first
    if (selectedCards.some(c => c.isWild)) {
        pendingWildCardId = selectedCards.map(c => c.id).join(',');
        cancelCombo();
        document.getElementById('color-modal').style.display = 'flex';
        return;
    }

    const ids = selectedCards.map(c => c.id).join(',');
    cancelCombo();
    const r = await apiCall('play_card', { card_ids: ids });
    if (r.success) AudioController.playCard();
    forceRefresh();
}

function selectColor(color) {
    document.getElementById('color-modal').style.display = 'none';
    if (pendingWildCardId) {
        playCardReq(pendingWildCardId, color);
        pendingWildCardId = null;
    }
}
async function playCardReq(cardId, color) {
    const p = { card_ids: cardId };
    if (color) p.chosen_color = color;
    const r = await apiCall('play_card', p);
    if (r.success) AudioController.playCard();
    forceRefresh();
}

/* ========== POLLING ========== */
async function pollStatus() {
    if (isPolling) return;
    isPolling = true;
    try {
        const r = await (await fetch(`index.php?action=game_status&room_id=${ROOM_ID}`)).json();
        if (r.success) renderGame(r.data);
    } catch (e) { console.error(e); }
    isPolling = false;
}

/** Bypass the isPolling lock — used immediately after a user action */
async function forceRefresh() {
    try {
        const r = await (await fetch(`index.php?action=game_status&room_id=${ROOM_ID}`)).json();
        if (r.success) renderGame(r.data);
    } catch (e) { console.error(e); }
}

/* ---- Radial opponent position helper ---- */
function getRadialPositions(count) {
    const positions = [];
    if (count <= 0) return positions;
    let startAngle, endAngle, radius;
    // All opponents positioned in the UPPER arc (sin positive -> y < 50% = top half)
    // 0° = right, 90° = top, 180° = left
    let yOffset = 0;
    if (count <= 4) {
        startAngle = 25; endAngle = 155; radius = 46; yOffset = 2;
    } else if (count <= 6) {
        startAngle = 15; endAngle = 165; radius = 48; yOffset = 3;
    } else if (count <= 8) {
        startAngle = 5; endAngle = 175; radius = 46; yOffset = 4;
    } else {
        startAngle = 0; endAngle = 180; radius = 44; yOffset = 5;
    }
    const span = endAngle - startAngle;
    for (let i = 0; i < count; i++) {
        const angleDeg = count === 1 ? 90 : startAngle + (i * span / (count - 1));
        const angleRad = angleDeg * Math.PI / 180;
        // Standard math angles: 0=right, 90=top, 180=left, 270=bottom
        const x = 50 + radius * Math.cos(angleRad);
        const y = 50 - radius * Math.sin(angleRad) + yOffset;
        positions.push({ left: `${x}%`, top: `${y}%` });
    }
    return positions;
}

/* ========== RENDER ========== */
function renderGame(state) {
    if (isCountdownRunning) {
        document.getElementById('lobby-panel').style.display = 'none';
        document.getElementById('board-panel').style.display = 'flex';
        document.getElementById('finished-panel').style.display = 'none';
        return;
    }

    // Skip full DOM rebuild if state unchanged (huge mobile perf win)
    const _h = JSON.stringify(state);
    if (_h === _renderHash) { lastState = state; return; }
    _renderHash = _h;

    if (lastState && lastState.status === 'lobby' && state.status === 'playing') {
        MusicController.start();
        isCountdownRunning = true;
        lastState = state;
        runSpinAnimation(() => {
            isCountdownRunning = false;
            renderGame(state);
        });
        return;
    }

    // --- Sound triggers & Special Animations ---
    if (lastState && lastState.status === 'playing') {
        if (state.status === 'finished') {
            AudioController.victory();
            MusicController.stop();
        }
        else if (state.status === 'playing') {
            // Card played / drawn sounds
            const pTop = lastState.discard_card?.id, cTop = state.discard_card?.id;
            if (cTop && cTop !== pTop) {
                AudioController.playCard();
                const penaltyDelta = (state.accumulated_draw_penalty || 0) - (lastState.accumulated_draw_penalty || 0);
                triggerEffectAnimation(state.discard_card, state.selected_color, penaltyDelta);
            } else if (state.deck_count < lastState.deck_count) {
                AudioController.drawCard();
            }
            // UNO declaration
            state.players.forEach(p => {
                const prev = lastState.players.find(lp => lp.id === p.id);
                if (prev && p.called_uno && !prev.called_uno) AudioController.uno();
            });
            // Turn change → my turn: sound + vibration
            const prevActive = lastState.players[lastState.current_turn];
            const curActive = state.players[state.current_turn];
            if (curActive && prevActive && curActive.id !== prevActive.id && curActive.id === PLAYER_ID) {
                AudioController.turn();
                AudioController.vibrate([150, 80, 150]);
            }
        }
    }
    lastState = state;

    // --- Badge ---
    const sb = document.getElementById('status-badge');
    if (state.status === 'lobby')    { sb.innerText = 'Lobi';    sb.className = 'cyber-status-badge badge-lobby'; }
    else if (state.status === 'playing') { sb.innerText = 'Bermain'; sb.className = 'cyber-status-badge badge-playing'; }
    else { sb.innerText = 'Selesai'; sb.className = 'cyber-status-badge'; }

    // --- Panel toggles ---
    document.getElementById('lobby-panel').style.display   = state.status === 'lobby'    ? 'flex' : 'none';
    document.getElementById('board-panel').style.display    = state.status === 'playing'  ? 'flex' : 'none';
    document.getElementById('finished-panel').style.display = state.status === 'finished' ? 'flex' : 'none';
    document.getElementById('sort-bar').style.display       = state.status === 'playing'  ? 'flex' : 'none';


    // === LOBBY ===
    if (state.status === 'lobby') {
        document.getElementById('lobby-player-count').innerText = state.players.length;
        document.getElementById('btn-start-game').style.display = PLAYER_ID === state.creator_id ? 'block' : 'none';
        const isCreator = PLAYER_ID === state.creator_id;
        const pl = document.getElementById('lobby-players');
        pl.innerHTML = '';
        state.players.forEach(p => {
            const isMe = p.id === PLAYER_ID;
            let init = p.is_bot ? '🤖' : (p.name ? p.name.charAt(0).toUpperCase() : '?');
            pl.innerHTML += `
                <div class="lobby-player-row ${isMe?'is-me':''} ${p.is_bot?'is-bot':''}">
                    <div class="player-identity">
                        <div class="player-avatar">${init}</div>
                        <span class="player-name">${p.name} ${isMe?'(Kamu)':''}</span>
                    </div>
                    <div>
                        ${p.id === state.creator_id ? '<i class="fas fa-crown" style="color:#eab308;"></i>' : ''}
                        ${p.is_bot && isCreator ? `<button class="btn-remove-bot" onclick="removeBot('${p.id}')"><i class="fas fa-times"></i></button>` : ''}
                    </div>
                </div>`;
        });
        // Card count selector (creator only)
        const actionsDiv = document.querySelector('.lobby-actions');
        if (isCreator) {
            if (!document.getElementById('card-count-wrap')) {
                _cardCount = state.initial_card_count || 7;
                const wrap = document.createElement('div');
                wrap.id = 'card-count-wrap';
                wrap.style.cssText = 'display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:10px;font-size:0.85rem;';
                wrap.innerHTML = `
                    <label style="color:var(--text-secondary);font-weight:600;">Kartu per pemain:</label>
                    <button class="btn btn-secondary" style="padding:4px 12px;font-size:1rem;" onclick="adjustCardCount(-1)">−</button>
                    <span id="card-count-display" style="font-weight:800;font-size:1.2rem;min-width:30px;text-align:center;">${state.initial_card_count || 7}</span>
                    <button class="btn btn-secondary" style="padding:4px 12px;font-size:1rem;" onclick="adjustCardCount(1)">+</button>
                `;
                document.querySelector('.lobby-actions').before(wrap);
            } else {
                document.getElementById('card-count-display').textContent = _cardCount;
            }
        }
    }

    // === PLAYING ===
    if (state.status === 'playing') {
        const active = state.players[state.current_turn];
        const isMyTurn = active.id === PLAYER_ID;

        // Turn Banner
        const tb = document.getElementById('turn-banner');
        if (isMyTurn) { tb.innerText = 'GILIRANMU!'; tb.className = 'turn-banner my-turn'; }
        else { tb.innerText = `Giliran: ${active.name}`; tb.className = 'turn-banner other-turn'; }

        // Direction
        document.getElementById('direction-indicator').className =
            `direction-ring ${state.direction === 1 ? 'cw' : 'ccw'}`;

        // --- Opponents: radial avatar ring layout ---
        const opponents = state.players.filter(p => p.id !== PLAYER_ID);
        const ring = document.getElementById('opponents-ring');
        ring.innerHTML = '';

        const positions = getRadialPositions(opponents.length);

        opponents.forEach((p, i) => {
            const isActive = active.id === p.id;
            const showChallenge = p.card_count === 1 && !p.called_uno;
            const init = p.is_bot ? '🤖' : (p.name ? p.name.charAt(0).toUpperCase() : '?');
            const pos = positions[i] || { left: '50%', top: '10%' };
            const isUno = p.card_count === 1;

            // Anonymous mini card backs fan (do not reveal actual colors)
            const miniCount = Math.min(p.card_count, 6);
            let miniCards = '';
            for (let c = 0; c < miniCount; c++) {
                miniCards += `<div class="opp-minicard"></div>`;
            }

            const widget = document.createElement('div');
            widget.className = 'player-avatar';
            widget.style.left = pos.left;
            widget.style.top = pos.top;
            widget.innerHTML = `
                ${miniCount > 0 ? `<div class="opp-cards-fan">${miniCards}</div>` : ''}
                <div class="avatar-img-container ${isActive ? 'avatar-active' : ''}">
                    <span class="opp-avatar-icon">${init}</span>
                    ${isUno ? '<div class="opp-badge-alert">!</div>' : ''}
                    ${p.called_uno ? '<div class="opp-uno-badge">UNO</div>' : ''}
                    <div class="card-count-badge">${p.card_count}</div>
                </div>
                <span class="opp-nametag ${isUno ? 'uno-alert' : ''}">${isUno ? 'UNO!' : p.name}</span>
                ${showChallenge ? `<button class="opp-challenge-btn" onclick="challenge('${p.id}')">TANTANG!</button>` : ''}
            `;
            ring.appendChild(widget);
        });

        // --- Discard Card Stack (combo fan or single card) ---
        const dc = document.getElementById('discard-card-container');
        dc.innerHTML = '';

        // Use last_play_cards for combo/single; fallback to discard_recent top card
        let cardsToShow = [];
        if (state.last_play_cards && state.last_play_cards.length > 0) {
            cardsToShow = state.last_play_cards;
        } else if (state.discard_recent && state.discard_recent.length > 0) {
            cardsToShow = [state.discard_recent[state.discard_recent.length - 1]];
        }

        if (cardsToShow.length > 0) {
            const N = cardsToShow.length;
            const isCombo = N > 1;
            const totalSpread = isCombo ? (N - 1) * 12 : 0;
            const baseX = totalSpread / 2; // center the fan

            for (let i = 0; i < N; i++) {
                // newest card = last in array = front of fan
                const card = cardsToShow[N - 1 - i];
                const wrap = document.createElement('div');
                wrap.className = 'discard-stack-card';

                if (isCombo) {
                    const offX = baseX - i * 12;
                    const angle = -i * 3;
                    wrap.style.transform = `translateX(${offX}px) rotate(${angle}deg)`;
                    wrap.style.zIndex = N - i;
                } else {
                    wrap.style.zIndex = 1;
                }

                const img = document.createElement('img');
                img.className = 'uno-card-img discard-img';
                img.src = `uno_card/${card.image}`;
                img.alt = 'Card';

                // Front card gets full highlight
                if (i === 0) {
                    img.style.borderWidth = '3px';
                    img.style.boxShadow = '0 8px 25px rgba(0,0,0,0.7), 0 0 20px rgba(255,255,255,0.15)';
                    const glowColor = card.color === 'wild' ? (state.selected_color || 'wild') : card.color;
                    img.classList.add(`card-glow-${glowColor}`);
                    if (card.color === 'wild' && state.selected_color) {
                        img.classList.add('wild-selected');
                    }
                } else {
                    img.style.borderWidth = '2px';
                    img.style.borderColor = 'rgba(255,255,255,0.25)';
                    img.style.opacity = '0.85';
                }

                wrap.appendChild(img);
                dc.appendChild(wrap);
            }

            // Action indicator ring below the front card
            const topCard = cardsToShow[cardsToShow.length - 1];
            const colorMap = { red: '#FF4136', blue: '#0074D9', green: '#2ECC40', yellow: '#FFDC00', wild: '#ce5dff' };
            const activeColor = topCard.color === 'wild'
                ? (state.selected_color ? colorMap[state.selected_color] : '#ce5dff')
                : (colorMap[topCard.color] || '#FF4136');
            const indicator = document.createElement('div');
            indicator.className = 'action-indicator-ring';
            indicator.style.cssText = `background:${activeColor};box-shadow:0 0 20px ${activeColor},0 0 0 4px rgba(0,0,0,0.3)`;
            dc.appendChild(indicator);
        }

        // --- Deck count & draw pile ---
        document.getElementById('deck-count').innerText = state.deck_count;

        const drawWrap = document.querySelector('.draw-pile-wrap');
        const drawBtn = document.querySelector('.draw-btn');
        if (state.accumulated_draw_penalty > 0) {
            drawBtn.innerHTML = `DRAW <span style="color:#fff;font-weight:900;">+${state.accumulated_draw_penalty}</span>`;
            drawWrap.classList.add('pulse-penalty');
        } else {
            drawBtn.innerText = 'DRAW';
            drawWrap.classList.remove('pulse-penalty');
        }

        // --- Pass button ---
        document.getElementById('btn-pass').style.display = (isMyTurn && state.has_drawn) ? 'flex' : 'none';

        // --- UNO button: show when I have 2 cards (my turn) or when I have 1 card (any time to save myself) ---
        const myData = state.players.find(p => p.id === PLAYER_ID);
        const showUno = myData && myData.cards && (
            (isMyTurn && myData.cards.length === 2) || 
            (myData.cards.length === 1)
        ) && !myData.called_uno;
        document.getElementById('btn-uno').style.display = showUno ? 'flex' : 'none';

        // --- Hand cards ---
        const hc = document.getElementById('my-cards-container');
        const prevScrollLeft = hc.scrollLeft;
        hc.innerHTML = '';
        if (myData && myData.cards) {
            const N = myData.cards.length;
            // Calculate card overlap — switch to scrollable if cards get too cramped
            let overlap = 4;
            let scrollable = false;
            if (N > 1) {
                const containerWidth = Math.max(hc.clientWidth || 420, 240) - 16;
                const maxHandWidth = Math.min(containerWidth, 420);
                const cardWidth = 68;
                const minVisible = 55; // visible width per card before scrolling kicks in
                const neededWidth = N * cardWidth;
                if (neededWidth > maxHandWidth) {
                    overlap = (neededWidth - maxHandWidth) / (N - 1);
                    const visibleWidth = cardWidth - overlap;
                    if (visibleWidth < minVisible) {
                        // Too cramped — switch to scrollable mode with generous spacing
                        scrollable = true;
                        overlap = cardWidth - minVisible; // 23px overlap, 55px visible each
                    } else {
                        overlap = Math.min(overlap, cardWidth - minVisible);
                        overlap = Math.max(overlap, 4);
                    }
                } else {
                    overlap = Math.min(overlap, 2);
                }
            }

            // Apply scrollable mode
            if (scrollable) {
                hc.classList.add('scrollable');
                hc.style.paddingLeft = '14px';
            } else {
                hc.classList.remove('scrollable');
                hc.style.paddingLeft = '';
            }

            // Sort cards based on _handSort preference
            document.querySelectorAll('.sort-btn').forEach(b => b.classList.toggle('active', b.dataset.sort === _handSort));
            let sortedCards = [...myData.cards];
            if (_handSort !== 'none') {
                const typeOrder = { wild_4: 0, wild: 1, skip: 2, reverse: 3, draw_2: 4, number: 5 };
                const colorOrder = { red: 0, yellow: 1, green: 2, blue: 3 };
                sortedCards.sort((a, b) => {
                    const ta = typeOrder[a.type] ?? 99;
                    const tb = typeOrder[b.type] ?? 99;
                    if (ta !== tb) return ta - tb;
                    if (a.type === 'number') {
                        if (_handSort === 'number') return (a.value ?? 0) - (b.value ?? 0);
                        if (_handSort === 'color') return (colorOrder[a.color] ?? 99) - (colorOrder[b.color] ?? 99);
                    }
                    return 0;
                });
            }

            sortedCards.forEach((card, idx) => {
                let playable = false;
                if (isMyTurn) {
                    if (state.accumulated_draw_penalty > 0) {
                        playable = (card.type === 'draw_2' || card.type === 'wild_4');
                    } else if (card.color === 'wild') {
                        playable = true;
                    } else if (state.discard_card) {
                        const tc = (state.discard_card.color === 'wild' && state.selected_color)
                            ? state.selected_color : state.discard_card.color;
                        if (card.color === tc) playable = true;
                        else if (card.type === state.discard_card.type) {
                            playable = card.type === 'number' ? card.value === state.discard_card.value : true;
                        }
                    }
                }
                const isWild = card.color === 'wild';
                const w = document.createElement('div');
                w.className = `hand-card-wrap ${playable ? 'playable' : ''}`;
                
                // Apply the calculated overlap margin
                if (idx > 0) {
                    w.style.marginLeft = `-${overlap}px`;
                } else {
                    w.style.marginLeft = '0px';
                }

                // No fan rotation — cards stay straight for readability
                const maxAngle = 0;
                const maxYShift = 0;
                const rotateDeg = 0;
                const translateVal = 0;
                
                w.style.transform = `rotate(${rotateDeg.toFixed(1)}deg) translateY(${translateVal.toFixed(1)}px)`;
                w.style.transformOrigin = 'bottom center';

                // Add datasets to identify card parameters in updateComboUI()
                w.dataset.cardId = card.id;
                w.dataset.cardType = card.type;
                w.dataset.cardValue = card.value !== null ? card.value : '';
                w.dataset.cardColor = card.color;

                // Maintain selection classes across polls
                if (selectedCards.find(s => s.id === card.id)) {
                    w.classList.add('selected');
                } else if (comboMode && selectedCards.length > 0) {
                    const first = selectedCards[0];
                    if (card.type === first.type && (card.type !== 'number' || String(card.value) === String(first.value)) && card.color !== 'wild') {
                        w.classList.add('combo-able');
                    }
                }

                w.innerHTML = `<img src="uno_card/${card.image}" alt="${card.image}"
                    class="hand-card-img"
                    onclick="selectCard('${card.id}', ${playable}, ${isWild}, '${card.type}', '${card.value !== null ? card.value : ''}', '${card.color}')">`;
                hc.appendChild(w);
            });
        }
        // Restore scroll position — force sync layout so scrollWidth is accurate NOW
        if (prevScrollLeft > 0) {
            void hc.offsetHeight; // force reflow
            if (hc.scrollWidth > hc.clientWidth) {
                hc.scrollLeft = Math.min(prevScrollLeft, hc.scrollWidth - hc.clientWidth);
            }
        }
    }

    // === FINISHED ===
    if (state.status === 'finished') {
        const w = state.players.find(p => p.id === state.winner_id);
        document.getElementById('winner-name').innerText = `🏆 ${w ? w.name : '?'} MENANG!`;
        const isCreator = PLAYER_ID === state.creator_id;
        document.getElementById('btn-reset-lobby').style.display = isCreator ? 'block' : 'none';
        document.getElementById('finished-lobby-status').style.display = isCreator ? 'none' : 'block';
    }
    applyCrtIntensity();
}

function triggerEffectAnimation(card, selectedColor, penaltyDelta) {
    const overlay = document.getElementById('effect-overlay');
    const iconEl = document.getElementById('effect-icon');
    const textEl = document.getElementById('effect-text');
    
    if (!overlay || !iconEl || !textEl) return;
    
    let icon = '';
    let text = '';
    
    const colorMap = {
        red: '#ef4444',
        blue: '#3b82f6',
        green: '#10b981',
        yellow: '#eab308',
        wild: '#c084fc'
    };
    
    const activeColor = card.color === 'wild' ? selectedColor : card.color;
    const colorHex = colorMap[activeColor] || '#ffffff';

    if (card.type === 'skip') {
        icon = '🚫';
        text = 'SKIP!';
    } else if (card.type === 'reverse') {
        icon = '🔄';
        text = 'REVERSE!';
    } else if (card.type === 'draw_2') {
        icon = penaltyDelta > 2 ? `+${penaltyDelta}` : '＋２';
        text = penaltyDelta > 2 ? `DRAW ${penaltyDelta}!` : 'DRAW 2!';
    } else if (card.type === 'wild_4') {
        icon = penaltyDelta > 4 ? `+${penaltyDelta}` : '＋４';
        text = penaltyDelta > 4 ? `DRAW ${penaltyDelta}!` : 'WILD DRAW 4!';
    } else if (card.type === 'wild') {
        icon = '🎨';
        const indonesianColors = {
            red: 'MERAH',
            blue: 'BIRU',
            green: 'HIJAU',
            yellow: 'KUNING'
        };
        const colorName = indonesianColors[selectedColor] || (selectedColor ? selectedColor.toUpperCase() : '');
        text = colorName ? `WARNA: ${colorName}` : 'UBAH WARNA';
    } else {
        // No animation for standard number cards
        return;
    }
    
    iconEl.innerText = icon;
    iconEl.style.color = colorHex;
    textEl.innerText = text;
    textEl.style.color = '#ffffff';
    textEl.style.textShadow = `0 0 10px ${colorHex}, 0 0 20px ${colorHex}`;
    
    overlay.style.display = 'flex';
    overlay.style.animation = 'overlayFadeIn 0.25s ease-out forwards';
    
    // Hide after 1.5 seconds with fade out
    setTimeout(() => {
        overlay.style.animation = 'overlayFadeOut 0.3s ease-in forwards';
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 300);
    }, 1200);
}

function runCountdown(callback) {
    const overlay = document.getElementById('countdown-overlay');
    const numberEl = document.getElementById('countdown-number');
    if (!overlay || !numberEl) {
        if (callback) callback();
        return;
    }
    
    overlay.style.display = 'flex';
    overlay.style.opacity = '1';
    
    const steps = ['3', '2', '1', 'MULAI!'];
    let curStep = 0;
    
    function nextStep() {
        if (curStep >= steps.length) {
            overlay.style.transition = 'opacity 0.4s ease';
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.style.display = 'none';
                overlay.style.transition = '';
                if (callback) callback();
            }, 400);
            return;
        }
        
        numberEl.innerText = steps[curStep];
        
        // Play synthetic arcade tone!
        if (typeof AudioController !== 'undefined') {
            AudioController.init();
            if (AudioController.ctx) {
                const isMulai = curStep === 3;
                const freq = isMulai ? 880 : 440;
                const dur = isMulai ? 0.35 : 0.15;
                AudioController._tone('sine', freq, freq * 1.2, dur, 0.08);
            }
        }
        
        numberEl.classList.remove('pulse-in');
        void numberEl.offsetWidth; // trigger reflow
        numberEl.classList.add('pulse-in');
        
        curStep++;
        setTimeout(nextStep, 800);
    }
    
    nextStep();
}

/* ========== SPIN (Starting Player) ========== */
function runSpinAnimation(callback) {
    const players = lastState ? lastState.players : null;
    const targetIdx = lastState ? lastState.current_turn : 0;
    const overlay = document.getElementById('spin-overlay');
    const display = document.getElementById('spin-display');
    if (!overlay || !display || !players || players.length < 2) {
        if (callback) callback();
        return;
    }

    overlay.style.display = 'flex';
    overlay.style.opacity = '1';

    const totalFlips = 18 + targetIdx; // always lands on target
    let step = 0;
    let delay = 60;
    const colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b'];

    function flip() {
        const idx = step % players.length;
        const p = players[idx];
        const color = colors[idx % colors.length];

        display.innerHTML =
            `<div class="spin-avatar" style="background:${color}">${p.is_bot ? '🤖' : p.name.charAt(0).toUpperCase()}</div>` +
            `<div class="spin-name">${p.name}</div>`;

        if (step < totalFlips) {
            if (step > totalFlips - 16) delay += 25; // gradual slowdown
            step++;
            setTimeout(flip, delay);
        } else {
            display.classList.add('spin-landed');
            // Victory jingle
            if (typeof AudioController !== 'undefined') {
                AudioController.init();
                if (AudioController.ctx) {
                    [523, 659, 784].forEach((f, i) => {
                        const o = AudioController.ctx.createOscillator(),
                              g = AudioController.ctx.createGain();
                        o.type = 'square'; o.frequency.setValueAtTime(f, AudioController.ctx.currentTime + i * 0.08);
                        g.gain.setValueAtTime(0.06, AudioController.ctx.currentTime + i * 0.08);
                        g.gain.exponentialRampToValueAtTime(0.001, AudioController.ctx.currentTime + i * 0.08 + 0.3);
                        o.connect(g); g.connect(AudioController.ctx.destination);
                        o.start(AudioController.ctx.currentTime + i * 0.08);
                        o.stop(AudioController.ctx.currentTime + i * 0.08 + 0.3);
                    });
                }
            }
            setTimeout(() => {
                overlay.style.transition = 'opacity 0.5s ease';
                overlay.style.opacity = '0';
                setTimeout(() => {
                    overlay.style.display = 'none';
                    overlay.style.transition = '';
                    display.classList.remove('spin-landed');
                    if (callback) callback();
                }, 500);
            }, 1400);
        }
    }

    flip();
}

/* ========== BOOT ========== */
document.addEventListener('DOMContentLoaded', () => {
    pollStatus();
    setInterval(pollStatus, 1500);
});
