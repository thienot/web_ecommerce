FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libgd-dev \
    libxpm-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    build-essential \
    default-mysql-client \
    dos2unix \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure GD with WebP support - order matters!
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-xpm

# Install GD extension first
RUN docker-php-ext-install gd

# Install other extensions
RUN docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath zip xml

# Verify GD installation and WebP support
RUN php -m | grep -i gd
RUN php -r "var_dump(gd_info());"
RUN php -r "if(function_exists('imagewebp')) { echo 'WebP support: YES\n'; } else { echo 'WebP support: NO\n'; }"

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and npm
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get update && \
    apt-get install -y nodejs && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Create system user
RUN useradd -G www-data,root -u 1000 -d /home/laravel laravel
RUN mkdir -p /home/laravel/.composer && \
    chown -R laravel:laravel /home/laravel

# Set working directory
WORKDIR /var/www/html

# Copy entrypoint script and set permissions
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN dos2unix /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

# Set proper ownership
RUN chown -R laravel:laravel /var/www/html

EXPOSE 9000

# Use entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]