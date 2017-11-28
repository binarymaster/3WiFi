<?php
/**
 * Clips a number to the specified minimum and maximum values.
 * 
 * @param $n The number to clip.
 * @param $min_value Minimum allowable value.
 * @param $max_value Maximum allowable value.
 * 
 * @return The clipped value.
 */
function clip($n, $min_value, $max_value)
{
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
function lat_to_tile_y($latitude, $zoom)
{
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
function lon_to_tile_x($longitude, $zoom)
{
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
function tile_to_quadkey($tile_x, $tile_y, $zoom)
{
	if ($zoom == 0) return 0;

	$quadkey = '';
	for ($i = 0; $i < $zoom; $i++)
	{
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
function latlon_to_quadkey($latitude, $longitude, $zoom)
{
	if ($zoom == 0) return 0;

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
function get_quadkeys_for_tiles($tile_x1, $tile_y1, $tile_x2, $tile_y2, $zoom)
{
	$quadkeys = array();
	for ($j = $tile_y1; $j <= $tile_y2; $j++)
	{
		for ($i = $tile_x1; $i <= $tile_x2; $i++)
		{
			$quadkeys[] = tile_to_quadkey($i, $j, $zoom);
		}
	}

	// group subsequent quadkeys
	sort($quadkeys, SORT_STRING);
	$done = false;
	while (!$done)
	{
		$done = true;
		for ($i = 0; $i < count($quadkeys) - 1; $i++)
		{
			$parent = substr($quadkeys[$i], 0, strlen($quadkeys[$i]) - 1);
			if ($quadkeys[$i + 1] === $parent . '1')
			{
				$quadkeys[$i] = $parent;
				array_splice($quadkeys, $i + 1, 1);
				$done = false;
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
 * @param bool $scatter Don't clusterize on large zoom
 * @return array Rerutn array of clusters: [quadkey => cluster], where cluster
 * is array ['count' => (int), 'lat' => (float), 'lon' => (float)/, bssids => []/]
 */
function get_clusters($db, $tile_x1, $tile_y1, $tile_x2, $tile_y2, $zoom, $scatter)
{
	$quadkeys = get_quadkeys_for_tiles($tile_x1, $tile_y1, $tile_x2, $tile_y2, $zoom);

	$clusters = array();
	if ($scatter && $zoom >= MAX_YANDEX_ZOOM - 1)
	{
		$group_level = MAX_ZOOM_LEVEL;
		$fetch_all = true;
	}
	else
	{
		$group_level = $zoom + 2;
		$fetch_all = $zoom >= MAX_YANDEX_ZOOM;
	}
	foreach ($quadkeys as $quadkey)
	{
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
function find_clusters_on_quadkey($db, $quadkey, $group_level, $fetch_all=false)
{
	$q1 = base_convert(str_pad($quadkey, 2 * MAX_ZOOM_LEVEL, "0"), 2, 10);
	$q2 = base_convert(str_pad($quadkey, 2 * MAX_ZOOM_LEVEL, "1"), 2, 10);
	$mask = 2 * (MAX_ZOOM_LEVEL - $group_level);
	$geo = (TRY_USE_MEMORY_TABLES ? GEO_MEM_TABLE : GEO_TABLE);

	$clusters = array();
	if (!$fetch_all)
	{
		$sql = "SELECT (`quadkey` >> $mask) as `cluster_qk`, BSSID,
					COUNT(BSSID) AS count, AVG(longitude) AS lon_avg,
					AVG(latitude) AS lat_avg 
				FROM $geo
				WHERE `quadkey` BETWEEN $q1 AND $q2 
				GROUP BY (`cluster_qk`) ";

		if (($res = $db->query($sql)))
		{
			while ($row = $res->fetch_assoc())
			{
				$cluster_qk = base_convert($row['cluster_qk'], 10, 2);
				$cluster_qk = str_pad($cluster_qk, 2*$group_level, "0", STR_PAD_LEFT);
				$clusters[$cluster_qk] = array('count' => $row['count'],
							'lat' => $row['lat_avg'], 'lon' => $row['lon_avg']);
				if ($row['count'] == 1)
				{
					$clusters[$cluster_qk]['bssids'] = array($row['BSSID']);
				}
			}
		}
	}
	else
	{ // fetch all appropriate records and group them manually
		$sql = "SELECT (`quadkey` >> $mask) as `cluster_qk`, BSSID, longitude, latitude 
				FROM $geo
				WHERE `quadkey` BETWEEN $q1 AND $q2";

		if (($res = $db->query($sql)))
		{
			while ($row = $res->fetch_assoc())
			{
				$cluster_qk = base_convert($row['cluster_qk'], 10, 2);
				$cluster_qk = str_pad($cluster_qk, 2*$group_level, "0", STR_PAD_LEFT);
				if(empty($clusters[$cluster_qk]))
				{
					$clusters[$cluster_qk] = array('count' => 0, 'lat' => 0.0, 'lon' => 0.0, 'bssids'=>array());
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

/**
* Converts (using elliptic Mercator projection) tile Y coordinate into latitude
* (in degrees) of the left upper corner of the tile at a specified level of zoom.
*
* @param int The tile Y coordinate.
* @param int $zoom Level of detail, from 0 (the whole map as single tile)
* to 23 (highest detail).
*
* @return float Latitude of the tile left upper corner, in degrees.
*/
function tile_y_to_lat($tile_y, $zoom)
{
	$eps = 1e-7; // precision
	$e = 0.0818191908426; // eccentricity of the Earth

	$y = pi() * (1 - 2 * $tile_y / (1 << $zoom)); //  -pi <= $y <= pi
	$sign = ($y < 0 ? -1 : 1);
	$y *= $sign;
	$lat_n1 = atan(sinh($y));
	do
	{
		$lat_n = $lat_n1;
		$sin_lat = sin($lat_n);
		$lat_n1 = asin(1 - (1 + $sin_lat) * pow((1 - $e * $sin_lat) / (1 + $e * $sin_lat), $e) / exp(2 * $y));
		$abs = abs($lat_n1 - $lat_n);
	} while($abs > $eps && !is_nan($abs));
	return rad2deg($sign * $lat_n1);
}

/**
* Converts (using elliptic Mercator projection) tile X coordinate into longitude
* (in degrees) of the left upper corner of the tile at a specified level of zoom.
*
* @param int The tile X coordinate.
* @param int $zoom Level of detail, from 0 (the whole map as single tile)
* to 23 (highest detail).
*
* @return float Longitude of the tile left upper corner, in degrees.
*/
function tile_x_to_lon($tile_x, $zoom)
{
	return rad2deg(pi() * (2 * $tile_x / (1 << $zoom) - 1));
}

/**
* Converts QuadKey into tile XY coordinates.
*
* @param string $quadkey A string containing the QuadKey in binary form.
* @param int& $tile_x [out] Tile X coordinate.
* @param int& $tile_y [out] Tile Y coordinate.
*/
function quadkey_to_tile($quadkey, &$tile_x, &$tile_y)
{
	$tile_x = 0;
	$tile_y = 0;
	if ($quadkey === '0') return;

	$len = strlen($quadkey);
	$quadkey = str_split($quadkey);
	for ($i = 0; $i < $len; $i += 2)
	{
		$tile_y = ($tile_y << 1) | $quadkey[$i];
	}
	for ($i = 1; $i < $len; $i += 2)
	{
		$tile_x = ($tile_x << 1) | $quadkey[$i];
	}
}

/**
* Get bbox for the tile identified via QuadKey.
*
* @param string $quadkey A string containing the QuadKey in binary form.
*
* @return array [[lat1, lon1], [lat2, lon2]].
*/
function get_tile_bbox($quadkey)
{
	$tile_x = 0;
	$tile_y = 0;
	quadkey_to_tile($quadkey, $tile_x, $tile_y);

	$zoom = (int) (strlen($quadkey) / 2);

	$lat1 = tile_y_to_lat($tile_y + 1, $zoom);
	$lat2 = tile_y_to_lat($tile_y, $zoom);
	$lon1 = tile_x_to_lon($tile_x, $zoom);
	$lon2 = tile_x_to_lon($tile_x + 1, $zoom);

	return array(array($lat1, $lon1), array($lat2, $lon2));
}

function query_radius_ids($db, $lat, $lon, $radius)
{
	$lat_km = 111.143 - 0.562 * cos(2 * deg2rad($lat));
	$lon_km = abs(111.321 * cos(deg2rad($lon)) - 0.094 * cos(3 * deg2rad($lon)));
	$lat1 = min(max($lat - $radius / $lat_km, -90), 90);
	$lat2 = min(max($lat + $radius / $lat_km, -90), 90);
	$lon1 = min(max($lon - $radius / $lon_km, -180), 180);
	$lon2 = min(max($lon + $radius / $lon_km, -180), 180);
	$tile_x1 = lon_to_tile_x($lon1, 7);
	$tile_y1 = lat_to_tile_y($lat2, 7);
	$tile_x2 = lon_to_tile_x($lon2, 7);
	$tile_y2 = lat_to_tile_y($lat1, 7);
	$quadkeys = get_quadkeys_for_tiles($tile_x1, $tile_y1, $tile_x2, $tile_y2, 7);
	$quadkeys = '(' . implode(',', array_map(function($x){return base_convert($x, 2, 10);}, $quadkeys)) . ')';

	$res = QuerySql(
		"CREATE TEMPORARY TABLE IF NOT EXISTS radius_ids AS (SELECT id 
		FROM `BASE_TABLE`, `GEO_TABLE` 
		WHERE (`GEO_TABLE`.`quadkey` >> 32) IN $quadkeys AND
				`BASE_TABLE`.`BSSID` = `GEO_TABLE`.`BSSID` 
				AND (`GEO_TABLE`.`quadkey` IS NOT NULL) 
				AND (`GEO_TABLE`.`latitude` BETWEEN $lat1 AND $lat2 AND `GEO_TABLE`.`longitude` BETWEEN $lon1 AND $lon2)
		)
	");
	return $res !== false;
}
