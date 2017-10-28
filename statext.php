<?php
require_once 'utils.php';

function getMainStats($db)
{
	$result = array();
	//$json['stat']['total'] = GetStatsValue(STATS_BASE_ROWN_NUMS);
	if ($res = QuerySql('SELECT COUNT(id) FROM BASE_TABLE'))
	{
		$row = $res->fetch_row();
		$result['total'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql('SELECT COUNT(BSSID) FROM GEO_TABLE WHERE (`quadkey` IS NOT NULL)'))
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

function getExtStats($db)
{
	$result = array();
	if ($res = QuerySql('SELECT COUNT(id) FROM BASE_TABLE WHERE NoBSSID = 0'))
	{
		$row = $res->fetch_row();
		$result['bssids'] = (int)$row[0];
		$res->close();
	}
	if ($res = QuerySql('SELECT COUNT(BSSID) FROM GEO_TABLE'))
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

function getLoads($db)
{
	$result = array();
	date_default_timezone_set('UTC');
	if ($res = QuerySql('SELECT DATE_FORMAT(time,\'%Y.%m.%d\'), COUNT(id) FROM BASE_TABLE GROUP BY DATE_FORMAT(time,\'%Y%m%d\') ORDER BY id DESC LIMIT 30'))
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

function getComments($db)
{
	$result = array();
	if ($res = QuerySql('SELECT `cmtid`, COUNT(cmtid) FROM BASE_TABLE GROUP BY `cmtid` ORDER BY COUNT(cmtid) DESC'))
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

function getUsers($db, $top)
{
	$result = array();
	$result['top'] = $top;
	if ($res = $db->query('SELECT COUNT(uid) FROM users'))
	{
		$row = $res->fetch_row();
		$result['total'] = (int)$row[0];
		$res->close();
	}
	else
		return false;
	if ($res = $db->query("SELECT nick, COUNT(id) FROM uploads LEFT JOIN users USING(uid) GROUP BY uploads.uid ORDER BY COUNT(id) DESC LIMIT $top"))
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

function getCountStats($db, $col, $top, $where = array(), $postfunc = null)
{
	$result = array();
	$result['top'] = $top;
	$where[] = "IFNULL(`$col`, '') != ''";
	$where = implode(' AND ', $where);
	if ($res = QuerySql("SELECT COUNT(DISTINCT `$col`) FROM BASE_TABLE WHERE $where"))
	{
		$row = $res->fetch_row();
		$result['total'] = (int)$row[0];
		$res->close();
	}
	else
		return false;
	if ($res = QuerySql("SELECT `$col`, COUNT($col) FROM BASE_TABLE WHERE $where GROUP BY `$col` ORDER BY COUNT($col) DESC LIMIT $top"))
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

function getMultiStats($db, $cols, $top, $where = array(), $postfunc = null)
{
	$result = array();
	$result['top'] = $top;
	$from = array();
	foreach ($cols as $col)
	{
		$whr = array_merge($where, array("IFNULL(`$col`, '') != ''"));
		$whr = implode(' AND ', $whr);
		$whr = str_replace('$col', $col, $whr);
		$from[] = "SELECT $col AS {$cols[0]} FROM BASE_TABLE WHERE $whr";
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
	if ($res = QuerySql("SELECT `{$cols[0]}`, COUNT({$cols[0]}) FROM ($from) TmpTable GROUP BY `{$cols[0]}` ORDER BY COUNT({$cols[0]}) DESC LIMIT $top"))
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