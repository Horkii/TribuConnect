FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev libpq-dev \
 && docker-php-ext-install intl opcache pdo pdo_pgsql \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Apache vhost
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Entrypoint
COPY docker/php/docker-entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# App source (for PaaS builds; overridden by bind mount in dev compose)
COPY . /var/www/html

EXPOSE 80
CMD ["/usr/local/bin/app-entrypoint"]
