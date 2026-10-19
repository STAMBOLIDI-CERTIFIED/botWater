<?php if (!defined('ABSPATH')) exit;
$ru_months = [1=>'январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь'];
$month_label = mb_convert_case($ru_months[(int)date('n', strtotime($month_start))] . ' ' . date('Y', strtotime($month_start)), MB_CASE_TITLE, 'UTF-8');
$fmt = function ($v) { return number_format((int)$v, 0, '', ' '); };
$dt = function ($v) { return $v ? date('d.m.Y H:i', strtotime($v)) : '—'; };
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Отчёт за <?php echo esc_html($month_label); ?> — ISTOK</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 12px; color: #1d1d1f; padding: 16px; }
.print-btn {
    position: fixed; top: 20px; right: 20px; z-index: 1000;
    padding: 12px 24px; font-size: 16px; font-weight: bold;
    background: #2271b1; color: #fff; border: none; border-radius: 6px;
    cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.print-btn:hover { background: #135e96; }
h1 { font-size: 20px; margin-bottom: 4px; }
.sub { color: #555; margin-bottom: 14px; }
h2 { font-size: 15px; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #2271b1; }
.cards { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; }
.card { border: 1px solid #ccd0d4; border-radius: 8px; padding: 10px 14px; min-width: 150px; }
.card b { display: block; font-size: 20px; }
.card span { color: #666; font-size: 11px; }
.card.red b { color: #d63638; }
.card.green b { color: #00a32a; }
table { width: 100%; border-collapse: collapse; margin-bottom: 12px; page-break-inside: auto; }
th, td { border: 1px solid #ccd0d4; padding: 5px 7px; text-align: left; vertical-align: top; }
th { background: #f0f0f1; font-size: 11px; }
tfoot td, tfoot th { background: #f6f7f7; font-weight: bold; }
.num { text-align: right; white-space: nowrap; }
.red { color: #d63638; font-weight: bold; }
.green { color: #00a32a; font-weight: bold; }
code { font-size: 10px; }
tr { page-break-inside: avoid; }
.foot { margin-top: 14px; color: #888; font-size: 10px; }
@media print {
    .print-btn { display: none !important; }
    body { padding: 0; font-size: 10px; }
    h1 { font-size: 16px; }
    h2 { font-size: 13px; }
    .card b { font-size: 15px; }
}
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨️ Печать / Сохранить PDF</button>

<h1>Отчёт за <?php echo esc_html($month_label); ?></h1>
<div class="sub">ISTOK — использованные купоны и взаиморасчёты · сформировано <?php echo esc_html(date('d.m.Y H:i')); ?></div>

<div class="cards">
    <div class="card"><b><?php echo $fmt($totals['cnt']); ?></b><span>Использовано купонов</span></div>
    <div class="card"><b><?php echo $fmt($totals['nominal']); ?></b><span>Номинал, баллов</span></div>
    <div class="card"><b><?php echo $fmt($totals['commission']); ?></b><span>Комиссия 10% за месяц</span></div>
    <div class="card"><b><?php echo $fmt($totals['paid_month']); ?></b><span>Оплачено за месяц</span></div>
    <div class="card <?php echo $totals['debt'] > 0 ? 'red' : 'green'; ?>">
        <b><?php echo $fmt($totals['debt']); ?></b><span>Долг ISTOK на конец месяца</span></div>
</div>

<h2>Взаиморасчёты по партнёрам</h2>
<table>
    <thead>
        <tr>
            <th>Партнёр</th><th>Категория</th>
            <th class="num">Купонов за мес.</th><th class="num">Начислено за мес.</th>
            <th class="num">Оплачено за мес.</th><th class="num">Начислено всего</th>
            <th class="num">Оплачено всего</th><th class="num">Долг</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($settlements as $s): ?>
        <tr>
            <td><?php echo esc_html($s['name'] ?: '—'); ?> <code><?php echo esc_html($s['telegram_id']); ?></code></td>
            <td><?php echo esc_html($s['category_title'] ?: '—'); ?></td>
            <td class="num"><?php echo $fmt($s['used_month']); ?></td>
            <td class="num"><?php echo $fmt($s['accrued_month']); ?></td>
            <td class="num"><?php echo $fmt($s['paid_month']); ?></td>
            <td class="num"><?php echo $fmt($s['accrued_total']); ?></td>
            <td class="num"><?php echo $fmt($s['paid_total']); ?></td>
            <td class="num <?php echo $s['debt'] > 0 ? 'red' : 'green'; ?>"><?php echo $fmt($s['debt']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">Итого</td>
            <td class="num"><?php echo $fmt($totals['used_month']); ?></td>
            <td class="num"><?php echo $fmt($totals['accrued_month']); ?></td>
            <td class="num"><?php echo $fmt($totals['paid_month']); ?></td>
            <td class="num"><?php echo $fmt($totals['accrued_total']); ?></td>
            <td class="num"><?php echo $fmt($totals['paid_total']); ?></td>
            <td class="num <?php echo $totals['debt'] > 0 ? 'red' : 'green'; ?>"><?php echo $fmt($totals['debt']); ?></td>
        </tr>
    </tfoot>
</table>

<h2>Журнал оплат за <?php echo esc_html($month_label); ?></h2>
<?php if (empty($journal)): ?>
    <p style="color:#777;margin-bottom:10px;">Оплат за месяц нет.</p>
<?php else: ?>
<table>
    <thead><tr><th>Партнёр</th><th class="num">Сумма</th><th>Комментарий</th><th>Кто внёс</th><th>Дата записи</th></tr></thead>
    <tbody>
        <?php foreach ($journal as $j): ?>
        <tr>
            <td><?php echo esc_html($j['account_name'] ?: '—'); ?> <code><?php echo esc_html($j['telegram_id']); ?></code></td>
            <td class="num"><?php echo $fmt($j['amount']); ?></td>
            <td><?php echo esc_html($j['comment'] ?: '—'); ?></td>
            <td><?php echo esc_html($j['created_by'] ?: '—'); ?></td>
            <td><?php echo esc_html($dt($j['created_at'])); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>Использованные купоны (<?php echo count($usage); ?>)</h2>
<?php if (empty($usage)): ?>
    <p style="color:#777;">В этом месяце купоны не использовались.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Дата</th><th>Пользователь</th><th>Приз</th>
            <th class="num">Номинал</th><th class="num">Комиссия</th>
            <th>Категория</th><th>Активировал</th><th>Источник</th><th>QR-код</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($usage as $u): ?>
        <tr>
            <td><?php echo esc_html($dt($u['used_at'])); ?></td>
            <td><?php echo esc_html($u['user_name'] ?: '—'); ?> <code><?php echo esc_html($u['telegram_id']); ?></code></td>
            <td><?php echo esc_html($u['prize_name'] ?: '—'); ?></td>
            <td class="num"><?php echo $fmt($u['nominal'] ?? 0); ?></td>
            <td class="num"><?php echo $fmt($u['commission'] ?? 0); ?></td>
            <td><?php echo esc_html($u['category_title'] ?: '—'); ?></td>
            <td><?php echo esc_html($u['activator_name'] ?: ($u['activator_tg'] ? 'TG ' . $u['activator_tg'] : 'не указан')); ?></td>
            <td><?php echo esc_html($u['source_title'] ?: '—'); ?></td>
            <td><code><?php echo esc_html($u['qr_code']); ?></code></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">Итого: <?php echo count($usage); ?></td>
            <td class="num"><?php echo $fmt($totals['nominal']); ?></td>
            <td class="num"><?php echo $fmt($totals['commission']); ?></td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<div class="foot">Комиссия ISTOK — 10% от балловой стоимости погашенного купона, удерживается с партнёра-активатора. Долг накопительно уменьшается на суммы из журнала оплат.</div>
</body>
</html>
