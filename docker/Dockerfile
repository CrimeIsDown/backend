FROM jrottenberg/ffmpeg:4.3-ubuntu1804 AS ffmpeg

FROM php:7-fpm

# Update all the OS dependencies
RUN apt-get update \
 && apt-get upgrade -y

# Install runtime dependencies
RUN apt-get install -y bash xz-utils libzip4 libexpat1 \
 && update-ca-certificates

# Build and install our extensions (some other ones we need are already compiled in, so those aren't listed)
RUN docker-php-ext-install \
    bcmath \
    mysqli \
    opcache \
    pcntl \
    pdo_mysql \
    sockets \
 && pecl install apcu redis \
 && docker-php-ext-enable apcu redis opcache

# Configure PHP
COPY docker/php.ini /usr/local/etc/php/

# Install ffmpeg
COPY --from=ffmpeg /usr/local /usr/local

ENV LD_LIBRARY_PATH=/usr/local/lib:$LD_LIBRARY_PATH
