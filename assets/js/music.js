/* ========== MUSIC CONTROLLER ========== */
const MusicController = {
    ctx: null, playing: false, _timer: null,

    init() {
        if (!this.ctx) this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        if (this.ctx.state === 'suspended') this.ctx.resume();
    },
    _vol() { return ((typeof Settings !== 'undefined' && Settings) ? Settings.musicVolume : 50) / 100; },
    _n(f, dur, type, vol, t) {
        try {
            const o = this.ctx.createOscillator(), g = this.ctx.createGain();
            o.type = type; o.frequency.setValueAtTime(f, t);
            g.gain.setValueAtTime(vol * this._vol(), t);
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
            g.gain.setValueAtTime(vol * this._vol(), t); g.gain.exponentialRampToValueAtTime(0.001, t + dur * 0.9);
            src.start(t);
        } catch(e) {}
    },

    _chord(bar) {
        const chords = [
            [261.63, 329.63, 392.00, 523.25],
            [392.00, 493.88, 587.33, 783.99],
            [440.00, 523.25, 659.25, 880.00],
            [349.23, 440.00, 523.25, 698.46],
            [293.66, 349.23, 440.00, 587.33],
        ];
        const map = [0,1,2,3, 2,3,0,1, 3,0,4,1, 0,3,1,0];
        return chords[map[bar % 16]];
    },

    _melody(t, bar, chord) {
        const b = 0.5;
        const s = Math.floor(bar / 4) % 4;
        const bi = bar % 4;
        const patterns = [
            [[[0,0,1],[1,1,0.5],[2,1.5,0.5],[3,2,0.9],[2,2.9,0.4],[1,3.3,0.5]],
             [[1,0,0.8],[2,0.8,0.4],[3,1.2,0.6],[2,1.8,0.4],[0,2.2,0.9],[1,3.1,0.4],[2,3.5,0.3]],
             [[3,0,0.6],[2,0.6,0.4],[1,1,0.5],[0,1.5,0.9],[1,2.4,0.4],[2,2.8,0.4],[3,3.2,0.5]],
             [[0,0,0.9],[2,0.9,0.4],[1,1.3,0.5],[3,1.8,0.8],[2,2.6,0.3],[1,2.9,0.4],[0,3.3,0.5]]],
            [[[3,0,0.5],[2,0.5,0.5],[1,1,0.5],[0,1.5,1],[1,2.5,0.4],[2,2.9,0.4],[3,3.3,0.4]],
             [[0,0,0.6],[1,0.6,0.4],[2,1,0.5],[3,1.5,0.7],[2,2.2,0.4],[1,2.6,0.4],[0,3,0.5],[2,3.5,0.3]],
             [[3,0,0.4],[2,0.4,0.4],[1,0.8,0.5],[0,1.3,0.9],[2,2.2,0.4],[1,2.6,0.4],[3,3,0.5]],
             [[1,0,0.4],[0,0.4,0.4],[2,0.8,0.5],[3,1.3,0.8],[1,2.1,0.4],[0,2.5,0.4],[2,2.9,0.5],[1,3.4,0.3]]],
            [[[0,0,0.8],[2,0.8,0.4],[1,1.2,0.5],[3,1.7,0.6],[2,2.3,0.4],[1,2.7,0.4],[0,3.1,0.5]],
             [[3,0,0.5],[2,0.5,0.4],[1,0.9,0.5],[3,1.4,0.5],[2,1.9,0.3],[0,2.2,0.6],[1,2.8,0.4],[2,3.2,0.4]],
             [[1,0,0.5],[2,0.5,0.4],[0,0.9,0.5],[2,1.4,0.4],[3,1.8,0.7],[2,2.5,0.3],[1,2.8,0.4],[0,3.2,0.5]],
             [[2,0,0.5],[1,0.5,0.4],[0,0.9,0.5],[2,1.4,0.4],[3,1.8,0.6],[2,2.4,0.3],[1,2.7,0.4],[2,3.1,0.4]]],
            [[[0,0,0.8],[1,0.8,0.4],[2,1.2,0.4],[3,1.6,0.7],[2,2.3,0.4],[1,2.7,0.4],[3,3.1,0.5]],
             [[3,0,0.4],[2,0.4,0.4],[1,0.8,0.4],[0,1.2,0.6],[1,1.8,0.4],[2,2.2,0.4],[3,2.6,0.6],[2,3.2,0.3]],
             [[1,0,0.5],[2,0.5,0.4],[0,0.9,0.5],[3,1.4,0.6],[2,2,0.3],[0,2.3,0.5],[1,2.8,0.3],[3,3.1,0.5]],
             [[0,0,1],[2,1,0.5],[3,1.5,0.6],[2,2.1,0.3],[0,2.4,1]], ]];
        (patterns[s]?.[bi] ?? patterns[0][0]).forEach(([ti, sb, db]) => {
            this._n(chord[ti], db * b, 'triangle', 0.03, t + sb * b);
        });
    },

    _playBar(t, bar) {
        const b = 0.5;
        const chord = this._chord(bar);
        const root = chord[0];
        for (let i = 0; i < 3; i++) this._n(chord[i] * 0.5, b * 3.9, 'sine', 0.02, t);
        for (let i = 0; i < 3; i++) this._n(chord[i], b * 3.9, 'sine', 0.01, t);
        this._n(root * 0.5, b * 1.8, 'triangle', 0.07, t);
        this._n(root * 0.5, b * 1.6, 'triangle', 0.05, t + b * 2);
        this._noise(0.04, 0.03, t);
        this._noise(0.03, 0.02, t + b * 2);
        for (let i = 0; i < 4; i++) {
            this._noise(0.01, 0.008, t + i * b + b * 0.5);
        }
        if (bar % 2 === 1) this._noise(0.02, 0.025, t + b * 2 + b * 0.5);
        for (let i = 0; i < 8; i++) {
            this._n(chord[i % 4], b * 0.35, 'sine', 0.007, t + i * b * 0.5 + b * 0.05);
        }
        this._melody(t, bar, chord);
    },

    _schedule(startBar) {
        if (!this.playing) return;
        const now = this.ctx.currentTime, barLen = 2;
        for (let i = 0; i < 8; i++) this._playBar(now + i * barLen, startBar + i);
        this._timer = setTimeout(() => this._schedule(startBar + 8), barLen * 5 * 1000);
    },

    start() {
        if (this.playing) return;
        this.init(); if (!this.ctx) return;
        this.playing = true;
        this._schedule(0);
    },
    stop() {
        this.playing = false;
        if (this._timer) { clearTimeout(this._timer); this._timer = null; }
    },
    toggle() {
        if (this.playing) { this.stop(); return false; }
        this.start(); return true;
    }
};
