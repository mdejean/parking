parking/map
================

A map showing the amount of curbside parking space in New York City.

## See it online

https://marcel.dejean.nyc/parking/map/

## Run it locally

1. Install the dependencies
2. Add the user who will be running the script to postgres `createuser -d myusername`
3. Create the template database `sudo -U postgres -f postgis_template.sql`
4. Get the data and put it in `import/`
5. Run `./import.sh`
6. Run `./update_map.sh`
7. Run `php -S localhost:8080`
8. Go to `http://localhost:8080/map/` in your browser

## Data

These datasets must be unzipped and placed in import/

 * [locations.csv](https://www1.nyc.gov/html/dot/downloads/ParkReg/locations.csv)
 * [signs.csv](https://www1.nyc.gov/html/dot/downloads/ParkReg/signs.csv)
 * [Parking_Regulation_Shapefile/](https://www1.nyc.gov/html/dot/downloads/ParkReg/Parking_Regulation_Shapefile.zip)
 * [lion/](https://www1.nyc.gov/site/planning/data-maps/open-data/dwn-lion.page) (street centerlines)
 * [nybb.shp Borough boundaries (clipped to shoreline)](https://www1.nyc.gov/site/planning/data-maps/open-data/districts-download-metadata.page)
 * [nyct2010wi.shp Census tracts (water included)](https://www1.nyc.gov/assets/planning/download/zip/data-maps/open-data/nyct2010wi_20d.zip)
 * [nycb2010wi.shp Census blocks (water included)](https://www1.nyc.gov/assets/planning/download/zip/data-maps/open-data/nycb2010wi_20d.zip)
 * [DEPHydrants/ Fire hydrants](https://data.cityofnewyork.us/api/geospatial/6pui-xhxz?method=export&format=Original)

## Dependencies

 * PHP >=7.0
 * postgresql >=11.0
 * postgis >=3.0
 * gdal-bin (for ogr2ogr)
 * [Geosupport Desktop Edition](https://www1.nyc.gov/site/planning/data-maps/open-data/dwn-gdelx-request.page)
 * gcc

## License

Except as otherwise noted, this software is distributed under the terms of the GNU General Public License, version 2 or later. See LICENSE for the full text. Alternate licensing is available.

Parts based on jamesbursa/analyze-nyc-parking-signs
