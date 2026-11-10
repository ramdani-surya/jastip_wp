<?php

declare(strict_types=1);

chdir('/var/www/html');

define('WP_INSTALLING', true);

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$required = [
    'WP_SITE_TITLE',
    'WP_ADMIN_USER',
    'WP_ADMIN_PASSWORD',
    'WP_ADMIN_EMAIL',
    'WORDPRESS_URL',
];

$values = [];

foreach ($required as $name) {
    $value = getenv($name);

    if ($value === false || trim($value) === '') {
        fwrite(STDERR, "Missing required environment variable: {$name}\n");
        exit(1);
    }

    $values[$name] = $value;
}

global $wpdb;

if (!is_blog_installed()) {
    $seed_file = '/var/www/database/seed.sql.gz';
    $imported_seed = false;

    if (file_exists($seed_file)) {
        echo "[install] Importing baseline seed from {$seed_file}...\n";
        $host = getenv('WORDPRESS_DB_HOST') ?: 'db:3306';
        [$db_host, $db_port] = explode(':', $host . ':3306');
        $db_name = getenv('WORDPRESS_DB_NAME') ?: 'titip_cirebon';
        $db_user = getenv('WORDPRESS_DB_USER') ?: 'titip_cirebon';
        $db_pass = getenv('WORDPRESS_DB_PASSWORD') ?: '';

        $cmd = sprintf(
            'gunzip -c %s | mysql --host=%s --port=%s --user=%s --password=%s %s 2>&1',
            escapeshellarg($seed_file),
            escapeshellarg($db_host),
            escapeshellarg($db_port ?: '3306'),
            escapeshellarg($db_user),
            escapeshellarg($db_pass),
            escapeshellarg($db_name)
        );

        exec($cmd, $output, $return_var);

        if ($return_var === 0) {
            echo "[install] Baseline seed successfully imported.\n";
            $imported_seed = true;
        } else {
            fwrite(STDERR, "[install] Seed import failed: " . implode("\n", $output) . "\n");
        }
    }

    if (!$imported_seed) {
        echo "[install] Running standard wp_install...\n";
        $result = wp_install(
            $values['WP_SITE_TITLE'],
            $values['WP_ADMIN_USER'],
            $values['WP_ADMIN_EMAIL'],
            true,
            '',
            $values['WP_ADMIN_PASSWORD']
        );

        if (is_wp_error($result)) {
            fwrite(STDERR, $result->get_error_message() . "\n");
            exit(1);
        }

        echo "WordPress installation completed.\n";
    }
} else {
    echo "WordPress is already installed; updating settings.\n";
}

// 1. Ekstrak baseline uploads jika wp-content/uploads/ masih kosong
$uploads_seed = '/var/www/database/uploads-seed.tar.gz';
if (file_exists($uploads_seed) && !file_exists('/var/www/html/wp-content/uploads/2026')) {
    echo "[install] Extracting baseline media uploads...\n";
    exec(sprintf('tar -xzf %s -C /var/www/html/wp-content', escapeshellarg($uploads_seed)));
}

// 2. Update atau sinkronisasi akun Administrator sesuai environment
$admin_user = get_user_by('login', $values['WP_ADMIN_USER']);
if ($admin_user) {
    wp_set_password($values['WP_ADMIN_PASSWORD'], $admin_user->ID);
    wp_update_user([
        'ID'         => $admin_user->ID,
        'user_email' => $values['WP_ADMIN_EMAIL'],
        'role'       => 'administrator',
    ]);
    echo "[install] Administrator user '{$values['WP_ADMIN_USER']}' credentials synchronized.\n";
} else {
    // Cari user ID 1 atau buat user baru
    $user_id = wp_create_user(
        $values['WP_ADMIN_USER'],
        $values['WP_ADMIN_PASSWORD'],
        $values['WP_ADMIN_EMAIL']
    );
    if (!is_wp_error($user_id)) {
        $u = new WP_User((int) $user_id);
        $u->set_role('administrator');
        echo "[install] Created new Administrator user '{$values['WP_ADMIN_USER']}'.\n";
    }
}

// 3. Jaminan Safeguard Nomor Pesanan: Pastikan tidak ada sisa order dummy
// agar nomor pesanan berikutnya 100% PASTI dimulai dari 0001 (TC-YYYY-0001)
$wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_type = 'tc_order'");
$wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'tc_order'");

// 4. Update option standar
update_option('blogname', $values['WP_SITE_TITLE']);
update_option('siteurl', $values['WORDPRESS_URL']);
update_option('home', $values['WORDPRESS_URL']);
update_option('timezone_string', 'Asia/Jakarta');
update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules(false);

echo "Local URL: {$values['WORDPRESS_URL']}\n";
echo "Timezone: " . wp_timezone_string() . "\n";
echo "Permalink structure: " . get_option('permalink_structure') . "\n";
if (class_exists('TC_Order')) {
    echo "Next order sequence verified: " . TC_Order::get_instance()->get_next_order_code() . "\n";
}
