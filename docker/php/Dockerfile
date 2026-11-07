FROM wordpress:7.1.4-php8.4-fpm

COPY docker/php/wordpress-entrypoint.sh /usr/local/bin/titip-cirebon-entrypoint
RUN chmod 0755 /usr/local/bin/titip-cirebon-entrypoint

ENTRYPOINT ["titip-cirebon-entrypoint"]
CMD ["php-fpm"]
