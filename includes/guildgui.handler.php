<?php

	// Check steam login and command 
	Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman";
	if( ! isSet($_REQUEST['command']) || ! Livemap::get_steam_id() ) Livemap::error_redirect();
	
	// Check source guild id
	if( ! isSet($_REQUEST['source_guild_id']) || ! $_REQUEST['source_guild_id'] ) Livemap::error_redirect();

	// Init source guild
	$source_id = intval($_REQUEST['source_guild_id']);
	$guild = new Guild($server->get_db());
	$guild->load($source_id) || Livemap::error_redirect();
	
	// Check permission and set producer char id
	$guild->is_admin(Livemap::get_steam_id(), TRUE) || Livemap::error_redirect(Livemap::get_ui_string(239));

	switch( $_REQUEST['command'] ) {
		
		case 'kick_member':
		
			// Check required input vars 
			if( ! isSet($_GET['subject_id']) ) Livemap::error_redirect();
			
			$subject_id = intval($_GET['subject_id']);
			if( ! $subject_id ) Livemap::error_redirect();
			
			// Process action in database
			$code = $guild->kick_member($subject_id);
			($code === 1) && Livemap::success_redirect(Livemap::get_ui_string(242));
			Livemap::error_redirect(Livemap::get_ui_string(241+$code));
			
		break;
		
		case 'update_charter':

			// Check required input vars 
			if( ! isSet($_POST['charter'], $_POST['public']) ) Livemap::error_redirect();

			// Process action in database
			$code = $guild->update_charter($_POST['charter'], (bool)$_POST['public']));
			($code === 1) && Livemap::success_redirect(Livemap::get_ui_string(242));
			Livemap::error_redirect(Livemap::get_ui_string(241+$code));
			
		break;
		
		case 'change_rank':

			// Check required input vars 
			if( ! isSet($_POST['subject_id'], $_POST['rank']) ) Livemap::error_redirect();
			
			$subject_id = intval($_POST['subject_id']);
			$rank_id = intval($_POST['rank']);
			if( ! $subject_id || ! $rank_id ) Livemap::error_redirect();
			
			// Process action in database
			$code = $guild->change_rank($subject_id, $rank_id);
			($code === 1) && Livemap::success_redirect(Livemap::get_ui_string(242));
			Livemap::error_redirect(Livemap::get_ui_string(241+$code));
			
		break;

		case 'change_standing':
	
			Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman&m=standings&gid=$source_id";
		
			// Check input
			if( ! isSet($_POST['target_guild'], $_POST['standing']) || ! is_array($_POST['target_guild']) || empty($_POST['target_guild']) ) Livemap::error_redirect();
			
			// Check standing
			$standing = intval($_POST['standing']);
			if( $standing < 1 || $standing > 5 ) Livemap::error_redirect();
			
			// Loop target guilds, request tickets
			$tickets = array();
			foreach( $_POST['target_guild'] AS $target_id ) {
				$tickets[] = $guild->change_standing($target_id, $standing, TRUE);
			}
			
			// Process tickets
			$success_tickets = 0;
			foreach( $tickets AS $ticket ) {
				$code = $guild->process_ticket($ticket);
				if( $code === -1 ) Livemap::error_redirect(Livemap::get_ui_string(240));
				$success_tickets += $code;
			}
			
			// Report result
			$success_tickets || Livemap::error_redirect(Livemap::get_ui_string(241));
			Livemap::success_redirect(Livemap::get_ui_string(242));
		
		break;
		
		case 'add_rule':

			Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman&m=permissions&gid=$source_id";
		
			// Guild Rule
			if( isset($_POST["target_guild_id"]) ) {
				Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman&m=permissions&gid=$source_id#tab-guild";
				$subject_type = 'guild';
				$subject_id = intval($_POST["target_guild_id"]);
				// Check for valid guild id
				$server->get_guild($subject_id) || Livemap::error_redirect("Invalid Guild ID");
				// Check for existing rules
				foreach( $guild->get_guild_permissions() AS $row ) {
					if( intval($row['GuildID']) === $subject_id ) Livemap::error_redirect("A rule for this guild exists already");
				}
			// Char Rule
			} elseif( isset($_POST["target_char_id"]) ) {
				Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman&m=permissions&gid=$source_id#tab-char";
				$subject_type = 'char';
				$subject_id = intval($_POST["target_char_id"]);
				// Check for valid guild id
				$server->get_character($subject_id) || Livemap::error_redirect("Invalid Character ID");
				// Check for existing rules
				foreach( $guild->get_char_permissions() AS $row ) {
					if( intval($row['CharID']) === $subject_id ) Livemap::error_redirect("A rule for this character exists already");
				}
			// Undefined - error out
			} else Livemap::error_redirect();
			
			// Process action in database with default ruleset
			$code = $guild->change_permission($subject_type, $subject_id, 1, 0, 0, 0, 0);
			($code === 1) && Livemap::success_redirect(Livemap::get_ui_string(242));
			Livemap::error_redirect(Livemap::get_ui_string(241+$code));
		
		break;
		
		case 'delete_permission':
		
			Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=guildman&m=permissions&gid=$source_id";
		
			if( isSet($_GET['char_id']) ) {
				$type = 'char';
				$subject_id = intval($_GET['char_id']);
			}
			elseif( isSet($_GET['guild_id']) ) {
				$type = 'guild';
				$subject_id = intval($_GET['guild_id']);
			} else {
				Livemap::error_redirect();
			}

			// Process action in database
			$code = $guild->delete_permission($type, $subject_id);
			($code === 1) && Livemap::success_redirect(Livemap::get_ui_string(242));
			Livemap::error_redirect(Livemap::get_ui_string(241+$code));

		break;
				
	}
	
?>