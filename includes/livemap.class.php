<?php

/*
	module simplexml (php-xml)
	depends Group, MySQL
*/

class Livemap {
	
	protected function __construct() {}
	protected function __clone() {}
	
	const TTMOD_VERSION = 1.3;
	const MAX_UPLOAD_SIZE = 8388608;
	const LOGIN_COOKIE_DAYS = 14;
	const POI_ICON_PATH = "images/poi";
	
	public static $id;
	public static $db;
	public static $group;
	public static $config;
	public static $redirect;
	
	public static function configure( &$config, MySQL $db ) {
		self::$db = $db;
		self::$id = intval($config['ID']);
		self::$config   = $config;
		self::$language = $config['language'];
		self::$redirect = "index.php?livemap_id=" . self::$id;
		date_default_timezone_set($config['timezone']);
	}
	
	public static function get_public_config() {
		$protected_keys = ['table_c', 'table_l', 'table_g', 'table_s', 'table_d', 'path', 'server_id', 'admin_pass', 'db_ip', 'db_port', 'db_user', 'db_pass', 'db_name'];
		return array_diff_key(self::$config, array_flip($protected_keys));
	}


	# Privilege Groups
	
	public static function get_group_data( $gid ) {
		$query = sprintf("SELECT * FROM %s WHERE livemap_id = '%u' AND ID = '%u'", self::$config['table_g'], self::$id, $gid);
		return self::$db->query($query, FALSE);
	}
	
	public static function get_group( $gid ) {
		$data = self::get_group_data($gid);
		return self::get_group_from_dataset($data);
	}
	
	public static function get_visitor_group() {
		$query = sprintf("SELECT * FROM %s WHERE livemap_id = '%u' AND type_id = 1", self::$config['table_g'], self::$id);
		$data  = self::$db->query($query, FALSE);
		return new Group($data['ID'], $data['name'], $data['type_id'], $data['privileges']);
	}
	
	public static function get_groups_array() {
		$query = sprintf("SELECT * FROM %s WHERE livemap_id = '%u'", self::$config['table_g'], self::$id);
		return self::$db->query($query);
	}
	
	public static function get_group_from_dataset( $data ) {
		if( ! $data || ! is_array($data) ) return FALSE;
		$group = new Group($data['ID'], $data['name'], $data['type_id'], $data['privileges']);
		if( strlen($data['password']) ) $group->password  = $data['password'];
		if( $data['steamlogin']       ) $group->steam_ids = explode(",", $data['members_csv']);
		return $group;
	}
	
	public static function create_group( $name ) {
		$query = sprintf("INSERT INTO %s ( livemap_id, name ) VALUES ('%u', '%s')", self::$config['table_g'], self::$id, $name);
		return self::$db->query($query);
	}
	
	public static function delete_group( $id ) {
		$query = sprintf("DELETE FROM %s WHERE livemap_id = '%u' AND ID = '%u'", self::$config['table_g'], self::$id, $id);
		return self::$db->query($query);
	}
	
	
	# Auth & Session
	
	public static function set_steam_id( $id ) {
		$_SESSION['LIVEMAP_STEAM'] = $id;
	}
	
	public static function get_steam_id() {
		if( isSet($_SESSION['LIVEMAP_STEAM']) ) return $_SESSION['LIVEMAP_STEAM'];
		else return FALSE;
	}
	
	public static function login_group( $id ) {
		$group = $id === 0 ? new Group($id, 'Admin') : self::get_group($id);
		$_SESSION['LIVEMAP_INFO'][self::$id]['LOGGED_IN'] = TRUE;
		$_SESSION['LIVEMAP_INFO'][self::$id]['AUTHGROUP'] = serialize($group);
	}
	
	public static function session_auth() {
		$session =& $_SESSION['LIVEMAP_INFO'][self::$id];
		isset($session['LOGGED_IN']) || $session['LOGGED_IN'] = FALSE;
		if( isset($session['AUTHGROUP']) ) {
			self::$group = unserialize($session['AUTHGROUP']);
		} else {
			self::$group = self::get_visitor_group();
			$session['AUTHGROUP'] = serialize(self::$group);
		}
	}
	
	public static function cookie_auth() {
		if( self::get_steam_id() !== FALSE ) return FALSE;
		if( ! isset($_COOKIE['LIVEMAP_TOKEN']) ) return FALSE;
		$cookie  = unserialize($_COOKIE['LIVEMAP_TOKEN']);
		$steamid = self::$db->esc($cookie[0]);
		$token	 = self::$db->esc($cookie[1]);
		$query = sprintf("SELECT * FROM %s WHERE SteamID = '%s' AND Token = '%s' AND Expires > NOW()", self::$config['table_s'], $steamid, $token);
		$rs = self::$db->query($query);
		if( empty($rs) ) return FALSE;
		self::set_steam_id($steamid);
		// Privilege assignment
		foreach( self::get_groups_array() AS $group ) {
			$steam_id_list = explode(",", $group['members_csv']);
			if( in_array($steamid, $steam_id_list) ) self::login_group($group['ID']);
		}
		return TRUE;
	}
	
	public static function set_auth_cookie() {
		$steamid = self::get_steam_id();
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$length = strlen($characters);
		$token = '';
		for( $i = 0; $i < 32; $i++ ) $token .= $characters[mt_rand(0, $length - 1)];
		// Save token to database
		$query = sprintf(
			"INSERT INTO %s (SteamID, Token, Expires) VALUES ('%s', '%s', DATE_ADD(NOW(), INTERVAL %u DAY))",
			self::$config['table_s'], $steamid, $token, Livemap::LOGIN_COOKIE_DAYS
		);
		self::$db->query($query);
		// Clear expired cookies from database
		self::$db->query("DELETE FROM " . self::$config['table_s'] . " WHERE Expires < NOW()");
		// Save cookie to client device
		setcookie('LIVEMAP_TOKEN', serialize([$steamid, $token]), time()+3600*24*Livemap::LOGIN_COOKIE_DAYS);	
	}

	public static function clear_session() {
		unset($_SESSION['LIVEMAP_INFO'][self::$id]);
		unset($_SESSION['LIVEMAP_STEAM']);
	}
	
	public static function clear_cookie() {
		unset($_COOKIE['LIVEMAP_TOKEN']);
		setcookie('LIVEMAP_TOKEN', null, -1, '/'); 
	}

	# Font business
	
	public static $fonts = array(
		[ 'name' => 'Alice', 'import' => 'https://fonts.googleapis.com/css?family=Alice' ],
		[ 'name' => 'Calibri', 'import' => FALSE ],
		[ 'name' => 'Gentium Book Baisc', 'import' => 'https://fonts.googleapis.com/css?family=Gentium+Book+Basic' ],
		[ 'name' => 'Georgia', 'import' => FALSE ],
		[ 'name' => 'IM Fell English SC', 'import' => 'https://fonts.googleapis.com/css?family=IM+Fell+English+SC' ],
		[ 'name' => 'Kurale', 'import' => 'https://fonts.googleapis.com/css?family=Kurale' ],
		[ 'name' => 'Libre Baskerville', 'import' => 'https://fonts.googleapis.com/css?family=Libre+Baskerville' ],
		[ 'name' => 'Lore', 'import' => 'https://fonts.googleapis.com/css?family=Lora' ],
		[ 'name' => 'Marko One', 'import' => 'https://fonts.googleapis.com/css?family=Marko+One' ],
		[ 'name' => 'Mate SC', 'import' => 'https://fonts.googleapis.com/css?family=Mate+SC' ],
		[ 'name' => 'Oleo Script', 'import' => 'https://fonts.googleapis.com/css?family=Oleo+Script' ],
		[ 'name' => 'Palatino Linotype', 'import' => FALSE ],
		[ 'name' => 'Source Sans Pro', 'import' => 'https://fonts.googleapis.com/css?family=Source+Sans+Pro' ],
		[ 'name' => 'Tahoma', 'import' => FALSE ],
		[ 'name' => 'Trebuchet MS', 'import' => FALSE ],
		[ 'name' => 'Verdana', 'import' => FALSE ],
		[ 'name' => 'Vidaloka', 'import' => 'https://fonts.googleapis.com/css?family=Vidaloka' ],
	);
	
	public static $fonts_imported = array();
	
	public static function get_font_names() { $r = array(); foreach( self::$fonts AS $font ) array_push($r, $font['name']); return $r; }
	
	public static function load_font( $name ) { foreach( self::$fonts AS $font ) if( $font['name'] === $name && $font['import'] !== FALSE && ! in_array($name, self::$fonts_imported) ) array_push( self::$fonts_imported, $font['import'] ); }


	# Translations
	
	public static $languages = array(
		'en' => 'English',
	#	'fr' => 'French',
		'de' => 'German',
	#	'it' => 'Italian',
		'ru' => 'Russian',
	#	'es' => 'Spanish',
	);
	
	public static $language = 'en';
	public static $locale_strings = NULL;
	
	private static function load_locale_xml( $filename ) {
		self::$locale_strings = array();
		$xml = simplexml_load_file($filename);
		foreach( $xml->children() AS $category ) {
			self::$locale_strings[$category->getName()] = array();
			foreach( $category AS $str ) {
				self::$locale_strings[$category->getName()][intval($str['id']->__toString())] = $str->__toString();
			}
		}
	}

	private static function load_locale() {
		if( self::$locale_strings === NULL ) {
			$filename = "locale/livemap_" . self::$language . ".xml";
			if( file_exists($filename) ) {
				self::load_locale_xml($filename);
			} else {
				self::load_locale_xml("locale/livemap_en.xml");
			}
		}
	}
	
	public static function get_locale() {
		self::load_locale();
		return self::$locale_strings;
	}
	
	public static function get_ui_strings() {
		self::load_locale();
		return self::$locale_strings['ui'];
	}
	
	public static function get_ui_daynames() {
		self::load_locale();
		return self::$locale_strings['daynames'];
	}
	
	public static function get_ui_ranks() {
		self::load_locale();
		return [ 1 => self::$locale_strings['ranks_m'], 2 => self::$locale_strings['ranks_f'] ];
	}
	
	public static function get_ui_weathers() {
		self::load_locale();
		return array(
			'Shower' => self::$locale_strings['weather'][0],
			'Cloudy' => self::$locale_strings['weather'][1],
			'Snowy'  => self::$locale_strings['weather'][2],
			'Fair'   => self::$locale_strings['weather'][3],
		);
	}
	
	public static function get_ui_string( $id ) {
		self::load_locale();
		return self::$locale_strings['ui'][$id];
	}
	
	public static function get_ui_guildtypes() {
		self::load_locale();
		return self::$locale_strings['guildtypes'];
	}
	
	public static function get_ui_permissions() {
		self::load_locale();
		return array(
			'CanEnter'   => self::$locale_strings['permissions'][0],
			'CanBuild'   => self::$locale_strings['permissions'][1],
			'CanClaim'   => self::$locale_strings['permissions'][2],
			'CanUse'     => self::$locale_strings['permissions'][3],
			'CanDestroy' => self::$locale_strings['permissions'][4],
		);
	}
	
	public static function get_ui_standings() {
		self::load_locale();
		return self::$locale_strings['standings'];
	}
	
	public static function get_ui_rank( $gender, $rank ) {
		self::load_locale();
		return self::get_ui_ranks()[$gender][$rank];
	}


	# Logging 

	public static function log_action( $action, $detail = '') {
		$query = sprintf("INSERT INTO %s (livemap_id, user_group, action, detail) VALUES ('%u', '%s', '$action', '$detail')", self::$config['table_l'], intval(self::$config['ID']), self::$db->esc(self::$group->name) );
		return ( self::$db->query($query) > 0 );
	}


	# Messaging and Redirects
	
	public static function set_notification( $message ) {
		$_SESSION['LIVEMAP_INFO'][self::$id]['MESSAGE'] = array( 'type' => 'notification', 'popup' => FALSE, 'text' => $message );
	}
	
	public static function set_confirmation( $message ) {
		$_SESSION['LIVEMAP_INFO'][self::$id]['MESSAGE'] = array( 'type' => 'confirmation', 'popup' => FALSE, 'text' => $message );
	}
	
	public static function set_error( $message, $popup = TRUE ) {
		$_SESSION['LIVEMAP_INFO'][self::$id]['MESSAGE'] = array( 'type' => 'error', 'popup' => $popup, 'text' => $message );
	}
	
	public static function error_redirect( $text = NULL, $popup = TRUE ) {
		$text = is_null($text) ? self::get_ui_string(30) : $text;
		self::set_error($text, $popup);
		header("Location: " . self::$redirect);
		die;
	}

	public static function success_redirect( $text = NULL ) {
		is_null($text) || self::set_confirmation($text);
		header("Location: " . self::$redirect);
		die;
	}
	
	public static function error_json( $text = NULL ) {
		$text = is_null($text) ? self::get_ui_string(30) : $text;
		$data = array( 'success' => FALSE, 'message' => $text );
		echo json_encode($data);
		die;
	}
	
	
	# Custom Data Interface
	
	public static function get_custom_pois( $get_svg = TRUE ) {
		$query = sprintf("SELECT * FROM %s WHERE livemap_id = '%u' AND data_type = 'poi'", self::$config['table_d'], self::$id);
		$rs = self::$db->query($query);
		$pois = [];
		foreach( $rs AS $row ) {
			$data = json_decode($row['data_json'], TRUE);
			$data['ID'] = $row['ID'];
			$data['icon'] = basename($data['icon']);
			if( $get_svg ) {
				$svg = file_get_contents(self::POI_ICON_PATH . "/" . $data['icon']);
				$data['svg'] = str_replace('<path ', "<path fill=\"#{$data['color']}\" ", $svg);
				list($data['x'], $data['y']) = self::geoid2pixelpos(intval($data['geoid'])); 
			}
			array_push($pois, $data);
		}
		return $pois;
	}
	
	public static function get_custom_areas() {
		$query = sprintf("SELECT * FROM %s WHERE livemap_id = '%u' AND data_type = 'area'", self::$config['table_d'], self::$id);
		$rs = self::$db->query($query);
		$areas = [];
		foreach( $rs AS $row ) {
			$data = json_decode($row['data_json'], TRUE);
			$data['ID'] = $row['ID'];
			array_push($areas, $data);
		}
		return $areas;
	}
	

	# Header Sections (Weather, Restarts)
	
	public static function get_restart_timestamps() {
		
		$restarts = explode(' ', self::$config['restarts']);
		
		// Convert each timestring to unix timestamp
		foreach( $restarts AS &$time ) {
			
			$hres = intval( substr($time, 0, 2) );
			$ires = intval( substr($time, -2) );
			
			$time = mktime($hres, $ires, 0);
			// Force future timestamps only
			if( $time < time() ) $time += (24*60*60);

		}
		
		sort($restarts);
		
		return $restarts;
		
	}
	
	public static function get_weather_array( $first, $last ) {
		
		$custompath = "weather/" . self::$config['ID'] . "_weather.xml";
		$xmlfile = file_exists($custompath) ? $custompath : 'weather/cm_weather1.xml';
		
		$xml = simplexml_load_file($xmlfile);
		
		$array = array();
		
		// Loop from $first to $last day of the year
		for( $i = $first; $i <= $last; $i++ ) {
			$day = $i < 365 ? $i : $i - 365;
			// Grab config from weather XML
			if( $day !== intval($xml->day[$day]['id']) ) throw new Exception('Invalid cm_weather1.xml file');
			// Wrap it up
			$weather_key = $xml->day[$day]->__toString();
			$array[] = array(
				'day' => $day,
				'key' => $weather_key,
				'weather' => self::get_ui_weathers()[$weather_key],
				'season' => $xml->day[$day]['season'],
			);
		}
		
		return $array;
		
	}


	# Util functions
	
	public static function validate_geoid( $geoid ) {
		
		foreach( self::geoid2pixelpos($geoid) AS $pos ) {
			if( $pos < 0 || $pos > 1533 ) return FALSE;
		}
		
		return TRUE;
		
	}
	
	public static function geoid2pixelpos( $geoid ) {
		
		$TerID = $geoid >> 18;
		$TerX  = $geoid & ((1 << 9) - 1);
		$TerY  = ($geoid >> 9) & ((1 << 9) - 1);
		
		return self::terpos2pixelpos($TerID, $TerX, $TerY);
		
	}

	public static function terpos2pixelpos( $TerID, $TerX, $TerY ) {
	
		switch( $TerID ) {
			case 442:
			case 443:
			case 444:	$y = 1532 - $TerY;		break;
			case 445:
			case 446:
			case 447:	$y = 1021 - $TerY;		break;
			default:	$y =  510 - $TerY;		break;
		}
		switch( $TerID ) {
			case 443:	
			case 446:	
			case 449:	$x = $TerX + 511;		break;
			case 444:	
			case 447:	
			case 450:	$x = $TerX + 1022;		break;
			default:	$x = $TerX;
		}
		
		return array( intval($x), intval($y) );
	
	}
	
	public static function get_distance( $a, $b ) {
		
		if( is_numeric($a) && is_numeric($b) ) {
			$a = self::geoid2pixelpos($a);
			$b = self::geoid2pixelpos($b);
		}

		$x = abs($a[0] - $b[0]);
		$y = abs($a[1] - $b[1]);
		return hypot($x, $y);

	}
	
}

?>