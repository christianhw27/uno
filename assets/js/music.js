/* ========== MUSIC CONTROLLER - YouTube Background Audio ========== */
const MusicController = {
    _player: null,
    _ready: false,
    _playing: false,
    _intended: false,
    _videoId: '_Q8Ih2SW-TE',
    _apiLoaded: false,

    get playing() { return this._playing; },

    /* Load YouTube IFrame API (called once) */
    init() {
        if (this._apiLoaded) return;
        this._apiLoaded = true;

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
        this._player.setVolume(this._vol());
        this._player.playVideo();
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
