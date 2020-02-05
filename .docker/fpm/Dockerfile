FROM php:7.4-fpm

# Set working directory
WORKDIR /var/www/app

# Set timezone
RUN rm /etc/localtime && ln -s /usr/share/zoneinfo/Europe/Berlin /etc/localtime && "date"

# Update
RUN apt-get update

# Install additional dependencies
RUN apt-get update && apt-get install -y zlib1g-dev libicu-dev g++

# Install php extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli bcmath intl

# Install Redis extension
RUN pecl install -o -f redis && rm -rf /tmp/pear && echo "extension=redis.so" > /usr/local/etc/php/conf.d/docker-php-ext-redis.ini

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Add user for laravel application
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy the application files
COPY . /var/www/app

# Copy existing application directory permissions
COPY --chown=www:www . /var/www/app

# Optimize for production
RUN php artisan clear-compiled && php artisan optimize
RUN php artisan view:clear && php artisan view:cache
RUN php artisan event:clear && php artisan event:cache

# Change current user to www
USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000

# Bind container to the php-fpm service
CMD ["php-fpm"]
