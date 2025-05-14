FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    curl \
    git \
    unzip \
    && docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd zip \
    && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install -j$(nproc) gd

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN a2enmod rewrite
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html