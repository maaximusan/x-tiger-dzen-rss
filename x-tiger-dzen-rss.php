<?php
/**
 * Plugin Name: X-Tiger RSS for Dzen
 * Description: Безотказная RSS-лента для Дзена с валидацией контента
 * Version: 1.1.0
 * Author: X-Tiger
 * Text Domain: x-tiger-dzen-rss
 */

if (!defined('ABSPATH')) exit;

define('XT_DZEN_OPTION', 'xt_dzen_settings');
define('XT_DZEN_LOG', 'xt_dzen_sent_guids');

/* =====================
   НАСТРОЙКИ ПО УМОЛЧАНИЮ
===================== */
register_activation_hook(__FILE__, function () {
    add_option(XT_DZEN_OPTION, [
        'mode' => 'native-draft', // native | native-draft
        'days_limit' => 3,
        'posts_limit' => 20
    ]);
    add_option(XT_DZEN_LOG, []);
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
   ГЕНЕРАЦИЯ RSS
===================== */
add_action('template_redirect', function () {

    if (get_query_var('xt_dzen_rss') != 1) return;

    header('Content-Type: application/rss+xml; charset=UTF-8');

    $opt = get_option(XT_DZEN_OPTION);
    $sent = get_option(XT_DZEN_LOG, []);

    $query = new WP_Query([
        'post_type' => 'post',
        'post_status' => 'publish',
        'category_name' => 'dzen',
        'posts_per_page' => (int)$opt['posts_limit'],
        'date_query' => [
            [
                'after' => $opt['days_limit'] . ' days ago'
            ]
        ]
    ]);

    echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
<title>X-Tiger — блог о сайтах для предпринимателей</title>
<link><?= esc_url(home_url('/')); ?></link>
<language>ru</language>

<?php while ($query->have_posts()) : $query->the_post();

    $id = get_the_ID();
    $guid = get_permalink($id);

    // ❌ Уже отправляли
    if (in_array($guid, $sent)) continue;

    $thumb_id = get_post_thumbnail_id($id);
    if (!$thumb_id) continue;

    $img = wp_get_attachment_image_src($thumb_id, 'full');
    if (!$img || $img[1] < 700) continue;

    $raw = apply_filters('the_content', get_post_field('post_content', $id));
    $clean = xt_dzen_clean_html($raw);

    if (mb_strlen(strip_tags($clean)) < 300) continue;

    $sent[] = $guid;
?>

<item>
<title><?= esc_html(get_the_title()); ?></title>
<link><?= esc_url($guid); ?></link>
<guid isPermaLink="false"><?= esc_url($guid); ?></guid>
<pubDate><?= mysql2date(DATE_RSS, get_post_time('Y-m-d H:i:s', true)); ?></pubDate>
<category><?= esc_html($opt['mode']); ?></category>
<enclosure url="<?= esc_url($img[0]); ?>" type="image/jpeg"/>
<content:encoded><![CDATA[
<h1><?= esc_html(get_the_title()); ?></h1>
<?= $clean; ?>
]]></content:encoded>
</item>

<?php endwhile;

update_option(XT_DZEN_LOG, $sent);
wp_reset_postdata(); ?>

</channel>
</rss>
<?php exit;
});

/* =====================
   ОЧИСТКА HTML ПОД ДЗЕН
===================== */
function xt_dzen_clean_html($html) {

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

    $allowed = [
        'p','a','b','i','u','s',
        'h1','h2','h3','h4',
        'ul','ol','li',
        'blockquote','figure','img','figcaption'
    ];

    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//*') as $node) {
        if (!in_array($node->nodeName, $allowed)) {
            $node->parentNode->removeChild($node);
            continue;
        }
        while ($node->attributes && $node->attributes->length) {
            $node->removeAttributeNode($node->attributes->item(0));
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }

    return $out;
}

// Админка плагина
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin.php';
}


/* =====================
   GITHUB REQUEST (PRIVATE REPO)
===================== */
function xt_dzen_github_request($url) {

    if (!defined('XT_DZEN_GITHUB_TOKEN')) {
        return new WP_Error('no_token', 'GitHub token is not defined');
    }

    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . XT_DZEN_GITHUB_TOKEN,
            'Accept'        => 'application/vnd.github+json',
            'User-Agent'    => 'WordPress-X-Tiger-Dzen-RSS'
        ],
        'timeout' => 20
    ];

    return wp_remote_get($url, $args);
}



add_filter('pre_set_site_transient_update_plugins', 'xt_dzen_check_update');
add_filter('plugins_api', 'xt_dzen_plugin_info', 20, 3);

define(
  'XT_DZEN_UPDATE_URL',
  'https://api.github.com/repos/maaximusan/x-tiger-dzen-rss/contents/update.json'
);


function xt_dzen_check_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $response = xt_dzen_github_request(XT_DZEN_UPDATE_URL);
    if (is_wp_error($response)) return $transient;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($body['content'])) return $transient;
    $json = base64_decode($body['content']);
    $data = json_decode($json);


    $plugin_file = plugin_basename(__FILE__);
    $current_version = $transient->checked[$plugin_file];

    if (version_compare($current_version, $data->version, '<')) {
        $transient->response[$plugin_file] = (object) [
            'slug'        => 'x-tiger-dzen-rss',
            'plugin'      => $plugin_file,
            'new_version' => $data->version,
            'url'         => $data->author_profile,
            'package'     => $data->download_url,
        ];
    }

    return $transient;
}

function xt_dzen_plugin_info($false, $action, $args) {
    if ($action !== 'plugin_information') return false;
    if ($args->slug !== 'x-tiger-dzen-rss') return false;

    $response = xt_dzen_github_request(XT_DZEN_UPDATE_URL);
    if (is_wp_error($response)) return false;

    return json_decode(wp_remote_retrieve_body($response));
}