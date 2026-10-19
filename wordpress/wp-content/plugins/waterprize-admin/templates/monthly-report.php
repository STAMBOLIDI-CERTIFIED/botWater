<?php if (!defined('ABSPATH')) exit;
$month_start = $month . '-01';
$ru_months = [1=>'январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь'];
$month_label = $ru_months[(int)date('n', strtotime($month_start))] . ' ' . date('Y', strtotime($month_start));
$month_label = mb_convert_case($month_label, MB_CASE_TITLE, 'UTF-8');
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>📄 Отчёт за месяц — <?php echo esc_html($month_label); ?></h1>

    <!-- ═══ Выбор месяца + экспорт ═══ -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="page" value="wpz-monthly-report">
            <select name="month">
                <?php foreach ($months as $m): ?>
                <option value="<?php echo esc_attr($m); ?>" <?php selected($month, $m); ?>>
                    <?php echo esc_html($ru_months[(int)substr($m, 5, 2)] . ' ' . substr($m, 0, 4)); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-primary">Показать</button>

            <span style="flex:1"></span>

            <a class="button" href="?page=wpz-monthly-report&amp;month=<?php echo esc_attr($month); ?>&amp;export=coupons">⬇️ CSV: купоны</a>
            <a class="button" href="?page=wpz-monthly-report&amp;month=<?php echo esc_attr($month); ?>&amp;export=settlements">⬇️ CSV: взаиморасчёты</a>
            <a class="button" href="?page=wpz-monthly-report&amp;month=<?php echo esc_attr($month); ?>&amp;print=1" target="_blank">🖨️ Печать / PDF</a>
        </form>
    </div>

    <!-- ═══ Итоги ═══ -->
    <div class="wpz-stats-grid" style="margin-bottom:20px;">
        <div class="wpz-stat-card wpz-purple">
            <div class="wpz-stat-icon">🎫</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($totals['cnt']); ?></div>
            <div class="wpz-stat-label">Использовано купонов</div>
        </div>
        <div class="wpz-stat-card wpz-blue">
            <div class="wpz-stat-icon">🪙</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($totals['nominal']); ?></div>
            <div class="wpz-stat-label">Номинал, баллов</div>
        </div>
        <div class="wpz-stat-card wpz-orange">
            <div class="wpz-stat-icon">💳</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($totals['commission']); ?></div>
            <div class="wpz-stat-label">Комиссия 10% (за месяц)</div>
        </div>
        <div class="wpz-stat-card <?php echo $totals['paid_month'] > 0 ? 'wpz-green' : 'wpz-blue'; ?>">
            <div class="wpz-stat-icon">💰</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($totals['paid_month']); ?></div>
            <div class="wpz-stat-label">Оплачено за месяц</div>
        </div>
        <div class="wpz-stat-card <?php echo $totals['debt'] > 0 ? 'wpz-red' : 'wpz-green'; ?>">
            <div class="wpz-stat-icon">⚠️</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($totals['debt']); ?></div>
            <div class="wpz-stat-label">Долг ISTOK на конец месяца</div>
        </div>
    </div>

    <!-- ═══ Взаиморасчёты по партнёрам ═══ -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>🤝 Взаиморасчёты по партнёрам</h2>
        <p style="color:#646970;font-size:12px;margin:-6px 0 12px;">
            Долг = 10% от номинала купонов, погашенных на точке партнёра (всё время), минус оплаты из журнала (по конец выбранного месяца).
        </p>
        <?php if (empty($settlements)): ?>
            <p style="color:#666;text-align:center;padding:16px">Аккаунтов партнёров нет</p>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Партнёр</th>
                    <th>Категория</th>
                    <th style="width:105px">Купонов за мес.</th>
                    <th style="width:115px">Начислено за мес.</th>
                    <th style="width:115px">Оплачено за мес.</th>
                    <th style="width:115px">Начислено всего</th>
                    <th style="width:115px">Оплачено всего</th>
                    <th style="width:110px">Долг</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($settlements as $s): ?>
                <tr>
                    <td>
                        <b><?php echo esc_html($s['name'] ?: '—'); ?></b><br>
                        <code style="font-size:11px"><?php echo esc_html($s['telegram_id']); ?></code>
                    </td>
                    <td><?php echo esc_html($s['category_title'] ?: '—'); ?></td>
                    <td><?php echo number_format_i18n($s['used_month']); ?></td>
                    <td><?php echo number_format_i18n($s['accrued_month']); ?></td>
                    <td><?php echo number_format_i18n($s['paid_month']); ?></td>
                    <td><?php echo number_format_i18n($s['accrued_total']); ?></td>
                    <td><?php echo number_format_i18n($s['paid_total']); ?></td>
                    <td><b style="color:<?php echo $s['debt'] > 0 ? '#d63638' : '#00a32a'; ?>">
                        <?php echo number_format_i18n($s['debt']); ?></b></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">Итого</th>
                    <th><?php echo number_format_i18n($totals['used_month']); ?></th>
                    <th><?php echo number_format_i18n($totals['accrued_month']); ?></th>
                    <th><?php echo number_format_i18n($totals['paid_month']); ?></th>
                    <th><?php echo number_format_i18n($totals['accrued_total']); ?></th>
                    <th><?php echo number_format_i18n($totals['paid_total']); ?></th>
                    <th style="color:<?php echo $totals['debt'] > 0 ? '#d63638' : '#00a32a'; ?>">
                        <?php echo number_format_i18n($totals['debt']); ?></th>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>

    <!-- ═══ Новая оплата ═══ -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>➕ Зафиксировать оплату</h2>
        <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="report_month" value="<?php echo esc_attr($month); ?>">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Партнёр</label>
                <select name="account_id" required style="min-width:200px;">
                    <option value="">— Выберите —</option>
                    <?php foreach ($accounts as $a): ?>
                    <option value="<?php echo (int)$a['id']; ?>">
                        <?php echo esc_html(($a['name'] ?: '—') . ' (TG ' . $a['telegram_id'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Месяц</label>
                <select name="period_month">
                    <?php foreach (array_slice($months, 0, 6) as $m): ?>
                    <option value="<?php echo esc_attr($m); ?>" <?php selected($month, $m); ?>>
                        <?php echo esc_html($ru_months[(int)substr($m, 5, 2)] . ' ' . substr($m, 0, 4)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Сумма, баллы</label>
                <input type="number" name="amount" min="1" step="1" required style="width:120px;">
            </div>
            <div style="flex:1;min-width:200px;">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Комментарий</label>
                <input type="text" name="comment" placeholder="Например: перевод за октябрь" style="width:100%;">
            </div>
            <button type="submit" name="action" value="add_settlement" class="button button-primary">💾 Записать</button>
        </form>
    </div>

    <!-- ═══ Журнал оплат ═══ -->
    <div class="wpz-card" style="margin-bottom:20px;">
        <h2>📒 Журнал оплат за <?php echo esc_html($month_label); ?></h2>
        <?php if (empty($journal)): ?>
            <p style="color:#666;text-align:center;padding:16px">Оплат за этот месяц нет</p>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:140px">Партнёр</th>
                    <th style="width:110px">Сумма</th>
                    <th>Комментарий</th>
                    <th style="width:140px">Кто внёс</th>
                    <th style="width:140px">Дата записи</th>
                    <th style="width:70px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($journal as $j): ?>
                <tr>
                    <td><?php echo esc_html(($j['account_name'] ?: '—')); ?>
                        <br><code style="font-size:11px"><?php echo esc_html($j['telegram_id']); ?></code></td>
                    <td><b><?php echo number_format_i18n($j['amount']); ?></b></td>
                    <td><?php echo esc_html($j['comment'] ?: '—'); ?></td>
                    <td><?php echo esc_html($j['created_by'] ?: '—'); ?></td>
                    <td><?php echo esc_html(date('d.m.Y H:i', strtotime($j['created_at']))); ?></td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Удалить запись журнала?')">
                            <?php wp_nonce_field('wpz_action'); ?>
                            <input type="hidden" name="report_month" value="<?php echo esc_attr($month); ?>">
                            <input type="hidden" name="settlement_id" value="<?php echo (int)$j['id']; ?>">
                            <button type="submit" name="action" value="delete_settlement" class="button button-small button-link-delete">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ═══ Использованные купоны ═══ -->
    <div class="wpz-card">
        <h2>🎫 Использованные купоны за <?php echo esc_html($month_label); ?> — <?php echo count($usage); ?></h2>
        <?php if (empty($usage)): ?>
            <p style="color:#666;text-align:center;padding:16px">В этом месяце купоны не использовались</p>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:130px">Дата</th>
                    <th>Пользователь</th>
                    <th>Приз</th>
                    <th style="width:95px">Номинал</th>
                    <th style="width:95px">Комиссия</th>
                    <th>Категория</th>
                    <th>Активировал</th>
                    <th>Источник</th>
                    <th style="width:150px">QR-код</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usage as $u): ?>
                <tr>
                    <td><?php echo esc_html($u['used_at'] ? date('d.m.Y H:i', strtotime($u['used_at'])) : '—'); ?></td>
                    <td><?php echo esc_html($u['user_name'] ?: '—'); ?>
                        <br><code style="font-size:11px"><?php echo esc_html($u['telegram_id']); ?></code></td>
                    <td><?php echo esc_html($u['prize_name'] ?: '—'); ?></td>
                    <td><?php echo number_format_i18n($u['nominal'] ?? 0); ?></td>
                    <td><b><?php echo number_format_i18n($u['commission'] ?? 0); ?></b></td>
                    <td><?php echo esc_html($u['category_title'] ?: '—'); ?></td>
                    <td>
                        <?php if ($u['activator_name'] || $u['activator_tg']): ?>
                            <?php echo esc_html($u['activator_name'] ?: '—'); ?>
                            <br><code style="font-size:11px"><?php echo esc_html($u['activator_tg']); ?></code>
                        <?php else: ?>
                            <span style="color:#999">не указан</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($u['source_title'] ?: '—'); ?></td>
                    <td><code style="font-size:11px"><?php echo esc_html($u['qr_code']); ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Итого: <?php echo count($usage); ?> купонов</th>
                    <th><?php echo number_format_i18n($totals['nominal']); ?></th>
                    <th><?php echo number_format_i18n($totals['commission']); ?></th>
                    <th colspan="4"></th>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>
