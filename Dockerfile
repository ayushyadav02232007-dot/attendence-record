FROM php:8.2-apache

# Install SQLite extensions inside the container
RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Copy your local PHP files into the container's web directory
COPY . /var/www/html/

# Expose port 80 for web traffic
EXPOSE 80
