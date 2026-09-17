# Runtime = FrankenPHP official image: frankenphp binary is on PATH,
# so Laravel Octane detects it without downloading anything.
FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions @composer pcntl pdo_pgsql redis

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && rm -rf storage/oauth-*.key .env \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]
CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--port=8000", "--no-interaction"]
