#syntax=docker/dockerfile:1

# ─── Stage 1: Composer dependencies ──────────────────────────────────────────
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-progress

COPY src/ src/

RUN composer dump-autoload --classmap-authoritative --no-dev

# ─── Stage 2: FrankenPHP builder (Debian 13) ─────────────────────────────────
FROM dunglas/frankenphp:1.12.4 AS builder

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN install-php-extensions \
    pdo_pgsql \
    apcu \
    intl \
    opcache \
    zip

COPY frankenphp/conf.d/10-app.ini      $PHP_INI_DIR/app.conf.d/10-app.ini
COPY frankenphp/conf.d/20-app.ini $PHP_INI_DIR/app.conf.d/20-app.ini

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

# Extraer librerías compartidas de frankenphp y cada extensión .so
RUN apt-get update \
    && apt-get install -y --no-install-recommends libtree \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /tmp/libs \
    && for target in $(which frankenphp) \
        $(find "$(php -r 'echo ini_get("extension_dir");')" -maxdepth 2 -name "*.so"); do \
        libtree -pv "$target" 2>/dev/null \
            | grep -oP '(?:── )\K/\S+(?= \[)' \
            | while IFS= read -r lib; do \
                [ -f "$lib" ] && cp -n "$lib" /tmp/libs/; \
            done; \
    done

# Preparar directorios de Caddy con propiedad del usuario nonroot (UID 65532)
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R 65532:65532 /data /config

# ─── Stage 3: Build de la app (warmup de caché) ───────────────────────────────
FROM builder AS app_build

WORKDIR /app

COPY --from=vendor /app/vendor vendor/
COPY bin/        bin/
COPY config/     config/
COPY public/     public/
COPY src/        src/
COPY templates/  templates/
COPY migrations/ migrations/
COPY .env        ./

RUN mkdir -p var/cache var/log var/share config/jwt \
    && APP_ENV=prod php bin/console cache:warmup \
    && APP_ENV=prod php bin/console assets:install public \
    && chown -R 65532:65532 var public config/jwt

# ─── Stage 4: Imagen final distroless ────────────────────────────────────────
FROM gcr.io/distroless/base-debian13 AS final

# Binarios PHP (frankenphp para el servidor, php para el servicio migrate)
COPY --from=builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp
COPY --from=builder /usr/local/bin/php        /usr/local/bin/php

# Extensiones PHP y librerías compartidas
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /tmp/libs                      /usr/lib

# Configuración PHP (php.ini + conf.d + app.conf.d con nuestros INI)
COPY --from=builder /usr/local/etc/php /usr/local/etc/php

# Caddyfile y directorios de datos de Caddy (ya con UID 65532)
COPY frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --from=builder /data   /data
COPY --from=builder /config /config

WORKDIR /app

COPY --link --chown=65532:65532 --from=vendor    /app/vendor vendor/
COPY --link --chown=65532:65532 .env             ./
COPY --link --chown=65532:65532 bin/             bin/
COPY --link --chown=65532:65532 config/          config/
COPY --link --chown=65532:65532 --from=app_build /app/config/jwt/ config/jwt/
COPY --link --chown=65532:65532 templates/       templates/
COPY --link --chown=65532:65532 src/             src/
COPY --link --chown=65532:65532 migrations/      migrations/
COPY --link --chown=65532:65532 --from=app_build /app/var/cache  var/cache
COPY --link --chown=65532:65532 --from=app_build /app/var/log    var/log
COPY --link --chown=65532:65532 --from=app_build /app/var/share  var/share
COPY --link --chown=65532:65532 --from=app_build /app/public     public

USER nonroot

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV PHP_INI_SCAN_DIR=":/usr/local/etc/php/app.conf.d"
ENV XDG_CONFIG_HOME=/config
ENV XDG_DATA_HOME=/data

ENTRYPOINT ["/usr/local/bin/frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
