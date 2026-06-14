FROM php:8.1-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite \
    sqlite-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite bcmath gd xml

# Configure PHP-FPM
RUN echo "cgi.fix_pathinfo=0" > /usr/local/etc/php/conf.d/pathinfo.ini

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Setup SQLite Database
RUN mkdir -p database && touch database/database.sqlite

# Directory permissions
RUN chmod -R 777 storage bootstrap/cache database

# Copy Nginx & Supervisor Configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose Port 80
EXPOSE 80

# Build optimizations
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
