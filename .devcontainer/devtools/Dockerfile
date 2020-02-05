FROM ubuntu:18.04

WORKDIR /var/www/app

ENV TZ=Europe/Berlin

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update

RUN apt-get install -y git unzip zip vim wget curl sudo zsh net-tools

RUN apt-get install -y software-properties-common

RUN apt-get install -y default-mysql-client

RUN add-apt-repository ppa:ondrej/php

RUN apt-get install -y php7.4 php7.4-zip php7.4-dom php7.4-intl php7.4-mbstring php7.4-simplexml php7.4-xml php7.4-common php7.4-opcache php7.4-cli php7.4-gd php7.4-curl php7.4-mysql php7.4-fpm php7.4-bcmath

RUN apt-get update --fix-missing

RUN apt-get install -y npm

RUN apt-get install -y gnupg2 ca-certificates lsb-release apt-transport-https

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get -y install dirmngr apt-transport-https lsb-release ca-certificates

RUN curl -sL https://deb.nodesource.com/setup_10.x | bash

CMD ["sleep","infinity"]
