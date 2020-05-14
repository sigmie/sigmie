FROM debian:buster

ENV DEBIAN_FRONTEND noninteractive

WORKDIR /var/www/app

ENV TZ=Europe/Berlin
# setup the timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# update
RUN apt-get update

# install system dependencies
RUN apt-get install -y git unzip zip vim wget curl nginx supervisor

RUN apt-get install -y apt-transport-https gnupg lsb-release ca-certificates curl
RUN curl -fsSL -o /etc/apt/trusted.gpg.d/php.gpg "https://packages.sury.org/php/apt.gpg"
RUN sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'

# update
RUN apt-get update

# install mysql dependecies
RUN apt-get install -y software-properties-common default-mysql-client

# add the php repository
RUN add-apt-repository ppa:ondrej/php

# install php and its extensions
RUN apt-get install -y php7.4 php7.4-fpm php7.4-zip php7.4-dom php7.4-intl php7.4-mbstring php7.4-simplexml php7.4-xml php7.4-common php7.4-opcache php7.4-cli php7.4-gd php7.4-curl php7.4-mysql php7.4-fpm php7.4-bcmath

# remove apt-cache leftovers
RUN apt-get clean
RUN rm -rf /var/lib/apt/lists/*

# disable default vhost
RUN unlink /etc/nginx/sites-enabled/default && rm -rf /var/www/html

# create php socket folder
RUN mkdir -p /run/php

# copy the application code
COPY . /var/www/app

# copy the nginx configuration
COPY .docker/nginx/conf.d /etc/nginx/conf.d

# copy the supervisor configuration
COPY .docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# install composer and the project dependecies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer validate && composer install --no-dev --optimize-autoloader --no-ansi --no-interaction --no-scripts --no-suggest --no-progress --prefer-dist

# optimize app for production
RUN php artisan cache:clear && php artisan clear-compiled && php artisan optimize && php artisan view:clear && php artisan view:cache && php artisan event:clear && php artisan event:cache

# Remove env file
RUN rm .env

# publish app engine port 8080
EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
