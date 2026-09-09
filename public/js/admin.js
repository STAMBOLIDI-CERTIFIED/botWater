// WaterPrize Admin Panel JS

// Sidebar toggle
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}

// Admin token persistence
(function() {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    if (token) {
        sessionStorage.setItem('admin_token', token);
    }
    const stored = sessionStorage.getItem('admin_token');
    if (stored) {
        document.querySelectorAll('a[href^="/admin"]').forEach(function(a) {
            var url = new URL(a.href, window.location.origin);
            if (!url.searchParams.has('token')) {
                url.searchParams.set('token', stored);
                a.href = url.toString();
            }
        });
    }
})();

// Codes page: mark as won
function showMarkWon(codeId) {
    document.getElementById('wonCodeId').value = codeId;
    document.getElementById('markWonModal').classList.add('show');
}

// Users page: show user modal
function showUserModal(id, name, tg_id, balance, phone, fio, snumber, inn) {
    document.getElementById('modalTitle').textContent = '👤 ' + (name || 'Без имени');
    document.getElementById('modalBody').innerHTML =
        '<div style="font-size:13px;line-height:1.8;">' +
        '<b>ID:</b> ' + id + '<br>' +
        '<b>Telegram ID:</b> <code>' + tg_id + '</code><br>' +
        '<b>Баланс:</b> ' + balance + ' баллов<br>' +
        '<b>Телефон:</b> ' + (phone || '—') + '<br>' +
        (fio ? '<hr style="border-color:var(--border);margin:8px 0;"><b>ФИО:</b> ' + fio + '<br><b>Паспорт:</b> ' + snumber + '<br><b>ИНН:</b> ' + inn : '') +
        '</div>';
    document.getElementById('modalTgId').value = tg_id;
    document.getElementById('modalTgId2').value = tg_id;
    document.getElementById('userModal').classList.add('show');
}

// Bottles page: QR modal
function showQR(bottleId) {
    var token = sessionStorage.getItem('admin_token');
    var params = '?id=' + encodeURIComponent(bottleId);
    if (token) params += '&token=' + encodeURIComponent(token);
    document.getElementById('qrModalTitle').textContent = 'QR-код';
    document.getElementById('qrModalImg').src = '/admin/view/qr/' + encodeURIComponent(bottleId) + (token ? '?token=' + encodeURIComponent(token) : '');
    document.getElementById('qrModalId').textContent = bottleId;
    document.getElementById('qrDownloadLink').href = '/admin/download/single' + params;
    document.getElementById('qrModal').classList.add('show');
}
function hideQR() {
    var modal = document.getElementById('qrModal');
    modal.classList.remove('show');
    document.getElementById('qrModalImg').src = '';
}

// Modal close on overlay click
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('show');
        });
    });
});
