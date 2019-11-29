FROM php:7.2-fpm

# Set working directory
WORKDIR /var/www/app

# Set timezone
RUN rm /etc/localtime && ln -s /usr/share/zoneinfo/Europe/Berlin /etc/localtime && "date"

# Install PDO and MysQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install Redis extension
RUN pecl install -o -f redis && rm -rf /tmp/pear && echo "extension=redis.so" > /usr/local/etc/php/conf.d/docker-php-ext-redis.ini

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Change current user to www-data
USER www-data

# Expose port 9000 and start php-fpm server
EXPOSE 9000

# Bind container to the php-fpm service
CMD ["php-fpm"]
