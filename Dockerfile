FROM php:8.3-apache

# unzip lets Composer extract packages; pdo_mysql for the app, bcmath for money math.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql bcmath \
    && a2enmod rewrite

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-bella-vista.ini
COPY docker/apache/bella-vista.conf /etc/apache2/conf-available/bella-vista.conf
RUN a2enconf bella-vista

ENV TZ=Asia/Manila

# Development image: the project is bind-mounted at /var/www/html by docker-compose.yml.
