FROM php:8.1-apache

# Install PDO MySQL driver extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache Mod Rewrite for clean routing
RUN a2enmod rewrite

# Copy project files to Apache web server root
COPY . /var/www/html/

# Set owner permissions for web server
RUN chown -R www-data:www-data /var/www/html/

# Expose port 80 for web traffic
EXPOSE 80
