<?php if (!defined('ABSPATH')) exit;
$gift_statuses = ['claimed' => 'Выдан', 'expired' => 'Истёк', 'revoked' => 'Отозван'];
$gift_colors = ['claimed' => 'green', 'expired' => 'yellow', 'revoked' => 'red'];
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🎁 Подарки от любимого партнёра <span class="wpz-count"><?php echo esc_html(count($gifts)); ?></span></h1>

    <!-- ═══ Статистика ═══ -->
    <div class="wpz-stats-grid">
        <div class="wpz-stat-card wpz-green">
            <div class="wpz-stat-icon">🎁</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['total'] ?? 0); ?></div>
            <div class="wpz-stat-label">Всего выдано</div>
        </div>
        <div class="wpz-stat-card wpz-blue">
            <div class="wpz-stat-icon">✅</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['claimed'] ?? 0); ?></div>
            <div class="wpz-stat-label">Активных</div>
        </div>
        <div class="wpz-stat-card wpz-purple">
            <div class="wpz-stat-icon">📅</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['last_30d'] ?? 0); ?></div>
            <div class="wpz-stat-label">За 30 дней</div>
        </div>
        <div class="wpz-stat-card wpz-red">
            <div class="wpz-stat-icon">↩️</div>
            <div class="wpz-stat-val"><?php echo esc_html($stats['revoked'] ?? 0); ?></div>
            <div class="wpz-stat-label">Отозвано</div>
        </div>
    </div>

    <!-- ═══ Настройки ═══ -->
    <div class="wpz-card">
        <h2>⚙️ Настройки подарка</h2>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="action" value="save_gift_settings">
            <table class="form-table">
                <tr>
                    <th><label for="gift_prize_id">Приз-подарок</label></th>
                    <td>
                        <select id="gift_prize_id" name="gift_prize_id">
                            <option value="0">— не выбран (выдача отключена) —</option>
                            <?php foreach ($prizes as $p): ?>
                                <option value="<?php echo esc_attr($p['id']); ?>"
                                    <?php selected($values['gift_prize_id'], (int)$p['id']); ?>>
                                    <?php echo esc_html($p['name']); ?> (<?php echo esc_html($p['price_points']); ?> баллов)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Приз, который получает пользователь после «тряски» дерева. Себестоимость подарка = балловой стоимости приза.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gift_ttl_days">Срок действия купона</label></th>
                    <td>
                        <input type="number" id="gift_ttl_days" name="gift_ttl_days" min="0" max="365"
                               value="<?php echo esc_attr($values['gift_ttl_days']); ?>" class="small-text">
                        <span class="description">дней. 0 — без ограничения.</span>
                    </td>
                </tr>
            </table>
            <?php submit_button('Сохранить настройки подарка'); ?>
        </form>
        <p class="description">
            Порог опыта и лимит выдач задаются <strong>индивидуально для каждого партнёра</strong>
            в разделе «Партнёры» → карточка партнёра. Порог = 0 — подарки этого партнёра выключены.
        </p>
    </div>

    <!-- ═══ Выданные подарки ═══ -->
    <div class="wpz-card">
        <div class="wpz-table-header">
            <h2>Выданные подарки</h2>
            <form method="get" class="wpz-search-row">
                <input type="hidden" name="page" value="wpz-gifts">
                <select name="g_partner">
                    <option value="0">Все партнёры</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo esc_attr($c['id']); ?>" <?php selected($partner_id, (int)$c['id']); ?>>
                            <?php echo esc_html($c['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Фильтровать</button>
                <?php if ($partner_id): ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpz-gifts')); ?>">Сбросить</a>
                <?php endif; ?>
            </form>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Партнёр</th>
                    <th>Приз</th>
                    <th>Порог / опыт</th>
                    <th>Выдан</th>
                    <th>Срок</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($gifts)): ?>
                    <tr><td colspan="8">Подарков пока не выдано</td></tr>
                <?php else: foreach ($gifts as $g): ?>
                    <tr>
                        <td><?php echo esc_html($g['id']); ?></td>
                        <td>
                            <?php echo esc_html($g['user_name'] ?: '—'); ?><br>
                            <code><?php echo esc_html($g['telegram_id']); ?></code>
                        </td>
                        <td>
                            <?php echo esc_html($g['partner_title'] ?: '—'); ?>
                            <?php if (!empty($g['coupon_status'])): ?>
                                <br><small>купон: <code><?php echo esc_html($g['coupon_status']); ?></code></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($g['prize_name'] ?: '—'); ?></td>
                        <td><?php echo esc_html($g['threshold_xp']); ?> / <?php echo esc_html($g['partner_xp']); ?> XP</td>
                        <td><?php echo $g['created_at'] ? date('Y-m-d H:i', strtotime($g['created_at'])) : '—'; ?></td>
                        <td><?php echo $g['expires_at'] ? date('Y-m-d H:i', strtotime($g['expires_at'])) : '—'; ?></td>
                        <td>
                            <?php $color = $gift_colors[$g['status']] ?? 'gray'; ?>
                            <span class="wpz-badge wpz-<?php echo $color; ?>">
                                <?php echo esc_html($gift_statuses[$g['status']] ?? $g['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ═══ Любимые партнёры ═══ -->
    <div class="wpz-card">
        <h2>Любимые партнёры</h2>
        <p class="description">
            Партнёр с наибольшим числом подтверждённых оплаченных заказов (approved/completed);
            при равенстве — партнёр с более последней покупкой. Опыт хранится отдельно по каждому партнёру.
        </p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Любимый партнёр</th>
                    <th>Покупок</th>
                    <th>Последняя покупка</th>
                    <th>Опыт партнёра</th>
                    <th>Порог подарка</th>
                    <th>Выдано подарков</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($favorites)): ?>
                    <tr><td colspan="7">Пока нет подтверждённых заказов</td></tr>
                <?php else: foreach ($favorites as $f): ?>
                    <tr>
                        <td>
                            <?php echo esc_html($f['user_name'] ?: '—'); ?><br>
                            <code><?php echo esc_html($f['telegram_id']); ?></code>
                        </td>
                        <td><?php echo esc_html($f['partner_title'] ?: '—'); ?></td>
                        <td><?php echo esc_html($f['purchases']); ?></td>
                        <td><?php echo $f['last_purchase'] ? date('Y-m-d H:i', strtotime($f['last_purchase'])) : '—'; ?></td>
                        <td><?php echo esc_html($f['partner_xp']); ?> XP</td>
                        <td>
                            <?php $threshold = (int)$f['gift_threshold']; ?>
                            <?php if ($threshold <= 0): ?>
                                <span class="wpz-badge wpz-gray">выключен</span>
                            <?php else: ?>
                                <span class="wpz-badge wpz-blue"><?php echo esc_html($threshold); ?> XP</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($f['gifts_claimed']); ?><?php echo (int)$f['gift_limit'] > 0 ? ' / ' . esc_html($f['gift_limit']) : ''; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ═══ Опыт по партнёрам ═══ -->
    <div class="wpz-card">
        <h2>Опыт по партнёрам</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Партнёр</th>
                    <th>Пользователей с опытом</th>
                    <th>Суммарный опыт</th>
                    <th>Порог подарка</th>
                    <th>Лимит выдач</th>
                    <th>Выдано подарков</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary)): ?>
                    <tr><td colspan="6">Нет данных</td></tr>
                <?php else: foreach ($summary as $s): ?>
                    <tr>
                        <td><?php echo esc_html($s['title'] ?: '—'); ?></td>
                        <td><?php echo esc_html($s['users_with_xp']); ?></td>
                        <td><?php echo esc_html($s['total_xp']); ?> XP</td>
                        <td>
                            <?php if ((int)$s['gift_threshold'] <= 0): ?>
                                <span class="wpz-badge wpz-gray">выключен</span>
                            <?php else: ?>
                                <span class="wpz-badge wpz-blue"><?php echo esc_html($s['gift_threshold']); ?> XP</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)$s['gift_limit'] > 0 ? esc_html($s['gift_limit']) : '—'; ?></td>
                        <td><?php echo esc_html($s['gifts_issued']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
