<?php

	if( ! defined('VERSION') ) die;

	$my_steam_id = Livemap::get_steam_id();

	if( ! $config['guildmanager'] || ! $my_steam_id ) {
		
		$content = new Template('html/403.html');
		
	} else {

		# Subpages
		if( isSet($_GET['m'], $_GET['gid']) ) {
			
			# Load own guild info
			$guild = new Guild( $server->get_db() );
			if( ! $guild->load($_GET['gid']) || ! $guild->is_member($my_steam_id) ) Livemap::error_redirect("Error: Failed to access guild information.");
			
			switch($_GET['m']) {
				
				# Standings
				case 'standings':
					$content = new Template('html/guildman_standings.html');
					
					$standings = $guild->get_standings();
					foreach( $standings AS &$standing ) {
						$standing['GuildTier'] = Guild::get_claim_tier( intval($standing['Radius']) );
						$standing['Distance'] = round( Livemap::get_distance($guild->center_geoid, intval($standing['CenterGeoID'])) * 2);
					}
					unset($standing);
					
					$content->assign('JSON_GUILDS', json_encode($standings))
					->assign('isLeader', ($guild->leader_steam_id === $my_steam_id))
					->assign('isAdmin', $guild->is_admin($my_steam_id));

				break;
				
				# Permissions
				case 'permissions':
					$content = new Template('html/guildman_permissions.html');
					
					Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman";
					$guild->is_admin($my_steam_id) || Livemap::error_redirect("Only guild leaders can see and edit permissions.");
					
					$content->assign( 'JSON_RANK_PERMISSIONS', json_encode($guild->get_rank_permissions()) )
					->assign( 'JSON_STANDING_PERMISSIONS', json_encode($guild->get_standing_permissions()) )
					->assign( 'JSON_GUILD_PERMISSIONS', json_encode($guild->get_guild_permissions()) )
					->assign( 'JSON_CHAR_PERMISSIONS', json_encode($guild->get_char_permissions()) )		
					->assign( 'JSON_ALL_CHARS', json_encode($server->get_characters(FALSE)) )
					->assign( 'JSON_ALL_GUILDS', json_encode($server->get_guilds()) );
					
				break;
				
			}
			
			$content->assign('MyGuildID', $guild->id)
			->assign('MyGuildName', htmlspecialchars($guild->name))
			->assign('JSON_PERMISSIONS', json_encode(Livemap::get_ui_permissions()))
			->assign('JSON_GUILDTYPES', json_encode(Livemap::get_ui_guildtypes()))
			->assign('JSON_STANDINGS', json_encode(Livemap::get_ui_standings()))
			->assign('JSON_RANKS', json_encode(Livemap::get_ui_ranks()));
			
		}
		
		# Guild selection and summary page
		if( ! isSet($content) ) {
			
			$content = new Template('html/guildman.html');
			
			$ui_ranks = Livemap::get_ui_ranks();
		
			// Get own characters
			$mychars = $server->get_steam_characters($my_steam_id);
			foreach( $mychars AS $key => &$char ) {
				// Skip other chars if CharID is set in session (GuildGUI)
				if( isSet($_SESSION['CharID']) && $_SESSION['CharID'] !== intval($char['ID']) ) {
					unset($mychars[$key]);
					continue;
				}
				// Has guild?
				if( $char['has_guild'] = (bool)$char['GuildID'] ) {
					$guild = new Guild( $server->get_db() );
					$guild->load($char['GuildID']);
					$char['has_claim'] = (bool)$char['Radius'];
					$char['isAdmin'] = $guild->is_admin($my_steam_id);
					$char['GuildRoleName'] = Livemap::get_ui_rank($char['gender'], $char['GuildRoleID']);
					$char['GuildTier'] = Guild::get_claim_tier(intval($char['Radius']));
					$char['GuildType'] = Livemap::get_ui_guildtypes()[$guild->type];
					$charter = $guild->get_detail('GuildCharter');
					$char['GuildCharter'] = ( $charter ? htmlspecialchars($charter) : "" );
					$char['GuildCharterPublic'] = intval( $guild->get_detail('GuildCharterPublic') );
					$char['MemberCount'] = count($guild->get_members());
				}
			}
			sort($mychars); // Rebuild array index
			
			$content->assign('characters', $mychars)
			->assign('has_chars', (bool)$mychars)
			->assign('JSON_CHARACTERS', json_encode($mychars))
			->assign('JSON_STANDINGS', json_encode(Livemap::get_ui_standings()))
			->assign('JSON_RANKS', json_encode($ui_ranks))
			->assign('JSON_PERMISSIONS', json_encode(Livemap::get_ui_permissions()))
			->assign('JSON_ALL_GUILDS', json_encode($server->get_guilds()));
			
		}

	}


	// Ingame view ? Overwrite main template
	$content->assign( 'INGAME', (isSet($_SESSION['InGame']) && $_SESSION['InGame']) );
	if( isSet($_SESSION['InGame']) && $_SESSION['InGame'] ) {
		$html = new Template('html/main_ingame.html');
	}
