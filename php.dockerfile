FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    bcmath \
    gd \
    intl \
    mbstring \
    opcache \
    pcntl \
    pdo \
    pdo_mysql \
    zip

COPY ./php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN groupadd -g 1000 laravel \
    && useradd -u 1000 -g laravel -m -s /bin/bash laravel

RUN mkdir -p /var/www/html \
    && chown -R laravel:laravel /var/www/html

WORKDIR /var/www/html