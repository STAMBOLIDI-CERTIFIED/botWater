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
        '<div class="info-grid">' +
        '<div class="info-item"><div class="info-label">ID</div><div class="info-value">' + id + '</div></div>' +
        '<div class="info-item"><div class="info-label">Telegram ID</div><div class="info-value"><code>' + tg_id + '</code></div></div>' +
        '<div class="info-item"><div class="info-label">Баланс</div><div class="info-value">' + balance + ' баллов</div></div>' +
        '<div class="info-item"><div class="info-label">Телефон</div><div class="info-value">' + (phone || '—') + '</div></div>' +
        '</div>' +
        (fio ? '<div class="section-divider"></div><div class="info-grid"><div class="info-item"><div class="info-label">ФИО</div><div class="info-value">' + fio + '</div></div><div class="info-item"><div class="info-label">Паспорт</div><div class="info-value">' + snumber + '</div></div><div class="info-item"><div class="info-label">ИНН</div><div class="info-value">' + inn + '</div></div></div>' : '');
    document.getElementById('modalTgId').value = tg_id;
    document.getElementById('modalTgId2').value = tg_id;
    document.getElementById('userModal').classList.add('show');
}

function showUserModalFromAttrs(btn) {
    var d = btn.dataset;
    showUserModal(
        d.userId, d.userName, d.userTg, d.userBalance,
        d.userPhone, d.userPassportFio, d.userPassportSnumber, d.userPassportInn
    );
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

    // Auto-dismiss success/error messages after 5 seconds
    document.querySelectorAll('.success-msg, .error-msg').forEach(function(msg) {
        setTimeout(function() {
            msg.style.transition = 'opacity 300ms, transform 300ms';
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-8px)';
            setTimeout(function() { msg.remove(); }, 300);
        }, 5000);
    });
});
