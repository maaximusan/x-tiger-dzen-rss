<?php
/**
 * Plugin Name: X-Tiger RSS for Dzen
 * Description: RSS-лента для Яндекс.Дзена с корректной разметкой
 * Version: 1.1.7
 * Author: X-Tiger
 * Text Domain: x-tiger-dzen-rss
 */


if (!defined('ABSPATH')) {
    exit;
}

/* ======================================================
   КОНСТАНТЫ
====================================================== */

define('XT_DZEN_OPTION', 'xt_dzen_settings');

define(
    'XT_DZEN_UPDATE_URL',
    'https://raw.githubusercontent.com/maaximusan/x-tiger-dzen-rss/main/update.json'
);

/**
 * Разрешённые категории Дзена (whitelist)
 * Нейтральные жанры, безопасные для модерации
 */
function xt_dzen_allowed_modes() {
    return [
        'news'      => 'Новости',
        'analytics' => 'Аналитика',
        'education' => 'Обучение',
        'opinion'   => 'Мнение',
        'review'    => 'Обзор',
        'feature'   => 'Материал',
        'insight'   => 'Инсайт',
    ];
}

/* ======================================================
   АКТИВАЦИЯ
====================================================== */

register_activation_hook(__FILE__, function () {

    add_option(XT_DZEN_OPTION, [
        'posts_limit'         => 10,
        'category_slug'       => 'dzen',        // WP-рубрика
        'mode'                => 'education',   // категория Дзена (безопасная по умолчанию)
        'channel_description' => 'Авторский блог о сайтах и цифровых продуктах. Аналитика, объяснения и наблюдения без рекламы и призывов.',
    ]);

    flush_rewrite_rules();
});

/* ======================================================
   RSS ENDPOINT
====================================================== */

add_action('init', function () {
    add_rewrite_rule('^dzen/rss\.xml$', 'index.php?xt_dzen_rss=1', 'top');
    add_rewrite_tag('%xt_dzen_rss%', '1');
});

/* ======================================================
   RSS GENERATION
====================================================== */

add_action('template_redirect', function () {

    if ((int) get_query_var('xt_dzen_rss') !== 1) {
        return;
    }

    header('Content-Type: application/rss+xml; charset=UTF-8');

    $opt = get_option(XT_DZEN_OPTION, []);

    // Безопасная категория Дзена
    $allowed_modes = xt_dzen_allowed_modes();
    $mode = isset($allowed_modes[$opt['mode'] ?? ''])
        ? $opt['mode']
        : 'education';

    // WP-рубрика
    $category_slug = sanitize_title($opt['category_slug'] ?? 'dzen');

    echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title>X-Tiger — блог о сайтах для предпринимателей</title>
    <link><?= esc_url(home_url('/')); ?></link>

<?php if (!empty($opt['channel_description'])) : ?>
    <description><![CDATA[
<?= trim($opt['channel_description']); ?>
    ]]></description>
<?php endif; ?>

    <language>ru</language>

<?php
    $posts = get_posts([
        'post_type'        => 'post',
        'post_status'      => 'publish',
        'posts_per_page'   => (int) ($opt['posts_limit'] ?? 10),
        'category_name'    => $category_slug,
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post) {
        setup_postdata($post);

        $id   = $post->ID;
        $guid = get_permalink($id);

        // Обязательное изображение
        $thumb_id = get_post_thumbnail_id($id);
        if (!$thumb_id) {
            continue;
        }

        $img = wp_get_attachment_image_src($thumb_id, 'full');
        if (!$img || $img[1] < 700) {
            continue;
        }

        // Контент
        $raw   = apply_filters('the_content', $post->post_content);
        $clean = xt_dzen_clean_html($raw);

        if (mb_strlen(strip_tags($clean)) < 300) {
            continue;
        }
?>
    <item>
        <title><?= esc_html(get_the_title($id)); ?></title>
        <link><?= esc_url($guid); ?></link>
        <guid isPermaLink="true"><?= esc_url($guid); ?></guid>
        <pubDate><?= mysql2date(DATE_RSS, get_post_time('Y-m-d H:i:s', true, $id)); ?></pubDate>
        <category><?= esc_html($mode); ?></category>
        <enclosure url="<?= esc_url($img[0]); ?>" type="image/jpeg"/>
        <content:encoded><![CDATA[
<?= $clean; ?>
        ]]></content:encoded>
    </item>
<?php
    }

    wp_reset_postdata();
?>
</channel>
</rss>
<?php
    exit;
});

/* ======================================================
   HTML CLEANER (DZEN SAFE)
====================================================== */

function xt_dzen_clean_html($html) {

    libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

    $allowed = [
        'p','a','b','i','u','s',
        'h2','h3','h4',
        'ul','ol','li',
        'blockquote','figure','img','figcaption'
    ];

    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//*') as $node) {

        if (!in_array($node->nodeName, $allowed, true)) {
            while ($node->firstChild) {
                $node->parentNode->insertBefore($node->firstChild, $node);
            }
            $node->parentNode->removeChild($node);
            continue;
        }

        // удаляем все атрибуты
        while ($node->attributes && $node->attributes->length) {
            $node->removeAttributeNode($node->attributes->item(0));
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    $out  = '';

    foreach ($body->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }

    return trim($out);
}

/* ======================================================
   AUTO UPDATE (GitHub)
====================================================== */

add_filter('pre_set_site_transient_update_plugins', 'xt_dzen_check_update');

function xt_dzen_check_update($transient) {

    if (empty($transient->checked)) {
        return $transient;
    }

    $response = wp_remote_get(XT_DZEN_UPDATE_URL, ['timeout' => 10]);
    if (is_wp_error($response)) {
        return $transient;
    }

    $data = json_decode(wp_remote_retrieve_body($response));
    if (!$data || empty($data->version)) {
        return $transient;
    }

    $plugin_file    = plugin_basename(__FILE__);
    $current_version = $transient->checked[$plugin_file] ?? null;

    if ($current_version && version_compare($current_version, $data->version, '<')) {
        $transient->response[$plugin_file] = (object) [
            'slug'        => 'x-tiger-dzen-rss',
            'plugin'      => $plugin_file,
            'new_version' => $data->version,
            'url'         => $data->author_profile ?? '',
            'package'     => $data->download_url,
        ];
    }

    return $transient;
}

add_filter('plugins_api', 'xt_dzen_plugin_info', 20, 3);

function xt_dzen_plugin_info($false, $action, $args) {

    if ($action !== 'plugin_information') {
        return false;
    }

    if ($args->slug !== 'x-tiger-dzen-rss') {
        return false;
    }

    $response = wp_remote_get(XT_DZEN_UPDATE_URL);
    if (is_wp_error($response)) {
        return false;
    }

    return json_decode(wp_remote_retrieve_body($response));
}


/* ======================================================
   Выводим последние записи RSS
====================================================== */


function xt_dzen_get_rss_items_preview($limit = 5) {

    $opt = get_option(XT_DZEN_OPTION, []);
    $items = [];

    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'category_name'  => sanitize_title($opt['category_slug'] ?? 'dzen'),
    ]);

    foreach ($posts as $post) {

        $thumb_id = get_post_thumbnail_id($post->ID);
        if (!$thumb_id) continue;

        $raw   = apply_filters('the_content', $post->post_content);
        $clean = xt_dzen_clean_html($raw);

        if (mb_strlen(strip_tags($clean)) < 300) continue;

        $items[] = [
            'title' => get_the_title($post->ID),
            'date'  => get_the_date('', $post->ID),
            'text'  => mb_substr(strip_tags($clean), 0, 300) . '…',
        ];
    }

    return $items;
}


/* ======================================================
   Индикаторы, почему не прошла модерация
====================================================== */

function xt_dzen_check_post_eligibility($post) {

    $opt = get_option(XT_DZEN_OPTION, []);
    $reasons = [];

    if ($post->post_status !== 'publish') {
        $reasons[] = 'Пост не опубликован';
    }

    if (!has_category($opt['category_slug'], $post)) {
        $reasons[] = 'Пост не в выбранной рубрике';
    }

    $thumb_id = get_post_thumbnail_id($post->ID);
    if (!$thumb_id) {
        $reasons[] = 'Нет изображения записи';
    } else {
        $img = wp_get_attachment_image_src($thumb_id, 'full');
        if (!$img || $img[1] < 700) {
            $reasons[] = 'Изображение меньше 700px по ширине';
        }
    }

    $raw   = apply_filters('the_content', $post->post_content);
    $clean = xt_dzen_clean_html($raw);

    if (mb_strlen(strip_tags($clean)) < 300) {
        $reasons[] = 'Слишком короткий текст после очистки';
    }

    return $reasons;
}





/* ======================================================
   ADMIN
====================================================== */

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin.php';
}

