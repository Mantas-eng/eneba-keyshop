FROM php:8.4-cli
WORKDIR /shop
COPY public public
COPY server/*.php server/schema.sql server/
RUN chown -R www-data:www-data server
USER www-data
EXPOSE 8080
CMD ["sh","-c","exec php -S 0.0.0.0:${PORT:-8080} -t public"]
