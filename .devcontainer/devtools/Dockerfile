FROM ubuntu:18.04

WORKDIR /var/www/app

ENV TZ=Europe/Berlin

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update

RUN apt-get install -y git unzip zip vim wget curl sudo zsh net-tools

RUN apt-get install -y software-properties-common

RUN apt-get install -y default-mysql-client

RUN add-apt-repository ppa:ondrej/php

RUN apt-get install -y php7.2 php7.2-zip php7.2-dom php7.2-intl php7.2-mbstring php7.2-simplexml php7.2-xml php7.2-common php7.2-opcache php7.2-mcrypt php7.2-cli php7.2-gd php7.2-curl php7.2-mysql php7.2-redis php7.2-fpm php7.2-bcmath

RUN apt-get update --fix-missing

RUN apt-get install -y npm

RUN apt-get install -y gnupg2 ca-certificates lsb-release apt-transport-https

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get -y install dirmngr apt-transport-https lsb-release ca-certificates

RUN curl -sL https://deb.nodesource.com/setup_10.x | bash

CMD ["sleep","infinity"]
