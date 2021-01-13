# Install docker engine
FROM debian:buster

# avoid errro message
ENV DEBIAN_FRONTEND noninteractive

# app directory
WORKDIR /var/www/app

# time zone
ENV TZ=UTC

# setup timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# update
RUN apt-get update

# install system dependencies
RUN apt-get install -y git unzip zip vim wget curl software-properties-common default-mysql-client

# add sury packages to source list
RUN apt install apt-transport-https lsb-release ca-certificates wget -y && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' && \
    apt-get update

# install php and its extensions
RUN apt-get install -y php8.0 php8.0-fpm php8.0-zip php8.0-dom php8.0-intl php8.0-mbstring php8.0-simplexml php8.0-xml php8.0-common php8.0-opcache php8.0-cli php8.0-gd php8.0-curl php8.0-mysql php8.0-fpm php8.0-bcmath php8.0-redis php8.0-sqlite3

# remove apt-cache leftovers
RUN apt-get autoremove -y && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# copy app folders
COPY . /var/www/poll-ops

# install composer and the project dependecies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composercurl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer validate && composer install --no-dev --optimize-autoloader --no-ansi --no-interaction --no-scripts --no-suggest --no-progress --prefer-dist

# command
CMD sleep infinity
