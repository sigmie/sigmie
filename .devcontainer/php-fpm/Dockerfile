FROM php:7.4-fpm

# Set working directory
WORKDIR /var/www/app

# Set timezone
RUN rm /etc/localtime && ln -s /usr/share/zoneinfo/Europe/Berlin /etc/localtime && "date"

# Update
RUN apt-get update

# Install php extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli bcmath

RUN apt-get update && apt-get install -y zlib1g-dev libicu-dev g++
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl

# Install Redis extension
RUN pecl install -o -f redis && rm -rf /tmp/pear && echo "extension=redis.so" > /usr/local/etc/php/conf.d/docker-php-ext-redis.ini

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy the application files
COPY . /var/www/app

# Bind container to the php-fpm service
CMD ["php-fpm"]
