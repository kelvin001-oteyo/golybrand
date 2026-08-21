FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy your entire project to Apache's root directory
COPY ./GOLYBRAND_SYSTEM_NEW/ /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
