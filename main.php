<?php

	/* 
	
		Main Livemap Application
	   
	*/
	
	defined('VERSION') || die;

	# Include all classes and dependencies
	require_once('includes/group.class.php');
	require_once('includes/libs/SourceQuery.php');
	require_once('includes/gameserver.class.php');
	require_once('includes/livemap.class.php');
	require_once('includes/guild.class.php');
	
	# Set session shortcut
	$session =& $_SESSION['LIVEMAP_INFO'][$livemap_id];
	
	# Configure livemap class
	Livemap::configure( $config, $cdb );
	
	# Detect login and set privileges and group
	Livemap::session_auth();
	Livemap::cookie_auth();
	$mygroup =& Livemap::$group;
	
	# Create and configure server object
	$server = new LiFServer();
	$config['server_query'] && $server->set_gameserver($config['game_ip'], $config['game_port']);
	$config['isttmap'] ? $server->set_db_credentials($config['db_ip'], $config['db_port'], $config['db_user'], $config['db_pass'], $config['db_name']) : $server->set_db($cdb);

	# Include request handlers
	isset($_REQUEST['ajax'])   && require_once('includes/ajax.handler.php');
	isset($_REQUEST['action']) && require_once('includes/action.handler.php');
	
	# Initialize main template
	require_once('includes/template.class.php');
	$html = new Template('html/main.html');

	/********************
		Fill content
	*********************/
	
	$site = isset($_GET['s']) ? $_GET['s'] : 'main';
	switch( $site ) {
		
		// Leaflet Livemap
		case 'main':
		default: 
		
			$content = new Template();
			$content->set("<div id=\"livemap\"></div>");

		break;
		
		// Configuration Page
		case 'conf':
		
			// Ensure we're admin
			if( ! $mygroup->isAdmin() ) {
				$content = new Template('html/403.html');
				break;
			}
			
			// Modify group details
			$groups = Livemap::get_groups_array();
			foreach( $groups AS &$group ) {
				$g = Livemap::get_group_from_dataset($group);
				$group['isProtected']    = $g->isProtected();
				$group['isVisitorGroup'] = $g->isVisitor();
				$group['isGMGroup']      = $g->isGM();
				$group['passwordLogin']  = $g->canPasswordLogin();
				$group['steamLogin']     = $g->canSteamLogin();
				// Get privileges
				$privs = $g->get_privilege_list(TRUE);
				$group['privCount']  = count($privs);
				$group['privileges'] = array();
				foreach( $privs AS $priv ) $group['privileges'][] = array( 'priv_name' => $priv[0] );
				// Get members count
				$group['memcount'] = count($g->steam_ids);
				if( $g->isGM() ) $group['memcount'] += count($server->get_accounts_where('IsGM', 1));
			}
			
			// Prepare languages
			$languages = array();
			foreach( Livemap::$languages AS $key => $name ) {
				$languages[] = array('shortcut' => $key, 'name' => $name, 'selected' => (Livemap::$language === $key) );
			}
			
			// Prepare webserver info
			$arch_hint   = "";
			$arch_string = "64-Bit";
			$arch_ok     = TRUE;
			if( PHP_INT_SIZE === 4 ) {
				$arch_string = "32-Bit";
				$arch_hint   = "Some functions require 64-Bit PHP or the GMP extension enabled.";
				$arch_ok     = FALSE;
				if( extension_loaded('gmp') ) {
					$arch_string .= " (GMP extension enabled)";
					$arch_ok     = TRUE;
				}
			}
			
			// Fill content template
			$content = new Template('html/conf.html');
			// Assign config
			foreach( $config AS $key => $val ) $content->assign( $key, $val );
			$content->assign( 'languages', $languages )
			// Rules
			->assign( 'SanitizedRules', htmlspecialchars($config['rules']) );
			// Assign styles
			$claimstyles = array( 'solid', 'dashed', 'dotted' );
			$content->assign( 'claimstyles', Template::simple_select_array($claimstyles, $config['style_claim']) );
			$claimborder = array( '1', '2', '3', '4', '5' );
			$content->assign( 'claimthickness', Template::simple_select_array($claimborder, $config['width_claim']) );
			$tooltips = array( 'standard', 'dark' );
			$content->assign( 'tooltips', Template::simple_select_array($tooltips, $config['style_tooltip']) );
			// Assign timezones
			$content->assign( 'timezones', Template::simple_select_array(timezone_identifiers_list(), $config['timezone']) );
			// Assign groups
			$content->assign( 'groups', $groups );
			// Assign weatherfile info
			$is_default = ( $config['isttmap'] && ! file_exists("weather/{$livemap_id}_weather.xml") );
			$content->assign( 'DEFAULT_WEATHER', $is_default );
			// Guild Manager
			$content->assign( 'guildmanager', (bool)intval($config['guildmanager']) );
			// GuildGUI URL
			$content->assign( 'GuildGUI_URL', ( isset($_SERVER['HTTPS']) ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]/index.php?livemap_id=$livemap_id&action=GuildGUI" );
			// Webserver info
			$content->assign( 'ARCH_OK', $arch_ok );
			$content->assign( 'PHP_VERSION', PHP_VERSION . ' on ' . PHP_OS );
			$content->assign( 'PHP_ARCH_TEXT', $arch_string );
			$content->assign( 'PHP_ARCH_HINT', $arch_hint );
			$content->assign( 'ALLOW_FSOCKOPEN', strpos(ini_get('disable_functions'), 'fsockopen') === false );
			
			$content->assign( 'font_claimlabel', $config['font_claimlabel'] );
			$content->assign( 'font_claimdetail', $config['font_claimdetail'] );
			
			// Get last TTmod heartbeat and version
			if( $server->detect_ttmod() ) {
				$ttmod_version = $server->get_ttmod_version();
				$rs = $server->passthru_db_query( "SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(MAX(`time`)) AS diff FROM nyu_tracker_stats", FALSE );
				$content->assign( 'ttmod_sec', $rs['diff'] )
				->assign( 'ttmod_healthy', (intval($rs['diff']) < 60*6 ) )
				->assign( 'ttmod_version', $ttmod_version )
				->assign( 'ttmod_up2date', (floatval($ttmod_version) >= Livemap::TTMOD_VERSION) )
				->assign( 'ttmod_tracker_start', $server->get_stat_info('tracker_first') );
			}
			
			// Assign fonts
			$content->assign( 'fonts_claimlabel', Template::simple_select_array(Livemap::get_font_names(), $config['font_claimlabel']) );
			$content->assign( 'fonts_claimdetail', Template::simple_select_array(Livemap::get_font_names(), $config['font_claimdetail']) );
			// Load all fonts
			foreach( Livemap::$fonts AS $font ) Livemap::load_font( $font['name'] );
			
			// Custom Data
			$poi_path = Livemap::POI_ICON_PATH;
			$poi_icons = array();
			foreach( scandir(Livemap::POI_ICON_PATH) AS $file ) {
				if( strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg' ) {
					$poi_icons[] = array( 'file' => $file, 'svg' => str_replace('<path ', '<path fill="#FFF" ', file_get_contents(Livemap::POI_ICON_PATH . "/" . $file)) );
				}
			}
			$content->assign( 'poi_icons', $poi_icons );
			$content->assign( 'poi_json', json_encode(Livemap::get_custom_pois()) );
			$content->assign( 'area_json', json_encode(Livemap::get_custom_areas()) );
			
			// Get logs
			$logs = $cdb->query( "SELECT ID, user_group, timestamp, action, detail FROM {$config['table_l']} WHERE livemap_id = $livemap_id ORDER BY ID DESC" );
			$content->assign( 'json_logs', json_encode($logs) );
			
			// Security check: updater exists?
			$content->assign( "UPDATER_EXISTS", ( ! $config['isttmap'] && file_exists("includes/updater.php") ) );

		break;
		
		case 'group':
		
			// Ensure we're admin and group ID is defined
			if( ! $mygroup->admin || ! isset($_GET['id']) ) {
				$content = new Template('html/403.html');
				break;
			}
		
			// Get group info
			$group_data = Livemap::get_group_data($_GET['id']);
			$group = Livemap::get_group_from_dataset($group_data);
			
			// Privileges array
			$allprivs   = array();
			foreach( $group->privileges AS $name => $bool ) {
				$translated = $group->translate_privilege($name);
				$allprivs[] = array( 'key'  => $name, 'isset' => $bool, 'name' => $translated[0], 'descr' => $translated[1] );
			}
			
			// Members
			$accounts = [];
			if( $group_data['members_csv'] ) {
				foreach( str_getcsv($group_data['members_csv']) AS $id ) {
					$accounts[] = array(
						'SteamID'    => $id,
						'Static'     => FALSE,
						'Characters' => [],
					);
				}
			}
			
			// Group members
			$chars = $server->get_characters_where( "SteamID", $group_data['members_csv'] );
			foreach( $chars AS $char ) {
				$charname = $char['FirstName'] . " " . $char['LastName'];
				$index = array_search($char['SteamID'], array_column($accounts, "SteamID"));
				if( $index === FALSE ) {
					$accounts[] = array(
						'SteamID'    => $char['SteamID'],
						'Static'     => FALSE,
						'Characters' => array( $charname )
					);
				} else {
					$accounts[$index]['Characters'][] = $charname;
				}
			}
			
			// Static members for GM group
			$static = $group->isGM() ? $server->get_characters_where( "IsGM", "1" ) : array();
			foreach( $static AS $char ) {
				$charname = $char['FirstName'] . " " . $char['LastName'];
				$index = array_search($char['SteamID'], array_column($accounts, "SteamID"));
				if( $index === FALSE ) {
					$accounts[] = array(
						'SteamID'    => $char['SteamID'],
						'Static'     => TRUE,
						'Characters' => array( $charname )
					);
				} else {
					$accounts[$index]['Characters'][] = $charname;
				}
			}
			
			
			// Fill content template
			$content = new Template('html/group.html');
			$content->assign( 'name', htmlspecialchars($group->name) )
			->assign( 'group_id', $group->id )
			->assign( 'pwlogin', $group->canPasswordLogin() )
			->assign( 'steamlogin', $group->canSteamLogin() )
			->assign( 'isVisitorGroup', $group->isVisitor() )
			->assign( 'isGMGroup', $group->isGM() )
			->assign( 'isProtected', $group->isProtected() )
			->assign( 'privs', $allprivs )
			->assign( 'members', json_encode($accounts) );
		
		break;
		
		// Account & Character Management
		case 'char':
		
			// Check permissions
			if( ! $mygroup->privileges['manage_chars'] ) {
				$content = new Template('html/403.html');
				break;
			}
			
			// Load characters list
			if( $server->get_ttmod_version() >= 1.3 ) {
				$query = "SELECT
							a.SteamID, a.IsActive AS AccountActive, a.ID AS AccountID, a.IsGM,
							c.ID, c.GeoID, c.GuildRoleID, c.IsActive AS CharActive, ROUND(c.Alignment/1000000) AS Alignment,
							c.CreateTimestamp AS CreateString, UNIX_TIMESTAMP(c.CreateTimestamp) AS CreateTimestamp,
							c.Name AS FirstName, c.LastName, CONCAT(c.Name, ' ', c.LastName) AS Name,
							ASCII( SUBSTRING(c.appearance,1) ) AS Gender,
							g.ID AS GuildID, g.Name AS GuildName,
							COUNT(op.CharID) AS is_online,
							MAX(`ts`.`time`) AS LastOnlineString,
							UNIX_TIMESTAMP(MAX(`ts`.`time`)) AS LastOnlineTimestamp,
							ROUND( COUNT(`ts`.`ID`) * 5 / 60 ) AS Playtime,
							ci.deaths AS Deaths, ci.kills AS Kills
						  FROM
							`account` a, `character` c
						  LEFT JOIN `nyu_chars_info` ci
							ON c.ID = ci.CharID
						  LEFT JOIN `guilds` g
							ON c.GuildID = g.ID
						  LEFT JOIN `nyu_ttmod_tokens` op
							ON c.ID = op.CharID
						  LEFT JOIN `nyu_tracker_chars` tc
							INNER JOIN `nyu_tracker_stats` ts
							  ON ts.ID = tc.stat_id
							ON tc.CharacterID = c.ID
						  WHERE a.ID = c.AccountID
						  GROUP BY c.ID
						  ORDER BY c.Name";
			} else {
				$query = "SELECT
							a.SteamID, a.IsActive AS AccountActive, a.ID AS AccountID, a.IsGM,
							c.ID, c.GeoID, c.GuildRoleID, c.CreateTimestamp, c.IsActive AS CharActive, ROUND(c.Alignment/1000000) AS Alignment, 
							c.CreateTimestamp AS CreateString, UNIX_TIMESTAMP(c.CreateTimestamp) AS CreateTimestamp,
							c.Name AS FirstName, c.LastName, CONCAT(c.Name, ' ', c.LastName) AS Name,
							ASCII( SUBSTRING(c.appearance,1) ) AS Gender,
							g.ID AS GuildID, g.Name AS GuildName,
							0 AS is_online
						  FROM
							`account` a, `character` c
						  LEFT JOIN `guilds` g
							ON c.GuildID = g.ID
						  WHERE a.ID = c.AccountID
						  GROUP BY c.ID
						  ORDER BY c.Name";
			}
			$charlist = $server->passthru_db_query($query);

			// Make sure JSON conversion succeeds
			$charjson = json_encode($charlist);
			if( $charjson === FALSE ) {
				$content = new Template('html/500.html');
				break;
			}
			
			// Load template
			$content = new Template('html/chman.html');	
			
			// Assign characters and items list
			$content->assign( 'characters', $charjson );
			$content->assign( 'items', $server->get_all_items() );

			// Translations
			$content->assign( 'json_ranks', json_encode(Livemap::get_ui_ranks()) );
			
		break;
		
		// RCON
		case 'rcon':
		
			// Check permission
			if( ! $mygroup->privileges['rcon'] ) {
				$content = new Template('html/403.html');
				break;
			}
			
			// Ensure server mod is installed
			if( ! $server->detect_ttmod() ) {
				$content = new Template();
				$content->set("<div class=\"content-page\">RCON is not available on your server. Please check if TTmod is installed and loaded properly.</div>");
				break;
			}
		
			$content = new Template('html/rcon.html');
			$content->assign( 'items', $server->get_all_items() )
			->assign( 'show_ttmod_warning', ($server->get_ttmod_version() < Livemap::TTMOD_VERSION) );

		break;

		// Guild Management & GuildGUI
		case 'guildman':
			
			require_once('guildgui.php');
			
		break;
		
	}
	
	$html->assign( 'CONTENT', $content->parse() );
	
	/******************************
		Fill main template
	*******************************/
	
	// Assign environment config
	$html->assign( 'VERSION', VERSION )
	->assign( 'BASE_URL', BASE_URL )
	->assign( 'LIVEMAP_ID', $livemap_id )
	->assign( 'FEUDALTOOLS', $config['isttmap'] )
	->assign( 'REAL_LINK', ( isset($_SERVER['HTTPS']) ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" )
	->assign( 'IS_MAP', $site === 'main' )
	->assign( 'IS_PUBLIC', (bool)$config['server_query'] )
	->assign( 'MOD_INSTALLED', $server->detect_ttmod() );
	
	// Assign user status and group privileges
	foreach( $mygroup->privileges AS $key => $bool ) $html->assign( "PRIV_$key", $bool );
	$html->assign( 'LOGGED_IN', $session['LOGGED_IN'] )
	->assign( 'STEAM_AUTH', isset($_SESSION['SteamAuth']) )
	->assign( 'PRIVILEGES', json_encode($mygroup->get_privilege_list()) )
	->assign( 'PRIV_conf', $mygroup->isAdmin() );
	
	// Assign fonts
	Livemap::load_font( $config['font_claimlabel'] );
	Livemap::load_font( $config['font_claimdetail'] );
	$html->assign( 'FONTS_IMPORT', Livemap::$fonts_imported );
	
	// Assign visual config
	$html->assign( 'WEB_TITLE', htmlspecialchars($config['title']) )
	->assign( 'COLOR_BACKGR', $config['color_bg'] )
	->assign( 'SHOW_LABELS', ($config['color_label'] !== 'ZZZZZZ') )
	->assign( 'COLOR_GLABEL', $config['color_label'] )
	->assign( 'FONT_GLABEL', $config['font_claimlabel'] )
	->assign( 'FONT_TOOLTIP', $config['font_claimdetail'] )
	->assign( 'PLAYERS_ENABLE', ($server->detect_ttmod() || (bool)intval($config['server_query'])) );
	
	// Assign server details
	$html->assign( 'SERVER_IP', $config['game_ip'] )
	->assign( 'SERVER_PORT', $config['game_port'] )
	->assign( 'SERVER_TIMEZONE', $config['timezone'] )
	->assign( 'WEB_LINK', $config['homepage'] )
	->assign( 'HAS_LINK', (bool)$config['homepage'] )
	->assign( 'DISCORD', $config['discord'] )
	->assign( 'HAS_DISCORD', (bool)$config['discord'] )
	->assign( 'HAS_TEAMSPEAK', (bool)$config['teamspeak'] )
	->assign( 'TEAMSPEAK', $config['teamspeak'] )
	->assign( 'HAS_GUILDMAN', (bool)intval($config['guildmanager']) )
	->assign( 'HAS_RULES', (bool)$config['rules'] )
	->assign( 'SanitizedRulesHtml', nl2br(htmlspecialchars($config['rules'])) );
	
	// Get weather info
	$weather = array();
	if( $mygroup->privileges['weather_now'] || $mygroup->privileges['weather_fc'] ) {
		$forecast = $mygroup->privileges['weather_fc'] ? 9 : 3;
		$gameday = $server->get_day( floatval($config['daycycle']) );
		$weather = Livemap::get_weather_array( $gameday, $gameday + $forecast );
		// Assign to html
		$html->assign('weather_now', $weather[0]['weather'])
		->assign('weather_icon', $weather[0]['key'])
		->assign('weather_tomorrow', $weather[1]['weather']);
	}
	
	// Fetch config array without private info
	$public_config = Livemap::get_public_config();
	$public_config['restarts_ts'] = Livemap::get_restart_timestamps();
	$public_config['hasMap'] = ( $site === 'main' );
	$public_config['weatherInfo'] = $weather;
	$public_config['hasSteam'] = Livemap::get_steam_id() !== FALSE;
	$html->assign( 'JSON_CONFIG', json_encode($public_config) );
	
	// Messages
	$html->assign( 'MESSAGE', isset($session['MESSAGE']) ? json_encode($session['MESSAGE']) : 'false' );
	unset($session['MESSAGE']);
	
	// Assign localization
	foreach( Livemap::get_ui_strings() AS $key => $value ) $html->assign( "ui_$key", $value );
	$html->assign('LOCALE_UI_JSON', json_encode(Livemap::get_locale()));
	
	// Print page
	print $html->parse();
