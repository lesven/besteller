FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    netcat-traditional \
    autoconf \
    gcc \
    make \
    pkg-config \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy entrypoint script
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Install Xdebug (PECL) so code coverage is available for tests
## Xdebug für Code-Coverage installieren
RUN pecl channel-update pecl.php.net && pecl install xdebug && docker-php-ext-enable xdebug
# Copy xdebug configuration (mode off, trigger start)
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
# Ensure the zend_extension is explicitly set to the installed xdebug.so
RUN php -r "echo ini_get('extension_dir');" > /tmp/extension_dir && \
    EXT_DIR=$(cat /tmp/extension_dir) && \
    printf 'zend_extension=%s/xdebug.so\n' "$EXT_DIR" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
