#!/bin/bash
set -e

gcc -Wall -Werror -L/opt/geosupport/lib/ -lgeo -lapequiv -ledequiv -lsan -lsnd -lstExcpt -lStdLast -lStdUniv -lstEnder -lstretch -lthined -lm -lc -lgcc_s -ldl blockface.c -o blockface

psql postgres -c "drop database if exists parking;"
createdb parking 

psql -v ON_ERROR_STOP=1 -f postgis.sql parking

csvsql -e windows-1252 --db "postgresql:///parking" --tables location --insert --overwrite --chunk-size 5000 import/locations.csv
csvsql -e windows-1252 --db "postgresql:///parking" --tables import_sign --insert --overwrite --chunk-size 5000 import/signs.csv
ogr2ogr import/lion.shp import/lion/lion.gdb lion
shp2pgsql -I -D -s 102718 import/lion.shp street_segment | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 102718 import/nybb.shp borough | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 102718 import/nyct2010wi.shp census_tract | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 102718 import/nycb2010wi.shp census_block | psql -v ON_ERROR_STOP=1 parking
shp2pgsql -I -D -s 4326 -m columns.txt import/Parking_Regulation_Shapefile/Parking_Regulation_Shapefile.shp parking_regulation | psql -v ON_ERROR_STOP=1 parking

psql -v ON_ERROR_STOP=1 -f blockface_geom.sql parking

psql -v ON_ERROR_STOP=1 -f sign.sql parking
psql -v ON_ERROR_STOP=1 -f supersedes.sql parking

psql -v ON_ERROR_STOP=1 -f order_segment.sql parking
php -f index.php order_segment

psql -v ON_ERROR_STOP=1 -f sign_regulation.sql parking
php -f index.php interpret_signs

psql -v ON_ERROR_STOP=1 -f parking.sql parking
php -f index.php parking

php -f index.php boroughs
php -f index.php tracts
php -f index.php blocks