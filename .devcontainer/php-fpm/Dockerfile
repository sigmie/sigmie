FROM php:7.3-fpm

# Set working directory
WORKDIR /var/www/app

# Install dependencies
RUN apt-get update && apt-get install -y \
    unzip
 
# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version
 
# Set timezone
RUN rm /etc/localtime \
    && ln -s /usr/share/zoneinfo/Europe/Warsaw /etc/localtime \
    && "date"
 
# Install PDO
RUN apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql
 
RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng-dev \
        vim \
    && docker-php-ext-install -j$(nproc) iconv bcmath \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd
 
RUN apt-get install -y zlib1g-dev libicu-dev g++ && \
    docker-php-ext-configure intl && \
    docker-php-ext-install intl zip && \
    apt-get purge -y g++
 
RUN apt-get install -y libxslt-dev

RUN docker-php-ext-install xsl soap mysqli

RUN pecl install -o -f redis \
    && rm -rf /tmp/pear \
    && echo "extension=redis.so" > /usr/local/etc/php/conf.d/docker-php-ext-redis.ini

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql

# Install extensions
RUN docker-php-ext-install mbstring zip exif pcntl
RUN docker-php-ext-configure gd --with-gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ --with-png-dir=/usr/include/
RUN docker-php-ext-install gd

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add user for laravel application
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy existing application directory permissions
COPY --chown=www:www . /var/www/app

# Change current user to www
USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]