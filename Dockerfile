FROM php:8.2-cli

RUN docker-php-ext-install pdo pdo_mysql

COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/www/html"]
