<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>👥 Пользователи <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <div class="wpz-card">
        <form method="get" class="wpz-search-form">
            <input type="hidden" name="page" value="wpz-users">
            <input type="search" name="search" placeholder="Поиск по имени, телефону или Telegram ID..." value="<?php echo esc_attr($search); ?>" class="regular-text">
            <button type="submit" class="button button-primary">🔍 Поиск</button>
            <?php if ($search): ?>
                <a href="<?php echo admin_url('admin.php?page=wpz-users'); ?>" class="button">Сброс</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="wpz-card">
        <form method="post" class="wpz-inline-form">
            <?php wp_nonce_field('wpz_action'); ?>
            <table class="wpz-form-table">
                <tr>
                    <td><label>Telegram ID</label></td>
                    <td><input type="number" name="telegram_id" required placeholder="Telegram ID" class="regular-text"></td>
                    <td><label>Кол-во</label></td>
                    <td><input type="number" name="amount" required placeholder="+100 / -50" class="regular-text"></td>
                    <td><label>Причина</label></td>
                    <td><input type="text" name="reason" placeholder="Причина" class="regular-text"></td>
                    <td><button type="submit" name="action" value="add_balance" class="button button-primary">💾 Применить</button></td>
                </tr>
            </table>
        </form>
    </div>

    <div class="wpz-card">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th>Telegram ID</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Баланс</th>
                    <th>Паспорт</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7">Нет пользователей</td></tr>
                <?php else: foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo esc_html($u['id']); ?></td>
                        <td><code><?php echo esc_html($u['telegram_id']); ?></code></td>
                        <td><?php echo esc_html($u['name'] ?: '—'); ?></td>
                        <td><?php echo esc_html($u['phone'] ?: '—'); ?></td>
                        <td><strong><?php echo esc_html($u['balance']); ?></strong></td>
                        <td><?php echo !empty($u['passport_fio']) ? '✅' : '—'; ?></td>
                        <td><?php echo $u['created_at'] ? date('Y-m-d', strtotime($u['created_at'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
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
