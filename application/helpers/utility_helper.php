<?php
function debug($obj) {
	echo '<pre>';
	var_dump($obj);
	echo '</pre>';
}

//Database singleton
function collection($key = FALSE) {
	$APP =& get_instance();
	if (!isset($APP->db)) {
		$APP->connection = new MongoClient(DBCONN);
		// Select a database
		$a = DBNAME;
		$APP->db = $APP->connection->$a;
	}
	return $key ? $APP->db->$key : $APP->db;
}

function next_id($name) {
	$ret = collection('counters')->findAndModify(
		array('_id' => $name . '_id'),
		array('$inc' => array('seq' => 1)),
		null,
		array('new' => true)
	);

	return $ret['seq'];
}

//Loads a file inside models/helpers folder
function helper($name) {
	$APP =& get_instance();
	$model_name = 'helper_' . strtolower($name);
	if (!isset($APP->$model_name)) {
		$APP->load->model('helpers/' . $model_name);
	}
	return $APP->$model_name;
}

//Unique id
function newid($str = '') {
	return $str . rand(0,9) . uniqid(rand(0,999));
}

function newintid()
{
	return time();
}

//Adds the saxon genitive
function add_s($username = '') {
	return substr($username, -1) == 's' ? $username . "'" : $username . "'s";
}

function date_display($timestamp, $prefix = '') {
	$d = date('d/m/Y', $timestamp);
	if ($d == date('d/m/Y')) {
		return 'Today';
	} else if(date('dmy', $timestamp-86400) == date('dmy', time()-86400)) {
		return 'Yesterday';
	}
	return $prefix.$d;
}

function made_on($date) {
	$t = date('Ymd', $date);
	if ($t == date('Ymd')) {
		return date('H:i', $date);
	} else {
		return date('M d', $date);
	}
}

//Displays "x minutes ago" such as Facebook timeline
function time_ago($date, $prefix = 'added ', $granularity=1) {
	$difference = time() - $date;
	$retval = '';
	$periods = array(
		'year' => 31536000,
		'month' => 2628000,
		'week' => 604800,
		'day' => 86400,
		'hour' => 3600,
		'minute' => 60);
	if ($difference < 60) { // less than 60 seconds ago, let's say "just now"
		$retval = $prefix . "just now";
		return $retval;
	} else {
		foreach ($periods as $key => $value) {
			if ($difference >= $value) {
				$time = floor($difference/$value);
				$difference %= $value;
				$retval .= ($retval ? ' ' : '').$time.' ';
				$retval .= (($time > 1) ? $key.'s' : $key);
				$granularity--;
			}
			if ($granularity <= '0') { break; }
		}

		$tmp = $prefix.$retval;

		switch ($tmp) {
			case '1 day':
				return 'yesterday';

			default:
				return $tmp . ' ago';
		}
	}
}

// Sanitize function
function sanitize_title($title) {
	if (!function_exists('convert_accented_characters')) {
		get_instance()->load->helper('text');
	}
	return url_title(strtolower(convert_accented_characters($title)));
}