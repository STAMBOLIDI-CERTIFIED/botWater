<?php
class WaterPrize_PDF_Export {

    public static function export_batch($batch, $year) {
        if (!current_user_can('manage_options')) wp_die('Forbidden');

        $db = WaterPrize_DB::instance();
        $bottles = $db->query(
            "SELECT bottle_id FROM bottles WHERE batch = ? AND year = ? ORDER BY id ASC",
            [$batch, $year]
        );

        if (empty($bottles)) {
            wp_die('Нет бутылок для экспорта из партии ' . esc_html($batch));
        }
        ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>QR-коды — Партия <?php echo esc_html($batch); ?> (<?php echo esc_html($year); ?>)</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; }
.print-btn {
    position: fixed; top: 20px; right: 20px; z-index: 1000;
    padding: 12px 24px; font-size: 16px; font-weight: bold;
    background: #2271b1; color: #fff; border: none; border-radius: 6px;
    cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.print-btn:hover { background: #135e96; }
.header { text-align: center; padding: 15px; font-size: 14px; color: #333; border-bottom: 1px solid #ddd; }
.grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    padding: 10px;
    max-width: 210mm;
    margin: 0 auto;
}
.qr-cell {
    text-align: center;
    padding: 3px;
    page-break-inside: avoid;
}
.qr-cell img { width: 20mm; height: 20mm; display: block; margin: 0 auto 2px; }
.qr-cell .label { font-size: 5pt; color: #333; word-break: break-all; }
@media print {
    .print-btn { display: none !important; }
    body { margin: 0; }
    .header { padding: 8px 0; }
    .grid { gap: 3px; padding: 5px; }
    .qr-cell img { width: 18mm; height: 18mm; }
}
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨️ Печать / Сохранить PDF</button>
<div class="header">
    <strong>Партия:</strong> <?php echo esc_html($batch); ?> &nbsp;|&nbsp;
    <strong>Год:</strong> <?php echo esc_html($year); ?> &nbsp;|&nbsp;
    <strong>Бутылок:</strong> <?php echo count($bottles); ?>
</div>
<div class="grid">
<?php foreach ($bottles as $b): ?>
    <div class="qr-cell">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($b['bottle_id']); ?>&format=png&margin=0"
             alt="<?php echo esc_attr($b['bottle_id']); ?>" loading="lazy">
        <div class="label"><?php echo esc_html($b['bottle_id']); ?></div>
    </div>
<?php endforeach; ?>
</div>
</body>
</html>
<?php
        exit;
    }
}
