FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    mysqli \
    gd \
    intl \
    zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install dependencies (ignoring scripts for now to avoid db connection errors during build)
RUN composer install --no-interaction --no-scripts --prefer-dist

# Create necessary SilverStripe directories and set permissions
RUN mkdir -p silverstripe-cache assets \
    && chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Default command
CMD ["apache2-foreground"]
