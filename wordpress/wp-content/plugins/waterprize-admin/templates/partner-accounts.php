<?php if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>🏢 Бизнес-партнёры</h1>
    <p style="color:#646970;">Управление аккаунтами партнёров. Привяжите Telegram ID руководителя к категории партнёра.</p>

    <?php if (!empty($edit_account)): ?>
    <!-- Edit Form -->
    <div class="wpz-card">
        <h2>Редактировать партнёра</h2>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <input type="hidden" name="account_id" value="<?php echo esc_attr($edit_account['id']); ?>">
            <table class="form-table">
                <tr>
                    <th><label for="telegram_id">Telegram ID</label></th>
                    <td>
                        <input type="number" id="telegram_id" name="telegram_id" value="<?php echo esc_attr($edit_account['telegram_id']); ?>" class="regular-text" required>
                        <p class="description">Цифровой Telegram ID руководителя партнёра</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="account_name">Имя</label></th>
                    <td>
                        <input type="text" id="account_name" name="account_name" value="<?php echo esc_attr($edit_account['name']); ?>" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="category_id">Категория</label></th>
                    <td>
                        <select id="category_id" name="category_id" required>
                            <option value="">— Выберите категорию —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat['id']); ?>" <?php selected($edit_account['category_id'], $cat['id']); ?>>
                                <?php echo esc_html($cat['icon'] . ' ' . $cat['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="is_active">Активен</label></th>
                    <td>
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($edit_account['is_active'], true); ?>>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="action" value="update_partner_account" class="button button-primary">💾 Сохранить</button>
                <a href="?page=wpz-partners&tab=accounts" class="button">Отмена</a>
            </p>
        </form>
    </div>

    <?php else: ?>
    <!-- Add Form -->
    <div class="wpz-card">
        <h2>Добавить партнёра</h2>
        <form method="post">
            <?php wp_nonce_field('wpz_action'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="telegram_id">Telegram ID</label></th>
                    <td>
                        <input type="number" id="telegram_id" name="telegram_id" class="regular-text" required placeholder="123456789">
                        <p class="description">Цифровой Telegram ID руководителя партнёра</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="account_name">Имя</label></th>
                    <td>
                        <input type="text" id="account_name" name="account_name" class="regular-text" required placeholder="Иван Иванов">
                    </td>
                </tr>
                <tr>
                    <th><label for="category_id">Категория</label></th>
                    <td>
                        <select id="category_id" name="category_id" required>
                            <option value="">— Выберите категорию —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat['id']); ?>">
                                <?php echo esc_html($cat['icon'] . ' ' . $cat['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="action" value="add_partner_account" class="button button-primary">➕ Добавить</button>
            </p>
        </form>
    </div>

    <!-- List -->
    <div class="wpz-card">
        <h2>Список партнёров</h2>
        <?php if (empty($accounts)): ?>
            <p style="color:#666;text-align:center;padding:20px">Партнёров пока нет</p>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th style="width:150px">Telegram ID</th>
                    <th style="width:150px">Имя</th>
                    <th style="width:150px">Категория</th>
                    <th style="width:80px">Статус</th>
                    <th style="width:150px">Дата</th>
                    <th style="width:120px">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $acc): ?>
                <tr>
                    <td><?php echo esc_html($acc['id']); ?></td>
                    <td><code><?php echo esc_html($acc['telegram_id']); ?></code></td>
                    <td><?php echo esc_html($acc['name']); ?></td>
                    <td>
                        <?php
                        $cat_title = '';
                        foreach ($categories as $c) {
                            if ($c['id'] == $acc['category_id']) {
                                $cat_title = $c['icon'] . ' ' . $c['title'];
                                break;
                            }
                        }
                        echo esc_html($cat_title ?: '—');
                        ?>
                    </td>
                    <td>
                        <?php if ($acc['is_active']): ?>
                            <span style="color:#3D9E6A;font-weight:600">Активен</span>
                        <?php else: ?>
                            <span style="color:#999;font-weight:600">Неактивен</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date('d.m.Y H:i', strtotime($acc['created_at']))); ?></td>
                    <td>
                        <a href="?page=wpz-partners&tab=accounts&edit_account=<?php echo $acc['id']; ?>" class="button button-small">✏️</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Удалить партнёра?')">
                            <?php wp_nonce_field('wpz_action'); ?>
                            <input type="hidden" name="account_id" value="<?php echo $acc['id']; ?>">
                            <button type="submit" name="action" value="delete_partner_account" class="button button-small button-link-delete">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
