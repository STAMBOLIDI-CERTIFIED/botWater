<?php if (!defined('ABSPATH')) exit;
$stats = $stats ?? [];
$reg_chart = $reg_chart ?? [];
$top_balances = $top_balances ?? [];
$by_level = $by_level ?? [];
$banned_count = $banned_count ?? 0;
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>👥 Пользователи <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <!-- ═══ Statistics Cards ═══ -->
    <div class="wpz-stats-grid">
        <div class="wpz-stat-card wpz-blue">
            <div class="wpz-stat-icon">👥</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['total'] ?? 0); ?></div>
            <div class="wpz-stat-label">Всего</div>
        </div>
        <div class="wpz-stat-card wpz-green">
            <div class="wpz-stat-icon">💰</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['with_balance'] ?? 0); ?></div>
            <div class="wpz-stat-label">С балансом</div>
        </div>
        <div class="wpz-stat-card wpz-gold">
            <div class="wpz-stat-icon">⭐</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['total_balance'] ?? 0); ?></div>
            <div class="wpz-stat-label">Всего баллов</div>
        </div>
        <div class="wpz-stat-card wpz-purple">
            <div class="wpz-stat-icon">📊</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['avg_balance'] ?? 0); ?></div>
            <div class="wpz-stat-label">Средний баланс</div>
        </div>
        <div class="wpz-stat-card wpz-teal">
            <div class="wpz-stat-icon">📱</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['with_phone'] ?? 0); ?></div>
            <div class="wpz-stat-label">С телефоном</div>
        </div>
        <div class="wpz-stat-card wpz-indigo">
            <div class="wpz-stat-icon">🪪</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['with_passport'] ?? 0); ?></div>
            <div class="wpz-stat-label">С паспортом</div>
        </div>
        <div class="wpz-stat-card wpz-orange">
            <div class="wpz-stat-icon">🌳</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['above_level1'] ?? 0); ?></div>
            <div class="wpz-stat-label">Уровень 2+</div>
        </div>
        <div class="wpz-stat-card wpz-cyan wpz-pulse">
            <div class="wpz-stat-icon">🟢</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['online'] ?? 0); ?></div>
            <div class="wpz-stat-label">Онлайн</div>
        </div>
        <div class="wpz-stat-card wpz-green">
            <div class="wpz-stat-icon">📅</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['new_today'] ?? 0); ?></div>
            <div class="wpz-stat-label">Сегодня</div>
        </div>
        <div class="wpz-stat-card wpz-blue">
            <div class="wpz-stat-icon">📆</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($stats['new_month'] ?? 0); ?></div>
            <div class="wpz-stat-label">За месяц</div>
        </div>
        <div class="wpz-stat-card wpz-red">
            <div class="wpz-stat-icon">🚫</div>
            <div class="wpz-stat-val"><?php echo number_format_i18n($banned_count); ?></div>
            <div class="wpz-stat-label">Забанены</div>
        </div>
    </div>

    <!-- ═══ Charts Row ═══ -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:16px;">
        <!-- Registrations Chart -->
        <div class="wpz-card" style="margin:0;">
            <h2>📈 Регистрации (30 дней)</h2>
            <div style="height:160px;position:relative;">
                <canvas id="wpz-users-reg-chart"></canvas>
            </div>
        </div>

        <!-- Top Balances -->
        <div class="wpz-card" style="margin:0;">
            <h2>🏆 Топ по балансу</h2>
            <?php if (empty($top_balances)): ?>
                <p style="color:#666;font-size:13px;">Нет данных</p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed" style="border:0;">
                <tbody>
                    <?php foreach ($top_balances as $i => $u): ?>
                    <tr style="border:0;">
                        <td style="border:0;padding:6px 8px;width:30px;font-weight:700;color:<?php echo ['#b8860b','#666','#8B4513','#2271b1','#666'][$i] ?? '#666'; ?>">
                            <?php echo ($i + 1); ?>.
                        </td>
                        <td style="border:0;padding:6px 8px;">
                            <strong><?php echo esc_html($u['name'] ?: '—'); ?></strong>
                            <br><small style="color:#999;">Lvl <?php echo esc_html($u['tree_level']); ?></small>
                        </td>
                        <td style="border:0;padding:6px 8px;text-align:right;">
                            <strong style="color:#b8860b;"><?php echo number_format_i18n($u['balance']); ?></strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ Users by Level ═══ -->
    <?php if (!empty($by_level)): ?>
    <div class="wpz-card" style="margin-bottom:16px;">
        <h2>🌳 Уровни деревьев</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php
            $level_names = [1 => 'Росток', 2 => 'Саженец', 3 => 'Молодое', 4 => 'Крепкое', 5 => 'Могучее', 6 => 'Древо'];
            $level_colors = [1 => '#81C784', 2 => '#66BB6A', 3 => '#4CAF50', 4 => '#43A047', 5 => '#388E3C', 6 => '#2E7D32'];
            foreach ($by_level as $l):
                $lv = $l['tree_level'];
            ?>
            <div style="flex:1;min-width:100px;text-align:center;padding:10px 8px;background:<?php echo $level_colors[$lv] ?? '#666'; ?>15;border:1px solid <?php echo $level_colors[$lv] ?? '#666'; ?>30;border-radius:8px;">
                <div style="font-size:20px;font-weight:700;color:<?php echo $level_colors[$lv] ?? '#666'; ?>;"><?php echo esc_html($l['cnt']); ?></div>
                <div style="font-size:11px;color:#666;">Lvl <?php echo esc_html($lv); ?> · <?php echo esc_html($level_names[$lv] ?? ''); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ Balance Form ═══ -->
    <div class="wpz-card">
        <h2>💰 Начислить / Списать баллы</h2>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div style="flex:1;min-width:140px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Telegram ID</label>
                    <input type="number" name="telegram_id" required placeholder="Telegram ID"
                           class="regular-text" style="width:100%;box-sizing:border-box;">
                </div>
                <div style="flex:1;min-width:100px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Кол-во</label>
                    <input type="number" name="amount" required placeholder="+100 / -50"
                           class="regular-text" style="width:100%;box-sizing:border-box;">
                </div>
                <div style="flex:2;min-width:160px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Причина</label>
                    <input type="text" name="reason" placeholder="Причина"
                           class="regular-text" style="width:100%;box-sizing:border-box;">
                </div>
                <div style="flex:0 0 auto;">
                    <button type="submit" name="action" value="add_balance"
                            class="button button-primary" style="margin-top:18px;">💾 Применить</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ═══ Tree XP Form ═══ -->
    <div class="wpz-card">
        <h2>🌳 Начислить опыт для дерева</h2>
        <p style="margin:0 0 12px;color:#666;font-size:13px;">Уровни: Росток (0) → Саженец (100) → Молодое (500) → Крепкое (1000) → Могучее (2000) → Древо (5000)</p>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div style="flex:1;min-width:140px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Telegram ID</label>
                    <input type="number" name="telegram_id" required placeholder="Telegram ID"
                           class="regular-text" style="width:100%;box-sizing:border-box;">
                </div>
                <div style="flex:1;min-width:100px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Опыт (XP)</label>
                    <input type="number" name="xp_amount" required placeholder="+50 / -10"
                           class="regular-text" style="width:100%;box-sizing:border-box;">
                </div>
                <div style="flex:2;min-width:160px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Причина</label>
                    <input type="text" name="xp_reason" placeholder="Причина"
                           class="regular-text" style="width:100%;box-sizing:border-box;">
                </div>
                <div style="flex:0 0 auto;">
                    <button type="submit" name="action" value="add_tree_xp"
                            class="button button-primary" style="margin-top:18px;">🌳 Применить</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ═══ Search ═══ -->
    <div class="wpz-card">
        <form method="get" class="wpz-search-form" style="margin-bottom:0;">
            <input type="hidden" name="page" value="wpz-users">
            <input type="search" name="search" placeholder="Поиск по имени, телефону или ID..."
                   value="<?php echo esc_attr($search); ?>" style="flex:1;min-width:200px;">
            <button type="submit" class="button button-primary">🔍 Найти</button>
            <?php if ($search): ?>
                <a href="<?php echo admin_url('admin.php?page=wpz-users'); ?>" class="button">Сброс</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ═══ Users Table ═══ -->
    <div class="wpz-card">
        <div class="wpz-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Telegram ID</th>
                        <th>Имя</th>
                        <th>Телефон</th>
                        <th style="width:80px">Баланс</th>
                        <th style="width:60px">XP</th>
                        <th style="width:60px">Ур.</th>
                        <th style="width:60px">Паспорт</th>
                        <th style="width:90px">Дата</th>
                        <th style="width:180px">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="10">Нет пользователей<?php echo $search ? ' по запросу «' . esc_html($search) . '»' : ''; ?></td></tr>
                    <?php else: foreach ($users as $u): ?>
                        <tr<?php echo !empty($u['is_banned']) ? ' style="background:#fff5f5;"' : ''; ?>>
                            <td><?php echo esc_html($u['id']); ?></td>
                            <td><code style="font-size:12px;"><?php echo esc_html($u['telegram_id']); ?></code></td>
                            <td>
                                <strong><?php echo esc_html($u['name'] ?: '—'); ?></strong>
                                <?php if (!empty($u['username'])): ?>
                                    <br><small style="color:#999;">@<?php echo esc_html($u['username']); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($u['is_banned'])): ?>
                                    <br><span class="wpz-badge wpz-red" title="<?php echo esc_attr($u['ban_reason'] ?: 'Заблокирован'); ?>">🚫 Забанен</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($u['phone'] ?: '—'); ?></td>
                            <td><strong style="color:#b8860b;"><?php echo esc_html($u['balance']); ?></strong></td>
                            <td><?php echo esc_html($u['tree_xp']); ?></td>
                            <td><span class="wpz-badge wpz-green">Lvl <?php echo esc_html($u['tree_level']); ?></span></td>
                            <td><?php echo !empty($u['passport_fio']) ? '✅' : '—'; ?></td>
                            <td style="white-space:nowrap;"><?php echo $u['created_at'] ? date('d.m.Y', strtotime($u['created_at'])) : '—'; ?></td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <?php if (empty($u['is_banned'])): ?>
                                        <button type="button" class="button wpz-btn-ban"
                                                onclick="wpzBanUser(<?php echo esc_js($u['telegram_id']); ?>, '<?php echo esc_js($u['name'] ?: $u['telegram_id']); ?>')"
                                                title="Заблокировать">🚫</button>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Разблокировать пользователя?')">
                                            <?php wp_nonce_field('wpz_action'); ?>
                                            <input type="hidden" name="telegram_id" value="<?php echo esc_attr($u['telegram_id']); ?>">
                                            <button type="submit" name="action" value="unban_user"
                                                    class="button" title="Разблокировать">✅</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Удалить пользователя «<?php echo esc_js($u['name'] ?: $u['telegram_id']); ?>»?\n\n⚠️ Это действие необратимо!\nБудут удалены: баллы, заказы, сканирования, уведомления.')">
                                        <?php wp_nonce_field('wpz_action'); ?>
                                        <input type="hidden" name="telegram_id" value="<?php echo esc_attr($u['telegram_id']); ?>">
                                        <button type="submit" name="action" value="delete_user"
                                                class="button wpz-btn-danger" title="Удалить">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $page,
                        'total' => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
var wpzNonce = '<?php echo wp_create_nonce('wpz_action'); ?>';
function wpzBanUser(tgId, userName) {
    var reason = prompt('Заблокировать «' + userName + '»?\nВведите причину бана (необязательно):');
    if (reason === null) return;
    if (!confirm('⚠️ Вы уверены?\n\nПользователь «' + userName + '» (ID: ' + tgId + ') будет заблокирован.\nПричина: ' + (reason || 'не указана'))) return;

    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="_wpnonce" value="' + wpzNonce + '">'
        + '<input type="hidden" name="action" value="ban_user">'
        + '<input type="hidden" name="telegram_id" value="' + tgId + '">'
        + '<input type="hidden" name="ban_reason" value="' + (reason || '').replace(/"/g, '&quot;') + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var data = <?php echo json_encode($reg_chart); ?>;
    var canvas = document.getElementById('wpz-users-reg-chart');
    if (!canvas || !data.length) return;

    var labels = data.map(function(d) { return d.day; });
    var values = data.map(function(d) { return d.cnt; });

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Регистрации',
                data: values,
                backgroundColor: 'rgba(34,113,177,0.6)',
                borderColor: 'rgba(34,113,177,1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45 } },
                y: { beginAtZero: true, ticks: { font: { size: 10 }, stepSize: 1 } }
            }
        }
    });
});
</script>
