    window._splashHidden = false;
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
    loadSplashLogo();
    window.addEventListener('load', function() { setTimeout(hideSplash, 1000); });
    setTimeout(hideSplash, 3000);