FROM php:8.4-apache-bookworm
ARG BUILD=local
LABEL org.opencontainers.image.revision=$BUILD

RUN apt-get update && apt-get install -y --no-install-recommends \
      libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd dom opcache \
    && a2enmod rewrite headers remoteip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/site.ini
COPY docker/entrypoint.sh /usr/local/bin/atour-entrypoint
RUN mkdir -p /var/lib/php/sessions && chown www-data:www-data /var/lib/php/sessions

ENV AT_STANDALONE=1 BUILD=$BUILD
EXPOSE 80
HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=6 \
  CMD php -r '$r=@file_get_contents("http://127.0.0.1/healthz.php"); exit($r===false?1:0);'
ENTRYPOINT ["bash", "/usr/local/bin/atour-entrypoint"]
CMD ["apache2-foreground"]
