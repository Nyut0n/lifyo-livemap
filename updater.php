<?php

	defined('VERSION') || die;
	isset($_POST['ajax']) && die;
	
	if( isset($_POST['update'], $_POST['admin_pw']) && $_POST['update'] ) {
		
		if( $_POST['admin_pw'] !== ADMIN_PASS ) {
			
			$error = "Wrong password";
			
		} else {
			
			$current_version = isset($config) && isset($config['version']) ? $config['version'] : "2.7.2";
			
			// 3.0.0 base installation
			if( version_compare($current_version, "3.0.0", "<") ) {
				// Cleanup old Livemap installations
				$cdb->query( "DROP TABLE IF EXISTS nyu_livemap, nyu_livemap_groups, nyu_livemap_log, nyu_livemap_sessions, nyu_livemap_customdata" );
				// Table setup
				$cdb->query( "CREATE TABLE `nyu_livemap` (
					`version` VARCHAR(10) NOT NULL DEFAULT '3.0.0',
					`ID` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 1,
					`title` VARCHAR(60) NOT NULL DEFAULT 'New Livemap',
					`homepage` VARCHAR(255) NOT NULL DEFAULT '',
					`discord` VARCHAR(255) NOT NULL DEFAULT '',
					`teamspeak` VARCHAR(100) NOT NULL DEFAULT '',
					`language` CHAR(2) NOT NULL DEFAULT 'en',
					`timezone` VARCHAR(40) NOT NULL DEFAULT 'Europe/London',
					`daycycle` FLOAT(3,1) UNSIGNED NOT NULL DEFAULT '3.0',
					`restarts` VARCHAR(60) NOT NULL DEFAULT '',
					`rules` MEDIUMTEXT,
					`alt_map` TINYINT(1) NOT NULL DEFAULT '0',
					`file_revision` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '1',
					`guildmanager` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
					`color_bg` CHAR(6) NOT NULL DEFAULT '001B36',
					`color_claim_t1` CHAR(6) NOT NULL DEFAULT 'FFFF00',
					`color_claim_t2` CHAR(6) NOT NULL DEFAULT 'FFFF00',
					`color_claim_t3` CHAR(6) NOT NULL DEFAULT 'FFFF00',
					`color_claim_t4` CHAR(6) NOT NULL DEFAULT 'FFFF00',
					`color_label` CHAR(6) NOT NULL DEFAULT 'FFFFFF',
					`style_claim` VARCHAR(10) NOT NULL DEFAULT 'solid',
					`style_tooltip` VARCHAR(10) NOT NULL DEFAULT 'standard',
					`width_claim` TINYINT(1) UNSIGNED NOT NULL DEFAULT '2',
					`font_claimlabel` VARCHAR(255) NOT NULL DEFAULT 'Georgia',
					`font_claimdetail` VARCHAR(255) NOT NULL DEFAULT 'Georgia',
					`geo_cache_version` INT(10) NOT NULL DEFAULT '0',
					PRIMARY KEY (`ID`) )" );
				$cdb->query( "CREATE TABLE `nyu_livemap_groups` (
					`ID` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
					`livemap_id` MEDIUMINT(8) UNSIGNED NOT NULL,
					`type_id` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0 = Custom, 1 = Anonymous, 2 = GMs',
					`name` VARCHAR(50) NULL,
					`password` VARCHAR(64) NULL,
					`steamlogin` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
					`privileges` INT(10) UNSIGNED NOT NULL DEFAULT '0',
					`members_csv` TEXT,
					PRIMARY KEY (`ID`) )" );
				$cdb->query( "CREATE TABLE `nyu_livemap_sessions` (
					`ID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					`SteamID` BIGINT(20) UNSIGNED NOT NULL,
					`Token` CHAR(32) NOT NULL,
					`Expires` DATETIME NOT NULL,
					PRIMARY KEY (`ID`) )" );
				$cdb->query( "CREATE TABLE `nyu_livemap_customdata` (
					`ID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					`livemap_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
					`data_type` VARCHAR(20) NOT NULL DEFAULT '',
					`data_json` TEXT NOT NULL,
					PRIMARY KEY (`ID`) )" );
				$cdb->query( "CREATE TABLE `nyu_livemap_log` (
					`ID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					`livemap_id` MEDIUMINT(8) UNSIGNED NOT NULL,
					`user_group` VARCHAR(50) NOT NULL,
					`action` VARCHAR(30) NOT NULL,
					`detail` VARCHAR(255) NOT NULL,
					`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (`ID`) )" );
				// Create default records
				$timezone = date_default_timezone_get();
				$cdb->query( "INSERT INTO nyu_livemap (ID, timezone) VALUES ('$livemap_id', '$timezone')" );
				$cdb->query( "INSERT INTO nyu_livemap_groups (livemap_id, type_id, name, privileges) VALUES ('$livemap_id', 1, 'Anonymous visitor', 3051183), ('$livemap_id', 2, 'Game Masters', 3137535)" );
			}
			
			// 3.1.0 update
			if( version_compare($current_version, "3.1.0", "<") ) {
				$cdb->query( "ALTER TABLE `nyu_livemap` ADD COLUMN `admin_steam` BIGINT UNSIGNED NULL AFTER `ID`" );
				$cdb->query( "ALTER TABLE `nyu_livemap` ADD COLUMN `pri_map` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `rules`" );
			}
			
			// Finally, update version column in livemap table
			$cdb->query( "UPDATE nyu_livemap SET version = '" . VERSION . "' WHERE ID = '$livemap_id'" );
			
			header("Location: index.php");
			die;
			
		}
		
	}
		
	require_once('includes/template.class.php');
	$html = new Template('html/updater.html');
	
	// Test gameserver query connection
	try {
		$queryport = GAMESERVER_PORT + 2;
		$socket = @fsockopen("udp://" . GAMESERVER_IP, GAMESERVER_PORT + 2);
		stream_set_timeout($socket, 2);
		stream_set_blocking($socket, TRUE);
		fwrite($socket, "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00");
		$response = fread($socket, 4096);
		@fclose($socket);
		$query_ok = (bool)$response;
	} catch( Exception $e ) {
		$query_ok = FALSE;
	}
	
	$arch_ok = (PHP_INT_SIZE > 4 || extension_loaded('gmp'));
	$lif_ok  = $cdb->table_exists('outposts');
	$php_ok  = version_compare(PHP_VERSION, "5.5.0", ">=");
	$xml_ok  = extension_loaded('xml');
	
	$html->assign('PHP_VERSION', PHP_VERSION)
	->assign('ARCH64', PHP_INT_SIZE > 4)
	->assign('ARCH32', PHP_INT_SIZE === 4 && ! extension_loaded('gmp'))
	->assign('ARCH32OK', PHP_INT_SIZE === 4 && extension_loaded('gmp'))
	->assign('LIF_OK', $lif_ok)
	->assign('GQ_ENABLE', strtolower(QUERY_SERVER) === 'yes')
	->assign('GQ_OK', $query_ok)
	->assign('XML_OK', $xml_ok)
	->assign('PHP_OK', $php_ok)
	->assign('REQ_OK', $arch_ok && $lif_ok && $php_ok && $xml_ok)
	->assign('SHOW_ERROR', isset($error))
	->assign('ERROR', isset($error) ? $error : '');
	
	print $html->parse();
	
	die;