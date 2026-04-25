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

# Install cron if available, set up watchdog to run every 2 minutes
if command -v crontab > /dev/null 2>&1; then
    echo "*/2 * * * * $PHP_BIN /var/www/html/api/watchdog.php >> /var/www/html/ffmpeg_error.log 2>&1" | crontab -
    cron 2>/dev/null || true
    echo "[entrypoint] Cron watchdog installed."
fi

# Auto-start streams on container boot (run in background after Apache starts)
(
    sleep 15
    echo "[entrypoint] Auto-starting camera streams..."
    $PHP_BIN /var/www/html/api/watchdog.php >> /var/www/html/ffmpeg_error.log 2>&1 || true
    echo "[entrypoint] Initial stream sync done."
) &

echo "[entrypoint] Starting Apache..."
# Execute the original CMD (Apache)
exec docker-php-entrypoint apache2-foreground
