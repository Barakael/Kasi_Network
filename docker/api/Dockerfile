FROM php:8.4-cli-alpine

# pcntl/posix are required by queue workers for graceful restarts; gd renders the
# PNG QR codes embedded in voucher PDFs; freeradius-utils provides the radclient
# binary the CoA dispatcher shells out to.
RUN apk add --no-cache \
        freeradius-utils \
        git \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-jpeg=no \
    && docker-php-ext-install -j"$(nproc)" bcmath gd pcntl pdo_mysql zip \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY api/composer.json api/composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY api/ ./
RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
