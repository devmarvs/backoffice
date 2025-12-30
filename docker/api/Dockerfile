ARG FRANKENPHP_IMAGE=dunglas/frankenphp:latest
FROM ${FRANKENPHP_IMAGE}

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN install-php-extensions pdo_pgsql gd mbstring

COPY ./docker/api/Caddyfile /etc/caddy/Caddyfile
