#!/bin/sh
set -e

echo "[entrypoint] Initializing SiPantau NVR..."

# Ensure directories exist and have correct permissions
mkdir -p /var/www/html/recordings
mkdir -p /var/www/html/streams

# Create log files so PHP can read them immediately
touch /var/www/html/error.log
touch /var/www/html/ffmpeg_error.log

# Fix all permissions for web server user
chown -R www-data:www-data /var/www/html/recordings \
                           /var/www/html/streams \
                           /var/www/html/error.log \
                           /var/www/html/ffmpeg_error.log
chmod -R 777 /var/www/html/recordings /var/www/html/streams
chmod 666 /var/www/html/error.log /var/www/html/ffmpeg_error.log

# Find PHP binary path (varies by Docker image)
PHP_BIN=$(which php 2>/dev/null || echo "/usr/local/bin/php")
echo "[entrypoint] PHP binary: $PHP_BIN"

# Auto-start the new High-Precision Python NVR Daemon
(
    sleep 5
    echo "[entrypoint] Starting Python NVR Daemon..."
    python3 /var/www/html/engine/daemon.py >> /var/www/html/ffmpeg_error.log 2>&1
) &

echo "[entrypoint] Starting Apache..."
# Execute the original CMD (Apache)
exec docker-php-entrypoint apache2-foreground
