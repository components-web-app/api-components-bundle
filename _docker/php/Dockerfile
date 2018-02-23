FROM composer:1.5
FROM php:7.2-fpm-alpine

RUN apk add --no-cache --virtual .persistent-deps \
		git \
		icu-libs \
		zlib

ENV APCU_VERSION 5.1.9

RUN set -xe \
	&& apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		icu-dev \
		zlib-dev \
	&& apk add --no-cache \
		freetype-dev \
		libpng-dev \
		libjpeg-turbo-dev \
		rabbitmq-c-dev \
		libssh-dev \
		libxml2-dev autoconf g++ imagemagick-dev libtool make \
	&& docker-php-ext-install \
		exif \
    bcmath \
    sockets \
  && pecl install \
    apcu-${APCU_VERSION} \
    amqp \
    imagick \
  && docker-php-ext-enable --ini-name 20-apcu.ini apcu \
  && docker-php-ext-enable --ini-name 05-opcache.ini opcache \
  && docker-php-ext-enable amqp \
  && docker-php-ext-enable imagick \
	&& docker-php-ext-install \
		pdo pdo_mysql \
		intl zip \
	&& docker-php-ext-configure gd \
    --with-gd \
	  --with-freetype-dir=/usr/include/ \
	  --with-png-dir=/usr/include/ \
	  --with-jpeg-dir=/usr/include/ \
	&& docker-php-ext-install -j$(getconf _NPROCESSORS_ONLN) gd \
	&& apk del .build-deps

RUN pecl install xdebug \
  && docker-php-ext-enable xdebug

COPY --from=0 /usr/bin/composer /usr/bin/composer
COPY _docker/php/php.ini /usr/local/etc/php/php.ini
COPY _docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR /srv
ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER 1
RUN composer global require "hirak/prestissimo:^0.3" --prefer-dist --no-progress --no-suggest --classmap-authoritative  --no-interaction

# Prevent the reinstallation of vendors at every changes in the source code
COPY ./composer.json composer.lock ./
RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress --no-suggest \
	&& composer clear-cache

COPY ./ ./

RUN mkdir -p tests/app/var/cache tests/app/var/logs tests/app/var/sessions \
  && composer dump-autoload --classmap-authoritative --no-dev \
	  && chown -R www-data tests/app/var
