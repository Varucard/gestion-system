FROM php:8.4-apache

# produccion (por defecto): php.ini de producción y sin Xdebug.
# desarrollo: php.ini de desarrollo y Xdebug instalado (ENTORNO=desarrollo en .env).
ARG ENTORNO=produccion

# Extensiones necesarias: PDO MySQL, GD (Dompdf), mbstring/zip (Composer)
RUN apt-get update && apt-get install --yes --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libonig-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip mbstring \
    && if [ "$ENTORNO" = "desarrollo" ]; then pecl install xdebug && docker-php-ext-enable xdebug; fi \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Solo la carpeta public/ queda expuesta por Apache; el resto del código no es accesible por web.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && a2enmod rewrite headers \
    && if [ "$ENTORNO" = "desarrollo" ]; then ini=development; else ini=production; fi \
    && cp "$PHP_INI_DIR/php.ini-$ini" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]

LABEL description="Servicio Técnico - PHP 8.4 + Apache + PDO MySQL + GD + Composer (+ Xdebug en desarrollo)"
