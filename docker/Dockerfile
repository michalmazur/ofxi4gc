FROM php:8.2.3-cli
COPY --from=composer:2.5.4 /usr/bin/composer /usr/local/bin/composer
RUN pecl install xdebug && docker-php-ext-enable xdebug
RUN apt-get update && apt-get install -y unzip
