<?php
/**
 * Plugin Name: X-Tiger RSS for Dzen
 * Description: RSS-лента для публикации статей в Яндекс.Дзен с корректной разметкой
 * Version: 1.1.2
 * Author: X-Tiger
 * Text Domain: x-tiger-dzen-rss
 */

if (!defined('ABSPATH')) exit;

/* =====================
   КОНСТАНТЫ
===================== */
if (!defined('XT_DZEN_OPTION')) {
    define('XT_DZEN_OPTION', 'xt_dzen_settings');
}

/* =====================
   АКТИВАЦИЯ
===================== */
register_activation_hook(__FILE__, function () {
    add_option(XT_DZEN_OPTION, [
        'mode'                => 'native',
        'days_limit'          => 30,
        'posts_limit'         => 20,
        'channel_description' => 'Авторский блог о сайтах и цифровых продуктах. Аналитика, наблюдения и объяснение типичных ситуаций в бизнесе без рекламы и призывов.',
    ]);
    flush_rewrite_rules();
});

/* =====================
   RSS ENDPOINT
===================== */
add_action('init', function () {
    add_rewrite_rule('^dzen/rss\.xml$', 'index.php?xt_dzen_rss=1', 'top');
    add_rewrite_tag('%xt_dzen_rss%', '1');
});

/* =====================
   RSS GENERATION
===================== */
add_action('template_redirect', function () {

    if ((int) get_query_var('xt_dzen_rss') !== 1) {
        return;
    }

    header('Content-Type: application/rss+xml; charset=UTF-8');

    $opt = get_option(XT_DZEN_OPTION, []);

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
        'category_name'    => 'dzen',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post) {
        setup_postdata($post);

        $id   = $post->ID;
        $guid = get_permalink($id);

        // изображение обязательно
        $thumb_id = get_post_thumbnail_id($id);
        if (!$thumb_id) continue;

        $img = wp_get_attachment_image_src($thumb_id, 'full');
        if (!$img || $img[1] < 700) continue;

        // контент
        $raw   = apply_filters('the_content', $post->post_content);
        $clean = xt_dzen_clean_html($raw);

        // минимальная длина уже ПОСЛЕ очистки
        if (mb_strlen(strip_tags($clean)) < 300) continue;
?>
    <item>
        <title><?= esc_html(get_the_title($id)); ?></title>
        <link><?= esc_url($guid); ?></link>
        <guid isPermaLink="true"><?= esc_url($guid); ?></guid>
        <pubDate><?= mysql2date(DATE_RSS, get_post_time('Y-m-d H:i:s', true, $id)); ?></pubDate>
        <category><?= esc_html($opt['mode'] ?? 'native'); ?></category>
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

/* =====================
   HTML CLEANER FOR DZEN
===================== */
function xt_dzen_clean_html($html) {

    libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

    // ВАЖНО: h1 УБРАН
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

        // чистим все атрибуты
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

/* =====================
   ADMIN
===================== */
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin.php';
}
