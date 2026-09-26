FROM php:8.2-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

WORKDIR /var/www/html

# Composer files first so dependency install is cached unless they change.
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --no-interaction --optimize-autoloader

COPY . .

# content/ and data/ are meant to be mounted as volumes; make sure they exist
# even on a fresh checkout, and are writable by the user Apache runs as.
RUN mkdir -p content data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R u+rwX,g+rwX /var/www/html/content /var/www/html/data

EXPOSE 80
