<?php

  const MYSQL_HOST = '127.0.0.1';		# LiF MariaDB server address
  const MYSQL_PORT = '3306';			# LiF MariaDB server port ... 3306 is default
  const MYSQL_USER = 'root';			# LiF MariaDB username
  const MYSQL_PASS = 'password';		# LiF MariaDB password
  const MYSQL_DBSE = 'lif_1';			# LiF MariaDB database name
	
  const QUERY_SERVER = 'yes';			# Use Steamworks API to query the server. Set to 'no' for private servers!
  const GAMESERVER_IP = '123.45.67.89';	# Gameserver IP address or hostname
  const GAMESERVER_PORT = '28000';		# Gameserver Port
	
  const ADMIN_PASS = 'change_me';		# Admin password used to login and configure the livemap.

  $livemap_id = 1;
  # Change this to a different number if you are running multiple livemaps on the same domain.
  # Each map must have a different ID.