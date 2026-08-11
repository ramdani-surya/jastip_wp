#!/usr/bin/env bash
set -Eeuo pipefail

readonly source_dir=/usr/src/wordpress
readonly target_dir=/var/www/html
readonly target_version=7.0.3
readonly root_manifest=/usr/local/share/wordpress-root-files.txt

read_core_version() {
    local version_file="$1/wp-includes/version.php"

    if [[ ! -f "$version_file" ]]; then
        return 0
    fi

    php -r "require '$version_file'; echo \$wp_version;"
}

if [[ "${1:-}" == php-fpm* ]]; then
    source_version="$(read_core_version "$source_dir")"
    current_version="$(read_core_version "$target_dir")"

    if [[ "$source_version" != "$target_version" ]]; then
        echo >&2 "Refusing to start: image core is '$source_version', expected '$target_version'."
        exit 1
    fi

    if [[ "$current_version" != "$target_version" && ( -n "$current_version" || -f "$target_dir/wp-config.php" ) ]]; then
        echo "Updating persistent WordPress core from ${current_version:-an incomplete copy} to $target_version."

        while IFS= read -r root_file; do
            rm --force "$target_dir/$root_file"
        done < "$root_manifest"

        rm --recursive --force "$target_dir/wp-admin" "$target_dir/wp-includes"

        tar \
            --create \
            --directory "$source_dir" \
            --exclude='./wp-content' \
            --exclude='./wp-config-docker.php' \
            --exclude='./wp-includes/version.php' \
            . \
            | tar --extract --directory "$target_dir"

        install \
            --owner=www-data \
            --group=www-data \
            --mode=0644 \
            "$source_dir/wp-includes/version.php" \
            "$target_dir/wp-includes/version.php"

        echo "Persistent WordPress core is now $target_version; wp-content and wp-config.php were preserved."
    fi
fi

exec /usr/local/bin/docker-entrypoint.sh "$@"
