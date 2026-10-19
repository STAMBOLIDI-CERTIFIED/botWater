<?php if (!defined('ABSPATH')) exit;
$db = WaterPrize_DB::instance();
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>

    <?php if (!empty($detail)): ?>
        <?php
        $detail_stats = null;
        foreach ($stats as $s) {
            if ((int)$s['id'] === (int)$detail['id']) { $detail_stats = $s; break; }
        }
        $paid_all = 0;
        foreach ($detail_payments as $p) $paid_all += (int)$p['amount'];
        ?>
        <h1>
            <a href="?page=wpz-partner-cabinet" style="text-decoration:none">←</a>
            📊 <?php echo esc_html($detail['name'] ?: 'Партнёр #' . $detail['id']); ?>
        </h1>
        <p style="color:#646970;">
            Telegram ID: <code><?php echo esc_html($detail['telegram_id']); ?></code>
            <?php if ($detail_stats): ?>
                · Категория: <b><?php echo esc_html(($detail_stats['category_icon'] ?: '') . ' ' . ($detail_stats['category_title'] ?: '—')); ?></b>
                · Статус: <?php echo $detail['is_active'] ? '✅ активен' : '⏸ неактивен'; ?>
            <?php endif; ?>
        </p>

        <?php if ($detail_stats): ?>
        <div class="wpz-stats-grid" style="margin-bottom:20px;">
            <div class="wpz-stat-card wpz-blue">
                <div class="wpz-stat-icon">🎫</div>
                <div class="wpz-stat-val"><?php echo number_format_i18n($detail_stats['coupons_received']); ?></div>
                <div class="wpz-stat-label">Купонов получено</div>
            </div>
            <div class="wpz-stat-card wpz-purple">
                <div class="wpz-stat-icon">✅</div>
                <div class="wpz-stat-val"><?php echo number_format_i18n($detail_stats['coupons_used']); ?></div>
                <div class="wpz-stat-label">Использовано у него</div>
            </div>
            <div class="wpz-stat-card <?php echo $detail_stats['must_pay_istok'] > 0 ? 'wpz-red' : 'wpz-green'; ?>">
                <div class="wpz-stat-icon">💳</div>
                <div class="wpz-stat-val"><?php echo number_format_i18n($detail_stats['must_pay_istok']); ?></div>
                <div class="wpz-stat-label">Должен ISTOK (остаток)</div>
            </div>
            <div class="wpz-stat-card wpz-green">
                <div class="wpz-stat-icon">💰</div>
                <div class="wpz-stat-val"><?php echo number_format_i18n($detail_stats['earned_as_source']); ?></div>
                <div class="wpz-stat-label">Заработал источником</div>
            </div>
            <div class="wpz-stat-card wpz-orange">
                <div class="wpz-stat-icon">👥</div>
                <div class="wpz-stat-val"><?php echo number_format_i18n($detail_stats['referred_users']); ?></div>
                <div class="wpz-stat-label">Привёл пользователей</div>
            </div>
        </div>

        <div class="wpz-card" style="margin-bottom:20px;">
            <h2>Взаиморасчёты с ISTOK</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Начислено (10% от погашенных купонов)</th>
                        <th>Оплачено</th>
                        <th>Долг ISTOK</th>
                        <th>Номинал погашенных купонов</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><b><?php echo number_format_i18n($detail_stats['accrued_istok']); ?></b> баллов</td>
                        <td><b><?php echo number_format_i18n($detail_stats['paid_istok']); ?></b> баллов</td>
                        <td><b style="color:<?php echo $detail_stats['must_pay_istok'] > 0 ? '#d63638' : '#00a32a'; ?>">
                            <?php echo number_format_i18n($detail_stats['must_pay_istok']); ?></b> баллов</td>
                        <td><?php echo number_format_i18n($detail_stats['used_nominal']); ?> баллов</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="wpz-card" style="margin-bottom:20px;">
            <h2>Использованные купоны (<?php echo count($detail_coupons); ?>)</h2>
            <?php if (empty($detail_coupons)): ?>
                <p style="color:#666;text-align:center;padding:16px">Купонов пока не погашали</p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:140px">Дата</th>
                        <th>Пользователь</th>
                        <th>Приз</th>
                        <th style="width:110px">Номинал</th>
                        <th style="width:180px">QR-код</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail_coupons as $c): ?>
                    <tr>
                        <td><?php echo esc_html($c['used_at'] ? date('d.m.Y H:i', strtotime($c['used_at'])) : '—'); ?></td>
                        <td><?php echo esc_html($c['user_name'] ?: '—'); ?>
                            <code style="font-size:11px"><?php echo esc_html($c['telegram_id']); ?></code></td>
                        <td><?php echo esc_html($c['prize_name'] ?: '—'); ?></td>
                        <td><?php echo esc_html($c['nominal'] ?? '—'); ?></td>
                        <td><code style="font-size:11px"><?php echo esc_html($c['qr_code']); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="wpz-card" style="margin-bottom:20px;">
            <h2>Журнал оплат ISTOK (<?php echo count($detail_payments); ?>, всего <?php echo number_format_i18n($paid_all); ?> баллов)</h2>
            <?php if (empty($detail_payments)): ?>
                <p style="color:#666;text-align:center;padding:16px">Оплат пока нет — фиксируйте их в разделе «Отчёт за месяц»</p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:100px">Месяц</th>
                        <th style="width:120px">Сумма</th>
                        <th>Комментарий</th>
                        <th style="width:150px">Кто внёс</th>
                        <th style="width:140px">Дата записи</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail_payments as $p): ?>
                    <tr>
                        <td><?php echo esc_html(date('m.Y', strtotime($p['period_month']))); ?></td>
                        <td><b><?php echo number_format_i18n($p['amount']); ?></b></td>
                        <td><?php echo esc_html($p['comment'] ?: '—'); ?></td>
                        <td><?php echo esc_html($p['created_by'] ?: '—'); ?></td>
                        <td><?php echo esc_html(date('d.m.Y H:i', strtotime($p['created_at']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="wpz-card">
            <h2>Приведённые пользователи (первый скан — его QR) — <?php echo count($detail_referred); ?></h2>
            <?php if (empty($detail_referred)): ?>
                <p style="color:#666;text-align:center;padding:16px">Пользователей пока нет</p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:60px">ID</th>
                        <th>Имя</th>
                        <th style="width:160px">Telegram ID</th>
                        <th style="width:110px">Баланс</th>
                        <th style="width:160px">Первый скан</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail_referred as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r['id']); ?></td>
                        <td><?php echo esc_html($r['name'] ?: '—'); ?>
                            <?php if (!empty($r['username'])): ?><code style="font-size:11px">@<?php echo esc_html($r['username']); ?></code><?php endif; ?></td>
                        <td><code><?php echo esc_html($r['telegram_id']); ?></code></td>
                        <td><?php echo number_format_i18n($r['balance']); ?></td>
                        <td><?php echo esc_html($r['scanned_at'] ? date('d.m.Y H:i', strtotime($r['scanned_at'])) : '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    <?php else: ?>

    <h1>📊 Кабинеты партнёров</h1>
    <p style="color:#646970;font-size:13px;margin-bottom:16px;">
        <b>Купонов получено</b> — выпущено на призы его категории ·
        <b>Использовано</b> — погашено на его точке ·
        <b>Должен ISTOK</b> — 10% от номинала погашенных купонов за вычетом оплат из журнала ·
        <b>Заработал источником</b> — реально начисленные баллы за приведённых пользователей ·
        <b>Привёл</b> — пользователи, чей первый скан был его QR.
    </p>

    <?php if (empty($stats)): ?>
        <div class="wpz-card"><p style="color:#666;text-align:center;padding:20px">Аккаунтов партнёров нет</p></div>
    <?php else: ?>
    <div class="wpz-card">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th>Партнёр</th>
                    <th>Категория</th>
                    <th style="width:105px">Купонов получено</th>
                    <th style="width:105px">Использовано</th>
                    <th style="width:115px">Номинал</th>
                    <th style="width:115px">Должен ISTOK</th>
                    <th style="width:125px">Заработал источником</th>
                    <th style="width:105px">Привёл</th>
                    <th style="width:110px">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $t_recv = $t_used = $t_nom = $t_acc = $t_paid = $t_debt = $t_earned = $t_ref = 0;
                foreach ($stats as $s):
                    $t_recv += $s['coupons_received'];
                    $t_used += $s['coupons_used'];
                    $t_nom += $s['used_nominal'];
                    $t_acc += $s['accrued_istok'];
                    $t_paid += $s['paid_istok'];
                    $t_debt += $s['must_pay_istok'];
                    $t_earned += $s['earned_as_source'];
                    $t_ref += $s['referred_users'];
                ?>
                <tr>
                    <td><?php echo esc_html($s['id']); ?></td>
                    <td>
                        <b><?php echo esc_html($s['name'] ?: '—'); ?></b><br>
                        <code style="font-size:11px"><?php echo esc_html($s['telegram_id']); ?></code>
                        <?php if (!$s['is_active']): ?><span style="color:#999;font-size:11px">(неактивен)</span><?php endif; ?>
                    </td>
                    <td><?php echo esc_html(trim(($s['category_icon'] ?: '') . ' ' . ($s['category_title'] ?: '—'))); ?></td>
                    <td><?php echo number_format_i18n($s['coupons_received']); ?></td>
                    <td><?php echo number_format_i18n($s['coupons_used']); ?></td>
                    <td><?php echo number_format_i18n($s['used_nominal']); ?></td>
                    <td>
                        <b style="color:<?php echo $s['must_pay_istok'] > 0 ? '#d63638' : '#00a32a'; ?>">
                            <?php echo number_format_i18n($s['must_pay_istok']); ?></b>
                    </td>
                    <td><b style="color:#00a32a"><?php echo number_format_i18n($s['earned_as_source']); ?></b></td>
                    <td><?php echo number_format_i18n($s['referred_users']); ?></td>
                    <td>
                        <a href="?page=wpz-partner-cabinet&account=<?php echo (int)$s['id']; ?>" class="button button-small">Подробнее</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Итого</th>
                    <th><?php echo number_format_i18n($t_recv); ?></th>
                    <th><?php echo number_format_i18n($t_used); ?></th>
                    <th><?php echo number_format_i18n($t_nom); ?></th>
                    <th><?php echo number_format_i18n($t_debt); ?></th>
                    <th><?php echo number_format_i18n($t_earned); ?></th>
                    <th><?php echo number_format_i18n($t_ref); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        <p style="color:#646970;font-size:12px;margin-top:10px;">
            Начислено ISTOK всего: <b><?php echo number_format_i18n($t_acc); ?></b> баллов ·
            Оплачено: <b><?php echo number_format_i18n($t_paid); ?></b> баллов ·
            Долг: <b style="color:<?php echo $t_debt > 0 ? '#d63638' : '#00a32a'; ?>"><?php echo number_format_i18n($t_debt); ?></b> баллов
        </p>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
