<?php
require_once 'utils.php';

function getMainStats($db, $useloc = false)
{
	$result = array();
	//$json['stat']['total'] = GetStatsValue(STATS_BASE_ROWN_NUMS);
	$sql = 'SELECT COUNT(id) FROM BASE_TABLE';
	if ($useloc) $sql = 'SELECT COUNT(id) FROM radius_ids';
	if ($res = QuerySql($sql))
	{
		$row = $res->fetch_row();
		$result['total'] = (int)$row[0];
		$res->close();
	}
	$sql = 'SELECT COUNT(BSSID) FROM GEO_TABLE WHERE (`quadkey` IS NOT NULL)';
	if ($useloc) $sql = 'SELECT COUNT(id) FROM radius_ids';
	if ($res = QuerySql($sql))
	{
		$row = $res->fetch_row();
		$result['onmap'] = (int)$row[0];
		$res->close();
	}
	if (count(array_keys($result)) == 0)
		return false;
	date_default_timezone_set('UTC');
	$result['date'] = date('Y.m.d H:i:s');
	return $result;
}

function getExtStats($db, $useloc = false)
{
	$result = array();
	$sql = 'SELECT COUNT(id) FROM BASE_TABLE WHERE NoBSSID = 0';
	if ($useloc) $sql = 'SELECT COUNT(id) FROM radius_ids';
	if ($res = QuerySql($sql))
	{
		$row = $res->fetch_row();
		$result['bssids'] = (int)$row[0];
		$res->close();
	}
	$sql = 'SELECT COUNT(BSSID) FROM GEO_TABLE';
	if ($useloc) $sql = 'SELECT COUNT(DISTINCT BSSID) FROM BASE_TABLE JOIN radius_ids USING(id)';
	if ($res = QuerySql($sql))
	{
		$row = $res->fetch_row();
		$result['uniqbss'] = (int)$row[0];
		$res->close();
	}
	if (count(array_keys($result)) == 0)
		return false;
	return $result;
}

function getRealtimeStats($db)
{
	$result = array();
	if ($res = QuerySql('SELECT COUNT(BSSID) FROM GEO_TABLE WHERE latitude IS NULL'))
	{
		$row = $res->fetch_row();
		$result['geoloc'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query('SELECT COUNT(tid) FROM tasks WHERE tstate = 0'))
	{
		$row = $res->fetch_row();
		$result['tasks']['uploading'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query('SELECT COUNT(tid) FROM tasks WHERE tstate > 0 AND tstate < 3'))
	{
		$row = $res->fetch_row();
		$result['tasks']['processing'] = (int)$row[0];
		$res->close();
	}
	if ($res = $db->query('SELECT comment FROM tasks WHERE tstate > 0 AND tstate < 3 ORDER BY created LIMIT 1'))
	{
		$row = $res->fetch_row();
		$result['tasks']['comment'] = $row[0];
		$res->close();
	}
	if (count(array_keys($result)) == 0)
		return false;
	return $result;
}

function getLoads($db, $useloc = false)
{
	$result = array();
	date_default_timezone_set('UTC');
	$sql = 'SELECT DATE_FORMAT(time,\'%Y.%m.%d\'), COUNT(id) FROM BASE_TABLE ';
	if ($useloc)
		$sql .= 'JOIN radius_ids USING(id) ';
	$sql .= 'GROUP BY DATE_FORMAT(time,\'%Y%m%d\') ORDER BY id DESC LIMIT 30';
	if ($res = QuerySql($sql))
	{
		while ($row = $res->fetch_row())
			$result[] = array($row[0], (int)$row[1]);
		$res->close();
	}
	else
		return false;
	$result = array_reverse($result);
	return $result;
}

function getComments($db, $useloc = false)
{
	$result = array();
	$sql = 'SELECT `cmtid`, COUNT(cmtid) FROM BASE_TABLE ';
	if ($useloc)
		$sql .= 'JOIN radius_ids USING(id) ';
	$sql .= 'GROUP BY `cmtid` HAVING COUNT(cmtid) > 1 ORDER BY COUNT(cmtid) DESC';
	if ($res = QuerySql($sql))
	{
		$result['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = ($row[0] == null ? 'no comment' : getCommentVal((int)$row[0]));
			$result['data'][] = $data;
		}
		$res->close();
	}
	else
		return false;
	date_default_timezone_set('UTC');
	$result['date'] = date('Y.m.d H:i:s');
	return $result;
}

function getUsers($db, $top, $useloc = false)
{
	$result = array();
	$result['top'] = $top;
	$sql = 'SELECT COUNT(uid) FROM users';
	if ($useloc) $sql = 'SELECT COUNT(DISTINCT uid) FROM uploads JOIN radius_ids USING(id)';
	if ($res = $db->query($sql))
	{
		$row = $res->fetch_row();
		$result['total'] = (int)$row[0];
		$res->close();
	}
	else
		return false;
	$sql = "SELECT nick, COUNT(id) FROM uploads ";
	if ($useloc)
		$sql .= "JOIN radius_ids USING(id) ";
	$sql .= "LEFT JOIN users USING(uid) GROUP BY uploads.uid ORDER BY COUNT(id) DESC LIMIT $top";
	if ($res = $db->query($sql))
	{
		$result['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = $row[0];
			$result['data'][] = $data;
		}
		$res->close();
	}
	else
		return false;
	date_default_timezone_set('UTC');
	$result['date'] = date('Y.m.d H:i:s');
	return $result;
}

function getCountStats($db, $col, $top, $useloc = false, $where = array(), $postfunc = null)
{
	$result = array();
	$result['top'] = $top;
	$where[] = "IFNULL(`$col`, '') != ''";
	$where = implode(' AND ', $where);
	$sql = "SELECT COUNT(DISTINCT `$col`) FROM BASE_TABLE ";
	if ($useloc)
		$sql .= "JOIN radius_ids USING(id) ";
	$sql .= "WHERE $where";
	if ($res = QuerySql($sql))
	{
		$row = $res->fetch_row();
		$result['total'] = (int)$row[0];
		$res->close();
	}
	else
		return false;
	$sql = "SELECT `$col`, COUNT($col) FROM BASE_TABLE ";
	if ($useloc)
		$sql .= "JOIN radius_ids USING(id) ";
	$sql .= "WHERE $where GROUP BY `$col` HAVING COUNT($col) > 1 ORDER BY COUNT($col) DESC LIMIT $top";
	if ($res = QuerySql($sql))
	{
		$result['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = ($postfunc == null ? $row[0] : $postfunc($row[0]));
			$result['data'][] = $data;
		}
		$res->close();
	}
	else
		return false;
	date_default_timezone_set('UTC');
	$result['date'] = date('Y.m.d H:i:s');
	return $result;
}

function getMultiStats($db, $cols, $top, $useloc = false, $where = array(), $postfunc = null)
{
	$result = array();
	$result['top'] = $top;
	$from = array();
	foreach ($cols as $i => $col)
	{
		$whr = array_merge($where, array("IFNULL(`$col`, '') != ''"));
		$whr = implode(' AND ', $whr);
		$whr = str_replace('$col', $col, $whr);
		$sql = "SELECT $col AS {$cols[0]} FROM BASE_TABLE ";
		if ($useloc)
		{
			// MySQL cannot refer to a TEMPORARY table more than once in the same query.
			if ($i > 0) QuerySql("CREATE TEMPORARY TABLE radius_ids$i SELECT * FROM radius_ids");
			$ii = ($i > 0 ? (string)$i : '');
			$sql .= "JOIN radius_ids$ii USING(id) ";
		}
		$sql .= "WHERE $whr";
		$from[] = $sql;
	}
	$from = implode(' UNION ALL ', $from);
	if ($res = QuerySql("SELECT COUNT(DISTINCT `{$cols[0]}`) FROM ($from) TmpTable"))
	{
		$row = $res->fetch_row();
		$result['total'] = (int)$row[0];
		$res->close();
	}
	else
		return false;
	if ($res = QuerySql("SELECT `{$cols[0]}`, COUNT({$cols[0]}) FROM ($from) TmpTable GROUP BY `{$cols[0]}` HAVING COUNT({$cols[0]}) > 1 ORDER BY COUNT({$cols[0]}) DESC LIMIT $top"))
	{
		$result['data'] = array();
		while ($row = $res->fetch_row())
		{
			$data = array();
			$data[] = (int)$row[1];
			$data[] = ($postfunc == null ? $row[0] : $postfunc($row[0]));
			$result['data'][] = $data;
		}
		$res->close();
	}
	else
		return false;
	date_default_timezone_set('UTC');
	$result['date'] = date('Y.m.d H:i:s');
	return $result;
}
?>