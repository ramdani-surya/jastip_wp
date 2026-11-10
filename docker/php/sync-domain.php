<?php
/**
 * sync-domain.php — Search-and-replace URL/domain safely across plain text,
 * JSON structures (such as Elementor data), and PHP serialized strings.
 */

declare(strict_types=1);

chdir('/var/www/html');
require '/var/www/html/wp-load.php';

$target_url = getenv('WORDPRESS_URL');
if (empty($target_url)) {
    echo "[sync-domain] WORDPRESS_URL not set, skipping domain sync.\n";
    exit(0);
}

// Normalize URLs (remove trailing slashes)
$target_url = rtrim($target_url, '/');
$current_url = rtrim((string) get_option('siteurl'), '/');

if ($current_url === $target_url) {
    echo "[sync-domain] Domain is already up to date: {$target_url}\n";
    exit(0);
}

echo "[sync-domain] Syncing domain from '{$current_url}' to '{$target_url}'...\n";

/**
 * Recursively replace string in arrays, objects, JSON, and serialized strings.
 */
function tc_safe_replace(string $search, string $replace, mixed $data): mixed {
    if (is_string($data)) {
        if (empty($data)) {
            return $data;
        }

        // 1. Cek jika data adalah PHP serialized string
        if (is_serialized($data)) {
            $unserialized = @unserialize($data);
            if (false !== $unserialized || 'b:0;' === $data) {
                return serialize(tc_safe_replace($search, $replace, $unserialized));
            }
        }

        // 2. Cek jika data adalah JSON (misal _elementor_data)
        if (str_starts_with($data, '{') || str_starts_with($data, '[')) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return wp_json_encode(
                    tc_safe_replace($search, $replace, $decoded),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            }
        }

        return str_replace($search, $replace, $data);
    }

    if (is_array($data)) {
        $result = [];
        foreach ($data as $key => $val) {
            $result[$key] = tc_safe_replace($search, $replace, $val);
        }
        return $result;
    }

    if (is_object($data)) {
        foreach ($data as $prop => $val) {
            $data->$prop = tc_safe_replace($search, $replace, $val);
        }
        return $data;
    }

    return $data;
}

global $wpdb;

// 1. Update wp_options
echo "[sync-domain] Updating wp_options...\n";
update_option('siteurl', $target_url);
update_option('home', $target_url);

$options_to_check = ['elementor_active_kit', 'sidebars_widgets', 'theme_mods_hello-elementor'];
foreach ($options_to_check as $opt) {
    $val = get_option($opt);
    if (!empty($val)) {
        $new_val = tc_safe_replace($current_url, $target_url, $val);
        if ($new_val !== $val) {
            update_option($opt, $new_val);
        }
    }
}

// 2. Update wp_posts (post_content & guid)
echo "[sync-domain] Updating wp_posts...\n";
$posts = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT ID, post_content, guid FROM {$wpdb->posts} WHERE post_content LIKE %s OR guid LIKE %s",
        '%' . $wpdb->esc_like($current_url) . '%',
        '%' . $wpdb->esc_like($current_url) . '%'
    )
);

foreach ($posts as $p) {
    $new_content = str_replace($current_url, $target_url, $p->post_content);
    $new_guid    = str_replace($current_url, $target_url, $p->guid);
    $wpdb->update(
        $wpdb->posts,
        ['post_content' => $new_content, 'guid' => $new_guid],
        ['ID' => $p->ID],
        ['%s', '%s'],
        ['%d']
    );
}

// 3. Update wp_postmeta (khusus _elementor_data dan meta bernilai URL)
echo "[sync-domain] Updating wp_postmeta...\n";
$metas = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
        '%' . $wpdb->esc_like($current_url) . '%'
    )
);

foreach ($metas as $m) {
    $new_meta = tc_safe_replace($current_url, $target_url, $m->meta_value);
    if ($new_meta !== $m->meta_value) {
        $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => $new_meta],
            ['meta_id' => $m->meta_id],
            ['%s'],
            ['%d']
        );
    }
}

// 4. Clear Elementor CSS cache agar file CSS statis di-generate ulang dengan URL baru
if (class_exists('\Elementor\Plugin')) {
    echo "[sync-domain] Clearing Elementor CSS cache...\n";
    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

flush_rewrite_rules(false);

echo "[sync-domain] Domain sync completed successfully: {$target_url}\n";
