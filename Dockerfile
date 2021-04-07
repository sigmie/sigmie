FROM debian:buster AS prod

LABEL maintainer="Nico Orfanos"

# avoid errro message
ENV DEBIAN_FRONTEND noninteractive

# app directory
WORKDIR /var/www/app

# time zone
ENV TZ=UTC

# setup the timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# update
RUN apt-get update && \
    apt-get install -y git unzip zip vim wget curl nginx supervisor curl sudo && \
    apt -y install lsb-release apt-transport-https ca-certificates software-properties-common default-mysql-client libcurl4-openssl-dev && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list && \
    apt-get update && \
    apt-get install -y php8.0 php8.0-dev php8.0-fpm php8.0-zip php8.0-dom php8.0-intl php8.0-mbstring php8.0-simplexml php8.0-xml php8.0-common php8.0-opcache php8.0-cli php8.0-gd php8.0-curl php8.0-mysql  php8.0-bcmath php8.0-redis php8.0-sqlite3 && \
    apt-get autoremove -y && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# Install swoole
RUN pecl install swoole

# disable default vhost
RUN unlink /etc/nginx/sites-enabled/default && rm -rf /var/www/html

# copy the application code
COPY . /var/www/app

# copy the nginx configuration
COPY .docker/nginx/conf.d /etc/nginx/conf.d

# copy the supervisor configuration
COPY .docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# copy the supervisor kill script into container's path
COPY .docker/supervisor/stop-supervisor /usr/local/bin

# copy the scheduler script into container's path
COPY .docker/supervisor/scheduler /usr/local/bin

COPY .docker/php/php.ini /etc/php/8.0/cli/conf.d/99-sigmie.ini

# Create sigmie user and hadle permissions
RUN adduser --disabled-password --gecos '' sigmie && \
    adduser sigmie sudo && \
    echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers && \
    chown -R sigmie:www-data /var/www/app && \
    find /var/www/app -type f -exec chmod 664 {} \; && \
    find /var/www/app -type d -exec chmod 775 {} \; && \
    mkdir -p /run/php && chown sigmie:sigmie /run/php

# publish app engine port 8080
EXPOSE 8080

# set web as default user
USER sigmie

# install composer
RUN curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# production command
CMD composer validate && \
    composer install --no-dev --optimize-autoloader --no-interaction --no-suggest --no-progress --prefer-dist && \
    php artisan cache:clear         && \
    php artisan clear-compiled      && \
    php artisan migrate --force     && \
    php artisan optimize            && \
    php artisan view:cache          && \
    php artisan event:cache         && \
    rm .env                         && \
    rm auth.json                    && \
    sudo /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

