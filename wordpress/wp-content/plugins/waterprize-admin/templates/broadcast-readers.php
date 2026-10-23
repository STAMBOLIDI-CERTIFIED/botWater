<?php if (!defined('ABSPATH')) exit;

$pages = (int)ceil($total_readers / max(1, $per_page));
$base_url = admin_url('admin.php?page=wpz-broadcasts&readers=' . (int)$broadcast['id']);
$title = $broadcast['title'] ?: mb_strimwidth((string)$broadcast['body'], 0, 70, '...');

if (empty($broadcast['reads_tracked'])): ?>
    <div class="wrap">
        <p style="margin:0 0 8px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-broadcasts')); ?>">← Назад к рассылкам</a>
        </p>
        <h1>👁️ Прочитали рассылку #<?php echo esc_html($broadcast['id']); ?></h1>
        <div class="wpz-card">
            <p style="color:#646970;">
                Для этой рассылки статистика прочтения не велась: она отправлена до включения трекинга.
            </p>
        </div>
    </div>
    <?php return; ?>
<?php endif; ?>
<div class="wrap">
    <p style="margin:0 0 8px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-broadcasts')); ?>">← Назад к рассылкам</a>
    </p>
    <h1>👁️ Прочитали рассылку #<?php echo esc_html($broadcast['id']); ?></h1>

    <div class="wpz-card">
        <h2><?php echo esc_html($title ?: 'Без заголовка'); ?></h2>
        <?php if (!empty($broadcast['body'])): ?>
            <p style="color:#646970;font-size:13px;white-space:pre-line;max-width:900px;">
                <?php echo esc_html(mb_strimwidth((string)$broadcast['body'], 0, 500, '...')); ?>
            </p>
        <?php endif; ?>
        <p style="color:#646970;font-size:13px;margin:0;">
            Отправлена:
            <strong><?php echo $broadcast['sent_at'] ? esc_html(date('Y-m-d H:i', strtotime($broadcast['sent_at']))) : '—'; ?></strong>
            · Доставлено: <strong><?php echo esc_html($broadcast['sent_count']); ?></strong>
            из <?php echo esc_html($broadcast['recipients']); ?>
            · Заходили после отправки: <strong><?php echo esc_html($total_readers); ?></strong>
        </p>
    </div>

    <div class="wpz-card">
        <h2>Список пользователей</h2>
        <?php if (empty($readers)): ?>
            <p style="color:#646970;">
                Пока никто не заходил в мини-приложение после отправки этой рассылки.
            </p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:60px;">№</th>
                        <th>Пользователь</th>
                        <th style="width:180px;">@username</th>
                        <th style="width:160px;">Telegram ID</th>
                        <th style="width:180px;">Последняя активность</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($readers as $i => $r):
                        $name = trim((string)($r['name'] ?? ''));
                        $uname = trim((string)($r['username'] ?? ''));
                        $active = $r['last_active_at']
                            ? date_i18n('d.m.Y H:i', strtotime($r['last_active_at']))
                            : '—';
                    ?>
                        <tr>
                            <td><?php echo esc_html((($paged - 1) * $per_page) + $i + 1); ?></td>
                            <td><strong><?php echo esc_html($name !== '' ? $name : '—'); ?></strong></td>
                            <td>
                                <?php if ($uname !== ''): ?>
                                    <a href="<?php echo esc_url('https://t.me/' . rawurlencode($uname)); ?>"
                                       target="_blank" rel="noopener">@<?php echo esc_html($uname); ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($r['telegram_id']); ?></td>
                            <td><?php echo esc_html($active); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pages > 1): ?>
                <div style="margin-top:16px;">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%', $base_url),
                        'format' => '',
                        'current' => $paged,
                        'total' => $pages,
                        'prev_text' => '‹',
                        'next_text' => '›',
                    ]);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
