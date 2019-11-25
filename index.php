<?php

	/*
	 *	LiF:YO Livemap
	 *	http://nyuton.net
	 *
	 *	This program is free software: you can redistribute it and/or modify
	 *	it under the terms of the GNU General Public License as published by
	 *	the Free Software Foundation, either version 3 of the License, or
	 *	(at your option) any later version.
	 *
	 *	This program is distributed in the hope that it will be useful,
	 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
	 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 *	GNU General Public License for more details.
	 *
	 *	You should have received a copy of the GNU General Public License
	 *	along with this program.  If not, see <https://www.gnu.org/licenses/>.
	 */

	const VERSION  = '3.1.0';
	const BASE_URL = '/';
	
	require_once('config-dedicated.php');
	require_once('includes/mysql.class.php');

	session_start();
	
	# Fetch config information from session for AJAX requests
	if( isset($_REQUEST['ajax']) && isset($_SESSION['LIVEMAP_INFO']) && array_key_exists($livemap_id, $_SESSION['LIVEMAP_INFO']) ) {
		$config = $_SESSION['LIVEMAP_INFO'][$livemap_id]['CONFIG'];
	}
	
	# Initialize database object. Will auto-connect on first query
	$cdb = new MySQL(MYSQL_USER, MYSQL_PASS, MYSQL_DBSE, MYSQL_HOST, intval(MYSQL_PORT));
	$cdb->connect_exception = TRUE;
	
	# Get livemap information from database if none in session or livemap was updated
	if( ! isset($config) || $config['version'] !== VERSION ) {
	
		try{
			// First connection attempt to database. Check if livemap table exists, otherwise trigger installation
			$cdb->table_exists('nyu_livemap') || require_once('updater.php');
			// Get livemap configuration record
			$config = $cdb->query( "SELECT * FROM nyu_livemap WHERE ID = '$livemap_id'", FALSE );
			// Trigger installation/update page if no record found
			$config || require_once('updater.php');
		// Couldn't connect to database
		} catch( Exception $e ) { 
			die("<b>Failed to connect to the database.</b><br>Error: {$e->getMessage()}");
		}
		
		// Trigger updater if using old version
		version_compare(VERSION, $config['version'], "==") || require_once('updater.php');
		
		// Server facts
		$config['game_ip'] = GAMESERVER_IP;
		$config['game_port'] = intval(GAMESERVER_PORT);
		$config['db_ip']   = MYSQL_HOST;
		$config['db_port'] = intval(MYSQL_PORT);
		$config['db_user'] = MYSQL_USER;
		$config['db_pass'] = MYSQL_PASS;
		$config['db_name'] = MYSQL_DBSE;
		
		// Database table names
		$config['table_c'] = 'nyu_livemap';
		$config['table_g'] = 'nyu_livemap_groups';
		$config['table_s'] = 'nyu_livemap_sessions';
		$config['table_l'] = 'nyu_livemap_log';
		$config['table_d'] = 'nyu_livemap_customdata';
		
		// Append environment details
		$config['path'] = dirname(__FILE__);
		$config['isttmap'] = FALSE;
		$config['server_query'] = strtolower(QUERY_SERVER) === 'yes' ? '1' : '0';
		$config['mapfile_default'] = 'maps/primary.jpg';
		$config['mapfile_alternative'] = 'maps/secondary.jpg';
		$config['admin_pass'] = password_hash(ADMIN_PASS, PASSWORD_DEFAULT);
		
		// Put information into session
		$_SESSION['LIVEMAP_INFO'][$livemap_id]['CONFIG'] = $config;
	}
	
	require_once('main.php');