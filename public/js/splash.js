    (function() {
        var host = document.getElementById('splash-dust');
        if (!host) return;
        var frag = document.createDocumentFragment();
        for (var i = 0; i < 18; i++) {
            var b = document.createElement('span');
            b.className = 'sp-dust';
            var sz = 2 + Math.random() * 4;
            b.style.width = sz + 'px';
            b.style.height = sz + 'px';
            b.style.left = (Math.random() * 100).toFixed(1) + '%';
            b.style.top = (Math.random() * 100).toFixed(1) + '%';
            b.style.animationDuration = (3.5 + Math.random() * 4.5).toFixed(2) + 's';
            b.style.animationDelay = '-' + (Math.random() * 6).toFixed(2) + 's';
            frag.appendChild(b);
        }
        host.appendChild(frag);
    })();

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
