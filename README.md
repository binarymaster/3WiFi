# 3WiFi Database

(c) 2015 Anton Kokarev

This project was created to collect data from Router Scan log reports, search for access points, obtain its geolocation coordinates, and display it on world map.

## Installation steps:
1. Copy all required files to your `/www` directory
2. Create database (execute `3wifi.sql` to create tables)
3. (optional) Turn on memory tables (in the `db.php` define `TRY_USE_MEMORY_TABLES` as `true`)
4. (optional) Use `import.free.php` once to import old format database
5. Start all background daemons:
```sh
# Upload routine loads data into database
php -f 3wifid.php uploads
# Finalize routine prepares tasks for finalization
php -f 3wifid.php finalize
# Geolocate routine locates new added BSSIDs on map
php -f 3wifid.php geolocate
```
Before running the daemons, make sure that `php-cli` interpreter is accessible from your directory.
