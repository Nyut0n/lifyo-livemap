<?php

	require 'includes/rcon.class.php';

	Livemap::$redirect = isSet($_POST['schedule']) ? "index.php?livemap_id=$livemap_id&s=rcon#tab-scheduler" : "index.php?livemap_id=$livemap_id&s=rcon";
	
	$message_functions = array( 'centerprint', 'centerprintall', 'bottomprint', 'bottomprintall', 'system_msg', 'system_msg_all', 'local_msg', 'local_msg_all' );
	$server_functions  = array( 'forest_grow', 'crops_grow', 'patch_maint', 'spawn_maint' );
	$interval_units    = array( 'MINUTE', 'HOUR', 'DAY', 'WEEK' );
	
	// Spawn RCON object
	$rcon = new RCON($server);
	
	// Get Character List
	$char_list = isSet($_POST['characters']) ? array_map('intval', explode(',', $_POST['characters'])) : array();
	$char_list = array_diff(array_unique($char_list), array(0));
	$char_string = implode(',', $char_list);
	
	// Check basic RCON privilege
	$mygroup->privileges['rcon'] || Livemap::error_redirect();
	
	// Check command input
	isSet($_POST['command']) || Livemap::error_redirect();
	
	// Check scheduling options
	$schedule = isSet($_POST['schedule']) ? $_POST['schedule'] : "now";
	in_array($schedule, ['now', 'delay', 'repeat']) || Livemap::error_redirect();
	if( $schedule === "delay" || $schedule === "repeat" ) {
		// Check starting date and time
		isSet($_POST['run_hour'], $_POST['run_minute'], $_POST['run_date']) || Livemap::error_redirect();
		$runtime = array(
			'date' => $_POST['run_date'],
			'hour' => intval($_POST['run_hour']),
			'minute' => intval($_POST['run_minute'])
		);
		if( $runtime['hour'] > 23   || $runtime['hour'] < 0   ) Livemap::error_redirect("Invalid time error");
		if( $runtime['minute'] > 59 || $runtime['minute'] < 0 ) Livemap::error_redirect("Invalid time error");
		list($year, $month, $day) = explode("-", $runtime['date']);
		checkdate($month, $day, $year) || Livemap::error_redirect("Invalid date error");
		$rcon->set_time($runtime);
		// Check repeat interval
		if( $schedule === "repeat" ) {
			isSet($_POST['interval_unit'], $_POST['interval_value']) || Livemap::error_redirect();
			$interval_unit = $_POST['interval_unit'];
			$interval_value = intval($_POST['interval_value']);
			in_array($interval_unit, $interval_units) || Livemap::error_redirect();
			if( $interval_value < 0 || $interval_value > 999 ) Livemap::error_redirect();
			$rcon->set_schedule($interval_unit, $interval_value);
		}
	}
	
	$message_ok = $schedule === "now" ? "Command was sent to server and will be executed within the next 20 seconds." : "Task was scheduled successfully.";
	
	switch( $_POST['command'] ) {
		
		# Message all players 
		case 'broadcast':
		
			isSet($_POST['message'], $_POST['message_function']) || Livemap::error_redirect();
		
			$message  = trim( $cdb->esc($_POST['message']) );
			$function = $_POST['message_function'];
			$duration = isSet($_POST['seconds']) ? intval($_POST['seconds']) : 10;
			
			// Check message
			if( strlen($message) < 1 ) Livemap::error_redirect("Message can't be empty");
			// Check function
			if( ! in_array($function, $message_functions) ) Livemap::error_redirect();
			// Check duration
			if( $duration < 5 || $duration > 120  ) Livemap::error_redirect();
			
			$rcon->add_command( $function, $duration, '', $message );
			$rcon->submit();
			
			Livemap::log_action( $schedule === 'now' ? 'rcon_message_all' : 'task_message_all', "($function) $message" );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Trigger a function
		case 'exec_function':
		
			$mygroup->privileges['rcon_advanced'] || Livemap::error_redirect("Insufficient privileges to run this function");
			
			// Check input
			if( ! isSet($_POST['function']) ) Livemap::error_redirect();
			if( ! in_array( $_POST['function'], $server_functions ) ) Livemap::error_redirect('Invalid function called.');
			
			$rcon->add_command( $_POST['function'] );
			$rcon->submit();
			
			Livemap::log_action( $schedule === 'now' ? 'rcon_exec_function' : 'task_exec_function', $_POST['function'] );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Run a custom command
		case 'exec_command':
		
			$mygroup->privileges['rcon_advanced'] || Livemap::error_redirect("Insufficient privileges to run this function");
		
			// Check input
			if( ! isSet($_POST['command_string']) ) Livemap::error_redirect();
			
			$command = $cdb->esc( trim($_POST['command_string']) );
			
			if( strlen($command) < 1 ) Livemap::error_redirect("Command can't be empty");
			
			$rcon->add_command( 'exec_command', '', '', $command );
			$rcon->submit();
			
			Livemap::log_action( $schedule === 'now' ? 'rcon_exec_command' : 'task_exec_command', $command );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Teleport player
		case 'teleport_all':
		
			$geoid = isSet($_POST['geo_id']) ? intval($_POST['geo_id']) : 0;
			$geoid >= 115867648 || Livemap::error_redirect("Invalid GeoID");

			// Send command to server
			$rcon->add_command( 'teleport', "ALL", $geoid, "location" );
			$rcon->submit();

			Livemap::log_action( 'task_teleport', "GeoID $geoid" );
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
				if( ! isSet($_POST['geo_id']) || intval($_POST['geo_id']) < 115867648 ) Livemap::error_redirect("Invalid GeoID");
				$subject = intval($_POST['geo_id']);
				$subject_type = 'location';
				$logdetail = "CharID $char_string to GeoID $subject";
			}

			// Send command to server
			foreach( $char_list AS $char_id ) $rcon->add_command( 'teleport', $char_id, $subject, $subject_type );
			$rcon->submit();

			Livemap::log_action( 'rcon_teleport', $logdetail );
			Livemap::success_redirect($message_ok);
		
		break; # ----------------------------------------------------------------------------------------------------------------
		
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
			foreach( $char_list AS $char_id ) $rcon->add_command( 'kick_player', $char_id, '', $message );
			$rcon->submit();
			
			Livemap::log_action( 'rcon_kick_player', "CharID $char_string / Message: $message" );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Ban player
		case 'ban':
		
			// Check input
			isSet($_POST['bantype']) || Livemap::error_redirect();
			$permaban = ( $_POST['bantype'] === 'permanent' );
			
			if( $permaban ) {
				$message = "You are permanently banned from this server.";
				$duration = 0;
			} else {
				$duration   = intval($_POST['duration']);
				$duration_h = floor($duration/60);
				$duration_m = $duration % 60;
				$message = "You are banned from this server for $duration_h hours and $duration_m minutes.";
			}
			
			$rcon->set_delay($duration);

			// Send commands to server
			foreach( $char_list AS $char_id ) {
				// Ban the account of this character
				$server->ban_player( $char_id );
				// Kick player from the server
				$tempRCON = new RCON($server);
				$tempRCON->add_command( 'kick_player', $char_id, '', $message );
				$tempRCON->submit();
				// Schedule unban if duration was set
				if( ! $permaban ) {
					$rcon->add_command( 'unban_player', $char_id, '', '' );
					Livemap::log_action( 'rcon_ban_player', "CharID $char_id / Duration: $duration s" );
				} else {
					Livemap::log_action( 'rcon_ban_player', "CharID $char_id / Duration: Permanent" );
				}
			}
			$rcon->submit();

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
			foreach( $char_list AS $char_id ) $rcon->add_command( $_POST['function'], $char_id, $duration, $message );
			$rcon->submit();
			
			Livemap::log_action( 'rcon_message_player', "CharID $char_string / Message: $message" );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		# Distribute items
		case 'insert_item':
		case 'insert_item_all':

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
			
			// To individual players ...
			if( $_POST['command'] === 'insert_item' ) {
				foreach( $char_list AS $char_id ) $rcon->add_command( 'insert_item', $char_id, $object_id, "$quantity|$quality|$durability" );
			// Via scheduler
			} else {
				$rcon->add_command( 'insert_item_all', "ALL", $object_id, "$quantity|$quality|$durability" );
			}
			$rcon->submit();
			
			Livemap::log_action( $schedule === 'now' ? 'rcon_insert_item' : 'task_insert_item', "CharID $char_string / ObjectID $object_id / Quantity $quantity / Quality $quality" );
			Livemap::success_redirect($message_ok);
			
		break; # ----------------------------------------------------------------------------------------------------------------
		
		default:
		
			Livemap::error_redirect('Unknown RCON command');
			
	}
	