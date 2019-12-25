<?php

/*
	to handle GET and POST action requests
*/

switch( $_REQUEST['action'] ) {
			
	# Password Login
	case 'Login':

		isset($_POST['auth_pass']) || Livemap::error_redirect(Livemap::get_ui_string(31));
		strlen($_POST['auth_pass']) > 3 || Livemap::error_redirect(Livemap::get_ui_string(31));

		// FeudalTools only: Get real password hash from database. Don't rely on cached one.
		// Not necessary on dedicated maps, since the plain-text password is read from config-dedicated.php
		if( $config['isttmap'] ) {
			$rs = $cdb->query("SELECT admin_pass FROM {$config['table_c']} WHERE ID = '$livemap_id'", FALSE);
			$config['admin_pass'] = $session['config']['admin_pass'] = $rs['admin_pass'];
		}
		
		// Legacy (v2) authentication hash
		// Needed for hosted livemaps that were migrated from v2
		$v2_hash = hash('sha256', $_POST['auth_pass']);
		
		// Admin Login
		if( password_verify($_POST['auth_pass'], $config['admin_pass']) || $config['admin_pass'] === $v2_hash ) {
			Livemap::login_group(0);
			Livemap::success_redirect(Livemap::get_ui_string(33));
		// User login
		} else {
			foreach( Livemap::get_groups_array() AS $group ) {
				if( password_verify($_POST['auth_pass'], $group['password']) || $group['password'] === $v2_hash ) {
					Livemap::login_group($group['ID']);
					Livemap::success_redirect(Livemap::get_ui_string(33));
				}
			} 
		}

		// Failed auth
		Livemap::error_redirect(Livemap::get_ui_string(31));

	break;
	
	# Steam Login
	case 'SteamAuth':
	case 'GuildAuth':
	
		$to_guildman = ($_REQUEST['action'] === 'GuildAuth');
		Livemap::$redirect = "index.php?livemap_id=$livemap_id";
		require_once('includes/libs/openid.php');
		
		try {
		
			$openid = new LightOpenID($_SERVER['HTTP_HOST']);
			
			// Initiale OpenID auth through Steam provider
			if( ! $openid->mode ) {
				$openid->identity = 'https://steamcommunity.com/openid';
				header('Location: ' . $openid->authUrl());
				die;
			} 
			
			// Authentication canceled by user. Just redirect to main page...
			if( $openid->mode === 'cancel' ) Livemap::success_redirect();

			// Auth success
			if( $openid->validate() )  {
				
				$steamid = explode('/', $openid->identity);
				$steamid = end($steamid);
				
				// Configure session
				Livemap::set_steam_id($steamid);
				
				// Remember Login?
				isSet($_GET['rememberme']) && $_GET['rememberme'] && Livemap::set_auth_cookie();			

				// Admin Login?
				if( $steamid && $steamid === $config['admin_steam'] ) {
					Livemap::login_group(0);
				// Assign group permissions
				} else {
					foreach( Livemap::get_groups_array() AS $group ) {
						$steam_id_list = explode(",", $group['members_csv']);
						if( in_array($steamid, $steam_id_list) ) Livemap::login_group($group['ID']);
					}
				}
				
				if( $to_guildman ) {
					// Guild Management Redirect
					Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman";
				} else {
					// No permissions
					$session['LOGGED_IN'] || Livemap::error_redirect(Livemap::get_ui_string(32));
				}
				
				Livemap::success_redirect(Livemap::get_ui_string(33));
				
			}
			
		// Exception during OpenID auth (Steam API down?)
		} catch(ErrorException $e) {
			Livemap::error_redirect("Failed to connect to Steam API: " . $e->getMessage());
		}
		
		// Failed Steam authentication
		Livemap::error_redirect(Livemap::get_ui_string(31));

	break;
	
	# Logout
	case 'Logout':
	
		Livemap::clear_session();
		Livemap::clear_cookie();
		Livemap::success_redirect();

	break;
	
	# Login through TTmod security token (ingame browser)
	case 'GuildGUI':
	
		$errormsg = "An error ocurred during authentication.";
	
		if( ! isSet($_GET['Token']) || strlen($_GET['Token']) !== 64 ) die($errormsg);

		$token = $cdb->esc($_GET['Token']);
		$details = $server->get_token_details($token) or die($errormsg);
		
		Livemap::set_steam_id($details['SteamID']);
		$_SESSION['InGame'] = TRUE;
		$_SESSION['CharID'] = intval($details['CharID']);
		
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman";
		Livemap::success_redirect();

	break;

	# Change Config
	case 'ChangeConfig':
		
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-general";
		
		// Check permission
		$mygroup->isAdmin() || Livemap::error_redirect();
		
		// Check input is set
		isSet($_POST['title'], $_POST['homepage'], $_POST['discord'], $_POST['teamspeak'], $_POST['restarts'], $_POST['language'], $_POST['daycycle'], $_POST['timezone'], $_POST['guildmanager']) || Livemap::error_redirect();

		// Evaluate input
		$title    = trim($cdb->esc($_POST['title']));
		$language = $_POST['language'];
		$restarts = trim($_POST['restarts']);
		$daycycle = floatval($_POST['daycycle']);
		$timezone = $_POST['timezone'];
		$guildman = intval($_POST['guildmanager']);
		$homepage = trim($cdb->esc($_POST['homepage']));
		$discord  = trim($cdb->esc($_POST['discord']));
		$teamspeak= trim($cdb->esc($_POST['teamspeak']));
		$rules	  = trim($cdb->esc($_POST['rules']));
		$steamid  = trim($cdb->esc($_POST['steamid']));
		
		// Check title
		if( strlen($title) < 5 ) Livemap::error_redirect('Livemap title too short (min. 5 characters)');
		// Check Language
		if( ! in_array( $language, array_keys(Livemap::$languages) ) ) Livemap::error_redirect('Invalid language key?');
		// Check Restarts
		if( strlen($restarts) > 50 ) Livemap::error_redirect('Too many restart timestamps');
		if( strlen($restarts) > 0 ) {
			$errms = 'Invalid timestamp for server restarts. Please use 24-hour HH:MM format, such as 03:00 or 21:30. Separate multiple times using a blank space in between.';
			$times = explode(' ', $restarts);
			foreach( $times AS $time ) {
				if( preg_match("/^\d\d:\d\d$/", $time) !== 1 ) Livemap::error_redirect($errms);
				$hourmin = explode(':', $time);
				if( count($hourmin) !== 2 || intval($hourmin[0]) < 0 || intval($hourmin[0]) > 23 || intval($hourmin[1]) < 0 || intval($hourmin[1]) > 59 ) Livemap::error_redirect($errms);
			}
		}
		// Check Daycycle
		if( $daycycle < 1 || $daycycle > 24 ) Livemap::error_redirect("dayCycle setting is out of range (1.0 to 24.0)");
		// Check Timezone
		if( ! in_array($timezone, timezone_identifiers_list()) ) Livemap::error_redirect('Invalid timezone.');
		// Social Links
		if( strlen($homepage) > 0 && filter_var($homepage, FILTER_VALIDATE_URL) === FALSE ) {
			$homepage = "http://$homepage";
			if( filter_var($homepage, FILTER_VALIDATE_URL) === FALSE ) Livemap::error_redirect('Invalid homepage address!');
		}
		// Admin SteamID
		if( $steamid && ! is_numeric($steamid) ) Livemap::error_redirect('SteamID must be numeric');
		
		// FeudalTools only: Check weather xml file upload
		if( $config['isttmap'] && isSet($_FILES['weather_xml']) && $_FILES['weather_xml']['error'] !== UPLOAD_ERR_NO_FILE ) {
			$file  = $_FILES['weather_xml'];
			$errms = "Invalid weather configuration XML file.";
			// Upload error check
			if( $file['error'] !== UPLOAD_ERR_OK ) Livemap::error_redirect("An error occoured during file upload (Error code {$file['error']}). Try again?");
			// Filesize error check
			if( $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['error'] === UPLOAD_ERR_INI_SIZE ) Livemap::error_redirect("The maximum filesize was exceeded.");
			// Check filesize and mime type
			if( $file['size'] < 1024*2 || $file['size'] > 1024*50 || ($file['type'] !== 'application/xml' && $file['type'] !== 'text/xml') ) Livemap::error_redirect($errms);
			// Basic XML file plausibility check
			try {
				$xml = simplexml_load_file($file['tmp_name']);
				for( $day = 0; $day < 365; $day++ ) if( intval($xml->day[$day]['id']->__toString()) !== $day ) Livemap::error_redirect($errms);
			} catch(Exception $e) {
				// Catch xml syntax errors
				Livemap::error_redirect($errms);
			}
			// Save file to disk
			if( ! $xml->asXML("weather/{$livemap_id}_weather.xml") ) Livemap::error_redirect("Unable to save weather XML file on server. Please try again later.");
		}
		
		// Update database and redirect with force_reload
		$cdb->query( "UPDATE {$config['table_c']} SET title = '$title', admin_steam = '$steamid', homepage = '$homepage', discord = '$discord', teamspeak = '$teamspeak', language = '$language', daycycle = '$daycycle', timezone = '$timezone', restarts = '$restarts', rules = '$rules', guildmanager = '$guildman' WHERE ID = '$livemap_id'" );
		Livemap::log_action('config_general');
		$session['FORCE_RELOAD'] = TRUE;
		Livemap::success_redirect("Livemap configuration was updated!");

	break;
	
	# Change Appearance
	case 'ChangeAppearance':
	
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-appearance";
		
		// Check permission
		$mygroup->isAdmin() OR Livemap::error_redirect();
		
		// Check input is set
		isSet($_POST['alt_map'], $_POST['pri_map'], $_POST['color_bg'], $_POST['color_bg'], $_POST['color_label'], $_POST['color_claim_t1'], $_POST['color_claim_t2'], $_POST['color_claim_t3'], $_POST['color_claim_t4'], $_POST['style_claim'], $_POST['style_tooltip'], $_POST['width_claim'],  $_POST['font_claimdetail'],  $_POST['font_claimlabel']) OR Livemap::error_redirect();
	
		// Evaluate input
		$pri_map  = intval($_POST['pri_map']);	// 0 = Disable // 1 = Image // 2 = Tileset
		$alt_map  = intval($_POST['alt_map']);
		$c_bg	  = $_POST['color_bg'];
		$c_label  = isSet($_POST['show_labels']) && $_POST['show_labels'] === '1' ? $_POST['color_label'] : 'ZZZZZZ';
		$s_claim  = $_POST['style_claim'];
		$w_claim  = intval($_POST['width_claim']);
		$s_tooltip= $_POST['style_tooltip'];
		$f_detail = $_POST['font_claimdetail'];
		$f_label  = $_POST['font_claimlabel'];
		$c_claim_t1	= $_POST['color_claim_t1'];
		$c_claim_t2	= $_POST['color_claim_t2'];
		$c_claim_t3	= $_POST['color_claim_t3'];
		$c_claim_t4	= $_POST['color_claim_t4'];
		
		// Check input plausibility
		$valid_fonts = Livemap::get_font_names();
		if( ! in_array($f_detail, $valid_fonts) || ! in_array($f_label, $valid_fonts) ) Livemap::error_redirect('Invalid font type');
		if( preg_match('/^[a-f0-9]{6}$/i', $c_bg)    !== 1 ) Livemap::error_redirect('Invalid hex color code for background');
		if( preg_match('/^[a-f0-9]{6}$/i', $c_claim_t1) !== 1 ) Livemap::error_redirect('Invalid hex color code for claims');
		if( preg_match('/^[a-f0-9]{6}$/i', $c_claim_t2) !== 1 ) Livemap::error_redirect('Invalid hex color code for claims');
		if( preg_match('/^[a-f0-9]{6}$/i', $c_claim_t3) !== 1 ) Livemap::error_redirect('Invalid hex color code for claims');
		if( preg_match('/^[a-f0-9]{6}$/i', $c_claim_t4) !== 1 ) Livemap::error_redirect('Invalid hex color code for claims');
		if( preg_match('/^[a-f0-9]{6}$/i', $c_label) !== 1 && $c_label !== 'ZZZZZZ' ) Livemap::error_redirect('Invalid hex color code for claim labels');
		if( ! in_array($s_claim, ['solid', 'dotted', 'dashed'] ) ) Livemap::error_redirect();
		if( ! in_array($s_tooltip, ['standard', 'dark'] ) ) Livemap::error_redirect();
		if( $w_claim < 1 || $w_claim > 5 ) Livemap::error_redirect();
		if( $pri_map < 1 ) Livemap::error_redirect();
		
		// Tileset verification	
		if( max($pri_map, $alt_map) === 2 ) {
			if( $pri_map === 2 && $alt_map === 2 ) Livemap::error_redirect("Tileset can be used for either primary or secondary map image. Not both.");
			$tileset_path = Livemap::$config['isttmap'] ? 'maps/tileset/' . Livemap::$id : 'maps/tileset';
			file_exists("$tileset_path/full.jpg") || file_exists("$tileset_path/1_0_3.jpg") || Livemap::error_redirect('No tileset found. Install a tileset first.');
		}
		
		// FeudalTools only: Check map image uploads
		if( $config['isttmap'] ) {
			foreach( $_FILES AS $name => $file ) {
				// Form input name check
				if( ! in_array($name, ['mapfile_default','mapfile_alternative']) )
					continue;
				// Skip if name is invalid or no file was selected
				if( $file['error'] === UPLOAD_ERR_NO_FILE )
					continue;
				// Upload error check
				if( $file['error'] !== UPLOAD_ERR_OK )
					Livemap::error_redirect("An error occurred during file upload (Error code {$file['error']}). Try again?");
				// Filesize check
				if( $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['error'] === UPLOAD_ERR_INI_SIZE || $file['size'] > Livemap::MAX_UPLOAD_SIZE )
					Livemap::error_redirect("The maximum filesize was exceeded.");
				// Filetype check
				$valid_mime = ['image/png', 'image/jpg', 'image/jpeg'];
				if( ! in_array($file['type'], $valid_mime) ) Livemap::error_redirect("Invalid image filetype. You can upload JPG, JPEG or PNG files only.");
				// Image dimension check via getimagesize
				$imginfo = getimagesize( $file['tmp_name'] );
				if( $imginfo === FALSE ) Livemap::error_redirect("Unable to open image file. Please check format.");
				if( $imginfo[0] !== 1533 || $imginfo[1] !== 1533 ) Livemap::error_redirect("Map image needs to be exactly 1533x1533 pixel. Please scale your image before uploading if necessary.");
				if( 	$imginfo[2] === IMAGETYPE_JPEG ) $image = imagecreatefromjpeg($file['tmp_name']);
				elseif( $imginfo[2] === IMAGETYPE_PNG  ) $image = imagecreatefrompng($file['tmp_name']);
				else Livemap::error_redirect("Invalid image filetype. You can upload JPG, JPEG or PNG files only.");	
				// Get filename
				$filename = $name === 'mapfile_default' ? "{$livemap_id}_default.jpg" : "{$livemap_id}_alternative.jpg";
				// Save image
				imagejpeg($image, "{$config['path']}/maps/user/$filename", 82) OR Livemap::error_redirect("An error occoured during file upload");
				imagedestroy($image);
				// Set new revision id
				$cdb->query( "UPDATE {$config['table_c']} SET file_revision = file_revision + 1 WHERE ID = '$livemap_id'" );
			}
		}
		
		// Update database and redirect with force_reload
		$cdb->query( "UPDATE {$config['table_c']} SET pri_map = $pri_map, alt_map = $alt_map, color_bg = '$c_bg', color_claim_t1 = '$c_claim_t1', color_claim_t2 = '$c_claim_t2', color_claim_t3 = '$c_claim_t3', color_claim_t4 = '$c_claim_t4', color_label = '$c_label', style_claim = '$s_claim', style_tooltip = '$s_tooltip', width_claim = '$w_claim', font_claimlabel = '$f_label', font_claimdetail = '$f_detail' WHERE ID = '$livemap_id'" );
		Livemap::log_action('config_appearance');
		$session['FORCE_RELOAD'] = TRUE;
		Livemap::success_redirect("Livemap design settings were updated!");

	break;
	
	# Add Point of Interest
	case 'AddPoi':
		
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-customdata";
		
		// Check permission
		if( ! $mygroup->admin ) Livemap::error_redirect();
		
		// Check input
		isset($_POST['poi_name'], $_POST['poi_desc'], $_POST['poi_icon'], $_POST['poi_size'], $_POST['poi_color'], $_POST['poi_geoid']) || Livemap::error_redirect();

		// Name & Description
		$name = $_POST['poi_name'];
		$desc = $_POST['poi_desc'];
		strlen($name) >= 3 || Livemap::error_redirect("Name is too short (min 3 characters)");
		
		// Color
		$color = $_POST['poi_color'];
		(ctype_xdigit($color) && strlen($color) == 6) || Livemap::error_redirect("Invalid color identifier");
		
		// Size
		$size = intval($_POST['poi_size']);
		($size >= 16 && $size <= 48) || Livemap::error_redirect();
		
		// Icon image
		$file = basename($_POST['poi_icon']);
		file_exists("images/poi/$file") || Livemap::error_redirect();
		
		// Position
		$geoid = intval($_POST['poi_geoid']);
		Livemap::validate_geoid($geoid) || Livemap::error_redirect();
		
		// Insert POI
		$data = array('name' => $name, 'desc' => $desc, 'icon' => $file, 'size' => $size, 'color' => $color, 'geoid' => $geoid);
		$data_json = $cdb->esc(json_encode($data));
		$cdb->query("INSERT INTO {$config['table_d']} (livemap_id, data_type, data_json) VALUES ('$livemap_id', 'poi', '$data_json')");
		Livemap::log_action('cdata_poi', $cdb->esc($name));
		
		Livemap::success_redirect("The point of interest was saved");
	
	break;
	
	# Add Area
	case 'AddArea':
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-customdata";
		
		// Check permission
		if( ! $mygroup->admin ) Livemap::error_redirect();
		
		// Check input
		isset($_POST['area_name'], $_POST['area_desc'], $_POST['area_color'], $_POST['area_geometry']) || Livemap::error_redirect();

		// Name & Description
		$name = $_POST['area_name'];
		$desc = $_POST['area_desc'];
		strlen($name) >= 3 || Livemap::error_redirect("Name is too short (min 3 characters)");
		
		// Color
		$color = $_POST['area_color'];
		(ctype_xdigit($color) && strlen($color) == 6) || Livemap::error_redirect("Invalid color identifier");

		// Geometry
		$geometry = json_decode($_POST['area_geometry'], TRUE);
		foreach( $geometry AS $position ) {
			( count($position) === 2 ) 		   || Livemap::error_redirect("Error during geometry validation");
			array_key_exists('lat', $position) || Livemap::error_redirect("Error during geometry validation");
			array_key_exists('lng', $position) || Livemap::error_redirect("Error during geometry validation");
		}
		
		// Insert Area
		$data = array('name' => $name, 'desc' => $desc, 'color' => $color, 'geometry' => $geometry);
		$data_json = $cdb->esc(json_encode($data));
		$cdb->query("INSERT INTO {$config['table_d']} (livemap_id, data_type, data_json) VALUES ('$livemap_id', 'area', '$data_json')");
		Livemap::log_action('cdata_area', $cdb->esc($name));
		
		Livemap::success_redirect("The new area was created");
	
	break;
	
	# Delete POI or Area
	case 'RemoveCustomData':
	
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-customdata";
		
		// Check permission
		if( ! $mygroup->admin ) Livemap::error_redirect();
	
		// Check input
		isset($_GET['id']) || Livemap::error_redirect();
		$id = intval($_GET['id']);
	
		// Valid id
		$all = array_merge( Livemap::get_custom_pois(FALSE), Livemap::get_custom_areas(FALSE) );
		in_array($id, array_map('intval', array_column($all, 'ID'))) || Livemap::error_redirect();
		
		// Delete it
		$cdb->query("DELETE FROM {$config['table_d']} WHERE livemap_id = '$livemap_id' AND ID = '$id'");
		Livemap::log_action('cdata_remove');
		
		Livemap::success_redirect("deleted!");
	
	break;
	
	# Add a group
	case 'AddGroup':
		
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-groups";
		
		// Check Name
		isSet($_POST['groupname']) OR Livemap::error_redirect();
		$name = $cdb->esc($_POST['groupname']);
		if( strlen($name) < 2  ) Livemap::error_redirect("Group name is too short (min 2 characters)");
		if( strlen($name) > 50 ) Livemap::error_redirect("Group name is too long (max 50 characters)");
		
		// Check permission
		if( ! $mygroup->admin ) Livemap::error_redirect();
		
		// Create group, fetch new group ID
		$group_id = Livemap::create_group($name);
		Livemap::log_action('group_add', $name);
		
		// Redirect to group edit screen
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=group&id=$group_id";
		Livemap::success_redirect("The new group was created");
	
	break;
	
	# Edit a group
	case 'ChangeGroup':
		
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-groups";
		isSet($_POST['group_id']) OR Livemap::error_redirect();
		$group_id =  intval($_POST['group_id']);
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=group&id=$group_id";
		
		// Check permission
		if( ! $mygroup->isAdmin() ) Livemap::error_redirect();
		
		// Load group info from database
		$group = Livemap::get_group($group_id);
		
		// Check: Group Name
		$name = $group->isProtected() ? $cdb->esc($group->name) : $cdb->esc($_POST['name']);
		if( strlen($name) < 2  ) Livemap::error_redirect("Group name is too short (min 2 characters)");
		if( strlen($name) > 50 ) Livemap::error_redirect("Group name is too long (max 50 characters)");

		// Check: Steam Members
		if( $steam_login = isSet($_POST['enable_steam']) && (bool)$_POST['enable_steam'] ) {
			$members = isSet($_POST['steamid']) && is_array($_POST['steamid']) ? array_values(array_unique($_POST['steamid'])) : array();
			foreach( $members AS $member ) {
				if( ! is_numeric($member) || strlen($member) !== 17 ) Livemap::error_redirect("Invalid SteamID in member list.");
			}
			$members_csv = implode(',', $members);
		} else {
			$members_csv = '';
		}
		$steamlogin_int = intval($steam_login);
		
		// Check: Privileges
		$privileges  = isSet($_POST['privileges']) && is_array($_POST['privileges']) ? $_POST['privileges'] : array();
		foreach( $privileges AS $priv ) {
			if( ! in_array($priv, array_keys($mygroup->privileges)) ) Livemap::error_redirect("Invalid key in privilege list.");
		}
		$dummygroup = new Group($group->id, $group->name); // Create a dummy group to generate a new bitmask
		foreach( $privileges AS $priv ) $dummygroup->privileges[$priv] = TRUE;
		$bitmask = $dummygroup->generate_bitmask();
		
		// Check and hash password
		$pw_login = isSet($_POST['enable_pw']) && (bool)$_POST['enable_pw'];
		if( $group->canPasswordLogin() && $pw_login && strlen($_POST['login_pw']) > 0 && strlen($_POST['login_pw']) < 5 ) Livemap::error_redirect("Password is too short (min 5 characters)");
		if( ! $group->canPasswordLogin() && $pw_login && strlen($_POST['login_pw']) < 5 ) Livemap::error_redirect("Password is too short (min 5 characters)");
		$password = $pw_login ? password_hash($_POST['login_pw'], PASSWORD_DEFAULT) : '';
		
		// Check for password doubles
		if( $pw_login ) {
			// ... check against other groups
			foreach( Livemap::get_groups_array() AS $gr ) {
				if( intval($gr['ID']) !== $group_id && $password === $gr['password'] ) Livemap::error_redirect("This password is already used for group: " . htmlspecialchars($gr['name']) );
			}
			// ... check for conflict with admin password
			if( $config['admin_pass'] === $password ) Livemap::error_redirect("This password is already used for admin login.");
		}
		
		// Update group in database
		$cdb->query( "UPDATE {$config['table_g']} SET name = '$name', password = '$password', steamlogin = '$steamlogin_int', privileges = '$bitmask', members_csv = '$members_csv' WHERE ID = '$group_id' AND livemap_id = '$livemap_id'" );
		Livemap::log_action('group_edit', $name);
		
		// Redirect with force_reload flag
		$_SESSION['force_reload'] = TRUE;
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-groups";
		Livemap::success_redirect("Group configuration was saved successfully.");
		
	break;
	
	# Delete a group
	case 'DeleteGroup':
	
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-groups";
		isSet($_GET['id']) OR Livemap::error_redirect();
		$group_id = intval($_GET['id']);
		
		// Check permission
		if( ! $mygroup->isAdmin() ) Livemap::error_redirect();

		// Group exists?
		$group = Livemap::get_group($group_id);
		$group OR Livemap::error_redirect();
		
		// Dont' delete protected groups
		$group->isProtected() AND Livemap::error_redirect();
		
		// Delete
		Livemap::delete_group($group_id);
		Livemap::log_action('group_delete', $group->name);
		
		Livemap::success_redirect("Group deleted successfully.");
	
	break;
	
	# Clear logs
	case 'ClearLogs':
	
		Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=conf#tab-logs";

		if( ! $mygroup->isAdmin() ) Livemap::error_redirect();

		$num = Livemap::$db->query("DELETE FROM {$config['table_l']} WHERE livemap_id = '$livemap_id'");
		
		Livemap::log_action('config_clearlog');
		
		Livemap::success_redirect("Log was cleared. $num records deleted.");
	
	break;
	
	# View PHPinfo page
	case 'PHPinfo':
	
		// Check permission
		if( ! $mygroup->isAdmin() || $config['isttmap'] ) Livemap::error_redirect();
		
		// Render phpinfo and kill process
		phpinfo();
		die;
		
	break;
	
	# Call outsourced modules
	case 'RCON':		require_once('includes/rcon.handler.php');		break;
	case 'CHMAN': 		require_once('includes/chman.handler.php');		break;
	case 'GUILDMAN': 	require_once('includes/guildgui.handler.php');	break;
	
}