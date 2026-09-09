// QR Scanner Logic
const tg = window.Telegram.WebApp;
tg.ready();
tg.expand();

tg.BackButton.show();
tg.BackButton.onClick(() => {
    stopScanner();
    tg.close();
});

let html5QrCode = null;

function handleResult(data) {
    const resultDiv = document.getElementById('result');
    const scanBtn = document.getElementById('scanBtn');
    resultDiv.style.display = 'block';
    resultDiv.textContent = '✅ ' + data;
    scanBtn.textContent = 'Открыть сканер';
    scanBtn.disabled = false;
    stopScanner();

    if (tg.initDataUnsafe) {
        tg.sendData(data);
    }
}

function stopScanner() {
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop().catch(() => {});
    }
    document.getElementById('reader').style.display = 'none';
}

function startScan() {
    if (tg.initDataUnsafe && typeof tg.showScanQrPopup === 'function') {
        tg.showScanQrPopup(
            { text: 'Наведите камеру на QR-код' },
            function(data) {
                if (data) {
                    handleResult(data);
                    tg.closeScanQrPopup();
                    return true;
                }
                return false;
            }
        );
        return;
    }

    const scanBtn = document.getElementById('scanBtn');
    const readerDiv = document.getElementById('reader');
    scanBtn.textContent = 'Идёт сканирование...';
    scanBtn.disabled = true;
    readerDiv.style.display = 'block';

    html5QrCode = new Html5Qrcode('reader');
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => handleResult(decodedText),
        () => {}
    ).catch(() => {
        scanBtn.textContent = '⚠️ Камера недоступна';
        scanBtn.disabled = false;
        readerDiv.style.display = 'none';
    });
}
