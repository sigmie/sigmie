#!/bin/bash

# Run scheduler
while [ true ]
do
  php /var/www/app/artisan queue:work --verbose --tries=3 --timeout=90
done
