<?php

define('MAX_ZOOM_LEVEL', 23);
define('MAX_YANDEX_ZOOM', 18);

/**
 * Clips a number to the specified minimum and maximum values.
 * 
 * @param $n The number to clip.
 * @param $min_value Minimum allowable value.
 * @param $max_value Maximum allowable value.
 * 
 * @return The clipped value.
 */
function clip($n, $min_value, $max_value) {
    return min(max($n, $min_value), $max_value);
}

/**
 * Converts latitude (in degrees) into tile Y coordinate of the tile 
 * (for elliptic Mercator projection) containing the specified point 
 * at a specified level of zoom.
 *
 * @param float $latitude Latitude of the point, in degrees.</param>
 * @param int $zoom Level of detail, from 0 (the whole map as single tile)
 * to 23 (highest detail).
 * 
 * @return int The tile Y coordinate.
 */
function lat_to_tile_y($latitude, $zoom) {
    $latitude = clip($latitude, -85.05112878, 85.05112878);
    $sin_lat = sin(deg2rad($latitude));
    //$y = 0.5 - log((1 + $sin_lat) / (1 - $sin_lat)) / (4 * pi());
    $e = 0.0818191908426; // eccentricity of the Earth
    $y = 0.5 - (atanh($sin_lat) - $e * atanh($e * $sin_lat)) / (2 * pi());
    $size_in_tiles = 1 << $zoom;
    return min((int) ($y * $size_in_tiles), $size_in_tiles - 1);
}

/**
 * Converts longitude (in degrees) into tile X coordinate of the tile 
 * (for elliptic Mercator projection) containing the specified point 
 * at a specified level of zoom.
 *
 * @param float $longitude Longitude of the point, in degrees.</param>
 * @param int $zoom Level of detail, from 0 (the whole map as single tile)
 * to 23 (highest detail).
 * 
 * @return int The tile X coordinate.
 */
function lon_to_tile_x($longitude, $zoom) {
    $longitude = clip($longitude, -180, 180);
    $x = ($longitude + 180) / 360;
    $size_in_tiles = 1 << $zoom;
    return min((int) ($x * $size_in_tiles), $size_in_tiles - 1);
}

/**
 * Converts tile XY coordinates into a QuadKey at a specified level of zoom.
 *
 * @param int $tile_x Tile X coordinate.
 * @param int $tile_y Tile Y coordinate.
 * @param int $zoom Level of detail, from 0 (the whole map as single tile)
 * to 23 (highest detail).
 * 
 * @return string A string containing the QuadKey in binary form.
 */
function tile_to_quadkey($tile_x, $tile_y, $zoom) {
    if ($zoom == 0) {
        return 0;
    }

    $quadkey = '';
    for ($i = 0; $i < $zoom; $i++) {
        $quadkey = ($tile_y & 1) . ($tile_x & 1) . $quadkey;
        $tile_x >>= 1;
        $tile_y >>= 1;
    }
    return $quadkey;
}

/**
 * Converts a point from latitude/longitude WGS-84 coordinates (in degrees)
 * into a QuadKey at a specified level of zoom (using elliptic Mercator
 *  projection).
 *
 * @param float $latitude Latitude of the point, in degrees.
 * @param float $longitude Longitude of the point, in degrees.
 * @param int $zoom Level of detail, from 0 (the whole map as single tile)
 * to 23 (highest detail).

 * @return string A string containing the QuadKey in binary form.
 */
function latlon_to_quadkey($latitude, $longitude, $zoom) {
    if ($zoom == 0) {
        return 0;
    }

    $tile_x = lon_to_tile_x($longitude, $zoom);
    $tile_y = lat_to_tile_y($latitude, $zoom);
    return tile_to_quadkey($tile_x, $tile_y, $zoom);
}

/**
 * Get QuadKeys for the range of tiles.
 * 
 * @param int $tile_x1 X coordinate of the upper left tile.
 * @param int $tile_y1 Y coordinate of the upper left tile.
 * @param int $tile_x2 X coordinate of the bottom right tile
 * @param int $tile_y2 Y coordinate of the bottom right tile
 * @param int $zoom Level of detail
 * @return array Rerutn array of quadkeys.
 */
function get_quadkeys_for_tiles($tile_x1, $tile_y1, $tile_x2, $tile_y2, $zoom) {
    $quadkeys = [];
    for ($j = $tile_y1; $j <= $tile_y2; $j++) {
        for ($i = $tile_x1; $i <= $tile_x2; $i++) {
            $quadkeys[] = tile_to_quadkey($i, $j, $zoom);
        }
    }

    // group subsequent quadkeys
    sort($quadkeys);
    $done = False;
    while (!$done) {
        $done = True;
        for ($i = 0; $i < count($quadkeys) - 1; $i++) {
            $parent = substr($quadkeys[$i], 0, strlen($quadkeys[$i]) - 1);
            if ($quadkeys[$i + 1] == $parent . '1') {
                $quadkeys[$i] = $parent;
                array_splice($quadkeys, $i + 1, 1);
                $done = False;
            }
        }
    }
    
    return $quadkeys;
}

/**
 * Get clusters for the specified tiles.
 * 
 * @param object $db Object which represents the connection to a MySQL Server.
 * @param int $tile_x1 X coordinate of the upper left tile.
 * @param int $tile_y1 Y coordinate of the upper left tile.
 * @param int $tile_x2 X coordinate of the bottom right tile
 * @param int $tile_y2 Y coordinate of the bottom right tile
 * @param int $zoom Level of detail
 * @return array Rerutn array of clusters: [quadkey => cluster], where cluster
 * is array ['count' => (int), 'lat' => (float), 'lon' => (float)/, bssids => []/]
 */
function get_clusters($db, $tile_x1, $tile_y1, $tile_x2, $tile_y2, $zoom) {
    $quadkeys = get_quadkeys_for_tiles($tile_x1, $tile_y1, $tile_x2, $tile_y2, $zoom);
    
    $clusters = [];
    $group_level = $zoom + 2;
    if ($zoom >= MAX_YANDEX_ZOOM) {
        $fetch_all = True;
    } else {
        $fetch_all = False; 
    }
    foreach ($quadkeys as $quadkey) {
        $clusters += find_clusters_on_quadkey($db, $quadkey, $group_level, $fetch_all);
    }
    return $clusters;
}

/**
 * Query all points in the tile with given QuadKey and group those that fall 
 * in the same tile at the specified zoom level
 * 
 * @param object $db Object which represents the connection to a MySQL Server.
 * @param string $quadkey QuadKey (as binary string) of the tile to search within.
 * @param int $group_level Will group all points in tiles of specified zoom level.
 * @param boolean $fetch_all Retrieve info about all points in clusters.
 * @return array Rerutn array of clusters: [quadkey => cluster], where cluster
 * is array ['count' => (int), 'lat' => (float), 'lon' => (float)/, bssids => []/]
 */
function find_clusters_on_quadkey($db, $quadkey, $group_level, $fetch_all=False) {
    $q1 = bindec(str_pad($quadkey, 2 * MAX_ZOOM_LEVEL, "0"));
    $q2 = bindec(str_pad($quadkey, 2 * MAX_ZOOM_LEVEL, "1"));
    $mask = 2 * (MAX_ZOOM_LEVEL - $group_level);

    $clusters = [];
    if (!$fetch_all) {
        $sql = "SELECT (`quadkey` >> $mask) as `cluster_qk`, BSSID,
                    COUNT(BSSID) AS count, AVG(longitude) AS lon_avg,
                    AVG(latitude) AS lat_avg 
                FROM `geo2` 
                WHERE `quadkey` BETWEEN $q1 AND $q2 
                GROUP BY (`cluster_qk`) ";

        if (($res = $db->query($sql))) {
            foreach ($res as $row) {
                $cluster_qk = base_convert($row['cluster_qk'], 10, 2);
                $cluster_qk = str_pad($cluster_qk, 2*$group_level, "0", STR_PAD_LEFT);
                $clusters[$cluster_qk] = ['count' => $row['count'],
                            'lat' => $row['lat_avg'], 'lon' => $row['lon_avg']];
                if ($row['count'] == 1) {
                    $clusters[$cluster_qk]['bssids'] = [$row['BSSID']];
                }
            }
        }
    } else { // fetch all appropriate records and group them manually
        $sql = "SELECT (`quadkey` >> $mask) as `cluster_qk`, BSSID, longitude, latitude 
                FROM `geo2` 
                WHERE `quadkey` BETWEEN $q1 AND $q2";
        
        if (($res = $db->query($sql))) {
            foreach ($res as $row) {
                $cluster_qk = base_convert($row['cluster_qk'], 10, 2);
                $cluster_qk = str_pad($cluster_qk, 2*$group_level, "0", STR_PAD_LEFT);
                if(empty($clusters[$cluster_qk])) {
                    $clusters[$cluster_qk] = ['count' => 0, 'lat' => 0.0, 'lon' => 0.0, 'bssids'=>[]];
                }
                $count = ++$clusters[$cluster_qk]['count'];
                $clusters[$cluster_qk]['lat'] = 
                        ($clusters[$cluster_qk]['lat'] * ($count - 1) + $row['latitude']) / $count;
                $clusters[$cluster_qk]['lon'] = 
                        ($clusters[$cluster_qk]['lon'] * ($count - 1) + $row['longitude']) / $count;
                $clusters[$cluster_qk]['bssids'][] = $row['BSSID'];
            }
        }
    }
    return $clusters;
}
