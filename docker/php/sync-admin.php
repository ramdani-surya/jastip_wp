<?php
/**
 * sync-admin.php — Ensure administrator account exists and credentials match environment.
 */

declare(strict_types=1);

chdir('/var/www/html');
define('WP_INSTALLING', true);
require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-includes/pluggable.php';

$user = trim((string) (getenv('WP_ADMIN_USER') ?: 'titip_min'));
$pass = trim((string) (getenv('WP_ADMIN_PASSWORD') ?: ''));
$email = trim((string) (getenv('WP_ADMIN_EMAIL') ?: 'admin@titipcirebon.com'));

if ($user === '') {
    $user = 'titip_min';
}
if ($email === '') {
    $email = 'admin@titipcirebon.com';
}

echo "[sync-admin] Synchronizing administrator account to '{$user}'...\n";

global $wpdb;

// 1. Update User ID 1 directly in database (atomic update, avoids failing mail hooks)
$data = [
    'user_login'    => $user,
    'user_nicename' => sanitize_title($user),
    'display_name'  => $user,
    'user_email'    => $email,
];
if ($pass !== '') {
    $data['user_pass'] = wp_hash_password($pass);
}

$wpdb->update($wpdb->users, $data, ['ID' => 1]);

// 2. Guarantee that a user named 'titip_min' exists and has administrator role
if ($user !== 'titip_min') {
    $titip_min_user = get_user_by('login', 'titip_min');
    if (!$titip_min_user) {
        $uid = wp_create_user('titip_min', $pass !== '' ? $pass : 'change-me', $email);
        if (!is_wp_error($uid)) {
            $u_entity = new WP_User((int) $uid);
            $u_entity->set_role('administrator');
            echo "[sync-admin] Created additional administrator 'titip_min' (ID {$uid}).\n";
        }
    } else {
        if ($pass !== '') {
            $wpdb->update($wpdb->users, ['user_pass' => wp_hash_password($pass)], ['ID' => $titip_min_user->ID]);
        }
        $titip_min_user->set_role('administrator');
        echo "[sync-admin] Administrator 'titip_min' role and password confirmed.\n";
    }
}

// 3. Clear user caches
clean_user_cache(1);
if (isset($titip_min_user) && $titip_min_user) {
    clean_user_cache($titip_min_user->ID);
}
wp_cache_delete('titip_admin', 'userlogins');
wp_cache_delete('titip_min', 'userlogins');
wp_cache_delete($user, 'userlogins');

// 4. Refresh rewrite rules, opcache, and Elementor cache
flush_rewrite_rules(false);
if (function_exists('opcache_reset')) {
    opcache_reset();
}
if (class_exists('\\Elementor\\Plugin')) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

echo "[sync-admin] Administrator synchronization complete. User '{$user}' (and 'titip_min') active.\n";
