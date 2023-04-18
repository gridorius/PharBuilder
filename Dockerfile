FROM php:7.4-cli
COPY . /usr/src/builder
COPY php.ini /usr/local/etc/php
WORKDIR /usr/src/myapp
ENTRYPOINT php /usr/src/builder/index.php /usr/src/builder