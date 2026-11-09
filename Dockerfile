# SplitWise+ production image: PHP 8.3 on FrankenPHP (Caddy + PHP in one binary).
# Build:  docker build -t splitwise-plus .
# Run:    docker run --rm -p 8080:8080 --env-file .env.production splitwise-plus
# The image contains NO credentials: every secret comes from environment variables
# at runtime (Vercel: Project -> Settings -> Environment Variables).
#
# Base image tag: verify the current tag on https://hub.docker.com/r/dunglas/frankenphp
# before the first real build (could not be pulled from the build sandbox).
FROM dunglas/frankenphp:1-php8.3-bookworm

# Only what the app needs: PDO MySQL + OPcache (mbstring, fileinfo, openssl, json are built in)
RUN install-php-extensions pdo_mysql opcache

# Production php.ini + our limits (receipts up to 5 MB, no PHP version header, opcache)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deploy/php.ini "$PHP_INI_DIR/conf.d/zz-splitwise.ini"

WORKDIR /app
# Application code only (.dockerignore keeps out .env, .git, tests, docs, database dumps, storage data)
COPY app/ /app/app/
COPY config/ /app/config/
COPY public/ /app/public/
COPY Caddyfile /app/Caddyfile
# Run as non-root: www-data needs only its log dir and Caddy's own data/config dirs
RUN mkdir -p /app/storage/logs \
 && rm -f /app/public/router.php /app/public/.htaccess \
 && chown -R www-data:www-data /app/storage /data/caddy /config/caddy

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_TRUST_PROXY=true \
    SESSION_DRIVER=database \
    MAIL_LOG=stderr \
    PORT=8080 \
    SERVER_NAME=:8080

USER www-data
EXPOSE 8080
# Health: GET /healthz (process up) and GET /readyz (MySQL reachable) — configure in the platform/monitor.
CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
