# ./Dockerfile
FROM php:8.3-fpm

# System deps
RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libzip-dev libicu-dev libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install -j$(nproc) bcmath intl pcntl opcache \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install zip

# Redis extension (optional but nice with Horizon)
RUN pecl install redis \
    && docker-php-ext-enable redis
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 20 (for Vite/asset builds)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && npm -v && node -v

WORKDIR /var/www/html

# Opcache recommended defaults for dev (tweak for prod)
RUN { \
  echo 'opcache.enable=1'; \
  echo 'opcache.enable_cli=1'; \
  echo 'opcache.validate_timestamps=1'; \
  echo 'opcache.memory_consumption=128'; \
  echo 'opcache.max_accelerated_files=10000'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Xdebug defaults (off; enable via env at runtime)
RUN { \
  echo "xdebug.mode=off"; \
  echo "xdebug.start_with_request=trigger"; \
  echo "xdebug.client_host=host.docker.internal"; \
  echo "xdebug.client_port=9003"; \
  echo "xdebug.log_level=0"; \
} > /usr/local/etc/php/conf.d/xdebug.ini

# Healthcheck (php-fpm ping)
HEALTHCHECK --interval=30s --timeout=3s \
  CMD php -v || exit 1
