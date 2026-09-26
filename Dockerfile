FROM php:8.2-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# league/commonmark requires ext-mbstring, which isn't compiled in by default
# and needs the Oniguruma headers to build. libonig-dev is left installed
# (not purged) since mbstring.so links against its runtime library, and
# apt's --auto-remove would otherwise cascade-remove that too.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

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
