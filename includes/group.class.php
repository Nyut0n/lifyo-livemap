<?php

class Group {

	public $id, $name, $type, $admin;
	
	public $password   = null;
	public $steam_ids  = [];
	public $privileges = [];
	
	protected $privbits = array(
		'claims' 		=> 1,
		'outposts'      => 262144,
		'member_count'	=> 2,
		'member_names'	=> 4,
		'standings'		=> 32768,
		'online_list'	=> 8,
		'steam_links'	=> 16,
		'struct_count'	=> 32,
		'struct_layer'	=> 64,
		'road_layer'	=> 128,
		'trees_layer'   => 2097152,
		'player_layer'	=> 256,
		'pclaim_layer'  => 65536,
		'aclaim_layer'  => 131072,
		'trading_posts' => 524288,
		'weather_now'	=> 512,
		'weather_fc'	=> 1024,
		'ingame_time'	=> 2048,
		'rcon'			=> 4096,
		'rcon_advanced' => 8192,
		'manage_chars'	=> 16384,
		'manage_adv'    => 1048576,
		'animal_spawns' => 4194304,
	);
	
	public function __construct( $id, $name, $type = 0, $bitmask = 0 ) {
		// Set general properties
		$this->id    = intval($id);
		$this->name  = $name;
		$this->type  = intval($type);
		// GID 0 = admin
		$this->admin = ( $this->id === 0 );
		// Modify bitmask for admin
		$bitmask = $this->admin ? array_sum($this->privbits) : intval($bitmask);
		// Translate bitmask to priv array
		foreach( $this->privbits AS $key => $bits ) $this->privileges[$key] = (bool)($bitmask & $bits);
	}
	
	/* Authentication Helpers */
	
	public function add_steam_id( $steam_id ) {
		array_push($this->steam_ids, $steam_id);
	}
	
	public function try_password( $password ) {
		return ( $this->canPasswordLogin() && $password === $this->password );
	}
	
	public function try_steam_id( $steam_id ) {
		return in_array($steam_id, $this->steam_ids);
	}
	
	/* Privilege Control */
	
	public function generate_bitmask() {
		$bitmask = 0;
		foreach( $this->privileges AS $name => $add ) if( $add ) $bitmask += $this->privbits[$name];
		return $bitmask;
	}
	
	public function get_privilege_list( $translate = FALSE ) {
		$output = array();
		foreach( $this->privileges AS $name => $bool ) {
			if( $bool ) $output[] = $translate ? $this->translate_privilege($name) : $name;
		}
		return $output;
	}
	
	// ToDo: Move this elsewhere
	public function translate_privilege( $key ) {
		// this blows
		switch( $key ) {
			case 'claims':			return array( 'Guild Claims', 					'Show location, size and some basic information of all guild claims on the map.' );						break;
			case 'outposts':		return array( 'Outposts', 						'Show location, type and owner of all outposts on the map.' );											break;
			case 'trading_posts':	return array( 'Trading Posts',					'Display icons for trading posts on the map' );															break;
			case 'member_count':	return array( 'Claim Population',				'Display total population count in each claim detail window.' );										break;
			case 'member_names':	return array( 'Claim Members',					'Display list of claim members in each claim detail window.' );											break;
			case 'standings':		return array( 'Guild Standings', 				'Display guilds standings when claim is highlighted.' );												break;
			case 'online_list':		return array( 'Online Players',					'Show list of online players (Requires server mod installed).' );										break;
			case 'steam_links':		return array( 'Steam Links',					'All player names are linked to their steam account profile.' );										break;
			case 'struct_count':	return array( 'Claim Structures',				'Show total amount of structures built in each claim.' );												break;
			case 'struct_layer':	return array( 'Structures Layer',				'Adds function to display all structures on the map.' );												break;
			case 'road_layer':		return array( 'Roads Layer',					'Adds function to display all paved roads on the map.' );												break;
			case 'player_layer':	return array( 'Players Layer',					'Adds function to display all online players on the map (Requires TTmod).' );							break;
			case 'pclaim_layer':	return array( 'Personal Claims',				'Adds function to display all personal claims on the map.' );											break;
			case 'aclaim_layer':	return array( 'Admin Lands',					'Adds function to display all admin lands on the map.' );												break;
			case 'weather_now':		return array( 'Current Weather',				'Displays todays and tomorrows weather on the server. (Requires correct cm_weather.xml uploaded).' );	break;
			case 'weather_fc':		return array( 'Weahter Forecast',				'Displays weather forecast for eight days. (Requires correct cm_weather.xml uploaded).' );				break;
			case 'ingame_time':		return array( 'Ingame Time/Date',				'Displays in-game time and date.' );																	break;
			case 'rcon':			return array( 'RCON Access',					'Basic access to RCON console (Requires TTmod).' );														break;
			case 'rcon_advanced':	return array( 'RCON Advanced Permissions',		'Trigger server functions and execute code in RCON console (Requires RCON Access).' );					break;
			case 'manage_chars':	return array( 'Character Management - Basic',	'Manage player accounts and characters with basic permission set.' );									break;
			case 'manage_adv':	    return array( 'Character Management - Advanced','Permissions to delete characters, promote GMs, insert items and change skills in Character Management' );	break;
			case 'trees_layer':		return array( 'Trees Layer',					'See trees on the map' );	break;
			case 'animal_spawns':	return array( 'Animal Spawn Locations',			'See location, type and average quality of animal spawns' );	break;
		}
	}
	
	/* Simple Q&A */
	
	public function isGM() {
		return ( $this->type === 2 );
	}
	public function isVisitor() {
		return ( $this->type === 1 );
	}
	public function isProtected() {
		return ( $this->type > 0 );
	}
	public function isAdmin() {
		return ( $this->id === 0 );
	}
	public function canSteamLogin() {
		return !empty($this->steam_ids);
	}
	public function canPasswordLogin() {
		return is_string($this->password);
	}
	
}
