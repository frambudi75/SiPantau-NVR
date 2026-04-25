FROM php:8.2-apache

# Install dependencies: FFmpeg, cron (for watchdog), procps (for ps command), curl (for healthcheck)
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libmariadb-dev \
    cron \
    procps \
    curl \
    && docker-php-ext-install pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Copy entrypoint script
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# The rest of the files will be mounted via docker-compose for development
ENTRYPOINT ["entrypoint.sh"]
