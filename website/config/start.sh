#!/bin/bash

# Start supervisord and services
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

composer install
yarn
yarn build

