#!/bin/bash
# Clear all Laravel caches for fresh data

echo "Clearing Laravel caches..."

# Clear application cache
php artisan cache:clear

# Clear route cache
php artisan route:clear

# Clear config cache
php artisan config:clear

# Clear view cache
php artisan view:clear

# Clear compiled classes
php artisan clear-compiled

# Optimize for production
php artisan optimize:clear

echo "All caches cleared successfully!"
echo "Please refresh your browser with Ctrl+Shift+R (or Cmd+Shift+R on Mac)"
