FROM debian:buster AS prod

# avoid errro message
ENV DEBIAN_FRONTEND noninteractive

# app directory
WORKDIR /var/www/app

# time zone
ENV TZ=UTC

# setup the timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# create php socket folder
RUN mkdir -p /run/php && chown www-data:www-data /run/php

# update
RUN apt-get update

# install system dependencies
RUN apt-get install -y git unzip zip vim wget curl nginx supervisor curl

RUN apt -y install lsb-release apt-transport-https ca-certificates && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list

# update
RUN apt-get update

# install mysql dependecies
RUN apt-get install -y software-properties-common default-mysql-client

# add sury packages to source list
RUN apt install apt-transport-https lsb-release ca-certificates wget -y && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' && \
    apt-get update

# install php and its extensions
RUN apt-get install -y php8.0 php8.0-fpm php8.0-zip php8.0-dom php8.0-intl php8.0-mbstring php8.0-simplexml php8.0-xml php8.0-common php8.0-opcache php8.0-cli php8.0-gd php8.0-curl php8.0-mysql  php8.0-bcmath php8.0-redis php8.0-sqlite3

# remove apt-cache leftovers
RUN apt-get autoremove -y && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# disable default vhost
RUN unlink /etc/nginx/sites-enabled/default && rm -rf /var/www/html

# copy the application code
COPY . /var/www/app

# copy the nginx configuration
COPY .docker/nginx/conf.d /etc/nginx/conf.d

# copy the supervisor configuration
COPY .docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# copy the supervisor kill script into container's path
COPY .docker/supervisor/stop-supervisor /bin

# copy the scheduler script into container's path
COPY .docker/supervisor/scheduler /bin

# Assign app folder ownership to www-data and set permissions
RUN chown -R www-data:www-data /var/www/app && \
    find /var/www/app -type f -exec chmod 644 {} \; && \
    find /var/www/app -type d -exec chmod 755 {} \;

# install composer and the project dependecies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer validate && composer install --no-dev --optimize-autoloader --no-ansi --no-interaction --no-scripts --no-suggest --no-progress --prefer-dist

# publish app engine port 8080
EXPOSE 8080

# production command
CMD php artisan cache:clear         && \
    php artisan clear-compiled      && \
    php artisan migrate --force     && \
    php artisan optimize            && \
    php artisan view:cache          && \
    php artisan event:cache         && \
    rm .env                         && \
    rm auth.json                    && \
    /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
