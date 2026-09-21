<?php if (!defined('ABSPATH')) exit;
$active_chat_id = (int)($_GET['chat'] ?? 0);
$active_chat = null;
$messages = [];
if ($active_chat_id) {
    $active_chat = $db->get_support_chat($active_chat_id);
    $messages = $db->get_support_messages($active_chat_id);
}
?>
<div class="wrap">
    <?php WaterPrize_Pages::flash_message(); ?>
    <h1>💬 Чат поддержки <span class="wpz-count"><?php echo esc_html($total); ?></span></h1>

    <div style="display:flex;gap:16px;height:calc(100vh - 200px);min-height:500px">
        <!-- Список чатов -->
        <div style="width:320px;flex-shrink:0;background:#fff;border:1px solid #c3c4c7;border-radius:4px;overflow-y:auto">
            <div style="padding:12px;border-bottom:1px solid #c3c4c7;font-weight:600;background:#f9f9f9">
                Чаты (<?php echo esc_html(count($chats)); ?>)
            </div>
            <?php if (empty($chats)): ?>
                <div style="padding:40px 20px;text-align:center;color:#787c82">Нет чатов</div>
            <?php else: foreach ($chats as $chat):
                $is_active = $chat['id'] == $active_chat_id;
                $status_color = $chat['status'] === 'open' ? '#46b450' : '#dc3232';
                $status_label = $chat['status'] === 'open' ? 'Открыт' : 'Закрыт';
            ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpz-support&chat=' . $chat['id'])); ?>"
                   style="display:block;padding:12px;border-bottom:1px solid #f0f0f1;text-decoration:none;color:inherit;<?php echo $is_active ? 'background:#fff8e1;' : ''; ?>">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                        <strong style="color:#1d2327"><?php echo esc_html($chat['user_name'] ?: 'Пользователь #' . $chat['user_telegram_id']); ?></strong>
                        <span style="font-size:11px;padding:2px 6px;border-radius:3px;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>"><?php echo $status_label; ?></span>
                    </div>
                    <div style="font-size:13px;color:#50575e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px">
                        <?php echo esc_html($chat['last_message'] ?: 'Нет сообщений'); ?>
                    </div>
                    <div style="font-size:11px;color:#787c82;margin-top:4px;display:flex;justify-content:space-between">
                        <span>ID: <?php echo esc_html($chat['user_telegram_id']); ?></span>
                        <span><?php echo $chat['last_message_at'] ? date('d.m H:i', strtotime($chat['last_message_at'])) : ''; ?></span>
                    </div>
                    <?php if ($chat['unread_count'] > 0): ?>
                        <div style="margin-top:4px">
                            <span style="background:#dc3232;color:#fff;font-size:11px;padding:1px 6px;border-radius:10px">
                                <?php echo esc_html($chat['unread_count']); ?> новых
                            </span>
                        </div>
                    <?php endif; ?>
                </a>
            <?php endforeach; endif; ?>
        </div>

        <!-- Область чата -->
        <div style="flex:1;background:#fff;border:1px solid #c3c4c7;border-radius:4px;display:flex;flex-direction:column">
            <?php if ($active_chat): ?>
                <div style="padding:12px 16px;border-bottom:1px solid #c3c4c7;background:#f9f9f9;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <strong><?php echo esc_html($active_chat['user_name'] ?: 'Пользователь'); ?></strong>
                        <span style="color:#787c82;margin-left:8px">Telegram ID: <?php echo esc_html($active_chat['user_telegram_id']); ?></span>
                        <span style="margin-left:8px;padding:2px 8px;border-radius:3px;font-size:12px;background:<?php echo $active_chat['status'] === 'open' ? '#46b45020;color:#46b450' : '#dc323220;color:#dc3232'; ?>">
                            <?php echo $active_chat['status'] === 'open' ? 'Открыт' : 'Закрыт'; ?>
                        </span>
                    </div>
                    <?php if ($active_chat['status'] === 'open'): ?>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('wpz_action'); ?>
                            <input type="hidden" name="action" value="close_support_chat">
                            <input type="hidden" name="chat_id" value="<?php echo esc_attr($active_chat['id']); ?>">
                            <button type="submit" class="button button-small" onclick="return confirm('Закрыть чат?')">🔒 Закрыть</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Сообщения -->
                <div id="support-chat-messages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#f6f7f7">
                    <?php if (empty($messages)): ?>
                        <div style="text-align:center;padding:40px;color:#787c82">Нет сообщений</div>
                    <?php else: foreach ($messages as $msg):
                        $is_admin = $msg['sender_type'] === 'admin';
                    ?>
                        <div style="display:flex;flex-direction:column;<?php echo $is_admin ? 'align-items:flex-end' : 'align-items:flex-start'; ?>">
                            <div style="max-width:70%;padding:10px 14px;border-radius:12px;font-size:14px;line-height:1.45;<?php echo $is_admin ? 'background:#2271b1;color:#fff;border-bottom-right-radius:4px' : 'background:#fff;color:#1d2327;border:1px solid #dcdcde;border-bottom-left-radius:4px'; ?>">
                                <?php echo nl2br(esc_html($msg['message'])); ?>
                            </div>
                            <div style="font-size:11px;color:#787c82;margin-top:3px;padding:0 4px">
                                <?php echo $is_admin ? 'Администратор' : esc_html($active_chat['user_name'] ?: 'Пользователь'); ?>
                                &middot;
                                <?php echo $msg['created_at'] ? date('d.m.Y H:i', strtotime($msg['created_at'])) : ''; ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- Поле ввода -->
                <?php if ($active_chat['status'] === 'open'): ?>
                <div style="padding:12px 16px;border-top:1px solid #c3c4c7;background:#f9f9f9">
                    <form method="post" style="display:flex;gap:8px" onsubmit="document.getElementById('send-btn').disabled=true">
                        <?php wp_nonce_field('wpz_action'); ?>
                        <input type="hidden" name="action" value="reply_support_chat">
                        <input type="hidden" name="chat_id" value="<?php echo esc_attr($active_chat['id']); ?>">
                        <input type="text" name="message" placeholder="Введите ответ..." required
                               style="flex:1;padding:8px 12px;border:1px solid #dcdcde;border-radius:4px;font-size:14px">
                        <button type="submit" id="send-btn" class="button button-primary">Отправить</button>
                    </form>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#787c82">
                    <div style="text-align:center">
                        <div style="font-size:48px;margin-bottom:12px">💬</div>
                        <div style="font-size:16px">Выберите чат слева</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Автопрокрутка к последнему сообщению
var chatBox = document.getElementById('support-chat-messages');
if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
</script>
