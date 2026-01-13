<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_options_page(
        'RSS для Дзена',
        'RSS для Дзена',
        'manage_options',
        'xt-dzen-rss',
        'xt_dzen_admin_page'
    );
});

function xt_dzen_admin_page() {
    $options = get_option(XT_DZEN_OPTION);
    $sent = get_option(XT_DZEN_LOG, []);
    ?>
    <div class="wrap">
        <h1>RSS для Дзена — X-Tiger</h1>

        <form method="post" action="options.php">
            <?php settings_fields('xt_dzen_group'); ?>

            <table class="form-table">
                <tr>
                    <th>Режим публикации</th>
                    <td>
                        <select name="<?php echo XT_DZEN_OPTION; ?>[mode]">
                            <option value="native" <?php selected($options['mode'], 'native'); ?>>Публиковать сразу</option>
                            <option value="native-draft" <?php selected($options['mode'], 'native-draft'); ?>>Черновик (рекомендуется)</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Дней свежести</th>
                    <td>
                        <input type="number" min="1" max="7"
                               name="<?php echo XT_DZEN_OPTION; ?>[days_limit]"
                               value="<?php echo esc_attr($options['days_limit']); ?>">
                        <p class="description">Рекомендуется: 2–3 дня</p>
                    </td>
                </tr>

                <tr>
                    <th>Лимит статей</th>
                    <td>
                        <input type="number" min="1" max="500"
                               name="<?php echo XT_DZEN_OPTION; ?>[posts_limit]"
                               value="<?php echo esc_attr($options['posts_limit']); ?>">
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2>Отправленные статьи (GUID)</h2>
        <?php if ($sent): ?>
            <ul style="max-height:300px;overflow:auto;background:#fff;padding:10px;">
                <?php foreach ($sent as $guid): ?>
                    <li><?php echo esc_html($guid); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Пока ничего не отправлено.</p>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('xt_dzen_group', XT_DZEN_OPTION);
});