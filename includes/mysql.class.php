<?php
	
	class MySQL {
		
		// Connection information properties
		protected $host, $port, $user, $pass, $schema;
		// Status boolean, MySQLi Object and error string
		protected $last_error = '';
		protected $connected = FALSE;
		protected $link = NULL;
		// Configuration
		public $connect_exception = FALSE;
		public $query_exception = TRUE;
		
		// CONSTRUCTOR :: Assign connection info variables to protected properties
		#  @param string -> Username used to connect to SQL host
		#  @param string -> Password for the SQL user
		#  @param string -> Schema/Database name within the SQL host
		#  @param string -> Hostname or IP address of SQL database host, optional
		#  @param int    -> Port of SQL database host, optional
		#  @param string -> Charset, optional
		#  return void
		public function __construct( $user, $pass, $schema, $host = 'localhost', $port = 3306, $switch_charset = FALSE ) {
			$this->host    = $host;
			$this->port    = $port;
			$this->user    = $user;
			$this->pass    = $pass;
			$this->schema  = $schema;
			$this->charset = $switch_charset;
		}
		
		// DESTRUCTOR :: Cleanly disconnect on script exit
		public function __destruct() {
			$this->disconnect();
		}
		
		// SLEEP :: On serialize...
		public function __sleep() {
			$this->disconnect();
			return array( 'host', 'port', 'user', 'pass', 'schema', 'charset' );
		}
		
		# ===========================================================================================================================
		
		// Establish a connection to the database
		public function connect() {
			
			if( $this->connected ) return $this->connected;
			
			$this->link = new mysqli( $this->host, $this->user, $this->pass, $this->schema, $this->port );
			
			if( $this->link->connect_errno ) {
				$this->handle_error('connect');
				$this->connected = FALSE;
			} else {
				$this->connected = TRUE;
				if( $this->charset !== FALSE ) $this->link->set_charset($this->charset);
			}
			
			return $this->connected;
			
		}
		
		// Disconnect from database
		public function disconnect() {

			if( $this->connected ) $this->link->close();
			$this->connected = FALSE;
			
			return TRUE;
			
		}
		
		// Force reconnect
		public function reconnect() {
			
			$this->connected = FALSE;
			$this->connect();
			
		}
		
		// Escape a string
		public function esc( $string ) {
		
			$this->connect();
		
			return $this->link->real_escape_string( $string );
			
		}
		
		// Execute query
		#  @param string -> The actualy SQL query
		#  @param bool   -> Optional parameter: 
		#					TRUE returns all result rows in a multidimensional array.
		#					FALSE returns only the first result as onedimensional array.
		#  return array || array[array] || bool
		public function query( $query, $multi = TRUE ) {

			$this->connect();

			try {
				// Execute Query
				$result = $this->link->query( $query );
				
			// Handle error
			} catch( Exception $e ) {
				$this->handle_error( 'query', $query );
				return FALSE;
			}
			if( ! $result )	{
				$this->handle_error( 'query', $query );
				return FALSE;
			}
			
			// Get query statement type
			$qtype = strtok($query, " \n\t");
			
			// Handle resultset based on query type
			switch( trim($qtype) ) {
				
				case 'SELECT':
					if( $multi ) {
						$output = array();
						while( $row = $result->fetch_assoc() ) $output[] = $row;
					} else {
						$output = $result->fetch_assoc();
					}
					$result->free();
					return $output;
					break;
				
				case 'CALL':
					$output = array();
					while( $this->link->more_results() ) $output[] = $this->link->next_result();
					return $output;
					break;
				
				case 'INSERT':
					return $this->link->insert_id;
					break;
				
				case 'DELETE':
				case 'UPDATE':
					return $this->link->affected_rows;
					break;
				
			}

		}
		
		// Import an SQL dump file
		public function import_file( $filename ) {
			
			$this->connect();
			
			// Get main script path, unless we have an absolute path
			list($scriptPath) = get_included_files();
			$location = $filename[0] === '/' ? $filename : dirname($scriptPath) . '/' . $filename;
			
			// Read file information
			$filesize = filesize($location);
			$commands = file_get_contents($location); 
			
			// If more than 1 MB, split into single queries
			if( $filesize / 1024 / 1024 > 1.0 ) {
				
				// Split into single commands
				$command_array = explode(';', $commands);
				unset($commands);
				// Loop and execute each command
				foreach( $command_array AS $command ) {
					$query = trim($command);
					if( strlen($query) > 0 ) $this->query($query);
				}
				
			// If less than 1 MB, use multi_query method
			} else {

				$result = $this->link->multi_query($commands);
				if( ! $result ) $this->handle_error('query');
				while( $this->link->more_results() ) {
					$lineresult = $this->link->next_result();
					if( ! $lineresult ) $this->handle_error('query');
				}
			
			}
			
			return TRUE;
			
		}
		
		// Checks if a table exists in the database
		public function table_exists( $table ) {

			$this->connect();

			if( $this->link->query("SELECT 1 FROM $table") ) return TRUE;

			return FALSE;
			
		}
		
		// Transaction handling passtru
		public function begin_transaction() {
			
			$this->connect();
			
			$this->link->autocommit( FALSE );

			return TRUE;
			
		}
		public function commit() {
			
			$this->link->commit();
			$this->link->autocommit( TRUE );
			
			return TRUE;
			
		}
		
		// Error handling function
		protected function handle_error( $type, $query = '' ) {
			
			switch( $type ) {
				
				case 'connect':
					$errno   = $this->link->connect_errno;
					$error	 = $this->link->connect_error;
					$message = "Failed to connect to the SQL database! Error $errno: $error";
					if( $this->connect_exception ) {
						throw new Exception($message);
					} else {
						$this->last_error = $message; 
						return TRUE;
					}
					break;
					
				case 'query':
					$errno   = $this->link->errno;
					$error	 = $this->link->error;
					$message = "A MySQL query failed!\nError $errno: $error";
					if( $this->query_exception ) {
						throw new Exception($message);
					} else {
						$this->last_error = $message; 
						return TRUE;
					}
					break;
					
			}

		}

	}

?>