<?php
	
	Livemap::$redirect = "index.php?livemap_id=$livemap_id&s=char";
	
	# Check privilege and command
	if( ! $mygroup->privileges['manage_chars'] && ! $mygroup->privileges['manage_adv'] ) Livemap::error_redirect("You don't have permission to access this feature.");
	if( ! isSet($_POST['command']) ) Livemap::error_redirect();

	# Check for input errors
	if( ! isSet($_POST['CharacterID']) || ! is_array($_POST['CharacterID']) || count($_POST['CharacterID']) < 1 ) Livemap::error_redirect("You have to select at least one character from the list.");
	
	# Make sure we have integers as character IDs only
	foreach( $_POST['CharacterID'] AS &$id ) $id = intval($id);
	
	# Make id list from array
	$id_list  = implode(',', $_POST['CharacterID']);
	$id_count = count($_POST['CharacterID']);
	
	$log_end  = '';
	$affected = 0;
	switch($_POST['command']) {
		
		// Ban or Unban
		case 'ban':
		case 'unban':
			$ban = ($_POST['command'] === 'ban');
			$isActive = $ban ? 0 : 1;
			$message  = $ban ? "The accounts of all selected characters were banned." : "The accounts of all selected characters were unbanned.";
			$server->passthru_db_query( "UPDATE `account` a, `character` c SET a.IsActive = $isActive WHERE c.AccountID = a.ID AND c.ID IN ($id_list)" );
		break;
		
		// Enable or Disable
		case 'disable':
		case 'enable':
			$disable = ($_POST['command'] === 'disable');
			$isActive = $disable ? 0 : 1;
			$message  = $disable ? "All selected characters were disabled." : "All selected characters were enabled.";
			$server->passthru_db_query( "UPDATE `character` SET IsActive = $isActive WHERE ID IN ($id_list)" );
		break;
		
		// Make or revoke permanent GM
		case 'takegm':
		case 'makegm':
			if( ! $mygroup->privileges['manage_adv'] ) Livemap::error_redirect();
			$isGM = ($_POST['command'] === 'makegm');
			$message = $isGM ? "All selected characters were promoted to permanent GMs." : "Permanent GM powers of all selected accounts were revoked.";
			$server->passthru_db_query("UPDATE `account` a, `character` c SET a.IsGM = " . intval($isGM) . " WHERE a.ID = c.AccountID AND c.ID IN ($id_list)");
		break;
		
		// Delete chars
		case 'delete':
			if( ! $mygroup->privileges['manage_adv'] ) Livemap::error_redirect();
			foreach( $_POST['CharacterID'] AS $char_id ) if( $server->delete_character($char_id) ) $affected++;
			// Success or error message based on counter
			if( $affected === count($_POST['CharacterID']) ) 
				$message = $affected === 1 ? "The selected character was deleted" : "All $affected selected characters were deleted.";
			elseif( $affected > 0 )
				$message = "Could not delete all characters. $affected characters were deleted.";
			else
				Livemap::error_redirect("Could not delete any selected characters.");
		break;

		// Change alignment
		case 'align':
			if( ! isSet($_POST['new_alignment']) ) Livemap::error_redirect();
			$new_alignment = intval($_POST['new_alignment']);
			if( abs($new_alignment) > 1000 ) Livemap::error_redirect("New alignment is not within the valid range from -1000 to 1000.");
			$new_alignment *= 1000000;
			$server->passthru_db_query( "UPDATE `character` SET Alignment = '$new_alignment' WHERE ID IN ($id_list)" );
			$message = "The alignment of all selected characters was updated.";
			$log_end = " / New Alignment: " . intval($_POST['new_alignment']);
		break;
		
		// Insert item
		case 'item':
			if( ! $mygroup->privileges['manage_adv'] ) Livemap::error_redirect();
			// Input check
			if( ! isSet($_POST['item_id'], $_POST['item_name_id'], $_POST['item_data_type'], $_POST['quantity'], $_POST['quality'], $_POST['durability']) ) Livemap::error_redirect();
			// Sanitize input
			$object_id = $_POST['item_data_type'] === 'name' ? intval($_POST['item_name_id']) : intval($_POST['item_id']);
			$quality = intval($_POST['quality']);
			$quantity = intval($_POST['quantity']);
			$durability = intval($_POST['durability']);
			$region = intval($_POST['region']);
			// Plausibility check
			if( $quantity < 1 || $quantity > 1000 ) Livemap::error_redirect("Invalid Quantity");
			if( $quality < 1 || $quality > 1000 ) Livemap::error_redirect("Invalid Quality");
			if( $object_id < 1 || $object_id > 9999 ) Livemap::error_redirect("Invalid Item ID");
			if( $durability < 1 || $durability > 20000 ) Livemap::error_redirect("Invalid Durability");
			if( ! in_array($region, [0,12,13,14]) ) Livemap::error_redirect("Invalid Durability");
			// Replace 0 region with NULL
			if( ! $region ) $region = 'NULL';
			// Insert items
			foreach( $_POST['CharacterID'] AS $char_id ) {
				$server->passthru_db_query( "SELECT f_insertNewItemInventory(c.RootContainerID, $object_id, $quality, $quantity, $durability, $durability, NULL, 0, NULL, $region, 200000000, 100000000) FROM `character` c WHERE c.ID = '$char_id'" );
			}
			$message = "The item was inserted in all selected characters inventories.";
			$log_end = " / ObjectID $object_id / Quantity $quantity / Quality $quality";
		break;
		
		// Rename
		case 'rename':
			if( ! isSet($_POST['newlastname'], $_POST['newfirstname']) ) Livemap::error_redirect();
			if( $id_count !== 1 ) Livemap::error_redirect();
			$first = $cdb->esc( str_replace('"', '', str_replace("'", "", $_POST['newfirstname'])) );
			$last  = $cdb->esc( str_replace('"', '', str_replace("'", "", $_POST['newlastname'])) );
			// Check string length
			if( strlen($first) < 3 || strlen($last) < 3 ) Livemap::error_redirect("First and last names must have at least 3 characters.");
			if( strlen($first) > 9 ) Livemap::error_redirect("First name is too long. Use a maximum of 9 characters.");
			if( strlen($last) > 15 ) Livemap::error_redirect("Last name is too long. Use a maximum of 15 characters.");
			// Check name in use?
			if( $server->passthru_db_query("SELECT ID FROM `character` WHERE Name = '$first' AND ID != $id_list") ) {
				Livemap::error_redirect("This first name is already in use by another character. First names must be unique.");
			}
			// Rename char
			$server->passthru_db_query( "UPDATE `character` SET Name = '$first', LastName = '$last' WHERE ID = $id_list" );
			// Kick if online
			$online = $server->get_online_players();
			foreach( $online AS $player ) {
				if( intval($id_list) === intval($player['ID']) ) {
					// Load rcon class
					$kick_message = "Your character was renamed. Please reconnect to apply this change. Thank you.";
					require 'includes/rcon.class.php';
					$rcon = new RCON($server);
					$rcon->add_command( 'kick_player', intval($player['ID']), '', $kick_message );
					$rcon->submit();
					Livemap::log_action( 'rcon_kick_player', "CharID {$player['ID']} / Message: $kick_message" );
				}
			}
			$message = "Character ID $id_list was renamed to: $first $last";
			$log_end = " / Renamed to : $first $last";
		break;
		
		// No match: error
		default: 
			Livemap::error_redirect();
		
	}

	# Log action
	$log_subject = $id_count > 1 ? "$id_count Characters" : "CharID $id_list";
	Livemap::log_action("chman_{$_POST['command']}", $log_subject . $log_end);
	
	# Redirect back to char manager
	Livemap::success_redirect($message);
	
?>