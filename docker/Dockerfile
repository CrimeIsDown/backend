FROM php:7-fpm-alpine

# Update all the Alpine dependencies
RUN apk --update upgrade \
 && rm -rf /var/cache/apk/*

# Install runtime dependencies
RUN apk add --no-cache bash ffmpeg xz fcgi libzip \
 && update-ca-certificates

# Install extension building dependencies
RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    automake \
    make \
    gcc \
    g++ \
    libzip-dev \
    zlib-dev

# Build and install our extensions (some other ones we need are already compiled in, so those aren't listed)
RUN docker-php-ext-install \
    bcmath \
    mysqli \
    opcache \
    pcntl \
    pdo_mysql \
    sockets \
    zip \
 && pecl install apcu redis \
 && docker-php-ext-enable apcu bcmath redis opcache

# Uninstall build deps now that we have completed installing extensions
RUN apk del .build-deps

# Configure PHP
COPY docker/php.ini /usr/local/etc/php/
