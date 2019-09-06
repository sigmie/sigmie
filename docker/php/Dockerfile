FROM php:7.2-cli

WORKDIR /var/www/app

RUN apt-get update
RUN apt-get install -y wget zip unzip git

# Install composer
RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

CMD [ "sleep", "infinity" ]
