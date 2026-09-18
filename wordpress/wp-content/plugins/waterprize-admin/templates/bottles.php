<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>🍾 Бутылки <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <?php if ($edit_bottle): ?>
    <!-- ═══ Карточка бутылки ═══ -->
    <div class="wpz-card wpz-bottle-detail">
        <h2>
            Бутылка <code><?php echo esc_html($edit_bottle['bottle_id']); ?></code>
            <a href="<?php echo admin_url('admin.php?page=wpz-bottles'); ?>" class="page-title-action">← Назад</a>
        </h2>
        <div class="wpz-bottle-grid">
            <div class="wpz-bottle-info">
                <table class="form-table">
                    <tr><th>ID бутылки</th><td><code style="font-size:16px;"><?php echo esc_html($edit_bottle['bottle_id']); ?></code></td></tr>
                    <tr><th>Партия</th><td><?php echo esc_html($edit_bottle['batch'] ?: '—'); ?></td></tr>
                    <tr><th>Год</th><td><?php echo esc_html($edit_bottle['year'] ?: '—'); ?></td></tr>
                    <tr><th>Назначена</th><td>
                        <?php if ($edit_bottle['assigned_to']): ?>
                            <span style="color:#00a32a;">✅ <?php echo esc_html($edit_bottle['user_name'] ?: 'Без имени'); ?> (ID: <?php echo esc_html($edit_bottle['user_telegram_id'] ?? $edit_bottle['assigned_to']); ?>)</span>
                        <?php else: ?>
                            <span style="color:#646970;">🔓 Не назначена</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th>Отсканирована</th><td>
                        <?php if ($edit_bottle['assigned_at']): ?>
                            <span style="color:#2271b1;">📅 <?php echo esc_html(wp_date('d.m.Y H:i', strtotime($edit_bottle['assigned_at']))); ?></span>
                        <?php else: ?>
                            <span style="color:#646970;">—</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th>Создана</th><td><?php echo esc_html($edit_bottle['created_at'] ?? '—'); ?></td></tr>
                </table>
            </div>
            <div class="wpz-bottle-qr">
                <div id="wpz-qr-code"></div>
                <p class="description" style="text-align:center;margin-top:8px;">QR-код бутылки</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="wpz-card">
        <h2>Генерация бутылок</h2>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <div class="wpz-gen-form">
                <div class="wpz-gen-field">
                    <label for="count">Кол-во</label>
                    <input type="number" id="count" name="count" value="100" min="1" max="10000">
                </div>
                <div class="wpz-gen-field">
                    <label for="batch">Партия</label>
                    <input type="text" id="batch" name="batch" required placeholder="01">
                </div>
                <div class="wpz-gen-field">
                    <label for="year">Год</label>
                    <input type="text" id="year" name="year" value="<?php echo date('Y'); ?>" required>
                </div>
                <div class="wpz-gen-field wpz-gen-btn">
                    <label>&nbsp;</label>
                    <button type="submit" name="action" value="generate_bottles" class="button button-primary">⚡ Сгенерировать</button>
                </div>
            </div>
        </form>
    </div>

    <div class="wpz-card">
        <h2>Партии</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Год</th><th>Партия</th><th>Количество</th><th style="width:180px;">Действия</th></tr></thead>
            <tbody>
                <?php if (empty($batches)): ?>
                    <tr><td colspan="4">Нет партий</td></tr>
                <?php else: foreach ($batches as $b): ?>
                    <tr>
                        <td><?php echo esc_html($b['year']); ?></td>
                        <td><?php echo esc_html($b['batch']); ?></td>
                        <td><strong><?php echo esc_html($b['count']); ?></strong></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wpz-bottles&export_batch=' . urlencode($b['batch']) . '&export_year=' . urlencode($b['year'])); ?>"
                               class="button button-primary" target="_blank" title="Скачать PDF с QR-кодами">
                                📄 Скачать PDF
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="wpz-card">
        <div class="wpz-table-header">
            <h2>Бутылки <span class="wpz-count"><?php echo esc_html($total); ?></span></h2>
            <form method="get" class="wpz-search-row">
                <input type="hidden" name="page" value="wpz-bottles">
                <input type="search" name="search" placeholder="Поиск по ID, партии, году..." value="<?php echo esc_attr($search); ?>">
                <button type="submit" class="button button-primary">🔍</button>
            </form>
        </div>
        <div class="wpz-table-wrap">
            <table class="wp-list-table widefat fixed striped wpz-bottles-table">
                <thead>
                    <tr>
                        <th class="col-id">ID бутылки</th>
                        <th class="col-batch">Партия</th>
                        <th class="col-year">Год</th>
                        <th class="col-assigned">Назначена</th>
                        <th class="col-scan">Отсканирована</th>
                        <th class="col-date">Создана</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bottles)): ?>
                        <tr><td colspan="6">Нет бутылок</td></tr>
                    <?php else: foreach ($bottles as $b): ?>
                        <tr>
                            <td class="col-id">
                                <a href="<?php echo admin_url('admin.php?page=wpz-bottles&edit=' . $b['id']); ?>"
                                   class="wpz-bottle-link">
                                    <code><?php echo esc_html($b['bottle_id']); ?></code>
                                </a>
                            </td>
                            <td class="col-batch"><?php echo esc_html($b['batch'] ?: '—'); ?></td>
                            <td class="col-year"><?php echo esc_html($b['year'] ?: '—'); ?></td>
                            <td class="col-assigned"><?php echo $b['assigned_to'] ? '✅ ' . esc_html($b['user_name'] ?: 'ID:' . $b['assigned_to']) : '🔓'; ?></td>
                            <td class="col-scan"><?php echo $b['assigned_at'] ? wp_date('d.m.Y H:i', strtotime($b['assigned_at'])) : '—'; ?></td>
                            <td class="col-date"><?php echo $b['created_at'] ? wp_date('Y-m-d', strtotime($b['created_at'])) : '—'; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($edit_bottle): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new QRCode(document.getElementById('wpz-qr-code'), {
        text: <?php echo json_encode($edit_bottle['bottle_id']); ?>,
        width: 200,
        height: 200,
        colorDark: '#1e293b',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>
<?php endif; ?>
