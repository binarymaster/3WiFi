# 3WiFi Database

(c) 2015-2022 Anton Kokarev et al.

This project was created to collect data from Router Scan log reports, search for access points, obtain its geolocation coordinates, and display it on world map.

## Prerequirements:
1. Disable display of errors, warnings, and notices in `php.ini`
1. Make sure your web server is set up to apply .htaccess policies per directory
1. Make sure you have installed `bcmath`, `curl`, `mysqli`, and `simplexml` php extensions

## Installation steps:
1. Copy all required files to your `/www` directory
1. Create database (execute `3wifi.sql` to create tables)
1. Copy config.php-distr to config.php
1. Edit config.php (DB_SERV, DB_NAME, DB_USER, DB_PASS etc)
1. (optional) Turn on memory tables (in the `config.php` define `TRY_USE_MEMORY_TABLES` as `true`)
1. (optional) Use `import.free.php` once to import old format database
1. Start all background daemons:
```sh
# Upload routine loads data into database
php -f 3wifid.php uploads
# Finalize routine prepares tasks for finalization
php -f 3wifid.php finalize
# Geolocate routine locates new added BSSIDs on map
php -f 3wifid.php geolocate
# Stats routine caches statistics (use only when stats caching enabled)
php -f 3wifid.php stats
# Memory table manager (use only with memory tables enabled)
php -f 3wifid.php memory
```

## Database maintenance:
```sh
# Recheck not found BSSIDs in the database
php -f 3wifid.php recheck
```
Before running the daemons, make sure that `php-cli` interpreter is accessible from your directory.
