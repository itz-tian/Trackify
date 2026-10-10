#!/bin/sh
set -e

# Default to 80 if PORT is not set
PORT="${PORT:-80}"

# Replace Apache port dynamically at runtime
sed -i "s/Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
