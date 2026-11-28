FROM dunglas/frankenphp:php8.4

RUN install-php-extensions pdo_mysql

COPY . /app/

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
