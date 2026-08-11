<?php

declare(strict_types=1);

chdir('/var/www/html');

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

wp_upgrade();

global $wp_db_version, $wp_version;

$database_version = (int) get_option('db_version');

if ($database_version !== (int) $wp_db_version) {
    fwrite(
        STDERR,
        "Database upgrade incomplete: {$database_version}; expected {$wp_db_version}.\n"
    );
    exit(1);
}

echo "WordPress core version: {$wp_version}\n";
echo "WordPress database version: {$database_version}\n";
