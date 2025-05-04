FROM alpine:3.21
LABEL Maintainer="Stoney Eagle <stoney@nomercy.tv>"
LABEL Description="Lightweight container with Nginx 1.26 & PHP 8.4 based on Alpine Linux."
# Setup document root
WORKDIR /var/www/html

# Install packages and remove default server definition
RUN apk add --no-cache \
  curl \
  nginx \
  php84 \
  php84-bcmath \
  php84-ctype \
  php84-curl \
  php84-dom \
  php84-fileinfo \
  php84-fpm \
  php84-ftp \
  php84-gd \
  php84-iconv \
  php84-intl \
  php84-mbstring \
  php84-mysqli \
  php84-opcache \
  php84-openssl \
  php84-pdo_mysql \
  php84-pdo_sqlite \
  php84-pecl-xdebug \
  php84-pdo_pgsql \
  php84-phar \
  php84-session \
  php84-simplexml \
  php84-sqlite3 \
  php84-tokenizer \
  php84-xml \
  php84-xmlreader \
  php84-xmlwriter \
  php84-zip \
  bash \
  curl \
  git \
  htop \
  imagemagick \
  libcurl \
  libwebp-dev \
  libzip-dev \
  nano \
  npm \
  openjpeg \
  openssh-client \
  supervisor \
  texinfo \
  wget \
  yarn \
  zlib

RUN ln -s /usr/bin/php84 /usr/bin/php

# Configure nginx - http
COPY config/nginx.conf /etc/nginx/nginx.conf
# Configure nginx - default server
COPY config/conf.d /etc/nginx/conf.d/

# Configure PHP-FPM
ENV PHP_INI_DIR /etc/php84
COPY config/fpm-pool.conf ${PHP_INI_DIR}/php-fpm.d/www.conf
COPY config/php.ini ${PHP_INI_DIR}/conf.d/custom.ini

# Configure supervisord
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Make sure files/folders needed by the processes are accessable when they run under the nobody user
RUN chown -R nobody:nobody /var/www/html /run /var/lib/nginx /var/log/nginx

# Add application
COPY --chown=nobody ./data/ /var/www/html/

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN composer update
RUN yarn && yarn build

# Expose the port nginx is reachable on
EXPOSE 8080

# Add the start script
COPY ./config/start.sh /start.sh
RUN chmod +x /start.sh

# Switch to use a non-root user from here on
USER nobody

# Use the script as the container's entry point
CMD ["/start.sh"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping || exit 1
