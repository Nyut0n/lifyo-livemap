<?php

	if( ! isSet($server) ) die;

	session_write_close();

	switch( $_REQUEST['ajax'] ) {
		
		case 'get_base_data':
		
			$data = array();
			$data['healthCheck'] = $server->check_db_connection();
			if( $data['healthCheck'] ) {
		
				// Fetch data from gameserver database
				$data['guildClaims'] = $mygroup->privileges['claims'] ? $server->get_guild_claims() : array();
				$data['adminClaims'] = $mygroup->privileges['aclaim_layer'] ? $server->get_admin_lands() : array();
				$data['persoClaims'] = $mygroup->privileges['pclaim_layer'] ? $server->get_personal_claims() : array();
				$data['outposts']	 = $mygroup->privileges['outposts'] ? $server->get_outposts() : array();
				$data['tradeposts']  = $mygroup->privileges['trading_posts'] ? $server->get_tradeposts() : array();
				$data['standings']   = $mygroup->privileges['standings'] ? $server->get_guild_standings() : array();
				$data['animalSpawns']= $mygroup->privileges['animal_spawns'] ? $server->get_animal_spawns() : array();
				$characters = $server->get_characters();
				
				// Fetch custom data from config database
				$data['pois']  = Livemap::get_custom_pois();
				$data['areas'] = Livemap::get_custom_areas();

				// Convert GeoIDs
				foreach( $data['adminClaims'] AS &$claim ) {
					list($claim['x1'], $claim['y1']) = Livemap::geoid2pixelpos($claim['GeoID1']);
					list($claim['x2'], $claim['y2']) = Livemap::geoid2pixelpos($claim['GeoID2']);
					$claim['SizeX'] = 1 + abs($claim['x1'] - $claim['x2']);
					$claim['SizeY'] = 1 + abs($claim['y1'] - $claim['y2']);
				}
				unset($claim);
				foreach( $data['persoClaims'] AS &$claim ) {
					list($claim['x1'], $claim['y1']) = Livemap::geoid2pixelpos($claim['GeoID1']);
					list($claim['x2'], $claim['y2']) = Livemap::geoid2pixelpos($claim['GeoID2']);
					$claim['SizeX'] = 1 + abs($claim['x1'] - $claim['x2']);
					$claim['SizeY'] = 1 + abs($claim['y1'] - $claim['y2']);
				}
				unset($claim);
				foreach( $data['outposts'] AS &$outpost ) {
					list($outpost['x'], $outpost['y']) = Livemap::geoid2pixelpos($outpost['GeoDataID']);
				}
				foreach( $data['tradeposts'] AS &$tp ) {
					list($tp['x'], $tp['y']) = Livemap::geoid2pixelpos($tp['GeoDataID']);
				}
				foreach( $data['animalSpawns'] AS &$spawn ) {
					list($spawn['x'], $spawn['y']) = Livemap::geoid2pixelpos($spawn['GeoID']);
				}
				
				// Guild Claims
				foreach( $data['guildClaims'] AS &$claim ) {
					// Coordinates and dimension of guild claim circle
					list($claim['x'], $claim['y']) = Livemap::geoid2pixelpos($claim['CenterGeoID']);
					$claim['d'] = intval($claim['Radius']);
					$claim['r'] = ceil($claim['d']/2);
					// Misc info about guild
					$claim['name'] = htmlentities($claim['guild']);
					$claim['founded'] = date('Y-m-d', strtotime($claim['ctime']));
					$claim['GuildTier'] = Guild::get_claim_tier($claim['d']);
					$claim['bcount'] = 0;
					// Unpack charter info
					$charter_data = json_decode($claim['GuildCharter'], TRUE);
					if( isSet($charter_data['GuildCharterPublic']) && $charter_data['GuildCharterPublic'] ) {
						$claim['GuildCharterPublic'] = TRUE;
						$claim['SanitizedCharter'] = htmlentities($charter_data['GuildCharter']);
					} else {
						$claim['GuildCharterPublic'] = FALSE;
						$claim['SanitizedCharter'] = "";
						$claim['GuildCharter'] = "";
					}
					// Build list of guild members
					$guild_id = $claim['GuildID'];
					$claim['members'] = array_values(array_filter($characters, function($c) use($guild_id) { return $c['GuildID'] === $guild_id; }));
					$claim['mcount'] = count($claim['members']);
					foreach( $claim['members'] AS $i => $member ) $claim['members'][$i]['FullName'] = htmlspecialchars("{$member['FirstName']} {$member['LastName']}");				
				}
				unset($claim);
				
				// Calculate building count for guild claims
				if( $mygroup->privileges['struct_count'] ) {
					// this may be huge on a highly populated server with many guilds and buildings
					// maybe find a better solution some day
					$buildings = $server->get_structures();
					foreach( $buildings AS $b ) {
						foreach( $data['guildClaims'] AS &$c ) {
							if( Livemap::get_distance($c['CenterGeoID'], $b['GeoDataID']) <= $c['r'] ) {
								$c['bcount']++;
								break;
							}
						}
						unset($c);
					}
				}
				
			}

			// Output as json
			echo json_encode( $data );
			
		break;
		
		# Online player list and positions
		case 'get_players':
		
			// Get online players list if permitted
			$list = $mygroup->privileges['online_list'] ? $server->get_online_players() : array();
			foreach( $list AS &$player ) {
				// Add player positions if permitted
				if( $mygroup->privileges['player_layer'] ) {
					list($player['x'], $player['y']) = Livemap::geoid2pixelpos($player['GeoID']);
				}
				unset($player['GeoID']);
			}
			
			$data = array(
				'max'    => $server->get_server_info('MaxPlayers'),
				'online' => $server->get_server_info('Players'),
				'list'   => $list
			);

			echo json_encode($data);
			
		break;
		
		# Get server details from SourceQuery
		case 'get_server_details':
		
			$jh = $server->get_judgement_hour();
			
			$result = array(
				'sq_rules' => $server->serverrules,
				'judgementHour' => $jh,
				'world_color' => $server->get_world_color(),
				'ttmod' => $server->get_ttmod_version(),
			);
		
			echo json_encode($result);
			
		break;
		
		# Get paved roads
		case 'get_paved':

			if( $mygroup->privileges['road_layer'] ) {
				
				@set_time_limit(600);
				
				// Get cached and current version sum
				$rs = Livemap::$db->query( "SELECT geo_cache_version FROM {$config['table_c']} WHERE ID = '$livemap_id'", FALSE );
				$cached_version = intval($rs['geo_cache_version']);
				$current_version = $server->get_geo_version_sum();

				// Need full refresh after FT map-refresh script or server wipe
				$force_refresh = ( $cached_version > $current_version );
				
				// Use cache if it's not terribily outdated
				$use_cache = ( $current_version < ($cached_version + 3000) && ! $force_refresh );
				
				// Convert for output
				$data = array( 'stone' => [], 'marble' => [], 'slate' => [] );
				
				// Read data from server
				$rs = $server->get_paved_tiles($use_cache);
				
				// Assign records to result array
				$k = array('177' => 'stone', '180' => 'marble', '181' => 'slate');
				foreach( $rs AS &$tile ) {
					$data[$k[$tile['Substance']]][] = Livemap::geoid2pixelpos(intval($tile['GeoDataID']));
					unset($tile);
				}

				// Output json data
				ob_start();
				echo json_encode( $data );
				
				// Close connection to browser
				ignore_user_abort(TRUE);
				header( 'Connection: close' );
				header( 'Content-Length: '.ob_get_length() );
				ob_end_flush();
				ob_flush();
				flush();
				
				// Update cache if version changed
				if( $current_version > $cached_version + 500 || $force_refresh ) {
					Livemap::$db->query( "UPDATE {$config['table_c']} SET geo_cache_version = '$current_version' WHERE ID = '$livemap_id'" );
					$server->cache_paved_tiles();
				}

			} else {
				
				echo "[]";
				
			}
			
		break;
		
		# Get buildings / structures
		case 'get_structures':
		
			if( $mygroup->privileges['struct_layer'] ) {

				$buildings = array_map('reset', $server->get_structures());
				$buildings = array_map('Livemap::geoid2pixelpos', $buildings);
				echo json_encode( $buildings );
				
			} else echo "[]";
				
		break;
		
		case 'get_trees':
		
			if( $mygroup->privileges['trees_layer'] ) {

				$trees = array_map('reset', $server->get_trees());
				$trees = array_map('Livemap::geoid2pixelpos', $trees);
				echo json_encode( $trees );
				
			} else echo "[]";

		break;
		
		case 'get_regions':
		
			echo json_encode( $server->passthru_db_query("SELECT ID, RegionID FROM terrain_blocks") );

		break;
		
		case 'player_inventory':
		
			if( $mygroup->privileges['manage_chars'] && isSet($_GET['char_id']) && is_numeric($_GET['char_id']) ) {
				
				$data = array(
					'inventory' => $server->get_character_inventory($_GET['char_id']),
					'equipment' => $server->get_character_equipment($_GET['char_id'])
				);
				
				echo json_encode( $data );
				
			} else echo "{}";

		break;
		
		case 'player_statistics':
		
			if( $mygroup->privileges['manage_chars'] && isSet($_GET['char_id']) ) {
				
				$id = intval($_GET['char_id']);
	
				echo json_encode( $server->get_character_stats($id) );
				
			} else echo "{}";
				
		break;
		
		case 'player_skills':
		
			if( $mygroup->privileges['manage_chars'] && isSet($_GET['char_id']) ) {
				
				$id = intval($_GET['char_id']);
	
				echo json_encode( $server->get_character_skills($id) );
				
			} else echo "{}";
				
		break;
		
		case 'get_rcon_schedule':
		
			$mygroup->privileges['rcon'] || die('[]');
			
			echo json_encode($server->get_rcon_schedule());
		
		break;
		
		case 'get_database_time':
		
			$mygroup->privileges['rcon'] || die;
			
			$result = [ 'time' => $server->get_database_time() ];
			
			echo json_encode($result);
		
		break;
		
		# Update player skills
		case 'skill_update':
		
			if( $mygroup->privileges['manage_chars'] && isSet($_GET['char_id'], $_GET['skill_id'], $_GET['skill_value']) ) {
				
				$char_id = intval($_GET['char_id']);
				$skill_id = intval($_GET['skill_id']);
				$skill_value = floatval($_GET['skill_value']);
				
				if( $skill_value < 0 || $skill_value > 100 ) {
					echo json_encode( ['result' => 'ERROR'] );
					die;
				}
					
				if( ! $server->set_character_skill($char_id, $skill_id, $skill_value) ) {
					echo json_encode( ['result' => 'ERROR'] );
					die;
				}
				
				echo json_encode( ['result' => 'OK'] );
				
			} else {
				
				echo json_encode( ['result' => 'ERROR'] );
				
			}
		
		break;
		
		# Check my privileges
		case 'gg_privcheck':
		
			if( ! (bool)intval($config['guildmanager']) || ! Livemap::get_steam_id() ) Livemap::error_json();
			
			$guild_id = intval($_GET['guild']);
			$char_id  = intval($_GET['char']);
			$steam_id = Livemap::get_steam_id();
			
			// Get characters guild
			$tmp = $server->passthru_db_query("SELECT GuildID FROM `character` c, `account` a WHERE c.AccountID = a.ID AND a.SteamID = '$steam_id' AND c.ID = '$char_id'", FALSE);
			$tmp || Livemap::error_json();
			$char_guild_id = intval($tmp['GuildID']);
			
			// Is member?
			$is_member = ($char_guild_id === $guild_id);

			// Get Standing
			$standing = $server->get_guild_standings($guild_id, $char_guild_id);
			$standing_id = empty($standing) ? 1 : intval($standing[0]['StandingTypeID']);

			$result = array(
				'permissions' => $server->get_char_permissions($char_id, $guild_id),
				'standing' => $standing_id,
				'is_member' => $is_member
			);
			
			$result || Livemap::error_json();

			echo json_encode($result);
			
		break;
		
		# GuildGUI: Get memberlist
		case 'gg_memberlist':
		
			if( ! (bool)intval($config['guildmanager']) || ! Livemap::get_steam_id() ) die('ERROR');
			
			$steam_id = Livemap::get_steam_id();
			$guild_id = intval($_GET['guild']);
			
			// Make sure we're member of this guild
			$rs = $server->passthru_db_query("SELECT GuildID FROM `character` c, `account` a WHERE c.AccountID = a.ID AND a.SteamID = '$steam_id' AND c.GuildID = '$guild_id'", FALSE);
			if( ! $rs ) die('ERROR');
			
			// Create guild object
			$guild = new Guild( $server->get_db() );
			$guild->load($guild_id) || Livemap::error_json();
			
			// Get member list
			$members = $guild->get_members();
			foreach( $members AS &$member ) {
				$member['IsLeader'] = (intval($member['ID']) === $guild->leader_char_id);
			}

			if( ! $members ) die('ERROR');
			echo json_encode($members);
			
		break;
		
		# GuildGUI: Update permission
		case 'gg_togglepriv':

			// Check input
			if( ! (bool)intval($config['guildmanager']) || ! Livemap::get_steam_id() ) Livemap::error_json();
			if( ! isSet($_GET['sourceGuild'], $_GET['which'], $_GET['subjectType'], $_GET['subjectID']) ) Livemap::error_json();
			if( ! in_array( $_GET['which'], ['CanEnter', 'CanBuild', 'CanClaim', 'CanUse', 'CanDestroy']) ) Livemap::error_json();

			// Init source guild
			$guild = new Guild($server->get_db());
			$guild->load(intval($_GET['sourceGuild'])) || Livemap::error_json();
			
			// Check own privs
			$guild->is_admin(Livemap::get_steam_id(), TRUE) || Livemap::error_json();
			
			// Load subject type permission list
			$match = NULL;
			$subject_id = intval($_GET['subjectID']);
			switch( $_GET['subjectType'] ) {
				case 'rank':
					foreach( $guild->get_rank_permissions() AS $row ) {
						if( intval($row['GuildRoleID']) === $subject_id ) {
							$match = $row;
							break;
						}
					}
					break;
				case 'standing':
					foreach( $guild->get_standing_permissions() AS $row ) {
						if( intval($row['StandingTypeID']) === $subject_id ) {
							$match = $row;
							break;
						}
					}
					break;
				case 'guild':
					foreach( $guild->get_guild_permissions() AS $row ) {
						if( intval($row['GuildID']) === $subject_id ) {
							$match = $row;
							break;
						}
					}
					break;
				case 'char':
					foreach( $guild->get_char_permissions() AS $row ) {
						if( intval($row['CharID']) === $subject_id ) {
							$match = $row;
							break;
						}
					}
					break;
				default:
					Livemap::error_json();
			}
			
			// Make sure we have a match
			$match || Livemap::error_json();
			
			// Toggle requested permission
			$match[$_GET['which']] = 1 - intval($match[$_GET['which']]);

			// Process action in database
			$code = $guild->change_permission($_GET['subjectType'], $subject_id, $match['CanEnter'], $match['CanBuild'], $match['CanClaim'], $match['CanUse'], $match['CanDestroy']);
			if( $code === 1 ) {
				$response = ['success' => TRUE, 'value' => $match[$_GET['which']]];
				echo json_encode($response);
			} else Livemap::error_json();
			
		break;
		
	}

	die;
