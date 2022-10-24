FROM php:7.4-cli
COPY . /usr/src/builder
WORKDIR /usr/scr/myapp
CMD [ "php", "/usr/src/builder/index.php", '.']