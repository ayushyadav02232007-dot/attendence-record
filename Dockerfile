FROM php:8.2-apache

# Install SQLite extensions inside the container
RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Copy your local PHP files into the container's web directory
COPY . /var/www/html/

# Create the data directory if it doesn't exist
RUN mkdir -p /var/www/html/data

# Grant full read/write permissions to the web server user (www-data) for the data folder
RUN chown -R www-data:www-data /var/www/html/data && chmod -R 777 /var/www/html/data

# Expose port 80 for web traffic
EXPOSE 80
