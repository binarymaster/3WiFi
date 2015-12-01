<?php

/**

  3WiFi Script for add `quadkey` column to geo tables

 * */
set_time_limit(0);
require 'db.php';
require 'quadkey.php';

db_connect();

foreach (['geo', 'mem_geo'] as $geo_table) {
    $sql = "ALTER TABLE `$geo_table`
            ADD COLUMN `quadkey` BIGINT(20) UNSIGNED DEFAULT NULL,
            DROP INDEX `Coords`,
            ADD INDEX (`quadkey`)";
    var_dump($sql);
    if (!$db->query($sql)) {
        echo "Failed to alter table $geo_table: ";
        echo "(" . $db->errno . ") " . $db->error;
    }
    
    $coord_res = QuerySql(
            "SELECT * FROM $geo_table  
             WHERE `latitude` != 0 AND `longitude` != 0 
                AND `latitude` IS NOT NULL AND `longitude` IS NOT NULL
                AND `quadkey` IS NULL");
    if (!$coord_res) {
        echo "Failed to select from $geo_table: ";
        echo "(" . $db->errno . ") " . $db->error;
        exit();
    }
    if (!($stmt = $db->prepare("UPDATE $geo_table SET `quadkey`=? WHERE `BSSID`=?"))) {
        echo "Failed to prepare query: (" . $db->errno . ") " . $db->error;
        exit();
    }
    $quadkey = '';
    $bssid = '';
    if (!$stmt->bind_param("ss", $quadkey, $bssid)) {
        echo "Failed to bind params: (" . $stmt->errno . ") " . $stmt->error;
        exit();
    }
    while ($coord_row = $coord_res->fetch_row()) {
        $bssid = $coord_row[0];
        $quadkey = base_convert(
                latlon_to_quadkey($coord_row[1], $coord_row[2], MAX_ZOOM_LEVEL),
                2,
                10);
        $stmt->execute();
    }
    $stmt->close();
}

