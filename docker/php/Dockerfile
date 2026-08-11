FROM wordpress:7.0.2-php8.4-fpm@sha256:cf4241558043a4aaed67aaaa01f56e7c1316b5de78ed8c27571ec9c954b7622e

ARG WORDPRESS_VERSION=7.0.3
ARG WORDPRESS_SHA1=344b74d7cbf13c55ba0f12cad207c06cfee4368a
ARG WORDPRESS_SHA256=38e4c7d79596d58f60fa5496a235471407c44dc37a6f9c4d31d46070985e8bc4

RUN set -eux; \
    find /usr/src/wordpress -mindepth 1 -maxdepth 1 -type f \
        ! -name wp-config-docker.php \
        ! -name .htaccess \
        -printf '%f\n' > /tmp/base-root-files.txt; \
    cp /usr/src/wordpress/wp-config-docker.php /tmp/wp-config-docker.php; \
    curl --fail --location --retry 3 \
        "https://wordpress.org/wordpress-${WORDPRESS_VERSION}.tar.gz" \
        --output /tmp/wordpress.tar.gz; \
    echo "${WORDPRESS_SHA1} */tmp/wordpress.tar.gz" | sha1sum --check --strict -; \
    echo "${WORDPRESS_SHA256} */tmp/wordpress.tar.gz" | sha256sum --check --strict -; \
    tar --extract --gzip --file /tmp/wordpress.tar.gz --directory /tmp; \
    test "$(php -r 'require "/tmp/wordpress/wp-includes/version.php"; echo $wp_version;')" = "${WORDPRESS_VERSION}"; \
    find /tmp/wordpress -mindepth 1 -maxdepth 1 -type f \
        ! -name wp-config-docker.php \
        ! -name .htaccess \
        -printf '%f\n' > /tmp/target-root-files.txt; \
    sort -u /tmp/base-root-files.txt /tmp/target-root-files.txt > /usr/local/share/wordpress-root-files.txt; \
    rm --recursive --force /usr/src/wordpress; \
    mv /tmp/wordpress /usr/src/wordpress; \
    mv /tmp/wp-config-docker.php /usr/src/wordpress/wp-config-docker.php; \
    chown --recursive www-data:www-data /usr/src/wordpress; \
    cd /usr/src/wordpress; \
    find . -type f \
        ! -path './wp-content/*' \
        ! -name wp-config-docker.php \
        -print0 \
        | sort --zero-terminated \
        | xargs --null sha256sum > /usr/local/share/wordpress-core.sha256; \
    rm --force /tmp/base-root-files.txt /tmp/target-root-files.txt /tmp/wordpress.tar.gz

COPY docker/php/wordpress-entrypoint.sh /usr/local/bin/titip-cirebon-entrypoint
RUN chmod 0755 /usr/local/bin/titip-cirebon-entrypoint

ENTRYPOINT ["titip-cirebon-entrypoint"]
CMD ["php-fpm"]
