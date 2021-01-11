#!/bin/bash
set -e

cd map

echo "boroughs..."
php -f get_data.php boroughs

echo "tracts..."
php -f get_data.php tracts

echo "blocks..."
php -f get_data.php blocks

echo "ungeocoded segments..."
php -f get_data.php ungeocoded
