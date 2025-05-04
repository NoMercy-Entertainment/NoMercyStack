#!/bin/bash
set -e

# Start supervisord
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf &

# Wait for the site to be ready
sleep 10

# Run Laravel commands
php artisan migrate --force
php artisan optimize

# Keep the container running
wait
