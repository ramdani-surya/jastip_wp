<?php

declare(strict_types=1);

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

use Elementor\Core\Files\CSS\Post as Elementor_Post_CSS;
use Elementor\Plugin as Elementor_Plugin;
use ElementorPro\Modules\ThemeBuilder\Module as Theme_Builder_Module;

const TC_SITE_KIT_ID = 11;
const TC_SITE_OPTION = 'titip_cirebon_site_components_ids';
const TC_SITE_MARKER = '_titip_cirebon_site_component';
const TC_SITE_MENU_OPTION = 'titip_cirebon_primary_menu_id';
const TC_SITE_MENU_MARKER = '_titip_cirebon_site_menu';
const TC_SITE_PREVIEW_KEY = 'preview';
const TC_SITE_PREVIEW_SLUG = 'site-components-preview';
const TC_SITE_CSS_START = '/* TITIP CIREBON SITE COMPONENTS: START */';
const TC_SITE_CSS_END = '/* TITIP CIREBON SITE COMPONENTS: END */';

function tc_site_fail(string $message): never
{
    throw new RuntimeException($message);
}

function tc_site_assert(bool $condition, string $message): void
{
    if (!$condition) {
        tc_site_fail($message);
    }
}

function tc_site_size(float|int $size, string $unit = 'px'): array
{
    return ['unit' => $unit, 'size' => $size, 'sizes' => []];
}

function tc_site_dimensions(int $top, int $right, int $bottom, int $left): array
{
    return [
        'unit' => 'px',
        'top' => (string) $top,
        'right' => (string) $right,
        'bottom' => (string) $bottom,
        'left' => (string) $left,
        'isLinked' => $top === $right && $right === $bottom && $bottom === $left,
    ];
}

function tc_site_element_id(string $name): string
{
    return substr(md5('titip-cirebon-site-components:' . $name), 0, 7);
}

function tc_site_global_color(string $id): string
{
    return 'globals/colors?id=' . $id;
}

function tc_site_global_typography(string $id): string
{
    return 'globals/typography?id=' . $id;
}

function tc_site_widget(string $name, string $type, array $settings): array
{
    return [
        'id' => tc_site_element_id($name),
        'elType' => 'widget',
        'settings' => $settings,
        'elements' => [],
        'widgetType' => $type,
    ];
}

function tc_site_container(string $name, string $classes, array $elements, array $settings = []): array
{
    return [
        'id' => tc_site_element_id($name),
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

function tc_site_heading(string $name, string $title, string $tag, string $typography, string $classes = ''): array
{
    return tc_site_widget($name, 'heading', [
        'title' => $title,
        'header_size' => $tag,
        '_css_classes' => $classes,
        '__globals__' => [
            'title_color' => tc_site_global_color('tc-primary-dark'),
            'typography_typography' => tc_site_global_typography($typography),
        ],
    ]);
}

function tc_site_button(string $name, string $label, string $url, string $classes = ''): array
{
    return tc_site_widget($name, 'button', [
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

function tc_site_preflight(): void
{
    global $wpdb, $wp_version;

    tc_site_assert($wp_version === '7.0.3', "WordPress {$wp_version} found; expected 7.0.3.");
    tc_site_assert(PHP_VERSION === '8.4.24', 'PHP 8.4.24 is required.');
    tc_site_assert($wpdb->db_version() === '8.0.46', 'MySQL 8.0.46 is required.');
    tc_site_assert(is_plugin_active('elementor/elementor.php'), 'Elementor Free is not active.');
    tc_site_assert(is_plugin_active('elementor-pro/elementor-pro.php'), 'Elementor Pro is not active.');
    tc_site_assert(defined('ELEMENTOR_VERSION') && ELEMENTOR_VERSION === '4.2.2', 'Elementor Free 4.2.2 is required.');
    tc_site_assert(defined('ELEMENTOR_PRO_VERSION') && ELEMENTOR_PRO_VERSION === '4.0.4', 'Elementor Pro 4.0.4 is required.');

    $plugins = (array) get_option('active_plugins', []);
    sort($plugins);
    tc_site_assert($plugins === ['elementor-pro/elementor-pro.php', 'elementor/elementor.php'], 'Only Elementor Free and Elementor Pro may be active.');

    $theme = wp_get_theme();
    tc_site_assert($theme->get_stylesheet() === 'hello-elementor', 'Hello Elementor must remain active.');
    tc_site_assert($theme->get('Version') === '3.4.9', 'Hello Elementor 3.4.9 is required.');
    tc_site_assert((int) get_option('elementor_active_kit') === TC_SITE_KIT_ID, 'Active Elementor Kit must remain ID 11.');

    $kit = get_post_meta(TC_SITE_KIT_ID, '_elementor_page_settings', true);
    tc_site_assert(is_array($kit), 'Sprint 1B Elementor Kit settings are missing.');
    $colors = [];
    foreach (array_merge($kit['system_colors'] ?? [], $kit['custom_colors'] ?? []) as $color) {
        $colors[$color['_id'] ?? ''] = $color['color'] ?? '';
    }
    foreach ([
        'primary' => '#B45309',
        'secondary' => '#0F766E',
        'text' => '#292524',
        'accent' => '#F59E0B',
        'tc-primary-dark' => '#78350F',
        'tc-background' => '#FFFDF8',
        'tc-surface' => '#FFFFFF',
        'tc-border' => '#E7E5E4',
    ] as $id => $value) {
        tc_site_assert(($colors[$id] ?? '') === $value, "Sprint 1B color {$id} changed unexpectedly.");
    }
    tc_site_assert(($kit['container_width']['size'] ?? null) === 1200, 'Sprint 1B container width changed unexpectedly.');
    tc_site_assert(get_option('show_on_front') === 'posts' && (int) get_option('page_on_front') === 0, 'Homepage state must remain posts/0.');

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
        tc_site_assert(is_string($source) && str_contains($source, $signature), "Required PHP 8.4 hotfix is missing: {$file}.");
    }
}

function tc_site_ids(): array
{
    $ids = get_option(TC_SITE_OPTION, []);
    return is_array($ids) ? array_map('intval', $ids) : [];
}

function tc_site_resolve_owned_post(
    string $key,
    string $postType,
    string $title,
    string $slug,
    string $documentType,
    bool $allowCreate
): int {
    $ids = tc_site_ids();
    $storedId = (int) ($ids[$key] ?? 0);
    $slugPost = get_page_by_path($slug, OBJECT, $postType);

    if ($storedId > 0) {
        $stored = get_post($storedId);
        tc_site_assert($stored instanceof WP_Post && $stored->post_type === $postType, "Stored {$key} ID is invalid.");
        tc_site_assert(get_post_meta($storedId, TC_SITE_MARKER, true) === $key, "Stored {$key} is missing its ownership marker.");
        tc_site_assert(!$slugPost || (int) $slugPost->ID === $storedId, "Slug {$slug} conflicts with another post.");
        tc_site_assert(get_post_meta($storedId, '_elementor_template_type', true) === $documentType, "Stored {$key} document type changed.");
        return $storedId;
    }

    if ($slugPost) {
        $slugId = (int) $slugPost->ID;
        tc_site_assert(get_post_meta($slugId, TC_SITE_MARKER, true) === $key, "Slug {$slug} is owned by user content; refusing to overwrite it.");
        tc_site_assert(get_post_meta($slugId, '_elementor_template_type', true) === $documentType, "Owned {$key} document type changed.");
        $ids[$key] = $slugId;
        update_option(TC_SITE_OPTION, $ids);
        return $slugId;
    }

    tc_site_assert($allowCreate, "Owned {$key} has not been created.");
    $postId = wp_insert_post([
        'post_type' => $postType,
        'post_status' => 'draft',
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => '',
        'comment_status' => 'closed',
    ], true);
    tc_site_assert(!is_wp_error($postId), "Could not create {$title}.");

    $postId = (int) $postId;
    update_post_meta($postId, TC_SITE_MARKER, $key);
    update_post_meta($postId, '_elementor_template_type', $documentType);
    $ids[$key] = $postId;
    update_option(TC_SITE_OPTION, $ids);
    return $postId;
}

function tc_site_resolve_menu(bool $allowCreate): WP_Term
{
    $storedId = (int) get_option(TC_SITE_MENU_OPTION, 0);
    $namedMenu = wp_get_nav_menu_object('Titip Cirebon Primary Navigation');

    if ($storedId > 0) {
        $menu = wp_get_nav_menu_object($storedId);
        tc_site_assert($menu instanceof WP_Term, 'Stored primary navigation menu is invalid.');
        tc_site_assert(get_term_meta($storedId, TC_SITE_MENU_MARKER, true) === 'primary', 'Stored primary menu is missing its ownership marker.');
        tc_site_assert(!$namedMenu || (int) $namedMenu->term_id === $storedId, 'Primary navigation menu name conflicts with another menu.');
        return $menu;
    }

    if ($namedMenu instanceof WP_Term) {
        tc_site_assert(get_term_meta($namedMenu->term_id, TC_SITE_MENU_MARKER, true) === 'primary', 'A user menu already uses the primary navigation name; refusing to overwrite it.');
        update_option(TC_SITE_MENU_OPTION, (int) $namedMenu->term_id);
        return $namedMenu;
    }

    tc_site_assert($allowCreate, 'Primary navigation menu has not been created.');
    $menuId = wp_create_nav_menu('Titip Cirebon Primary Navigation');
    tc_site_assert(!is_wp_error($menuId), 'Could not create primary navigation menu.');
    $menuId = (int) $menuId;
    update_term_meta($menuId, TC_SITE_MENU_MARKER, 'primary');
    update_option(TC_SITE_MENU_OPTION, $menuId);
    $menu = wp_get_nav_menu_object($menuId);
    tc_site_assert($menu instanceof WP_Term, 'Created navigation menu could not be loaded.');
    return $menu;
}

function tc_site_apply_menu(): WP_Term
{
    $menu = tc_site_resolve_menu(true);
    $expected = [
        'products' => ['Produk', home_url('/#produk')],
        'order-guide' => ['Cara Order', home_url('/#cara-order')],
        'tracking' => ['Lacak Pesanan', home_url('/#lacak-pesanan')],
        'about' => ['Tentang Kami', home_url('/#tentang-kami')],
    ];

    $owned = [];
    foreach (wp_get_nav_menu_items($menu->term_id, ['post_status' => 'any']) ?: [] as $item) {
        $key = (string) get_post_meta($item->ID, TC_SITE_MARKER, true);
        if ($key !== '') {
            tc_site_assert(isset($expected[$key]), "Unexpected owned navigation item {$key} found.");
            $owned[$key] = (int) $item->ID;
        }
    }

    $position = 0;
    foreach ($expected as $key => [$label, $url]) {
        $position++;
        $itemId = wp_update_nav_menu_item((int) $menu->term_id, $owned[$key] ?? 0, [
            'menu-item-title' => $label,
            'menu-item-url' => $url,
            'menu-item-status' => 'publish',
            'menu-item-type' => 'custom',
            'menu-item-position' => $position,
        ]);
        tc_site_assert(!is_wp_error($itemId), "Could not save navigation item {$label}.");
        update_post_meta((int) $itemId, TC_SITE_MARKER, $key);
    }
    return $menu;
}

function tc_site_css(): string
{
    return <<<'CSS'
/* Site shell and reusable component patterns built on Sprint 1B tokens. */
.tc-site-header {
    position: relative;
    z-index: 20;
    width: 100%;
    min-height: 76px;
    background: var(--tc-surface, #FFFFFF);
    border-bottom: 1px solid var(--tc-border, #E7E5E4);
    box-shadow: 0 2px 12px rgba(41, 37, 36, 0.04);
}

.tc-shell-container {
    width: min(100%, 1200px);
    margin-inline: auto;
    padding-inline: 24px;
}

.tc-header-inner {
    min-height: 76px;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.tc-brand {
    flex: 0 0 auto;
    margin: 0;
}

.tc-brand .elementor-heading-title,
.tc-brand .elementor-heading-title a {
    color: var(--tc-primary-dark, #78350F);
    font-family: Poppins, system-ui, sans-serif;
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
    text-decoration: none;
    white-space: nowrap;
}

.tc-primary-nav {
    flex: 1 1 auto;
    min-width: 0;
}

.tc-primary-nav .elementor-nav-menu--main {
    justify-content: center;
}

.tc-primary-nav .elementor-nav-menu--main .elementor-item {
    min-height: 44px;
    padding: 10px 14px;
    color: var(--tc-text, #292524);
    font-family: Inter, system-ui, sans-serif;
    font-size: 15px;
    font-weight: 600;
}

.tc-primary-nav .elementor-nav-menu--main .elementor-item:hover,
.tc-primary-nav .elementor-nav-menu--main .elementor-item:focus-visible {
    color: var(--tc-primary, #B45309);
}

.tc-primary-nav .elementor-menu-toggle {
    width: 44px;
    height: 44px;
    margin-left: auto;
    padding: 10px;
    color: var(--tc-primary-dark, #78350F);
    background: var(--tc-background, #FFFDF8);
    border: 1px solid var(--tc-border, #E7E5E4);
    border-radius: 10px;
}

.tc-primary-nav .elementor-nav-menu--dropdown {
    background: var(--tc-surface, #FFFFFF);
    border: 1px solid var(--tc-border, #E7E5E4);
    border-radius: 12px;
    box-shadow: var(--tc-shadow-default, 0 4px 20px rgba(0, 0, 0, 0.06));
}

.tc-primary-nav .elementor-nav-menu--dropdown .elementor-item {
    min-height: 48px;
    padding: 12px 16px;
    color: var(--tc-text, #292524);
    font-size: 16px;
}

.tc-header-cta {
    flex: 0 0 auto;
}

.tc-button--utility .elementor-button {
    color: var(--tc-primary-dark, #78350F);
    background: transparent;
    border: 1px solid var(--tc-border, #E7E5E4);
}

.tc-button--utility .elementor-button:hover,
.tc-button--utility .elementor-button:focus-visible {
    color: var(--tc-primary-dark, #78350F);
    background: #FFF7ED;
    border-color: var(--tc-primary, #B45309);
}

.tc-whatsapp-pattern {
    padding: 24px;
    background: #ECFDF5;
    border: 1px solid #A7F3D0;
    border-radius: 16px;
}

.tc-whatsapp-pattern .elementor-button {
    background: var(--tc-success, #15803D);
}

.tc-whatsapp-pattern .elementor-button:hover,
.tc-whatsapp-pattern .elementor-button:focus-visible {
    background: #166534;
}

.tc-component-template {
    width: 100%;
}

.tc-component-actions {
    display: flex;
    flex-flow: row wrap;
    gap: 16px;
    align-items: center;
}

.tc-section-heading-pattern {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px 32px;
    align-items: end;
}

.tc-section-heading-pattern__copy {
    display: grid;
    gap: 10px;
}

.tc-eyebrow {
    margin: 0;
    color: var(--tc-primary, #B45309);
    font: 600 13px/1.3 Inter, system-ui, sans-serif;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.tc-section-heading-pattern h2,
.tc-section-heading-pattern p {
    margin: 0;
}

.tc-section-heading-pattern p:not(.tc-eyebrow) {
    max-width: 64ch;
    color: var(--tc-text-muted, #78716C);
}

.tc-trust-grid,
.tc-content-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
}

.tc-trust-card {
    min-height: 100%;
    padding: 24px;
    background: var(--tc-surface, #FFFFFF);
    border: 1px solid var(--tc-border, #E7E5E4);
    border-radius: 16px;
}

.tc-trust-card__icon {
    width: 44px;
    height: 44px;
    display: inline-grid;
    place-items: center;
    margin-bottom: 18px;
    color: var(--tc-secondary, #0F766E);
    background: #F0FDFA;
    border-radius: 12px;
    font-size: 22px;
    font-weight: 700;
}

.tc-trust-card h3 {
    margin: 0 0 8px;
    color: var(--tc-primary-dark, #78350F);
    font-size: 18px;
}

.tc-trust-card p {
    margin: 0;
    color: var(--tc-text-muted, #78716C);
    font-size: 15px;
}

.tc-content-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.tc-content-pattern {
    padding: 28px;
    background: var(--tc-surface, #FFFFFF);
    border: 1px solid var(--tc-border, #E7E5E4);
    border-radius: 16px;
}

.tc-content-pattern h3 {
    margin: 0 0 10px;
    color: var(--tc-primary-dark, #78350F);
}

.tc-content-pattern p {
    margin: 0;
    color: var(--tc-text-muted, #78716C);
}

.tc-site-components-preview {
    width: 100%;
    background: var(--tc-background, #FFFDF8);
}

.tc-preview-intro {
    padding-block: 64px 40px;
}

.tc-preview-section {
    padding-block: 48px;
}

.tc-preview-section + .tc-preview-section {
    border-top: 1px solid var(--tc-border, #E7E5E4);
}

.tc-preview-template-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    align-items: stretch;
}

.tc-preview-product-card .elementor-template {
    width: 100%;
    max-width: 400px;
}

.tc-site-footer {
    width: 100%;
    color: #F5F5F4;
    background: #292524;
}

.tc-footer-inner {
    padding-block: 56px 24px;
}

.tc-footer-grid {
    display: grid;
    grid-template-columns: minmax(240px, 1.5fr) repeat(3, minmax(140px, 1fr));
    gap: 40px;
}

.tc-site-footer .tc-footer-brand,
.tc-site-footer .tc-footer-heading {
    margin: 0 0 14px;
    color: #FFFFFF;
    font-family: Poppins, system-ui, sans-serif;
    font-weight: 600;
}

.tc-site-footer .tc-footer-brand {
    font-size: 22px;
    font-weight: 700;
}

.tc-footer-description,
.tc-footer-copy {
    margin: 0;
    color: #D6D3D1;
}

.tc-footer-nav ul {
    display: grid;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.tc-site-footer a:not(.elementor-button) {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    color: #F5F5F4;
    text-decoration: none;
}

.tc-site-footer a:hover,
.tc-site-footer a:focus-visible {
    color: #FBBF24;
}

.tc-footer-order {
    margin-top: 12px;
}

.tc-footer-bottom {
    margin-top: 40px;
    padding-top: 20px;
    color: #A8A29E;
    border-top: 1px solid #44403C;
    font-size: 14px;
}

@media (max-width: 1024px) {
    .tc-shell-container { padding-inline: 20px; }
    .tc-header-inner { gap: 14px; }
    .tc-primary-nav .elementor-nav-menu--main .elementor-item { padding-inline: 9px; font-size: 14px; }
    .tc-footer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .tc-trust-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 900px) and (min-width: 768px) {
    .tc-header-cta { display: none; }
}

@media (max-width: 767px) {
    .tc-shell-container { padding-inline: 16px; }
    .tc-site-header, .tc-header-inner { min-height: 68px; }
    .tc-header-inner { gap: 12px; }
    .tc-brand .elementor-heading-title, .tc-brand .elementor-heading-title a { font-size: 20px; }
    .tc-primary-nav { flex: 0 1 auto; }
    .tc-header-cta { display: none; }
    .tc-primary-nav .elementor-nav-menu__container.elementor-nav-menu--dropdown { position: fixed; top: 60px; left: 16px; right: 16px; width: calc(100vw - 32px); max-width: none; margin-top: 8px; }
    .tc-section-heading-pattern { grid-template-columns: 1fr; align-items: start; }
    .tc-trust-grid, .tc-content-grid, .tc-preview-template-grid { grid-template-columns: 1fr; }
    .tc-footer-grid { grid-template-columns: 1fr; gap: 32px; }
    .tc-footer-inner { padding-block: 48px 24px; }
    .tc-preview-intro { padding-block: 48px 32px; }
    .tc-preview-section { padding-block: 40px; }
    .tc-whatsapp-pattern { padding: 20px; }
    .tc-whatsapp-pattern .elementor-button { width: 100%; }
}
CSS;
}

function tc_site_replace_css_block(string $existing, string $css): string
{
    $block = TC_SITE_CSS_START . "\n" . trim($css) . "\n" . TC_SITE_CSS_END;
    $pattern = '/' . preg_quote(TC_SITE_CSS_START, '/') . '.*?' . preg_quote(TC_SITE_CSS_END, '/') . '/s';
    if (preg_match($pattern, $existing) === 1) {
        return (string) preg_replace($pattern, $block, $existing, 1);
    }
    return trim($existing) === '' ? $block : rtrim($existing) . "\n\n" . $block;
}

function tc_site_header_elements(string $menuSlug): array
{
    $brand = tc_site_widget('header-brand', 'heading', [
        'title' => 'Titip Cirebon',
        'header_size' => 'div',
        'link' => ['url' => home_url('/')],
        '_css_classes' => 'tc-brand',
        '__globals__' => [
            'title_color' => tc_site_global_color('tc-primary-dark'),
            'typography_typography' => tc_site_global_typography('primary'),
        ],
    ]);
    $navigation = tc_site_widget('header-navigation', 'nav-menu', [
        'menu_name' => 'Primary navigation',
        'menu' => $menuSlug,
        'layout' => 'horizontal',
        'align_items' => 'center',
        'pointer' => 'underline',
        'animation_line' => 'none',
        'dropdown' => 'mobile',
        'toggle' => 'burger',
        'toggle_align' => 'end',
        'toggle_size' => tc_site_size(24),
        '_css_classes' => 'tc-primary-nav',
    ]);
    $cta = tc_site_button('header-primary-cta', 'Pesan Sekarang', '#whatsapp-order', 'tc-header-cta');
    $inner = tc_site_container('header-inner', 'tc-shell-container tc-header-inner', [$brand, $navigation, $cta], [
        'flex_direction' => 'row',
        'align_items' => 'center',
        'justify_content' => 'space-between',
        'flex_wrap' => 'nowrap',
    ]);
    return [tc_site_container('header-shell', 'tc-site-header', [$inner])];
}

function tc_site_footer_elements(): array
{
    $home = esc_url(home_url('/'));
    $html = <<<HTML
<div class="tc-footer-grid">
  <section aria-labelledby="tc-footer-brand"><h2 id="tc-footer-brand" class="tc-footer-brand">Titip Cirebon</h2><p class="tc-footer-description">Titip produk dan jajanan pilihan langsung dari Cirebon untuk kamu di Sumedang.</p></section>
  <nav class="tc-footer-nav" aria-label="Footer navigation"><h2 class="tc-footer-heading">Navigasi</h2><ul><li><a href="{$home}#produk">Produk</a></li><li><a href="{$home}#cara-order">Cara Order</a></li><li><a href="{$home}#lacak-pesanan">Lacak Pesanan</a></li><li><a href="{$home}#tentang-kami">Tentang Kami</a></li></ul></nav>
  <section aria-labelledby="tc-footer-order"><h2 id="tc-footer-order" class="tc-footer-heading">Order</h2><p class="tc-footer-copy">Hubungi kami ketika daftar produk dan nomor WhatsApp resmi sudah tersedia.</p><a class="elementor-button tc-footer-order" href="#whatsapp-order">Pesan lewat WhatsApp</a></section>
  <section aria-labelledby="tc-footer-schedule"><h2 id="tc-footer-schedule" class="tc-footer-heading">Jadwal</h2><p class="tc-footer-copy">Open PO Senin–Kamis<br>Pengiriman setiap Sabtu</p></section>
</div>
<div class="tc-footer-bottom">&copy; Titip Cirebon. Informasi pada komponen preview masih berupa fondasi antarmuka.</div>
HTML;
    $content = tc_site_widget('footer-content', 'html', ['html' => $html]);
    $inner = tc_site_container('footer-inner', 'tc-shell-container tc-footer-inner', [$content]);
    return [tc_site_container('footer-shell', 'tc-site-footer', [$inner])];
}

function tc_site_component_specs(): array
{
    $primary = [tc_site_container('component-primary-cta', 'tc-component-template', [
        tc_site_button('component-primary-button', 'Pesan Sekarang', '#whatsapp-order'),
    ])];
    $secondary = [tc_site_container('component-secondary-cta', 'tc-component-template', [
        tc_site_button('component-secondary-button', 'Lihat Produk', '#produk', 'tc-button--secondary'),
    ])];
    $utility = [tc_site_container('component-utility-cta', 'tc-component-template', [
        tc_site_button('component-utility-button', 'Lacak Pesanan', '#lacak-pesanan', 'tc-button--utility'),
    ])];
    $whatsapp = [tc_site_container('component-whatsapp', 'tc-component-template tc-whatsapp-pattern', [
        tc_site_heading('component-whatsapp-heading', 'Butuh bantuan untuk memesan?', 'h3', 'tc-h3'),
        tc_site_widget('component-whatsapp-copy', 'text-editor', [
            'editor' => '<p>Gunakan CTA ini setelah nomor WhatsApp resmi ditambahkan. Tautan saat ini adalah placeholder internal yang aman.</p>',
            '__globals__' => ['text_color' => tc_site_global_color('text'), 'typography_typography' => tc_site_global_typography('tc-body')],
        ]),
        tc_site_button('component-whatsapp-button', 'Pesan via WhatsApp', '#whatsapp-order'),
    ], ['_element_id' => 'whatsapp-order'])];

    $productHtml = <<<'HTML'
<article class="tc-product-card">
  <div class="tc-product-card__media" role="img" aria-label="Placeholder area foto produk dengan rasio empat banding tiga">Area foto produk 4:3<br>Foto produk akan ditambahkan kemudian</div>
  <div class="tc-product-card__content">
    <div class="tc-badges"><span class="tc-badge tc-badge--primary">PO Minggu Ini</span></div>
    <h3 class="tc-product-card__title">Bandung Cheesecuit</h3>
    <p class="tc-product-card__description">Jajanan populer yang bisa dipesan untuk PO minggu ini.</p>
    <strong class="tc-product-card__price">Rp xx.xxx</strong>
    <div class="tc-product-card__footer"><span class="tc-preview-note">Harga masih placeholder</span><a class="elementor-button" href="#whatsapp-order">Pesan Produk</a></div>
  </div>
</article>
HTML;
    $product = [tc_site_container('component-product-card', 'tc-component-template', [tc_site_widget('component-product-html', 'html', ['html' => $productHtml])])];

    $badgesHtml = <<<'HTML'
<div class="tc-card" aria-label="Available product badge states"><h3>Product Badge States</h3><div class="tc-badges"><span class="tc-badge tc-badge--primary">PO Minggu Ini</span><span class="tc-badge tc-badge--secondary">Baru</span><span class="tc-badge tc-badge--accent">Terlaris</span><span class="tc-badge tc-badge--success">Viral</span><span class="tc-badge tc-badge--error">Limited</span></div><p class="tc-preview-note">Pilih satu atau dua badge yang relevan per product card.</p></div>
HTML;
    $badges = [tc_site_container('component-product-badges', 'tc-component-template', [tc_site_widget('component-badges-html', 'html', ['html' => $badgesHtml])])];

    $trustHtml = <<<'HTML'
<div class="tc-trust-grid">
  <article class="tc-trust-card"><span class="tc-trust-card__icon" aria-hidden="true">✓</span><h3>Tidak perlu antre</h3><p>Contoh benefit singkat yang menjelaskan kemudahan layanan.</p></article>
  <article class="tc-trust-card"><span class="tc-trust-card__icon" aria-hidden="true">＋</span><h3>Beberapa toko</h3><p>Pattern untuk menjelaskan cara menggabungkan beberapa titipan.</p></article>
  <article class="tc-trust-card"><span class="tc-trust-card__icon" aria-hidden="true">⌂</span><h3>Pengantaran terjadwal</h3><p>Informasi jadwal disampaikan jelas tanpa klaim berlebihan.</p></article>
  <article class="tc-trust-card"><span class="tc-trust-card__icon" aria-hidden="true">i</span><h3>Informasi transparan</h3><p>Gunakan kartu ini untuk detail yang membantu membangun kepercayaan.</p></article>
</div>
HTML;
    $trust = [tc_site_container('component-trust-cards', 'tc-component-template', [tc_site_widget('component-trust-html', 'html', ['html' => $trustHtml])])];

    $sectionHeading = [tc_site_container('component-section-heading', 'tc-component-template tc-section-heading-pattern', [
        tc_site_container('component-section-heading-copy', 'tc-section-heading-pattern__copy', [
            tc_site_widget('component-section-eyebrow', 'html', ['html' => '<p class="tc-eyebrow">PO Minggu Ini</p>']),
            tc_site_heading('component-section-title', 'Pilihan dari Cirebon', 'h2', 'tc-h2'),
            tc_site_widget('component-section-description', 'text-editor', [
                'editor' => '<p>Produk yang sedang kami tawarkan minggu ini. Copy ini hanya specimen komponen.</p>',
                '__globals__' => ['text_color' => tc_site_global_color('tc-text-muted'), 'typography_typography' => tc_site_global_typography('tc-body')],
            ]),
        ]),
        tc_site_button('component-section-cta', 'Lihat Produk', '#produk', 'tc-button--secondary'),
    ])];

    $contentHtml = <<<'HTML'
<div class="tc-content-grid">
  <article class="tc-content-pattern"><p class="tc-eyebrow">Informasi</p><h3>Cara memakai content pattern</h3><p>Gunakan blok ringan ini untuk informasi order, pengiriman, atau penjelasan layanan yang singkat.</p></article>
  <article class="tc-content-pattern"><p class="tc-eyebrow">Prinsip</p><h3>Satu topik per blok</h3><p>Judul ringkas dan deskripsi terfokus membantu pengguna Facebook membaca cepat di perangkat mobile.</p></article>
</div>
HTML;
    $content = [tc_site_container('component-content-pattern', 'tc-component-template', [tc_site_widget('component-content-html', 'html', ['html' => $contentHtml])])];

    return [
        'primary-cta' => ['Primary CTA', 'tc-primary-cta', $primary],
        'secondary-cta' => ['Secondary CTA', 'tc-secondary-cta', $secondary],
        'utility-cta' => ['Utility CTA', 'tc-utility-cta', $utility],
        'whatsapp-cta' => ['WhatsApp CTA', 'tc-whatsapp-cta', $whatsapp],
        'product-card' => ['Product Card', 'tc-product-card', $product],
        'product-badges' => ['Product Badges', 'tc-product-badges', $badges],
        'trust-cards' => ['Trust Cards', 'tc-trust-cards', $trust],
        'section-heading' => ['Section Heading', 'tc-section-heading', $sectionHeading],
        'content-pattern' => ['Basic Content Pattern', 'tc-basic-content-pattern', $content],
    ];
}

function tc_site_template_widget(string $name, int $templateId, string $classes = ''): array
{
    return tc_site_widget($name, 'template', ['template_id' => $templateId, '_css_classes' => $classes]);
}

function tc_site_preview_elements(array $componentIds): array
{
    $intro = tc_site_container('preview-intro', 'tc-shell-container tc-preview-intro', [
        tc_site_heading('preview-title', 'Site Components Preview', 'h1', 'tc-h1'),
        tc_site_widget('preview-intro-copy', 'text-editor', [
            'editor' => '<p>Reusable global components dan site shell Titip Cirebon. Halaman ini adalah specimen internal, bukan homepage.</p>',
            '__globals__' => ['text_color' => tc_site_global_color('text'), 'typography_typography' => tc_site_global_typography('tc-body')],
        ]),
    ]);

    $buttons = tc_site_container('preview-buttons', 'tc-shell-container tc-preview-section', [
        tc_site_heading('preview-buttons-title', 'CTA Patterns', 'h2', 'tc-h2'),
        tc_site_container('preview-buttons-grid', 'tc-preview-template-grid', [
            tc_site_template_widget('preview-primary-cta', $componentIds['primary-cta']),
            tc_site_template_widget('preview-secondary-cta', $componentIds['secondary-cta']),
            tc_site_template_widget('preview-utility-cta', $componentIds['utility-cta']),
            tc_site_template_widget('preview-whatsapp-cta', $componentIds['whatsapp-cta']),
        ]),
    ]);

    $sections = [
        ['preview-product', 'Product Card', 'product-card'],
        ['preview-badges', 'Product Badges', 'product-badges'],
        ['preview-trust', 'Trust / Info Cards', 'trust-cards'],
        ['preview-section-heading', 'Section Heading Pattern', 'section-heading'],
        ['preview-content-pattern', 'Basic Content Pattern', 'content-pattern'],
    ];
    $elements = [$intro, $buttons];
    foreach ($sections as [$name, $title, $key]) {
        $elements[] = tc_site_container($name, 'tc-shell-container tc-preview-section', [
            tc_site_heading($name . '-title', $title, 'h2', 'tc-h2'),
            tc_site_template_widget($name . '-template', $componentIds[$key], $key === 'product-card' ? 'tc-preview-product-card' : ''),
        ]);
    }
    return [tc_site_container('preview-root', 'tc-site-components-preview', $elements)];
}

function tc_site_save_document(int $postId, string $title, array $elements, array $settings = []): void
{
    $document = Elementor_Plugin::$instance->documents->get($postId, false);
    tc_site_assert($document !== false && $document !== null, "Could not load Elementor document {$postId}.");
    $saved = $document->save([
        'settings' => array_merge(['post_title' => $title, 'post_status' => 'publish'], $settings),
        'elements' => $elements,
    ]);
    tc_site_assert((bool) $saved, "Elementor document {$title} failed to save.");
    $document->set_is_built_with_elementor(true);
    Elementor_Post_CSS::create($postId)->update();
}

function tc_site_apply(): void
{
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID']);
    tc_site_assert($admins !== [], 'No administrator account is available for Elementor saves.');
    wp_set_current_user((int) $admins[0]);

    $menu = tc_site_apply_menu();

    $headerId = tc_site_resolve_owned_post('header', 'elementor_library', 'Titip Cirebon Global Header', 'titip-cirebon-global-header', 'header', true);
    $footerId = tc_site_resolve_owned_post('footer', 'elementor_library', 'Titip Cirebon Global Footer', 'titip-cirebon-global-footer', 'footer', true);
    tc_site_save_document($headerId, 'Titip Cirebon Global Header', tc_site_header_elements($menu->slug));
    tc_site_save_document($footerId, 'Titip Cirebon Global Footer', tc_site_footer_elements());

    $conditions = Theme_Builder_Module::instance()->get_conditions_manager();
    $conditions->save_conditions($headerId, [['type' => 'include', 'name' => 'general']]);
    $conditions->save_conditions($footerId, [['type' => 'include', 'name' => 'general']]);
    tc_site_assert(get_post_meta($headerId, '_elementor_conditions', true) === ['include/general'], 'Could not save global Header condition.');
    tc_site_assert(get_post_meta($footerId, '_elementor_conditions', true) === ['include/general'], 'Could not save global Footer condition.');

    $componentIds = [];
    foreach (tc_site_component_specs() as $key => [$title, $slug, $elements]) {
        $id = tc_site_resolve_owned_post($key, 'elementor_library', 'Titip Cirebon — ' . $title, $slug, 'container', true);
        tc_site_save_document($id, 'Titip Cirebon — ' . $title, $elements);
        $componentIds[$key] = $id;
    }

    $previewId = tc_site_resolve_owned_post(TC_SITE_PREVIEW_KEY, 'page', 'Site Components Preview', TC_SITE_PREVIEW_SLUG, 'wp-page', true);
    tc_site_save_document($previewId, 'Site Components Preview', tc_site_preview_elements($componentIds), [
        'template' => 'elementor_header_footer',
        'hide_title' => 'yes',
    ]);

    $customCss = tc_site_replace_css_block(wp_get_custom_css('hello-elementor'), tc_site_css());
    $cssPost = wp_update_custom_css_post($customCss, ['stylesheet' => 'hello-elementor']);
    tc_site_assert(!is_wp_error($cssPost), 'Could not update controlled Site Components CSS.');

    $regenerationIds = array_merge([TC_SITE_KIT_ID, $headerId, $footerId, $previewId], array_values($componentIds));
    $designPreviewId = (int) get_option('titip_cirebon_design_system_preview_id', 0);
    if ($designPreviewId > 0 && get_post_meta($designPreviewId, '_titip_cirebon_design_system_preview', true) === '1') {
        $regenerationIds[] = $designPreviewId;
    }
    foreach (array_unique($regenerationIds) as $id) {
        Elementor_Post_CSS::create($id)->update();
    }

    echo "Site components applied.\n";
    echo "Header template ID: {$headerId}\n";
    echo "Footer template ID: {$footerId}\n";
    echo "Preview page ID: {$previewId}\n";
    echo 'Preview URL: ' . get_permalink($previewId) . "\n";
}

function tc_site_verify(): void
{
    $menu = tc_site_resolve_menu(false);
    $expectedMenu = [
        ['Produk', home_url('/#produk')],
        ['Cara Order', home_url('/#cara-order')],
        ['Lacak Pesanan', home_url('/#lacak-pesanan')],
        ['Tentang Kami', home_url('/#tentang-kami')],
    ];
    $items = wp_get_nav_menu_items($menu->term_id) ?: [];
    tc_site_assert(count($items) === 4, 'Primary navigation must contain exactly four items.');
    foreach ($expectedMenu as $index => [$title, $url]) {
        tc_site_assert(($items[$index]->title ?? '') === $title && ($items[$index]->url ?? '') === $url, "Navigation item {$title} is incorrect.");
    }

    $expectedPosts = [
        'header' => ['elementor_library', 'titip-cirebon-global-header', 'header'],
        'footer' => ['elementor_library', 'titip-cirebon-global-footer', 'footer'],
        'primary-cta' => ['elementor_library', 'tc-primary-cta', 'container'],
        'secondary-cta' => ['elementor_library', 'tc-secondary-cta', 'container'],
        'utility-cta' => ['elementor_library', 'tc-utility-cta', 'container'],
        'whatsapp-cta' => ['elementor_library', 'tc-whatsapp-cta', 'container'],
        'product-card' => ['elementor_library', 'tc-product-card', 'container'],
        'product-badges' => ['elementor_library', 'tc-product-badges', 'container'],
        'trust-cards' => ['elementor_library', 'tc-trust-cards', 'container'],
        'section-heading' => ['elementor_library', 'tc-section-heading', 'container'],
        'content-pattern' => ['elementor_library', 'tc-basic-content-pattern', 'container'],
        TC_SITE_PREVIEW_KEY => ['page', TC_SITE_PREVIEW_SLUG, 'wp-page'],
    ];
    $resolved = [];
    foreach ($expectedPosts as $key => [$postType, $slug, $documentType]) {
        $id = tc_site_resolve_owned_post($key, $postType, '', $slug, $documentType, false);
        $post = get_post($id);
        tc_site_assert($post instanceof WP_Post && $post->post_status === 'publish' && $post->post_name === $slug, "Owned {$key} is not published correctly.");
        tc_site_assert(get_post_meta($id, '_elementor_edit_mode', true) === 'builder', "Owned {$key} is not marked as Elementor content.");
        $ownedCount = (new WP_Query([
            'post_type' => $postType,
            'post_status' => 'any',
            'meta_key' => TC_SITE_MARKER,
            'meta_value' => $key,
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]))->found_posts;
        tc_site_assert((int) $ownedCount === 1, "Owned {$key} is duplicated.");
        $cssPath = Elementor_Post_CSS::create($id)->get_path();
        tc_site_assert(is_file($cssPath) && filesize($cssPath) > 0, "Generated Elementor CSS for {$key} is missing.");
        $resolved[$key] = $id;
    }

    tc_site_assert(get_post_meta($resolved['header'], '_elementor_conditions', true) === ['include/general'], 'Header global condition is incorrect.');
    tc_site_assert(get_post_meta($resolved['footer'], '_elementor_conditions', true) === ['include/general'], 'Footer global condition is incorrect.');
    foreach (['header', 'footer'] as $location) {
        $locationIds = array_map('intval', array_keys(Theme_Builder_Module::instance()->get_conditions_manager()->get_location_templates($location)));
        tc_site_assert(in_array($resolved[$location], $locationIds, true), "Theme Builder does not resolve the global {$location}.");
    }

    $headerData = (string) get_post_meta($resolved['header'], '_elementor_data', true);
    $footerData = (string) get_post_meta($resolved['footer'], '_elementor_data', true);
    tc_site_assert(str_contains($headerData, 'tc-site-header') && str_contains($headerData, 'nav-menu') && str_contains($headerData, 'Pesan Sekarang'), 'Header Elementor structure is incomplete.');
    tc_site_assert(str_contains($footerData, 'tc-site-footer') && str_contains($footerData, 'Open PO Senin') && str_contains($footerData, '#whatsapp-order'), 'Footer Elementor structure is incomplete.');

    $previewId = $resolved[TC_SITE_PREVIEW_KEY];
    tc_site_assert(get_page_template_slug($previewId) === 'elementor_header_footer', 'Preview must use Elementor Full Width with global shell.');
    $previewData = (string) get_post_meta($previewId, '_elementor_data', true);
    foreach (['primary-cta', 'secondary-cta', 'utility-cta', 'whatsapp-cta', 'product-card', 'product-badges', 'trust-cards', 'section-heading', 'content-pattern'] as $key) {
        tc_site_assert(str_contains($previewData, (string) $resolved[$key]), "Preview does not reference reusable template {$key}.");
    }

    $css = wp_get_custom_css('hello-elementor');
    tc_site_assert(substr_count($css, TC_SITE_CSS_START) === 1 && substr_count($css, TC_SITE_CSS_END) === 1, 'Site Components CSS block is missing or duplicated.');
    tc_site_assert(!str_contains($css, '!important'), 'Site Components CSS must not use !important.');
    foreach (['.tc-site-header', '.tc-site-footer', '.tc-button--utility', '.tc-whatsapp-pattern', '.tc-trust-card', '.tc-section-heading-pattern'] as $selector) {
        tc_site_assert(str_contains($css, $selector), "Required selector {$selector} is missing.");
    }
    tc_site_assert(!preg_match('/(<script|@keyframes|parallax|lottie|video)/i', $headerData . $footerData . $previewData . $css), 'Forbidden heavy/custom behavior found.');
    tc_site_assert(get_option('show_on_front') === 'posts' && (int) get_option('page_on_front') === 0, 'Homepage state changed unexpectedly.');

    echo "Site components verification passed.\n";
    echo "Header template ID: {$resolved['header']}\n";
    echo "Footer template ID: {$resolved['footer']}\n";
    echo "Preview page ID: {$previewId}\n";
    echo 'Preview URL: ' . get_permalink($previewId) . "\n";
    echo 'Reusable component templates: 9' . "\n";
}

try {
    tc_site_preflight();
    $mode = getenv('SITE_COMPONENTS_MODE') ?: '';
    if ($mode === 'apply') {
        tc_site_apply();
        tc_site_verify();
    } elseif ($mode === 'verify') {
        tc_site_verify();
    } else {
        tc_site_fail('SITE_COMPONENTS_MODE must be apply or verify.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Site components error: ' . $error->getMessage() . "\n");
    exit(1);
}
