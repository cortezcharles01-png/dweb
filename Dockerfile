FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN sed -i '/^LoadModule mpm_event/d' /etc/apache2/mods-enabled/*.load 2>/dev/null || true \
    && a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true \
    && a2enmod mpm_prefork 2>/dev/null || true

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
