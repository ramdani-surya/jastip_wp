<?php

declare(strict_types=1);

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';

use Elementor\Core\Files\CSS\Post as Elementor_Post_CSS;
use Elementor\Plugin as Elementor_Plugin;

const TC_KIT_ID = 11;
const TC_PREVIEW_OPTION = 'titip_cirebon_design_system_preview_id';
const TC_PREVIEW_MARKER = '_titip_cirebon_design_system_preview';
const TC_PREVIEW_SLUG = 'design-system-preview';
const TC_CSS_START = '/* TITIP CIREBON DESIGN SYSTEM: START */';
const TC_CSS_END = '/* TITIP CIREBON DESIGN SYSTEM: END */';

function tc_fail(string $message): never
{
    throw new RuntimeException($message);
}

function tc_assert(bool $condition, string $message): void
{
    if (!$condition) {
        tc_fail($message);
    }
}

function tc_assert_homepage_state(): void
{
    $mode = (string) get_option('show_on_front');
    $frontId = (int) get_option('page_on_front');

    if ($mode === 'posts' && $frontId === 0) {
        return;
    }

    $ownedHomeId = (int) get_option('titip_cirebon_homepage_id', 0);
    tc_assert(
        $mode === 'page'
        && $frontId > 0
        && $frontId === $ownedHomeId
        && get_post_meta($frontId, '_titip_cirebon_homepage_owned', true) === 'home',
        'Homepage must remain posts/0 or the owned Sprint 2 static Home page.'
    );
}

function tc_dimensions(int $top, int $right, int $bottom, int $left): array
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

function tc_size(float|int $size, string $unit = 'px'): array
{
    return [
        'unit' => $unit,
        'size' => $size,
        'sizes' => [],
    ];
}

function tc_typography(
    string $id,
    string $title,
    string $family,
    string $weight,
    int $desktop,
    int $tablet,
    int $mobile,
    float $lineHeight
): array {
    return [
        '_id' => $id,
        'title' => $title,
        'typography_typography' => 'custom',
        'typography_font_family' => $family,
        'typography_font_weight' => $weight,
        'typography_font_size' => tc_size($desktop),
        'typography_font_size_tablet' => tc_size($tablet),
        'typography_font_size_mobile' => tc_size($mobile),
        'typography_line_height' => tc_size($lineHeight, 'em'),
    ];
}

function tc_element_id(string $name): string
{
    return substr(md5('titip-cirebon-design-system:' . $name), 0, 7);
}

function tc_widget(string $name, string $type, array $settings): array
{
    return [
        'id' => tc_element_id($name),
        'elType' => 'widget',
        'settings' => $settings,
        'elements' => [],
        'widgetType' => $type,
    ];
}

function tc_container(string $name, string $classes, array $elements, array $settings = []): array
{
    return [
        'id' => tc_element_id($name),
        'elType' => 'container',
        'settings' => array_merge([
            'content_width' => 'boxed',
            'flex_direction' => 'column',
            'css_classes' => $classes,
        ], $settings),
        'elements' => $elements,
        'isInner' => false,
    ];
}

function tc_global_color(string $id): string
{
    return 'globals/colors?id=' . $id;
}

function tc_global_typography(string $id): string
{
    return 'globals/typography?id=' . $id;
}

function tc_preflight(): void
{
    global $wpdb, $wp_version;

    tc_assert($wp_version === '7.0.3', "WordPress {$wp_version} found; expected 7.0.3.");
    tc_assert(PHP_VERSION === '8.4.24', 'PHP 8.4.24 is required.');
    tc_assert($wpdb->db_version() === '8.0.46', 'MySQL 8.0.46 is required.');
    tc_assert(is_plugin_active('elementor/elementor.php'), 'Elementor Free is not active.');
    tc_assert(is_plugin_active('elementor-pro/elementor-pro.php'), 'Elementor Pro is not active.');
    tc_assert(defined('ELEMENTOR_VERSION') && ELEMENTOR_VERSION === '4.2.2', 'Elementor Free 4.2.2 is required.');
    tc_assert(defined('ELEMENTOR_PRO_VERSION') && ELEMENTOR_PRO_VERSION === '4.0.4', 'Elementor Pro 4.0.4 is required.');

    $activePlugins = (array) get_option('active_plugins', []);
    sort($activePlugins);
    tc_assert($activePlugins === ['elementor-pro/elementor-pro.php', 'elementor/elementor.php'], 'Only Elementor Free and Elementor Pro may be active in Sprint 1B.');

    $theme = wp_get_theme();
    tc_assert($theme->get_stylesheet() === 'hello-elementor', 'Hello Elementor must remain active.');
    tc_assert($theme->get('Version') === '3.4.9', 'Hello Elementor 3.4.9 is required.');
    tc_assert((int) get_option('elementor_active_kit') === TC_KIT_ID, 'Active Elementor Kit must remain ID 11.');
    tc_assert(Elementor_Plugin::$instance->experiments->is_feature_active('container'), 'Elementor Container must be active.');

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
        tc_assert(is_string($source) && str_contains($source, $signature), "Required PHP 8.4 hotfix is missing: {$file}.");
    }
}

function tc_system_colors(): array
{
    return [
        ['_id' => 'primary', 'title' => 'Primary', 'color' => '#B45309'],
        ['_id' => 'secondary', 'title' => 'Secondary', 'color' => '#0F766E'],
        ['_id' => 'text', 'title' => 'Text', 'color' => '#292524'],
        ['_id' => 'accent', 'title' => 'Accent', 'color' => '#F59E0B'],
    ];
}

function tc_custom_colors(): array
{
    return [
        ['_id' => 'tc-primary-dark', 'title' => 'Primary Dark', 'color' => '#78350F'],
        ['_id' => 'tc-background', 'title' => 'Background', 'color' => '#FFFDF8'],
        ['_id' => 'tc-surface', 'title' => 'Surface', 'color' => '#FFFFFF'],
        ['_id' => 'tc-text-muted', 'title' => 'Text Muted', 'color' => '#78716C'],
        ['_id' => 'tc-border', 'title' => 'Border', 'color' => '#E7E5E4'],
        ['_id' => 'tc-success', 'title' => 'Success', 'color' => '#15803D'],
        ['_id' => 'tc-warning', 'title' => 'Warning', 'color' => '#B45309'],
        ['_id' => 'tc-error', 'title' => 'Error', 'color' => '#B91C1C'],
    ];
}

function tc_system_typography(): array
{
    return [
        ['_id' => 'primary', 'title' => 'Primary', 'typography_typography' => 'custom', 'typography_font_family' => 'Poppins', 'typography_font_weight' => '700'],
        ['_id' => 'secondary', 'title' => 'Secondary', 'typography_typography' => 'custom', 'typography_font_family' => 'Poppins', 'typography_font_weight' => '600'],
        ['_id' => 'text', 'title' => 'Text', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '400'],
        ['_id' => 'accent', 'title' => 'Accent', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '600'],
    ];
}

function tc_custom_typography(): array
{
    return [
        tc_typography('tc-h1', 'Heading 1', 'Poppins', '700', 48, 40, 34, 1.15),
        tc_typography('tc-h2', 'Heading 2', 'Poppins', '700', 36, 32, 28, 1.20),
        tc_typography('tc-h3', 'Heading 3', 'Poppins', '600', 28, 26, 22, 1.25),
        tc_typography('tc-h4', 'Heading 4', 'Poppins', '600', 22, 20, 18, 1.30),
        tc_typography('tc-h5', 'Heading 5', 'Poppins', '600', 18, 18, 17, 1.40),
        tc_typography('tc-h6', 'Heading 6', 'Poppins', '600', 16, 16, 16, 1.40),
        tc_typography('tc-body', 'Body', 'Inter', '400', 16, 16, 16, 1.60),
        tc_typography('tc-small', 'Small', 'Inter', '400', 14, 14, 14, 1.50),
        tc_typography('tc-button', 'Button', 'Inter', '600', 16, 16, 16, 1.20),
    ];
}

function tc_heading_settings(string $prefix, int $desktop, int $tablet, int $mobile, string $weight, float $lineHeight): array
{
    return [
        "{$prefix}_color" => '#78350F',
        "{$prefix}_typography_typography" => 'custom',
        "{$prefix}_typography_font_family" => 'Poppins',
        "{$prefix}_typography_font_weight" => $weight,
        "{$prefix}_typography_font_size" => tc_size($desktop),
        "{$prefix}_typography_font_size_tablet" => tc_size($tablet),
        "{$prefix}_typography_font_size_mobile" => tc_size($mobile),
        "{$prefix}_typography_line_height" => tc_size($lineHeight, 'em'),
    ];
}

function tc_kit_settings(): array
{
    $settings = [
        'system_colors' => tc_system_colors(),
        'custom_colors' => tc_custom_colors(),
        'system_typography' => tc_system_typography(),
        'custom_typography' => tc_custom_typography(),
        'default_generic_fonts' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'body_background_background' => 'classic',
        'body_background_color' => '#FFFDF8',
        'body_color' => '#292524',
        'body_typography_typography' => 'custom',
        'body_typography_font_family' => 'Inter',
        'body_typography_font_weight' => '400',
        'body_typography_font_size' => tc_size(16),
        'body_typography_font_size_tablet' => tc_size(16),
        'body_typography_font_size_mobile' => tc_size(16),
        'body_typography_line_height' => tc_size(1.60, 'em'),
        'paragraph_spacing' => tc_size(16),
        'link_normal_color' => '#B45309',
        'link_hover_color' => '#78350F',
        'container_width' => tc_size(1200),
        'container_padding' => tc_dimensions(0, 24, 0, 24),
        'container_padding_tablet' => tc_dimensions(0, 20, 0, 20),
        'container_padding_mobile' => tc_dimensions(0, 16, 0, 16),
        'space_between_widgets' => ['row' => '20', 'column' => '20', 'unit' => 'px', 'isLinked' => true],
        'button_typography_typography' => 'custom',
        'button_typography_font_family' => 'Inter',
        'button_typography_font_weight' => '600',
        'button_typography_font_size' => tc_size(16),
        'button_typography_font_size_tablet' => tc_size(16),
        'button_typography_font_size_mobile' => tc_size(16),
        'button_typography_line_height' => tc_size(1.20, 'em'),
        'button_text_color' => '#FFFFFF',
        'button_background_background' => 'classic',
        'button_background_color' => '#B45309',
        'button_hover_text_color' => '#FFFFFF',
        'button_hover_background_background' => 'classic',
        'button_hover_background_color' => '#78350F',
        'button_border_radius' => tc_dimensions(10, 10, 10, 10),
        'button_hover_border_radius' => tc_dimensions(10, 10, 10, 10),
        'button_padding' => tc_dimensions(12, 20, 12, 20),
        'form_label_color' => '#292524',
        'form_label_typography_typography' => 'custom',
        'form_label_typography_font_family' => 'Inter',
        'form_label_typography_font_weight' => '500',
        'form_label_typography_font_size' => tc_size(16),
        'form_field_typography_typography' => 'custom',
        'form_field_typography_font_family' => 'Inter',
        'form_field_typography_font_weight' => '400',
        'form_field_typography_font_size' => tc_size(16),
        'form_field_text_color' => '#292524',
        'form_field_background_color' => '#FFFFFF',
        'form_field_border_border' => 'solid',
        'form_field_border_width' => tc_dimensions(1, 1, 1, 1),
        'form_field_border_color' => '#E7E5E4',
        'form_field_border_radius' => tc_dimensions(10, 10, 10, 10),
        'form_field_focus_text_color' => '#292524',
        'form_field_focus_background_color' => '#FFFFFF',
        'form_field_focus_border_border' => 'solid',
        'form_field_focus_border_width' => tc_dimensions(1, 1, 1, 1),
        'form_field_focus_border_color' => '#0F766E',
        'form_field_focus_border_radius' => tc_dimensions(10, 10, 10, 10),
        'form_field_focus_transition_duration' => tc_size(150),
        'form_field_padding' => tc_dimensions(12, 14, 12, 14),
    ];

    foreach ([
        ['h1', 48, 40, 34, '700', 1.15],
        ['h2', 36, 32, 28, '700', 1.20],
        ['h3', 28, 26, 22, '600', 1.25],
        ['h4', 22, 20, 18, '600', 1.30],
        ['h5', 18, 18, 17, '600', 1.40],
        ['h6', 16, 16, 16, '600', 1.40],
    ] as [$prefix, $desktop, $tablet, $mobile, $weight, $lineHeight]) {
        $settings = array_merge($settings, tc_heading_settings($prefix, $desktop, $tablet, $mobile, $weight, $lineHeight));
    }

    return $settings;
}

function tc_design_system_css(): string
{
    return <<<'CSS'
/* Design tokens and reusable component foundations. */
.elementor-kit-11 {
    --tc-primary: var(--e-global-color-primary, #B45309);
    --tc-primary-dark: var(--e-global-color-tc-primary-dark, #78350F);
    --tc-secondary: var(--e-global-color-secondary, #0F766E);
    --tc-accent: var(--e-global-color-accent, #F59E0B);
    --tc-background: var(--e-global-color-tc-background, #FFFDF8);
    --tc-surface: var(--e-global-color-tc-surface, #FFFFFF);
    --tc-text: var(--e-global-color-text, #292524);
    --tc-text-muted: var(--e-global-color-tc-text-muted, #78716C);
    --tc-border: var(--e-global-color-tc-border, #E7E5E4);
    --tc-success: var(--e-global-color-tc-success, #15803D);
    --tc-warning: var(--e-global-color-tc-warning, #B45309);
    --tc-error: var(--e-global-color-tc-error, #B91C1C);
    --tc-radius-lg: 16px;
    --tc-radius-sm: 12px;
    --tc-radius-control: 10px;
    --tc-shadow-default: 0 4px 20px rgba(0, 0, 0, 0.06);
    --tc-shadow-elevated: 0 8px 30px rgba(0, 0, 0, 0.08);
}

.elementor-kit-11 .tc-section {
    padding-block: 80px;
}

.elementor-kit-11 .tc-section--compact {
    padding-block: 48px;
}

.elementor-kit-11 .tc-card,
.elementor-kit-11 .tc-product-card {
    background: var(--tc-surface);
    border: 1px solid var(--tc-border);
    border-radius: var(--tc-radius-lg);
    box-shadow: var(--tc-shadow-default);
}

.elementor-kit-11 .tc-card {
    padding: 24px;
}

.elementor-kit-11 .tc-card--elevated {
    box-shadow: var(--tc-shadow-elevated);
}

.elementor-kit-11 .tc-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
    gap: 20px;
    width: 100%;
}

.elementor-kit-11 .tc-swatches {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
    gap: 16px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.elementor-kit-11 .tc-swatch {
    overflow: hidden;
    background: var(--tc-surface);
    border: 1px solid var(--tc-border);
    border-radius: var(--tc-radius-sm);
}

.elementor-kit-11 .tc-swatch__color {
    display: block;
    min-height: 88px;
    border-bottom: 1px solid var(--tc-border);
}

.elementor-kit-11 .tc-swatch__label {
    display: grid;
    gap: 2px;
    padding: 12px;
    color: var(--tc-text);
    font: 600 14px/1.4 Inter, system-ui, sans-serif;
}

.elementor-kit-11 .tc-swatch__label small {
    color: var(--tc-text-muted);
    font-weight: 400;
}

.elementor-kit-11 .tc-swatch--primary .tc-swatch__color { background: #B45309; }
.elementor-kit-11 .tc-swatch--primary-dark .tc-swatch__color { background: #78350F; }
.elementor-kit-11 .tc-swatch--secondary .tc-swatch__color { background: #0F766E; }
.elementor-kit-11 .tc-swatch--accent .tc-swatch__color { background: #F59E0B; }
.elementor-kit-11 .tc-swatch--background .tc-swatch__color { background: #FFFDF8; }
.elementor-kit-11 .tc-swatch--surface .tc-swatch__color { background: #FFFFFF; }
.elementor-kit-11 .tc-swatch--text .tc-swatch__color { background: #292524; }
.elementor-kit-11 .tc-swatch--muted .tc-swatch__color { background: #78716C; }
.elementor-kit-11 .tc-swatch--border .tc-swatch__color { background: #E7E5E4; }
.elementor-kit-11 .tc-swatch--success .tc-swatch__color { background: #15803D; }
.elementor-kit-11 .tc-swatch--warning .tc-swatch__color { background: #B45309; }
.elementor-kit-11 .tc-swatch--error .tc-swatch__color { background: #B91C1C; }

.elementor-kit-11 .tc-badges,
.elementor-kit-11 .tc-preview-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.elementor-kit-11 .tc-badge {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 4px 10px;
    border-radius: 999px;
    font: 600 13px/1.2 Inter, system-ui, sans-serif;
}

.elementor-kit-11 .tc-badge--primary { color: #FFFFFF; background: var(--tc-primary); }
.elementor-kit-11 .tc-badge--secondary { color: #FFFFFF; background: var(--tc-secondary); }
.elementor-kit-11 .tc-badge--accent { color: var(--tc-primary-dark); background: #FEF3C7; }
.elementor-kit-11 .tc-badge--success { color: #FFFFFF; background: var(--tc-success); }
.elementor-kit-11 .tc-badge--error { color: #FFFFFF; background: var(--tc-error); }

.elementor-kit-11 .elementor-button {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 160ms ease, background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
}

.elementor-kit-11 .tc-button--secondary .elementor-button {
    color: var(--tc-secondary);
    background: var(--tc-surface);
    border: 1px solid var(--tc-secondary);
}

.elementor-kit-11 .tc-button--secondary .elementor-button:hover,
.elementor-kit-11 .tc-button--secondary .elementor-button:focus {
    color: #FFFFFF;
    background: var(--tc-secondary);
}

.elementor-kit-11 :where(a, button, input, select, textarea, .elementor-button):focus-visible {
    outline: 3px solid rgba(15, 118, 110, 0.35);
    outline-offset: 3px;
}

.elementor-kit-11 a:not(.elementor-button):hover,
.elementor-kit-11 a:not(.elementor-button):focus-visible {
    text-decoration: underline;
    text-underline-offset: 0.18em;
}

.elementor-kit-11 .tc-product-card {
    overflow: hidden;
}

.elementor-kit-11 .tc-product-card__media {
    aspect-ratio: 4 / 3;
    display: grid;
    place-items: center;
    padding: 20px;
    color: var(--tc-text-muted);
    background: linear-gradient(135deg, #FFF7ED, #FEF3C7);
    border-bottom: 1px solid var(--tc-border);
    text-align: center;
    font: 500 14px/1.5 Inter, system-ui, sans-serif;
}

.elementor-kit-11 .tc-product-card__content {
    display: grid;
    gap: 12px;
    padding: 20px;
}

.elementor-kit-11 .tc-product-card__title {
    margin: 0;
    color: var(--tc-primary-dark);
    font: 600 20px/1.3 Poppins, system-ui, sans-serif;
}

.elementor-kit-11 .tc-product-card__description {
    margin: 0;
    color: var(--tc-text-muted);
}

.elementor-kit-11 .tc-product-card__price {
    color: var(--tc-text);
    font: 700 20px/1.3 Poppins, system-ui, sans-serif;
}

.elementor-kit-11 .tc-product-card__footer {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
}

.elementor-kit-11 .tc-form {
    display: grid;
    gap: 16px;
}

.elementor-kit-11 .tc-form__field {
    display: grid;
    gap: 8px;
}

.elementor-kit-11 .tc-form label {
    color: var(--tc-text);
    font-weight: 500;
}

.elementor-kit-11 .tc-form input,
.elementor-kit-11 .tc-form select,
.elementor-kit-11 .tc-form textarea {
    width: 100%;
    min-height: 46px;
    padding: 12px 14px;
    color: var(--tc-text);
    background: var(--tc-surface);
    border: 1px solid var(--tc-border);
    border-radius: var(--tc-radius-control);
    font: 400 16px/1.5 Inter, system-ui, sans-serif;
}

.elementor-kit-11 .tc-form textarea {
    min-height: 112px;
    resize: vertical;
}

.elementor-kit-11 .tc-form input:focus,
.elementor-kit-11 .tc-form select:focus,
.elementor-kit-11 .tc-form textarea:focus {
    border-color: var(--tc-secondary);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.14);
}

.elementor-kit-11 .tc-local-accent {
    position: relative;
}

.elementor-kit-11 .tc-local-accent::before {
    content: "";
    display: block;
    width: 72px;
    height: 4px;
    margin-bottom: 20px;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--tc-primary), var(--tc-accent), var(--tc-secondary));
}

.elementor-kit-11 .tc-token-list {
    display: grid;
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.elementor-kit-11 .tc-token-list li {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--tc-border);
}

.elementor-kit-11 .tc-preview-note {
    color: var(--tc-text-muted);
    font-size: 14px;
}

@media (max-width: 1024px) {
    .elementor-kit-11 .tc-section { padding-block: 64px; }
    .elementor-kit-11 .tc-section--compact { padding-block: 40px; }
}

@media (max-width: 767px) {
    .elementor-kit-11 .tc-section { padding-block: 48px; }
    .elementor-kit-11 .tc-section--compact { padding-block: 32px; }
    .elementor-kit-11 .tc-card { padding: 20px; }
    .elementor-kit-11 .tc-product-card__footer { align-items: stretch; }
    .elementor-kit-11 .tc-product-card__footer .elementor-button { width: 100%; }
}
CSS;
}

function tc_preview_elements(): array
{
    $title = tc_widget('preview-title', 'heading', [
        'title' => 'Titip Cirebon Design System',
        'header_size' => 'h1',
        '__globals__' => [
            'title_color' => tc_global_color('tc-primary-dark'),
            'typography_typography' => tc_global_typography('tc-h1'),
        ],
    ]);

    $intro = tc_widget('preview-intro', 'text-editor', [
        'editor' => '<p><strong>Modern Local Food</strong> — fondasi visual internal untuk pengalaman yang hangat, lokal, ramah, dan terpercaya. Halaman ini adalah specimen design system, bukan homepage.</p>',
        '__globals__' => [
            'text_color' => tc_global_color('text'),
            'typography_typography' => tc_global_typography('tc-body'),
        ],
    ]);

    $swatches = tc_widget('color-swatches', 'html', [
        'html' => <<<'HTML'
<ul class="tc-swatches" aria-label="Global color palette">
  <li class="tc-swatch tc-swatch--primary"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Primary<small>#B45309</small></span></li>
  <li class="tc-swatch tc-swatch--primary-dark"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Primary Dark<small>#78350F</small></span></li>
  <li class="tc-swatch tc-swatch--secondary"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Secondary<small>#0F766E</small></span></li>
  <li class="tc-swatch tc-swatch--accent"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Accent<small>#F59E0B</small></span></li>
  <li class="tc-swatch tc-swatch--background"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Background<small>#FFFDF8</small></span></li>
  <li class="tc-swatch tc-swatch--surface"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Surface<small>#FFFFFF</small></span></li>
  <li class="tc-swatch tc-swatch--text"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Text<small>#292524</small></span></li>
  <li class="tc-swatch tc-swatch--muted"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Text Muted<small>#78716C</small></span></li>
  <li class="tc-swatch tc-swatch--border"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Border<small>#E7E5E4</small></span></li>
  <li class="tc-swatch tc-swatch--success"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Success<small>#15803D</small></span></li>
  <li class="tc-swatch tc-swatch--warning"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Warning<small>#B45309</small></span></li>
  <li class="tc-swatch tc-swatch--error"><span class="tc-swatch__color"></span><span class="tc-swatch__label">Error<small>#B91C1C</small></span></li>
</ul>
HTML,
    ]);

    $typography = [];
    foreach ([
        ['type-h1', 'H1 — Cerita Rasa dari Cirebon', 'h1', 'tc-h1'],
        ['type-h2', 'H2 — Oleh-oleh Pilihan Minggu Ini', 'h2', 'tc-h2'],
        ['type-h3', 'H3 — Jadwal Open PO', 'h3', 'tc-h3'],
        ['type-h4', 'H4 — Detail Pengiriman', 'h4', 'tc-h4'],
        ['type-h5', 'H5 — Informasi Produk', 'h5', 'tc-h5'],
        ['type-h6', 'H6 — Catatan Penting', 'h6', 'tc-h6'],
    ] as [$name, $text, $tag, $global]) {
        $typography[] = tc_widget($name, 'heading', [
            'title' => $text,
            'header_size' => $tag,
            '__globals__' => [
                'title_color' => tc_global_color('tc-primary-dark'),
                'typography_typography' => tc_global_typography($global),
            ],
        ]);
    }
    $typography[] = tc_widget('type-body', 'text-editor', [
        'editor' => '<p>Body — Informasi produk dan cara order ditulis ringkas, ramah, serta mudah dipahami pengguna mobile.</p><p><small>Small — Detail tambahan, status, dan keterangan sekunder tetap terbaca dengan nyaman.</small></p>',
        '__globals__' => [
            'text_color' => tc_global_color('text'),
            'typography_typography' => tc_global_typography('tc-body'),
        ],
    ]);

    $primaryButton = tc_widget('primary-button', 'button', [
        'text' => 'Pesan Sekarang',
        'link' => ['url' => '#components'],
        'size' => 'sm',
    ]);
    $secondaryButton = tc_widget('secondary-button', 'button', [
        'text' => 'Cara Order',
        'link' => ['url' => '#components'],
        'size' => 'sm',
        '_css_classes' => 'tc-button--secondary',
    ]);

    $components = tc_widget('component-specimens', 'html', [
        'html' => <<<'HTML'
<div id="components" class="tc-preview-grid">
  <article class="tc-product-card">
    <div class="tc-product-card__media" role="img" aria-label="Placeholder area foto produk dengan rasio empat banding tiga">Area foto produk 4:3<br>Gunakan foto produk nyata</div>
    <div class="tc-product-card__content">
      <div class="tc-badges"><span class="tc-badge tc-badge--primary">PO Minggu Ini</span><span class="tc-badge tc-badge--accent">Terlaris</span></div>
      <h3 class="tc-product-card__title">Contoh Nama Produk</h3>
      <p class="tc-product-card__description">Deskripsi singkat asal produk dan alasan produk ini layak dititip.</p>
      <strong class="tc-product-card__price">Rp00.000</strong>
      <div class="tc-product-card__footer"><span class="tc-preview-note">Tutup PO: Minggu</span><a class="elementor-button" href="#components">Lihat Produk</a></div>
    </div>
  </article>
  <div class="tc-card">
    <h3>Badge System</h3>
    <div class="tc-badges">
      <span class="tc-badge tc-badge--primary">Pre-order</span>
      <span class="tc-badge tc-badge--secondary">Baru</span>
      <span class="tc-badge tc-badge--accent">Viral</span>
      <span class="tc-badge tc-badge--success">Tersedia</span>
      <span class="tc-badge tc-badge--error">Habis</span>
    </div>
  </div>
  <div class="tc-card tc-card--elevated">
    <h3>Form System</h3>
    <div class="tc-form" role="group" aria-label="Form style specimen">
      <div class="tc-form__field"><label for="tc-name">Nama</label><input id="tc-name" type="text" placeholder="Nama pelanggan"></div>
      <div class="tc-form__field"><label for="tc-status">Status pesanan</label><select id="tc-status"><option>Pilih status</option><option>Diproses</option></select></div>
      <div class="tc-form__field"><label for="tc-note">Catatan</label><textarea id="tc-note" placeholder="Tulis catatan singkat"></textarea></div>
    </div>
  </div>
</div>
HTML,
    ]);

    $tokens = tc_widget('foundation-tokens', 'html', [
        'html' => <<<'HTML'
<div class="tc-preview-grid">
  <div class="tc-card"><h3>Spacing</h3><ul class="tc-token-list"><li><span>Section</span><strong>80 / 64 / 48px</strong></li><li><span>Compact</span><strong>48 / 40 / 32px</strong></li><li><span>Widget gap</span><strong>20px</strong></li></ul></div>
  <div class="tc-card"><h3>Radius</h3><ul class="tc-token-list"><li><span>Large card</span><strong>16px</strong></li><li><span>Small card</span><strong>12px</strong></li><li><span>Controls</span><strong>10px</strong></li></ul></div>
  <div class="tc-card tc-card--elevated"><h3>Elevation</h3><ul class="tc-token-list"><li><span>Default</span><strong>0 4px 20px / 6%</strong></li><li><span>Elevated</span><strong>0 8px 30px / 8%</strong></li></ul></div>
</div>
HTML,
    ]);

    return [
        tc_container('intro-section', 'tc-section tc-local-accent', [$title, $intro]),
        tc_container('colors-section', 'tc-section tc-section--compact', [
            tc_widget('colors-heading', 'heading', ['title' => 'Global Colors', 'header_size' => 'h2', '__globals__' => ['title_color' => tc_global_color('tc-primary-dark'), 'typography_typography' => tc_global_typography('tc-h2')]]),
            $swatches,
        ]),
        tc_container('typography-section', 'tc-section tc-section--compact', array_merge([
            tc_widget('typography-heading', 'heading', ['title' => 'Typography', 'header_size' => 'h2', '__globals__' => ['title_color' => tc_global_color('tc-primary-dark'), 'typography_typography' => tc_global_typography('tc-h2')]]),
        ], $typography)),
        tc_container('buttons-section', 'tc-section tc-section--compact', [
            tc_widget('buttons-heading', 'heading', ['title' => 'Buttons', 'header_size' => 'h2', '__globals__' => ['title_color' => tc_global_color('tc-primary-dark'), 'typography_typography' => tc_global_typography('tc-h2')]]),
            tc_container('button-row', 'tc-preview-actions', [$primaryButton, $secondaryButton], ['content_width' => 'full', 'flex_direction' => 'row', 'flex_wrap' => 'wrap']),
        ]),
        tc_container('components-section', 'tc-section tc-section--compact', [
            tc_widget('components-heading', 'heading', ['title' => 'Components', 'header_size' => 'h2', '__globals__' => ['title_color' => tc_global_color('tc-primary-dark'), 'typography_typography' => tc_global_typography('tc-h2')]]),
            $components,
        ]),
        tc_container('foundation-section', 'tc-section tc-section--compact', [
            tc_widget('foundation-heading', 'heading', ['title' => 'Layout & Foundation', 'header_size' => 'h2', '__globals__' => ['title_color' => tc_global_color('tc-primary-dark'), 'typography_typography' => tc_global_typography('tc-h2')]]),
            $tokens,
        ]),
    ];
}

function tc_replace_css_block(string $existing, string $css): string
{
    $block = TC_CSS_START . "\n" . trim($css) . "\n" . TC_CSS_END;
    $pattern = '/' . preg_quote(TC_CSS_START, '/') . '.*?' . preg_quote(TC_CSS_END, '/') . '/s';

    if (preg_match($pattern, $existing) === 1) {
        return (string) preg_replace($pattern, $block, $existing, 1);
    }

    return trim($existing) === '' ? $block : rtrim($existing) . "\n\n" . $block;
}

function tc_resolve_preview_page(bool $allowCreate): int
{
    $optionId = (int) get_option(TC_PREVIEW_OPTION, 0);
    $slugPage = get_page_by_path(TC_PREVIEW_SLUG, OBJECT, 'page');

    if ($optionId > 0) {
        $optionPage = get_post($optionId);
        tc_assert($optionPage instanceof WP_Post && $optionPage->post_type === 'page', 'Stored design preview page ID is invalid.');
        tc_assert(get_post_meta($optionId, TC_PREVIEW_MARKER, true) === '1', 'Stored preview page is missing its ownership marker.');
        tc_assert(!$slugPage || (int) $slugPage->ID === $optionId, 'Preview slug conflicts with another page.');
        return $optionId;
    }

    if ($slugPage) {
        tc_assert(get_post_meta((int) $slugPage->ID, TC_PREVIEW_MARKER, true) === '1', 'A page already owns the design-system-preview slug; refusing to overwrite it.');
        return (int) $slugPage->ID;
    }

    tc_assert($allowCreate, 'Design System Preview page has not been created.');

    $pageId = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'draft',
        'post_title' => 'Design System Preview',
        'post_name' => TC_PREVIEW_SLUG,
        'post_content' => '',
        'comment_status' => 'closed',
    ], true);

    tc_assert(!is_wp_error($pageId), 'Could not create Design System Preview page.');
    $pageId = (int) $pageId;
    update_post_meta($pageId, TC_PREVIEW_MARKER, '1');
    update_option(TC_PREVIEW_OPTION, $pageId);

    return $pageId;
}

function tc_apply(): void
{
    $admins = get_users([
        'role' => 'administrator',
        'number' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'ID',
    ]);
    tc_assert($admins !== [], 'No administrator account is available for Elementor Document API saves.');
    wp_set_current_user((int) $admins[0]);

    $pageId = tc_resolve_preview_page(true);
    $kit = Elementor_Plugin::$instance->kits_manager->get_active_kit();
    tc_assert($kit && $kit->get_main_id() === TC_KIT_ID, 'Could not load active Elementor Kit 11.');

    update_option('elementor_disable_color_schemes', 'yes');
    update_option('elementor_disable_typography_schemes', 'yes');
    update_option('elementor_font_display', 'swap');

    $existingSettings = get_post_meta(TC_KIT_ID, '_elementor_page_settings', true);
    $mergedSettings = array_merge(is_array($existingSettings) ? $existingSettings : [], tc_kit_settings());
    tc_assert($kit->save(['settings' => $mergedSettings]), 'Elementor Kit save failed.');

    $customCss = tc_replace_css_block(wp_get_custom_css('hello-elementor'), tc_design_system_css());
    $cssPost = wp_update_custom_css_post($customCss, ['stylesheet' => 'hello-elementor']);
    tc_assert(!is_wp_error($cssPost), 'Could not update controlled WordPress Additional CSS.');

    $document = Elementor_Plugin::$instance->documents->get($pageId, false);
    tc_assert($document !== false && $document !== null, 'Could not load the Elementor preview document.');
    $saved = $document->save([
        'settings' => [
            'post_title' => 'Design System Preview',
            'post_status' => 'publish',
            'template' => 'elementor_canvas',
            'hide_title' => 'yes',
        ],
        'elements' => tc_preview_elements(),
    ]);
    tc_assert($saved, 'Elementor preview document save failed.');
    $document->set_is_built_with_elementor(true);

    update_post_meta($pageId, TC_PREVIEW_MARKER, '1');
    update_option(TC_PREVIEW_OPTION, $pageId);

    Elementor_Plugin::$instance->files_manager->clear_cache();
    Elementor_Post_CSS::create(TC_KIT_ID)->update();
    Elementor_Post_CSS::create($pageId)->update();
    $homeId = (int) get_option('titip_cirebon_homepage_id', 0);
    if ($homeId > 0 && get_post_meta($homeId, '_titip_cirebon_homepage_owned', true) === 'home') {
        Elementor_Post_CSS::create($homeId)->update();
    }
    $supportIds = get_option('titip_cirebon_homepage_support_ids', []);
    if (is_array($supportIds)) {
        foreach ($supportIds as $key => $supportId) {
            $supportId = (int) $supportId;
            if ($supportId > 0 && get_post_meta($supportId, '_titip_cirebon_homepage_owned', true) === (string) $key) {
                Elementor_Post_CSS::create($supportId)->update();
            }
        }
    }

    echo "Design system applied to Elementor Kit " . TC_KIT_ID . ".\n";
    echo "Preview page ID: {$pageId}\n";
    echo 'Preview URL: ' . get_permalink($pageId) . "\n";
}

function tc_index_by_id(array $items): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (isset($item['_id'])) {
            $indexed[$item['_id']] = $item;
        }
    }
    return $indexed;
}

function tc_verify(): void
{
    $kitSettings = get_post_meta(TC_KIT_ID, '_elementor_page_settings', true);
    tc_assert(is_array($kitSettings), 'Elementor Kit settings are missing.');

    $actualColors = tc_index_by_id(array_merge($kitSettings['system_colors'] ?? [], $kitSettings['custom_colors'] ?? []));
    foreach (array_merge(tc_system_colors(), tc_custom_colors()) as $expected) {
        tc_assert(($actualColors[$expected['_id']]['title'] ?? null) === $expected['title'], "Global color title {$expected['_id']} is incorrect.");
        tc_assert(($actualColors[$expected['_id']]['color'] ?? null) === $expected['color'], "Global color {$expected['_id']} is incorrect.");
    }

    $actualTypography = tc_index_by_id(array_merge($kitSettings['system_typography'] ?? [], $kitSettings['custom_typography'] ?? []));
    foreach (array_merge(tc_system_typography(), tc_custom_typography()) as $expected) {
        $id = $expected['_id'];
        tc_assert(isset($actualTypography[$id]), "Global typography {$id} is missing.");
        foreach ($expected as $key => $value) {
            tc_assert(($actualTypography[$id][$key] ?? null) === $value, "Global typography {$id} setting {$key} is incorrect.");
        }
    }

    $collectionKeys = ['system_colors', 'custom_colors', 'system_typography', 'custom_typography'];
    foreach (array_diff_key(tc_kit_settings(), array_flip($collectionKeys)) as $key => $expected) {
        tc_assert(($kitSettings[$key] ?? null) === $expected, "Kit setting {$key} is incorrect.");
    }

    tc_assert(get_option('elementor_disable_color_schemes') === 'yes', 'Elementor default colors are not disabled.');
    tc_assert(get_option('elementor_disable_typography_schemes') === 'yes', 'Elementor default fonts are not disabled.');
    tc_assert(get_option('elementor_font_display') === 'swap', 'Elementor font-display must be swap.');

    $css = wp_get_custom_css('hello-elementor');
    tc_assert(substr_count($css, TC_CSS_START) === 1 && substr_count($css, TC_CSS_END) === 1, 'Controlled design-system CSS block is missing or duplicated.');
    tc_assert(!str_contains($css, '!important'), 'Design-system CSS must not use !important.');
    foreach (['.tc-section', '.tc-card', '.tc-product-card', '.tc-badge', '.tc-button--secondary', '.tc-form', '.tc-local-accent'] as $selector) {
        tc_assert(str_contains($css, $selector), "Utility selector {$selector} is missing.");
    }

    $pageId = tc_resolve_preview_page(false);
    $page = get_post($pageId);
    tc_assert($page instanceof WP_Post && $page->post_status === 'publish', 'Design System Preview must be published.');
    tc_assert($page->post_title === 'Design System Preview' && $page->post_name === TC_PREVIEW_SLUG, 'Preview page identity is incorrect.');
    tc_assert(get_page_template_slug($pageId) === 'elementor_canvas', 'Preview page must use Elementor Canvas.');
    tc_assert(get_post_meta($pageId, '_elementor_edit_mode', true) === 'builder', 'Preview page is not marked as Elementor content.');
    $elements = get_post_meta($pageId, '_elementor_data', true);
    $decodedElements = is_string($elements) ? json_decode($elements, true) : null;
    $normalizedElements = is_array($decodedElements) ? wp_json_encode($decodedElements, JSON_UNESCAPED_SLASHES) : '';
    tc_assert(str_contains($normalizedElements, 'tc-product-card') && str_contains($normalizedElements, 'globals/colors?id=tc-primary-dark'), 'Preview Elementor data is incomplete.');
    tc_assert(str_contains($normalizedElements, '"css_classes":"tc-section tc-local-accent"'), 'Preview Container utility classes are not stored in Elementor 4 format.');

    tc_assert_homepage_state();
    tc_assert((int) get_option('page_on_front') !== $pageId, 'Design preview must not become the homepage.');

    $generatedKitCss = Elementor_Post_CSS::create(TC_KIT_ID)->get_path();
    $generatedPageCss = Elementor_Post_CSS::create($pageId)->get_path();
    tc_assert(is_file($generatedKitCss) && filesize($generatedKitCss) > 0, 'Generated Elementor Kit CSS is missing.');
    tc_assert(is_file($generatedPageCss) && filesize($generatedPageCss) > 0, 'Generated preview page CSS is missing.');

    echo "Design system verification passed.\n";
    echo "Kit: " . TC_KIT_ID . "\n";
    echo "Preview page ID: {$pageId}\n";
    echo 'Preview URL: ' . get_permalink($pageId) . "\n";
    echo 'Global colors: ' . count($actualColors) . "\n";
    echo 'Global typography styles: ' . count($actualTypography) . "\n";
}

try {
    tc_preflight();
    $mode = getenv('DESIGN_SYSTEM_MODE') ?: '';

    if ($mode === 'apply') {
        tc_apply();
        tc_verify();
    } elseif ($mode === 'verify') {
        tc_verify();
    } else {
        tc_fail('DESIGN_SYSTEM_MODE must be apply or verify.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Design system error: ' . $error->getMessage() . "\n");
    exit(1);
}
