<?php

declare(strict_types=1);

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';

use Elementor\Core\Files\CSS\Post as Elementor_Post_CSS;
use Elementor\Plugin as Elementor_Plugin;
use ElementorPro\Modules\ThemeBuilder\Module as Theme_Builder_Module;

const TC_HOME_KIT_ID = 11;
const TC_HOME_OPTION = 'titip_cirebon_homepage_id';
const TC_HOME_SUPPORT_OPTION = 'titip_cirebon_homepage_support_ids';
const TC_HOME_MEDIA_OPTION = 'titip_cirebon_homepage_placeholder_ids';
const TC_HOME_SEO_OPTION = 'titip_cirebon_homepage_seo_snippet_id';
const TC_HOME_MARKER = '_titip_cirebon_homepage_owned';
const TC_HOME_CSS_START = '/* TITIP CIREBON HOMEPAGE: START */';
const TC_HOME_CSS_END = '/* TITIP CIREBON HOMEPAGE: END */';
const TC_HOME_META_DESCRIPTION = 'Titip jajanan dan produk pilihan langsung dari Cirebon untuk kamu di Sumedang. Open PO Senin–Kamis, pengiriman setiap Sabtu.';

function tc_home_fail(string $message): never
{
    throw new RuntimeException($message);
}

function tc_home_assert(bool $condition, string $message): void
{
    if (!$condition) {
        tc_home_fail($message);
    }
}

function tc_home_element_id(string $name): string
{
    return substr(md5('titip-cirebon-homepage:' . $name), 0, 7);
}

function tc_home_global_color(string $id): string
{
    return 'globals/colors?id=' . $id;
}

function tc_home_global_typography(string $id): string
{
    return 'globals/typography?id=' . $id;
}

function tc_home_widget(string $name, string $type, array $settings): array
{
    return [
        'id' => tc_home_element_id($name),
        'elType' => 'widget',
        'settings' => $settings,
        'elements' => [],
        'widgetType' => $type,
    ];
}

function tc_home_container(string $name, string $classes, array $elements, array $settings = []): array
{
    return [
        'id' => tc_home_element_id($name),
        'elType' => 'container',
        'settings' => array_merge([
            'content_width' => 'full',
            'flex_direction' => 'column',
            'css_classes' => $classes,
        ], $settings),
        'elements' => $elements,
        'isInner' => false,
    ];
}

function tc_home_heading(string $name, string $title, string $tag, string $typography, string $classes = ''): array
{
    return tc_home_widget($name, 'heading', [
        'title' => $title,
        'header_size' => $tag,
        '_css_classes' => $classes,
        '__globals__' => [
            'title_color' => tc_home_global_color('tc-primary-dark'),
            'typography_typography' => tc_home_global_typography($typography),
        ],
    ]);
}

function tc_home_text(string $name, string $html, string $classes = '', string $color = 'text'): array
{
    return tc_home_widget($name, 'text-editor', [
        'editor' => $html,
        '_css_classes' => $classes,
        '__globals__' => [
            'text_color' => tc_home_global_color($color),
            'typography_typography' => tc_home_global_typography('tc-body'),
        ],
    ]);
}

function tc_home_button(string $name, string $label, string $url, string $classes = ''): array
{
    return tc_home_widget($name, 'button', [
        'text' => $label,
        'link' => [
            'url' => $url,
            'is_external' => '',
            'nofollow' => '',
            'custom_attributes' => '',
        ],
        'size' => 'sm',
        '_css_classes' => $classes,
    ]);
}

function tc_home_image(string $name, int $attachmentId, string $classes): array
{
    $url = wp_get_attachment_image_url($attachmentId, 'full');
    tc_home_assert(is_string($url) && $url !== '', "Attachment {$attachmentId} has no image URL.");
    return tc_home_widget($name, 'image', [
        'image' => ['url' => $url, 'id' => $attachmentId, 'size' => ''],
        'image_size' => 'full',
        'caption_source' => 'none',
        'link_to' => 'none',
        '_css_classes' => $classes,
    ]);
}

function tc_home_template(string $name, int $templateId, string $classes = ''): array
{
    return tc_home_widget($name, 'template', [
        'template_id' => $templateId,
        '_css_classes' => $classes,
    ]);
}

function tc_home_preflight(): void
{
    global $wpdb, $wp_version;

    tc_home_assert($wp_version === '7.1.4', "WordPress {$wp_version} found; expected 7.1.4.");
    tc_home_assert(PHP_VERSION === '8.4.24', 'PHP 8.4.24 is required.');
    tc_home_assert($wpdb->db_version() === '8.0.46', 'MySQL 8.0.46 is required.');
    tc_home_assert(is_plugin_active('elementor/elementor.php'), 'Elementor Free is not active.');
    tc_home_assert(is_plugin_active('elementor-pro/elementor-pro.php'), 'Elementor Pro is not active.');
    tc_home_assert(defined('ELEMENTOR_VERSION') && ELEMENTOR_VERSION === '4.2.2', 'Elementor Free 4.2.2 is required.');
    tc_home_assert(defined('ELEMENTOR_PRO_VERSION') && ELEMENTOR_PRO_VERSION === '4.0.4', 'Elementor Pro 4.0.4 is required.');

    $plugins = (array) get_option('active_plugins', []);
    sort($plugins);
    tc_home_assert($plugins === ['elementor-pro/elementor-pro.php', 'elementor/elementor.php'], 'Only Elementor Free and Elementor Pro may be active.');

    $theme = wp_get_theme();
    tc_home_assert($theme->get_stylesheet() === 'hello-elementor', 'Hello Elementor must remain active.');
    tc_home_assert($theme->get('Version') === '3.4.9', 'Hello Elementor 3.4.9 is required.');
    tc_home_assert((int) get_option('elementor_active_kit') === TC_HOME_KIT_ID, 'Active Elementor Kit must remain ID 11.');
    tc_home_assert(function_exists('imagepng') && extension_loaded('gd'), 'PHP GD with PNG support is required for deterministic placeholders.');
    tc_home_assert(post_type_exists('elementor_snippet'), 'Elementor Pro Custom Code is unavailable.');

    $componentIds = get_option('titip_cirebon_site_components_ids', []);
    tc_home_assert(is_array($componentIds), 'Sprint 1C component registry is missing.');
    foreach (['header', 'footer', 'whatsapp-cta', 'trust-cards'] as $key) {
        $id = (int) ($componentIds[$key] ?? 0);
        tc_home_assert($id > 0 && get_post_meta($id, '_titip_cirebon_site_component', true) === $key, "Sprint 1C component {$key} is missing.");
    }

    $hotfixes = [
        '/var/www/html/wp-content/plugins/elementor/modules/global-classes/atomic-global-styles.php' => '?string $key = null',
        '/var/www/html/wp-content/plugins/elementor/app/modules/import-export-customization/processes/import.php' => '?array $old_instance = null',
        '/var/www/html/wp-content/plugins/elementor/app/modules/import-export/processes/import.php' => '?array $old_instance = null',
        '/var/www/html/wp-content/plugins/elementor/core/utils/import-export/wp-exporter.php' => '?array $post_ids = null',
        '/var/www/html/wp-content/plugins/elementor-pro/core/database/base-migration.php' => '?\\wpdb $wpdb_instance = null',
        '/var/www/html/wp-content/plugins/elementor-pro/modules/notes/database/models/document.php' => '?\\wpdb $connection = null',
        '/var/www/html/wp-content/plugins/elementor-pro/modules/notes/database/models/note.php' => '?\\wpdb $connection = null',
        '/var/www/html/wp-content/plugins/elementor-pro/modules/notes/database/models/user.php' => '?\\wpdb $connection = null',
        '/var/www/html/wp-content/plugins/elementor-pro/modules/notes/database/models/note-summary.php' => '?\\wpdb $connection = null',
        '/var/www/html/wp-content/plugins/elementor-pro/modules/notes/database/query/user-query-builder.php' => '?\\wpdb $connection = null',
        '/var/www/html/wp-content/plugins/elementor-pro/core/database/model-base.php' => '?\\wpdb $connection = null',
        '/var/www/html/wp-content/plugins/elementor-pro/core/database/join-clause.php' => '?\\wpdb $connection = null',
    ];
    foreach ($hotfixes as $file => $signature) {
        $source = is_file($file) ? file_get_contents($file) : false;
        tc_home_assert(is_string($source) && str_contains($source, $signature), "Required PHP 8.4 hotfix is missing: {$file}.");
    }
}

function tc_home_resolve_home(bool $allowCreate): int
{
    $storedId = (int) get_option(TC_HOME_OPTION, 0);
    $slugPage = get_page_by_path('home', OBJECT, 'page');

    if ($storedId > 0) {
        $post = get_post($storedId);
        tc_home_assert($post instanceof WP_Post && $post->post_type === 'page', 'Stored Home ID is invalid.');
        tc_home_assert(get_post_meta($storedId, TC_HOME_MARKER, true) === 'home', 'Stored Home is missing its ownership marker.');
        tc_home_assert(!$slugPage || (int) $slugPage->ID === $storedId, 'The home slug conflicts with another page.');
        return $storedId;
    }

    if ($slugPage) {
        tc_home_assert(get_post_meta((int) $slugPage->ID, TC_HOME_MARKER, true) === 'home', 'The home slug is owned by user content; refusing to overwrite it.');
        update_option(TC_HOME_OPTION, (int) $slugPage->ID);
        return (int) $slugPage->ID;
    }

    tc_home_assert($allowCreate, 'Owned Home page has not been created.');
    $pageId = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'draft',
        'post_title' => 'Home',
        'post_name' => 'home',
        'post_content' => '',
        'comment_status' => 'closed',
    ], true);
    tc_home_assert(!is_wp_error($pageId), 'Could not create Home page.');
    $pageId = (int) $pageId;
    update_post_meta($pageId, TC_HOME_MARKER, 'home');
    update_option(TC_HOME_OPTION, $pageId);
    return $pageId;
}

function tc_home_support_specs(): array
{
    return [
        'produk' => ['Produk', 'produk'],
        'cara-order' => ['Cara Order', 'cara-order'],
        'lacak-pesanan' => ['Lacak Pesanan', 'lacak-pesanan'],
        'tentang-kami' => ['Tentang Kami', 'tentang-kami'],
    ];
}

function tc_home_resolve_support(string $key, string $title, string $slug, bool $allowCreate): array
{
    $ids = get_option(TC_HOME_SUPPORT_OPTION, []);
    $ids = is_array($ids) ? array_map('intval', $ids) : [];
    $storedId = (int) ($ids[$key] ?? 0);
    $slugPage = get_page_by_path($slug, OBJECT, 'page');

    if ($storedId > 0) {
        $post = get_post($storedId);
        tc_home_assert($post instanceof WP_Post && $post->post_type === 'page' && $post->post_name === $slug, "Stored support page {$key} is invalid.");
        tc_home_assert(!$slugPage || (int) $slugPage->ID === $storedId, "Support slug {$slug} conflicts with another page.");
        $marker = (string) get_post_meta($storedId, TC_HOME_MARKER, true);
        if ($marker === $key) {
            return ['id' => $storedId, 'managed' => true];
        }
        tc_home_assert($post->post_status === 'publish', "Reused support page {$slug} must remain published.");
        return ['id' => $storedId, 'managed' => false];
    }

    if ($slugPage) {
        $slugId = (int) $slugPage->ID;
        $marker = (string) get_post_meta($slugId, TC_HOME_MARKER, true);
        if ($marker !== $key) {
            tc_home_assert($slugPage->post_status === 'publish', "Support slug {$slug} is user-owned but not published; refusing to overwrite it.");
            $ids[$key] = $slugId;
            update_option(TC_HOME_SUPPORT_OPTION, $ids);
            return ['id' => $slugId, 'managed' => false];
        }
        $ids[$key] = $slugId;
        update_option(TC_HOME_SUPPORT_OPTION, $ids);
        return ['id' => $slugId, 'managed' => true];
    }

    tc_home_assert($allowCreate, "Owned support page {$slug} has not been created.");
    $pageId = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'draft',
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => '',
        'comment_status' => 'closed',
    ], true);
    tc_home_assert(!is_wp_error($pageId), "Could not create support page {$title}.");
    $pageId = (int) $pageId;
    update_post_meta($pageId, TC_HOME_MARKER, $key);
    $ids[$key] = $pageId;
    update_option(TC_HOME_SUPPORT_OPTION, $ids);
    return ['id' => $pageId, 'managed' => true];
}

function tc_home_placeholder_specs(): array
{
    return [
        'hero' => [
            'slug' => 'tc-home-hero-placeholder',
            'filename' => 'titip-cirebon-home-hero-placeholder.png',
            'title' => 'Titip Cirebon — Placeholder Hero',
            'alt' => 'Placeholder foto produk dan jajanan Cirebon',
            'width' => 1200,
            'height' => 900,
            'label' => 'FOTO PRODUK / JAJANAN CIREBON',
        ],
        'product' => [
            'slug' => 'tc-home-product-placeholder',
            'filename' => 'titip-cirebon-home-product-placeholder.png',
            'title' => 'Titip Cirebon — Placeholder Produk',
            'alt' => 'Placeholder foto produk',
            'width' => 800,
            'height' => 600,
            'label' => 'FOTO PRODUK 4:3',
        ],
    ];
}

function tc_home_draw_placeholder(string $path, int $width, int $height, string $label): void
{
    $image = imagecreatetruecolor($width, $height);
    tc_home_assert($image instanceof GdImage, 'Could not allocate placeholder image.');
    $background = imagecolorallocate($image, 255, 253, 248);
    $surface = imagecolorallocate($image, 255, 247, 237);
    $primary = imagecolorallocate($image, 180, 83, 9);
    $secondary = imagecolorallocate($image, 15, 118, 110);
    $text = imagecolorallocate($image, 120, 113, 108);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);
    imagefilledrectangle($image, 36, 36, $width - 37, $height - 37, $surface);
    imagefilledrectangle($image, 36, 36, (int) ($width * 0.58), 48, $primary);
    imagefilledrectangle($image, (int) ($width * 0.58), 36, $width - 37, 48, $secondary);
    $caption = '[ ' . $label . ' ]';
    $captionWidth = imagefontwidth(5) * strlen($caption);
    imagestring($image, 5, max(48, (int) (($width - $captionWidth) / 2)), (int) (($height - imagefontheight(5)) / 2), $caption, $text);
    imagestring($image, 3, max(48, (int) (($width - (imagefontwidth(3) * 38)) / 2)), (int) ($height / 2) + 34, 'Ganti melalui widget Image di Elementor', $text);
    tc_home_assert(imagepng($image, $path, 9), "Could not write placeholder {$path}.");
    imagedestroy($image);
}

function tc_home_resolve_placeholder(string $key, array $spec, bool $allowCreate): int
{
    $ids = get_option(TC_HOME_MEDIA_OPTION, []);
    $ids = is_array($ids) ? array_map('intval', $ids) : [];
    $storedId = (int) ($ids[$key] ?? 0);
    $slugAttachment = get_page_by_path($spec['slug'], OBJECT, 'attachment');

    if ($storedId > 0) {
        tc_home_assert(get_post_type($storedId) === 'attachment', "Stored {$key} placeholder is invalid.");
        tc_home_assert(get_post_meta($storedId, TC_HOME_MARKER, true) === 'media-' . $key, "Stored {$key} placeholder is missing ownership.");
        tc_home_assert(!$slugAttachment || (int) $slugAttachment->ID === $storedId, "Placeholder slug {$spec['slug']} conflicts.");
        tc_home_assert(is_file(get_attached_file($storedId)), "Stored {$key} placeholder file is missing.");
        return $storedId;
    }

    if ($slugAttachment) {
        $slugId = (int) $slugAttachment->ID;
        tc_home_assert(get_post_meta($slugId, TC_HOME_MARKER, true) === 'media-' . $key, "Placeholder slug {$spec['slug']} is user-owned; refusing to overwrite it.");
        $ids[$key] = $slugId;
        update_option(TC_HOME_MEDIA_OPTION, $ids);
        return $slugId;
    }

    tc_home_assert($allowCreate, "Managed {$key} placeholder has not been created.");
    $uploads = wp_upload_dir();
    tc_home_assert(empty($uploads['error']), 'WordPress uploads directory is unavailable: ' . (string) $uploads['error']);
    $path = trailingslashit($uploads['path']) . $spec['filename'];
    tc_home_assert(!file_exists($path), "Unmanaged upload already exists at {$path}; refusing to overwrite it.");
    tc_home_draw_placeholder($path, $spec['width'], $spec['height'], $spec['label']);

    $attachmentId = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => $spec['title'],
        'post_name' => $spec['slug'],
        'post_content' => '',
        'post_status' => 'inherit',
    ], $path, 0, true);
    tc_home_assert(!is_wp_error($attachmentId), "Could not register {$key} placeholder attachment.");
    $attachmentId = (int) $attachmentId;
    wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $path));
    update_post_meta($attachmentId, '_wp_attachment_image_alt', $spec['alt']);
    update_post_meta($attachmentId, TC_HOME_MARKER, 'media-' . $key);
    $ids[$key] = $attachmentId;
    update_option(TC_HOME_MEDIA_OPTION, $ids);
    return $attachmentId;
}

function tc_home_existing_image_ids(int $homeId): array
{
    $raw = get_post_meta($homeId, '_elementor_data', true);
    $elements = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($elements)) {
        return [];
    }

    $images = [];
    $walk = static function (array $nodes) use (&$walk, &$images): void {
        foreach ($nodes as $node) {
            if (($node['widgetType'] ?? '') === 'image') {
                $id = (string) ($node['id'] ?? '');
                $attachmentId = (int) ($node['settings']['image']['id'] ?? 0);
                if ($id !== '' && $attachmentId > 0) {
                    $images[$id] = $attachmentId;
                }
            }
            if (isset($node['elements']) && is_array($node['elements'])) {
                $walk($node['elements']);
            }
        }
    };
    $walk($elements);
    return $images;
}

function tc_home_selected_image(string $widgetName, int $placeholderId, array $managedIds, array $existingImages): int
{
    $widgetId = tc_home_element_id($widgetName);
    $existingId = (int) ($existingImages[$widgetId] ?? 0);
    if (
        $existingId > 0
        && !in_array($existingId, $managedIds, true)
        && get_post_type($existingId) === 'attachment'
        && str_starts_with((string) get_post_mime_type($existingId), 'image/')
    ) {
        return $existingId;
    }
    return $placeholderId;
}

function tc_home_section_heading(string $name, string $title, string $description): array
{
    return tc_home_container($name . '-heading-wrap', 'tc-home-section-heading tc-local-accent', [
        tc_home_heading($name . '-heading', $title, 'h2', 'tc-h2'),
        tc_home_text($name . '-description', '<p>' . esc_html($description) . '</p>', '', 'tc-text-muted'),
    ]);
}

function tc_home_product_card(string $key, array $product, int $imageId): array
{
    $badgeClass = match ($product['badge']) {
        'Baru' => 'tc-badge--secondary',
        'Terlaris' => 'tc-badge--accent',
        default => 'tc-badge--primary',
    };
    $media = tc_home_image($key . '-image', $imageId, 'tc-product-card__media tc-home-product-image');
    $content = tc_home_container($key . '-content', 'tc-product-card__content', [
        tc_home_widget($key . '-badge', 'html', [
            'html' => '<div class="tc-badges"><span class="tc-badge ' . esc_attr($badgeClass) . '">' . esc_html($product['badge']) . '</span></div>',
        ]),
        tc_home_heading($key . '-title', $product['name'], 'h3', 'tc-h4', 'tc-product-card__title'),
        tc_home_text($key . '-description', '<p>' . esc_html($product['description']) . '</p>', 'tc-product-card__description', 'tc-text-muted'),
        tc_home_widget($key . '-price', 'html', ['html' => '<p class="tc-product-card__price">Rp xx.xxx</p>']),
        tc_home_container($key . '-footer', 'tc-product-card__footer', [
            tc_home_widget($key . '-price-note', 'html', ['html' => '<span class="tc-preview-note">Harga masih placeholder</span>']),
            tc_home_button($key . '-button', 'Pesan Produk', home_url('/#whatsapp-order')),
        ], ['flex_direction' => 'row', 'align_items' => 'center', 'justify_content' => 'space-between', 'flex_wrap' => 'wrap']),
    ]);
    return tc_home_container($key . '-card', 'tc-product-card tc-home-product-card', [$media, $content]);
}

function tc_home_step(string $key, string $number, string $title, string $description): array
{
    return tc_home_container($key, 'tc-card tc-home-step', [
        tc_home_widget($key . '-number', 'html', ['html' => '<span class="tc-home-step__number" aria-hidden="true">' . esc_html($number) . '</span>']),
        tc_home_heading($key . '-title', $title, 'h3', 'tc-h5'),
        tc_home_text($key . '-copy', '<p>' . esc_html($description) . '</p>', '', 'tc-text-muted'),
    ]);
}

function tc_home_info_card(string $key, string $eyebrow, string $title, string $description): array
{
    return tc_home_container($key, 'tc-card tc-home-info-card', [
        tc_home_widget($key . '-eyebrow', 'html', ['html' => '<p class="tc-eyebrow">' . esc_html($eyebrow) . '</p>']),
        tc_home_heading($key . '-title', $title, 'h3', 'tc-h5'),
        tc_home_text($key . '-copy', '<p>' . esc_html($description) . '</p>', '', 'tc-text-muted'),
    ]);
}

function tc_home_elements(int $heroImageId, array $productImageIds, array $componentIds, array $supportIds): array
{
    $heroCopy = tc_home_container('hero-copy', 'tc-home-hero__copy tc-local-accent', [
        tc_home_heading('hero-title', 'Pengen jajanan Cirebon?', 'h1', 'tc-h1'),
        tc_home_text('hero-description', '<p>Kami titipkan produk dan jajanan pilihan langsung dari Cirebon untuk kamu di Sumedang.</p>', 'tc-home-hero__description'),
        tc_home_container('hero-actions', 'tc-home-actions', [
            tc_home_button('hero-primary', 'Lihat PO Minggu Ini', '#produk'),
            tc_home_button('hero-secondary', 'Cara Order', '#cara-order', 'tc-button--secondary'),
        ], ['flex_direction' => 'row', 'flex_wrap' => 'wrap']),
        tc_home_widget('hero-schedule', 'html', ['html' => '<p class="tc-home-trust-note">Open PO Senin–Kamis <span aria-hidden="true">•</span> Kirim setiap Sabtu</p>']),
    ]);
    $heroVisual = tc_home_container('hero-visual', 'tc-home-hero__visual', [
        tc_home_image('hero-image', $heroImageId, 'tc-home-hero__image'),
    ]);
    $hero = tc_home_container('hero-section', 'tc-home-hero tc-section', [
        tc_home_container('hero-inner', 'tc-shell-container tc-home-hero__inner', [$heroCopy, $heroVisual]),
    ]);

    $products = [
        ['name' => 'Bandung Cheesecuit', 'badge' => 'PO Minggu Ini', 'description' => 'Produk pilihan dari Cirebon.'],
        ['name' => 'Produk Pilihan Cirebon', 'badge' => 'Baru', 'description' => 'Placeholder produk yang akan ditawarkan.'],
        ['name' => 'Jajanan Cirebon Pilihan', 'badge' => 'Terlaris', 'description' => 'Placeholder produk.'],
    ];
    $cards = [];
    foreach ($products as $index => $product) {
        $cards[] = tc_home_product_card('product-' . ($index + 1), $product, $productImageIds[$index]);
    }
    $productSection = tc_home_container('products-section', 'tc-home-products tc-section', [
        tc_home_container('products-inner', 'tc-shell-container', [
            tc_home_section_heading('products', 'PO Minggu Ini', 'Pilihan produk yang sedang kami tawarkan minggu ini.'),
            tc_home_container('products-grid', 'tc-home-product-grid', $cards),
            tc_home_button('products-all-button', 'Lihat Semua Produk', get_permalink($supportIds['produk']), 'tc-button--secondary tc-home-centered-button'),
        ]),
    ], ['_element_id' => 'produk']);

    $trust = tc_home_container('trust-section', 'tc-home-trust tc-section', [
        tc_home_container('trust-inner', 'tc-shell-container', [
            tc_home_section_heading('trust', 'Kenapa Titip Cirebon?', 'Cara praktis menikmati pilihan dari Cirebon tanpa harus pergi sendiri.'),
            tc_home_template('trust-template', (int) $componentIds['trust-cards'], 'tc-home-trust-template'),
        ]),
    ], ['_element_id' => 'tentang-kami']);

    $orderSteps = [
        tc_home_step('order-step-1', '01', 'Pilih Produk', 'Lihat produk yang tersedia pada PO minggu ini.'),
        tc_home_step('order-step-2', '02', 'Kirim Pesanan', 'Pesan melalui WhatsApp dan informasikan produk yang kamu inginkan.'),
        tc_home_step('order-step-3', '03', 'Kami Belikan di Cirebon', 'Pesanan diproses dan dibelikan langsung di Cirebon.'),
        tc_home_step('order-step-4', '04', 'Sabtu Dikirim', 'Pesanan dibawa ke Sumedang untuk diantar atau COD sesuai kesepakatan.'),
    ];
    $order = tc_home_container('order-section', 'tc-home-order tc-section', [
        tc_home_container('order-inner', 'tc-shell-container', [
            tc_home_section_heading('order', 'Cara Order', 'Empat langkah sederhana dari memilih produk sampai pesanan diterima.'),
            tc_home_container('order-grid', 'tc-home-order-grid', $orderSteps),
        ]),
    ], ['_element_id' => 'cara-order']);

    $compactCta = tc_home_container('compact-cta-section', 'tc-home-cta tc-home-cta--compact tc-section--compact', [
        tc_home_container('compact-cta-inner', 'tc-shell-container', [
            tc_home_template('compact-whatsapp-template', (int) $componentIds['whatsapp-cta']),
        ]),
    ], ['_element_id' => 'whatsapp-order-compact']);

    $schedule = tc_home_container('schedule-section', 'tc-home-schedule tc-section', [
        tc_home_container('schedule-inner', 'tc-shell-container', [
            tc_home_section_heading('schedule', 'Jadwal PO', 'Pesan pada jadwal berikut agar proses titip dan pengiriman tetap jelas.'),
            tc_home_container('schedule-grid', 'tc-home-schedule-grid', [
                tc_home_info_card('schedule-monday', 'Senin–Kamis', 'Open PO', 'Pilih produk dan kirim pesananmu.'),
                tc_home_info_card('schedule-friday', 'Jumat', 'Proses dan persiapan pesanan', 'Pesanan direkap dan disiapkan di Cirebon.'),
                tc_home_info_card('schedule-saturday', 'Sabtu', 'Pengiriman ke Sumedang', 'Pesanan dibawa untuk diantar atau COD.'),
            ]),
        ]),
    ]);

    $shipping = tc_home_container('shipping-section', 'tc-home-shipping tc-section', [
        tc_home_container('shipping-inner', 'tc-shell-container', [
            tc_home_section_heading('shipping', 'Area Pengiriman', 'Informasi pengiriman awal untuk wilayah Sumedang.'),
            tc_home_container('shipping-grid', 'tc-home-shipping-grid', [
                tc_home_info_card('shipping-free', 'Gratis Ongkir', 'Cimalaka dan Sumedang Kota', 'Gratis ongkir untuk Cimalaka serta Sumedang Kota / pusat kota.'),
                tc_home_info_card('shipping-other', 'Area Lainnya', 'Ongkir sesuai jarak', 'Untuk area lainnya, ongkir disesuaikan dengan jarak.'),
            ]),
            tc_home_widget('shipping-cod', 'html', ['html' => '<p class="tc-home-shipping-note"><strong>Bisa diantar sampai rumah atau COD.</strong></p>']),
        ]),
    ]);

    $tracking = tc_home_container('tracking-section', 'tc-home-tracking tc-section--compact', [
        tc_home_container('tracking-inner', 'tc-shell-container tc-card tc-home-tracking__card', [
            tc_home_container('tracking-copy', 'tc-home-tracking__copy', [
                tc_home_heading('tracking-title', 'Sudah pesan?', 'h2', 'tc-h2'),
                tc_home_text('tracking-description', '<p>Nanti kamu bisa mengecek perkembangan pesanan menggunakan Kode Pesanan.</p>', '', 'tc-text-muted'),
            ]),
            tc_home_button('tracking-button', 'Lacak Pesanan', get_permalink($supportIds['lacak-pesanan']), 'tc-button--utility'),
        ]),
    ], ['_element_id' => 'lacak-pesanan']);

    $finalCta = tc_home_container('final-cta-section', 'tc-home-cta tc-home-cta--final tc-section', [
        tc_home_container('final-cta-inner', 'tc-shell-container', [
            tc_home_template('final-whatsapp-template', (int) $componentIds['whatsapp-cta']),
        ]),
    ], ['_element_id' => 'whatsapp-order']);

    return [tc_home_container('homepage-root', 'tc-homepage', [
        $hero,
        $productSection,
        $trust,
        $order,
        $compactCta,
        $schedule,
        $shipping,
        $tracking,
        $finalCta,
    ])];
}

function tc_home_support_elements(string $key): array
{
    $specs = [
        'produk' => [
            'Produk',
            '<p>Katalog lengkap Titip Cirebon belum tersedia. Lihat tiga pilihan placeholder pada PO Minggu Ini di homepage.</p>',
            'Lihat PO Minggu Ini',
            home_url('/#produk'),
        ],
        'cara-order' => [
            'Cara Order',
            '<p>Pilih produk pada PO minggu ini, kirim pesanan melalui WhatsApp, lalu kami belikan di Cirebon. Pesanan dibawa ke Sumedang setiap Sabtu untuk diantar atau COD sesuai kesepakatan.</p>',
            'Lihat Langkah Order',
            home_url('/#cara-order'),
        ],
        'lacak-pesanan' => [
            'Lacak Pesanan',
            '<p>Fitur pelacakan pesanan sedang kami siapkan.</p><p>Setelah melakukan pemesanan, kamu nantinya bisa menggunakan Kode Pesanan untuk melihat perkembangan pesananmu.</p>',
            'Kembali ke Home',
            home_url('/'),
        ],
        'tentang-kami' => [
            'Tentang Kami',
            '<p>Titip Cirebon membantu masyarakat Sumedang menitip produk dan jajanan pilihan dari Cirebon tanpa perlu pergi dan antre sendiri.</p><p>Open PO berlangsung Senin–Kamis, Jumat untuk proses pesanan, dan pengiriman ke Sumedang setiap Sabtu.</p>',
            'Lihat Cara Order',
            home_url('/#cara-order'),
        ],
    ];
    tc_home_assert(isset($specs[$key]), "Unknown support page {$key}.");
    [$title, $copy, $button, $url] = $specs[$key];
    return [tc_home_container('support-' . $key . '-root', 'tc-support-page tc-section', [
        tc_home_container('support-' . $key . '-inner', 'tc-shell-container tc-card tc-support-page__card tc-local-accent', [
            tc_home_heading('support-' . $key . '-title', $title, 'h1', 'tc-h1'),
            tc_home_text('support-' . $key . '-copy', $copy),
            tc_home_button('support-' . $key . '-button', $button, $url, 'tc-button--secondary'),
        ]),
    ])];
}

function tc_home_css(): string
{
    return <<<'CSS'
/* Sprint 2 homepage layout; values reuse the Sprint 1B token contract. */
.elementor-kit-11 .tc-homepage {
    overflow: clip;
    color: var(--tc-text);
    background: var(--tc-background);
}

.elementor-kit-11 .tc-homepage .tc-shell-container,
.elementor-kit-11 .tc-support-page .tc-shell-container {
    width: min(100%, 1200px);
    margin-inline: auto;
    padding-inline: 24px;
}

.elementor-kit-11 .tc-home-section-heading {
    max-width: 720px;
    margin-bottom: 36px;
}

.elementor-kit-11 .tc-home-section-heading .elementor-widget-heading,
.elementor-kit-11 .tc-home-section-heading .elementor-widget-text-editor,
.elementor-kit-11 .tc-home-hero__copy .elementor-widget-heading,
.elementor-kit-11 .tc-home-hero__copy .elementor-widget-text-editor,
.elementor-kit-11 .tc-home-step .elementor-widget-heading,
.elementor-kit-11 .tc-home-step .elementor-widget-text-editor,
.elementor-kit-11 .tc-home-info-card .elementor-widget-heading,
.elementor-kit-11 .tc-home-info-card .elementor-widget-text-editor {
    margin-bottom: 0;
}

.elementor-kit-11 .tc-home-hero {
    background: linear-gradient(135deg, rgba(255, 253, 248, 1), rgba(245, 158, 11, 0.10));
}

.elementor-kit-11 .tc-home-hero__inner {
    display: grid;
    grid-template-columns: minmax(0, 1.02fr) minmax(360px, 0.98fr);
    gap: 64px;
    align-items: center;
}

.elementor-kit-11 .tc-home-hero__copy {
    gap: 22px;
}

.elementor-kit-11 .tc-home-hero__description {
    max-width: 650px;
    font-size: 18px;
}

.elementor-kit-11 .tc-home-actions {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 12px;
}

.elementor-kit-11 .tc-home-trust-note {
    margin: 0;
    color: var(--tc-text-muted);
    font: 600 14px/1.5 Inter, system-ui, sans-serif;
}

.elementor-kit-11 .tc-home-hero__visual {
    position: relative;
}

.elementor-kit-11 .tc-home-hero__image {
    overflow: hidden;
    margin: 0;
    border: 1px solid var(--tc-border);
    border-radius: var(--tc-radius-lg);
    box-shadow: var(--tc-shadow-elevated);
}

.elementor-kit-11 .tc-home-hero__image img {
    display: block;
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
}

.elementor-kit-11 .tc-home-products,
.elementor-kit-11 .tc-home-order,
.elementor-kit-11 .tc-home-shipping {
    background: var(--tc-surface);
}

.elementor-kit-11 .tc-home-product-grid,
.elementor-kit-11 .tc-home-schedule-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.elementor-kit-11 .tc-home-product-card {
    display: flex;
    min-width: 0;
    height: 100%;
}

.elementor-kit-11 .tc-home-product-image {
    display: block;
    overflow: hidden;
    padding: 0;
}

.elementor-kit-11 .tc-home-product-image img {
    display: block;
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
}

.elementor-kit-11 .tc-home-product-card .tc-product-card__content {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
}

.elementor-kit-11 .tc-home-product-card .tc-product-card__title,
.elementor-kit-11 .tc-home-product-card .tc-product-card__description,
.elementor-kit-11 .tc-home-product-card .tc-product-card__price {
    margin: 0;
}

.elementor-kit-11 .tc-home-product-card .tc-product-card__title .elementor-heading-title {
    color: var(--tc-primary-dark);
}

.elementor-kit-11 .tc-home-product-card .tc-product-card__footer {
    margin-top: auto;
}

.elementor-kit-11 .tc-home-centered-button {
    align-self: center;
    margin-top: 32px;
}

.elementor-kit-11 .tc-home-trust {
    background: var(--tc-background);
}

.elementor-kit-11 .tc-home-order-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
}

.elementor-kit-11 .tc-home-step,
.elementor-kit-11 .tc-home-info-card {
    min-width: 0;
    height: 100%;
    gap: 12px;
}

.elementor-kit-11 .tc-home-step__number {
    display: inline-grid;
    width: 44px;
    height: 44px;
    place-items: center;
    color: #FFFFFF;
    background: var(--tc-primary);
    border-radius: 999px;
    font: 700 15px/1 Poppins, system-ui, sans-serif;
}

.elementor-kit-11 .tc-home-cta {
    background: var(--tc-background);
}

.elementor-kit-11 .tc-home-cta--compact .tc-whatsapp-pattern {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
}

.elementor-kit-11 .tc-home-cta--compact .tc-whatsapp-pattern > .elementor-widget-text-editor {
    grid-column: 1;
}

.elementor-kit-11 .tc-home-cta--compact .tc-whatsapp-pattern > .elementor-widget-button {
    grid-column: 2;
    grid-row: 1 / span 2;
}

.elementor-kit-11 .tc-home-schedule-grid .tc-home-info-card:nth-child(2) {
    border-top: 4px solid var(--tc-accent);
}

.elementor-kit-11 .tc-home-schedule-grid .tc-home-info-card:nth-child(3) {
    border-top: 4px solid var(--tc-secondary);
}

.elementor-kit-11 .tc-home-shipping-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.elementor-kit-11 .tc-home-shipping-note {
    margin: 24px 0 0;
    padding: 16px 20px;
    color: var(--tc-primary-dark);
    background: rgba(245, 158, 11, 0.12);
    border-left: 4px solid var(--tc-accent);
    border-radius: var(--tc-radius-control);
}

.elementor-kit-11 .tc-home-tracking {
    background: var(--tc-background);
}

.elementor-kit-11 .tc-home-tracking__card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 32px;
    align-items: center;
}

.elementor-kit-11 .tc-home-tracking__copy {
    gap: 12px;
}

.elementor-kit-11 .tc-home-cta--final {
    background: var(--tc-surface);
}

.elementor-kit-11 .tc-support-page {
    min-height: 60vh;
    background: var(--tc-background);
}

.elementor-kit-11 .tc-support-page__card {
    max-width: 820px;
    gap: 20px;
}

@media (max-width: 1024px) {
    .elementor-kit-11 .tc-homepage .tc-shell-container,
    .elementor-kit-11 .tc-support-page .tc-shell-container {
        padding-inline: 20px;
    }

    .elementor-kit-11 .tc-home-hero__inner {
        grid-template-columns: minmax(0, 1fr) minmax(300px, 0.82fr);
        gap: 36px;
    }

    .elementor-kit-11 .tc-home-order-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767px) {
    .elementor-kit-11 .tc-homepage .tc-shell-container,
    .elementor-kit-11 .tc-support-page .tc-shell-container {
        padding-inline: 16px;
    }

    .elementor-kit-11 .tc-home-section-heading {
        margin-bottom: 28px;
    }

    .elementor-kit-11 .tc-home-hero__inner,
    .elementor-kit-11 .tc-home-product-grid,
    .elementor-kit-11 .tc-home-order-grid,
    .elementor-kit-11 .tc-home-schedule-grid,
    .elementor-kit-11 .tc-home-shipping-grid,
    .elementor-kit-11 .tc-home-tracking__card {
        grid-template-columns: 1fr;
    }

    .elementor-kit-11 .tc-home-hero__inner {
        gap: 32px;
    }

    .elementor-kit-11 .tc-home-hero__copy {
        order: 1;
    }

    .elementor-kit-11 .tc-home-hero__visual {
        order: 2;
    }

    .elementor-kit-11 .tc-home-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .elementor-kit-11 .tc-home-actions .elementor-button,
    .elementor-kit-11 .tc-home-centered-button .elementor-button,
    .elementor-kit-11 .tc-home-tracking .elementor-button {
        width: 100%;
    }

    .elementor-kit-11 .tc-home-cta--compact .tc-whatsapp-pattern {
        grid-template-columns: 1fr;
    }

    .elementor-kit-11 .tc-home-cta--compact .tc-whatsapp-pattern > .elementor-widget-text-editor,
    .elementor-kit-11 .tc-home-cta--compact .tc-whatsapp-pattern > .elementor-widget-button {
        grid-column: 1;
        grid-row: auto;
    }
}
CSS;
}

function tc_home_replace_css_block(string $existing, string $css): string
{
    $block = TC_HOME_CSS_START . "\n" . trim($css) . "\n" . TC_HOME_CSS_END;
    $pattern = '/' . preg_quote(TC_HOME_CSS_START, '/') . '.*?' . preg_quote(TC_HOME_CSS_END, '/') . '/s';
    if (preg_match($pattern, $existing) === 1) {
        return (string) preg_replace($pattern, $block, $existing, 1);
    }
    return trim($existing) === '' ? $block : rtrim($existing) . "\n\n" . $block;
}

function tc_home_save_document(int $postId, string $title, array $elements): void
{
    $document = Elementor_Plugin::$instance->documents->get($postId, false);
    tc_home_assert($document !== false && $document !== null, "Could not load Elementor document {$postId}.");
    $saved = $document->save([
        'settings' => [
            'post_title' => $title,
            'post_status' => 'publish',
            'template' => 'elementor_header_footer',
            'hide_title' => 'yes',
        ],
        'elements' => $elements,
    ]);
    tc_home_assert((bool) $saved, "Elementor document {$title} failed to save.");
    $document->set_is_built_with_elementor(true);
    Elementor_Post_CSS::create($postId)->update();
}

function tc_home_resolve_seo_snippet(bool $allowCreate): int
{
    $storedId = (int) get_option(TC_HOME_SEO_OPTION, 0);
    $slugPost = get_page_by_path('tc-homepage-meta-description', OBJECT, 'elementor_snippet');
    if ($storedId > 0) {
        $post = get_post($storedId);
        tc_home_assert($post instanceof WP_Post && $post->post_type === 'elementor_snippet', 'Stored homepage SEO snippet is invalid.');
        tc_home_assert(get_post_meta($storedId, TC_HOME_MARKER, true) === 'seo-snippet', 'Stored homepage SEO snippet is missing ownership.');
        tc_home_assert(!$slugPost || (int) $slugPost->ID === $storedId, 'Homepage SEO snippet slug conflicts.');
        return $storedId;
    }
    if ($slugPost) {
        $slugId = (int) $slugPost->ID;
        tc_home_assert(get_post_meta($slugId, TC_HOME_MARKER, true) === 'seo-snippet', 'Homepage SEO snippet slug is user-owned; refusing to overwrite it.');
        update_option(TC_HOME_SEO_OPTION, $slugId);
        return $slugId;
    }
    tc_home_assert($allowCreate, 'Homepage SEO snippet has not been created.');
    $snippetId = wp_insert_post([
        'post_type' => 'elementor_snippet',
        'post_status' => 'publish',
        'post_title' => 'Titip Cirebon Homepage Meta Description',
        'post_name' => 'tc-homepage-meta-description',
        'post_content' => '',
        'comment_status' => 'closed',
    ], true);
    tc_home_assert(!is_wp_error($snippetId), 'Could not create homepage SEO snippet.');
    $snippetId = (int) $snippetId;
    update_post_meta($snippetId, TC_HOME_MARKER, 'seo-snippet');
    update_post_meta($snippetId, '_elementor_template_type', 'code_snippet');
    update_option(TC_HOME_SEO_OPTION, $snippetId);
    return $snippetId;
}

function tc_home_apply_seo_snippet(): int
{
    $snippetId = tc_home_resolve_seo_snippet(true);
    wp_update_post([
        'ID' => $snippetId,
        'post_status' => 'publish',
        'post_title' => 'Titip Cirebon Homepage Meta Description',
        'post_name' => 'tc-homepage-meta-description',
    ]);
    update_post_meta($snippetId, '_elementor_template_type', 'code_snippet');
    update_post_meta($snippetId, '_elementor_location', 'elementor_head');
    update_post_meta($snippetId, '_elementor_priority', 1);
    update_post_meta($snippetId, '_elementor_code', '<meta name="description" content="' . esc_attr(TC_HOME_META_DESCRIPTION) . '">');
    update_post_meta($snippetId, '_elementor_extra_options', []);
    $saved = Theme_Builder_Module::instance()->get_conditions_manager()->save_conditions($snippetId, [
        ['type' => 'include', 'name' => 'singular', 'sub' => 'front_page'],
    ]);
    tc_home_assert((bool) $saved || get_post_meta($snippetId, '_elementor_conditions', true) === ['include/singular/front_page'], 'Could not save front-page condition for SEO snippet.');
    return $snippetId;
}

function tc_home_apply(): void
{
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID']);
    tc_home_assert($admins !== [], 'No administrator account is available for Elementor saves.');
    wp_set_current_user((int) $admins[0]);

    $homeId = tc_home_resolve_home(true);
    $existingImages = tc_home_existing_image_ids($homeId);

    $support = [];
    foreach (tc_home_support_specs() as $key => [$title, $slug]) {
        $support[$key] = tc_home_resolve_support($key, $title, $slug, true);
    }
    $supportIds = array_map(static fn (array $item): int => (int) $item['id'], $support);

    $placeholderIds = [];
    foreach (tc_home_placeholder_specs() as $key => $spec) {
        $placeholderIds[$key] = tc_home_resolve_placeholder($key, $spec, true);
    }
    $managedMediaIds = array_values($placeholderIds);
    $heroImageId = tc_home_selected_image('hero-image', $placeholderIds['hero'], $managedMediaIds, $existingImages);
    $productImageIds = [];
    for ($index = 1; $index <= 3; $index++) {
        $productImageIds[] = tc_home_selected_image('product-' . $index . '-image', $placeholderIds['product'], $managedMediaIds, $existingImages);
    }

    $componentIds = get_option('titip_cirebon_site_components_ids', []);
    tc_home_assert(is_array($componentIds), 'Sprint 1C component registry is unavailable.');
    tc_home_save_document($homeId, 'Home', tc_home_elements($heroImageId, $productImageIds, $componentIds, $supportIds));
    update_post_meta($homeId, TC_HOME_MARKER, 'home');

    foreach ($support as $key => $resolved) {
        if (!$resolved['managed']) {
            continue;
        }
        $title = tc_home_support_specs()[$key][0];
        tc_home_save_document((int) $resolved['id'], $title, tc_home_support_elements($key));
        update_post_meta((int) $resolved['id'], TC_HOME_MARKER, $key);
    }

    $customCss = tc_home_replace_css_block(wp_get_custom_css('hello-elementor'), tc_home_css());
    $cssPost = wp_update_custom_css_post($customCss, ['stylesheet' => 'hello-elementor']);
    tc_home_assert(!is_wp_error($cssPost), 'Could not update controlled Homepage CSS.');

    update_option('blogname', 'Titip Cirebon');
    update_option('blogdescription', 'Jajanan dan Produk Pilihan dari Cirebon');
    update_option('show_on_front', 'page');
    update_option('page_on_front', $homeId);
    $seoId = tc_home_apply_seo_snippet();

    Elementor_Plugin::$instance->files_manager->clear_cache();
    $regenerationIds = array_merge([TC_HOME_KIT_ID, $homeId], $supportIds, array_map('intval', $componentIds));
    foreach (array_unique($regenerationIds) as $id) {
        if ($id > 0 && in_array(get_post_type($id), ['page', 'elementor_library'], true)) {
            Elementor_Post_CSS::create($id)->update();
        }
    }

    echo "Homepage applied.\n";
    echo "Home page ID: {$homeId}\n";
    echo 'Home URL: ' . home_url('/') . "\n";
    echo 'Support page IDs: ' . wp_json_encode($supportIds) . "\n";
    echo 'Placeholder attachment IDs: ' . wp_json_encode($placeholderIds) . "\n";
    echo "SEO snippet ID: {$seoId}\n";
}

function tc_home_walk_elements(array $elements, callable $visitor): void
{
    foreach ($elements as $element) {
        $visitor($element);
        if (isset($element['elements']) && is_array($element['elements'])) {
            tc_home_walk_elements($element['elements'], $visitor);
        }
    }
}

function tc_home_decoded_elements(int $postId): array
{
    $raw = get_post_meta($postId, '_elementor_data', true);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    tc_home_assert(is_array($decoded), "Elementor data for post {$postId} is invalid.");
    return $decoded;
}

function tc_home_verify(): void
{
    $homeId = tc_home_resolve_home(false);
    $home = get_post($homeId);
    tc_home_assert($home instanceof WP_Post && $home->post_status === 'publish', 'Home must be published.');
    tc_home_assert($home->post_title === 'Home' && $home->post_name === 'home', 'Home page identity is incorrect.');
    tc_home_assert(get_post_meta($homeId, TC_HOME_MARKER, true) === 'home', 'Home ownership marker is missing.');
    tc_home_assert(get_page_template_slug($homeId) === 'elementor_header_footer', 'Home must use Elementor Full Width.');
    tc_home_assert(get_post_meta($homeId, '_elementor_edit_mode', true) === 'builder', 'Home is not marked as Elementor content.');
    tc_home_assert(get_option('show_on_front') === 'page' && (int) get_option('page_on_front') === $homeId, 'Owned Home is not the static front page.');
    tc_home_assert(get_option('blogname') === 'Titip Cirebon', 'Site name changed unexpectedly.');
    tc_home_assert(get_option('blogdescription') === 'Jajanan dan Produk Pilihan dari Cirebon', 'Homepage SEO tagline is incorrect.');

    $homeCount = (new WP_Query([
        'post_type' => 'page',
        'post_status' => 'any',
        'meta_key' => TC_HOME_MARKER,
        'meta_value' => 'home',
        'fields' => 'ids',
        'posts_per_page' => -1,
    ]))->found_posts;
    tc_home_assert((int) $homeCount === 1, 'Owned Home page is duplicated.');

    $supportIds = [];
    foreach (tc_home_support_specs() as $key => [$title, $slug]) {
        $resolved = tc_home_resolve_support($key, $title, $slug, false);
        $id = (int) $resolved['id'];
        $post = get_post($id);
        tc_home_assert($post instanceof WP_Post && $post->post_status === 'publish' && $post->post_name === $slug, "Support page {$slug} is not published correctly.");
        if ($resolved['managed']) {
            tc_home_assert(get_page_template_slug($id) === 'elementor_header_footer', "Support page {$slug} must use Elementor Full Width.");
            tc_home_assert(get_post_meta($id, '_elementor_edit_mode', true) === 'builder', "Support page {$slug} is not Elementor content.");
            $ownedCount = (new WP_Query([
                'post_type' => 'page',
                'post_status' => 'any',
                'meta_key' => TC_HOME_MARKER,
                'meta_value' => $key,
                'fields' => 'ids',
                'posts_per_page' => -1,
            ]))->found_posts;
            tc_home_assert((int) $ownedCount === 1, "Owned support page {$slug} is duplicated.");
        }
        $supportIds[$key] = $id;
    }

    $trackingData = wp_json_encode(tc_home_decoded_elements($supportIds['lacak-pesanan']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    tc_home_assert(str_contains($trackingData, 'Fitur pelacakan pesanan sedang kami siapkan.'), 'Tracking placeholder copy is missing.');
    tc_home_assert(str_contains($trackingData, 'Kode Pesanan'), 'Tracking explanation is incomplete.');
    tc_home_assert(!preg_match('/(<form|<input|"widgetType":"form"|ajax|rest_route)/i', $trackingData), 'Tracking page must not contain a form or backend behavior.');

    $placeholderIds = [];
    foreach (tc_home_placeholder_specs() as $key => $spec) {
        $id = tc_home_resolve_placeholder($key, $spec, false);
        tc_home_assert(get_post_mime_type($id) === 'image/png', "Managed {$key} placeholder must be PNG.");
        tc_home_assert(get_post_meta($id, '_wp_attachment_image_alt', true) === $spec['alt'], "Managed {$key} placeholder alt text is incorrect.");
        $placeholderIds[$key] = $id;
    }
    $managedMediaCount = (new WP_Query([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'meta_key' => TC_HOME_MARKER,
        'meta_compare' => 'LIKE',
        'meta_value' => 'media-',
        'fields' => 'ids',
        'posts_per_page' => -1,
    ]))->found_posts;
    tc_home_assert((int) $managedMediaCount === 2, 'Homepage must own exactly two managed placeholder attachments.');

    $elements = tc_home_decoded_elements($homeId);
    $normalized = wp_json_encode($elements, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    tc_home_assert(is_string($normalized), 'Could not normalize Homepage Elementor data.');
    foreach ([
        'Pengen jajanan Cirebon?',
        'Kami titipkan produk dan jajanan pilihan langsung dari Cirebon untuk kamu di Sumedang.',
        'Open PO Senin–Kamis',
        'PO Minggu Ini',
        'Bandung Cheesecuit',
        'Produk Pilihan Cirebon',
        'Jajanan Cirebon Pilihan',
        'Kenapa Titip Cirebon?',
        'Cara Order',
        'Jadwal PO',
        'Area Pengiriman',
        'Cimalaka dan Sumedang Kota',
        'Bisa diantar sampai rumah atau COD.',
        'Sudah pesan?',
        'Nanti kamu bisa mengecek perkembangan pesanan menggunakan Kode Pesanan.',
    ] as $copy) {
        tc_home_assert(str_contains($normalized, $copy), "Homepage copy is missing: {$copy}");
    }
    foreach (['slider', 'video', 'lottie', 'testimonial', '<script', '@keyframes', 'parallax'] as $forbidden) {
        tc_home_assert(!str_contains(strtolower($normalized), strtolower($forbidden)), "Forbidden Homepage behavior/content found: {$forbidden}");
    }

    $imageWidgets = [];
    $headings = ['h1' => 0, 'h2' => 0, 'h3' => 0];
    $templateRefs = [];
    tc_home_walk_elements($elements, static function (array $element) use (&$imageWidgets, &$headings, &$templateRefs): void {
        $type = (string) ($element['widgetType'] ?? '');
        if ($type === 'image') {
            $imageWidgets[(string) ($element['id'] ?? '')] = (int) ($element['settings']['image']['id'] ?? 0);
        }
        if ($type === 'heading') {
            $tag = (string) ($element['settings']['header_size'] ?? '');
            if (isset($headings[$tag])) {
                $headings[$tag]++;
            }
        }
        if ($type === 'template') {
            $templateRefs[] = (int) ($element['settings']['template_id'] ?? 0);
        }
    });
    $expectedImageWidgetIds = [
        tc_home_element_id('hero-image'),
        tc_home_element_id('product-1-image'),
        tc_home_element_id('product-2-image'),
        tc_home_element_id('product-3-image'),
    ];
    tc_home_assert(count($imageWidgets) === 4 && array_diff($expectedImageWidgetIds, array_keys($imageWidgets)) === [], 'Homepage must contain four stable, replaceable Image widgets.');
    foreach ($imageWidgets as $widgetId => $attachmentId) {
        tc_home_assert($attachmentId > 0 && str_starts_with((string) get_post_mime_type($attachmentId), 'image/'), "Image widget {$widgetId} does not reference a valid image.");
    }
    tc_home_assert($headings['h1'] === 1, 'Homepage must contain exactly one H1.');
    tc_home_assert($headings['h2'] >= 6 && $headings['h3'] >= 9, 'Homepage heading hierarchy is incomplete.');
    tc_home_assert(substr_count($normalized, 'tc-home-product-card') === 3, 'Homepage must contain exactly three Product Card instances.');

    $componentIds = get_option('titip_cirebon_site_components_ids', []);
    tc_home_assert(is_array($componentIds), 'Sprint 1C component registry is missing during verification.');
    tc_home_assert(count(array_keys($templateRefs, (int) $componentIds['trust-cards'], true)) === 1, 'Trust Cards template must be reused once.');
    tc_home_assert(count(array_keys($templateRefs, (int) $componentIds['whatsapp-cta'], true)) === 2, 'WhatsApp CTA template must be reused twice.');

    $css = wp_get_custom_css('hello-elementor');
    tc_home_assert(substr_count($css, TC_HOME_CSS_START) === 1 && substr_count($css, TC_HOME_CSS_END) === 1, 'Homepage CSS block is missing or duplicated.');
    tc_home_assert(!str_contains($css, '!important'), 'Homepage CSS must not use !important.');
    foreach (['.tc-home-hero__inner', '.tc-home-product-grid', '.tc-home-order-grid', '.tc-home-schedule-grid', '.tc-home-shipping-grid', '.tc-home-tracking__card'] as $selector) {
        tc_home_assert(str_contains($css, $selector), "Required Homepage selector {$selector} is missing.");
    }
    tc_home_assert(!preg_match('/(<script|@keyframes|parallax|lottie|video)/i', $css), 'Homepage CSS must not include scripts or heavy animation.');

    $seoId = tc_home_resolve_seo_snippet(false);
    $seo = get_post($seoId);
    tc_home_assert($seo instanceof WP_Post && $seo->post_status === 'publish', 'Homepage SEO snippet is not published.');
    tc_home_assert(get_post_meta($seoId, '_elementor_template_type', true) === 'code_snippet', 'Homepage SEO snippet document type is incorrect.');
    tc_home_assert(get_post_meta($seoId, '_elementor_location', true) === 'elementor_head', 'Homepage SEO snippet must render in head.');
    tc_home_assert((int) get_post_meta($seoId, '_elementor_priority', true) === 1, 'Homepage SEO snippet priority is incorrect.');
    tc_home_assert(get_post_meta($seoId, '_elementor_conditions', true) === ['include/singular/front_page'], 'Homepage SEO condition is incorrect.');
    tc_home_assert(get_post_meta($seoId, '_elementor_code', true) === '<meta name="description" content="' . TC_HOME_META_DESCRIPTION . '">', 'Homepage meta description is incorrect.');
    $seoCount = (new WP_Query([
        'post_type' => 'elementor_snippet',
        'post_status' => 'any',
        'meta_key' => TC_HOME_MARKER,
        'meta_value' => 'seo-snippet',
        'fields' => 'ids',
        'posts_per_page' => -1,
    ]))->found_posts;
    tc_home_assert((int) $seoCount === 1, 'Homepage SEO snippet is duplicated.');

    $generatedHomeCss = Elementor_Post_CSS::create($homeId)->get_path();
    tc_home_assert(is_file($generatedHomeCss) && filesize($generatedHomeCss) > 0, 'Generated Homepage Elementor CSS is missing.');
    foreach ($supportIds as $key => $id) {
        if (get_post_meta($id, TC_HOME_MARKER, true) === $key) {
            $path = Elementor_Post_CSS::create($id)->get_path();
            tc_home_assert(is_file($path) && filesize($path) > 0, "Generated Elementor CSS for support page {$key} is missing.");
        }
    }

    echo "Homepage verification passed.\n";
    echo "Home page ID: {$homeId}\n";
    echo 'Home URL: ' . home_url('/') . "\n";
    echo 'Support page IDs: ' . wp_json_encode($supportIds) . "\n";
    echo 'Placeholder attachment IDs: ' . wp_json_encode($placeholderIds) . "\n";
    echo "Image widgets: 4\n";
    echo "Product cards: 3\n";
    echo "WhatsApp conversion points: 2\n";
    echo "SEO snippet ID: {$seoId}\n";
}

try {
    tc_home_preflight();
    $mode = getenv('HOMEPAGE_MODE') ?: '';
    if ($mode === 'apply') {
        tc_home_apply();
        tc_home_verify();
    } elseif ($mode === 'verify') {
        tc_home_verify();
    } else {
        tc_home_fail('HOMEPAGE_MODE must be apply or verify.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Homepage error: ' . $error->getMessage() . "\n");
    exit(1);
}
