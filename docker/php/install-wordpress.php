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

if (!is_blog_installed()) {
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
} else {
    echo "WordPress is already installed; applying local settings.\n";
}

update_option('blogname', $values['WP_SITE_TITLE']);
update_option('siteurl', $values['WORDPRESS_URL']);
update_option('home', $values['WORDPRESS_URL']);
update_option('timezone_string', 'Asia/Jakarta');
update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules(false);

echo "Local URL: {$values['WORDPRESS_URL']}\n";
echo "Timezone: " . wp_timezone_string() . "\n";
echo "Permalink structure: " . get_option('permalink_structure') . "\n";
