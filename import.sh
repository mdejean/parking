#!/bin/bash
set -e

gcc -Wall -Werror geosupport/blockface.c -L/opt/geosupport/lib/ -lgeo -lapequiv -ledequiv -lsan -lsnd -lstExcpt -lStdLast -lStdUniv -lstEnder -lstretch -lthined -lm -lc -lgcc_s -ldl -o blockface

psql postgres -c "drop database if exists parking;"
createdb parking 

psql -v ON_ERROR_STOP=1 -f postgis.sql parking
psql -v ON_ERROR_STOP=1 -f multiline_functions.sql parking

echo "import tables..."

ogr2ogr -f "PostgreSQL" PG:"dbname=parking" -overwrite -nln location -oo AUTODETECT_TYPE=YES -oo EMPTY_STRING_AS_NULL=YES import/locations.csv
iconv -f latin1 -t utf-8 import/signs.csv | ogr2ogr -f "PostgreSQL" PG:"dbname=parking" -overwrite -nln import_sign -oo AUTODETECT_TYPE=YES -oo EMPTY_STRING_AS_NULL=YES CSV:/vsistdin/
ogr2ogr import/lion.shp import/lion/lion.gdb lion
shp2pgsql -I -D -s 2263 import/lion.shp street_segment | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 2263 import/nybb.shp borough | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 2263 import/nyct2010wi.shp census_tract | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 2263 import/nycb2010wi.shp census_block | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 4326 -m import/columns.txt import/Parking_Regulation_Shapefile/Parking_Regulation_Shapefile.shp parking_regulation | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 2263 import/NYC_Hydrants/NYCDEP_Hydrants.shp hydrant | psql -v ON_ERROR_STOP=1 parking

psql -v ON_ERROR_STOP=1 -f import.sql parking

echo "calculate blockface geometry..."

psql -v ON_ERROR_STOP=1 -f blockface_geom.sql parking

echo "hydrant positions..."

psql -v ON_ERROR_STOP=1 -f blockface_hydrant.sql parking

echo "map order_no to blockfaces..."

psql -v ON_ERROR_STOP=1 -f order_segment.sql parking
php -f index.php order_segment

echo "interpret signs..."

psql -v ON_ERROR_STOP=1 -f supersedes.sql parking
psql -v ON_ERROR_STOP=1 -f sign_regulation.sql parking
php -f index.php interpret_signs

echo "calculate parking spaces..."

psql -v ON_ERROR_STOP=1 -f parking.sql parking
php -f index.php parking
psql -v ON_ERROR_STOP=1 -f spaces.sql parking
