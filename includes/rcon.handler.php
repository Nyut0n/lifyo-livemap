<?php

	Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=rcon";
	$message_ok = "Command was sent to server and will be executed within the next 20 seconds.";
	
	$message_functions = array( 'centerprint', 'centerprintall', 'bottomprint', 'bottomprintall', 'system_msg', 'system_msg_all', 'local_msg', 'local_msg_all' );
	$server_functions  = array( 'forest_grow', 'crops_grow', 'patch_maint', 'spawn_maint' );
	
	// Get Character List
	$char_list = isSet($_POST['characters']) ? array_map('intval', explode(',', $_POST['characters'])) : array();
	$char_list = array_diff(array_unique($char_list), array(0));
	$char_string = implode(',', $char_list);
	
	// Check basic RCON privilege
	$mygroup->privileges['rcon'] || Livemap::error_redirect();
	
	// Check command input
	isSet($_POST['command']) || Livemap::error_redirect();

	switch( $_POST['command'] ) {
		
		# Message all players 
		case 'broadcast':
		
			Livemap::$redirect .= "#tab-global";

			if( ! isSet($_POST['message'], $_POST['function']) ) Livemap::error_redirect();
		
			$message  = trim( $cdb->esc($_POST['message']) );
			$function = $_POST['function'];
			$duration = isSet($_POST['seconds']) ? intval($_POST['seconds']) : 10;
			
			// Check message
			if( strlen($message) < 1 ) Livemap::error_redirect("Message can't be empty");
			// Check function
			if( ! in_array($function, $message_functions) ) Livemap::error_redirect();
			// Check duration
			if( $duration < 5 || $duration > 120  ) Livemap::error_redirect();
			
			$server->add_rcon_command( $function, $duration, '', $message );
			Livemap::log_action( 'rcon_message_all', "($function) $message" );

			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Trigger a function
		case 'exec_function':
		
			Livemap::$redirect .= "#tab-global";
		
			if( ! $mygroup->privileges['rcon_advanced'] ) Livemap::error_redirect();
			
			// Check input
			if( ! isSet($_POST['function']) ) Livemap::error_redirect();
			if( ! in_array( $_POST['function'], $server_functions ) ) Livemap::error_redirect('Invalid function called.');
			
			$server->add_rcon_command( $_POST['function'] );
			Livemap::log_action( 'rcon_exec_function', $_POST['function'] );

			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Run a custom command
		case 'exec_command':
		
			Livemap::$redirect .= "#tab-global";
		
			if( ! $mygroup->privileges['rcon_advanced'] ) Livemap::error_redirect();
		
			// Check input
			if( ! isSet($_POST['command_string']) ) Livemap::error_redirect();
			
			$command = $cdb->esc( trim($_POST['command_string']) );
			
			if( strlen($command) < 1 ) Livemap::error_redirect("Command can't be empty");
			
			$server->add_rcon_command( 'exec_command', '', '', $command );
			Livemap::log_action( 'rcon_exec_command', $command );
		
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Teleport player
		case 'teleport_player':

			if( ! isSet($_POST['dst_type']) ) Livemap::error_redirect();
		
			// Check players
			if( empty($char_list) ) Livemap::error_redirect("No players selected");
			
			// to other player
			if( $_POST['dst_type'] === 'player' ) {
				if( ! isSet($_POST['subject_id']) || intval($_POST['subject_id']) < 1 ) Livemap::error_redirect();
				$subject = intval($_POST['subject_id']);
				$subject_type = 'player';
				$logdetail = "CharID $char_string to CharID $subject";
			// to certain GeoID
			} else {
				if( ! isSet($_POST['geo_id']) || intval($_POST['geo_id']) < 1000000 ) Livemap::error_redirect();
				$subject = intval($_POST['geo_id']);
				$subject_type = 'location';
				$logdetail = "CharID $char_string to GeoID $subject";
			}

			// Send command to server
			foreach( $char_list AS $char_id ) $server->add_rcon_command( 'teleport', $char_id, $subject, $subject_type );

			Livemap::log_action( 'rcon_teleport', $logdetail );
			Livemap::success_redirect($message_ok);
		
		# Kick player
		case 'kick':

			// Check input
			isSet($_POST['message']) || Livemap::error_redirect();
			
			// Check players
			if( empty($char_list) ) Livemap::error_redirect("No players selected");
		
			// Process kick message
			$message = $cdb->esc($_POST['message']);
			if( strlen($message) < 1 ) $message = "You were kicked from the server via RCON";
			
			// Send command to server
			foreach( $char_list AS $char_id ) $server->add_rcon_command( 'kick_player', $char_id, '', $message );
			
			Livemap::log_action( 'rcon_kick_player', "CharID $char_string / Message: $message" );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Ban player
		case 'ban':
		
			// Check input
			isSet($_POST['bantype']) || Livemap::error_redirect();
			$permaban = ( $_POST['bantype'] === 'permanent' );
			
			if( $permaban ) {
				$message = "You've been permanently banned from this server.";
				$duration = 0;
			} else {
				$duration   = intval($_POST['duration']);
				$duration_h = floor($duration/60);
				$duration_m = $duration % 60;
				$message = "You are banned from this server for $duration_h hours and $duration_m minutes.";
			}

			// Send commands to server
			foreach( $char_list AS $char_id ) {
				// Ban the account of this character
				$server->ban_player( $char_id );
				// Kick player from the server
				$server->add_rcon_command( 'kick_player', $char_id, '', $message );
				// Schedule unban if duration was set
				if( ! $permaban ) {
					$server->add_rcon_command( 'unban_player', $char_id, '', '', $duration );
					Livemap::log_action( 'rcon_ban_player', "CharID $char_id / Duration: $duration s" );
				} else {
					Livemap::log_action( 'rcon_ban_player', "CharID $char_id / Duration: Permanent" );
				}
			}
			
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Message player
		case 'message_player':
		
			// Check input
			if( ! isSet($_POST['function'], $_POST['message']) ) Livemap::error_redirect();
		
			$duration = isSet($_POST['seconds']) ? intval($_POST['seconds']) : 10;
			$message  = $cdb->esc($_POST['message']);
			
			// Check message
			if( strlen($message) < 1 ) Livemap::error_redirect("Message can't be empty");
			// Check function
			if( ! in_array($_POST['function'], $message_functions) ) Livemap::error_redirect();
			// Check duration
			if( $duration < 5 || $duration > 120 ) Livemap::error_redirect();
			
			// Send command to server
			foreach( $char_list AS $char_id ) $server->add_rcon_command( $_POST['function'], $char_id, $duration, $message );
			
			Livemap::log_action( 'rcon_message_player', "CharID $char_string / Message: $message" );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Message player
		case 'insert_item':

			// Check input
			if( ! isSet($_POST['item_id'], $_POST['item_name_id'], $_POST['item_data_type'], $_POST['quantity'], $_POST['quality'], $_POST['durability']) ) Livemap::error_redirect();
		
			$quantity = intval($_POST['quantity']);
			$quality = intval($_POST['quality']);
			$durability = intval($_POST['durability']);
			$object_id = $_POST['item_data_type'] === 'id' ? intval($_POST['item_id']) : intval($_POST['item_name_id']);
			
			// Checks
			if( $quantity < 1 || $quantity > 1000 ) Livemap::error_redirect("Invalid Quantity");
			if( $quality < 1 || $quality > 1000 ) Livemap::error_redirect("Invalid Quality");
			if( $object_id < 1 || $object_id > 9999 ) Livemap::error_redirect("Invalid Item ID");
			if( $durability < 1 || $durability > 20000 ) Livemap::error_redirect("Invalid Durability");
			
			// Send command to server
			foreach( $char_list AS $char_id ) $server->add_rcon_command( 'insert_item', $char_id, $object_id, "$quantity|$quality|$durability" );
			
			Livemap::log_action( 'rcon_insert_item', "CharID $char_string / ObjectID $object_id / Quantity $quantity / Quality $quality" );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		default:
		
			Livemap::error_redirect('Unknown RCON command');
			
	}
	