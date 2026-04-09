FROM php:8.2-cli

# Instalar MySQL driver
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copiar proyecto
COPY . /app

WORKDIR /app

# Railway usa el puerto dinámico
CMD php -S 0.0.0.0:$PORT