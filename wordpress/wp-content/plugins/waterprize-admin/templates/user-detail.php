<?php if (!defined('ABSPATH')) exit;
$u = $detail_user ?? null;
$h = $detail_history ?? [];

if (!function_exists('wpz_ud_dt')) {
    function wpz_ud_dt($s) { return !empty($s) && $s !== '1970-01-01 00:00:00' ? date('d.m.Y H:i', strtotime($s)) : '—'; }
}
if (!function_exists('wpz_ud_d')) {
    function wpz_ud_d($s) { return !empty($s) && $s !== '1970-01-01' ? date('d.m.Y', strtotime($s)) : '—'; }
}
if (!function_exists('wpz_ud_e')) {
    function wpz_ud_e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$journey_labels = [
    'coupon_buy'    => '🎟 Покупка купона',
    'partner_scan'  => '🏪 Сканирование QR партнёра',
    'coupon_redeem' => '✅ Активация купона',
];
$points_labels = [
    'admin'        => 'Начисление/списание админом',
    'admin_xp'     => 'Опыт (XP) от админа',
    'daily_bonus'  => 'Ежедневный бонус',
    'exchange'     => 'Обмен на приз',
    'gift'         => 'Подарок',
    'partner_scan' => 'Сканирование QR партнёра',
    'scan'         => 'Сканирование QR',
];
$cat_map = [];
foreach (($h['categories'] ?? []) as $c) { $cat_map[$c['id']] = $c['title']; }

$msg_by_chat = [];
foreach (($h['support_messages'] ?? []) as $m) { $msg_by_chat[$m['chat_id']][] = $m; }

$counts = [
    'Действия' => count($h['journey'] ?? []),
    'Баллы' => count($h['points'] ?? []),
    'Партнёрские сканы' => count($h['partner_scans'] ?? []),
    'QR сканы' => count($h['scans'] ?? []),
    'Купоны' => count($h['coupons'] ?? []),
    'Заказы' => count($h['orders'] ?? []),
    'Входы' => count($h['checkins'] ?? []),
    'Выигрыши' => count($h['raffle_wins'] ?? []),
    'Уведомления' => count($h['notifications'] ?? []),
    'Сообщения поддержки' => count($h['support_messages'] ?? []),
    'Бутылки' => count($h['bottles'] ?? []),
    'Активации QR' => count($h['activations'] ?? []),
];
?>
<style>
    .wpz-ud-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
    .wpz-ud-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .wpz-ud-avatar { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,#43A047,#2E7D32);
        display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; color:#fff; flex:0 0 auto; }
    .wpz-ud-chips { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
    .wpz-ud-chip { font-size:12px; padding:4px 10px; border-radius:12px; background:#f0f6ff; border:1px solid #c8ddf5; color:#1d4e89; font-weight:600; }
    .wpz-ud-profile { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:0 18px; }
    .wpz-ud-profile div { display:flex; justify-content:space-between; gap:10px; padding:6px 0; border-bottom:1px dashed #eee; font-size:13px; }
    .wpz-ud-profile div span:first-child { color:#777; flex:0 0 auto; }
    .wpz-ud-profile div span:last-child { text-align:right; word-break:break-all; font-weight:600; }
    .wpz-ud-cards { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
    .wpz-ud-mini { background:#fff; border:1px solid #e2e2e2; border-radius:10px; padding:10px 16px; min-width:110px; text-align:center; }
    .wpz-ud-mini b { display:block; font-size:20px; }
    .wpz-ud-mini span { font-size:11px; color:#777; text-transform:uppercase; letter-spacing:.04em; }
    .wpz-ud-chat { border:1px solid #e5e5e5; border-radius:8px; padding:10px 12px; margin-bottom:10px; background:#fafbfc; }
    .wpz-ud-chat h3 { margin:0 0 8px; font-size:13px; }
    @media (max-width: 1000px) { .wpz-ud-grid { grid-template-columns:1fr; } }
</style>

<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>

    <?php if (!$u): ?>
        <div class="wpz-ud-head">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-users')); ?>" class="button">← К списку пользователей</a>
        </div>
        <div class="wpz-card"><h2>😔 Пользователь не найден</h2>
            <p style="color:#666;margin:0;">Возможно, он был удалён. <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-users')); ?>">Вернуться к списку</a>.</p>
        </div>
        <?php return; endif; ?>

    <div class="wpz-ud-head">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-users')); ?>" class="button">← К списку</a>
        <div class="wpz-ud-avatar"><?php echo esc_html(mb_strtoupper(mb_substr($u['name'] ?: '?', 0, 1))); ?></div>
        <div>
            <h1 style="margin:0;"><?php echo esc_html($u['name'] ?: ('Пользователь #' . $u['id'])); ?></h1>
            <div style="font-size:13px;color:#666;">
                <?php if (!empty($u['username'])): ?>@<?php echo esc_html($u['username']); ?> · <?php endif; ?>
                <code>tg:<?php echo esc_html($u['telegram_id']); ?></code> · ID <?php echo esc_html($u['id']); ?>
                <?php if (!empty($u['is_banned'])): ?>
                    <span class="wpz-badge wpz-red" title="<?php echo esc_attr($u['ban_reason'] ?: 'Заблокирован'); ?>">🚫 Забанен<?php echo $u['banned_at'] ? ' ' . wpz_ud_d($u['banned_at']) : ''; ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══ Quick stats ═══ -->
    <div class="wpz-ud-cards">
        <div class="wpz-ud-mini"><b style="color:#b8860b;"><?php echo esc_html($u['balance']); ?></b><span>Баланс</span></div>
        <div class="wpz-ud-mini"><b><?php echo esc_html($u['tree_xp']); ?></b><span>XP</span></div>
        <div class="wpz-ud-mini"><b style="color:#43A047;">Lvl <?php echo esc_html($u['tree_level']); ?></b><span>Уровень</span></div>
        <div class="wpz-ud-mini"><b>🔥 <?php echo esc_html($u['daily_streak']); ?></b><span>Стрик</span></div>
        <div class="wpz-ud-mini"><b style="font-size:15px;padding-top:4px;"><?php echo wpz_ud_d($u['created_at']); ?></b><span>Регистрация</span></div>
        <div class="wpz-ud-mini"><b style="font-size:15px;padding-top:4px;"><?php echo wpz_ud_dt($u['updated_at']); ?></b><span>Последний раз</span></div>
        <?php if (!empty($u['phone'])): ?>
            <div class="wpz-ud-mini"><b style="font-size:15px;padding-top:4px;"><?php echo esc_html($u['phone']); ?></b><span>Телефон</span></div>
        <?php endif; ?>
        <div class="wpz-ud-mini"><b style="font-size:15px;padding-top:4px;"><?php echo !empty($u['passport_fio']) ? '✅' : '—'; ?></b><span>Паспорт</span></div>
    </div>

    <!-- ═══ Full profile ═══ -->
    <div class="wpz-card">
        <h2>🪪 Профиль</h2>
        <div class="wpz-ud-profile">
            <div><span>ID</span><span><?php echo esc_html($u['id']); ?></span></div>
            <div><span>Telegram ID</span><span><code><?php echo esc_html($u['telegram_id']); ?></code></span></div>
            <div><span>Имя</span><span><?php echo esc_html($u['name'] ?: '—'); ?></span></div>
            <div><span>Username</span><span><?php echo !empty($u['username']) ? '@' . esc_html($u['username']) : '—'; ?></span></div>
            <div><span>Телефон</span><span><?php echo esc_html($u['phone'] ?: '—'); ?></span></div>
            <div><span>Шаг сценария</span><span><?php echo esc_html($u['step'] ?? '—'); ?></span></div>
            <div><span>Start payload</span><span><?php echo esc_html($u['start_payload'] ?: '—'); ?></span></div>
            <div><span>Согласие с оффертой</span><span><?php echo !empty($u['agreed_terms']) ? '✅ Да' : '❌ Нет'; ?></span></div>
            <div><span>Баланс</span><span style="color:#b8860b;"><?php echo esc_html($u['balance']); ?></span></div>
            <div><span>Дерево XP</span><span><?php echo esc_html($u['tree_xp']); ?></span></div>
            <div><span>Уровень дерева</span><span><?php echo esc_html($u['tree_level']); ?></span></div>
            <div><span>Паспорт ФИО</span><span><?php echo esc_html($u['passport_fio'] ?: '—'); ?></span></div>
            <div><span>Паспорт серия/номер</span><span><?php echo esc_html($u['passport_snumber'] ?: '—'); ?></span></div>
            <div><span>ИНН</span><span><?php echo esc_html($u['passport_inn'] ?: '—'); ?></span></div>
            <div><span>Подарок открыт</span><span><?php echo !empty($u['gift_opened']) ? '✅ (' . esc_html($u['gift_points'] ?? 0) . ' б.)' : '❌'; ?></span></div>
            <div><span>Стрик (входы)</span><span>🔥 <?php echo esc_html($u['daily_streak']); ?></span></div>
            <div><span>Последний бонус</span><span><?php echo !empty($u['daily_last_bonus']) ? wpz_ud_d($u['daily_last_bonus']) : '—'; ?></span></div>
            <div><span>Создан</span><span><?php echo wpz_ud_dt($u['created_at']); ?></span></div>
            <div><span>Обновлён</span><span><?php echo wpz_ud_dt($u['updated_at']); ?></span></div>
            <?php if (!empty($u['is_banned'])): ?>
                <div><span style="color:#d63638;">Бан</span><span style="color:#d63638;"><?php echo esc_html($u['ban_reason'] ?: 'без причины'); ?></span></div>
                <div><span>Забанен</span><span><?php echo wpz_ud_dt($u['banned_at']); ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ History chips ═══ -->
    <div class="wpz-card" style="margin-top:14px;">
        <h2>📚 История в системе</h2>
        <div class="wpz-ud-chips">
            <?php foreach ($counts as $label => $cnt): ?>
                <span class="wpz-ud-chip"><?php echo esc_html($label); ?>: <b><?php echo (int)$cnt; ?></b></span>
            <?php endforeach; ?>
        </div>

        <div class="wpz-ud-grid">
            <!-- 🧭 Journey -->
            <div class="wpz-card" style="margin:0;">
                <h2>🧭 Действия в системе <span class="wpz-count"><?php echo count($h['journey'] ?? []); ?></span></h2>
                <?php if (empty($h['journey'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>Действие</th><th>Партнёр</th><th style="width:70px;">Баллы</th>
                </tr></thead><tbody>
                    <?php foreach ($h['journey'] as $j): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($j['created_at']); ?></td>
                        <td>
                            <?php echo esc_html($journey_labels[$j['action_type']] ?? $j['action_type']); ?>
                            <?php $md = is_string($j['metadata'] ?? '') ? json_decode($j['metadata'], true) : ($j['metadata'] ?? null);
                                  if (is_array($md) && $md): ?>
                                <br><code style="font-size:11px;color:#888;"><?php echo esc_html(wp_json_encode($md)); ?></code>
                            <?php endif; ?>
                            <?php if (!empty($j['related_id'])): ?>
                                <br><small style="color:#999;">связано с #<?php echo esc_html($j['related_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php
                            $pid = $j['partner_id'] ?? null;
                            if ($pid && isset($cat_map[$pid])) echo esc_html($cat_map[$pid]);
                            elseif ($pid) echo '#' . esc_html($pid);
                            else echo '—';
                        ?></td>
                        <td style="white-space:nowrap;">
                            <?php if (!empty($j['points_used'])): ?><span style="color:#d63638;">−<?php echo esc_html($j['points_used']); ?></span><?php endif; ?>
                            <?php if (!empty($j['partner_reward'])): ?><span style="color:#00a32a;">+<?php echo esc_html($j['partner_reward']); ?></span><?php endif; ?>
                            <?php if (empty($j['points_used']) && empty($j['partner_reward'])) echo '—'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 💰 Points -->
            <div class="wpz-card" style="margin:0;">
                <h2>💰 Операции с баллами <span class="wpz-count"><?php echo count($h['points'] ?? []); ?></span></h2>
                <?php if (empty($h['points'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>Тип</th><th style="width:80px;">Сумма</th><th>Описание</th>
                </tr></thead><tbody>
                    <?php foreach ($h['points'] as $p): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($p['created_at']); ?></td>
                        <td><?php echo esc_html($points_labels[$p['type']] ?? $p['type']); ?></td>
                        <td style="font-weight:700;color:<?php echo $p['amount'] >= 0 ? '#00a32a' : '#d63638'; ?>;">
                            <?php echo ($p['amount'] >= 0 ? '+' : '') . esc_html($p['amount']); ?>
                        </td>
                        <td><?php echo esc_html($p['description'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 🏪 Partner scans -->
            <div class="wpz-card" style="margin:0;">
                <h2>🏪 Партнёрские сканирования <span class="wpz-count"><?php echo count($h['partner_scans'] ?? []); ?></span></h2>
                <?php if (empty($h['partner_scans'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>Магазин</th><th>QR</th><th style="width:70px;">Баллы</th>
                </tr></thead><tbody>
                    <?php foreach ($h['partner_scans'] as $s): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($s['scanned_at']); ?></td>
                        <td><strong><?php echo esc_html($s['category_title'] ?: '—'); ?></strong></td>
                        <td><code style="font-size:11px;"><?php echo esc_html($s['qr_code']); ?></code></td>
                        <td style="font-weight:700;color:#00a32a;">+<?php echo esc_html($s['points_earned']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 📷 QR scans -->
            <div class="wpz-card" style="margin:0;">
                <h2>📷 Сканирования QR-кодов <span class="wpz-count"><?php echo count($h['scans'] ?? []); ?></span></h2>
                <?php if (empty($h['scans'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>Код</th><th>Партия</th><th>Статус</th>
                </tr></thead><tbody>
                    <?php foreach ($h['scans'] as $s): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($s['scanned_at']); ?></td>
                        <td><code style="font-size:11px;"><?php echo esc_html($s['code'] ?? ('#' . $s['id'])); ?></code></td>
                        <td><?php echo esc_html($s['batch'] ?? '—'); ?></td>
                        <td><span class="wpz-badge <?php echo $s['status'] === 'active' ? 'wpz-green' : 'wpz-blue'; ?>"><?php echo esc_html($s['status'] ?? '—'); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 🎟 Coupons -->
            <div class="wpz-card" style="margin:0;">
                <h2>🎟 Купоны <span class="wpz-count"><?php echo count($h['coupons'] ?? []); ?></span></h2>
                <?php if (empty($h['coupons'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Создан</th><th>Приз</th><th>Статус</th><th>Использован</th>
                </tr></thead><tbody>
                    <?php foreach ($h['coupons'] as $c): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($c['created_at']); ?></td>
                        <td><strong><?php echo esc_html($c['prize_name'] ?: ($c['prize_id'] ? 'приз #' . $c['prize_id'] : 'приз (удалён)')); ?></strong>
                            <?php if (!empty($c['qr_code'])): ?><br><code style="font-size:11px;"><?php echo esc_html($c['qr_code']); ?></code><?php endif; ?>
                        </td>
                        <td><span class="wpz-badge <?php echo $c['status'] === 'used' ? 'wpz-blue' : 'wpz-green'; ?>">
                            <?php echo $c['status'] === 'used' ? 'Использован' : 'Активен'; ?></span></td>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($c['used_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 📦 Orders -->
            <div class="wpz-card" style="margin:0;">
                <h2>📦 Заказы <span class="wpz-count"><?php echo count($h['orders'] ?? []); ?></span></h2>
                <?php if (empty($h['orders'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>Приз</th><th>Статус</th><th>Получатель</th>
                </tr></thead><tbody>
                    <?php foreach ($h['orders'] as $o): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($o['created_at']); ?></td>
                        <td><strong><?php echo esc_html($o['prize_name'] ?: ($o['prize_joined'] ?: '—')); ?></strong></td>
                        <td><span class="wpz-badge <?php echo $o['status'] === 'pending' ? 'wpz-gold' : 'wpz-green'; ?>"><?php echo esc_html($o['status']); ?></span></td>
                        <td><?php echo esc_html(trim(($o['name'] ?? '') . ' ' . ($o['phone'] ?? '')) ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 📅 Checkins -->
            <div class="wpz-card" style="margin:0;">
                <h2>📅 Ежедневные входы <span class="wpz-count"><?php echo count($h['checkins'] ?? []); ?></span></h2>
                <?php if (empty($h['checkins'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>Стрик</th><th>Бонус</th>
                </tr></thead><tbody>
                    <?php foreach ($h['checkins'] as $d): ?>
                    <tr>
                        <td><?php echo wpz_ud_d($d['checkin_date']); ?></td>
                        <td>🔥 <?php echo esc_html($d['streak']); ?></td>
                        <td style="font-weight:700;color:#00a32a;">+<?php echo esc_html($d['bonus_points']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 🎰 Raffle wins -->
            <div class="wpz-card" style="margin:0;">
                <h2>🎰 Выигрыши в розыгрышах <span class="wpz-count"><?php echo count($h['raffle_wins'] ?? []); ?></span></h2>
                <?php if (empty($h['raffle_wins'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>Приз</th><th>Статус</th><th>Выплата</th>
                </tr></thead><tbody>
                    <?php foreach ($h['raffle_wins'] as $r): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($r['created_at']); ?></td>
                        <td><strong><?php echo esc_html($r['prize_amount']); ?> б.</strong>
                            <?php if (!empty($r['winning_code'])): ?><br><code style="font-size:11px;"><?php echo esc_html($r['winning_code']); ?></code><?php endif; ?>
                        </td>
                        <td><span class="wpz-badge wpz-green"><?php echo esc_html($r['status']); ?></span></td>
                        <td><?php echo esc_html(trim(($r['payout_status'] ?? '') . ' ' . ($r['payout_choice'] ?? '')) ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 🔔 Notifications -->
            <div class="wpz-card" style="margin:0;">
                <h2>🔔 Уведомления <span class="wpz-count"><?php echo count($h['notifications'] ?? []); ?></span></h2>
                <?php if (empty($h['notifications'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>Заголовок</th><th>Текст</th>
                </tr></thead><tbody>
                    <?php foreach ($h['notifications'] as $n): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($n['created_at']); ?>
                            <?php if (empty($n['read'])): ?><br><span class="wpz-badge wpz-gold">новое</span><?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html($n['title'] ?: '—'); ?></strong></td>
                        <td><?php echo esc_html($n['body'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 💬 Support -->
            <div class="wpz-card" style="margin:0;">
                <h2>💬 Поддержка <span class="wpz-count"><?php echo count($h['support_messages'] ?? []); ?> сообщ.</span></h2>
                <?php if (empty($h['support_chats'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет обращений</p>
                <?php else: foreach ($h['support_chats'] as $chat): ?>
                    <div class="wpz-ud-chat">
                        <h3>Чат #<?php echo esc_html($chat['id']); ?>
                            <span class="wpz-badge <?php echo $chat['status'] === 'closed' ? 'wpz-blue' : 'wpz-green'; ?>"><?php echo esc_html($chat['status']); ?></span>
                            <span style="font-weight:400;color:#888;">· <?php echo (int)$chat['msg_count']; ?> сообщ. · создан <?php echo wpz_ud_dt($chat['created_at']); ?></span>
                        </h3>
                        <?php $msgs = array_reverse($msg_by_chat[$chat['id']] ?? []); ?>
                        <?php if (!$msgs): ?>
                            <p style="color:#999;font-size:12px;margin:0;">Нет сообщений</p>
                        <?php else: foreach ($msgs as $m): ?>
                            <div style="display:flex;gap:8px;padding:4px 0;font-size:13px;border-bottom:1px dashed #eee;">
                                <span style="flex:0 0 auto;"><?php echo $m['sender_type'] === 'user' ? '👤' : '🤖'; ?></span>
                                <span style="flex:1;"><?php echo nl2br(wpz_ud_e($m['message'])); ?></span>
                                <span style="flex:0 0 auto;color:#999;font-size:11px;white-space:nowrap;"><?php echo wpz_ud_dt($m['created_at']); ?></span>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- 🍾 Bottles -->
            <div class="wpz-card" style="margin:0;">
                <h2>🍾 Бутылки <span class="wpz-count"><?php echo count($h['bottles'] ?? []); ?></span></h2>
                <?php if (empty($h['bottles'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Назначена</th><th>Номер</th><th>Партия</th><th>Год</th>
                </tr></thead><tbody>
                    <?php foreach ($h['bottles'] as $b): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($b['assigned_at'] ?: $b['created_at']); ?></td>
                        <td><strong><?php echo esc_html($b['bottle_id']); ?></strong></td>
                        <td><?php echo esc_html($b['batch']); ?></td>
                        <td><?php echo esc_html($b['year']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <!-- 📱 QR activations -->
            <div class="wpz-card" style="margin:0;">
                <h2>📱 Активации QR <span class="wpz-count"><?php echo count($h['activations'] ?? []); ?></span></h2>
                <?php if (empty($h['activations'])): ?>
                    <p style="color:#999;font-size:13px;margin:0;">Нет записей</p>
                <?php else: ?>
                <div class="wpz-table-wrap"><table class="wp-list-table widefat fixed striped"><thead><tr>
                    <th>Дата</th><th>QR-код</th>
                </tr></thead><tbody>
                    <?php foreach ($h['activations'] as $a): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo wpz_ud_dt($a['activated_at']); ?></td>
                        <td><code style="font-size:11px;"><?php echo esc_html($a['qr_code']); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
