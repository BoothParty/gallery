# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Base : FrankenPHP + extensions PHP requises par l'application
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4 AS base

# install-php-extensions est fourni par l'image FrankenPHP
RUN install-php-extensions \
      pdo_mysql \
      gd \
      zip \
      intl \
      bcmath \
      exif \
      pcntl \
      redis \
      opcache

WORKDIR /app

# ---------------------------------------------------------------------------
# Vendor : dépendances PHP de production
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction \
      --no-progress

COPY . .

# storage/ et bootstrap/cache sont exclus du contexte de build (.dockerignore)
# pour ne pas embarquer les artefacts locaux : on recrée l'arborescence vide.
RUN mkdir -p \
      bootstrap/cache \
      storage/app/private \
      storage/app/public \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/testing \
      storage/framework/views \
      storage/logs

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------------------------------------------------------------------------
# Assets : build Vite / Tailwind
# Les configs Tailwind scannent aussi les vues de vendor/laravel/framework,
# on récupère donc ce dossier depuis l'étape vendor.
# ---------------------------------------------------------------------------
FROM node:25-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
      ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views

RUN npm run build

# ---------------------------------------------------------------------------
# Image finale
# ---------------------------------------------------------------------------
FROM base AS final

# Le kamal-proxy termine le TLS : FrankenPHP écoute en HTTP sur :80
ENV SERVER_NAME=":80"
ENV APP_ENV=production
ENV APP_DEBUG=false

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

COPY --from=vendor --chown=www-data:www-data /app /app
COPY --from=assets --chown=www-data:www-data /app/public/build /app/public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
