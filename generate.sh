#!/bin/bash
set -e

cd map

echo "dump to files..."

php -f get_data.php boroughs
php -f get_data.php tracts
php -f get_data.php blocks
php -f get_data.php ungeocoded
