<?php

# Dependency: class MySQL
# 			  class SourceQuery

use xPaw\SourceQuery\SourceQuery;

class LiFServer {
	
	# Reference
	protected $db, $sq;
	
	# Data
	protected $ip, $port;
	protected $ispublic = FALSE;
	protected $ttmod_version = NULL;
	public $serverinfo  = NULL;
	public $serverrules = NULL;

	# Set and get database object
	public function set_db( MySQL $db ) {
		$this->db = $db;
	}
	public function get_db() {
		return $this->db;
	}
	
	# Create a database object
	public function set_db_credentials( $ip, $port, $user, $pass, $schema ) {
		$this->db = new MySQL( $user, $pass, $schema, $ip, intval($port), 'utf8' );
		$this->db->connect_exception = TRUE;
	}
	
	# Execute query in gameserver database
	public function passthru_db_query($query, $multi = TRUE) {
		return $this->db->query($query, $multi);
	}
	
	# Set gameserver connection details
	public function set_gameserver( $ip, $port ) {
		$this->ip = $ip;
		$this->port = $port;
		// Verify valid IP or resolvable hostname
		$this->ispublic = ( filter_var($ip, FILTER_VALIDATE_IP) || gethostbyname($ip) !== $ip );
	}
	
	# Connect to gameserver through SourceQuery library 
	public function sq_connect() {
		$this->sq = new SourceQuery();
		$this->sq->Connect( $this->ip, $this->port + 2, 2, SourceQuery::SOURCE );
	}
	
	# is TTmod installed?
	public function detect_ttmod() {
		if( $this->ttmod_version === NULL ) $this->ttmod_version = $this->get_ttmod_version();
		return ( $this->ttmod_version > 0 );
	}
	
	# Get TTmod version from database
	public function get_ttmod_version() {
		if( is_null($this->ttmod_version) ) {
			if( $this->db->table_exists('nyu_ttmod_info') ) {
				$info = $this->db->query("SELECT ttmod_version FROM nyu_ttmod_info LIMIT 1", FALSE);
				$this->ttmod_version = floatval($info['ttmod_version']);
			} else {
				$this->ttmod_version = 0;
			}
		}
		return $this->ttmod_version;
	}
	
	public function get_structures() {
		return $this->db->query( "SELECT GeoDataID FROM unmovable_objects WHERE IsComplete = 1" );
	}
	
	public function get_trees() {
		return $this->db->query( "SELECT GeoDataID FROM forest" );
	}
	
	public function get_paved_tiles( $use_cache = TRUE ) {
		// Read uncached data
		if( ! $use_cache || ! $this->db->table_exists('nyu_terrain_cache') ) {
			return $this->db->query( "SELECT gp.Substance, gp.GeoDataID FROM geo_patch gp 
									 INNER JOIN ( SELECT GeoDataID, MAX(Version) MaxVer FROM geo_patch GROUP BY GeoDataID ) tmp ON tmp.GeoDataID = gp.GeoDataID AND tmp.MaxVer = gp.Version
									 WHERE gp.Substance IN (177, 180, 181)" );
		// Get cached data
		} else {
			return $this->db->query( "SELECT * FROM nyu_terrain_cache" );
		}
	}
	
	public function get_animal_spawns() {
		if( $this->get_ttmod_version() < 1.4 ) return array();
		return $this->db->query( "SELECT * FROM nyu_ttmod_animals" );
	}
	
	public function get_tradeposts() {
		return $this->db->query( "SELECT ID, GeoDataID FROM unmovable_objects WHERE IsComplete = 1 AND ObjectTypeID = 1077" );
	}
	
	public function get_guilds() {
		return $this->db->query( "SELECT * FROM guilds ORDER BY Name" );
	}
	
	public function get_guild_claims() {
		return $this->db->query( "SELECT gl.*, g.Name AS guild, g.CreateTimestamp AS ctime, g.GuildCharter, g.GuildTag FROM guild_lands AS gl LEFT JOIN guilds g ON gl.GuildID = g.ID WHERE LandType < 4 ORDER BY guild" );
	}

	public function get_outposts() {
		return $this->db->query( "SELECT o.ID, uo.GeoDataID, ot.Name AS BuildingName, g.Name AS GuildName, uo.ObjectTypeID
									FROM outposts o
									JOIN unmovable_objects uo ON o.UnmovableObjectID = uo.ID
									JOIN objects_types ot ON uo.ObjectTypeID = ot.ID
									LEFT JOIN guilds g ON o.OwnerGuildID = g.ID" );
	}
	
	public function get_personal_claims() {
		return $this->db->query( "SELECT c.Name, c.LastName, pl.CharID, pl.ID, GeoID1, GeoID2 FROM `character` c, `personal_lands` pl WHERE pl.IsTemp = 0 AND pl.CharID = c.ID" );
	}
	
	public function get_admin_lands() {
		return $this->db->query( "SELECT ID, Name, GeoID1, GeoID2 FROM admin_lands" );
	}
	
	public function get_all_items() {
		$query = "SELECT ID, Name FROM objects_types WHERE IsUnmovableobject = 0 AND IsMovableobject = 0 AND ParentID IS NOT NULL AND ID NOT IN (SELECT DISTINCT ParentID FROM objects_types WHERE ParentID IS NOT NULL) ORDER BY Name";
		return $this->db->query( $query );
	}
	
	# Get a minified version of all characters from database
	public function get_characters_min() {
		
	}
	
	# Get all characters from database. Accepts parameter for minified/full version.
	public function get_characters( $full = TRUE ) {
		if( ! $full ) return $this->db->query( "SELECT ID, Name, LastName, ASCII( SUBSTRING(appearance,1) ) AS Gender FROM `character`" );
		return $this->db->query( "SELECT c.ID AS CharID, TRIM('\r\n' FROM TRIM(c.Name)) AS FirstName, TRIM('\r\n' FROM TRIM(c.LastName)) AS LastName, c.GuildID, c.GuildRoleId, c.AccountID, ASCII( SUBSTRING(c.appearance,1) ) AS gender, a.SteamID
									FROM `character` c, `account` a WHERE a.ID = c.AccountID ORDER BY FirstName ASC" );
	}
	
	# Get permanent server GM characters
	public function get_gms() {
		return $this->db->query( "SELECT a.SteamID, a.ID AS AccountID, c.ID AS CharacterID, c.Name AS FirstName, c.LastName FROM `character` c, `account` a WHERE c.AccountID = a.ID AND a.isGM = 1" );
	}

	# Get a guild dataset by ID
	public function get_guild( $id ) {
		$rs = $this->db->query( "SELECT * FROM guilds WHERE ID = '" . intval($id) . "'", FALSE );
		if( ! $rs ) return FALSE;
		return $rs;
	}
	
	# Get character dataset by ID
	public function get_character( $id ) {
		$rs = $this->db->query( "SELECT * FROM `character` WHERE ID = '" . intval($id) . "'", FALSE );
		if( ! $rs ) return FALSE;
		return $rs;
	}
	
	# Search characters
	public function get_characters_where( $prop, $csv ) {
		if( ! $prop || ! $csv ) return array();
		return $this->db->query( "SELECT a.SteamID, a.ID AS AccountID, c.ID AS CharacterID, c.Name AS FirstName, c.LastName FROM `character` c, `account` a WHERE a.ID = c.AccountID AND $prop IN ( $csv )" );
	}
	
	# Search accounts
	public function get_accounts_where( $perk, $csv ) {
		if( ! $perk || ! $csv ) return array();
		return $this->db->query( "SELECT * FROM account WHERE $perk IN ( $csv )" );
	}
	
	# Get guild standings
	#  optional filter on GuildIDs
	#  returns only standings not equal to 1 (War)
	public function get_guild_standings( $guild1 = NULL, $guild2 = NULL ) {
		$g1 = is_null($guild1) ? "" : "AND GuildID1 = '" . intval($guild1) . "'";
		$g2 = is_null($guild2) ? "" : "AND GuildID2 = '" . intval($guild2) . "'";
		return $this->db->query( "SELECT GuildID1, GuildID2, StandingTypeID FROM guild_standings WHERE StandingTypeID <> 1 $g1 $g2" );
	}
	
	# Get stats/facts about the server
	public function get_stat_info( $what ) {
		switch( $what ) {
			case 'chars_total':
				$r = $this->db->query( "SELECT COUNT(ID) AS total_chars FROM `character`", FALSE );
				return intval($r['total_chars']);
			break;
			case 'tracker_first':
				if( ! $this->detect_ttmod() ) return 'Never. Server mod is not installed.';
				$r = $this->db->query( "SELECT MIN(`time`) AS tracker_first FROM nyu_tracker_stats", FALSE );
				return $r['tracker_first'];
			break;
			case 'tracker_last':
				if( ! $this->detect_ttmod() ) return 'Never. Server mod is not installed.';
				$r = $this->db->query( "SELECT MAX(`time`) AS tracker_last FROM nyu_tracker_stats", FALSE );
				return $r['tracker_last'];
			break;
		}
	}
	
	# Get configured world type color
	public function get_world_color() {
		if( $this->get_ttmod_version() < 1.4 ) return FALSE;
		$rs = $this->db->query( "SELECT world_type FROM nyu_ttmod_info LIMIT 1", FALSE );
		return $rs['world_type'];
	}

	# Get JH schedule through SourceQuery interface
	public function get_judgement_hour() {
		// Return empty array if server is private. Can't detect JH values in this case
		if( ! $this->ispublic ) return FALSE;
		// Connect sourcequery socket if necessary
		$this->sq || $this->sq_connect(); 
		// In case of any problems, will return an empty array instead of throwing an exception
		try {
			$this->serverrules = $this->sq->GetRules();
			// Return FALSE if JH is disabled in config
			if( empty($this->serverrules['weekSchedule']) || ! $this->serverrules['duration'] ) return FALSE;
			// Convert the bullshit returned from SourceQuery to an offset
			// Real offset seconds minus DST seconds because Russians don't like DST
			$tz_utc_offset = date('Z') - date('I') * 3600;
			// Create an array of timestamps for the next JH events
			$days = explode( ' ', $this->serverrules['weekSchedule'] );
			$timestamps = array();
			foreach( $days AS $weekday ) $timestamps[] = strtotime("$weekday {$this->serverrules['startTime']}") - $tz_utc_offset;
			// Add a second one if it's only one timestamp
			if( count($timestamps) < 2 ) $timestamps[] = $timestamps[0] + (60*60*24*7);
			// Return timestamps and duration
			return array( 'timestamps' => $timestamps, 'duration' => intval($this->serverrules['duration']) );
		// Handle error if no communication with server possible
		} catch( Exception $e ) {
			# syslog(LOG_INFO, "Unable to query gameserver: " . $e->getMessage());
			return FALSE;
		}
	}
	
	# Get a specifc detail of the server
	public function get_server_info( $key ) {
		switch( $key ) {
			case 'Players':
				if( $this->ispublic ) {
					// Query server if necessary
					if( $this->serverinfo === NULL ) $this->query_serverinfo();
					// Return player count
					if( isSet($this->serverinfo[$key]) ) return intval($this->serverinfo[$key]);
					// If server is offline
					else return FALSE;
				// If server query disabled, but mod is installed, count online players in database. Anyway, this means we won't get maximum player count (server slots).
				} elseif( $this->detect_ttmod() ) return $this->get_db_online_count();	
				// If both disabled, this function is never called. Actually... or is it..
				else return FALSE;
			break;
			case 'MaxPlayers':
				if( ! $this->ispublic ) return FALSE;
				// Query server if necessary
				if( $this->serverinfo === NULL ) $this->query_serverinfo();
				// Return the requested info
				if( isSet($this->serverinfo[$key]) ) return intval($this->serverinfo[$key]);
				// If server is offline
				else return FALSE;
			break;
		}
		return FALSE;
	}
	
	# Fill $this->serverinfo with information from SourceQuery
	protected function query_serverinfo() {
		// Skip if private server
		if( ! $this->ispublic ) return FALSE;
		// Skip if we have the info already
		if( $this->serverinfo !== NULL ) return TRUE;
		// Connect to SQ if necessary
		if( ! $this->sq ) $this->sq_connect();
		// Fetch the information array from SourceQuery API
		try {
			$this->serverinfo = $this->sq->GetInfo();
			if( isSet($this->serverinfo['Players']) ) return TRUE;
			else return FALSE;
		// Handle error if no communication with LiF server possible
		} catch( Exception $e ) {
			syslog(LOG_INFO, "Unable to query gameserver: " . $e->getMessage());
			return FALSE;
		}
	}
	
	# Get number of online players from database
	public function get_db_online_count() {
		if( ! $this->detect_ttmod() ) return FALSE;	// Works only with TTmod installed
		$rs = $this->db->query( "SELECT COUNT(CharID) AS num_online FROM nyu_ttmod_tokens", FALSE );
		return intval($rs['num_online']);
	}
	
	# Get online player names and, optionally, their positions
	public function get_online_players() {
		if( ! $this->detect_ttmod() ) return array();	// Works only with TTmod installed
		return $this->db->query( "SELECT c.ID, c.GeoID, CONCAT(TRIM('\r\n' FROM TRIM(c.Name)), ' ', TRIM('\r\n' FROM TRIM(c.LastName))) AS FullName, ASCII( SUBSTRING(c.appearance,1) ) AS gender, g.Name AS GuildName
								  FROM `character` c JOIN `nyu_ttmod_tokens` t ON t.CharID = c.ID LEFT JOIN `guilds` g ON g.ID = c.GuildID ORDER BY c.Name ASC" );
	}

	# Cache roads to dedicated table - return cached version sum
	public function cache_paved_tiles() {
		// Create cache table if it doesn't exist
		$this->db->table_exists('nyu_terrain_cache') || $this->db->query("CREATE TABLE IF NOT EXISTS `nyu_terrain_cache` (`GeoDataID` INT(10) UNSIGNED NOT NULL, `Substance` TINYINT(3) UNSIGNED NOT NULL, PRIMARY KEY (`GeoDataID`))");
		// Clear and rewrite table with transaction safety
		$this->db->begin_transaction();
		$this->db->query( "DELETE FROM nyu_terrain_cache");
		$this->db->query( "INSERT IGNORE INTO nyu_terrain_cache
							SELECT gp.GeoDataID, gp.Substance FROM geo_patch gp 
							INNER JOIN ( SELECT GeoDataID, MAX(Version) MaxVer FROM geo_patch GROUP BY GeoDataID ) tmp 
								ON tmp.GeoDataID = gp.GeoDataID AND tmp.MaxVer = gp.Version
							WHERE gp.Substance IN (177, 180, 181)");
		$this->db->commit();
		return $this->get_geo_version_sum();				
	}
	
	# Get current sum of GeoVersions in terrain_blocks table
	public function get_geo_version_sum() {
		$rs = $this->db->query( "SELECT SUM(GeoVersion) AS ver_sum FROM terrain_blocks", FALSE );
		return intval($rs['ver_sum']);
	}
	
	# Get current ingame day of the year
	public function get_day( $daycycle ) {
		$base = 1404172800;
		$diff = time() - $base;
		$add  = round($diff * (24/$daycycle));
		$date = new DateTime();
		$date->setTimestamp( $base );
		$date->setTimezone( new DateTimeZone("UTC") );
		$date->modify( "+$add seconds" );
		$date->modify( "+12 hours" );
		return intval( $date->format('z') );
	}
	
	# Get TTmod character token details
	public function get_token_details( $token ) {
		if( ! $this->detect_ttmod() ) return FALSE;
		$rs = $this->db->query( "SELECT a.SteamID, t.CharID FROM `nyu_ttmod_tokens` t, `character` c, `account` a WHERE t.Token = '$token' AND t.CharID = c.ID AND c.AccountID = a.ID", FALSE );
		return $rs ? $rs : FALSE;
	}
	
	# Get characters by steam ID
	public function get_steam_characters( $steam_id ) {	// Old legacy code, used for GuildGUI
		if( ! $steam_id ) return array();
		$steam_id = $this->db->esc($steam_id);
		$query = "SELECT 
					c.ID, c.Name, c.LastName, ASCII( SUBSTRING(c.appearance,1) ) AS gender,
					c.GuildID, c.GuildRoleID, g.Name AS GuildName, gl.Radius
				  FROM `account` a
				  JOIN `character` c ON a.ID = c.AccountID
				  LEFT JOIN guilds g ON c.GuildID = g.ID
				  LEFT JOIN guild_lands gl ON gl.GuildID = g.ID AND gl.LandType < 4
				  WHERE c.AccountID = a.ID AND a.SteamID = '$steam_id'";
		return $this->db->query( $query );
	}
	
	# Get online stats from character
	public function get_character_stats( $id ) {	// Old legacy code, used for Character Manager
		if( ! $id = intval($id) ) return array();
		if( ! $this->detect_ttmod() ) return array();
		$rs = $this->db->query( "SELECT HOUR(ts.time) AS hour, ROUND( COUNT(1) / (SELECT SUM(1) FROM nyu_tracker_chars WHERE CharacterID = tc.CharacterID) * 100, 1) AS share
			FROM nyu_tracker_stats ts, nyu_tracker_chars tc
			WHERE ts.ID = tc.stat_id AND tc.CharacterID = $id
			GROUP BY hour ORDER BY hour" );
		// Make character online time distribution chart data
		$output = array();
		foreach( $rs AS $row ) {
			while( count($output) < intval($row['hour']) ) $output[] = array( 'hour' => count($output), 'share' => 0.0 );
			$output[] = array( 'hour' => intval($row['hour']), 'share' => floatval($row['share']) );
		}
		while( count($output) < 24 ) $output[] = array( 'hour' => count($output), 'share' => 0.0 );
		return $output;
	}
	
	public function get_character_skills( $id ) {
		if( ! $id = intval($id) ) return array();
		return $this->db->query( "SELECT st.ID AS ID, st.Parent, st.Name, round(s.SkillAmount / 10000000, 2) AS Skill 
									FROM skill_type st LEFT JOIN skills s ON st.ID = s.SkillTypeID AND s.CharacterID = '$id'
									ORDER BY st.Name" );
	}
	
	public function set_character_skill( $char_id, $skill_id, $skill_value ) {
		$skill_value = $skill_value * 10000000;
		$this->db->query( "INSERT INTO skills (CharacterID, SkillTypeID, SkillAmount) VALUES ('$char_id', '$skill_id', '$skill_value') ON DUPLICATE KEY UPDATE SkillAmount = '$skill_value'" );
		return TRUE;
	}
	
	public function get_character_equipment( $id ) {
		if( ! $id = intval($id) ) return array();
		return $this->db->query( "SELECT ot.Name, i.Quality, i.Quantity, i.Durability, i.CreatedDurability, es.Slot 
								  FROM `items` i, `equipment_slots` es, `objects_types` ot WHERE i.ID = es.ItemID AND i.ObjectTypeId = ot.ID AND es.CharacterID = '$id'" );
	}
	
	public function get_character_inventory( $id ) {
		if( ! $id = intval($id) ) return array();
		if( ! $char = $this->db->query( "SELECT RootContainerID FROM `character` WHERE ID = '$id'", FALSE ) ) return array();
		return $this->get_container_tree($char['RootContainerID']);
	}
	
	# Get content tree of an inventory container recursively
	public function get_container_tree( $id ) {
		$id = intval($id);
		$items = $this->db->query( "SELECT ot.Name, i.Quality, i.Quantity, i.Durability, i.CreatedDurability FROM `items` i, `objects_types` ot WHERE i.ContainerID = '$id' AND i.ObjectTypeId = ot.ID ORDER BY ot.Name" );
		$containers = $this->db->query( "SELECT c.ID, c.Quality, ot.Name FROM `containers` c, `objects_types` ot WHERE c.ParentID = '$id' AND c.ObjectTypeId = ot.ID ORDER BY ot.Name" );				
		foreach( $containers AS $container ) {
			$container['content'] = $this->get_container_tree($container['ID']);
			array_push($items, $container);
		}
		return $items;
	}
	
	# Get RCON schedule
	public function get_rcon_schedule() {
		if( $this->get_ttmod_version() < 1.4 ) return array();
		return $this->db->query( "SELECT *,
									CASE 
										WHEN runtime > NOW() THEN runtime 
										WHEN interval_unit = 'MINUTE' THEN DATE_ADD(runtime, INTERVAL (FLOOR(TIMESTAMPDIFF(MINUTE, runtime, NOW()) / interval_value) + 1) * interval_value MINUTE)
										WHEN interval_unit = 'HOUR' THEN DATE_ADD(runtime, INTERVAL (FLOOR(TIMESTAMPDIFF(HOUR, runtime, NOW()) / interval_value) + 1) * interval_value HOUR)
										WHEN interval_unit = 'DAY' THEN DATE_ADD(runtime, INTERVAL (FLOOR(TIMESTAMPDIFF(DAY, runtime, NOW()) / interval_value) + 1) * interval_value DAY)
										WHEN interval_unit = 'WEEK' THEN DATE_ADD(runtime, INTERVAL (FLOOR(TIMESTAMPDIFF(WEEK, runtime, NOW()) / interval_value) + 1) * interval_value WEEK)
										ELSE runtime
									END AS next_runtime 
									FROM nyu_rcon_schedule
									WHERE command != 'reload_schedule'" );
	}

	# LEGACY VERSION FOR TTMOD 1.3
	# Add RCON command to queue
	public function add_rcon_command($cmd, $param1 = '', $param2 = '', $detail = '', $minutes = 0) {
		if( ! $this->detect_ttmod() ) return FALSE;
		$detail = $this->db->esc($detail);
		return (bool)$this->db->query( "INSERT INTO nyu_rcon_queue (command, param1, param2, detail, exec_time) VALUES ('$cmd', '$param1', '$param2', '$detail', DATE_ADD(NOW(), INTERVAL $minutes MINUTE))" );
	}
	
	# Update RCON Task name
	public function update_rcon_name($task_id, $name) {
		if( $this->get_ttmod_version() < 1.4 ) return FALSE;
		$this->db->query( "UPDATE nyu_rcon_schedule SET name = '$name' WHERE ID = '$task_id'" );
		return TRUE;
	}
	
	# Update RCON Task name
	public function delete_rcon_task($task_id) {
		if( $this->get_ttmod_version() < 1.4 ) return FALSE;
		$this->db->query( "DELETE FROM nyu_rcon_schedule WHERE ID = '$task_id'" );
		return TRUE;
	}
	
	# Ban a player by character_id
	public function ban_player( $char_id ) {
		$char_id = intval($char_id);
		$this->db->query( "UPDATE `account` a, `character` c SET a.isActive = 0 WHERE a.ID = c.AccountID AND c.ID = '$char_id'" );
		return TRUE;
	}
	
	# Ban or unban a player by account_id
	public function set_account_status( $account_id, $active ) {
		$account_id = intval($account_id);
		$this->db->query( "UPDATE `account` SET isActive = " . intval($active) . " WHERE ID = '$account_id'" );
		return TRUE;
	}
	
	# Delete a character from database
	public function delete_character( $char_id ) {
		$rs = $this->db->query("SELECT AccountID, GuildRoleID FROM `character` WHERE ID = '$char_id'", FALSE);
		if( ! $rs || intval($rs['GuildRoleID']) === 1 ) return FALSE;
		$this->db->query("CALL p_deleteCharacter($char_id, {$rs['AccountID']})");
		return TRUE;
	}
	
	# Get char permissions on a guild claim
	public function get_char_permissions( $char_id, $guild_id ) {
		$char_id = intval($char_id);
		$guild_id = intval($guild_id);
		$permissions = array();
		// Grab source character and target guild info
		$char = $this->db->query( "SELECT ID, GuildID, GuildRoleID FROM `character` WHERE ID = '$char_id'", FALSE );
		$guild = $this->db->query( "SELECT c.ID AS ClaimID, c.GuildLandID FROM `claims` c, `guild_lands` gl WHERE c.GuildLandID = gl.ID AND gl.LandType < 4 AND gl.GuildID = '$guild_id'", FALSE );
		// Abort if any doesn't exist
		if( ! $char || ! $guild ) return FALSE;
		// is character guild member of owner guild?
		if( intval($char['GuildID']) === $guild_id ) {
			// Apply permissions for own rank
			$permissions[] = $this->db->query( "SELECT cr.CanEnter, cr.CanBuild, cr.CanClaim, cr.CanUse, cr.CanDestroy 
												FROM claim_subjects cs
												INNER JOIN claim_rules cr ON cr.ClaimSubjectID = cs.ID AND cr.ClaimID = '{$guild['ClaimID']}'
												WHERE cs.GuildRoleID = '{$char['GuildRoleID']}'", FALSE );
		}
		// is character member in some other guild?
		if( $char['GuildID'] ) {
			// is a standing set?
			$std = $this->db->query( "SELECT StandingTypeID FROM `guild_standings` WHERE GuildID1 = '$guild_id' AND GuildID2 = '{$char['GuildID']}'", FALSE );
			if( $std ) {
				// Grab standing permissions
				$permissions[] = $this->db->query( "SELECT cr.CanEnter, cr.CanBuild, cr.CanClaim, cr.CanUse, cr.CanDestroy
													FROM claim_subjects cs
													INNER JOIN claim_rules cr ON cr.ClaimSubjectID = cs.ID AND cr.ClaimID = '{$guild['ClaimID']}'
													WHERE cs.StandingTypeID = '{$std['StandingTypeID']}'", FALSE );
			}
			// is some special shit set for the guild of this char?
			$permissions[] = $this->db->query( "SELECT cr.CanEnter, cr.CanBuild, cr.CanClaim, cr.CanUse, cr.CanDestroy
												FROM claim_rules cr, claim_subjects cs
												WHERE cr.ClaimID = '{$guild['ClaimID']}' AND cr.ClaimSubjectID = cs.ID AND cs.GuildID = '{$char['GuildID']}'", FALSE );
		}
		// is some special privilege set for this character?
		$permissions[] = $this->db->query( "SELECT cr.CanEnter, cr.CanBuild, cr.CanClaim, cr.CanUse, cr.CanDestroy
											FROM `claim_rules` cr, `claim_subjects` cs
											WHERE cr.ClaimID = '{$guild['ClaimID']}' AND cr.ClaimSubjectID = cs.ID AND cs.CharID = '$char_id'", FALSE );
		// Forge result from highest privileges found
		$output = array( 'CanEnter' => FALSE, 'CanBuild' => FALSE, 'CanClaim' => FALSE, 'CanUse' => FALSE, 'CanDestroy' => FALSE );
		foreach( $permissions AS $rs ) {
			if( ! is_array($rs) ) continue;
			foreach( $rs AS $key => $value ) {
				$output[$key] = (bool)intval($value) ? TRUE : $output[$key];
			}
		}
		return $output;
	}

}