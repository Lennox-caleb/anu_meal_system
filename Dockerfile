FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Allow .htaccess overrides in web root
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Copy project into web root
COPY . /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html/

# Railway sets $PORT dynamically — configure Apache to use it
CMD bash -c "\
  sed -i \"s/Listen 80/Listen ${PORT:-80}/g\" /etc/apache2/ports.conf && \
  sed -i \"s/:80>/:${PORT:-80}>/g\" /etc/apache2/sites-available/000-default.conf && \
  apache2-foreground"