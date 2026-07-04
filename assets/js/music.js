/* ========== MUSIC CONTROLLER ========== */
const MusicController = {
    ctx: null, playing: false, _timer: null,

    init() {
        if (!this.ctx) this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        if (this.ctx.state === 'suspended') this.ctx.resume();
    },
    _n(f, dur, type, vol, t) {
        try {
            const o = this.ctx.createOscillator(), g = this.ctx.createGain();
            o.type = type; o.frequency.setValueAtTime(f, t);
            g.gain.setValueAtTime(vol, t);
            g.gain.exponentialRampToValueAtTime(0.001, t + dur * 0.85);
            o.connect(g); g.connect(this.ctx.destination);
            o.start(t); o.stop(t + dur * 1.1);
        } catch(e) {}
    },
    _noise(dur, vol, t) {
        try {
            const sr = this.ctx.sampleRate, buf = this.ctx.createBuffer(1, sr * dur, sr);
            const d = buf.getChannelData(0);
            for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
            const src = this.ctx.createBufferSource(); src.buffer = buf;
            const g = this.ctx.createGain();
            src.connect(g); g.connect(this.ctx.destination);
            g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(0.001, t + dur * 0.9);
            src.start(t);
        } catch(e) {}
    },

    /* -------- HARMONY --------
       Chord tones ALWAYS in C major key (no wrong notes).
       [root, third, fifth, octave_root]
    */
    _chord(bar) {
        const chords = [
            // 0:Cmaj (C4 E4 G4 C5), 1:Gmaj (G4 B4 D5 G5), 2:Am (A4 C5 E5 A5),
            // 3:Fmaj (F4 A4 C5 F5), 4:Dm (D4 F4 A4 D5)
            [261.63, 329.63, 392.00, 523.25],
            [392.00, 493.88, 587.33, 783.99],
            [440.00, 523.25, 659.25, 880.00],
            [349.23, 440.00, 523.25, 698.46],
            [293.66, 349.23, 440.00, 587.33],
        ];
        const map = [
        // S0:C→G→Am→F   S1:Am→F→C→G   S2:F→C→Dm→G   S3:C→F→G→C
            0,1,2,3,       2,3,0,1,       3,0,4,1,       0,3,1,0
        ];
        return chords[map[bar % 16]];
    },

    /* -------- MELODY --------
       Every single note is a CHORD TONE (index 0-3 into chord).
       No passing tones, no non-chord tones = impossible to hit a wrong note.
       4 sections × 4 bars = 16 unique patterns.
    */
    _melody(t, bar, chord) {
        const b = 0.5; // sec/beat at 120 BPM
        const s = Math.floor(bar / 4) % 4;
        const bi = bar % 4;

        const patterns = [
            // Section 0 — gentle ascending
            [[[0,0,1],[1,1,0.5],[2,1.5,0.5],[3,2,0.9],[2,2.9,0.4],[1,3.3,0.5]],
             [[1,0,0.8],[2,0.8,0.4],[3,1.2,0.6],[2,1.8,0.4],[0,2.2,0.9],[1,3.1,0.4],[2,3.5,0.3]],
             [[3,0,0.6],[2,0.6,0.4],[1,1,0.5],[0,1.5,0.9],[1,2.4,0.4],[2,2.8,0.4],[3,3.2,0.5]],
             [[0,0,0.9],[2,0.9,0.4],[1,1.3,0.5],[3,1.8,0.8],[2,2.6,0.3],[1,2.9,0.4],[0,3.3,0.5]]],
            // Section 1 — gentle descending
            [[[3,0,0.5],[2,0.5,0.5],[1,1,0.5],[0,1.5,1],[1,2.5,0.4],[2,2.9,0.4],[3,3.3,0.4]],
             [[0,0,0.6],[1,0.6,0.4],[2,1,0.5],[3,1.5,0.7],[2,2.2,0.4],[1,2.6,0.4],[0,3,0.5],[2,3.5,0.3]],
             [[3,0,0.4],[2,0.4,0.4],[1,0.8,0.5],[0,1.3,0.9],[2,2.2,0.4],[1,2.6,0.4],[3,3,0.5]],
             [[1,0,0.4],[0,0.4,0.4],[2,0.8,0.5],[3,1.3,0.8],[1,2.1,0.4],[0,2.5,0.4],[2,2.9,0.5],[1,3.4,0.3]]],
            // Section 2 — building (wider leaps, eighth-note drive)
            [[[0,0,0.8],[2,0.8,0.4],[1,1.2,0.5],[3,1.7,0.6],[2,2.3,0.4],[1,2.7,0.4],[0,3.1,0.5]],
             [[3,0,0.5],[2,0.5,0.4],[1,0.9,0.5],[3,1.4,0.5],[2,1.9,0.3],[0,2.2,0.6],[1,2.8,0.4],[2,3.2,0.4]],
             [[1,0,0.5],[2,0.5,0.4],[0,0.9,0.5],[2,1.4,0.4],[3,1.8,0.7],[2,2.5,0.3],[1,2.8,0.4],[0,3.2,0.5]],
             [[2,0,0.5],[1,0.5,0.4],[0,0.9,0.5],[2,1.4,0.4],[3,1.8,0.6],[2,2.4,0.3],[1,2.7,0.4],[2,3.1,0.4]]],
            // Section 3 — triumphant (octave leaps, longer final note)
            [[[0,0,0.8],[1,0.8,0.4],[2,1.2,0.4],[3,1.6,0.7],[2,2.3,0.4],[1,2.7,0.4],[3,3.1,0.5]],
             [[3,0,0.4],[2,0.4,0.4],[1,0.8,0.4],[0,1.2,0.6],[1,1.8,0.4],[2,2.2,0.4],[3,2.6,0.6],[2,3.2,0.3]],
             [[1,0,0.5],[2,0.5,0.4],[0,0.9,0.5],[3,1.4,0.6],[2,2,0.3],[0,2.3,0.5],[1,2.8,0.3],[3,3.1,0.5]],
             [[0,0,1],[2,1,0.5],[3,1.5,0.6],[2,2.1,0.3],[0,2.4,1]], ]];

        (patterns[s]?.[bi] ?? patterns[0][0]).forEach(([ti, sb, db]) => {
            this._n(chord[ti], db * b, 'triangle', 0.03, t + sb * b);
        });
    },

    /* -------- ONE BAR -------- */
    _playBar(t, bar) {
        const b = 0.5; // sec/beat @ 120 BPM
        const chord = this._chord(bar);
        const root = chord[0];

        // PAD (sine, soft — full chord sustain)
        for (let i = 0; i < 3; i++) this._n(chord[i] * 0.5, b * 3.9, 'sine', 0.02, t);
        for (let i = 0; i < 3; i++) this._n(chord[i], b * 3.9, 'sine', 0.01, t);

        // BASS (triangle, root on 1 & 3)
        this._n(root * 0.5, b * 1.8, 'triangle', 0.07, t);
        this._n(root * 0.5, b * 1.6, 'triangle', 0.05, t + b * 2);

        // PERCUSSION (subtle, no harsh snap)
        this._noise(0.04, 0.03, t);           // kick 1
        this._noise(0.03, 0.02, t + b * 2);   // kick 3
        for (let i = 0; i < 4; i++) {
            this._noise(0.01, 0.008, t + i * b + b * 0.5); // soft hi-hat offbeats
        }
        // Extra accent on bar 2 & 4 of section
        if (bar % 2 === 1) this._noise(0.02, 0.025, t + b * 2 + b * 0.5);

        // ARPEGGIO (sine, glitter — 8th-note chord tones)
        for (let i = 0; i < 8; i++) {
            this._n(chord[i % 4], b * 0.35, 'sine', 0.007, t + i * b * 0.5 + b * 0.05);
        }

        // MELODY (triangle — main voice, pure chord tones)
        this._melody(t, bar, chord);
    },

    _schedule(startBar) {
        if (!this.playing) return;
        const now = this.ctx.currentTime, barLen = 2; // 4 beats × 0.5s
        for (let i = 0; i < 8; i++) this._playBar(now + i * barLen, startBar + i);
        this._timer = setTimeout(() => this._schedule(startBar + 8), barLen * 5 * 1000);
    },

    start() {
        if (this.playing) return;
        this.init(); if (!this.ctx) return;
        this.playing = true;
        this._schedule(0);
        this._updateBtn(true);
    },
    stop() {
        this.playing = false;
        if (this._timer) { clearTimeout(this._timer); this._timer = null; }
        this._updateBtn(false);
    },
    toggle() {
        if (this.playing) { this.stop(); return false; }
        this.start(); return true;
    },
    _updateBtn(on) {
        const btn = document.getElementById('btn-music');
        if (!btn) return;
        btn.innerHTML = '<i class="fas fa-music"></i>';
        btn.classList.toggle('on', on);
    }
};
