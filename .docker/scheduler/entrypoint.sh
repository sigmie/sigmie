#!/bin/bash

# Run scheduler
while [ true ]
do
  php /var/www/app/artisan schedule:run --verbose --no-interaction
  sleep 60
done
