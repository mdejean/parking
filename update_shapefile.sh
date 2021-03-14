#!/bin/bash

cd parking_shapefile

pgsql2shp -f parking parking "`cat parking_shapefile.sql`"

zip parking.zip parking.* parking_shapefile.md