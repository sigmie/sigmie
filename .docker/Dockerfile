FROM php:7.4-fpm AS base

# Set working directory
WORKDIR /var/www/app

# disable dpkg interactive-mode
ENV DEBIAN_FRONTEND noninteractive

# Set timezone
RUN rm /etc/localtime && ln -s /usr/share/zoneinfo/Europe/Berlin /etc/localtime && "date"

# Install intl necessary packages
RUN apt-get update && apt-get install -y zlib1g-dev libicu-dev g++ apt-utils

# Install php extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli bcmath intl

# Install Redis extension
RUN pecl install -o -f redis && rm -rf /tmp/pear && echo "extension=redis.so" > /usr/local/etc/php/conf.d/docker-php-ext-redis.ini

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Add user for laravel application
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

# Copy the application files
COPY . /var/www/app

# Copy existing application directory permissions
COPY --chown=www:www . /var/www/app

# Expose port 9000 and start php-fpm server
EXPOSE 9000

# Bind container to the php-fpm service
CMD ["php-fpm"]


# Development stage
FROM base as development

# Install enable and configure xDebug
RUN pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    echo "error_reporting = E_ALL"                 >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "display_startup_errors = On"             >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "display_errors = On"                     >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_enable=1"                  >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_autostart=1"               >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_port=9001"                 >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_host=docker.host.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_connect_back=0"            >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Change current user to www
USER www


# Production stage
FROM base AS production

# Change the home dir of the www user
RUN usermod -d /var/www/app www

# Running as www user optimize the app for production
RUN su - www -c "php artisan clear-compiled && php artisan optimize && php artisan view:clear && php artisan view:cache && php artisan event:clear && php artisan event:cache"

# Remove env file
RUN rm .env

# Change current user to www
USER www


# Entrypoint stage
FROM production as entrypoint

# Copy the entrypoint file into the image
COPY entrypoint.sh /opt/bin/entrypoint.sh

# Override the default fpm command
CMD ["/opt/bin/entrypoint.sh"]
