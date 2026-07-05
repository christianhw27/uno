/* ========== MUSIC CONTROLLER - YouTube Background Audio ========== */
const MusicController = {
    _player: null,
    _ready: false,
    _playing: false,
    _intended: false,
    _apiLoaded: false,
    _seekTo: null,

    /* Video ID — read from global UNO_MUSIC_ID or fallback to default */
    get _videoId() {
        return typeof UNO_MUSIC_ID !== 'undefined' ? UNO_MUSIC_ID : '_Q8Ih2SW-TE';
    },

    get playing() { return this._playing; },

    /* Save playback state to sessionStorage before page unload */
    _saveState() {
        if (!this._ready || !this._player) return;
        try {
            sessionStorage.setItem('uno_video', this._videoId);
            sessionStorage.setItem('uno_time', this._player.getCurrentTime());
            sessionStorage.setItem('uno_playing', this._playing ? '1' : '0');
        } catch(e) {}
    },

    /* Load YouTube IFrame API (called once) */
    init() {
        if (this._apiLoaded) return;
        this._apiLoaded = true;

        // Restore saved playback position if same video
        const prevVideo = sessionStorage.getItem('uno_video');
        const prevTime = parseFloat(sessionStorage.getItem('uno_time') || '0');
        if (prevVideo === this._videoId && prevTime > 0) {
            this._seekTo = prevTime;
        }
        // Clean up stored state
        sessionStorage.removeItem('uno_video');
        sessionStorage.removeItem('uno_time');
        sessionStorage.removeItem('uno_playing');

        // Save state on page unload for next page
        window.addEventListener('beforeunload', () => this._saveState());

        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        const first = document.getElementsByTagName('script')[0];
        first.parentNode.insertBefore(tag, first);

        window.onYouTubeIframeAPIReady = () => this._createPlayer();

        // In case API already loaded before our callback was set
        if (typeof YT !== 'undefined' && YT.loaded) {
            this._createPlayer();
        }
    },

    /* Create hidden YouTube player */
    _createPlayer() {
        // Create a minimal hidden container
        const container = document.createElement('div');
        container.id = 'yt-bg-player';
        container.style.cssText = 'position:fixed;width:1px;height:1px;opacity:0.01;pointer-events:none;overflow:hidden;z-index:-1;';
        document.body.appendChild(container);

        this._player = new YT.Player('yt-bg-player', {
            width: 1,
            height: 1,
            videoId: this._videoId,
            playerVars: {
                autoplay: 0,
                controls: 0,
                disablekb: 1,
                enablejsapi: 1,
                modestbranding: 1,
                rel: 0,
                iv_load_policy: 3,
                fs: 0,
                loop: 1,
                playlist: this._videoId,
            },
            events: {
                onReady: () => {
                    this._ready = true;
                    // Seek to saved position if restoring same video
                    if (this._seekTo != null && !isNaN(this._seekTo) && this._seekTo > 0) {
                        this._player.seekTo(this._seekTo, true);
                        this._seekTo = null;
                    }
                    // Try to play — might be blocked (autoplay policy), but if it works
                    // we save the user a click. If blocked, _playing stays false and
                    // resumeMusic (click handler) will retry from a user gesture.
                    if (this._intended) this._resume();
                },
                onStateChange: (e) => {
                    if (e.data === YT.PlayerState.PLAYING) {
                        this._playing = true;
                    } else if (e.data === YT.PlayerState.PAUSED) {
                        this._playing = false;
                    }
                    // Restart if video ends while intended to play
                    if (e.data === YT.PlayerState.ENDED && this._intended) {
                        this._player.playVideo();
                    }
                }
            }
        });
    },

    _resume() {
        if (!this._ready || !this._player) return;
        try {
            this._player.setVolume(this._vol());
            this._player.playVideo();
        } catch(e) {
            // Player may be stale after tab switch — reset readiness
            this._ready = false;
            this._apiLoaded = false;
            this._player = null;
            this._playing = false;
            // Will be re-created on next start() call
        }
        // _playing will be set by onStateChange(PLAYING)
    },

    _pause() {
        if (!this._ready || !this._player) return;
        this._player.pauseVideo();
        this._playing = false;
    },

    /* Read current volume from whichever settings are active */
    _vol() {
        if (typeof Settings !== 'undefined' && Settings) return Settings.musicVolume;
        if (typeof _homeSettings !== 'undefined' && _homeSettings) return _homeSettings.musicVolume;
        return 50;
    },

    /* Apply volume change live to YouTube player */
    setVolume(val) {
        if (this._ready && this._player) {
            this._player.setVolume(parseInt(val));
        }
    },

    start() {
        if (this._playing) return;
        this._intended = true;
        if (!this._apiLoaded) this.init();
        if (this._ready) this._resume();
    },

    stop() {
        this._intended = false;
        this._pause();
    },

    toggle() {
        if (this._playing) { this.stop(); return false; }
        this.start(); return true;
    }
};
