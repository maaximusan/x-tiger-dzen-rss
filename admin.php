<?php
if (!defined('ABSPATH')) exit;

if (!defined('XT_DZEN_OPTION')) {
    define('XT_DZEN_OPTION', 'xt_dzen_settings');
}

/**
 * Admin page for X-Tiger RSS for Dzen
 */

add_action('admin_menu', function () {
    add_options_page(
        'X-Tiger RSS for Dzen',
        'X-Tiger RSS for Dzen',
        'manage_options',
        'x-tiger-dzen-rss',
        'xt_dzen_settings_page'
    );
});

function xt_dzen_settings_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    $options = get_option(XT_DZEN_OPTION, []);

    // Значения по умолчанию (на случай старых установок)
    $defaults = [
        'posts_limit'        => 10,
        'days_limit'         => 30,
        'mode'               => 'native',
        'channel_description'=> 'Авторский блог о сайтах и цифровых продуктах. Аналитика, наблюдения и объяснение типичных ситуаций в бизнесе без рекламы и призывов.',
    ];

    $options = wp_parse_args($options, $defaults);

    // Сохранение
    if (isset($_POST['xt_dzen_save']) && check_admin_referer('xt_dzen_save_settings')) {

        $options['posts_limit'] = max(1, intval($_POST['xt_dzen_settings']['posts_limit'] ?? 10));
        $options['days_limit']  = max(1, intval($_POST['xt_dzen_settings']['days_limit'] ?? 30));
        $options['mode']        = sanitize_text_field($_POST['xt_dzen_settings']['mode'] ?? 'native');

        $options['channel_description'] = sanitize_textarea_field(
            $_POST['xt_dzen_settings']['channel_description'] ?? ''
        );

        update_option(XT_DZEN_OPTION, $options);

        echo '<div class="updated"><p>Настройки сохранены.</p></div>';
    }
    ?>

    <div class="wrap">
        <h1>X-Tiger RSS for Dzen</h1>

        <form method="post">
            <?php wp_nonce_field('xt_dzen_save_settings'); ?>

            <table class="form-table">

                <tr>
                    <th scope="row">Лимит статей в RSS</th>
                    <td>
                        <input type="number"
                               name="xt_dzen_settings[posts_limit]"
                               value="<?= esc_attr($options['posts_limit']); ?>"
                               min="1"
                               max="50"
                        />
                        <p class="description">
                            Сколько последних статей показывать в RSS (рекомендуется 5–15).
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Глубина публикаций (дней)</th>
                    <td>
                        <input type="number"
                               name="xt_dzen_settings[days_limit]"
                               value="<?= esc_attr($options['days_limit']); ?>"
                               min="1"
                               max="365"
                        />
                        <p class="description">
                            Используется только как вспомогательное ограничение (не обязательно).
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Режим публикации</th>
                    <td>
                        <select name="xt_dzen_settings[mode]">
                            <option value="native" <?= selected($options['mode'], 'native'); ?>>
                                Нативный контент
                            </option>
                            <option value="draft" <?= selected($options['mode'], 'draft'); ?>>
                                Черновики
                            </option>
                        </select>
                    </td>
                </tr>

                <!-- 🔥 ВАЖНО: Описание канала -->
                <tr>
                    <th scope="row">Описание канала для Дзена</th>
                    <td>
                        <textarea
                            name="xt_dzen_settings[channel_description]"
                            rows="4"
                            cols="60"
                        ><?= esc_textarea($options['channel_description']); ?></textarea>
                        <p class="description">
                            Краткое описание источника для модерации Дзена.  
                            Спокойный, редакционный тон. Без рекламы и призывов.
                        </p>
                    </td>
                </tr>

            </table>

            <p class="submit">
                <button type="submit" name="xt_dzen_save" class="button button-primary">
                    Сохранить настройки
                </button>
            </p>
        </form>

        <hr>

        <h2>RSS для Дзена</h2>
        <p>
            <a href="<?= esc_url(home_url('/dzen/rss.xml')); ?>" target="_blank">
                <?= esc_html(home_url('/dzen/rss.xml')); ?>
            </a>
        </p>

        <p class="description">
            Используй эту ссылку для подключения RSS в Яндекс.Дзен.
        </p>
    </div>

<?php
}

