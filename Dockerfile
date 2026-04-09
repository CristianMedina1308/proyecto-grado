FROM php:8.2-apache

# Instalar extensiones necesarias
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite (opcional pero recomendado)
RUN a2enmod rewrite

# Copiar proyecto
COPY . /var/www/html/

# Permisos (evita errores)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80