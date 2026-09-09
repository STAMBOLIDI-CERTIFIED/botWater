// ═══════════════════════════════════════════
// TELEGRAM INIT
// ═══════════════════════════════════════════

if (!window.Telegram || !window.Telegram.WebApp) {
        document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:#111318;color:#F0EDE8;font-family:Plus Jakarta Sans,sans-serif;text-align:center;padding:32px"><div><div style="font-size:56px;margin-bottom:14px">' + icon('tree') + '</div><h1 style="font-size:22px;margin-bottom:6px;color:#C9A84C">ИСТОКЪ</h1><p style="color:#5C5854;font-size:13px">Только для Telegram</p></div></div>';
        throw new Error('Not in Telegram');
    }

    const tg = window.Telegram.WebApp;
    tg.ready(); tg.expand();
    try { tg.setBackgroundColor('#111318'); } catch(e) {}
    try { tg.setHeaderColor('#111318'); } catch(e) {}
    try { tg.disableVerticalSwipes(); } catch(e) {}
    document.documentElement.style.colorScheme = 'dark';
    document.body.style.background = '#111318';
    tg.onEvent('themeChanged', function() {
        try { tg.setBackgroundColor('#111318'); } catch(e) {}
        try { tg.setHeaderColor('#111318'); } catch(e) {}
        document.body.style.background = '#111318';
    });

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
    };
    function icon(name, cls) {
        var svg = ICONS[name] || '';
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
                return ICONS[name] ? '<span class="icn">' + ICONS[name] + '</span>' : m;
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
    setTimeout(checkAdmin, 100);

    // ═══════════════════════════════════════════
// API HELPERS
// ═══════════════════════════════════════════

async function apiFetch(p) { try { return await (await fetch(API_BASE + p)).json(); } catch(e) { return null; } }

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

function openAdmin() {
        var uid = getUID();
        if (!uid) return;
        fetch(window.location.origin + '/admin/auto-login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({telegram_id: uid})
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.token) {
                var url = '/admin?page=dashboard&token=' + d.token;
                if (tg.openLink) { tg.openLink(url); }
                else { window.location.href = url; }
            }
        })
        .catch(function() {});
    }

    async function checkAdmin() {
        var uid = getUID();
        if (!uid) return;
        try {
            var d = await apiFetch('/check-admin?user_id=' + uid);
            var el = document.getElementById('card-admin');
            if (el) el.style.display = d && d.is_admin ? '' : 'none';
        } catch(e) {}
    }

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
        if (!uid) return;

        try {
            var rawD = await fetch(API_BASE + '/user?user_id=' + uid);
            var txt = await rawD.text();
            var d = null;
            try { d = JSON.parse(txt); } catch(e) {}

            if (d) {
                var fullName = user.first_name || '';
                if (user.last_name) fullName += ' ' + user.last_name;
                var displayName = d.name || user.first_name || 'Пользователь';
                document.getElementById('top-name').textContent = displayName;
                document.getElementById('profile-name').textContent = displayName;
                document.getElementById('profile-id').innerHTML = 'ID: <span>' + (user.id || d.telegram_id || uid || '—') + '</span>';
                countUp(document.getElementById('top-balance'), d.balance);
                countUp(document.getElementById('profile-balance'), d.balance);
                countUp(document.getElementById('profile-scans'), d.total_scans);
                if (d.photo_url) {
                    console.log('[avatar] photo_url from API:', d.photo_url);
                    setAvatarPhoto(d.photo_url);
                } else if (user.photo_url) {
                    console.log('[avatar] photo_url from TG initData:', user.photo_url);
                    setAvatarPhoto(user.photo_url);
                } else {
                    console.log('[avatar] no photo_url, trying fallback');
                    try {
                        var photoResp = await fetch(API_BASE + '/user-photo?user_id=' + uid);
                        var photoData = await photoResp.json();
                        if (photoData && photoData.photo_url) {
                            console.log('[avatar] photo_url from fallback:', photoData.photo_url);
                            setAvatarPhoto(photoData.photo_url);
                        }
                    } catch(e) {}
                }
            }
            if (!initGiftChecked) {
                initGiftChecked = true;
                await checkGift();
            }

            await updateNotifBadge();
            await checkAdmin();
        } catch(e) {}
    }
    loadUserData();

    setTimeout(function() {
        document.querySelectorAll('.anim-in').forEach(function(el) {
            el.addEventListener('animationend', function() { el.classList.remove('anim-in'); }, { once: true });
        });
    }, 1000);

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
            var ico = n.type === 'scan' ? '' + icon('drop') + '' : n.type === 'points' ? '' + icon('coin') + '' : n.type === 'donation' ? '' + icon('heart') + '' : '' + icon('raffle') + '';
            var cls = n.type === 'scan' ? 'scan' : n.type === 'points' ? 'points' : n.type === 'donation' ? 'donation' : 'raffle';
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
        if (uid) fetch(API_BASE + '/notifications/clear?user_id=' + uid).catch(function(){});
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
            if (page === 'gift' || page === 'post-gift') {
                nav.classList.add('nav-hidden');
            } else {
                nav.classList.remove('nav-hidden');
            }
        }
        if (page === 'menu') loadUserData();
        if (page === 'scanner') {
            var sr = document.getElementById('scan-result');
            if (sr) sr.classList.remove('show');
            var sz = document.getElementById('scan-zone');
            if (sz) sz.style.display = 'block';
            var sb = document.getElementById('scan-btn');
            if (sb) { sb.textContent = 'Включить камеру'; sb.disabled = false; }
        }
        if (page === 'profile') loadUserData();
        if (page === 'history') switchHistoryTab('scans');
        if (page === 'shop') loadShop();
        if (page === 'partners') loadPartners();
        if (page === 'raffles') loadRaffles();
        if (page === 'tree') loadTree();
        if (page === 'bottles') renderBottles();
        if (page === 'gift') {} // gift page is static
    }

    // ═══════════════════════════════════════════
// PAGE: HISTORY
// ═══════════════════════════════════════════

function switchHistoryTab(tab) {
        document.getElementById('tab-scans').className = 'tab-btn' + (tab === 'scans' ? ' active' : '');
        document.getElementById('tab-points').className = 'tab-btn' + (tab === 'points' ? ' active' : '');
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

async function loadShop() {
        const d = await apiFetch('/user?user_id=' + getUID());
        document.getElementById('shop-balance-val').textContent = d ? d.balance : '—';
        const cats = await apiFetch('/shop/categories');
        const g = document.getElementById('shop-categories');
        if (!cats || !cats.length) { g.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('store') + '</div><div class="empty-t">Категории пока пусты</div></div>'; return; }
        g.innerHTML = cats.filter(c => c.is_active).map(function(c, i) {
            var accent = c.color || '#C9A84C';
            return '<div class="shop-cat-card" style="animation-delay:' + (i * 0.06) + 's;border-color:' + accent + '25" onclick="openShopCategory(' + c.id + ')">'
                + '<div class="shop-cat-icon-wrap" style="background:' + accent + '18">'
                + (c.image_url ? '<img src="' + esc(c.image_url) + '">' : '<span>' + esc(c.icon) + '</span>') + '</div>'
                + '<div class="shop-cat-info"><div class="shop-cat-title" style="color:' + accent + '">' + esc(c.title) + '</div>'
                + '<div class="shop-cat-sub">' + esc(c.subtitle) + '</div></div>'
                + '<div class="shop-cat-arrow">›</div></div>';
        }).join('');
    }

    async function openShopCategory(catId) {
        openPage('shop-category');
        const h = document.getElementById('shop-cat-header');
        const g = document.getElementById('shop-cat-items');
        const dForm = document.getElementById('shop-donation');
        h.innerHTML = '<div class="empty-state" style="padding:10px 0"><div class="empty-ico" style="width:40px;height:40px;font-size:20px;margin-bottom:6px">' + icon('hourglass') + '</div><div class="empty-t" style="font-size:13px">Загрузка...</div></div>';
        g.innerHTML = '';
        dForm.style.display = 'none';
        const data = await apiFetch('/shop/categories/' + catId);
        if (!data || !data.category) { h.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('question') + '</div><div class="empty-t">Категория не найдена</div></div>'; return; }
        const cat = data.category;
        const items = data.items || [];
        const d = await apiFetch('/user?user_id=' + getUID());
        const bal = d ? d.balance : 0;
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
            return '<div class="shop-prize-card" style="animation-delay:' + (i * 0.05) + 's">'
                + (p.image_url ? '<img class="shop-prize-img" src="' + esc(p.image_url) + '" onerror="this.style.display=\'none\'">' : '')
                + '<div class="shop-prize-body"><div class="shop-prize-name">' + esc(p.name) + '</div>'
                + '<div class="shop-prize-desc">' + esc(p.description) + '</div>'
                + '<div class="shop-prize-price">' + icon('target') + ' ' + p.price_points + ' баллов</div>'
                + (ok
                    ? '<button class="shop-prize-btn primary" onclick="sendToBot(\'exchange:' + p.id + '\')">' + icon('gift') + ' Обменять</button>'
                    : '<button class="shop-prize-btn outline" disabled>Не хватает ' + missing + ' баллов</button>')
                + '</div></div>';
        }).join('');
    }

    // ═══════════════════════════════════════════
// PAGE: PARTNERS
// ═══════════════════════════════════════════

var partnersData = { categories: [], balance: 0 };

async function loadPartners() {
        var uid = getUID();
        if (!uid) return;
        var d = await apiFetch('/user?user_id=' + uid);
        partnersData.balance = d ? d.balance : 0;
        document.getElementById('partners-balance').textContent = partnersData.balance;

        var maxPoints = 5000;
        var pct = Math.min(100, (partnersData.balance / maxPoints) * 100);
        document.getElementById('partners-bar').style.width = pct + '%';

        var nextMilestone = Math.ceil(partnersData.balance / 500) * 500;
        if (nextMilestone <= partnersData.balance) nextMilestone += 500;
        var needed = Math.max(0, nextMilestone - partnersData.balance);
        document.getElementById('partners-hint').textContent = 'До следующего уровня: ' + needed + ' баллов';

        var cats = await apiFetch('/shop/categories');
        if (!cats) cats = [];
        partnersData.categories = cats.filter(function(c) {
            return c.is_active && c.subtitle && c.subtitle.toLowerCase().indexOf('партнёр') !== -1;
        });

        var g = document.getElementById('partners-categories');
        var itemsGrid = document.getElementById('partners-items');
        itemsGrid.style.display = 'none';

        if (!partnersData.categories.length) {
            g.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('store') + '</div><div class="empty-t">Партнёры пока отсутствуют</div><div class="empty-d">Скоро здесь появятся товары от партнёров</div></div>';
            return;
        }

        g.innerHTML = partnersData.categories.map(function(c, i) {
            var accent = c.color || '#0EA5E9';
            return '<div class="partner-cat-card" style="animation-delay:' + (i * 0.06) + 's;border-color:' + accent + '25" onclick="openPartnerCategory(' + c.id + ')">'
                + '<div class="partner-cat-icon" style="background:' + accent + '18">'
                + (c.image_url ? '<img src="' + esc(c.image_url) + '">' : '<span>' + esc(c.icon) + '</span>') + '</div>'
                + '<div class="partner-cat-info"><div class="partner-cat-title" style="color:' + accent + '">' + esc(c.title) + '</div>'
                + '<div class="partner-cat-sub">' + esc(c.subtitle) + '</div></div>'
                + '<div class="partner-cat-arrow">›</div></div>';
        }).join('');
    }

    async function openPartnerCategory(catId) {
        var g = document.getElementById('partners-categories');
        var itemsGrid = document.getElementById('partners-items');
        var itemsContainer = document.getElementById('partners-items-grid');

        g.innerHTML = '<div class="empty-state" style="padding:10px 0"><div class="empty-ico" style="width:40px;height:40px;font-size:20px;margin-bottom:6px">' + icon('hourglass') + '</div><div class="empty-t" style="font-size:13px">Загрузка...</div></div>';
        itemsGrid.style.display = 'none';

        var data = await apiFetch('/shop/categories/' + catId);
        if (!data || !data.category) {
            g.innerHTML = '<div class="empty-state"><div class="empty-ico">' + icon('question') + '</div><div class="empty-t">Категория не найдена</div></div>';
            return;
        }

        var cat = data.category;
        var items = data.items || [];
        var bal = partnersData.balance;

        var headerHtml = '<div class="partner-back-btn" onclick="loadPartners()"><span class="bb-icon">‹</span> Назад</div>'
            + '<div style="text-align:center;padding:6px 0 14px;animation:fadeIn .4s var(--ease-out)">'
            + (cat.image_url ? '<img src="' + esc(cat.image_url) + '" style="width:72px;height:72px;border-radius:20px;object-fit:cover;margin-bottom:10px;border:2px solid ' + (cat.color || '#0EA5E9') + ';box-shadow:0 0 30px ' + (cat.color || '#0EA5E9') + '20">'
                : '<div style="font-size:40px;margin-bottom:8px;animation:emojiBounce .6s ease">' + esc(cat.icon) + '</div>')
            + '<div style="font-size:22px;font-weight:800;color:' + (cat.color || '#0EA5E9') + '">' + esc(cat.title) + '</div>'
            + '<div style="font-size:13px;color:var(--text-dim);margin-top:4px">' + esc(cat.subtitle) + '</div></div>';

        g.innerHTML = headerHtml;

        if (!items.length) {
            itemsGrid.style.display = 'block';
            itemsContainer.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="empty-ico" style="font-size:36px">' + esc(cat.icon) + '</div><div class="empty-t">Товаров пока нет</div><div class="empty-d">Скоро здесь появятся товары</div></div>';
            return;
        }

        itemsGrid.style.display = 'grid';
        itemsContainer.innerHTML = items.map(function(p, i) {
            var ok = bal >= p.price_points;
            var missing = Math.max(0, p.price_points - bal);
            return '<div class="partner-item-card" style="animation-delay:' + (i * 0.05) + 's">'
                + (p.image_url ? '<img class="partner-item-img" src="' + esc(p.image_url) + '" onerror="this.style.display=\'none\'">' : '')
                + '<div class="partner-item-body"><div class="partner-item-name">' + esc(p.name) + '</div>'
                + '<div class="partner-item-desc">' + esc(p.description) + '</div>'
                + '<div class="partner-item-price">' + icon('target') + ' ' + p.price_points + ' баллов</div>'
                + (ok
                    ? '<button class="partner-item-btn primary" onclick="sendToBot(\'exchange:' + p.id + '\')">' + icon('gift') + ' Обменять</button>'
                    : '<button class="partner-item-btn outline" disabled>Не хватает ' + missing + ' баллов</button>')
                + '</div></div>';
        }).join('');
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

function startScan() {
        const btn = document.getElementById('scan-btn'), reader = document.getElementById('reader'), zone = document.getElementById('scan-zone');
        document.getElementById('scan-result').classList.remove('show');
        btn.textContent = 'Запуск...'; btn.disabled = true;
        if (html5QrCode && html5QrCode.isScanning) html5QrCode.stop().then(() => doScan());
        else { html5QrCode = new Html5Qrcode('reader'); doScan(); }
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
        var uid = getUID();
        if (uid && data) {
            try {
                const r = await fetch(API_BASE + '/scan', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: uid, bottle_id: data }) });
                const res = await r.json();
                if (res.ok) {
                    document.getElementById('top-balance').textContent = res.balance;
                    document.getElementById('scan-data').textContent = data + '\n\n' + icon('check') + ' +10 баллов! Баланс: ' + res.balance;
                } else {
                    document.getElementById('scan-data').textContent = data + '\n\n' + icon('warning') + ' ' + (res.error || 'Ошибка');
                }
            } catch (e) { document.getElementById('scan-data').textContent = data + '\n\n' + icon('warning') + ' Ошибка сети'; }
        }
        tg.sendData(data);
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
    function sendToBot(c) { tg.sendData(c); setTimeout(updateNotifBadge, 2000); }
    function sendDonation() {
        var inp = document.getElementById('donation-amount');
        var amount = parseInt(inp.value);
        if (!amount || amount < 1) { inp.style.borderColor = '#EF4444'; return; }
        inp.style.borderColor = '';
        tg.sendData('donate:' + amount);
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
                headers: { 'Content-Type': 'application/json' },
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
        <div class="tree-stats">
            <div class="tree-stat"><div class="tree-stat-icon">${icon('chart')}</div><div class="tree-stat-val">${xp}</div><div class="tree-stat-lbl">опыта</div></div>
            <div class="tree-stat"><div class="tree-stat-icon">${si}</div><div class="tree-stat-val">${lv}</div><div class="tree-stat-lbl">уровень</div></div>
        </div>
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