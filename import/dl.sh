#!/bin/bash

# download files for parking map
# this script is fragile and will break in 3 months time

wget 'https://www1.nyc.gov/html/dot/downloads/ParkReg/locations.csv'
wget 'https://www1.nyc.gov/html/dot/downloads/ParkReg/signs.csv'

wget 'https://www1.nyc.gov/html/dot/downloads/ParkReg/Parking_Regulation_Shapefile.zip'
unzip -o Parking_Regulation_Shapefile.zip

wget --content-disposition --trust-server-names 'https://www1.nyc.gov/assets/planning/download/zip/data-maps/open-data/nyclion_20d.zip'
unzip -o nyclion_20d.zip

wget 'https://www1.nyc.gov/assets/planning/download/zip/data-maps/open-data/nybb_20d.zip'
unzip -oj nybb_20d.zip

wget 'https://www1.nyc.gov/assets/planning/download/zip/data-maps/open-data/nyct2010wi_20d.zip'
unzip -oj nyct2010wi_20d.zip

wget 'https://www1.nyc.gov/assets/planning/download/zip/data-maps/open-data/nycb2010wi_20d.zip'
unzip -oj nycb2010wi_20d.zip

wget --content-disposition --trust-server-names 'https://data.cityofnewyork.us/api/geospatial/6pui-xhxz?method=export&format=Original'
unzip -o DEPHydrants.zip
