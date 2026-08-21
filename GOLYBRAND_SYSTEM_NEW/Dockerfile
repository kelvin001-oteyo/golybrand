FROM php:8.2-apache

# Install PDO MySQL extension (required for your app)
RUN docker-php-ext-install pdo_mysql

# Copy your application files to the Apache web root
COPY ./ /var/www/html/

# Enable Apache mod_rewrite (for clean URLs if needed)
RUN a2enmod rewrite

# Set permissions
RUN chown -R www-data:www-data /var/www/html
