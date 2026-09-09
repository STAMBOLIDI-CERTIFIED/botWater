// WaterPrize Admin Login — Telegram auto-login
(async function() {
    try {
        const tg = window.Telegram?.WebApp;
        if (tg && tg.initDataUnsafe && tg.initDataUnsafe.user) {
            const userId = tg.initDataUnsafe.user.id;
            document.getElementById('autoStatus').style.display = 'block';
            document.querySelector('button').disabled = true;
            const resp = await fetch('/admin/auto-login', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({telegram_id: userId})
            });
            if (resp.ok) {
                const data = await resp.json();
                sessionStorage.setItem('admin_token', data.token);
                window.location.href = '/admin?page=dashboard&token=' + data.token;
            } else {
                document.getElementById('autoStatus').textContent = '❌ Доступ запрещён';
                document.querySelector('button').disabled = false;
            }
        }
    } catch(e) {
        console.log('Auto-login error:', e);
    }
})();
