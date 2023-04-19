FROM php:7.4-cli
COPY . /usr/src/builder
COPY php.ini /usr/local/etc/php
WORKDIR /usr/src/builder
ENTRYPOINT php -r "include 'Builder.php'; (new PharBuilder\Builder('/usr/src/builder'))->build();"