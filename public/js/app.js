// ═══════════════════════════════════════════
// TELEGRAM INIT
// ═══════════════════════════════════════════

if (!window.Telegram || !window.Telegram.WebApp) {
        console.warn('Not in Telegram — running in browser preview mode. API calls will use ?user_id fallback.');
        // Show subtle banner but keep app UI for testing
        document.addEventListener('DOMContentLoaded', function() {
            var banner = document.createElement('div');
            banner.textContent = '⚠️ Предпросмотр в браузере — откройте в Telegram для полной функциональности';
            banner.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#C9A84C;color:#111318;text-align:center;font-size:11px;padding:6px;z-index:9999';
            document.body.prepend(banner);
            requestAnimationFrame(function() {
                document.body.style.setProperty('--banner-h', banner.offsetHeight + 'px');
            });
        });
    }

    const tg = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : {
        initData: '',
        initDataUnsafe: { user: {} },
        ready: function(){}, expand: function(){},
        setBackgroundColor: function(){}, setHeaderColor: function(){},
        disableVerticalSwipes: function(){}, onEvent: function(){},
        sendData: function(d){ console.log('[stub] sendData', d); if (typeof showToast !== 'undefined') showToast('Откройте в Telegram для отправки'); },
        HapticFeedback: { impactOccurred: function(){}, notificationOccurred: function(){} },
        BackButton: { isVisible: false, onClick: function(){}, show: function(){}, hide: function(){} },
        showAlert: function(m){ alert(m); }
    };
    try { tg.ready(); tg.expand(); } catch(e) {}
    try { tg.disableVerticalSwipes(); } catch(e) {}

    function getTheme() {
        try { return localStorage.getItem('wpz_theme') === 'light' ? 'light' : 'dark'; } catch (e) { return 'dark'; }
    }
    function applyTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.style.colorScheme = t;
        try { localStorage.setItem('wpz_theme', t); } catch (e) {}
        var bg = t === 'light' ? '#F3F0E9' : '#111318';
        document.body.style.background = bg;
        try { tg.setBackgroundColor(bg); } catch (e) {}
        try { tg.setHeaderColor(bg); } catch (e) {}
        var sw = document.getElementById('theme-switch');
        if (sw) {
            sw.classList.toggle('on', t === 'light');
            sw.setAttribute('aria-checked', t === 'light' ? 'true' : 'false');
        }
    }
    function toggleTheme() { applyTheme(getTheme() === 'light' ? 'dark' : 'light'); }
    applyTheme(getTheme());

    try {
        tg.onEvent('themeChanged', function() {
            applyTheme(getTheme());
        });
    } catch(e) {}

    var user = {};
    try {
        user = tg.initDataUnsafe.user || {};
    } catch(e) {}
    if (!user.first_name && tg.initData) {
        try {
            var raw = tg.initData;
            var idx = raw.indexOf('user=');
            if (idx !== -1) {
                var start = idx + 5;
                var end = raw.indexOf('&', start);
                var jsonStr = end === -1 ? raw.substring(start) : raw.substring(start, end);
                jsonStr = decodeURIComponent(jsonStr.replace(/\+/g, ' '));
                var obj = JSON.parse(jsonStr);
                if (obj.id) user.id = obj.id;
                if (obj.first_name) user.first_name = obj.first_name;
                if (obj.last_name) user.last_name = obj.last_name;
                if (obj.username) user.username = obj.username;
                if (obj.photo_url) user.photo_url = obj.photo_url;
            }
        } catch(e) {}
    }
    if (!user.first_name) {
        user.first_name = '';
    }
    let html5QrCode = null, currentPage = 'menu';
    const API_BASE = window.location.origin + '/api';

    var initGiftChecked = false;

    // ═══════════════════════════════════════════
// ICONS & SVG
// ═══════════════════════════════════════════

const ICONS = {
        back:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7l7 7" /></svg>',
        sun:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-sun)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-sun" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFE082"/><stop offset="1" stop-color="#C9A84C"/></linearGradient></defs><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>',
        bell:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-bell)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-bell" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFE08A"/><stop offset="1" stop-color="#E8A33D"/></linearGradient></defs><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>',
        bolt:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-bolt)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-bolt" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFF59D"/><stop offset="1" stop-color="#FBC02D"/></linearGradient></defs><path d="M13 3l0 7l6 0l-8 11l0 -7l-6 0l8 -11" /></svg>',
        bottle:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-bottle)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-bottle" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4FC3F7"/><stop offset="1" stop-color="#1E88E5"/></linearGradient></defs><path d="M10 5h4v-2a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v2" /><path d="M14 3.5c0 1.626 .507 3.212 1.45 4.537l.05 .07a8.093 8.093 0 0 1 1.5 4.694v6.199a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2v-6.2c0 -1.682 .524 -3.322 1.5 -4.693l.05 -.07a7.823 7.823 0 0 0 1.45 -4.537" /><path d="M7 14.803a2.4 2.4 0 0 0 1 -.803a2.4 2.4 0 0 1 2 -1a2.4 2.4 0 0 1 2 1a2.4 2.4 0 0 0 2 1a2.4 2.4 0 0 0 2 -1a2.4 2.4 0 0 1 1 -.805" /></svg>',
        calendar:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-calendar)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-calendar" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FF8A80"/><stop offset="1" stop-color="#E53935"/></linearGradient></defs><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" /></svg>',
        camera:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-camera)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-camera" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#26C6DA"/><stop offset="1" stop-color="#0288D1"/></linearGradient></defs><path d="M5 12h14" /><path d="M3 7v-2a2 2 0 0 1 2 -2h2" /><path d="M3 17v2a2 2 0 0 0 2 2h2" /><path d="M17 3h2a2 2 0 0 1 2 2v2" /><path d="M17 21h2a2 2 0 0 0 2 -2v-2" /></svg>',
        cart:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-cart)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-cart" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#CE93D8"/><stop offset="1" stop-color="#8E24AA"/></linearGradient></defs><path d="M4 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg>',
        chart:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-chart)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-chart" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#66BB6A"/><stop offset="1" stop-color="#2E9E5B"/></linearGradient></defs><path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -6" /><path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -10" /><path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -14" /><path d="M4 20h14" /></svg>',
        check:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-check)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-check" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#66BB6A"/><stop offset="1" stop-color="#43A047"/></linearGradient></defs><path d="M5 12l5 5l10 -10" /></svg>',
        clipboard:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-clipboard)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-clipboard" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#B0BEC5"/><stop offset="1" stop-color="#78909C"/></linearGradient></defs><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 5a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2" /></svg>',
        coin:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-coin)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-coin" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFE082"/><stop offset="1" stop-color="#FFA000"/></linearGradient></defs><path d="M9 14c0 1.657 2.686 3 6 3s6 -1.343 6 -3s-2.686 -3 -6 -3s-6 1.343 -6 3" /><path d="M9 14v4c0 1.656 2.686 3 6 3s6 -1.344 6 -3v-4" /><path d="M3 6c0 1.072 1.144 2.062 3 2.598s4.144 .536 6 0c1.856 -.536 3 -1.526 3 -2.598c0 -1.072 -1.144 -2.062 -3 -2.598s-4.144 -.536 -6 0c-1.856 .536 -3 1.526 -3 2.598" /><path d="M3 6v10c0 .888 .772 1.45 2 2" /><path d="M3 11c0 .888 .772 1.45 2 2" /></svg>',
        diamond:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-diamond)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-diamond" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4DD0E1"/><stop offset="1" stop-color="#7E57C2"/></linearGradient></defs><path d="M6 5h12l3 5l-8.5 9.5a.7 .7 0 0 1 -1 0l-8.5 -9.5l3 -5" /><path d="M10 12l-2 -2.2l.6 -1" /></svg>',
        document:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-document)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-document" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#B0BEC5"/><stop offset="1" stop-color="#90A4AE"/></linearGradient></defs><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg>',
        drop:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-drop)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-drop" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4FC3F7"/><stop offset="1" stop-color="#1E88E5"/></linearGradient></defs><path d="M7.502 19.423c2.602 2.105 6.395 2.105 8.996 0c2.602 -2.105 3.262 -5.708 1.566 -8.546l-4.89 -7.26c-.42 -.625 -1.287 -.803 -1.936 -.397a1.376 1.376 0 0 0 -.41 .397l-4.893 7.26c-1.695 2.838 -1.035 6.441 1.567 8.546" /></svg>',
        gift:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-gift)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-gift" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F48FB1"/><stop offset="1" stop-color="#EC407A"/></linearGradient></defs><path d="M3 9a1 1 0 0 1 1 -1h16a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-16a1 1 0 0 1 -1 -1l0 -2" /><path d="M12 8l0 13" /><path d="M19 12v7a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-7" /><path d="M7.5 8a2.5 2.5 0 0 1 0 -5a4.8 8 0 0 1 4.5 5a4.8 8 0 0 1 4.5 -5a2.5 2.5 0 0 1 0 5" /></svg>',
        heart:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-heart)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-heart" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FF8A80"/><stop offset="1" stop-color="#E53935"/></linearGradient></defs><path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572" /></svg>',
        history:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-history)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-history" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#90CAF9"/><stop offset="1" stop-color="#42A5F5"/></linearGradient></defs><path d="M12 8l0 4l2 2" /><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5" /></svg>',
        home:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-home)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-home" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#64B5F6"/><stop offset="1" stop-color="#1E88E5"/></linearGradient></defs><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg>',
        hourglass:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-hourglass)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-hourglass" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFB74D"/><stop offset="1" stop-color="#EF6C00"/></linearGradient></defs><path d="M6.5 7h11" /><path d="M6.5 17h11" /><path d="M6 20v-2a6 6 0 1 1 12 0v2a1 1 0 0 1 -1 1h-10a1 1 0 0 1 -1 -1" /><path d="M6 4v2a6 6 0 1 0 12 0v-2a1 1 0 0 0 -1 -1h-10a1 1 0 0 0 -1 1" /></svg>',
        lock:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-lock)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-lock" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#B0BEC5"/><stop offset="1" stop-color="#78909C"/></linearGradient></defs><path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6" /><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M8 11v-4a4 4 0 1 1 8 0v4" /></svg>',
        party:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-party)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-party" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F48FB1"/><stop offset="1" stop-color="#EC407A"/></linearGradient></defs><path d="M4 5h2" /><path d="M5 4v2" /><path d="M11.5 4l-.5 2" /><path d="M18 5h2" /><path d="M19 4v2" /><path d="M15 9l-1 1" /><path d="M18 13l2 -.5" /><path d="M18 19h2" /><path d="M19 18v2" /><path d="M14 16.518l-6.518 -6.518l-4.39 9.58a1 1 0 0 0 1.329 1.329l9.579 -4.39" /></svg>',
        privacy:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-privacy)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-privacy" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4DB6AC"/><stop offset="1" stop-color="#00897B"/></linearGradient></defs><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3" /><path d="M11 11a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M12 12l0 2.5" /></svg>',
        question:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-question)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-question" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#B0BEC5"/><stop offset="1" stop-color="#78909C"/></linearGradient></defs><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 17l0 .01" /><path d="M12 13.5a1.5 1.5 0 0 1 1 -1.5a2.6 2.6 0 1 0 -3 -4" /></svg>',
        raffle:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-raffle)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-raffle" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFB74D"/><stop offset="1" stop-color="#F57C00"/></linearGradient></defs><path d="M15 5l0 2" /><path d="M15 11l0 2" /><path d="M15 17l0 2" /><path d="M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-3a2 2 0 0 0 0 -4v-3a2 2 0 0 1 2 -2" /></svg>',
        scanner:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-scanner)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-scanner" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#26C6DA"/><stop offset="1" stop-color="#00ACC1"/></linearGradient></defs><path d="M4 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4" /><path d="M7 17l0 .01" /><path d="M14 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4" /><path d="M7 7l0 .01" /><path d="M4 15a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4" /><path d="M17 7l0 .01" /><path d="M14 14l3 0" /><path d="M20 14l0 .01" /><path d="M14 14l0 3" /><path d="M14 20l3 0" /><path d="M17 17l3 0" /><path d="M20 17l0 3" /></svg>',
        shield:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-shield)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-shield" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4DB6AC"/><stop offset="1" stop-color="#00897B"/></linearGradient></defs><path d="M11.46 20.846a12 12 0 0 1 -7.96 -14.846a12 12 0 0 0 8.5 -3a12 12 0 0 0 8.5 3a12 12 0 0 1 -.09 7.06" /><path d="M15 19l2 2l4 -4" /></svg>',
        sparkles:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-sparkles)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-sparkles" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFE082"/><stop offset="1" stop-color="#FFC107"/></linearGradient></defs><path d="M17.8 19.817l-2.172 1.138a.392 .392 0 0 1 -.568 -.41l.415 -2.411l-1.757 -1.707a.389 .389 0 0 1 .217 -.665l2.428 -.352l1.086 -2.193a.392 .392 0 0 1 .702 0l1.086 2.193l2.428 .352a.39 .39 0 0 1 .217 .665l-1.757 1.707l.414 2.41a.39 .39 0 0 1 -.567 .411l-2.172 -1.138" /><path d="M6.2 19.817l-2.172 1.138a.392 .392 0 0 1 -.568 -.41l.415 -2.411l-1.757 -1.707a.389 .389 0 0 1 .217 -.665l2.428 -.352l1.086 -2.193a.392 .392 0 0 1 .702 0l1.086 2.193l2.428 .352a.39 .39 0 0 1 .217 .665l-1.757 1.707l.414 2.41a.39 .39 0 0 1 -.567 .411l-2.172 -1.138" /><path d="M12 9.817l-2.172 1.138a.392 .392 0 0 1 -.568 -.41l.415 -2.411l-1.757 -1.707a.389 .389 0 0 1 .217 -.665l2.428 -.352l1.086 -2.193a.392 .392 0 0 1 .702 0l1.086 2.193l2.428 .352a.39 .39 0 0 1 .217 .665l-1.757 1.707l.414 2.41a.39 .39 0 0 1 -.567 .411l-2.172 -1.138" /></svg>',
        sprout:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-sprout)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-sprout" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#81C784"/><stop offset="1" stop-color="#2E9E5B"/></linearGradient></defs><path d="M7 15h10v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2v-4" /><path d="M12 9a6 6 0 0 0 -6 -6h-3v2a6 6 0 0 0 6 6h3" /><path d="M12 11a6 6 0 0 1 6 -6h3v1a6 6 0 0 1 -6 6h-3" /><path d="M12 15l0 -6" /></svg>',
        star:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-star)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-star" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFE082"/><stop offset="1" stop-color="#FFB300"/></linearGradient></defs><path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873l-6.158 -3.245" /></svg>',
        store:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-store)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-store" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#CE93D8"/><stop offset="1" stop-color="#AB47BC"/></linearGradient></defs><path d="M3 21l18 0" /><path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4" /><path d="M5 21l0 -10.15" /><path d="M19 21l0 -10.15" /><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" /></svg>',
        target:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-target)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-target" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#90CAF9"/><stop offset="1" stop-color="#42A5F5"/></linearGradient></defs><path d="M11 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M12 7a5 5 0 1 0 5 5" /><path d="M13 3.055a9 9 0 1 0 7.941 7.945" /><path d="M15 6v3h3l3 -3h-3v-3l-3 3" /><path d="M15 9l-3 3" /></svg>',
        tree:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-tree)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-tree" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#66BB6A"/><stop offset="1" stop-color="#2E9E5B"/></linearGradient></defs><path d="M12 13l-2 -2" /><path d="M12 12l2 -2" /><path d="M12 21v-13" /><path d="M9.824 16a3 3 0 0 1 -2.743 -3.69a3 3 0 0 1 .304 -4.833a3 3 0 0 1 4.615 -3.707a3 3 0 0 1 4.614 3.707a3 3 0 0 1 .305 4.833a3 3 0 0 1 -2.919 3.695h-4l-.176 -.005" /></svg>',
        trophy:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-trophy)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-trophy" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFD54F"/><stop offset="1" stop-color="#FF8F00"/></linearGradient></defs><path d="M8 21l8 0" /><path d="M12 17l0 4" /><path d="M7 4l10 0" /><path d="M17 4v8a5 5 0 0 1 -10 0v-8" /><path d="M3 9a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 9a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /></svg>',
        warning:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-warning)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-warning" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFB74D"/><stop offset="1" stop-color="#F57C00"/></linearGradient></defs><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0" /><path d="M12 16h.01" /></svg>',
        chat:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#ig-chat)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ig-chat" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F59E0B"/><stop offset="1" stop-color="#D97706"/></linearGradient></defs><path d="M21 15a2 2 0 0 1 -2 2h-14l-4 4v-14a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2z" /><path d="M9 10h.01" /><path d="M12 10h.01" /><path d="M15 10h.01" /></svg>',
        send:'<svg width="20" height="20" viewBox="0 0 24 24" fill="#111318" stroke="none"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>',
    };
    var _icoUid = 0;
    function icoRaw(name) {
        var svg = ICONS[name];
        if (!svg) return '';
        _icoUid++;
        return svg.replace(/id="(ig-[\w-]+)"/g, 'id="$1-' + _icoUid + '"')
                  .replace(/url\(#(ig-[\w-]+)\)/g, 'url(#$1-' + _icoUid + ')');
    }
    function icon(name, cls) {
        var svg = icoRaw(name);
        return '<span class="icn ' + (cls||'') + '">' + svg + '</span>';
    }

    function renderStaticIcons(root) {
        root = root || document.body;
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, function(n) {
            return n.parentNode && n.parentNode.nodeName === 'SCRIPT' ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
        }, false);
        var node, nodes = [];
        while (node = walker.nextNode()) {
            if (node.nodeValue && /icon\('(\w+)'\)/.test(node.nodeValue)) {
                nodes.push(node);
            }
        }
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i], parent = el.parentNode;
            var html = el.nodeValue.replace(/icon\('(\w+)'\)/g, function(m, name) {
                var s = icoRaw(name);
                return s ? '<span class="icn">' + s + '</span>' : m;
            });
            var temp = document.createElement('span');
            temp.innerHTML = html;
            while (temp.firstChild) parent.insertBefore(temp.firstChild, el);
            parent.removeChild(el);
        }
    }
    renderStaticIcons();

    // ═══════════════════════════════════════════
// TREE LEVELS DATA
// ═══════════════════════════════════════════

var TL = [
        { level: 1, name: 'Росток', icon: icon('sprout'), xp: '0–99' },
        { level: 2, name: 'Саженец', icon: icon('sprout'), xp: '100–499' },
        { level: 3, name: 'Молодое дерево', icon: icon('tree'), xp: '500–999' },
        { level: 4, name: 'Крепкое дерево', icon: icon('tree'), xp: '1000–1999' },
        { level: 5, name: 'Могучее дерево', icon: icon('tree'), xp: '2000–4999' },
        { level: 6, name: 'Древо жизни', icon: icon('star'), xp: '5000+' },
    ];

    // ═══════════════════════════════════════════
// BOTTLE COMPONENTS
// ═══════════════════════════════════════════

function bottleHTML(uid) {
        uid = uid || 0;
        var capColors = ['#2B7BE4','#1E90FF','#4169E1','#1877F2','#0066CC','#3B82F6'];
        var capColor = capColors[uid % capColors.length];
        var waterLevel = 40 + (uid * 11 % 45);
        var label = String.fromCharCode(65 + (uid % 26));
        var waterGrad = 'linear-gradient(180deg, rgba(30,144,255,0.35) 0%, rgba(30,144,255,0.2) 100%)';
        var glow = 'radial-gradient(ellipse at center, rgba(30,144,255,0.08) 0%, transparent 70%)';
        var labelBg = 'rgba(255,255,255,0.08)';
        var labelColor = 'rgba(255,255,255,0.8)';
        return {
            glow: glow,
            html:
                '<div class="bottle-cap" style="background:' + capColor + ';box-shadow:0 2px 8px rgba(30,144,255,0.2)"></div>'
                + '<div class="bottle-neck" style="background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.07)"></div>'
                + '<div class="bottle-body" style="background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.08)">'
                + '<div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>'
                + '<div class="bottle-water" style="height:' + waterLevel + '%;background:' + waterGrad + '"></div>'
                + '<div class="bottle-label" style="background:' + labelBg + ';color:' + labelColor + '">' + label + '</div>'
                + '<div class="bottle-shine"></div>'
                + '<div class="bottle-shine-2"></div>'
                + '</div>'
        };
    }

    // ═══════════════════════════════════════════
// AVATAR
// ═══════════════════════════════════════════

function setAvatarPhoto(photoUrl) {
        if (!photoUrl) return;
        var photoEl = document.getElementById('top-avatar-photo');
        var topPlaceholder = document.getElementById('top-placeholder');
        var profilePhotoEl = document.getElementById('profile-photo');
        var profilePlaceholder = document.getElementById('profile-placeholder');
        if (photoEl) {
            photoEl.onerror = function() {
                console.warn('[avatar] photo failed to load:', photoUrl);
                photoEl.style.display = 'none';
                if (topPlaceholder) topPlaceholder.style.display = 'flex';
            };
            photoEl.src = photoUrl;
            photoEl.style.display = 'block';
            if (topPlaceholder) topPlaceholder.style.display = 'none';
        }
        if (profilePhotoEl) {
            profilePhotoEl.onerror = function() {
                console.warn('[avatar] profile photo failed to load:', photoUrl);
                profilePhotoEl.style.display = 'none';
                if (profilePlaceholder) profilePlaceholder.style.display = 'flex';
            };
            profilePhotoEl.src = photoUrl;
            profilePhotoEl.style.display = 'block';
            if (profilePlaceholder) profilePlaceholder.style.display = 'none';
        }
    }

    // ═══════════════════════════════════════════
// USER UI
// ═══════════════════════════════════════════

function setUserUI(data) {
        var n = (data && data.name) || user.first_name || 'Пользователь';
        var fullName = user.first_name || '';
        if (user.last_name) fullName += ' ' + user.last_name;
        document.getElementById('top-name').textContent = n;
        document.getElementById('profile-name').textContent = fullName || n;
        document.getElementById('profile-id').innerHTML = 'ID: <span>' + (user.id || (data && data.telegram_id ? data.telegram_id : '—')) + '</span>';
    }
    setUserUI(null);

    // ═══════════════════════════════════════════
// API HELPERS
// ═══════════════════════════════════════════

    async function apiFetch(p) {
        try {
            var resp = await fetch(API_BASE + p, { headers: { 'X-Telegram-Init-Data': tg.initData || '' } });
            var data = await resp.json();
            if (!resp.ok) {
                // 401 or other error — return null so callers show proper empty state instead of throwing
                console.warn('[apiFetch] ' + p + ' -> ' + resp.status, data);
                // For endpoints that returned {ok:false}, propagate null to trigger empty state
                if (data && data.error === 'unauthorized') return null;
                // If response is an error object but not auth, return it as-is for caller checks
                if (data && typeof data.ok !== 'undefined' && !data.ok) return data;
                return null;
            }
            return data;
        } catch(e) { console.warn('[apiFetch] network error', p, e); return null; }
    }
    function _h(extra) { return Object.assign({ 'X-Telegram-Init-Data': tg.initData || '' }, extra || {}); }

    function getUID() {
        if (user.id) return user.id;
        if (tg.initDataUnsafe && tg.initDataUnsafe.user && tg.initDataUnsafe.user.id) return tg.initDataUnsafe.user.id;
        try {
            var raw = tg.initData || '';
            var pairs = raw.split('&');
            for (var i = 0; i < pairs.length; i++) {
                var kv = pairs[i].split('=');
                if (kv[0] === 'user') {
                    var decoded = decodeURIComponent(kv[1] || '');
                    var obj = JSON.parse(decoded);
                    if (obj.id) return obj.id;
                }
            }
        } catch(e) {}
        try {
            var params = new URLSearchParams(window.location.search);
            var urlUid = params.get('user_id');
            if (urlUid) return parseInt(urlUid);
        } catch(e) {}
        return null;
    }

    // ═══════════════════════════════════════════
// UTILITIES
// ═══════════════════════════════════════════

function countUp(el, target, duration) {
        if (!el || target === undefined || target === null) return;
        var start = parseInt(el.textContent) || 0;
        var diff = target - start;
        if (diff === 0) { el.textContent = target; return; }
        var startTime = null;
        function step(ts) {
            if (!startTime) startTime = ts;
            var progress = Math.min((ts - startTime) / (duration || 600), 1);
            var ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(start + diff * ease);
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // ═══════════════════════════════════════════
// ADMIN
// ═══════════════════════════════════════════

    async function updateNotifBadge() {
        var uid = getUID();
        if (!uid) return;
        try {
            var d = await apiFetch('/notifications?user_id=' + uid);
            var badge = document.getElementById('bell-badge');
            if (!badge) return;
            if (d && d.length) {
                badge.textContent = d.length;
                badge.classList.add('show');
                var bell = document.getElementById('top-bell');
                if (bell) { bell.classList.remove('ring'); void bell.offsetWidth; bell.classList.add('ring'); }
            } else {
                badge.classList.remove('show');
            }
        } catch(e) {}
    }

    async function loadUserData() {
        var uid = getUID();
        if (!uid) {
            window._menuDataReady = true;
            if (window.tryRevealMenu) window.tryRevealMenu();
            return;
        }

        var d = null;
        try {
            var rawD = await fetch(API_BASE + '/user?user_id=' + uid, { headers: _h() });
            var txt = await rawD.text();
            try { d = JSON.parse(txt); } catch(e) {}

            if (d) {
                var displayName = d.name || user.first_name || 'Пользователь';
                document.getElementById('top-name').textContent = displayName;
                document.getElementById('profile-name').textContent = displayName;
                var idEl = document.getElementById('profile-id');
                if (idEl) idEl.textContent = user.id || d.telegram_id || uid || '—';
                var initialEl = document.getElementById('profile-initial');
                if (initialEl) initialEl.textContent = (displayName || '?').trim().charAt(0).toUpperCase();
                var handleEl = document.getElementById('profile-handle');
                if (handleEl) {
                    if (d.username) {
                        handleEl.textContent = '@' + d.username;
                        handleEl.style.display = 'block';
                    } else {
                        handleEl.style.display = 'none';
                    }
                }
                countUp(document.getElementById('profile-balance'), d.balance);
                countUp(document.getElementById('profile-scans'), d.total_scans);
                // top balance is on the hidden main screen — count it when the reveal happens
                var menuEl = document.getElementById('page-menu');
                if (menuEl && menuEl.classList.contains('menu-revealed')) {
                    countUp(document.getElementById('top-balance'), d.balance);
                } else {
                    window._pendingTopBalance = d.balance;
                }
                if (d.photo_url) {
                    console.log('[avatar] photo_url from API:', d.photo_url);
                    setAvatarPhoto(d.photo_url);
                } else if (user.photo_url) {
                    console.log('[avatar] photo_url from TG initData:', user.photo_url);
                    setAvatarPhoto(user.photo_url);
                }
            }
        } catch(e) {}

        try {
            var coupCard = document.getElementById('card-my-coupons');
            if (coupCard) coupCard.style.display = 'flex';

            // non-critical requests in parallel so the reveal isn't delayed by them
            var extras = [];
            if (d && !d.photo_url && !user.photo_url) {
                extras.push((async function() {
                    try {
                        console.log('[avatar] no photo_url, trying fallback');
                        var photoResp = await fetch(API_BASE + '/user-photo?user_id=' + uid, { headers: _h() });
                        var photoData = await photoResp.json();
                        if (photoData && photoData.photo_url) {
                            console.log('[avatar] photo_url from fallback:', photoData.photo_url);
                            setAvatarPhoto(photoData.photo_url);
                        }
                    } catch(e) {}
                })());
            }
            if (!initGiftChecked) {
                initGiftChecked = true;
                extras.push(checkGift().catch(function(){}));
            }
            extras.push(updateNotifBadge().catch(function(){}));
            extras.push((async function() {
                try {
                    var partAccData = await apiFetch('/partner-account/' + uid);
                    var partCard = document.getElementById('card-partner-dashboard');
                    if (partAccData && partAccData.ok && partCard) {
                        var cat = (partAccData.account && partAccData.account.shop_categories) || {};
                        partCard.style.display = 'flex';
                        var partName = document.getElementById('card-partner-name');
                        if (partName) partName.textContent = cat.title || 'Бизнес-партнёр';
                    } else if (partCard) {
                        partCard.style.display = 'none';
                    }
                } catch(e) {}
            })());
            await Promise.all(extras);
        } catch(e) {}
        window._menuDataReady = true;
        if (window.tryRevealMenu) window.tryRevealMenu();
    }
    async function loadMenuTree() {
        var box = document.getElementById('menu-tree');
        if (!box) return;
        var uid = getUID();
        if (!uid) return;
        var d = await apiFetch('/tree?user_id=' + uid);
        if (!d || typeof d.level === 'undefined') return;
        var xp = d.xp || 0, lv = d.level || 1, nx = d.next_level_xp || 100;
        var pr = Math.max(0, Math.min(100, d.progress || 0));
        var mx = lv >= 6;
        document.getElementById('menu-tree-lvl').textContent = mx ? 'Уровень ' + lv + ' ★' : 'Уровень ' + lv;
        document.getElementById('menu-tree-fill').style.width = pr + '%';
        document.getElementById('menu-tree-pct').textContent = pr + '%';
        document.getElementById('menu-tree-foot').textContent = mx
            ? xp + ' XP · максимальный уровень'
            : xp + ' / ' + nx + ' XP · ещё ' + Math.max(0, nx - xp) + ' до ' + (lv + 1) + ' уровня';
        box.style.display = 'flex';
    }

    var _homeTilesLoaded = false;
    async function loadHomeTiles() {
        if (_homeTilesLoaded) return;
        _homeTilesLoaded = true;
        try {
            var d = await apiFetch('/settings');
            var tiles = (d && d.tiles) || {};
            var map = {
                'card-shop-home': tiles.shop,
                'card-my-coupons': tiles.coupons,
                'card-partner-dashboard': tiles.partner,
                'card-support': tiles.support
            };
            Object.keys(map).forEach(function(id) {
                var url = map[id];
                if (!url) return;
                var card = document.getElementById(id);
                if (!card) return;
                var media = card.querySelector('.menu-card-media');
                if (!media) return;
                var img = new Image();
                img.className = 'menu-card-photo';
                img.alt = '';
                img.onload = function() {
                    media.insertBefore(img, media.firstChild);
                    var icn = media.querySelector('.icn');
                    if (icn) icn.style.display = 'none';
                };
                img.src = url;
            });
        } catch (e) {}
    }

    async function loadProfileExtras() {
        var uid = getUID();
        if (!uid) return;
        try {
            var d = await apiFetch('/tree?user_id=' + uid);
            if (!d || typeof d.level === 'undefined') return;
            var lv = d.level || 1;
            var pr = Math.max(0, Math.min(100, d.progress || 0));
            var stage = (TL[lv - 1] || TL[0]).name;
            var chip = document.getElementById('profile-level-chip');
            if (chip) {
                chip.innerHTML = 'Уровень <b>' + lv + '</b> · ' + stage;
                chip.style.display = 'inline-flex';
            }
            var lvlEl = document.getElementById('profile-level');
            if (lvlEl) lvlEl.textContent = lv;
            var fill = document.getElementById('profile-xp-fill');
            if (fill) fill.style.width = pr + '%';
            var statCard = lvlEl ? lvlEl.closest('.profile-stat-card') : null;
            if (statCard) statCard.title = stage + ' · ' + pr + '%';
        } catch (e) {}
    }

    // Simultaneous reveal: wait for both splash and data
    window._menuDataReady = false;
    window._pendingTopBalance = null;
    window.tryRevealMenu = function() {
        if (!window._menuDataReady || !window._splashHidden) return;
        var menu = document.getElementById('page-menu');
        if (menu && !menu.classList.contains('menu-revealed')) {
            menu.classList.add('menu-revealed');
            if (window._pendingTopBalance != null) {
                countUp(document.getElementById('top-balance'), window._pendingTopBalance);
                window._pendingTopBalance = null;
            }
        }
        setTimeout(function() {
            if (typeof maybeShowDailyBonus === 'function') maybeShowDailyBonus();
        }, 1100);
    };
    loadUserData();
    loadMenuTree();
    loadHomeTiles();

    // ═══════════════════════════════════════════
// NOTIFICATIONS
// ═══════════════════════════════════════════

var notifOpen = false;
    function toggleNotif(e) {
        if (e) e.stopPropagation();
        var d = document.getElementById('notif-dropdown');
        var bell = document.getElementById('top-bell');
        notifOpen = !notifOpen;
        if (notifOpen) {
            var rect = bell.getBoundingClientRect();
            d.style.top = (rect.bottom + 8) + 'px';
            d.style.right = (window.innerWidth - rect.right) + 'px';
        }
        d.classList.toggle('show', notifOpen);
        if (notifOpen) loadNotifs();
    }
    document.addEventListener('click', function(e) {
        var b = document.getElementById('top-bell');
        if (notifOpen && b && !b.contains(e.target)) {
            notifOpen = false;
            document.getElementById('notif-dropdown').classList.remove('show');
        }
    });
    async function loadNotifs() {
        var l = document.getElementById('notif-list');
        var uid = getUID();
        if (!uid) { l.innerHTML = '<div class="notif-empty">Нет уведомлений</div>'; return; }
        var d = await apiFetch('/notifications?user_id=' + uid);
        if (!d || !d.length) { l.innerHTML = '<div class="notif-empty">Нет новых уведомлений</div>'; var b = document.getElementById('bell-badge'); if (b) b.classList.remove('show'); return; }
        l.innerHTML = d.map(function(n) {
            var ico = n.type === 'scan' ? '' + icon('drop') + '' : n.type === 'points' ? '' + icon('coin') + '' : n.type === 'donation' ? '' + icon('heart') + '' : n.type === 'gift' ? '' + icon('gift') + '' : '' + icon('raffle') + '';
            var cls = n.type === 'scan' ? 'scan' : n.type === 'points' ? 'points' : n.type === 'donation' ? 'donation' : n.type === 'gift' ? 'donation' : 'raffle';
            return '<div class="notif-item" onclick="event.stopPropagation();openPage(\'' + (n.link || 'menu') + '\')">'
                + '<div class="notif-icon ' + cls + '">' + ico + '</div>'
                + '<div class="notif-content"><div class="notif-title">' + esc(n.title || '') + '</div>'
                + '<div class="notif-desc">' + esc(n.body || '') + '</div>'
                + '<div class="notif-time">' + (n.created_at ? new Date(n.created_at).toLocaleString('ru-RU') : '') + '</div></div></div>';
        }).join('');
        var badge = document.getElementById('bell-badge');
        if (badge) { badge.textContent = d.length; badge.classList.add('show'); }
        var bell = document.getElementById('top-bell');
        if (bell) { bell.classList.remove('ring'); void bell.offsetWidth; bell.classList.add('ring'); }
    }
    function clearNotifs() {
        var l = document.getElementById('notif-list');
        l.innerHTML = '<div class="notif-empty">Нет новых уведомлений</div>';
        var b = document.getElementById('bell-badge');
        if (b) { b.textContent = ''; b.classList.remove('show'); }
        var uid = getUID();
        if (uid) fetch(API_BASE + '/notifications/clear?user_id=' + uid, { headers: _h() }).catch(function(){});
    }

    // ═══════════════════════════════════════════
// NAVIGATION
// ═══════════════════════════════════════════

function stopScanner() {
        if (html5QrCode) {
            try { if (html5QrCode.isScanning) html5QrCode.stop(); } catch(e) {}
        }
        var r = document.getElementById('reader');
        if (r) r.style.display = 'none';
        var z = document.getElementById('scan-zone');
        if (z) z.style.display = 'block';
        if (supportPollTimer) { clearInterval(supportPollTimer); supportPollTimer = null; }
    }

    function openPage(page) {
        stopScanner();
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        const el = document.getElementById('page-' + page);
        if (!el) return;
        el.classList.add('active');
        void el.offsetHeight;
        currentPage = page;
        try { tg.BackButton.isVisible = false; } catch(e) {}
        document.querySelectorAll('.nav-item').forEach(n => {
            n.classList.toggle('active', n.dataset.page === page);
        });
        var nav = document.querySelector('.bottom-nav');
        if (nav) {
            if (page === 'gift' || page === 'post-gift' || page === 'support') {
                nav.classList.add('nav-hidden');
            } else {
                nav.classList.remove('nav-hidden');
            }
        }
        document.body.classList.toggle('chat-mode', page === 'support');
        if (page === 'menu') { loadUserData(); loadMenuTree(); loadHomeTiles(); }
        if (page === 'scanner') {
            var sr = document.getElementById('scan-result');
            if (sr) sr.classList.remove('show');
            var sz = document.getElementById('scan-zone');
            if (sz) sz.style.display = 'block';
            var sb = document.getElementById('scan-btn');
            if (sb) { sb.textContent = 'Включить камеру'; sb.disabled = false; }
        }
        if (page === 'profile') { applyTheme(getTheme()); loadUserData(); loadProfileExtras(); }
        if (page === 'history') switchHistoryTab('scans');
        if (page === 'shop') loadShop();
        if (page === 'raffles') loadRaffles();
        if (page === 'tree') loadTree();
        if (page === 'bottles') renderBottles();
        if (page === 'gift') {} // gift page is static
        if (page === 'support') openSupport();
        if (page === 'my-coupons') loadMyCoupons();
        if (page === 'partner-dashboard') loadPartnerDashboard();
    }

    // ═══════════════════════════════════════════
// PAGE: MY COUPONS
// ═══════════════════════════════════════════

    var myCouponsData = [];
    var myCouponsFilter = 'all';
    var currentCouponDetail = null;

    var MYC_STATUS = {
        active:  { label: 'Активен', color: '#0EA5E9' },
        used:    { label: 'Использован', color: '#EAB308' },
        expired: { label: 'Истёк', color: '#EF4444' },
        revoked: { label: 'Отозван', color: '#EF4444' }
    };

    function mycStatus(c) {
        return MYC_STATUS[c.status || 'active'] || { label: c.status || 'Активен', color: '#999' };
    }

    function mycIsArchive(c) {
        return ['used', 'expired', 'revoked'].indexOf(c.status || 'active') >= 0;
    }

    function mycDate(s) {
        if (!s) return '';
        try { return new Date(s).toLocaleDateString('ru-RU'); } catch (e) { return ''; }
    }

    function hexA(hex, a) {
        var h = String(hex || '#999').replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        var n = parseInt(h, 16);
        if (isNaN(n)) return 'rgba(153,153,153,' + a + ')';
        return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
    }

async function loadMyCoupons() {
        var uid = getUID();
        var list = document.getElementById('my-coupons-list');
        var chips = document.getElementById('my-coupons-chips');
        myCouponsData = [];
        myCouponsFilter = 'all';
        renderMycPill();
        if (!uid) {
            if (chips) chips.style.display = 'none';
            list.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('warning') + '</div><div class="empty-t">Пользователь не найден</div></div>';
            return;
        }
        var data = await apiFetch('/user/' + uid + '/coupons');
        if (!data || !data.ok || !data.coupons || !data.coupons.length) {
            if (chips) chips.style.display = 'none';
            list.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('gift') + '</div><div class="empty-t">Купонов пока нет</div><div class="empty-d">Обменивайте баллы на призы в магазине</div></div>';
            return;
        }
        myCouponsData = data.coupons;
        renderMycPill();
        renderMyCouponsChips();
        renderMyCouponsList();
    }

    function renderMycPill() {
        var n = myCouponsData.filter(function (c) { return (c.status || 'active') === 'active'; }).length;
        var pill = document.getElementById('myc-count-pill');
        var val = document.getElementById('myc-active-count');
        if (val) val.textContent = n;
        if (pill) pill.classList.toggle('zero', !n);
    }

    function renderMyCouponsChips() {
        var chips = document.getElementById('my-coupons-chips');
        if (!chips) return;
        if (!myCouponsData.length) { chips.style.display = 'none'; chips.innerHTML = ''; return; }
        var counts = {
            all: myCouponsData.length,
            active: myCouponsData.filter(function (c) { return (c.status || 'active') === 'active'; }).length,
            fav: myCouponsData.filter(function (c) { return !!c.is_favorite; }).length,
            arch: myCouponsData.filter(mycIsArchive).length
        };
        chips.style.display = 'flex';
        chips.innerHTML = ''
            + mycChip('all', 'Все', counts.all)
            + mycChip('active', 'Активные', counts.active)
            + mycChip('fav', icon('star') + ' Избранное', counts.fav)
            + mycChip('arch', 'Архив', counts.arch);
    }

    function mycChip(f, label, n) {
        return '<button type="button" class="shop-chip' + (myCouponsFilter === f ? ' active' : '') + '" onclick="filterMyCoupons(\'' + f + '\')">' + label + ' <span class="shop-chip-count">' + n + '</span></button>';
    }

    function filterMyCoupons(f) {
        myCouponsFilter = ['active', 'fav', 'arch'].indexOf(f) >= 0 ? f : 'all';
        renderMyCouponsChips();
        renderMyCouponsList();
    }

    function mycVisibleItems() {
        if (myCouponsFilter === 'active') return myCouponsData.filter(function (c) { return (c.status || 'active') === 'active'; });
        if (myCouponsFilter === 'fav') return myCouponsData.filter(function (c) { return !!c.is_favorite; });
        if (myCouponsFilter === 'arch') return myCouponsData.filter(mycIsArchive);
        return myCouponsData.slice();
    }

    function mycEmptyText() {
        if (myCouponsFilter === 'fav') return { ico: 'star', t: 'В избранном пока нет', d: 'Нажмите ★ на карточке купона' };
        if (myCouponsFilter === 'active') return { ico: 'question', t: 'Активных купонов нет', d: 'Загляните в архив или в магазин' };
        if (myCouponsFilter === 'arch') return { ico: 'history', t: 'Архив пуст', d: 'Здесь появятся использованные купоны' };
        return { ico: 'gift', t: 'Купонов пока нет', d: 'Обменивайте баллы на призы в магазине' };
    }

    function renderMyCouponsList() {
        var list = document.getElementById('my-coupons-list');
        if (!list) return;
        var items = mycVisibleItems();
        if (!items.length) {
            var e = mycEmptyText();
            list.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon(e.ico) + '</div><div class="empty-t">' + e.t + '</div><div class="empty-d">' + e.d + '</div></div>';
            return;
        }
        var rest = items.slice(1);
        list.innerHTML = mycHeroHtml(items[0])
            + (rest.length ? '<div class="myc-grid">' + rest.map(function (c, i) { return mycCardHtml(c, i + 1); }).join('') + '</div>' : '');

        Array.prototype.forEach.call(list.querySelectorAll('[data-myc-idx]'), function (el) {
            var c = items[Number(el.getAttribute('data-myc-idx'))];
            el.addEventListener('click', function () { openCouponModal(c); });
        });
        Array.prototype.forEach.call(list.querySelectorAll('[data-myc-fav]'), function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                toggleMycFavorite(items[Number(btn.getAttribute('data-myc-fav'))]);
            });
        });
        Array.prototype.forEach.call(list.querySelectorAll('[data-myc-copy]'), function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                var code = btn.getAttribute('data-myc-copy');
                if (code && navigator.clipboard) navigator.clipboard.writeText(code);
                showToast('Код скопирован');
            });
        });
    }

    function mycImgHtml(c) {
        if (c.prize_image) return '<img src="' + esc(c.prize_image) + '" alt="" onload="this.classList.add(\'img-loaded\')" onerror="this.remove()">';
        return '<div class="myc-fallback">' + esc(c.partner_icon || '\u{1F381}') + '</div>';
    }

    function mycFavBtnHtml(idx, fav) {
        return '<button type="button" class="myc-fav' + (fav ? ' on' : '') + '" data-myc-fav="' + idx + '" aria-label="В избранное">' + (fav ? '★' : '☆') + '</button>';
    }

    function mycStampHtml(c) {
        if (!mycIsArchive(c)) return '';
        var st = mycStatus(c);
        return '<span class="myc-stamp" style="color:' + st.color + ';border-color:' + st.color + '">' + st.label.toUpperCase() + '</span>';
    }

    function mycHeroHtml(c) {
        var st = mycStatus(c);
        var isActive = (c.status || 'active') === 'active';
        var badge = isActive
            ? '<span class="shop-hero-badge ok"><i></i>Готов к активации</span>'
            : '<span class="shop-hero-badge"><i></i>' + esc(st.label) + '</span>';
        var price = c.is_gift
            ? '<div class="shop-hero-price myc-gift-line">' + icon('gift') + '<b>Подарок</b></div>'
            : '<div class="shop-hero-price">' + icon('coin') + '<b>' + esc(c.prize_price || 0) + '</b> <span>баллов</span></div>';
        var exp = isActive && c.expires_at ? '<span class="myc-exp">до ' + mycDate(c.expires_at) + '</span>' : '';
        return '<div class="shop-hero myc-hero" data-myc-idx="0">'
            + '<div class="shop-hero-media' + (mycIsArchive(c) ? ' myc-arch' : '') + '">'
            + mycImgHtml(c) + mycStampHtml(c) + mycFavBtnHtml(0, c.is_favorite)
            + '</div>'
            + '<div class="shop-hero-body">'
            + '<div class="shop-hero-head">'
            + '<span class="shop-hero-label" style="color:' + esc(c.partner_color || '#C9A84C') + '">' + esc((c.partner_name || 'Купон').toUpperCase()) + '</span>'
            + badge
            + '</div>'
            + '<div class="shop-hero-title">' + esc(c.prize_name || 'Приз') + '</div>'
            + '<div class="shop-hero-desc">' + esc(c.prize_description || (c.is_gift ? 'Подарок от партнёра.' : 'Покажите QR-код партнёру для активации.')) + '</div>'
            + '<div class="shop-hero-foot">'
            + '<div class="myc-hero-meta">' + price + exp + '</div>'
            + '<button type="button" class="shop-hero-arrow" aria-label="Открыть">↗</button>'
            + '</div></div></div>';
    }

    function mycCardHtml(c, idx) {
        var st = mycStatus(c);
        var isActive = (c.status || 'active') === 'active';
        var arch = mycIsArchive(c);
        var price = c.is_gift
            ? '<div class="myc-price myc-gift-line">' + icon('gift') + '<b>Подарок</b></div>'
            : '<div class="myc-price">' + icon('coin') + '<b>' + esc(c.prize_price || 0) + '</b> <span>баллов</span></div>';
        var exp = isActive && c.expires_at ? '<span class="myc-exp">до ' + mycDate(c.expires_at) + '</span>' : '';
        var giftBadge = c.is_gift ? '<span class="myc-gift">' + icon('gift') + ' Подарок</span>' : '';
        var foot = isActive
            ? '<div class="myc-foot">'
                + '<button type="button" class="myc-qr-btn">Показать QR</button>'
                + '<button type="button" class="myc-copy-btn" data-myc-copy="' + esc(c.qr_code || '') + '" aria-label="Копировать код">' + icon('clipboard') + '</button>'
                + '</div>'
            : '<div class="myc-note">' + (c.used_at
                ? '\u2713 ' + esc(st.label) + ' ' + mycDate(c.used_at)
                : (arch && c.expires_at ? '\u23F3 Истёк ' + mycDate(c.expires_at) : '\u23F3 ' + esc(st.label))) + '</div>';
        return '<div class="myc-card' + (arch ? ' is-arch' : '') + '" style="animation-delay:' + (idx * 0.04) + 's" data-myc-idx="' + idx + '">'
            + '<div class="myc-card-media' + (arch ? ' myc-arch' : '') + '">'
            + mycImgHtml(c)
            + '<span class="myc-tag">' + esc(c.partner_name || 'Купон') + '</span>'
            + mycStampHtml(c)
            + mycFavBtnHtml(idx, c.is_favorite)
            + '</div>'
            + '<div class="myc-body">'
            + '<div class="myc-title">' + esc(c.prize_name || 'Приз') + '</div>'
            + '<div class="myc-meta-row">'
            + '<span class="myc-status" style="color:' + st.color + ';background:' + hexA(st.color, 0.15) + '"><i style="background:' + st.color + '"></i>' + esc(st.label) + '</span>'
            + giftBadge + exp
            + '</div>'
            + price
            + foot
            + '</div></div>';
    }

    function toggleMycFavorite(c) {
        setCouponFavorite(c, !c.is_favorite, function () {
            renderMycPill();
            renderMyCouponsChips();
            renderMyCouponsList();
        });
    }

    function openCouponModal(coupon) {
        coupon = coupon || {};
        currentCouponDetail = coupon;
        var st = coupon.status || 'active';
        var statusLabels = { active: 'Активен', used: 'Использован', expired: 'Истёк', revoked: 'Отозван' };
        var statusColors = { active: '#0EA5E9', used: '#EAB308', expired: '#EF4444', revoked: '#EF4444' };
        var stColor = statusColors[st] || '#999';

        var brandEl = document.getElementById('cd-brand');
        if (brandEl) brandEl.textContent = (coupon.partner_name || 'Купон').toUpperCase();
        var modelEl = document.getElementById('cd-model');
        if (modelEl) modelEl.textContent = coupon.is_gift ? 'Подарок' : 'Купон';

        var imgEl = document.getElementById('coupon-modal-img');
        if (coupon.prize_image) {
            imgEl.innerHTML = '<img src="' + esc(coupon.prize_image) + '" alt="">';
        } else {
            imgEl.innerHTML = '<div class="cd-media-fallback">' + icon('gift') + '</div>';
        }

        document.getElementById('coupon-modal-name').textContent = coupon.prize_name || 'Приз';
        document.getElementById('coupon-modal-meta').innerHTML = '<span style="color:' + stColor + '">' + (statusLabels[st] || st) + '</span>';

        var pill = document.getElementById('coupon-modal-badge');
        pill.innerHTML = coupon.is_gift
            ? icon('gift') + ' Подарок от ' + esc(coupon.partner_name || 'партнёра')
            : (Number(coupon.prize_price || 0) > 0 ? esc(coupon.prize_price + ' баллов') : 'Бесплатно');

        document.getElementById('coupon-modal-desc').textContent = coupon.prize_description
            || ('Купон партнёра «' + (coupon.partner_name || 'партнёр') + '». Покажите QR-код партнёру для активации.');

        document.getElementById('coupon-modal-price').textContent = coupon.is_gift
            ? 'Подарок'
            : Number(coupon.prize_price || 0) + ' баллов';
        document.getElementById('coupon-modal-sub').textContent = coupon.is_gift
            ? '/ 0 XP'
            : (coupon.order_id ? '/ заказ #' + coupon.order_id : '/ купон #' + (coupon.id || '—'));

        var copyEl = document.getElementById('coupon-modal-copy');
        copyEl.onclick = function () {
            if (!coupon.qr_code) return;
            if (navigator.clipboard) navigator.clipboard.writeText(coupon.qr_code);
            showToast('Код скопирован');
        };

        var idBlock = '<div class="cd-id">'
            + '<span class="cd-id-label">Уникальный ID</span>'
            + '<span class="cd-id-value">#' + (coupon.id || '—') + ' • Заказ #' + (coupon.order_id || '—') + ' • ' + esc(coupon.qr_code || '') + '</span>'
            + '</div>';

        var qrEl = document.getElementById('coupon-modal-qr');
        if (st === 'active' && coupon.qr_code) {
            var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(coupon.qr_code);
            qrEl.innerHTML = '<div class="cd-qr-card"><img src="' + qrUrl + '" width="188" height="188" alt="QR купон"></div>'
                + '<div class="cd-qr-hint">Покажите QR партнёру для списания</div>'
                + idBlock;
        } else if (st === 'used') {
            qrEl.innerHTML = '<div class="cd-state"><div class="cd-state-ico">✅</div><div class="cd-state-txt">Использован ' + (coupon.used_at ? new Date(coupon.used_at).toLocaleString('ru-RU') : '') + '</div></div>' + idBlock;
        } else {
            qrEl.innerHTML = '<div class="cd-state"><div class="cd-state-ico">⏳</div><div class="cd-state-txt">' + esc(statusLabels[st] || st) + '</div></div>' + idBlock;
        }

        var fine = 'Купон создан ' + new Date(coupon.created_at || Date.now()).toLocaleString('ru-RU');
        if (coupon.expires_at) fine += ' • Действует до ' + new Date(coupon.expires_at).toLocaleDateString('ru-RU');
        if (coupon.used_at) fine += ' • Использован ' + new Date(coupon.used_at).toLocaleString('ru-RU');
        document.getElementById('coupon-modal-fine').textContent = fine;

        var buyBtn = document.getElementById('coupon-modal-buy');
        if (st === 'active' && coupon.qr_code) {
            buyBtn.disabled = false;
            buyBtn.textContent = 'Показать QR';
            buyBtn.onclick = revealCouponQr;
        } else {
            buyBtn.disabled = true;
            buyBtn.textContent = statusLabels[st] || st;
            buyBtn.onclick = null;
        }
        updateCouponFavBtn();

        document.getElementById('coupon-modal').classList.add('active');
    }

    function updateCouponFavBtn() {
        var btn = document.getElementById('coupon-modal-fav');
        if (!btn || !currentCouponDetail) return;
        var fav = !!currentCouponDetail.is_favorite;
        btn.classList.toggle('on', fav);
        btn.innerHTML = fav ? '★ В избранном' : '+ В избранное';
    }

    function revealCouponQr() {
        var scroll = document.querySelector('#coupon-modal .cd-scroll');
        var qr = document.getElementById('coupon-modal-qr');
        if (!scroll || !qr) return;
        var top = qr.getBoundingClientRect().top - scroll.getBoundingClientRect().top + scroll.scrollTop - 16;
        scroll.scrollTo({ top: top, behavior: 'smooth' });
        qr.classList.remove('cd-qr-flash');
        void qr.offsetWidth;
        qr.classList.add('cd-qr-flash');
    }

    function setCouponFavorite(c, next, onDone) {
        var uid = getUID();
        if (!c || !c.id || !uid) return;
        fetch(API_BASE + '/coupon/favorite', {
            method: 'POST',
            headers: _h({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ user_id: uid, coupon_id: c.id, favorite: next })
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res || !res.ok) { showToast('Не удалось обновить избранное'); return; }
            c.is_favorite = res.is_favorite;
            for (var i = 0; i < myCouponsData.length; i++) {
                if (myCouponsData[i].id === c.id) myCouponsData[i].is_favorite = res.is_favorite;
            }
            if (onDone) onDone(res.is_favorite);
            showToast(res.is_favorite ? 'Добавлено в избранное' : 'Убрано из избранного');
        }).catch(function () { showToast('Сеть недоступна'); });
    }

    function toggleCouponFavorite() {
        var c = currentCouponDetail;
        if (!c) return;
        setCouponFavorite(c, !c.is_favorite, function () {
            updateCouponFavBtn();
            renderMycPill();
            renderMyCouponsChips();
            renderMyCouponsList();
        });
    }

    function closeCouponModal() {
        document.getElementById('coupon-modal').classList.remove('active');
    }

    // ═══════════════════════════════════════════
// PAGE: PARTNER DASHBOARD
// ═══════════════════════════════════════════

async function loadPartnerDashboard() {
        var uid = getUID();
        var list = document.getElementById('partner-dash-list');
        var subtitle = document.getElementById('partner-dash-subtitle');
        var statsEl = document.getElementById('partner-dash-stats');
        if (!uid) return;

        var accData = await apiFetch('/partner-account/' + uid);
        if (!accData || !accData.ok) {
            list.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('warning') + '</div><div class="empty-t">Вы не являетесь партнёром</div></div>';
            return;
        }

        var account = accData.account;
        var cat = account.shop_categories || {};
        subtitle.textContent = 'Панель: ' + (cat.title || '');
        // Show partner QR for self-test (scan via mini-app scanner → activate)
        var catId = account.category_id;
        if (catId && statsEl) {
            var qrUrl = API_BASE + '/partner/qr/' + catId;
            statsEl.innerHTML = '<div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:16px;text-align:center;margin-bottom:16px">'
                + '<div style="font-size:13px;color:var(--text-dim);margin-bottom:8px">Ваш QR для клиентов</div>'
                + '<img src="' + qrUrl + '" style="width:180px;height:180px;border-radius:12px;background:#fff;padding:8px" onerror="this.style.display=\'none\'">'
                + '<div style="font-size:11px;color:var(--text-dim);margin-top:8px;word-break:break-all" id="partner-qr-code"></div>'
                + '<div style="font-size:11px;color:var(--text-dim)">Сканируйте через Сканер в мини-приложении → появится кнопка «Активировать»</div></div>';
            // Load actual qr_code text
            apiFetch('/shop/categories').then(function(cats){
                if (Array.isArray(cats)) {
                    for (var i=0;i<cats.length;i++) if (cats[i].id===catId) {
                        var q = document.getElementById('partner-qr-code');
                        if (q) q.textContent = cats[i].qr_code || '';
                    }
                }
            });
        }

        var data = await apiFetch('/partner-account/' + account.id + '/used-coupons');
        if (!data || !data.ok) return;

        // KPI кабинета: купоны, взаиморасчёты, источник, приведение
        try {
            var kpiData = await apiFetch('/partner-account/' + uid + '/stats');
            if (kpiData && kpiData.ok && kpiData.kpi) {
                var k = kpiData.kpi;
                var debt = Math.max(0, k.must_pay_istok || 0);
                var cards = '<div class="partner-stats-row kpi">'
                    + '<div class="partner-stat-card"><div class="partner-stat-val">' + (k.coupons_received || 0) + '</div><div class="partner-stat-label">Купонов получено</div></div>'
                    + '<div class="partner-stat-card"><div class="partner-stat-val">' + (k.coupons_used || 0) + '</div><div class="partner-stat-label">Использовано у вас</div></div>'
                    + '<div class="partner-stat-card" title="Начислено: ' + (k.accrued_istok || 0) + ' · Оплачено: ' + (k.paid_istok || 0) + '">'
                    + '<div class="partner-stat-val">' + debt + '</div><div class="partner-stat-label">Должен ISTOK, баллы</div></div>'
                    + '<div class="partner-stat-card"><div class="partner-stat-val">' + (k.earned_as_source || 0) + '</div><div class="partner-stat-label">Заработал источником, баллы</div></div>'
                    + '<div class="partner-stat-card"><div class="partner-stat-val">' + (k.referred_users || 0) + '</div><div class="partner-stat-label">Привёл пользователей</div></div>';
                if (k.paid_istok > 0) {
                    cards += '<div class="partner-stat-card"><div class="partner-stat-val">' + k.paid_istok + '</div><div class="partner-stat-label">Оплачено ISTOK, баллы</div></div>';
                }
                statsEl.innerHTML += cards + '</div>';
            }
        } catch(e) {}

        var coupons = data.coupons || [];
        if (!coupons.length) {
            list.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('store') + '</div><div class="empty-t">Использованных купонов пока нет</div></div>';
            return;
        }

        list.innerHTML = coupons.map(function(c, i) {
            return '<div class="coupon-card" style="animation-delay:' + (i * 0.05) + 's;border-left:3px solid ' + (cat.color || '#0EA5E9') + '">'
                + '<div style="display:flex;gap:12px;align-items:flex-start;position:relative;z-index:1">'
                + (c.prize_image ? '<img class="coupon-card-img" src="' + esc(c.prize_image) + '" onerror="this.remove()">' : '')
                + '<div style="flex:1;min-width:0">'
                + '<div class="coupon-card-header">'
                + '<div class="coupon-card-title">' + esc(c.user_name || 'Пользователь') + '</div>'
                + '<div class="coupon-card-status" style="color:#EAB308">Использован</div>'
                + '</div>'
                + '<div class="coupon-card-meta">' + esc(c.prize_name || 'Приз') + '</div>'
                + '<div class="coupon-card-date">' + icon('clock') + ' ' + (c.used_at ? new Date(c.used_at).toLocaleDateString('ru-RU') : '—') + '</div>'
                + '</div></div>'
                + '</div>';
        }).join('');

        var scanBtn = '<button class="prize-modal-btn primary" style="margin-top:16px" onclick="openCouponScanner()">Сканировать купон</button>';
        list.innerHTML += scanBtn;
    }

    // ═══════════════════════════════════════════
// COUPON SCANNER (for partners)
// ═══════════════════════════════════════════

var couponHtml5QrCode = null;

function openCouponScanner() {
        document.getElementById('coupon-scanner-modal').classList.add('active');
        document.getElementById('coupon-scanner-result').textContent = '';
        document.getElementById('coupon-scanner-zone').style.display = 'block';
        document.getElementById('coupon-scanner-reader').style.display = 'none';
    }

function closeCouponScanner() {
        document.getElementById('coupon-scanner-modal').classList.remove('active');
        if (couponHtml5QrCode && couponHtml5QrCode.isScanning) couponHtml5QrCode.stop().catch(() => {});
    }

async function startCouponScanner() {
        var zone = document.getElementById('coupon-scanner-zone');
        var reader = document.getElementById('coupon-scanner-reader');
        zone.style.display = 'none';
        reader.style.display = 'block';
        var ready = await new Promise(function(resolve) {
            whenQrReady(function() { resolve(true); }, function() { resolve(false); });
        });
        if (!ready) {
            zone.style.display = 'block';
            reader.style.display = 'none';
            document.getElementById('coupon-scanner-result').textContent = 'Не удалось загрузить сканер, попробуйте ещё раз';
            return;
        }
        if (!couponHtml5QrCode) couponHtml5QrCode = new Html5Qrcode('coupon-scanner-reader');
        try {
            await couponHtml5QrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                async function(qrCode) {
                    if (couponHtml5QrCode && couponHtml5QrCode.isScanning) couponHtml5QrCode.stop().catch(() => {});
                    reader.style.display = 'none';
                    document.getElementById('coupon-scanner-result').textContent = 'Код: ' + qrCode;
                    var uid = getUID();
                    var resp = await fetch(API_BASE + '/coupon/activate', {
                        method: 'POST',
                        headers: _h({ 'Content-Type': 'application/json' }),
                        body: JSON.stringify({ qr_code: qrCode, partner_telegram_id: uid })
                    });
                    var result = await resp.json();
                    if (result.ok) {
                        document.getElementById('coupon-scanner-result').innerHTML = '✅ Купон использован!<br><b>' + esc(result.prize_name) + '</b><br>Пользователь: ' + esc(result.user_name);
                        showToast('Купон активирован!');
                    } else {
                        var _errMap = { 'coupon_not_found':'Купон не найден', 'coupon_already_used':'Купон уже использован', 'coupon_not_for_this_brand':'Этот купон принадлежит другому бренду — примите его у своего партнёра', 'coupon_expired':'Срок действия купона истёк', 'order_cancelled':'Заказ отменён — купон недействителен', 'partner_not_found':'Вы не партнёр' };
                        var _msg = _errMap[result.error] || result.error || 'Неизвестная ошибка';
                        document.getElementById('coupon-scanner-result').innerHTML = '❌ Ошибка: ' + esc(_msg);
                        zone.style.display = 'block';
                    }
                },
                function() {}
            );
        } catch(e) {
            document.getElementById('coupon-scanner-result').textContent = 'Камера недоступна';
            zone.style.display = 'block';
        }
    }

    // ═══════════════════════════════════════════
// PAGE: HISTORY
// ═══════════════════════════════════════════

function switchHistoryTab(tab) {
        document.getElementById('tab-scans').className = 'tab-btn' + (tab === 'scans' ? ' active' : '');
        document.getElementById('tab-points').className = 'tab-btn' + (tab === 'points' ? ' active' : '');
        var list = document.getElementById('history-list');
        if (list) list.innerHTML = skRowsHtml(3);
        if (tab === 'scans') renderHistory(); else renderPointsLog();
    }

    async function renderPointsLog() {
        const list = document.getElementById('history-list');
        const d = await apiFetch('/points_log?user_id=' + getUID());
        if (!d || !d.length) { list.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('coin') + '</div><div class="empty-t">Нет операций</div><div class="empty-d">Здесь появятся начисления и списания</div></div>'; return; }
        list.innerHTML = d.map(e => '<div class="h-item"><div class="h-top"><span class="h-badge' + (parseInt(e.amount) < 0 ? ' negative' : '') + '">' + (parseInt(e.amount) > 0 ? '+' : '') + e.amount + '</span><span class="h-date">' + (e.created_at ? new Date(e.created_at).toLocaleString('ru-RU') : '—') + '</span></div><div class="h-qr">' + esc(e.description) + '</div></div>').join('');
    }

    // ═══════════════════════════════════════════
// PAGE: SHOP
// ═══════════════════════════════════════════

function openPrizeModal(p, bal) {
        p = p || {};
        bal = Number(bal || 0);
        var price = Number(p.price_points || 0);
        var ok = bal >= price;
        var missing = Math.max(0, price - bal);

        var brandEl = document.getElementById('prize-modal-brand');
        if (brandEl) brandEl.textContent = (p._partner_name || 'Магазин').toUpperCase();
        var modelEl = document.getElementById('prize-modal-model');
        if (modelEl) modelEl.textContent = 'Купон';

        var imgWrap = document.getElementById('prize-modal-img');
        var modalImg = p.image_url || p._partner_img || '';
        if (modalImg) {
            imgWrap.innerHTML = '<img src="' + esc(modalImg) + '" alt="' + esc(p.name || '') + '">';
        } else {
            imgWrap.innerHTML = '<div class="cd-media-fallback">' + icon('store') + '</div>';
        }

        document.getElementById('prize-modal-name').textContent = p.name || 'Купон';
        var metaEl = document.getElementById('prize-modal-meta');
        if (metaEl) metaEl.textContent = p._partner_name || '';

        var pill = document.getElementById('prize-modal-badge');
        pill.className = 'cd-pill ' + (ok ? 'cd-pill-ok' : 'cd-pill-warn');
        pill.innerHTML = ok
            ? icon('check') + ' Обмен за ' + price + ' баллов'
            : icon('warning') + ' Не хватает ' + missing + ' баллов';

        document.getElementById('prize-modal-desc').textContent = p.description || 'Описание отсутствует';

        document.getElementById('prize-modal-price').textContent = price + ' баллов';
        var subEl = document.getElementById('prize-modal-sub');
        if (subEl) subEl.textContent = '/ баланс ' + bal + ' баллов';

        document.getElementById('prize-modal-fine').textContent =
            'Обмен мгновенный — баллы спишутся с баланса, а купон появится в разделе «Мои купоны».';

        var actionEl = document.getElementById('prize-modal-action');
        if (ok) {
            actionEl.innerHTML = '<button class="cd-buy cd-buy-wide" onclick="doExchange(' + p.id + ')">Обменять · ' + price + ' баллов</button>';
        } else {
            actionEl.innerHTML = '<button class="cd-buy cd-buy-wide" disabled>Не хватает ' + missing + ' баллов</button>';
        }

        document.getElementById('prize-modal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

function closePrizeModal() {
        var modal = document.getElementById('prize-modal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // ═══════════════════════════════════════════
// PAGE: SHOP — МАГАЗИН КУПОНОВ
// ═════════════════════════════════════════════

    var _shopBal = 0;
    var _shopFilter = null;     // null = «Всё», иначе id партнёра
    var _shopItems = [];        // все товары (+ _partner_* )
    var _shopPartners = [];     // только партнёры, у которых есть товары
    var _shopPartnerId = null;  // запрос карточки партнёра до перехода на страницу

    function shopVisibleItems() {
        if (_shopFilter == null) return _shopItems;
        return _shopItems.filter(function(it) { return it._partner_id === _shopFilter; });
    }

    function shopPrizeJson(p) {
        return JSON.stringify(p).replace(/'/g, '&#39;');
    }

    function shopMedia(src, cls) {
        return '<div class="' + cls + '">' + icon('store')
            + (src ? '<img loading="lazy" decoding="async" src="' + esc(src) + '" alt="" onload="this.classList.add(\'img-loaded\')" onerror="this.remove()">' : '')
            + '</div>';
    }

    function hideShopBlocks() {
        ['shop-banner', 'shop-chips', 'shop-hero', 'shop-grid', 'shop-partner-detail', 'shop-empty'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    }

    function showShopSkeleton() {
        hideShopBlocks();
        var s = document.getElementById('shop-skeleton');
        if (s) s.classList.add('show');
    }

    function hideShopSkeleton() {
        var s = document.getElementById('shop-skeleton');
        if (s) s.classList.remove('show');
    }

    function loadingHtml(text) {
        return '<div class="sk-load"><div class="sk-spinner"></div><span>' + (text || 'Загрузка…') + '</span></div>';
    }

    function skRowsHtml(n) {
        var rows = '';
        for (var i = 0; i < (n || 3); i++) rows += '<div class="sk sk-row"></div>';
        return '<div class="sk-rows">' + rows + '</div>';
    }

    function shopOpenPrize(p) {
        openPrizeModal(JSON.parse(p), _shopBal);
    }

    async function loadShop(opts) {
        opts = opts || {};
        var silent = !!opts.silent;

        // карточка партнёра запрошена из другого раздела (сканер, история и т.п.)
        if (_shopPartnerId != null) {
            var openId = _shopPartnerId;
            _shopPartnerId = null;
            await renderPartnerDetail(openId);
            return;
        }

        var bannerEl = document.getElementById('shop-banner');
        var chipsEl = document.getElementById('shop-chips');
        var heroEl = document.getElementById('shop-hero');
        var gridEl = document.getElementById('shop-grid');
        var detailEl = document.getElementById('shop-partner-detail');
        var emptyEl = document.getElementById('shop-empty');
        if (!bannerEl || !chipsEl || !heroEl || !gridEl) return;

        // повторный вход — мгновенно из кэша, свежие данные качаем в фоне
        if (!silent && _shopItems.length) {
            if (detailEl) detailEl.style.display = 'none';
            renderShopList();
            loadShop({ silent: true });
            return;
        }

        if (!silent) showShopSkeleton();

        // баланс и список партнёров — параллельно
        var uid = getUID();
        var first = await Promise.all([
            uid ? apiFetch('/user?user_id=' + uid) : Promise.resolve(null),
            apiFetch('/shop/categories')
        ]);
        var d = first[0];
        _shopBal = d && typeof d.balance !== 'undefined' ? d.balance : 0;
        var balEl = document.getElementById('shop-balance-val');
        if (balEl) balEl.textContent = _shopBal;

        var cats = first[1];
        if (!Array.isArray(cats)) {
            // apiFetch may return {ok:false} or null on error
            if (cats && cats.ok === false) console.warn('[loadShop] unauthorized or error', cats);
            cats = [];
        }

        var partners = cats.filter(function(c) {
            return c.is_active && c.title;
        });

        if (!partners.length) {
            hideShopSkeleton();
            hideShopBlocks();
            emptyEl.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('store') + '</div><div class="empty-t">Магазин пуст</div><div class="empty-d">Товары скоро появятся</div></div>';
            emptyEl.style.display = 'block';
            return;
        }

        // товары всех партнёров одним заходом (раньше — последовательно по запросу на партнёра)
        var details = await Promise.all(partners.map(function(c) {
            return apiFetch('/shop/categories/' + c.id);
        }));

        var allItems = [];
        var partnersWithItems = [];
        details.forEach(function(data, i) {
            var c = partners[i];
            if (data && data.items && data.items.length) {
                data.items.forEach(function(item) {
                    item._partner_id = c.id;
                    item._partner_name = c.title;
                    item._partner_color = c.color || '#0EA5E9';
                    item._partner_img = c.logo_url || c.image_url || '';
                    allItems.push(item);
                });
                partnersWithItems.push(c);
            }
        });

        if (!allItems.length) {
            hideShopSkeleton();
            hideShopBlocks();
            emptyEl.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('store') + '</div><div class="empty-t">Магазин пуст</div><div class="empty-d">Товары скоро появятся</div></div>';
            emptyEl.style.display = 'block';
            return;
        }

        _shopItems = allItems;
        _shopPartners = partnersWithItems;
        // фильтр сохраняем, пока партнёр на месте
        if (_shopFilter != null && !partnersWithItems.some(function(c) { return c.id === _shopFilter; })) {
            _shopFilter = null;
        }
        hideShopSkeleton();
        renderShopList();
    }

    function setShopFilter(pid) {
        _shopFilter = pid;
        var chipsEl = document.getElementById('shop-chips');
        var scrollLeft = chipsEl ? chipsEl.scrollLeft : 0;
        renderShopList();
        var chipsEl2 = document.getElementById('shop-chips');
        if (chipsEl2) chipsEl2.scrollLeft = scrollLeft;
    }

    function renderShopList() {
        var bannerEl = document.getElementById('shop-banner');
        var chipsEl = document.getElementById('shop-chips');
        var heroEl = document.getElementById('shop-hero');
        var gridEl = document.getElementById('shop-grid');
        var detailEl = document.getElementById('shop-partner-detail');
        var emptyEl = document.getElementById('shop-empty');
        // карточка партнёра открыта — список не перерисовываем
        if (detailEl && detailEl.style.display === 'block') return;
        if (detailEl) detailEl.style.display = 'none';
        if (emptyEl) emptyEl.style.display = 'none';

        // ─── Баннер «Партнёр дня» (детерминирован по дате)
        if (_shopPartners.length) {
            var promo = _shopPartners[Math.floor(Date.now() / 86400000) % _shopPartners.length];
            var promoImg = promo.logo_url || promo.image_url || '';
            bannerEl.innerHTML = shopMedia(promoImg, 'shop-promo-media')
                + '<div class="shop-promo-body">'
                + '<div class="shop-promo-label">Партнёр дня</div>'
                + '<div class="shop-promo-title">' + esc(promo.title) + '</div>'
                + (promo.subtitle ? '<div class="shop-promo-sub">' + esc(promo.subtitle) + '</div>' : '')
                + '</div>'
                + '<span class="shop-promo-arrow">↗</span>';
            bannerEl.setAttribute('data-partner', promo.id);
            bannerEl.onclick = function() { openPartnerCategory(promo.id); };
            bannerEl.style.display = 'flex';
        }

        // ─── Чипы фильтров
        var chips = '<div class="shop-chip' + (_shopFilter == null ? ' active' : '') + '" onclick="setShopFilter(null)">'
            + 'Всё <span class="shop-chip-count">' + _shopItems.length + '</span></div>';
        _shopPartners.forEach(function(cp) {
            var n = 0;
            _shopItems.forEach(function(it) { if (it._partner_id === cp.id) n++; });
            chips += '<div class="shop-chip' + (_shopFilter === cp.id ? ' active' : '') + '" onclick="setShopFilter(' + cp.id + ')">'
                + esc(cp.title) + ' <span class="shop-chip-count">' + n + '</span></div>';
        });
        chipsEl.innerHTML = chips;
        chipsEl.style.display = 'flex';

        // ─── Hero + сетка
        renderShopCards();
    }

    function renderShopCards() {
        var heroEl = document.getElementById('shop-hero');
        var gridEl = document.getElementById('shop-grid');
        var emptyEl = document.getElementById('shop-empty');
        var list = shopVisibleItems();

        if (!list.length) {
            heroEl.style.display = 'none';
            gridEl.style.display = 'none';
            emptyEl.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('question') + '</div><div class="empty-t">Пока пусто</div><div class="empty-d">В этом разделе пока нет купонов</div></div>';
            emptyEl.style.display = 'block';
            return;
        }

        // hero — первый купон текущего фильтра
        var p0 = list[0];
        var ok0 = _shopBal >= p0.price_points;
        var miss0 = Math.max(0, p0.price_points - _shopBal);
        var heroImg = p0.image_url || p0._partner_img || '';
        var badge = ok0
            ? '<span class="shop-hero-badge ok"><i></i>Доступно</span>'
            : '<span class="shop-hero-badge"><i></i>Не хватает ' + miss0 + '</span>';
        heroEl.innerHTML = shopMedia(heroImg, 'shop-hero-media')
            + '<div class="shop-hero-body">'
            + '<div class="shop-hero-head">'
            + '<span class="shop-hero-label" style="color:' + esc(p0._partner_color) + '">' + esc(p0._partner_name) + '</span>'
            + badge
            + '</div>'
            + '<div class="shop-hero-title">' + esc(p0.name) + '</div>'
            + '<div class="shop-hero-desc">' + esc(p0.description || 'Описание отсутствует') + '</div>'
            + '<div class="shop-hero-foot">'
            + '<div class="shop-hero-price">' + icon('coin') + '<b>' + p0.price_points + '</b> <span>баллов</span></div>'
            + '<button class="shop-hero-arrow" data-prize=\'' + shopPrizeJson(p0) + '\' onclick="event.stopPropagation();shopOpenPrize(this.dataset.prize)" aria-label="Открыть">↗</button>'
            + '</div></div>';
        heroEl.setAttribute('data-prize', JSON.stringify(p0));
        heroEl.onclick = function() { shopOpenPrize(this.getAttribute('data-prize')); };
        heroEl.style.display = 'flex';

        // сетка — остальные купоны
        gridEl.innerHTML = list.slice(1).map(function(p, i) {
            var cardImg = p.image_url || p._partner_img || '';
            return '<div class="shop-card" style="animation-delay:' + (i * 0.04) + 's" data-prize=\'' + shopPrizeJson(p) + '\' onclick="shopOpenPrize(this.dataset.prize)">'
                + shopMedia(cardImg, 'shop-card-media')
                + '<span class="shop-card-tag">' + esc(p._partner_name) + '</span>'
                + '<div class="shop-card-body">'
                + '<div class="shop-card-title">' + esc(p.name) + '</div>'
                + '<div class="shop-card-price">' + icon('coin') + '<b>' + p.price_points + '</b> <span>баллов</span></div>'
                + '</div></div>';
        }).join('');
        gridEl.style.display = 'grid';
    }

    async function openShopCategory(catId) {
        openPage('shop-category');
        const h = document.getElementById('shop-cat-header');
        const g = document.getElementById('shop-cat-items');
        const dForm = document.getElementById('shop-donation');
        h.innerHTML = loadingHtml('Загружаем призы…');
        g.innerHTML = '';
        dForm.style.display = 'none';
        const uid = getUID();
        const [data, uData] = await Promise.all([
            apiFetch('/shop/categories/' + catId),
            uid ? apiFetch('/user?user_id=' + uid) : Promise.resolve(null)
        ]);
        if (!data || !data.category) { h.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('question') + '</div><div class="empty-t">Категория не найдена</div></div>'; return; }
        const cat = data.category;
        const items = data.items || [];
        items.forEach(function(it) { it._partner_img = cat.logo_url || cat.image_url || ''; it._partner_name = it._partner_name || cat.title; });
        const bal = uData ? uData.balance : 0;
        const isCharity = cat.title === 'Благотворительность';
        var accent = cat.color || '#C9A84C';
        h.innerHTML = '<div style="text-align:center;padding:6px 0 14px;animation:fadeIn .4s cubic-bezier(.16,1,.3,1)">'
            + (cat.image_url ? '<img src="' + esc(cat.image_url) + '" style="width:72px;height:72px;border-radius:20px;object-fit:cover;margin-bottom:10px;border:2px solid ' + accent + ';box-shadow:0 0 30px ' + accent + '20">'
                : '<div style="font-size:40px;margin-bottom:8px;animation:emojiBounce .6s ease">' + esc(cat.icon) + '</div>')
            + '<div style="font-size:22px;font-weight:800;color:' + accent + '">' + esc(cat.title) + '</div>'
            + '<div style="font-size:13px;color:var(--text-dim);margin-top:4px">' + esc(cat.subtitle) + '</div></div>';
        if (isCharity) {
            document.getElementById('shop-donation-balance').textContent = bal;
            dForm.style.display = 'block';
            g.style.display = 'none';
            return;
        }
        g.style.display = 'grid';
        if (!items.length) { g.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="empty-ico" style="font-size:36px">' + esc(cat.icon) + '</div><div class="empty-t">Призов пока нет</div><div class="empty-d">Скоро здесь появятся призы</div></div>'; return; }
        g.innerHTML = items.map(function(p, i) {
            const ok = bal >= p.price_points;
            const missing = Math.max(0, p.price_points - bal);
            var itemImg = p.image_url || cat.logo_url || cat.image_url || '';
            var imgHtml = itemImg
                ? '<div class="shop-prize-img-wrap"><img class="shop-prize-img lazy" loading="lazy" decoding="async" src="' + esc(itemImg) + '" onload="this.classList.remove(\'lazy\');this.classList.add(\'loaded\')" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="shop-prize-img-fallback" style="display:none">' + icon('store') + '</div></div>'
                : '<div class="shop-prize-img-wrap"><div class="shop-prize-img-fallback">' + icon('store') + '</div></div>';
            var pJson = JSON.stringify(p).replace(/'/g, '&#39;');
            return '<div class="shop-prize-card" style="animation-delay:' + (i * 0.05) + 's" onclick="openPrizeModal(JSON.parse(this.dataset.prize),' + bal + ')" data-prize=\'' + pJson + '\'>'
                + imgHtml
                + '<div class="shop-prize-body"><div class="shop-prize-name">' + esc(p.name) + '</div>'
                + '<div class="shop-prize-desc">' + esc(p.description) + '</div>'
                + '<div class="shop-prize-price">' + icon('target') + ' ' + p.price_points + ' баллов</div>'
                 + (ok
                    ? '<button class="shop-prize-btn primary" onclick="event.stopPropagation();doExchange(' + p.id + ')">' + icon('gift') + ' Обменять</button>'
                    : '<button class="shop-prize-btn outline" onclick="event.stopPropagation();openPrizeModal(JSON.parse(this.closest(\'[data-prize]\').dataset.prize),' + bal + ')" style="cursor:pointer">Не хватает ' + missing + ' баллов</button>')
                + '</div></div>';
        }).join('');
    }

    // ═══════════════════════════════════════════
// PAGE: PARTNER DETAIL
// ═══════════════════════════════════════════

    async function openPartnerCategory(catId) {
        var pageEl = document.getElementById('page-shop');
        if (!pageEl || !pageEl.classList.contains('active')) {
            // пришли из другого раздела — открываем магазин, loadShop отрендерит деталь
            _shopPartnerId = catId;
            openPage('shop');
            return;
        }
        await renderPartnerDetail(catId);
    }

    async function renderPartnerDetail(catId) {
        var bannerEl = document.getElementById('shop-banner');
        var chipsEl = document.getElementById('shop-chips');
        var heroEl = document.getElementById('shop-hero');
        var gridEl = document.getElementById('shop-grid');
        var detailEl = document.getElementById('shop-partner-detail');
        var emptyEl = document.getElementById('shop-empty');
        [bannerEl, chipsEl, heroEl, gridEl, emptyEl].forEach(function(el) { if (el) el.style.display = 'none'; });
        if (!detailEl) return;
        detailEl.style.display = 'block';
        detailEl.innerHTML = loadingHtml('Загружаем партнёра…');

        // категория, баланс и купоны — одним заходом
        var uid = getUID();
        var res = await Promise.all([
            apiFetch('/shop/categories/' + catId),
            uid ? apiFetch('/user?user_id=' + uid) : Promise.resolve(null),
            getAvailableCoupons()
        ]);
        var data = res[0];
        if (!data || !data.category) {
            detailEl.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('question') + '</div><div class="empty-t">Категория не найдена</div></div>';
            return;
        }

        var cat = data.category;
        var items = data.items || [];
        items.forEach(function(it) { it._partner_img = cat.logo_url || cat.image_url || ''; it._partner_name = it._partner_name || cat.title; });
        var bal = res[1] ? res[1].balance : 0;

        var logoSrc = cat.logo_url || cat.image_url || '';
        var headerHtml = '<div class="partner-back-btn" onclick="loadShop()"><span class="bb-icon">' + icon('back') + '</span> Назад к магазину</div>'
            + '<div class="partner-detail-header" style="animation:fadeIn .4s var(--ease-out)">';

        if (logoSrc) {
            headerHtml += '<img src="' + esc(logoSrc) + '" class="partner-detail-logo" style="border-color:' + (cat.color || '#0EA5E9') + ';box-shadow:0 0 30px ' + (cat.color || '#0EA5E9') + '20">';
        } else {
            headerHtml += '<div class="partner-detail-emoji">' + esc(cat.icon) + '</div>';
        }

        headerHtml += '<div class="partner-detail-title" style="color:' + (cat.color || '#0EA5E9') + '">' + esc(cat.title) + '</div>';
        if (cat.subtitle) {
            headerHtml += '<div class="partner-detail-sub">' + esc(cat.subtitle) + '</div>';
        }

        var linksHtml = '';
        if (cat.website) linksHtml += '<a href="' + esc(cat.website) + '" target="_blank" rel="noopener" class="partner-info-link">🌐 Сайт</a>';
        if (cat.telegram) linksHtml += '<a href="' + esc(cat.telegram) + '" target="_blank" rel="noopener" class="partner-info-link">✈️ Telegram</a>';
        if (linksHtml) headerHtml += '<div class="partner-info-links">' + linksHtml + '</div>';

        if (cat.description) {
            headerHtml += '<div class="partner-detail-desc">' + esc(cat.description) + '</div>';
        }
        if (cat.info) {
            headerHtml += '<div class="partner-detail-info">' + esc(cat.info).replace(/\n/g, '<br>') + '</div>';
        }

        headerHtml += '</div>';

        if (!items.length) {
            headerHtml += '<div class="empty-state"><div class="empty-ico" style="font-size:36px">' + esc(cat.icon) + '</div><div class="empty-t">Товаров пока нет</div><div class="empty-d">Скоро здесь появятся товары</div></div>';
            detailEl.innerHTML = headerHtml;
            return;
        }

        var coupons = res[2] || [];
        var couponsHtml = '';
        if (coupons.length > 0) {
            couponsHtml = '<div class="shop-rec-section" style="margin-top:16px">'
                + '<div class="shop-rec-header"><div class="shop-rec-title">Ваши купоны</div><div class="shop-rec-count">' + coupons.length + '</div></div>'
                + '<div style="display:grid;grid-template-columns:1fr;gap:10px">'
                + coupons.map(function(c, i) {
                    return '<div class="partner-item-card" style="animation-delay:' + (i * 0.05) + 's;border-left:3px solid ' + (cat.color || '#0EA5E9') + ';display:flex;gap:10px;align-items:center;padding:10px 12px">'
                        + (c.prize_image ? '<img src="' + esc(c.prize_image) + '" loading="lazy" decoding="async" alt="" style="width:52px;height:52px;border-radius:10px;object-fit:cover;flex-shrink:0" onerror="this.remove()">' : '')
                        + '<div class="partner-item-body" style="flex:1;min-width:0;padding:0"><div class="partner-item-name">' + esc(c.prize_name || 'Приз') + '</div>'
                        + '<div class="partner-item-desc">Заказ #' + c.id + '</div>'
                        + '<button class="partner-item-btn primary" onclick="handleRedeemCoupon(' + c.id + ',' + cat.id + ')">' + icon('check') + ' Использовать купон</button>'
                        + '</div></div>';
                }).join('')
                + '</div></div>';
        }

        var itemsHtml = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px">'
            + items.map(function(p, i) {
                var ok = bal >= p.price_points;
                var missing = Math.max(0, p.price_points - bal);
                var itemImg = p.image_url || cat.logo_url || cat.image_url || '';
                return '<div class="partner-item-card" style="animation-delay:' + (i * 0.05) + 's">'
                    + (itemImg ? '<div class="partner-item-img-wrap"><img class="partner-item-img" loading="lazy" decoding="async" src="' + esc(itemImg) + '" onerror="this.parentElement.style.display=\'none\'">' + (ok ? '<div class="partner-item-badge">Доступно</div>' : '') + '</div>' : (ok ? '<div class="partner-item-badge" style="position:static;margin:10px 10px 0">Доступно</div>' : ''))
                    + '<div class="partner-item-body"><div class="partner-item-name">' + esc(p.name) + '</div>'
                    + '<div class="partner-item-desc">' + esc(p.description) + '</div>'
                    + '<div class="partner-item-price">' + icon('target') + ' ' + p.price_points + ' баллов</div>'
                    + (ok
                        ? '<button class="partner-item-btn primary" onclick="doExchange(' + p.id + ')">' + icon('gift') + ' Обменять</button>'
                        : '<button class="partner-item-btn outline" disabled>Не хватает ' + missing + ' баллов</button>')
                    + '</div></div>';
            }).join('')
            + '</div>';

        detailEl.innerHTML = headerHtml + couponsHtml + itemsHtml;
    }

    async function processPartnerScan(qrCode) {
        var uid = getUID();
        if (!uid || !qrCode) return { ok: false, error: 'no_user' };

        try {
            var resp = await fetch(API_BASE + '/partner/scan', {
                method: 'POST',
                headers: _h({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ user_id: uid, qr_code: qrCode }),
            });
            var result = await resp.json();
            if (result.ok) {
                document.getElementById('top-balance').textContent = result.balance;
            }
            return result;
        } catch(e) {}
        return { ok: false, error: 'network_error' };
    }

    async function getAvailableCoupons() {
        var uid = getUID();
        if (!uid) return [];
        try {
            var resp = await fetch(API_BASE + '/user/' + uid + '/available-coupons', { headers: _h() });
            var data = await resp.json();
            return data.ok ? data.orders : [];
        } catch(e) {}
        return [];
    }

    async function redeemCoupon(orderId, partnerId) {
        var uid = getUID();
        if (!uid) return { ok: false, error: 'no_user' };
        try {
            var resp = await fetch(API_BASE + '/coupon/redeem', {
                method: 'POST',
                headers: _h({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ user_id: uid, order_id: orderId, partner_id: partnerId }),
            });
            return await resp.json();
        } catch(e) {}
        return { ok: false, error: 'network_error' };
    }

    async function handleRedeemCoupon(orderId, partnerId) {
        var result = await redeemCoupon(orderId, partnerId);
        if (result.ok) {
            showToast('Купон использован!' + (result.reward > 0 ? ' + ' + result.reward + ' баллов партнёру' : ''));
            openPartnerCategory(partnerId);
        } else {
            var _rErrMap = { 'coupon_not_for_this_brand':'Этот купон принадлежит другому бренду' };
            showToast('Ошибка: ' + (_rErrMap[result.error] || result.error || 'Неизвестная ошибка'));
        }
    }

    // ═══════════════════════════════════════════
// PAGE: RAFFLES
// ═══════════════════════════════════════════

async function loadRaffles() {
        const l = document.getElementById('raffles-list');
        const d = await apiFetch('/raffles?user_id=' + getUID());
        if (!d || !d.length) { l.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('raffle') + '</div><div class="empty-t">Розыгрышей пока нет</div><div class="empty-d">Первый розыгрыш в ближайшую пятницу</div></div>'; return; }
        l.innerHTML = d.map(r => {
            const w = r.user_won;
            return '<div class="activity-card" style="' + (w ? 'border-color:rgba(61,158,106,0.3)' : '') + '">'
                + '<div class="activity-icon">' + (w ? '' + icon('trophy') + '' : '' + icon('raffle') + '') + '</div>'
                + '<div class="activity-info"><div class="activity-title">' + (w ? 'Вы выиграли!' : 'Розыгрыш') + '</div>'
                + '<div class="activity-sub">' + icon('coin') + ' ' + r.prize_amount + ' руб.' + (w ? ' • Код: ' + esc(r.winning_code || '') : '') + '</div></div>'
                + '<div class="activity-arrow">›</div></div>';
        }).join('');
    }

    tg.BackButton.onClick(() => {
        if (currentPage === 'gift' || currentPage === 'post-gift') return;
        if (currentPage !== 'menu') { closeScannerAndBack(); openPage('menu'); }
    });

    // ═══════════════════════════════════════════
// PAGE: SCANNER
// ═══════════════════════════════════════════

// html5-qrcode loads async (non-blocking) — wait for it before starting a scan
function whenQrReady(cb, onFail) {
        var tries = 0;
        (function poll() {
            if (typeof Html5Qrcode !== 'undefined') return cb();
            if (++tries > 60) return onFail ? onFail() : undefined;
            setTimeout(poll, 100);
        })();
    }

function startScan() {
        const btn = document.getElementById('scan-btn'), reader = document.getElementById('reader'), zone = document.getElementById('scan-zone');
        document.getElementById('scan-result').classList.remove('show');
        btn.textContent = 'Запуск...'; btn.disabled = true;
        whenQrReady(function() {
            if (html5QrCode && html5QrCode.isScanning) html5QrCode.stop().then(() => doScan());
            else { html5QrCode = new Html5Qrcode('reader'); doScan(); }
        }, function() {
            btn.textContent = 'Ошибка загрузки сканера'; btn.disabled = false;
        });
        function doScan() {
            zone.style.display = 'none'; reader.style.display = 'block';
            html5QrCode.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 250, height: 250 } },
                t => onScanSuccess(t), () => {}
            ).catch(() => { btn.textContent = 'Камера недоступна'; btn.disabled = false; reader.style.display = 'none'; zone.style.display = 'block'; });
        }
    }

    async function onScanSuccess(data) {
        if (html5QrCode && html5QrCode.isScanning) html5QrCode.stop().catch(() => {});
        document.getElementById('reader').style.display = 'none';
        document.getElementById('scan-zone').style.display = 'none';
        document.getElementById('scan-result').classList.add('show');
        document.getElementById('scan-data').textContent = data;
        document.getElementById('scan-btn').textContent = 'Сканировать ещё';
        document.getElementById('scan-btn').disabled = false;
        try { tg.HapticFeedback.notificationOccurred('success'); } catch(e) {}

        var raw = (data || '').trim();
        var partnerCode = null;
        // Robust extraction: partner_ codes may be bare or inside t.me URL
        if (raw) {
            // Try URL param start=partner_*
            var mUrl = raw.match(/partner_[A-Za-z0-9_]+/);
            // If raw is URL, extract start param properly
            if (raw.indexOf('start=partner_') !== -1) {
                try {
                    var urlObj = new URL(raw);
                    var sp = urlObj.searchParams.get('start');
                    if (sp && sp.indexOf('partner_') === 0) partnerCode = sp;
                    else if (mUrl) partnerCode = mUrl[0];
                } catch(e) {
                    var m = raw.match(/start=(partner_[^&\s]+)/);
                    if (m) partnerCode = m[1];
                    else if (mUrl) partnerCode = mUrl[0];
                }
            } else if (raw.indexOf('partner_') === 0) {
                partnerCode = raw.split(/\s+/)[0];
            } else if (mUrl && raw.indexOf('partner_') !== -1) {
                partnerCode = mUrl[0];
            }
            if (partnerCode) partnerCode = partnerCode.trim();
        }

        // Coupon QR detection must be before partner/bottle: coupon_*
        var couponCode = null;
        if (raw && raw.indexOf('coupon_') !== -1) {
            var mC = raw.match(/coupon_[A-Za-z0-9_]+/);
            if (mC) couponCode = mC[0];
        }
        if (couponCode) {
            var scanDataEl = document.getElementById('scan-data');
            // Check if current user is partner
            var _uid = getUID();
            var _acc = await apiFetch('/partner-account/' + _uid);
            var isPartner = _acc && _acc.ok;
            if (isPartner) {
                scanDataEl.innerHTML = esc(raw) + '<br><br><div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:14px;padding:12px">'
                    + '<div style="font-weight:700;color:#10B981;margin-bottom:6px">' + icon('gift') + ' Купон</div>'
                    + '<div style="font-size:12px;color:var(--text-dim);margin-bottom:10px;word-break:break-all">' + esc(couponCode) + '</div>'
                    + '<button id="coupon-activate-btn" class="prize-modal-btn primary" style="width:100%">' + icon('check') + ' Использовать купон</button>'
                    + '<div id="coupon-activate-status" style="margin-top:8px;font-size:13px"></div></div>';
                var cBtn = document.getElementById('coupon-activate-btn');
                var cStatus = document.getElementById('coupon-activate-status');
                if (cBtn) cBtn.onclick = async function(){
                    cBtn.disabled = true; cBtn.textContent = 'Проверка...';
                    cStatus.innerHTML = '';
                    try {
                        console.log('[coupon] activate', couponCode, 'uid', _uid, 'initData len', (tg.initData||'').length);
                        var resp = await fetch(API_BASE + '/coupon/activate', { method:'POST', headers:_h({'Content-Type':'application/json'}), body: JSON.stringify({ qr_code: couponCode, partner_telegram_id: _uid }) });
                        var txt = await resp.text();
                        console.log('[coupon] raw resp', resp.status, txt);
                        var res = null; try { res = JSON.parse(txt); } catch(_e){ res = { ok:false, error: txt.slice(0,120) }; }
                        if (resp.ok && res && res.ok) {
                            cStatus.innerHTML = icon('check') + ' Купон активирован: ' + esc(res.prize_name) + ' (' + esc(res.user_name) + ')';
                            showToast('Купон использован!');
                            try{ tg.HapticFeedback.notificationOccurred('success'); }catch(e){}
                            cBtn.textContent = 'Готово'; cBtn.disabled = true;
                        } else {
                            var errMap = { 'coupon_not_found':'Купон не найден', 'coupon_already_used':'Купон уже использован', 'coupon_not_for_this_brand':'Этот купон принадлежит другому бренду — примите его у своего партнёра', 'coupon_expired':'Срок действия купона истёк', 'order_cancelled':'Заказ отменён — купон недействителен', 'partner_not_found':'Вы не партнёр', 'unauthorized':'Ошибка авторизации (откройте через Telegram)' };
                            var msg = (res && (res.error || res.detail)) || ('HTTP ' + resp.status);
                            cStatus.innerHTML = icon('warning') + ' ' + esc(errMap[msg] || msg || 'Ошибка');
                            cBtn.disabled = false; cBtn.textContent = 'Попробовать снова';
                            if (resp.status===401) showToast('Откройте мини-приложение через кнопку в боте');
                        }
                    } catch(e){ console.error('[coupon] network', e); cStatus.innerHTML = icon('warning') + ' Ошибка сети: ' + esc(e.message||String(e)); cBtn.disabled=false; cBtn.textContent = 'Попробовать снова'; }
                };
            } else {
                scanDataEl.innerHTML = esc(raw) + '<br><br><div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:14px;padding:12px;text-align:center">'
                    + '<div style="font-size:32px;margin-bottom:8px">' + icon('warning') + '</div>'
                    + '<div style="font-weight:700;color:#EF4444;margin-bottom:4px">Невозможно отсканировать</div>'
                    + '<div style="font-size:13px;color:var(--text-dim)">Купон может активировать только партнёр. Покажите этот QR партнёру для списания.</div>'
                    + '<div style="font-size:11px;color:var(--text-dim);margin-top:8px;word-break:break-all">' + esc(couponCode) + '</div></div>';
                showToast('Купон активируется только у партнёра');
            }
        } else if (partnerCode) {
            // Show activation button first (as expected: "должна появляться кнопка активировать")
            var scanDataEl = document.getElementById('scan-data');
            scanDataEl.innerHTML = esc(raw) + '<br><br><div style="background:rgba(14,165,233,0.08);border:1px solid rgba(14,165,233,0.2);border-radius:14px;padding:12px;margin-top:8px">'
                + '<div style="font-weight:700;color:#0EA5E9;margin-bottom:6px">' + icon('store') + ' Партнёрский QR</div>'
                + '<div style="font-size:12px;color:var(--text-dim);margin-bottom:10px;word-break:break-all">' + esc(partnerCode) + '</div>'
                + '<button id="partner-activate-btn" class="prize-modal-btn primary" style="width:100%">' + icon('check') + ' Активировать</button>'
                + '<div id="partner-activate-status" style="margin-top:8px;font-size:13px"></div></div>';
            var btn = document.getElementById('partner-activate-btn');
            var statusEl = document.getElementById('partner-activate-status');
            if (btn) {
                btn.onclick = async function() {
                    btn.disabled = true; btn.textContent = 'Активация...';
                    var partnerResult = await processPartnerScan(partnerCode);
                    if (partnerResult && partnerResult.ok) {
                        try { tg.HapticFeedback.notificationOccurred('success'); } catch(e) {}
                        if (partnerResult.already_scanned) {
                            statusEl.innerHTML = icon('warning') + ' Баллы уже начислены ранее';
                            showToast('Баллы уже начислены');
                            setTimeout(function(){ openPartnerCategory(partnerResult.category_id); }, 800);
                        } else {
                            statusEl.innerHTML = icon('check') + ' +' + partnerResult.points_earned + ' баллов от «' + esc(partnerResult.partner_name) + '»! Баланс: ' + partnerResult.balance;
                            document.getElementById('top-balance').textContent = partnerResult.balance;
                            showToast('+' + partnerResult.points_earned + ' баллов!');
                            btn.textContent = 'Открыть партнёра';
                            btn.disabled = false;
                            btn.onclick = function(){ openPartnerCategory(partnerResult.category_id); };
                        }
                    } else {
                        statusEl.innerHTML = icon('warning') + ' ' + esc((partnerResult && partnerResult.error) || 'Партнёр не найден');
                        btn.disabled = false; btn.textContent = 'Попробовать снова';
                    }
                };
            }
        } else {
            var uid = getUID();
            if (uid && data) {
                try {
                    const r = await fetch(API_BASE + '/scan', { method: 'POST', headers: _h({ 'Content-Type': 'application/json' }), body: JSON.stringify({ user_id: uid, bottle_id: raw }) });
                    const res = await r.json();
                    if (res.ok) {
                        document.getElementById('top-balance').textContent = res.balance;
                        document.getElementById('scan-data').innerHTML = esc(raw) + '<br><br>' + icon('check') + ' +10 баллов! Баланс: ' + res.balance;
                    } else {
                        document.getElementById('scan-data').innerHTML = esc(raw) + '<br><br>' + icon('warning') + ' ' + esc(res.error || 'Ошибка');
                    }
                } catch (e) { document.getElementById('scan-data').innerHTML = esc(raw) + '<br><br>' + icon('warning') + ' Ошибка сети'; }
            }
            // Only for bottles forward to bot as fallback (partner is handled via API activation button)
            try { tg.sendData(raw); } catch(e) {}
        }
    }

    function closeScannerAndBack() {
        if (html5QrCode && html5QrCode.isScanning) html5QrCode.stop().catch(() => {});
        document.getElementById('reader').style.display = 'none';
        document.getElementById('scan-zone').style.display = 'block';
        openPage('menu');
    }

    // ═══════════════════════════════════════════
// PAGE: SCANNER (mini bottles)
// ═══════════════════════════════════════════

function miniScanBottle(seed) {
        var h = 0, i;
        for (i = 0; i < seed.length; i++) { h = ((h << 5) - h) + seed.charCodeAt(i); h |= 0; }
        var idx = Math.abs(h);
        var caps = ['#2B7BE4','#1E90FF','#4169E1','#1877F2','#0066CC','#3B82F6','#0D6EFD','#0B5ED7'];
        var waters = ['rgba(30,144,255,0.3)','rgba(65,105,225,0.25)','rgba(24,119,242,0.3)','rgba(13,110,253,0.25)'];
        var cc = caps[idx % caps.length];
        var wc = waters[idx % waters.length];
        var wl = 35 + (idx % 45);
        return '<div class="sbi-bottle">'
            + '<div class="sbi-cap" style="background:' + cc + '"></div>'
            + '<div class="sbi-body" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06)">'
            + '<div class="sbi-water" style="height:' + wl + '%;background:' + wc + '"></div>'
            + '<div class="sbi-shine"></div></div></div>';
    }

    function bottleSeed(s) {
        var h = 0, i;
        for (i = 0; i < s.length; i++) { h = ((h << 5) - h) + s.charCodeAt(i); h |= 0; }
        return Math.abs(h);
    }

    function bottleCardHTML(code, scanned_at, accent, waterColor) {
        var wl = 35 + (bottleSeed(code) % 50);
        var capColor = accent;
        var waterGrad = 'linear-gradient(180deg, ' + waterColor + ' 0%, ' + waterColor + '80 100%)';
        return '<div class="bottle-card" style="--bottle-accent:' + accent + ';--bottle-water:' + waterColor + '">'
            + '<div class="bc-bottle">'
            + '<div class="bc-cap" style="background:' + capColor + '"></div>'
            + '<div class="bc-neck"></div>'
            + '<div class="bc-body">'
            + '<div class="bc-water" style="height:' + wl + '%;background:' + waterGrad + '"></div>'
            + '<div class="bc-bubble" style="left:30%;bottom:10%"></div>'
            + '<div class="bc-bubble" style="left:60%;bottom:30%;width:3px;height:3px"></div>'
            + '<div class="bc-shine"></div>'
            + '</div></div>'
            + '<div class="bc-info">'
            + '<div class="bc-id">' + esc(code) + '</div>'
            + '<div class="bc-date">' + (scanned_at ? new Date(scanned_at).toLocaleString('ru-RU') : '—') + '</div>'
            + '</div></div>';
    }

    async function renderHistory() {
        const l = document.getElementById('history-list');
        const d = await apiFetch('/history?user_id=' + getUID());
        if (!d || !d.length) { l.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('drop') + '</div><div class="empty-t">Пока пусто</div><div class="empty-d">Сканируй QR-код с бутылки</div></div>'; return; }
        var accents = ['#2B7BE4','#1E90FF','#4169E1','#1877F2','#0066CC','#3B82F6','#0D6EFD','#6366F1','#7C3AED','#8B5CF6','#EC4899','#EF4444','#F59E0B','#10B981','#14B8A6','#06B6D4'];
        var waterColors = ['rgba(30,144,255,0.35)','rgba(65,105,225,0.3)','rgba(24,119,242,0.35)','rgba(99,102,241,0.3)','rgba(124,58,237,0.3)','rgba(139,92,246,0.3)','rgba(236,72,153,0.3)','rgba(16,185,129,0.3)'];
        l.innerHTML = d.map(function(s, i) {
            var idx = bottleSeed(s.code || '');
            var accent = accents[idx % accents.length];
            var wc = waterColors[idx % waterColors.length];
            return bottleCardHTML(s.code, s.scanned_at, accent, wc);
        }).join('');
    }

    // ═══════════════════════════════════════════
// HELPERS (esc, sendToBot, sendDonation)
// ═══════════════════════════════════════════

function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function showToast(msg) {
        var t = document.createElement('div');
        t.textContent = msg;
        Object.assign(t.style, {
            position:'fixed', bottom:'80px', left:'50%', transform:'translateX(-50%)',
            background:'rgba(201,168,76,0.95)', color:'#111318', padding:'10px 20px',
            borderRadius:'12px', fontSize:'13px', fontWeight:'600', zIndex:'9999',
            boxShadow:'0 4px 20px rgba(0,0,0,0.4)', transition:'opacity .3s', opacity:'0'
        });
        document.body.appendChild(t);
        requestAnimationFrame(function() { t.style.opacity = '1'; });
        setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 2000);
    }
    async function doExchange(prizeId) {
        var uid = getUID();
        if (!uid) { showToast('Откройте мини-приложение в Telegram'); return; }
        try { tg.HapticFeedback.impactOccurred('light'); } catch(e) {}
        showToast('Обмен...');
        closePrizeModal();
        try {
            var resp = await fetch(API_BASE + '/exchange', { method:'POST', headers:_h({'Content-Type':'application/json'}), body: JSON.stringify({ user_id: uid, prize_id: prizeId }) });
            var txt = await resp.text(); var res=null; try{res=JSON.parse(txt);}catch(_e){res={ok:false,error:txt};}
            if (resp.ok && res && res.ok) {
                showToast('Заказ #' + res.order_id + ' — ' + res.prize_name);
                document.getElementById('top-balance').textContent = res.balance;
                await loadUserData();
                // also send to bot chat for history (fallback)
                try{ tg.sendData('exchange:'+prizeId); }catch(e){}
                setTimeout(function(){ openPage('my-coupons'); }, 800);
            } else {
                var msg = (res && res.error) || 'Ошибка';
                if (msg==='not_enough_points') msg = 'Недостаточно баллов';
                else if (msg==='prize_not_found') msg='Приз не найден';
                showToast(msg);
                if (res && res.need) showToast('Не хватает ' + res.need + ' баллов');
            }
        } catch(e){ console.error('exchange',e); showToast('Ошибка сети: '+ (e.message||'')); }
        setTimeout(updateNotifBadge, 1500);
    }
    function sendToBot(c) {
        try { tg.HapticFeedback.impactOccurred('light'); } catch(e) {}
        // For exchange now use direct API (doExchange) — keep sendToBot as fallback for donate etc
        if (c && c.indexOf('exchange:') === 0) {
            var pid = parseInt(c.split(':')[1]||0); if (pid) { doExchange(pid); return; }
            showToast('Запрос отправлен — подтвердите обмен в чате бота');
        }
        else if (c && c.indexOf('donate:') === 0) showToast('Пожертвование отправлено');
        else showToast('Отправлено');
        try { tg.sendData(c); } catch(e) { console.warn('sendData failed', e); showToast('Ошибка отправки — откройте бота в Telegram'); }
        setTimeout(updateNotifBadge, 2000);
    }
    function sendDonation() {
        var inp = document.getElementById('donation-amount');
        var amount = parseInt(inp.value);
        if (!amount || amount < 1) {
            if (inp) { inp.style.borderColor = '#EF4444'; inp.animate && inp.animate([{transform:'translateX(0)'},{transform:'translateX(-4px)'},{transform:'translateX(4px)'},{transform:'translateX(0)'}], {duration:300}); }
            showToast('Введите сумму больше 0');
            return;
        }
        inp.style.borderColor = '';
        try { tg.HapticFeedback.notificationOccurred('success'); } catch(e) {}
        showToast('Отправляем пожертвование...');
        try { tg.sendData('donate:' + amount); } catch(e) { showToast('Ошибка — откройте мини-приложение в Telegram'); }
        setTimeout(updateNotifBadge, 2000);
    }

    setInterval(updateNotifBadge, 30000);

    // ═══════════════════════════════════════════
// PAGE: GIFT
// ═══════════════════════════════════════════

// === GIFT SYSTEM ===
    let giftData = null;

    async function checkGift() {
        var uid = getUID();
        if (!uid) return false;
        try {
            var d = await apiFetch('/gift?user_id=' + uid);
            if (d && !d.opened) {
                openPage('gift');
                return true;
            }
        } catch(e) {}
        return false;
    }

    // ═══════════════════════════════════════════
// DAILY BONUS — СЕРИЯ ВХОДОВ (14 дней, шкала 100→200)
// ═══════════════════════════════════════════

var dailyModalOpen = false;
var DAILY_MILESTONES = { 3: 200, 7: 500, 14: 1000 };

    function dailyBasePoints(day) {
        return day <= 5 ? 100 + (day - 1) * 20 : 200;
    }

    function renderDailyScale(day, filledUpTo) {
        var scale = document.getElementById('dbm-scale');
        if (!scale) return;
        scale.innerHTML = '';
        for (var i = 1; i <= 14; i++) {
            var cell = document.createElement('div');
            var cls = 'dbm-day';
            if (i <= filledUpTo) cls += ' done';
            else if (i === day) cls += ' today';
            cell.className = cls;
            cell.style.setProperty('--i', i);
            var ms = DAILY_MILESTONES[i];
            cell.innerHTML = '<span class="dbm-day-num">' + i + '</span>'
                + '<span class="dbm-day-pts">' + dailyBasePoints(i) + '</span>'
                + (ms ? '<span class="dbm-day-bonus">+' + ms + '</span>' : '');
            scale.appendChild(cell);
        }
    }

    async function maybeShowDailyBonus() {
        var uid = getUID();
        if (!uid || dailyModalOpen) return;
        if (currentPage !== 'menu') return;
        var st = await apiFetch('/daily-bonus/status?user_id=' + uid);
        if (!st || !st.enabled || st.claimed_today) return;
        if (currentPage !== 'menu' || dailyModalOpen) return;

        dailyModalOpen = true;
        var day = st.day || 1;
        var points = st.points || 0;
        renderDailyScale(day, day - 1);

        var amount = document.getElementById('dbm-amount');
        var hint = document.getElementById('dbm-hint');
        var closeBtn = document.getElementById('dbm-close-btn');
        amount.classList.remove('show');
        amount.textContent = '';
        hint.textContent = 'Завтра ждём вас за новым бонусом';
        closeBtn.style.display = 'none';
        document.getElementById('daily-bonus-modal').classList.add('active');
        try { tg.HapticFeedback.impactOccurred('medium'); } catch(e) {}

        var r = await apiFetch('/daily-bonus/claim?user_id=' + uid);

        // Заполнение сегодняшнего дня + начисление
        setTimeout(function() {
            var todayCell = document.querySelector('#dbm-scale .dbm-day.today');
            if (todayCell) {
                todayCell.classList.remove('today');
                todayCell.classList.add('done', 'just-done');
            }
            if (r && r.ok) {
                amount.textContent = '+' + (r.points || points) + ' баллов';
                amount.classList.add('show');
                if (typeof r.balance === 'number') {
                    countUp(document.getElementById('top-balance'), r.balance, 900);
                    countUp(document.getElementById('profile-balance'), r.balance, 900);
                }
                try { tg.HapticFeedback.notificationOccurred('success'); } catch(e) {}
            } else if (r && r.already) {
                hint.textContent = 'Сегодняшний бонус уже начислен.';
                if (typeof r.balance === 'number') {
                    countUp(document.getElementById('top-balance'), r.balance, 900);
                }
            } else {
                hint.textContent = 'Не удалось начислить бонус, попробуйте позже.';
            }
        }, 700);
        setTimeout(function() {
            closeBtn.style.display = '';
        }, 1500);
    }

    function closeDailyBonus() {
        var modal = document.getElementById('daily-bonus-modal');
        if (modal) modal.classList.remove('active');
        dailyModalOpen = false;
    }

    function showPostGift(points, balance) {
        document.getElementById('post-gift-points').textContent = '+' + points;
        document.getElementById('post-gift-balance').textContent = balance + ' / ' + (balance + 475) + ' баллов';

        var nearest = giftData ? giftData.nearest_prize : null;
        if (nearest) {
            var pct = nearest.price > 0 ? Math.min(100, Math.round((balance / nearest.price) * 100)) : 0;
            document.getElementById('post-gift-bar').style.width = pct + '%';
            document.getElementById('post-gift-balance').textContent = balance + ' / ' + nearest.price + ' баллов';
            if (nearest.missing > 0) {
                document.getElementById('post-gift-info').innerHTML =
                    'Для получения <strong>' + esc(nearest.name) + '</strong> не хватает <strong>' + nearest.missing + ' баллов</strong>.';
            } else {
                document.getElementById('post-gift-info').innerHTML =
                    'Вы можете обменять баллы на приз <strong>' + esc(nearest.name) + '</strong>!';
            }
        } else {
            document.getElementById('post-gift-info').innerHTML = 'Накапливайте баллы и обменивайте на призы!';
        }
        openPage('post-gift');
    }

    async function openGift() {
        var uid = getUID();
        if (!uid) return;

        var box = document.getElementById('gift-box');
        box.style.pointerEvents = 'none';
        box.style.animation = 'none';

        var opening = document.getElementById('gift-opening');
        opening.classList.add('active');

        createParticles();

        function closeOverlay() {
            opening.classList.remove('active');
            box.style.pointerEvents = '';
            box.style.animation = '';
        }

        var fallbackTimer = setTimeout(function() {
            closeOverlay();
            openPage('menu');
        }, 8000);

        try {
            var controller = new AbortController();
            var timeoutId = setTimeout(function() { controller.abort(); }, 5000);

            var resp = await fetch(API_BASE + '/gift/open', {
                method: 'POST',
                headers: _h({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ user_id: uid }),
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            var result;
            try { result = await resp.json(); } catch(parseErr) { result = null; }

            clearTimeout(fallbackTimer);

            if (result && result.ok) {
                giftData = result;
                setTimeout(function() {
                    document.getElementById('gift-opening-emoji').innerHTML = '' + icon('sparkles') + '';
                    document.getElementById('gift-opening-text').textContent = '+' + result.points + ' баллов!';
                }, 800);

                setTimeout(function() {
                    closeOverlay();
                    showPostGift(result.points, result.balance);
                }, 2000);
            } else {
                setTimeout(function() {
                    closeOverlay();
                    openPage('menu');
                }, 1000);
            }
        } catch(e) {
            clearTimeout(fallbackTimer);
            setTimeout(function() {
                closeOverlay();
                openPage('menu');
            }, 1000);
        }
    }

    function createParticles() {
        var container = document.getElementById('gift-particles');
        container.innerHTML = '';
        var colors = ['#C9A84C', '#E0C46A', '#3D9E6A', '#A855F7', '#EF4444', '#3B82F6'];
        for (var i = 0; i < 20; i++) {
            var p = document.createElement('div');
            p.className = 'gift-particle';
            p.style.left = '50%';
            p.style.top = '50%';
            p.style.background = colors[Math.floor(Math.random() * colors.length)];
            var angle = (Math.PI * 2 * i) / 20;
            var dist = 80 + Math.random() * 120;
            p.style.setProperty('--tx', Math.cos(angle) * dist + 'px');
            p.style.setProperty('--ty', Math.sin(angle) * dist + 'px');
            p.style.animationDelay = (Math.random() * 0.3) + 's';
            container.appendChild(p);
        }
    }

    // ═══════════════════════════════════════════
// PAGE: BOTTLES SHELF
// ═══════════════════════════════════════════

function shelfBottleHTML(code, scanned_at, accent, waterColor) {
        var seed = 0, i;
        for (i = 0; i < code.length; i++) { seed = ((seed << 5) - seed) + code.charCodeAt(i); seed |= 0; }
        var idx = Math.abs(seed);
        var wl = 85 + (idx % 14);
        var capColors = ['#2B7BE4','#1E90FF','#4169E1','#1877F2','#0066CC','#3B82F6','#0D6EFD','#6366F1','#7C3AED','#8B5CF6','#06B6D4','#0891B2','#0EA5E9','#2563EB','#1D4ED8','#3730A3'];
        var waterColors = ['#1E90FF','#1877F2','#2563EB','#3B82F6','#0EA5E9','#38BDF8','#1E40AF','#60A5FA'];
        var cc = capColors[idx % capColors.length];
        var wc = waterColors[idx % waterColors.length];
        var dateStr = scanned_at ? new Date(scanned_at).toLocaleDateString('ru-RU', {day:'numeric',month:'short',year:'numeric'}) : '';
        var animDelay = (idx % 10) * 0.05;
        var bubbleSet = [
            'width:4px;height:4px;left:22%;bottom:10%;animation-delay:0s;animation-duration:2.8s',
            'width:3px;height:3px;left:60%;bottom:35%;animation-delay:.9s;animation-duration:3.2s',
            'width:2px;height:2px;left:40%;bottom:5%;animation-delay:1.8s;animation-duration:3.6s',
            'width:3px;height:3px;left:75%;bottom:20%;animation-delay:2.5s;animation-duration:2.6s',
            'width:2px;height:2px;left:50%;bottom:45%;animation-delay:3.4s;animation-duration:3s',
        ];
        var bubblesHTML = '';
        for (var bi = 0; bi < bubbleSet.length; bi++) {
            bubblesHTML += '<div class="sb-bubble" style="' + bubbleSet[bi] + '"></div>';
        }
        return '<div class="shelf-bottle-wrap" style="animation-delay:' + animDelay + 's" onclick="tg.showAlert(\'' + esc(code) + '\n' + esc(dateStr) + '\')">'
            + '<div class="shelf-bottle">'
            + '<div class="sb-cap" style="background:' + cc + '"><div class="sb-cap-shine"></div><div class="sb-cap-edge"></div></div>'
            + '<div class="sb-neck"></div>'
            + '<div class="sb-body"><div class="sb-body-inner">'
            + '<div class="sb-water" style="height:' + wl + '%;background:' + wc + '"><div class="sb-water-surface"></div></div>'
            + '<div class="sb-shine"></div><div class="sb-shine2"></div>'
            + bubblesHTML
            + '</div></div>'
            + '<div class="sb-base"></div>'
            + '</div>'
            + '</div>';
    }

    async function renderBottles() {
        var c = document.getElementById('bottles-content');
        var uid = getUID();
        if (!uid) { c.innerHTML = '<div class="shelf-empty"><div class="se-ico">' + icon('drop') + '</div><div class="se-t">Нет данных</div></div>'; return; }
        var d = await apiFetch('/history?user_id=' + uid);
        if (!d || !d.length) {
            c.innerHTML = '<div class="shelf-empty"><div class="se-ico">' + icon('drop') + '</div><div class="se-t">Здесь пока пусто</div><div class="se-d">Отсканируй свою первую бутылку<br>и она появится на этой полке!</div></div>';
            return;
        }
        var perShelf = 3;
        var shelves = [];
        for (var i = 0; i < d.length; i += perShelf) {
            shelves.push(d.slice(i, i + perShelf));
        }
        var html = '<div class="shelf-header"><div class="section-title" style="margin-bottom:2px">' + icon('drop') + ' Мои бутылки</div><div class="sh-count">' + d.length + ' ' + (d.length === 1 ? 'бутылка' : (d.length < 5 ? 'бутылки' : 'бутылок')) + ' на полке</div></div><div class="shelf-container">';
        for (var s = 0; s < shelves.length; s++) {
            var shelfBottles = shelves[s];
            html += '<div class="shelf-unit" style="animation-delay:' + (s * 0.1) + 's"><div class="shelf-board"><div class="sb-wall-bg"></div>';
            for (var b = 0; b < shelfBottles.length; b++) {
                var item = shelfBottles[b];
                html += shelfBottleHTML(item.code, item.scanned_at);
            }
            html += '</div><div class="shelf-footer">';
            for (var b = 0; b < shelfBottles.length; b++) {
                var item = shelfBottles[b];
                var dateStr = item.scanned_at ? new Date(item.scanned_at).toLocaleDateString('ru-RU', {day:'numeric',month:'short',year:'numeric'}) : '';
                html += '<div class="shelf-footer-item">';
                html += '<div class="shelf-id">' + esc(item.code) + '</div>';
                html += '<div class="sb-date">' + esc(dateStr) + '</div>';
                html += '</div>';
            }
            html += '</div></div>';
        }
        html += '</div>';
        c.innerHTML = html;
    }

    // ═══════════════════════════════════════════
// PAGE: TREE
// ═══════════════════════════════════════════

async function loadTree() {
        try {
            var uid = getUID();
            const c = document.getElementById('tree-container');
            if (!uid) { c.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('tree') + '</div><div class="empty-t">Нет данных пользователя</div></div>'; return; }
            const d = await apiFetch('/tree?user_id=' + uid);
            if (!d) { c.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('tree') + '</div><div class="empty-t">Ошибка загрузки</div><div class="empty-d">Попробуйте позже</div></div>'; return; }
            const xp = d.xp||0, lv = d.level||1, nx = d.next_level_xp||100, pr = d.progress||0;
            const partners = Array.isArray(d.partners) ? d.partners : [];
            const gift = d.gift || {};
            const sn = TL[lv-1]?.name||'Древо жизни', si = TL[lv-1]?.icon||'' + icon('star') + '', mx = lv>=6;
            let ex = '';
            for (let i=3;i<=12;i++) ex+='<div class="tree-leaf"></div>';
            if (mx) { ex+='<div class="tree-glow"></div>'; for(let i=9;i<=12;i++) ex+='<div class="tree-dot"></div>'; }
            c.innerHTML = `<div class="tree-wrap">
            <div class="tree-stage">${si} ${sn}</div>
            <div class="tree-level-badge">Уровень ${lv}${mx?' ★':''}</div>
            <div class="tree-art tree-l${Math.min(lv,6)}"><div class="tree-ground"></div><div class="tree-trunk"></div><div class="tree-top"></div>${ex}</div>
            <div class="xp-bar-wrap"><div class="xp-label"><span>${icon('bolt')} Опыт</span><span>${xp} ${mx?'★ MAX':'/ '+nx}</span></div><div class="xp-track"><div class="xp-fill" style="width:${mx?100:pr}%"></div></div></div>
        </div>
        ${treeGiftHtml(gift)}
        <div class="tree-stats">
            <div class="tree-stat"><div class="tree-stat-icon">${icon('chart')}</div><div class="tree-stat-val">${xp}</div><div class="tree-stat-lbl">опыта</div></div>
            <div class="tree-stat"><div class="tree-stat-icon">${si}</div><div class="tree-stat-val">${lv}</div><div class="tree-stat-lbl">уровень</div></div>
        </div>
        ${treePartnersHtml(partners)}
        <div class="tree-levels"><div class="tree-levels-title">${icon('clipboard')} Уровни</div>
            ${TL.map(t => {
                const a=t.level===lv, dn=t.level<lv, lk=t.level>lv;
                return `<div class="tree-level-item ${a?'active':dn?'done':'locked'}"><div class="tli-icon">${t.icon}</div><div class="tli-name">${t.name}</div><div class="tli-xp">${t.xp} XP</div><div class="tli-status">${a?icon('star'):dn?icon('check'):icon('lock')}</div></div>`;
            }).join('')}
        </div>`;
        } catch(e) {
            document.getElementById('tree-container').innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('tree') + '</div><div class="empty-t">Ошибка</div><div class="empty-d">' + e.message + '</div></div>';
        }
    }

    // Подарок любимого партнёра на странице дерева
    function treeGiftHtml(gift) {
        if (!gift || !gift.category_id) return '';
        var reason = gift.reason || '';
        if (reason === 'no_favorite') return '';
        if (reason === 'ok' && gift.available) {
            return '<div class="tree-gift-card available">'
                + '<div class="tgc-emoji">' + (gift.icon || '🎁') + '</div>'
                + '<div class="tgc-body">'
                + '<div class="tgc-title">Подарок от партнёра готов!</div>'
                + '<div class="tgc-sub">' + esc(gift.title || 'Любимый партнёр') + ' подготовил вам подарок — заберите его.</div>'
                + '<button class="tgc-btn" onclick="claimPartnerGift()">' + icon('gift') + ' Забрать подарок</button>'
                + '</div></div>';
        }
        if (reason === 'already_claimed') {
            return '<div class="tree-gift-card">'
                + '<div class="tgc-emoji">🎁</div>'
                + '<div class="tgc-body">'
                + '<div class="tgc-title">Подарок уже получен</div>'
                + '<div class="tgc-sub">Партнёр «' + esc(gift.title || '') + '» выдал вам подарок. Найдите его в разделе «Купоны».</div>'
                + '</div></div>';
        }
        if (reason === 'disabled') return '';
        if (gift.threshold > 0) {
            var pct = Math.min(100, Math.round((gift.xp || 0) / gift.threshold * 100));
            return '<div class="tree-gift-card progress">'
                + '<div class="tgc-emoji">' + esc(gift.icon || '🎁') + '</div>'
                + '<div class="tgc-body">'
                + '<div class="tgc-title">Подарок от партнёра «' + esc(gift.title || '') + '»</div>'
                + '<div class="tgc-sub">' + (reason === 'limit_reached' ? 'Лимит подарков этого партнёра исчерпан.' : 'Накопите ' + gift.threshold + ' XP у любимого партнёра — и заберите подарок.') + '</div>'
                + '<div class="tgc-bar"><div class="tgc-fill" style="width:' + pct + '%"></div></div>'
                + '<div class="tgc-count">' + (gift.xp || 0) + ' / ' + gift.threshold + ' XP</div>'
                + '</div></div>';
        }
        return '';
    }

    // Прогресс опыта отдельно по каждому партнёру
    function treePartnersHtml(partners) {
        if (!partners || !partners.length) return '';
        var items = partners.map(function(p) {
            var pct = p.threshold > 0 ? Math.min(100, Math.round(p.xp / p.threshold * 100)) : 0;
            var bar = p.threshold > 0
                ? '<div class="xp-track"><div class="xp-fill" style="width:' + pct + '%"></div></div>'
                : '';
            var badges = '';
            if (p.is_favorite) badges += '<span class="tpi-badge fav">' + icon('star') + ' Любимый партнёр</span>';
            if (p.gift_claimed) badges += '<span class="tpi-badge got">Подарок получен</span>';
            return '<div class="tree-partner-item' + (p.is_favorite ? ' fav' : '') + '">'
                + '<div class="tpi-icon">' + esc(p.icon || '🎁') + '</div>'
                + '<div class="tpi-body">'
                + '<div class="tpi-head"><span class="tpi-name">' + esc(p.title || 'Партнёр') + '</span>'
                + '<span class="tpi-xp">' + p.xp + ' XP</span></div>'
                + bar
                + (p.threshold > 0 ? '<div class="tpi-progress">' + p.xp + ' / ' + p.threshold + ' XP до подарка</div>' : '')
                + (badges ? '<div class="tpi-badges">' + badges + '</div>' : '')
                + '</div></div>';
        }).join('');
        return '<div class="tree-partners"><div class="tree-partners-title">' + icon('heart') + ' Опыт по партнёрам</div>' + items + '</div>';
    }

    // Забрать подарок любимого партнёра
    async function claimPartnerGift() {
        var uid = getUID();
        if (!uid) return;
        var btn = document.querySelector('.tgc-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Получаем подарок...'; }
        try {
            var resp = await fetch(API_BASE + '/tree/gift/claim', {
                method: 'POST',
                headers: _h({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ user_id: uid })
            });
            var result = await resp.json().catch(function () { return {}; });
            if (!resp.ok || !result.ok) {
                var code = result.error || 'internal_error';
                showToast(TREE_GIFT_ERRORS[code] || 'Не удалось получить подарок');
                if (btn) { btn.disabled = false; btn.innerHTML = icon('gift') + ' Забрать подарок'; }
                return;
            }
            showToast('Подарок получен! Покажите QR партнёру');
            await loadTree();
            if (result.coupon) setTimeout(function () { openCouponModal(result.coupon); }, 400);
        } catch (e) {
            showToast('Не удалось получить подарок');
            if (btn) { btn.disabled = false; btn.innerHTML = icon('gift') + ' Забрать подарок'; }
        }
    }

    var TREE_GIFT_ERRORS = {
        no_favorite: 'Пока нет любимого партнёра',
        disabled: 'Подарки этого партнёра отключены',
        not_reached: 'Порог опыта ещё не достигнут',
        already_claimed: 'Подарок уже получен',
        limit_reached: 'Лимит подарков исчерпан',
        gift_not_configured: 'Приз-подарок не настроен',
        internal_error: 'Временная ошибка, попробуйте позже'
    };

    // ═══════════════════════════════════════════
// PAGE: SUPPORT CHAT
// ═══════════════════════════════════════════

var supportChatId = null;
    var supportPollTimer = null;
    var supportLastMsgCount = 0;

    function initSupportIcons() {
        var avatars = document.querySelectorAll('.support-header-avatar .icn');
        var emptyIcons = document.querySelectorAll('.support-empty-icon .icn');
        var sendBtns = document.querySelectorAll('.support-send-btn .icn');
        avatars.forEach(function(el) { var s = icoRaw('chat'); el.innerHTML = s ? '<span class="icn">' + s + '</span>' : '💬'; });
        emptyIcons.forEach(function(el) { var s = icoRaw('chat'); el.innerHTML = s ? '<span class="icn">' + s + '</span>' : '💬'; });
        sendBtns.forEach(function(el) { var s = icoRaw('send'); el.innerHTML = s ? '<span class="icn">' + s + '</span>' : '➤'; });
    }

    function formatDateSep(dateStr) {
        var d = new Date(dateStr);
        var now = new Date();
        var diff = Math.floor((now - d) / 86400000);
        if (diff === 0) return 'Сегодня';
        if (diff === 1) return 'Вчера';
        return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' });
    }

    function formatMsgTime(dateStr) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function shouldShowDateSep(messages, index) {
        if (index === 0) return true;
        var prev = new Date(messages[index - 1].created_at);
        var curr = new Date(messages[index].created_at);
        return prev.toDateString() !== curr.toDateString();
    }

    function isConsecutive(messages, index) {
        if (index === 0) return false;
        var prev = messages[index - 1];
        var curr = messages[index];
        if (prev.sender_type !== curr.sender_type) return false;
        var diff = new Date(curr.created_at) - new Date(prev.created_at);
        return diff < 120000;
    }

    async function openSupport() {
        var uid = getUID();
        if (!uid) return;
        var msgsEl = document.getElementById('support-messages');
        msgsEl.innerHTML = loadingHtml('Загружаем переписку…');

        initSupportIcons();

        try {
            var d = await apiFetch('/support/chat?user_id=' + uid);
            if (d && d.chat) {
                supportChatId = d.chat.id;
                var isClosed = d.chat.status === 'closed';
                var statusEl = document.getElementById('support-status');
                if (isClosed) {
                    statusEl.innerHTML = '<span style="color:var(--red)">Чат закрыт</span>';
                    document.querySelector('.support-input-wrap').style.display = 'none';
                } else {
                    statusEl.innerHTML = '<span class="online-dot"></span><span>Мы онлайн</span>';
                    document.querySelector('.support-input-wrap').style.display = '';
                }
                renderSupportMessages(d.messages || []);
                supportLastMsgCount = (d.messages || []).length;
            }
        } catch(e) {
            var warnIco = icoRaw('warning');
            msgsEl.innerHTML = '<div class="support-empty"><div class="support-empty-icon"><span class="icn">' + (warnIco || '⚠') + '</span></div><div class="support-empty-text">Ошибка загрузки чата</div></div>';
        }

        if (supportPollTimer) clearInterval(supportPollTimer);
        supportPollTimer = setInterval(pollSupportMessages, 5000);
    }

    function renderSupportMessages(messages, keepScrollTop) {
        var el = document.getElementById('support-messages');
        if (!messages.length) {
            var emptyChatIco = icoRaw('chat');
            el.innerHTML = '<div class="support-empty"><div class="support-empty-icon"><span class="icn">' + (emptyChatIco || '💬') + '</span></div><div class="support-empty-text">Напишите нам — мы ответим <strong>в ближайшее время</strong></div></div>';
            return;
        }

        var html = '';
        var shieldIco = icoRaw('shield');
        for (var i = 0; i < messages.length; i++) {
            var m = messages[i];
            var isUser = m.sender_type === 'user';
            var time = formatMsgTime(m.created_at);
            var showDate = shouldShowDateSep(messages, i);
            var groupFirst = i === 0 || !isConsecutive(messages, i);
            var groupLast = i === messages.length - 1 || !isConsecutive(messages, i + 1);

            if (showDate) {
                html += '<div class="support-date-sep"><span>' + formatDateSep(m.created_at) + '</span></div>';
            }

            html += '<div class="support-msg ' + (isUser ? 'support-msg-user' : 'support-msg-admin')
                + (groupFirst ? ' is-first' : '') + (groupLast ? ' is-last' : '') + '">';

            if (!isUser && groupFirst) {
                html += '<div class="support-msg-avatar"><span class="icn">' + (shieldIco || '🛡') + '</span></div>';
            }

            html += '<div class="support-msg-bubble">';
            if (!isUser && groupFirst) {
                html += '<span class="support-msg-sender">Поддержка</span>';
            }
            html += '<span class="support-msg-text">' + esc(m.message) + '</span>';
            html += '<span class="support-msg-meta"><span class="support-msg-time">' + time + '</span>'
                + (isUser ? '<span class="support-msg-status">✓</span>' : '')
                + '</span>';
            html += '</div></div>';
        }

        el.innerHTML = html;

        requestAnimationFrame(function() {
            if (typeof keepScrollTop === 'number') {
                el.scrollTop = keepScrollTop;
            } else {
                el.scrollTop = el.scrollHeight;
            }
        });
    }

    async function sendSupportMessage() {
        var input = document.getElementById('support-input');
        var msg = input.value.trim();
        if (!msg) return;
        var uid = getUID();
        if (!uid) return;

        input.value = '';
        input.disabled = true;
        document.getElementById('support-send-btn').disabled = true;

        var msgsEl = document.getElementById('support-messages');
        var emptyEl = msgsEl.querySelector('.support-empty');
        if (emptyEl) emptyEl.remove();

        var typingEl = document.getElementById('support-typing');
        if (typingEl) typingEl.classList.add('show');

        var msgEl = document.createElement('div');
        msgEl.className = 'support-msg support-msg-user is-first is-last';
        msgEl.innerHTML = '<div class="support-msg-bubble"><span class="support-msg-text">' + esc(msg) + '</span>'
            + '<span class="support-msg-meta">'
            + '<span class="support-msg-time">' + new Date().toLocaleTimeString('ru-RU', {hour:'2-digit', minute:'2-digit'}) + '</span>'
            + '<span class="support-msg-status">✓</span></span></div>';
        msgsEl.appendChild(msgEl);
        msgsEl.scrollTop = msgsEl.scrollHeight;

        try {
            var resp = await fetch(API_BASE + '/support/send', {
                method: 'POST',
                headers: _h({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ user_id: uid, message: msg })
            });
            var result = await resp.json();
            if (result.ok) {
                await pollSupportMessages();
            }
        } catch(e) {}
        if (typingEl) typingEl.classList.remove('show');

        input.disabled = false;
        document.getElementById('support-send-btn').disabled = false;
        input.focus();
    }

    async function pollSupportMessages() {
        var uid = getUID();
        if (!uid || !supportChatId) return;
        try {
            var d = await apiFetch('/support/chat?user_id=' + uid);
            if (d && d.messages) {
                if (d.messages.length !== supportLastMsgCount) {
                    var box = document.getElementById('support-messages');
                    var nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 100;
                    renderSupportMessages(d.messages, nearBottom ? undefined : box.scrollTop);
                    supportLastMsgCount = d.messages.length;
                }
                if (d.chat) {
                    var isClosed = d.chat.status === 'closed';
                    var statusEl = document.getElementById('support-status');
                    if (isClosed) {
                        statusEl.innerHTML = '<span style="color:var(--red)">Чат закрыт</span>';
                    } else {
                        statusEl.innerHTML = '<span class="online-dot"></span><span>Мы онлайн</span>';
                    }
                }
            }
        } catch(e) {}
    }

    document.getElementById('support-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendSupportMessage();
        }
    });

    // ═══════════════════════════════════════════
// AUTO PARTNER SCAN (via deep link)
// ═══════════════════════════════════════════

    (function() {
        try {
            var params = new URLSearchParams(window.location.search);
            var partnerScan = params.get('partner_scan');
            if (partnerScan && partnerScan.startsWith('partner_')) {
                var uid = getUID();
                if (uid) {
                    setTimeout(async function() {
                        var result = await processPartnerScan(partnerScan);
                        if (result && result.ok) {
                            if (result.already_scanned) {
                                showToast('Баллы уже начислены');
                                openPartnerCategory(result.category_id);
                            } else {
                                showToast('+' + result.points_earned + ' баллов от «' + result.partner_name + '»');
                                openPartnerCategory(result.category_id);
                            }
                        } else {
                            showToast('Партнёр не найден');
                        }
                    }, 500);
                }
            }
        } catch(e) {}
    })();