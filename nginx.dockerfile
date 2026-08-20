FROM nginx:stable-alpine

COPY ./nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./nginx/default.conf /etc/nginx/conf.d/default.conf

RUN addgroup -g 1000 laravel \
    && adduser -G laravel -g laravel -s /bin/sh -D laravel

RUN mkdir -p /var/www/html \
    && chown -R laravel:laravel /var/www/html

WORKDIR /var/www/html