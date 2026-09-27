    window._splashHidden = false;
    var _splashStart = Date.now();
    function hideSplash() {
        var s = document.getElementById('splash');
        if (s && !s.classList.contains('hide')) {
            s.classList.add('hide');
            setTimeout(function(){ if(s.parentNode) s.remove(); }, 600);
        }
        window._splashHidden = true;
        if (window.tryRevealMenu) window.tryRevealMenu();
    }
    async function loadSplashLogo() {
        try {
            var r = await fetch(window.location.origin + '/api/settings');
            var d = await r.json();
            if (d && d.splash_logo_url) {
                var el = document.getElementById('splash-icon');
                if (el) {
                    el.innerHTML = '<img src="' + d.splash_logo_url + '" style="width:80px;height:80px;border-radius:24px;object-fit:cover;background:#111318" alt="logo">';
                }
            }
        } catch(e) {}
    }
    // Hide splash only after the menu data is ready (no blank-screen gap),
    // but never wait longer than 3.5s in total.
    function tryHideSplash() {
        if (window._splashHidden) return;
        if (window._menuDataReady || (Date.now() - _splashStart) >= 3500) {
            hideSplash();
        } else {
            setTimeout(tryHideSplash, 120);
        }
    }
    loadSplashLogo();
    window.addEventListener('load', function() { setTimeout(tryHideSplash, 700); });
    setTimeout(tryHideSplash, 3500);
